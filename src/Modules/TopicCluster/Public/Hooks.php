<?php
/**
 * Public hooks for Topic Cluster.
 *
 * @package Airygen\Modules\TopicCluster\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\TopicCluster\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\TopicCluster\Admin\Settings;

/**
 * Registers Topic Cluster front-end outputs.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		Settings::ensure_exists();
		require_once __DIR__ . '/../TemplateTags.php';
		add_action(
			'init',
			static function (): void {
				add_shortcode( 'airygen_topic_cluster', array( __CLASS__, 'render_shortcode' ) );
			}
		);
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_filter( 'the_content', array( __CLASS__, 'inject_to_content' ), 45 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'print_styles' ) );
	}

	/**
	 * Register block.
	 *
	 * @return void
	 */
	public static function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'airygen/topic-cluster',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Render shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public static function render_shortcode( array $atts = array() ): string {
		$post_id = isset( $atts['post_id'] ) ? (int) $atts['post_id'] : 0;
		return self::render_for_template( $post_id );
	}

	/**
	 * Render block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return string
	 */
	public static function render_block( array $attributes = array() ): string {
		return self::render_shortcode( $attributes );
	}

	/**
	 * Render for template tags.
	 *
	 * @param int $post_id Optional post ID.
	 *
	 * @return string
	 */
	public static function render_for_template( int $post_id = 0 ): string {
		$target_post_id = $post_id > 0 ? $post_id : get_the_ID();
		if ( ! is_int( $target_post_id ) || $target_post_id <= 0 ) {
			return '';
		}

		$settings = Settings::get();
		if ( ! self::can_render_manual( $settings, $target_post_id ) ) {
			return '';
		}

		return Renderer::render( $target_post_id );
	}

	/**
	 * Inject Topic Cluster output into post content.
	 *
	 * @param string $content Post content.
	 *
	 * @return string
	 */
	public static function inject_to_content( string $content ): string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( has_shortcode( $content, 'airygen_topic_cluster' ) ) {
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

		$html = Renderer::render( $post_id );
		if ( '' === $html ) {
			return $content;
		}

		$position = isset( $settings['insert_position'] ) ? (string) $settings['insert_position'] : 'after-content';
		if ( 'before-content' === $position ) {
			return $html . $content;
		}

		return $content . $html;
	}

	/**
	 * Print front-end styles for Topic Cluster output.
	 *
	 * @return void
	 */
	public static function print_styles(): void {
		if ( ! ModuleSettings::is_enabled( 'topicCluster' ) ) {
			return;
		}

		$settings = Settings::get();
		$css      = Renderer::build_css( $settings );
		if ( '' === $css ) {
			return;
		}

		wp_register_style( 'airygen-topic-cluster-styles', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- No external file.
		wp_enqueue_style( 'airygen-topic-cluster-styles' );
		wp_add_inline_style( 'airygen-topic-cluster-styles', $css );
	}

	/**
	 * Base render gate.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param int                  $post_id Post ID.
	 *
	 * @return bool
	 */
	private static function can_render_base( array $settings, int $post_id ): bool {
		if ( ! ModuleSettings::is_enabled( 'topicCluster' ) ) {
			return false;
		}

		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return false;
		}

		$enabled_post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
		? array_values( array_filter( array_map( 'strval', $settings['post_types'] ) ) )
		: array( 'post' );

		return in_array( $post_type, $enabled_post_types, true );
	}

	/**
	 * Manual gate.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param int                  $post_id Post ID.
	 *
	 * @return bool
	 */
	private static function can_render_manual( array $settings, int $post_id ): bool {
		if ( empty( $settings['manual_output_enabled'] ) ) {
			return false;
		}

		return self::can_render_base( $settings, $post_id );
	}

	/**
	 * Auto gate.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param int                  $post_id Post ID.
	 *
	 * @return bool
	 */
	private static function can_render_auto( array $settings, int $post_id ): bool {
		if ( empty( $settings['auto_injection_enabled'] ) ) {
			return false;
		}

		return self::can_render_base( $settings, $post_id );
	}
}
