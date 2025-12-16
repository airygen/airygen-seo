<?php
/**
 * Convenience helpers for working with plugin options.
 *
 * @package Airygen\Support
 */

declare(strict_types=1);

namespace Airygen\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Lightweight wrapper around WordPress option access for Airygen SEO settings.
 */
final class Options {

	/**
	 * Retrieve a value from the plugin options array.
	 *
	 * @param string $key     Option key within the Airygen SEO settings.
	 * @param mixed  $fallback Default value when the key is missing.
	 *
	 * @return mixed
	 */
	public static function get( string $key, $fallback = '' ) {
		$options = get_option( Constants::SETTING_NAME );

		if ( is_array( $options ) && array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		return $fallback;
	}

	/**
	 * Build the field name used when submitting plugin options.
	 *
	 * @param string $key Option key.
	 *
	 * @return string
	 */
	public static function field_name( string $key ): string {
		return sprintf( '%s[%s]', Constants::SETTING_NAME, $key );
	}
}
