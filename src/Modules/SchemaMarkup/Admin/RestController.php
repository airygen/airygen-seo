<?php
/**
 * REST controller for Schema Markup previews.
 *
 * @package Airygen\Modules\SchemaMarkup\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SchemaMarkup\Admin\Settings;
use Airygen\Modules\SchemaMarkup\Domain\Service\BuildJsonLd;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\Article;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\Organization;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\Website;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes a JSON-LD preview endpoint for the editor.
 */
final class RestController {

	/**
	 * Permission callback for preview.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_preview( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post' );

		if ( $post_id <= 0 ) {
			return new WP_Error( ErrorCodes::AIRYGEN_INVALID_POST, __( 'Invalid post identifier.', 'airygen-seo' ), array( 'status' => 400 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( ErrorCodes::AIRYGEN_FORBIDDEN, __( 'You are not allowed to preview this post.', 'airygen-seo' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Handle schema preview requests.
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_preview( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post' );

		$product_preview = self::build_woocommerce_product_preview( $post_id );
		if ( null !== $product_preview ) {
			return rest_ensure_response(
				array(
					'post_id' => $post_id,
					'jsonld'  => $product_preview,
				)
			);
		}

		$options    = Settings::get();
		$site_name  = get_bloginfo( 'name' );
		$site_desc  = get_bloginfo( 'description' );
		$site_url   = home_url( '/' );
		$locale     = get_locale();
		$visibility = self::resolve_visibility( $options );

		$organization_context = null;
		if ( $visibility['organization'] || $visibility['article'] ) {
			$organization_context = Organization::build( $options, $site_name, $site_url );
		}

		$website_context = $visibility['website']
		? Website::build( $site_name, $site_url, $locale )
		: null;

		$article_context = null;
		if ( $visibility['article'] && $organization_context ) {
			$article_context = Article::from_post_id(
				$post_id,
				$options,
				$site_name,
				$site_desc,
				$organization_context
			);
		}

		$context = array(
			'organization' => ( $visibility['organization'] && $organization_context ) ? $organization_context->to_array() : array(),
			'website'      => ( $visibility['website'] && $website_context ) ? $website_context->to_array() : array(),
			'breadcrumb'   => array(),
			'article'      => ( $visibility['article'] && $article_context ) ? $article_context->to_array() : array(),
		);

		$payload = BuildJsonLd::from_context( $context );

		return rest_ensure_response(
			array(
				'post_id' => $post_id,
				'jsonld'  => $payload,
			)
		);
	}

	/**
	 * Build WooCommerce native schema preview for product posts.
	 *
	 * @param int $post_id Post identifier.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function build_woocommerce_product_preview( int $post_id ): ?array {
		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) || 'product' !== $post_type ) {
			return null;
		}

		if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'WC' ) ) {
			return null;
		}

		/** @disregard P1010 */
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return null;
		}

		/** @disregard P1010 */
		$woocommerce = WC();
		if ( ! is_object( $woocommerce ) || ! isset( $woocommerce->structured_data ) || ! is_object( $woocommerce->structured_data ) ) {
			return null;
		}

		$structured_data = $woocommerce->structured_data;
		if ( method_exists( $structured_data, 'set_data' ) ) {
			$structured_data->set_data( array(), true );
		}

		$product_markup = null;
		$capture_filter = static function ( $markup ) use ( &$product_markup ) {
			if ( is_array( $markup ) ) {
				$product_markup = $markup;
			}
			return $markup;
		};
		add_filter( 'woocommerce_structured_data_product', $capture_filter, 999, 1 );

		if ( method_exists( $structured_data, 'generate_product_data' ) ) {
			$structured_data->generate_product_data( $product );
		}

		remove_filter( 'woocommerce_structured_data_product', $capture_filter, 999 );

		if ( is_array( $product_markup ) && ! empty( $product_markup ) ) {
			if ( ! isset( $product_markup['@type'] ) || ! is_string( $product_markup['@type'] ) || '' === trim( $product_markup['@type'] ) ) {
				$product_markup['@type'] = 'Product';
			}

			if ( ! isset( $product_markup['url'] ) || ! is_string( $product_markup['url'] ) || '' === trim( $product_markup['url'] ) ) {
				$permalink = get_permalink( $post_id );
				if ( is_string( $permalink ) && '' !== trim( $permalink ) ) {
					$product_markup['url'] = $permalink;
				}
			}

			return array_merge(
				array(
					'@context' => 'https://schema.org/',
				),
				$product_markup
			);
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array(),
		);
	}

	/**
	 * Resolve visibility configuration from options.
	 *
	 * @param array<string, mixed> $options Schema options.
	 *
	 * @return array<string, bool>
	 */
	private static function resolve_visibility( array $options ): array {
		$defaults = array(
			'organization' => false,
			'website'      => false,
			'breadcrumb'   => false,
			'article'      => false,
		);

		if ( empty( $options['visibility'] ) || ! is_array( $options['visibility'] ) ) {
			return $defaults;
		}

		return array(
			'organization' => ! empty( $options['visibility']['organization'] ),
			'website'      => ! empty( $options['visibility']['website'] ),
			'breadcrumb'   => ! empty( $options['visibility']['breadcrumb'] ),
			'article'      => ! empty( $options['visibility']['article'] ),
		);
	}
}
