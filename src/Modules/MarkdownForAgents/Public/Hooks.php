<?php
/**
 * Public hooks for Markdown for Agents.
 *
 * @package Airygen\Modules\MarkdownForAgents\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\MarkdownForAgents\Public;

use Airygen\Modules\MarkdownForAgents\Admin\Settings;
use Airygen\Modules\MarkdownForAgents\Application\MarkdownExporter;
use Airygen\Modules\MarkdownForAgents\Infrastructure\MarkdownPostRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles markdown response negotiation.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_template_redirect' ), 0 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'prevent_md_redirect' ), 10, 2 );
	}

	/**
	 * Prevent canonical redirect for markdown requests.
	 *
	 * @param string|false $redirect_url Redirect URL.
	 * @param string       $requested_url Requested URL.
	 *
	 * @return string|false
	 */
	public static function prevent_md_redirect( $redirect_url, string $requested_url ) {
		unset( $requested_url );
		if ( self::is_markdown_request() ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Handle markdown output.
	 *
	 * @return void
	 */
	public static function handle_template_redirect(): void {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( self::is_markdown_request() ) {
			self::render_post_markdown( $settings );
		}
	}

	/**
	 * Detect markdown content negotiation.
	 *
	 * @return bool
	 */
	private static function is_markdown_request(): bool {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}

		$format_value = filter_input( INPUT_GET, 'format', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$format       = is_string( $format_value ) ? sanitize_key( $format_value ) : '';
		if ( 'md' === $format || 'markdown' === $format ) {
			return true;
		}

		$accept_raw = filter_input( INPUT_SERVER, 'HTTP_ACCEPT', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$accept     = is_string( $accept_raw ) ? strtolower( sanitize_text_field( $accept_raw ) ) : '';
		return false !== strpos( $accept, 'text/markdown' );
	}

	/**
	 * Render markdown for current singular.
	 *
	 * @param array<string,mixed> $settings Module settings.
	 *
	 * @return void
	 */
	private static function render_post_markdown( array $settings ): void {
		$post_id = get_queried_object_id();
		if ( $post_id <= 0 ) {
			return;
		}

		$allowed_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
		? array_values( array_filter( array_map( 'strval', $settings['post_types'] ) ) )
		: array();
		$post_type     = get_post_type( $post_id );
		if ( ! is_string( $post_type ) || ! in_array( $post_type, $allowed_types, true ) ) {
			return;
		}

		$repo    = new MarkdownPostRepository();
		$cached  = $repo->get_by_post_id( $post_id );
		$content = '';
		if ( is_array( $cached ) && empty( $cached['is_deleted'] ) && ! empty( $cached['markdown_content'] ) ) {
			$content = (string) $cached['markdown_content'];
		} else {
			$payload = MarkdownExporter::export( $post_id, $settings );
			if ( is_array( $payload ) ) {
				$repo->upsert( $payload );
				$content = (string) $payload['markdown_content'];
			}
		}

		if ( '' === $content ) {
			return;
		}

		nocache_headers();
		header( 'Vary: Accept' );
		header( 'Content-Type: text/markdown; charset=UTF-8' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markdown/plain-text response.
		exit;
	}
}
