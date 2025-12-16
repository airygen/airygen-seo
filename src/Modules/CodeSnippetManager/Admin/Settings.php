<?php
/**
 * Stores configuration for Code Snippets output.
 *
 * @package Airygen\Modules\CodeSnippetManager\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\CodeSnippetManager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists admin-configurable Code Snippets settings.
 */
final class Settings {

	private const OPTION = Constants::OPTION_CODE_SNIPPET_MANAGER;

	/**
	 * Ensure the option exists with defaults.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', 'no' );
			return;
		}

		self::update( (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Retrieve sanitized configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Persist sanitized settings.
	 *
	 * @param array<string, mixed> $value Raw option payload.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize input values against defaults.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize( $value ): array {
		$config    = self::defaults();
		$sanitized = is_array( $value ) ? $value : array();

		$config['snippets'] = self::sanitize_snippets(
			$sanitized['snippets'] ?? $config['snippets']
		);

		return $config;
	}

	/**
	 * Default configuration.
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults(): array {
		return array(
			'snippets' => array(),
		);
	}

	/**
	 * Sanitize a code snippet.
	 *
	 * Only {@html <script>} tags (including remote src) and raw inline
	 * JavaScript are accepted. All other HTML tags are rejected.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_snippet( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$snippet = trim( (string) $value );
		if ( '' === $snippet ) {
			return '';
		}

		// Strip script wrappers to inspect the inner content.
		$inner = preg_replace( '#^\s*<script\b[^>]*>#i', '', $snippet );
		$inner = is_string( $inner ) ? $inner : '';
		$inner = preg_replace( '#</script>\s*$#i', '', $inner );
		$inner = is_string( $inner ) ? trim( $inner ) : '';

		// Reject if any non-script HTML tag remains.
		if ( preg_match( '#<\s*/?\s*(?!script\b)\w+\b#i', $inner ) ) {
			return '';
		}

		return mb_substr( $snippet, 0, 20000 );
	}

	/**
	 * Sanitize snippet cards.
	 *
	 * @param mixed $value Raw snippets.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function sanitize_snippets( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$snippets = array();
		foreach ( $value as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id_raw = isset( $item['id'] ) ? (string) $item['id'] : '';
			$id     = sanitize_key( $id_raw );
			if ( '' === $id ) {
				$id = 'snippet_' . (string) ( (int) $index + 1 );
			}

			$enabled     = ! empty( $item['enabled'] );
			$description = isset( $item['description'] ) ? sanitize_text_field( (string) $item['description'] ) : '';
			$code        = self::sanitize_snippet( $item['code'] ?? '' );
			$placement   = isset( $item['placement'] ) ? sanitize_key( (string) $item['placement'] ) : 'inactive';
			if ( ! in_array( $placement, array( 'head', 'body', 'footer', 'inactive' ), true ) ) {
				$placement = 'inactive';
			}

			if ( '' === trim( $code ) ) {
				continue;
			}

			$snippets[] = array(
				'id'          => $id,
				'enabled'     => $enabled,
				'description' => $description,
				'code'        => $code,
				'placement'   => $placement,
			);
		}

		return array_values( $snippets );
	}
}
