<?php
/**
 * Emits Product JSON-LD for WooCommerce product pages.
 *
 * @package Airygen\Modules\WooCommerceSeo\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\WooCommerceSeo\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\WooCommerceSeo\Admin\Settings;
use Airygen\Modules\WooCommerceSeo\Domain\BuildProductSchema;
use Airygen\Support\Debug\Logger;

/**
 * Renders product schema graph on wp_head.
 */
final class ProductSchemaEmitter {

	/**
	 * Append Product node into shared schema payload.
	 *
	 * @param array<string, mixed> $payload Existing JSON-LD payload.
	 * @param array<string, mixed> $context Resolved schema context.
	 *
	 * @return array<string, mixed>
	 */
	public static function append_to_payload( array $payload, array $context ): array {
		unset( $context );

		$node = self::build_product_node();
		if ( null === $node ) {
			return $payload;
		}

		if ( empty( $payload ) ) {
			return array(
				'@context' => 'https://schema.org',
				'@graph'   => array( $node ),
			);
		}

		$graph = array();
		if ( isset( $payload['@graph'] ) && is_array( $payload['@graph'] ) ) {
			$graph = $payload['@graph'];
		}

		$graph[]           = $node;
		$payload['@graph'] = $graph;
		if ( ! isset( $payload['@context'] ) ) {
			$payload['@context'] = 'https://schema.org';
		}

		return $payload;
	}

	/**
	 * Build Product node when current request is eligible.
	 *
	 * @return void
	 */
	private static function build_product_node(): ?array {
		if ( ! is_singular( 'product' ) ) {
			Logger::log(
				'woocommerce-schema',
				array(
					'message' => 'skip: not product singular',
					'queried' => get_queried_object_id(),
				)
			);
			return null;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			Logger::log( 'woocommerce-schema', array( 'message' => 'skip: wc_get_product missing' ) );
			return null;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			Logger::log( 'woocommerce-schema', array( 'message' => 'skip: module disabled in woo settings' ) );
			return null;
		}

		$post_id = get_queried_object_id();
		if ( ! is_int( $post_id ) || $post_id < 1 ) {
			Logger::log(
				'woocommerce-schema',
				array(
					'message' => 'skip: invalid queried object id',
					'post_id' => $post_id,
				)
			);
			return null;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			Logger::log(
				'woocommerce-schema',
				array(
					'message' => 'skip: wc_get_product returned null',
					'post_id' => $post_id,
				)
			);
			return null;
		}

		$context = self::build_context( $post_id, $product, $settings );
		$node    = BuildProductSchema::build( $context );

		if ( null === $node ) {
			Logger::log(
				'woocommerce-schema',
				array(
					'message' => 'skip: schema node build returned null',
					'post_id' => $post_id,
					'name'    => $context['name'] ?? '',
					'url'     => $context['url'] ?? '',
				)
			);
			return null;
		}

		Logger::log(
			'woocommerce-schema',
			array(
				'message' => 'emitted product schema',
				'post_id' => $post_id,
			)
		);

		return $node;
	}

