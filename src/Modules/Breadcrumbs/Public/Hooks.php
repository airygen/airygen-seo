<?php
/**
 * Registers public hooks for breadcrumbs.
 *
 * @package Airygen\Modules\Breadcrumbs\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Breadcrumbs\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\Breadcrumbs\Admin\Settings;

/**
 * Entry point for breadcrumb public hooks.
 */
final class Hooks {

	/**
	 * Register hooks when the module is enabled.
	 *
	 * @return void
	 */
	public static function register(): void {
		Settings::ensure_exists();
		require_once __DIR__ . '/../TemplateTags.php';

		add_action(
			'init',
			static function (): void {
				add_shortcode( 'airygen_breadcrumbs', array( __CLASS__, 'render_shortcode' ) );
			}
		);
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_filter( 'the_content', array( __CLASS__, 'inject_to_content' ), 45 );

		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				if ( ! ModuleSettings::is_enabled( 'breadcrumbs' ) ) {
					return;
				}

				StyleEmitter::output();
			}
		);

		add_action(
			'wp',
			static function (): void {
				if ( ! ModuleSettings::is_enabled( 'breadcrumbs' ) ) {
					TrailStore::prime( null );
					return;
				}

				TrailStore::prime( TrailBuilder::from_current_query() );
			},
			20
		);
	}

	/**
	 * Register Breadcrumb block.
	 *
	 * @return void
	 */
	public static function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'airygen/breadcrumb',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Handle the [airygen_breadcrumbs] shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode( array $atts ): string {
		$args = shortcode_atts(
			array(
				'separator'   => null,
				'wrap_before' => null,
				'wrap_after'  => null,
				'before'      => null,
				'after'       => null,
				'prefix'      => null,
				'link_last'   => null,
			),
			$atts
		);

		$overrides = array_filter(
			$args,
			static function ( $value ): bool {
				return null !== $value && '' !== $value;
			}
		);

		return TrailRenderer::render_current( $overrides );
	}

	/**
	 * Render callback for the airygen/breadcrumb block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( array $attributes = array() ): string {
		return self::render_shortcode( $attributes );
	}

	/**
	 * Inject breadcrumbs before or after main content.
	 *
	 * @param string $content Post content.
	 *
	 * @return string
	 */
	public static function inject_to_content( string $content ): string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( has_shortcode( $content, 'airygen_breadcrumbs' ) ) {
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
		if ( empty( $settings['auto_injection_enabled'] ) ) {
			return $content;
		}

		$html = TrailRenderer::render_current( array(), false );
		if ( '' === $html ) {
			return $content;
		}

		$position = isset( $settings['injection_position'] ) ? (string) $settings['injection_position'] : 'before_content';
		if ( 'after_content' === $position ) {
			return $content . $html;
		}

		return $html . $content;
	}
}
