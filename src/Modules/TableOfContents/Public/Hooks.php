<?php
/**
 * Registers public hooks for Table of Contents.
 *
 * @package Airygen\Modules\TableOfContents\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\TableOfContents\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\TableOfContents\Admin\Settings;
use Airygen\Modules\TableOfContents\Block;
use Airygen\Support\Meta\OutputModes;

/**
 * Entry point for TOC public hooks.
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

		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_preview' ), 0 );

		add_filter( 'the_content', array( __CLASS__, 'inject_toc' ), 20 );

		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				if ( ! ModuleSettings::is_enabled( 'toc' ) ) {
					return;
				}

				StyleEmitter::output();
			}
		);

		add_action(
			'init',
			static function (): void {
				add_shortcode( 'airygen_toc', array( __CLASS__, 'render_shortcode' ) );
			}
		);

		add_action( 'init', array( Block::class, 'register' ) );
	}

	/**
	 * Register query vars for preview.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = 'airygen_toc_preview';
		return $vars;
	}

	/**
	 * Render preview page when requested.
	 *
	 * @return void
	 */
	public static function maybe_render_preview(): void {
		$preview = get_query_var( 'airygen_toc_preview' );
		if ( '' === $preview && empty( $_GET['airygen_toc_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview route gate.
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			status_header( 404 );
			exit;
		}

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		show_admin_bar( false );

		$settings = Settings::get();
		$sample   = self::load_preview_sample();

		$content        = ! empty( $settings['auto_injection_enabled'] ) ? Renderer::inject( $sample, $settings ) : $sample;
		$styles         = array();
		$stylesheet_uri = get_stylesheet_uri();
		if ( is_string( $stylesheet_uri ) && '' !== $stylesheet_uri ) {
			$styles[] = $stylesheet_uri;
		}
		$template_uri = get_template_directory_uri();
		if ( is_string( $template_uri ) && '' !== $template_uri ) {
			$parent_style = $template_uri . '/style.css';
			if ( ! in_array( $parent_style, $styles, true ) ) {
				$styles[] = $parent_style;
			}
		}

		$view_path = trailingslashit( AIRYGEN_PLUGIN_DIR ) . 'resources/views/admin/toc-preview.php';
		if ( ! file_exists( $view_path ) ) {
			exit;
		}

		$data = array(
			'content' => $content,
			'styles'  => $styles,
		);

		require $view_path;
		exit;
	}

	/**
	 * Load the localized preview content.
	 *
	 * @return string
	 */
	private static function load_preview_sample(): string {
		$sample  = '<h2>Getting started</h2>';
		$sample .= '<p>This is a short preview to show the table of contents structure.</p>';
		$sample .= '<h3>Define the goal</h3><p>Pick a single primary goal for the article.</p>';
		$sample .= '<h4>Primary intent</h4><p>Choose one intent to focus on.</p>';
		$sample .= '<h4>Audience fit</h4><p>Write to the reader\'s level.</p>';
		$sample .= '<h3>Plan the outline</h3><p>Sketch headings before drafting.</p>';
		$sample .= '<h4>Main sections</h4><p>Cover steps and examples.</p>';
		$sample .= '<h2>Publish and refine</h2>';
		$sample .= '<p>Refresh content as topics evolve.</p>';
		$sample .= '<h3>Monitor results</h3><p>Track impressions and clicks.</p>';
		$sample .= '<h4>Clarity check</h4><p>Ensure each section has one idea.</p>';
		$sample .= '<h3>Update cadence</h3><p>Review core content quarterly.</p>';

		return $sample;
	}

	/**
	 * Inject TOC into post content.
	 *
	 * @param string $content Content HTML.
	 * @return string
	 */
	public static function inject_toc( string $content ): string {
		if ( ! ModuleSettings::is_enabled( 'toc' ) ) {
			return $content;
		}

		if ( ! is_singular() ) {
			return $content;
		}

		$settings = Settings::get();
		if ( empty( $settings['auto_injection_enabled'] ) ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post ) {
			return $content;
		}

		if ( 'auto' !== self::get_post_mode( $post->ID ) ) {
			return $content;
		}

		$post_type = get_post_type( $post );
		if ( ! is_string( $post_type ) ) {
			return $content;
		}

		$post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
		? $settings['post_types']
		: array();

		if ( ! in_array( $post_type, $post_types, true ) ) {
			return $content;
		}

		return Renderer::inject( $content, $settings );
	}

	/**
	 * Handle the [airygen_toc] shortcode.
	 *
	 * @return string
	 */
	public static function render_shortcode(): string {
		if ( ! ModuleSettings::is_enabled( 'toc' ) ) {
			return '';
		}

		$settings = Settings::get();
		if ( empty( $settings['manual_output_enabled'] ) ) {
			return '';
		}

		$post = get_post();
		if ( ! $post ) {
			return '';
		}
		$post_mode = self::get_post_mode( $post->ID );
		if ( 'disabled' === $post_mode ) {
			return '';
		}
		if ( 'manual' !== $post_mode && 'auto' !== $post_mode ) {
			return '';
		}

		remove_filter( 'the_content', array( __CLASS__, 'inject_toc' ), 20 );
		$content = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
		add_filter( 'the_content', array( __CLASS__, 'inject_toc' ), 20 );

		$built = Renderer::build( $content, $settings );

		return $built['toc'];
	}

	/**
	 * Resolve per-post output mode.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private static function get_post_mode( int $post_id ): string {
		return OutputModes::get_mode( $post_id, 'toc' );
	}
}
