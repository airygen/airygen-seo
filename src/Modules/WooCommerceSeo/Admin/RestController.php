<?php
/**
 * REST controller for WooCommerce schema previews.
 *
 * @package Airygen\Modules\WooCommerceSeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\WooCommerceSeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\WooCommerceSeo\Public\ProductMetaResolver;
use Airygen\Support\Errors\ErrorCodes;
use Airygen\Support\Meta\PostData;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes WooCommerce preview helpers for admin settings UI.
 */
final class RestController {

	/**
	 * Permission callback.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return bool|WP_Error
	 */
	public static function can_preview( WP_REST_Request $request ) {
		unset( $request );

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_FORBIDDEN,
				__( 'You are not allowed to preview WooCommerce schema.', 'airygen-seo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle preview requests.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_preview( WP_REST_Request $request ) {
		if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'WC' ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_WOOCOMMERCE_UNAVAILABLE,
				__( 'WooCommerce is not available.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$search     = trim( (string) $request->get_param( 'q' ) );
		$product_id = (int) $request->get_param( 'product' );

		$products = self::search_products( $search );
		if ( empty( $products ) ) {
			return rest_ensure_response(
				array(
					'products'          => array(),
					'selectedProductId' => 0,
					'head'              => array(
						'title'       => '',
						'description' => '',
						'canonical'   => '',
					),
					'schema'            => array(
						'@context' => 'https://schema.org',
						'@graph'   => array(),
					),
				)
			);
		}

		if ( $product_id <= 0 || ! isset( $products[ $product_id ] ) ) {
			$product_ids = array_keys( $products );
			$product_id  = (int) $product_ids[0];
		}

		$head   = self::build_head_sample( $product_id );
		$schema = self::build_schema_sample( $product_id );

		return rest_ensure_response(
			array(
				'products'          => array_values( $products ),
				'selectedProductId' => $product_id,
				'head'              => $head,
				'schema'            => $schema,
			)
		);
	}

	/**
	 * Search products by title.
	 *
	 * @param string $search Search term.
	 * @return array<int, array{id:int,title:string}>
	 */
	private static function search_products( string $search ): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
				's'              => $search,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $query->posts ) || ! is_array( $query->posts ) ) {
			return array();
		}

		$products = array();
		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				continue;
			}

			$title = get_the_title( $post_id );
			if ( ! is_string( $title ) || '' === trim( $title ) ) {
				$title = '#' . (string) $post_id;
			}

			$products[ $post_id ] = array(
				'id'    => $post_id,
				'title' => $title,
			);
		}

		return $products;
	}

	/**
	 * Build the current head sample values for selected product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, string>
	 */
	private static function build_head_sample( int $product_id ): array {
		$post_data = PostData::get( $product_id );
		$payload   = array(
			'meta_title'       => $post_data['title'],
			'meta_description' => $post_data['description'],
			'post_title'       => (string) get_the_title( $product_id ),
			'post_excerpt'     => (string) get_post_field( 'post_excerpt', $product_id ),
			'permalink'        => (string) get_permalink( $product_id ),
			'canonical'        => $post_data['canonical'],
			'robots'           => $post_data['robots'],
		);

		$resolved = ProductMetaResolver::filter_onpage_meta_payload( $payload, $product_id );

		$title = '';
		if ( isset( $resolved['meta_title'] ) && is_string( $resolved['meta_title'] ) ) {
			$title = trim( $resolved['meta_title'] );
		}
		if ( '' === $title && isset( $resolved['post_title'] ) && is_string( $resolved['post_title'] ) ) {
			$title = trim( $resolved['post_title'] );
		}

		$description = '';
		if ( isset( $resolved['meta_description'] ) && is_string( $resolved['meta_description'] ) ) {
			$description = trim( $resolved['meta_description'] );
		}

		$canonical = '';
		if ( isset( $resolved['canonical'] ) && is_string( $resolved['canonical'] ) ) {
			$canonical = trim( $resolved['canonical'] );
		}
		if ( '' === $canonical && isset( $resolved['permalink'] ) && is_string( $resolved['permalink'] ) ) {
			$canonical = trim( $resolved['permalink'] );
		}

		return array(
			'title'       => $title,
			'description' => $description,
			'canonical'   => $canonical,
		);
	}

	/**
	 * Build WooCommerce native schema sample from selected product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	private static function build_schema_sample( int $product_id ): array {
		if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'WC' ) ) {
			return array(
				'@context' => 'https://schema.org',
				'@graph'   => array(),
			);
		}

		// for passing phpstan type checks.
		$product = call_user_func( 'wc_get_product', $product_id );
		if ( ! $product ) {
			return array(
				'@context' => 'https://schema.org',
				'@graph'   => array(),
			);
		}

		$woocommerce = call_user_func( 'WC' );
		if ( ! is_object( $woocommerce ) || ! isset( $woocommerce->structured_data ) || ! is_object( $woocommerce->structured_data ) ) {
			return array(
				'@context' => 'https://schema.org',
				'@graph'   => array(),
			);
		}

		$structured_data = $woocommerce->structured_data;
		if ( method_exists( $structured_data, 'set_data' ) ) {
			$structured_data->set_data( array(), true );
		}

		if ( method_exists( $structured_data, 'generate_product_data' ) ) {
			$structured_data->generate_product_data( $product );
		}

		$raw = array();
		if ( method_exists( $structured_data, 'get_structured_data' ) ) {
			$raw = $structured_data->get_structured_data( array( 'product', 'review', 'breadcrumblist', 'website' ) );
		}

		$graph = self::normalize_graph_nodes( $raw );

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	/**
	 * Normalize Woo structured payload into flat graph nodes.
	 *
	 * @param mixed $raw Raw structured data.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_graph_nodes( $raw ): array {
		$graph = array();
		if ( ! is_array( $raw ) ) {
			return $graph;
		}

		foreach ( $raw as $entry ) {
			if ( is_array( $entry ) && self::is_assoc( $entry ) && isset( $entry['@type'] ) ) {
				$graph[] = $entry;
				continue;
			}

			if ( is_array( $entry ) ) {
				foreach ( $entry as $node ) {
					if ( is_array( $node ) && isset( $node['@type'] ) ) {
						$graph[] = $node;
					}
				}
			}
		}

		return $graph;
	}

	/**
	 * Check whether array is associative.
	 *
	 * @param array<mixed> $value Input.
	 * @return bool
	 */
	private static function is_assoc( array $value ): bool {
		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}
}
