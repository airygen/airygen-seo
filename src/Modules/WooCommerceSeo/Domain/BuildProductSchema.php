<?php
/**
 * Builds Product schema node from normalized context.
 *
 * @package Airygen\Modules\WooCommerceSeo\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\WooCommerceSeo\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles Product JSON-LD payload.
 */
final class BuildProductSchema {

	/**
	 * Build a Product schema node.
	 *
	 * @param array<string, mixed> $context Product context.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function build( array $context ): ?array {
		$name = self::string( $context, 'name' );
		$url  = self::string( $context, 'url' );

		if ( '' === $name || '' === $url ) {
			return null;
		}

		$node = array(
			'@type' => 'Product',
			'name'  => $name,
			'url'   => $url,
		);

		$description = self::string( $context, 'description' );
		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$image = self::string( $context, 'image' );
		if ( '' !== $image ) {
			$node['image'] = $image;
		}

		$sku = self::string( $context, 'sku' );
		if ( '' !== $sku ) {
			$node['sku'] = $sku;
		}

		$brand = self::string( $context, 'brand' );
		if ( '' !== $brand ) {
			$node['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		$offer = self::build_offer( $context );
		if ( null !== $offer ) {
			$node['offers'] = $offer;
		}

		return $node;
	}

	/**
	 * Build offer node.
	 *
	 * @param array<string, mixed> $context Product context.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function build_offer( array $context ): ?array {
		$currency = self::string( $context, 'currency' );
		$url      = self::string( $context, 'url' );
		$type     = self::string( $context, 'offer_type' );

		if ( '' === $currency || '' === $url ) {
			return null;
		}

		$availability = self::availability_uri( self::string( $context, 'stock_status' ) );

		if ( 'aggregate' === $type ) {
			$min_price = self::string( $context, 'min_price' );
			$max_price = self::string( $context, 'max_price' );
			if ( '' === $min_price || '' === $max_price ) {
				return null;
			}

			return array(
				'@type'         => 'AggregateOffer',
				'priceCurrency' => $currency,
				'lowPrice'      => $min_price,
				'highPrice'     => $max_price,
				'offerCount'    => self::int_or_null( $context, 'offer_count' ),
				'availability'  => $availability,
				'url'           => $url,
			);
		}

		$price = self::string( $context, 'price' );
		if ( '' === $price ) {
			return null;
		}

		return array(
			'@type'         => 'Offer',
			'priceCurrency' => $currency,
			'price'         => $price,
			'availability'  => $availability,
			'url'           => $url,
		);
	}

	/**
	 * Map stock status to schema URI.
	 *
	 * @param string $stock_status Stock status.
	 *
	 * @return string
	 */
	private static function availability_uri( string $stock_status ): string {
		if ( 'outofstock' === $stock_status ) {
			return 'https://schema.org/OutOfStock';
		}

		if ( 'onbackorder' === $stock_status ) {
			return 'https://schema.org/BackOrder';
		}

		return 'https://schema.org/InStock';
	}

	/**
	 * Read scalar string from context.
	 *
	 * @param array<string, mixed> $context Context values.
	 * @param string               $key     Key.
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

	/**
	 * Read positive integer from context.
	 *
	 * @param array<string, mixed> $context Context values.
	 * @param string               $key     Key.
	 *
	 * @return int|null
	 */
	private static function int_or_null( array $context, string $key ): ?int {
		if ( ! array_key_exists( $key, $context ) ) {
			return null;
		}

		$value = $context[ $key ];
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$number = (int) $value;
		if ( $number < 1 ) {
			return null;
		}

		return $number;
	}
}
