<?php
/**
 * Public hooks for Code Snippets.
 *
 * @package Airygen\Modules\CodeSnippetManager\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\CodeSnippetManager\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\CodeSnippetManager\Admin\Settings;

/**
 * Registers public runtime hooks.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_head', array( __CLASS__, 'emit_head' ), 20 );
		add_action( 'wp_body_open', array( __CLASS__, 'emit_body_open' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'emit_footer' ), 20 );
	}

	/**
	 * Emit head snippets.
	 *
	 * @return void
	 */
	public static function emit_head(): void {
		if ( is_admin() || is_feed() ) {
			return;
		}

		self::emit_snippets( 'head' );
	}

	/**
	 * Emit body-open snippets.
	 *
	 * @return void
	 */
	public static function emit_body_open(): void {
		self::emit_snippets( 'body' );
	}

	/**
	 * Emit footer snippets.
	 *
	 * @return void
	 */
	public static function emit_footer(): void {
		self::emit_snippets( 'footer' );
	}

	/**
	 * Output each enabled snippet for a given placement individually.
	 *
	 * Snippets containing {@html <script>} tags are output as-is. Raw inline
	 * JavaScript without wrapping tags is wrapped via wp_print_inline_script_tag().
	 *
	 * @param string $placement Target placement (head, body, footer).
	 *
	 * @return void
	 */
	private static function emit_snippets( string $placement ): void {
		$settings = Settings::get();
		$snippets = isset( $settings['snippets'] ) && is_array( $settings['snippets'] )
		? $settings['snippets']
		: array();

		foreach ( $snippets as $snippet ) {
			if ( ! is_array( $snippet ) || empty( $snippet['enabled'] ) ) {
				continue;
			}

			$zone = isset( $snippet['placement'] ) ? (string) $snippet['placement'] : '';
			if ( $zone !== $placement ) {
				continue;
			}

			$code = isset( $snippet['code'] ) ? trim( (string) $snippet['code'] ) : '';
			if ( '' === $code ) {
				continue;
			}

			if ( preg_match( '#<\s*script\b#i', $code ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized at save time by Settings::sanitize_snippet().
				echo $code . "\n";
			} else {
				wp_print_inline_script_tag( $code );
			}
		}
	}
}
