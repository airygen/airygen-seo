<?php
/**
 * Product template rendering for WooCommerce SEO.
 *
 * @package Airygen\Modules\WooCommerceSeo\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\WooCommerceSeo\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders SEO templates using product context tokens.
 */
final class RenderProductTemplate {

	/**
	 * Render a template with product tokens.
	 *
	 * @param string               $template Template string.
	 * @param array<string, mixed> $context  Product context.
	 *
	 * @return string
	 */
	public static function render( string $template, array $context ): string {
		if ( '' === trim( $template ) ) {
			return '';
		}

		$separator = self::string( $context, 'separator' );
		if ( '' !== $separator ) {
			$separator = ' ' . $separator . ' ';
		}

		$replacements = array(
			'%product_name%'  => self::string( $context, 'product_name' ),
			'%sku%'           => self::string( $context, 'sku' ),
			'%price%'         => self::string( $context, 'price' ),
			'%min_price%'     => self::string( $context, 'min_price' ),
			'%max_price%'     => self::string( $context, 'max_price' ),
			'%currency%'      => self::string( $context, 'currency' ),
			'%stock_status%'  => self::string( $context, 'stock_status' ),
			'%brand%'         => self::string( $context, 'brand' ),
			'%category_name%' => self::string( $context, 'category_name' ),
			'%site_name%'     => self::string( $context, 'site_name' ),
			'%separator%'     => $separator,
			'%custom_1%'      => self::string( $context, 'custom_1' ),
			'%custom_2%'      => self::string( $context, 'custom_2' ),
			'%custom_3%'      => self::string( $context, 'custom_3' ),
		);

		$rendered = strtr( $template, $replacements );
		$rendered = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( (string) $rendered ) ) );

		if ( ! is_string( $rendered ) ) {
			return '';
		}

		return trim( $rendered );
	}

	/**
	 * Read normalized string from context.
	 *
	 * @param array<string, mixed> $context Context values.
	 * @param string               $key     Context key.
	 *
	 * @return string
	 */
	private static function string( array $context, string $key ): string {
		if ( ! array_key_exists( $key, $context ) ) {
			return '';
		}

		$value = $context[ $key ];
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return trim( (string) $value );
	}
}
