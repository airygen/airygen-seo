<?php
/**
 * Serves the IndexNow key file dynamically.
 *
 * @package Airygen\Modules\InstantIndexing\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Runtime;

use Airygen\Modules\InstantIndexing\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles dynamic responses for {key}.txt.
 */
final class KeyResponder {

	/**
	 * Output the key file when the current request matches the configured location.
	 *
	 * @return void
	 */
	public function maybe_output(): void {
		$settings = Settings::get();
		$key      = isset( $settings['key'] ) ? (string) $settings['key'] : '';

		if ( '' === $key ) {
			return;
		}

		$target   = Settings::key_location( $settings );
		$expected = wp_parse_url( $target, PHP_URL_PATH );
		if ( ! $expected ) {
			$expected = '/' . $key . '.txt';
		}

		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		$request_path = $this->normalize_path( $request_uri );

		if ( $request_path !== $this->normalize_path( $expected ) ) {
			return;
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo $key; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw key required.
		exit;
	}

	/**
	 * Normalize a URL/path string for comparison.
	 *
	 * @param string $path Requested path.
	 * @return string
	 */
	private function normalize_path( string $path ): string {
		$parsed = wp_parse_url( $path, PHP_URL_PATH );
		$parsed = is_string( $parsed ) ? $parsed : $path;
		$parsed = '/' . ltrim( $parsed, '/' );
		return untrailingslashit( $parsed );
	}
}
