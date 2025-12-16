<?php
/**
 * Applies redirects based on configured rules.
 *
 * @package Airygen\Modules\Redirects\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Redirects\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Redirects\Admin\Settings;
use Airygen\Modules\Redirects\Domain\Service\ResolveRedirect;

/**
 * Applies redirects during template_redirect.
 */
final class ApplyRedirects {

	/**
	 * Register hook.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 0 );
	}

	/**
	 * Maybe apply a redirect.
	 */
	public static function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		Settings::ensure_exists();
		$payload = Settings::get_rules();
		$rules   = $payload['rules'] ?? array();

		if ( empty( $rules ) ) {
			self::log_404_if_needed();
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		$match = ResolveRedirect::from_path( $request_uri, $rules );
		if ( $match ) {
			$target  = self::resolve_target_url( $match->get_target() );
			$current = self::current_url();

			if ( $target && $target !== $current ) {
				wp_safe_redirect( $target, $match->get_status(), 'Airygen SEO Redirects' );
				exit;
			}
		}

		self::log_404_if_needed();
	}

	/**
	 * Resolve target URL, supporting relative paths.
	 *
	 * @param string $target Target value.
	 *
	 * @return string
	 */
	private static function resolve_target_url( string $target ): string {
		$target = trim( $target );
		if ( '' === $target ) {
			return '';
		}

		if ( 0 === strpos( $target, 'http://' ) || 0 === strpos( $target, 'https://' ) ) {
			return $target;
		}

		return home_url( $target );
	}

	/**
	 * Get current URL for loop prevention.
	 *
	 * @return string
	 */
	private static function current_url(): string {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) ) : (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '/';

		return $scheme . $host . $uri;
	}

	/**
	 * Log 404s when applicable.
	 */
	private static function log_404_if_needed(): void {
		if ( is_404() ) {
			$path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
			Logger::log_404( $path );
		}
	}
}
