<?php
/**
 * Environment helpers.
 *
 * @package Airygen\Support\Utils
 */

declare(strict_types=1);

namespace Airygen\Support\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function str_contains;
use function rest_get_url_prefix;
use function wp_is_json_request;

/**
 * Utility functions for environment detection.
 */
final class Env {
	/**
	 * Detect a REST API request context.
	 *
	 * @return bool
	 */
	public static function is_rest_request(): bool {
		if ( defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' ) ) {
			return true;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}

		if ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';

		if ( '' === $request_uri || ! function_exists( 'rest_get_url_prefix' ) ) {
			return false;
		}

		$rest_path = '/' . trim( rest_get_url_prefix(), '/' ) . '/';

		return str_contains( $request_uri, $rest_path );
	}

	/**
	 * Detect WP-CLI context to ensure admin hooks (e.g. save_post) are wired.
	 *
	 * @return bool
	 */
	public static function is_cli(): bool {
		return defined( 'WP_CLI' ) && constant( 'WP_CLI' );
	}

	/**
	 * Determine whether the current context is admin, REST, or CLI.
	 *
	 * @return bool
	 */
	public static function is_admin_context(): bool {
		return is_admin() || self::is_rest_request() || self::is_cli();
	}
}
