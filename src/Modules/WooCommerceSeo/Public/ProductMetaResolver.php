<?php
/**
 * Resolves product metadata overrides for On-Page SEO payload.
 *
 * @package Airygen\Modules\WooCommerceSeo\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\WooCommerceSeo\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\WooCommerceSeo\Admin\Settings;
use Airygen\Modules\WooCommerceSeo\Domain\RenderProductTemplate;
use Airygen\Support\Meta\PostData;

/**
 * Applies product template fallbacks when post-level meta is empty.
 */
final class ProductMetaResolver {

	/**
	 * Filter On-Page payload before domain render.
	 *
	 * @param array<string, mixed> $meta    On-Page payload.
	 * @param int                  $post_id Current post ID.
	 *
	 * @return array<string, mixed>
	 */
	public static function filter_onpage_meta_payload( array $meta, int $post_id ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $meta;
		}

		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) || 'product' !== $post_type ) {
			return $meta;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return $meta;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return $meta;
		}

		$context = self::build_template_context( $post_id, $product, $settings );
		$title   = self::render_product_template( $settings, $context, 'title' );
		$desc    = self::render_product_template( $settings, $context, 'description' );

		$meta_title = isset( $meta['meta_title'] ) ? trim( (string) $meta['meta_title'] ) : '';
		if ( '' === $meta_title && '' !== $title ) {
			$meta['meta_title'] = $title;
		}

		$meta_desc = isset( $meta['meta_description'] ) ? trim( (string) $meta['meta_description'] ) : '';
		if ( '' === $meta_desc && '' !== $desc ) {
			$meta['meta_description'] = $desc;
		}

		$canonical = PostData::get_field( $post_id, 'canonical' );
		if ( Constants::CANONICAL_NONE_TOKEN !== $canonical && '' === trim( $canonical ) ) {
			$permalink = get_permalink( $post_id );
			if ( is_string( $permalink ) && '' !== $permalink ) {
				$meta['canonical'] = $permalink;
			}
		}

		return $meta;
	}

	/**
	 * Render one template field.
	 *
	 * @param array<string, mixed> $settings Woo settings.
	 * @param array<string, mixed> $context  Template context.
	 * @param string               $field    Field name.
	 *
	 * @return string
	 */
	private static function render_product_template( array $settings, array $context, string $field ): string {
		if ( ! isset( $settings['templates']['product'][ $field ] ) || ! is_string( $settings['templates']['product'][ $field ] ) ) {
			return '';
		}

		return RenderProductTemplate::render( $settings['templates']['product'][ $field ], $context );
	}

	/**
	 * Build template token context.
	 *
	 * @param int                  $post_id  Product post ID.
	 * @param \WC_Product          $product  Product object.
	 * @param array<string, mixed> $settings Woo settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_template_context( int $post_id, $product, array $settings ): array {
		$currency = '';
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$currency = (string) get_woocommerce_currency();
		}
		$price = (string) $product->get_price();
		$min   = '';
		$max   = '';
		if ( $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( true );
			$values = isset( $prices['price'] ) && is_array( $prices['price'] ) ? array_values( $prices['price'] ) : array();
			if ( ! empty( $values ) ) {
				$min = (string) min( $values );
				$max = (string) max( $values );
			}
		}

		$separator = '–';
		if ( isset( $settings['templates']['separator'] ) && is_string( $settings['templates']['separator'] ) && '' !== trim( $settings['templates']['separator'] ) ) {
			$separator = trim( $settings['templates']['separator'] );
		}

		$custom = isset( $settings['templates']['custom_tokens'] ) && is_array( $settings['templates']['custom_tokens'] )
		? $settings['templates']['custom_tokens']
		: array();

		return array(
			'product_name'  => $product->get_name(),
			'sku'           => (string) $product->get_sku(),
			'price'         => $price,
			'min_price'     => $min,
			'max_price'     => $max,
			'currency'      => $currency,
			'stock_status'  => (string) $product->get_stock_status(),
			'brand'         => self::brand_name( $post_id, $settings ),
			'category_name' => self::first_category_name( $post_id ),
			'site_name'     => get_bloginfo( 'name' ),
			'separator'     => $separator,
			'custom_1'      => isset( $custom['custom_1'] ) ? (string) $custom['custom_1'] : '',
			'custom_2'      => isset( $custom['custom_2'] ) ? (string) $custom['custom_2'] : '',
			'custom_3'      => isset( $custom['custom_3'] ) ? (string) $custom['custom_3'] : '',
		);
	}

	/**
	 * Resolve first category name.
	 *
	 * @param int $post_id Product post ID.
	 *
	 * @return string
	 */
	private static function first_category_name( int $post_id ): string {
		$terms = get_the_terms( $post_id, 'product_cat' );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}

		$first = reset( $terms );
		if ( $first instanceof \WP_Term ) {
			return (string) $first->name;
		}

		return '';
	}

	/**
	 * Resolve configured brand term name.
	 *
	 * @param int                  $post_id  Product post ID.
	 * @param array<string, mixed> $settings Woo settings.
	 *
	 * @return string
	 */
	public static function brand_name( int $post_id, array $settings ): string {
		$brand_tax = 'product_brand';
		if ( isset( $settings['brand_attribute'] ) && is_string( $settings['brand_attribute'] ) && '' !== trim( $settings['brand_attribute'] ) ) {
			$brand_tax = sanitize_key( $settings['brand_attribute'] );
		}

		$terms = get_the_terms( $post_id, $brand_tax );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}

		$first = reset( $terms );
		if ( $first instanceof \WP_Term ) {
			return (string) $first->name;
		}

		return '';
	}
}
