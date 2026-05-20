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
				/**
				 * The Code Snippet Manager intentionally allows site administrators
				 * to inject custom <script> tags (including ones with src=) into the
				 * page. Snippets are validated by Settings::sanitize_snippet() at save
				 * time: the wrapper must be exactly <script ...>...</script> and the
				 * inner body must not contain any other HTML tag. Escaping the body
				 * here would defeat the feature's purpose — administrators expect
				 * their script tag to be emitted verbatim. Saving the snippets option
				 * is gated by the manage_options capability via the standard
				 * Settings API permission flow.
				 */
				echo $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- See block comment above; admin-supplied <script> snippet, validated at save time.
			} else {
				wp_print_inline_script_tag( $code );
			}
		}
	}
}
