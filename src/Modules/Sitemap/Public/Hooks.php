<?php
/**
 * Public hooks for sitemap feature.
 *
 * @package Airygen\Modules\Sitemap\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Sitemap\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers public runtime integrations.
 */
final class Hooks {

	/**
	 * Register sitemap hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_filter( 'redirect_canonical', array( __CLASS__, 'prevent_canonical_redirect' ), 10, 2 );
		Routes::register();
		Controller::register();
	}

	/**
	 * Prevent WordPress from redirecting /sitemap.xml to /wp-sitemap.xml.
	 *
	 * @param string|false $redirect_url Proposed redirect target.
	 * @param string       $requested_url Current requested URL.
	 * @return string|false
	 */
	public static function prevent_canonical_redirect( $redirect_url, $requested_url ) {
		if ( empty( $redirect_url ) ) {
			return $redirect_url;
		}

		$requested_path = wp_parse_url( $requested_url, PHP_URL_PATH );
		$redirect_path  = wp_parse_url( $redirect_url, PHP_URL_PATH );

		if ( '/' !== substr( $requested_path, 0, 1 ) ) {
			$requested_path = '/' . $requested_path;
		}

		if ( '/' !== substr( $redirect_path, 0, 1 ) ) {
			$redirect_path = '/' . $redirect_path;
		}

		if ( '/wp-sitemap.xml' === $redirect_path && '/sitemap.xml' === $requested_path ) {
			return false;
		}

		return $redirect_url;
	}
}
