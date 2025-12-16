<?php
/**
 * Public hooks for Related Posts.
 *
 * @package Airygen\Modules\RelatedPosts\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\RelatedPosts\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\RelatedPosts\Admin\Settings;

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
		require_once __DIR__ . '/../TemplateTags.php';
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
		add_shortcode( 'airygen_related_posts', array( __CLASS__, 'render_shortcode' ) );
		add_filter( 'the_content', array( __CLASS__, 'inject_to_content' ), 45 );
	}

	/**
	 * Enqueue frontend styles before content rendering starts.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() || is_feed() ) {
			return;
		}
		if ( ! ModuleSettings::is_enabled( 'relatedPosts' ) || ! ModuleSettings::is_enabled( 'linkSuggestions' ) ) {
			return;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) && empty( $settings['auto_inject_enabled'] ) ) {
			return;
		}

		Renderer::enqueue_styles( $settings );
	}

	/**
	 * Render shortcode.
	 *
	 * @return string
	 */
	public static function render_shortcode(): string {
		if ( ! is_singular() ) {
			return '';
		}

		$post_id = get_the_ID();
		if ( ! is_int( $post_id ) || $post_id <= 0 ) {
			return '';
		}

		return self::render_for_post( $post_id );
	}

	/**
	 * Inject related posts into content.
	 *
	 * @param string $content Post content.
	 *
	 * @return string
	 */
	public static function inject_to_content( string $content ): string {
		static $is_injecting = false;

		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( $is_injecting ) {
			return $content;
		}
		if ( has_shortcode( $content, 'airygen_related_posts' ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! is_int( $post_id ) || $post_id <= 0 ) {
			return $content;
		}
		$queried_object_id = get_queried_object_id();
		if ( ! is_int( $queried_object_id ) || $queried_object_id <= 0 || $queried_object_id !== $post_id ) {
			return $content;
		}

		$settings = Settings::get();
		if ( ! self::can_render_auto( $settings, $post_id ) ) {
			return $content;
		}

		$is_injecting = true;
		try {
			$html = Renderer::render( $post_id, $settings );
		} finally {
			$is_injecting = false;
		}
		if ( '' === $html ) {
			return $content;
		}

		$position = isset( $settings['insert_position'] ) ? (string) $settings['insert_position'] : 'after_content';
		if ( 'before_content' === $position ) {
			return $html . $content;
		}

		return $content . $html;
	}

	/**
	 * Render related block for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private static function render_for_post( int $post_id ): string {
		static $is_rendering = false;

		// Guard against recursive rendering. Renderer::render() may call get_the_excerpt(),
		// and wp_trim_excerpt() can apply the_content internally, which re-enters
		// this module's output path and can cause an infinite loop/OOM without this check.
		if ( $is_rendering ) {
			return '';
		}

		$settings = Settings::get();
		if ( ! self::can_render_manual( $settings, $post_id ) ) {
			return '';
		}

		$is_rendering = true;
		try {
			return Renderer::render( $post_id, $settings );
		} finally {
			$is_rendering = false;
		}
	}

	/**
	 * Render related posts for template tag usage.
	 *
	 * @param int $post_id Optional target post ID.
	 *
	 * @return string
	 */
	public static function render_for_template( int $post_id = 0 ): string {
		$target_post_id = $post_id > 0 ? $post_id : get_the_ID();
		if ( ! is_int( $target_post_id ) || $target_post_id <= 0 ) {
			return '';
		}

		return self::render_for_post( $target_post_id );
	}

	/**
	 * @param array<string,mixed> $settings Settings.
	 * @param int                 $post_id Post ID.
	 *
	 * @return bool
	 */
	private static function can_render_base( array $settings, int $post_id ): bool {
		if ( ! ModuleSettings::is_enabled( 'relatedPosts' ) ) {
			return false;
		}
		if ( ! ModuleSettings::is_enabled( 'linkSuggestions' ) ) {
			return false;
		}

		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return false;
		}

		$enabled_post_types = isset( $settings['enabled_post_types'] ) && is_array( $settings['enabled_post_types'] )
		? array_values( array_filter( array_map( 'strval', $settings['enabled_post_types'] ) ) )
		: array( 'post' );

		return in_array( $post_type, $enabled_post_types, true );
	}

	/**
	 * Manual output gate (template tag/shortcode).
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param int                 $post_id Post ID.
	 *
	 * @return bool
	 */
	private static function can_render_manual( array $settings, int $post_id ): bool {
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		return self::can_render_base( $settings, $post_id );
	}

	/**
	 * Auto inject gate.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param int                 $post_id Post ID.
	 *
	 * @return bool
	 */
	private static function can_render_auto( array $settings, int $post_id ): bool {
		if ( empty( $settings['auto_inject_enabled'] ) ) {
			return false;
		}

		return self::can_render_base( $settings, $post_id );
	}
}