	/**
	 * Build normalized schema context.
	 *
	 * @param int                  $post_id  Product ID.
	 * @param \WC_Product          $product  Product object.
	 * @param array<string, mixed> $settings Woo settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_context( int $post_id, $product, array $settings ): array {
		$product_class = is_object( $product ) ? get_class( $product ) : gettype( $product );
		$product_id    = method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;
		$product_type  = method_exists( $product, 'get_type' ) ? (string) $product->get_type() : '';
		$product_name  = method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';

		Logger::log(
			'woocommerce-schema',
			array(
				'message'          => 'build_context product object',
				'post_id'          => $post_id,
				'product_class'    => $product_class,
				'is_wc_product'    => is_a( $product, 'WC_Product' ),
				'product_id'       => $product_id,
				'product_type'     => $product_type,
				'product_raw_name' => $product_name,
			)
		);

		$currency = '';
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$currency = (string) get_woocommerce_currency();
		}

		$offer_type  = 'offer';
		$price       = (string) $product->get_price();
		$min_price   = '';
		$max_price   = '';
		$offer_count = null;

		if ( $product->is_type( 'variable' ) ) {
			$prices      = $product->get_variation_prices( true );
			$values      = isset( $prices['price'] ) && is_array( $prices['price'] ) ? array_values( $prices['price'] ) : array();
			$offer_count = count( $values );
			if ( ! empty( $values ) ) {
				$offer_type = 'aggregate';
				$min_price  = (string) min( $values );
				$max_price  = (string) max( $values );
			}
		}

		$context = array(
			'name'         => self::product_name( $post_id, $product ),
			'description'  => self::product_description( $post_id, $product ),
			'url'          => get_permalink( $post_id ),
			'image'        => self::image_url( $post_id, $product ),
			'sku'          => (string) $product->get_sku(),
			'brand'        => ProductMetaResolver::brand_name( $post_id, $settings ),
			'currency'     => $currency,
			'offer_type'   => $offer_type,
			'price'        => $price,
			'min_price'    => $min_price,
			'max_price'    => $max_price,
			'offer_count'  => $offer_count,
			'stock_status' => (string) $product->get_stock_status(),
		);

		Logger::log(
			'woocommerce-schema',
			array(
				'message'      => 'build_context',
				'post_id'      => $post_id,
				'name'         => (string) ( $context['name'] ?? '' ),
				'url'          => (string) ( $context['url'] ?? '' ),
				'image'        => (string) ( $context['image'] ?? '' ),
				'sku'          => (string) ( $context['sku'] ?? '' ),
				'brand'        => (string) ( $context['brand'] ?? '' ),
				'currency'     => (string) ( $context['currency'] ?? '' ),
				'offer_type'   => (string) ( $context['offer_type'] ?? '' ),
				'price'        => (string) ( $context['price'] ?? '' ),
				'min_price'    => (string) ( $context['min_price'] ?? '' ),
				'max_price'    => (string) ( $context['max_price'] ?? '' ),
				'offer_count'  => (string) ( $context['offer_count'] ?? '' ),
				'stock_status' => (string) ( $context['stock_status'] ?? '' ),
			)
		);

		return $context;
	}

	/**
	 * Resolve product name with fallback.
	 *
	 * @param int         $post_id Product ID.
	 * @param \WC_Product $product Product object.
	 *
	 * @return string
	 */
	private static function product_name( int $post_id, $product ): string {
		$name = '';
		if ( method_exists( $product, 'get_name' ) ) {
			$name = trim( (string) $product->get_name() );
		}

		if ( '' !== $name ) {
			return $name;
		}

		if ( method_exists( $product, 'get_title' ) ) {
			$name = trim( (string) $product->get_title() );
			if ( '' !== $name ) {
				return $name;
			}
		}

		$post_title = get_post_field( 'post_title', $post_id );
		if ( is_string( $post_title ) && '' !== trim( $post_title ) ) {
			return trim( $post_title );
		}

		$wp_title = get_the_title( $post_id );
		if ( is_string( $wp_title ) && '' !== trim( $wp_title ) ) {
			return trim( $wp_title );
		}

		if ( method_exists( $product, 'get_slug' ) ) {
			$slug = trim( (string) $product->get_slug() );
			if ( '' !== $slug ) {
				return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
			}
		}

		return 'Product';
	}

	/**
	 * Resolve product description.
	 *
	 * @param int         $post_id Product ID.
	 * @param \WC_Product $product Product object.
	 *
	 * @return string
	 */
	private static function product_description( int $post_id, $product ): string {
		$excerpt = get_post_field( 'post_excerpt', $post_id );
		if ( is_string( $excerpt ) && '' !== trim( $excerpt ) ) {
			return trim( wp_strip_all_tags( $excerpt ) );
		}

		$short = $product->get_short_description();
		if ( is_string( $short ) && '' !== trim( $short ) ) {
			return trim( wp_strip_all_tags( $short ) );
		}

		$content = get_post_field( 'post_content', $post_id );
		return trim( wp_strip_all_tags( (string) $content ) );
	}

	/**
	 * Resolve image URL.
	 *
	 * @param int         $post_id Product ID.
	 * @param \WC_Product $product Product object.
	 *
	 * @return string
	 */
	private static function image_url( int $post_id, $product ): string {
		$image_id = (int) $product->get_image_id();
		if ( $image_id > 0 ) {
			$image = wp_get_attachment_url( $image_id );
			if ( is_string( $image ) ) {
				return $image;
			}
		}

		$fallback = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( is_string( $fallback ) ) {
			return $fallback;
		}

		return '';
	}
}
