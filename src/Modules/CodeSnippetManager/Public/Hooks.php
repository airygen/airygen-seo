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
	 * Snippets containing {@html <script>} tags are normalized to WordPress
	 * script enqueue/inline helpers instead of echoing the saved tag directly.
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

		foreach ( $snippets as $index => $snippet ) {
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
				self::emit_script_tag_snippet( $code, $placement, (int) $index );
			} else {
				self::emit_inline_snippet( $code, $placement, (int) $index );
			}
		}
	}

	/**
	 * Emit a snippet saved as a complete script tag.
	 *
	 * @param string $code      Saved snippet code.
	 * @param string $placement Target placement.
	 * @param int    $index     Snippet index for a stable handle.
	 *
	 * @return void
	 */
	private static function emit_script_tag_snippet( string $code, string $placement, int $index ): void {
		if ( ! preg_match( '#^\s*<script\b(?P<attrs>[^>]*)>(?P<body>.*?)</script>\s*$#is', $code, $matches ) ) {
			return;
		}

		$attributes = self::parse_script_attributes( (string) $matches['attrs'] );
		$body       = trim( (string) $matches['body'] );

		if ( isset( $attributes['src'] ) ) {
			self::emit_external_snippet( (string) $attributes['src'], $attributes, $placement, $index, $code );
			return;
		}

		self::emit_inline_snippet( $body, $placement, $index );
	}

	/**
	 * Emit an external script through WordPress' script loader.
	 *
	 * @param string               $src        Script URL.
	 * @param array<string,string> $attributes Parsed script attributes.
	 * @param string               $placement  Target placement.
	 * @param int                  $index      Snippet index for a stable handle.
	 * @param string               $code       Original saved snippet code.
	 *
	 * @return void
	 */
	private static function emit_external_snippet( string $src, array $attributes, string $placement, int $index, string $code ): void {
		$src = esc_url_raw( $src );
		if ( '' === $src ) {
			return;
		}

		$strategy = null;
		if ( array_key_exists( 'async', $attributes ) ) {
			$strategy = 'async';
		} elseif ( array_key_exists( 'defer', $attributes ) ) {
			$strategy = 'defer';
		}

		$args = array(
			'in_footer' => 'footer' === $placement,
		);
		if ( is_string( $strategy ) ) {
			$args['strategy'] = $strategy;
		}

		$handle = self::handle( $placement, $index, $code );
		wp_register_script( $handle, $src, array(), null, $args ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Admin-managed third-party snippet URL.
		wp_enqueue_script( $handle );
		wp_print_scripts( array( $handle ) );
	}

	/**
	 * Emit an inline script through WordPress' script loader.
	 *
	 * @param string $code      JavaScript source.
	 * @param string $placement Target placement.
	 * @param int    $index     Snippet index for a stable handle.
	 *
	 * @return void
	 */
	private static function emit_inline_snippet( string $code, string $placement, int $index ): void {
		$code = trim( $code );
		if ( '' === $code ) {
			return;
		}

		$handle = self::handle( $placement, $index, $code );
		wp_register_script( $handle, false, array(), null, false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline snippet has no external asset version.
		wp_enqueue_script( $handle );
		// Intentionally unescaped: this is an administrator-authored custom code snippet that
		// must execute verbatim, so escaping would break it. The trust boundary is enforced
		// at save time, where storing snippet code requires the `unfiltered_html` capability
		// (see Airygen\Admin\RestController::handle_update, line 148); the value here can only have been
		// written by a user with that capability.
		wp_add_inline_script( $handle, $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-authored snippet gated by `unfiltered_html` on save.
		wp_print_scripts( array( $handle ) );
	}

	/**
	 * Parse relevant script tag attributes.
	 *
	 * @param string $raw Raw attribute string.
	 *
	 * @return array<string,string>
	 */
	private static function parse_script_attributes( string $raw ): array {
		$attributes = array();
		if ( '' === trim( $raw ) ) {
			return $attributes;
		}

		if ( preg_match_all( '#([A-Za-z][A-Za-z0-9:_-]*)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?#', $raw, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$name = strtolower( sanitize_key( (string) $match[1] ) );
				if ( '' === $name ) {
					continue;
				}

				$value = '';
				if ( isset( $match[2] ) ) {
					$value = trim( (string) $match[2], "\"'" );
				}

				$attributes[ $name ] = sanitize_text_field( $value );
			}
		}

		return $attributes;
	}

	/**
	 * Build a script handle for one snippet.
	 *
	 * @param string $placement Target placement.
	 * @param int    $index     Snippet index.
	 * @param string $code      Snippet code.
	 *
	 * @return string
	 */
	private static function handle( string $placement, int $index, string $code ): string {
		return sprintf(
			'airygen-code-snippet-%s-%d-%s',
			sanitize_key( $placement ),
			$index,
			substr( hash( 'sha256', $code ), 0, 12 )
		);
	}
}
