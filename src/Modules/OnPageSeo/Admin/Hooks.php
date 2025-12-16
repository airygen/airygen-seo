<?php
/**
 * Registers admin hooks for the OnPage SEO feature.
 *
 * @package Airygen\Modules\OnPageSeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\OnPageSeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Admin\Panels\Order as PanelOrder;
use Airygen\Admin\Panels\Visibility as PanelVisibility;
use Airygen\Constants;
use Airygen\Support\Debug\Logger;
use Airygen\Support\Debug\Settings as DebugSettings;
use Airygen\Support\Meta\OutputModes;
use Airygen\Support\Meta\PostData;
use WP_Post;

/**
 * Hook loader for admin runtime.
 */
final class Hooks {

	/**
	 * Metabox ID for Classic editor container.
	 */
	private const CLASSIC_METABOX_ID = 'airygen_classic_editor_metabox';

	/**
	 * Tracks whether the meta box order filter has been registered per post type.
	 *
	 * @var array<string, bool>
	 */
	private static array $order_filter_registered = array();

	/**
	 * Register hooks used in the admin area.
	 *
	 * @return void
	 */
	public static function register(): void {
		Logger::log( 'debug', 'OnPageSeo\\Admin\\Hooks::register invoked.' );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'maybe_force_classic_editor' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_classic_editor_assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_dashboard_metabox' ), 10, 1 );
		add_action( 'save_post', array( __CLASS__, 'persist_classic_fields' ), 10, 2 );
		add_filter( 'airygen/editor/config', array( __CLASS__, 'extend_editor_config' ) );
	}

	/**
	 * Force Classic Editor when debug toggle is enabled.
	 *
	 * @param bool   $use_block_editor Whether block editor is enabled.
	 * @param string $post_type        Post type slug.
	 *
	 * @return bool
	 */
	public static function maybe_force_classic_editor( bool $use_block_editor, string $post_type ): bool {
		if ( '' === $post_type ) {
			return $use_block_editor;
		}

		if ( DebugSettings::is_classic_forced() ) {
			return false;
		}

		return $use_block_editor;
	}

	/**
	 * Enqueue block editor bundle.
	 *
	 * @return void
	 */
	public static function enqueue_block_editor_assets(): void {
		Logger::log( 'debug', 'enqueue_block_editor_assets fired.' );
		self::enqueue_editor_bundle(
			'block',
			'airygen-editor-block',
			'build/block-editor/airygen-editor.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-i18n',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-core-data',
				'wp-api-fetch',
			),
			'airygen-editor-block-style',
			'build/block-editor/style-block-editor.css',
			'build/block-editor/airygen-editor.asset.php'
		);
	}

	/**
	 * Enqueue assets when using the classic editor.
	 *
	 * @param string $hook Current admin hook.
	 *
	 * @return void
	 */
	public static function enqueue_classic_editor_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen && isset( $screen->post_type ) ? (string) $screen->post_type : '';

		if ( $post_type && self::is_block_editor_request( $post_type ) ) {
			return;
		}

		self::enqueue_editor_bundle(
			'classic',
			'airygen-editor-classic',
			'build/classic-editor/airygen-editor.js',
			array(
				'wp-i18n',
				'wp-element',
				'wp-dom-ready',
				'wp-api-fetch',
			),
			'airygen-editor-classic-style',
			'build/classic-editor/style-classic-editor.css',
			'build/classic-editor/airygen-editor.asset.php'
		);
	}

	/**
	 * Register preview metabox shared by both editors.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return void
	 */
	public static function register_dashboard_metabox( string $post_type ): void {
		if ( ! in_array( $post_type, self::eligible_post_types(), true ) ) {
			Logger::log( 'debug', 'Metabox skipped: ineligible post type ' . $post_type );
			return;
		}

		if ( self::is_block_editor_request( $post_type ) ) {
			Logger::log( 'debug', 'Metabox skipped: block editor detected for ' . $post_type );
			return;
		}

		Logger::log( 'debug', 'Registering metabox for ' . $post_type );
		self::register_meta_box_order_filter( $post_type );

		add_meta_box(
			self::CLASSIC_METABOX_ID,
			__( 'Airygen SEO Classic', 'airygen-seo' ),
			array( __CLASS__, 'render_dashboard_metabox' ),
			$post_type,
			'normal',
			'default'
		);
	}

	/**
	 * Output the React root container for the dashboard experience.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public static function render_dashboard_metabox( WP_Post $post ): void {
		wp_nonce_field( 'airygen_dashboard_save', 'airygen_dashboard_nonce' );

		$post_data    = PostData::get( (int) $post->ID );
		$output_modes = OutputModes::get( (int) $post->ID );

		printf(
			'<div id="airygen-classic-root" data-post-id="%1$d" data-mode="main" data-meta-title="%2$s" data-meta-description="%3$s" data-focus-keyphrase="%4$s" data-focus-long-tail="%5$s" data-agent-prompt="%6$s" data-canonical="%7$s" data-robots="%8$s" data-toc-mode="%9$s" data-faq-mode="%10$s" data-topic-mode="%11$s" data-schema-type="%12$s"></div>',
			(int) $post->ID,
			esc_attr( $post_data['title'] ),
			esc_attr( $post_data['description'] ),
			esc_attr( $post_data['focusKeyphrase'] ),
			esc_attr( $post_data['focusLongTail'] ),
			esc_attr( $post_data['agentPrompt'] ),
			esc_attr( $post_data['canonical'] ),
			esc_attr( $post_data['robots'] ),
			esc_attr( $output_modes['toc'] ),
			esc_attr( $output_modes['faq'] ),
			esc_attr( $output_modes['topicExpansion'] ),
			esc_attr( $post_data['schemaArticleType'] )
		);

		// Description moved into the Classic Editor footer message.
	}

	/**
	 * Persist form submissions from the classic editor metabox.
	 *
	 * @param int     $post_id The current post ID.
	 * @param WP_Post $post    The post object.
	 *
	 * @return void
	 */
	public static function persist_classic_fields( int $post_id, WP_Post $post ): void {
		if ( self::should_bail_on_save( $post_id, $post ) ) {
			return;
		}

		$nonce_field  = null;
		$nonce_action = null;

		if ( isset( $_POST['airygen_dashboard_nonce'] ) ) {
			$nonce_field  = 'airygen_dashboard_nonce';
			$nonce_action = 'airygen_dashboard_save';
		} elseif ( isset( $_POST['airygen_onpage_nonce'] ) ) {
			$nonce_field  = 'airygen_onpage_nonce';
			$nonce_action = 'airygen_onpage_save';
		}

		if ( ! $nonce_field || ! $nonce_action ) {
			return;
		}

		if ( ! isset( $_POST[ $nonce_field ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce = wp_unslash( $_POST[ $nonce_field ] );
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return;
		}

		$title        = self::read_post_value( 'airygen_title' );
		$description  = self::read_post_value( 'airygen_description' );
		$keyphrase    = self::read_post_value( 'airygen_focus_keyphrase' );
		$long_tail    = self::read_post_value( 'airygen_focus_long_tail' );
		$agent_prompt = self::read_post_value( 'airygen_agent_prompt' );
		$canonical    = isset( $_POST['airygen_canonical'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? self::read_post_value( 'airygen_canonical' )
		: '';
		$robots       = isset( $_POST['airygen_robots'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? self::read_post_value( 'airygen_robots' )
		: '';
		$schema_type  = isset( $_POST['airygen_schema_article_type'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? self::read_post_value( 'airygen_schema_article_type' )
		: '';

		PostData::save(
			$post_id,
			array(
				'title'             => $title,
				'description'       => $description,
				'focusKeyphrase'    => $keyphrase,
				'focusLongTail'     => $long_tail,
				'agentPrompt'       => $agent_prompt,
				'canonical'         => $canonical,
				'robots'            => $robots,
				'schemaArticleType' => $schema_type,
			)
		);

		if (
			isset( $_POST['airygen_toc_mode'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Missing
			isset( $_POST['airygen_faq_mode'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Missing
			isset( $_POST['airygen_topic_mode'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		) {
			OutputModes::save(
				$post_id,
				array(
					'toc'            => self::read_post_value( 'airygen_toc_mode' ),
					'faq'            => self::read_post_value( 'airygen_faq_mode' ),
					'topicExpansion' => self::read_post_value( 'airygen_topic_mode' ),
				)
			);
		}
	}

	/**
	 * Determine the post types that should expose SEO fields.
	 *
	 * @return array<int, string>
	 */
	private static function eligible_post_types(): array {
		$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment', 'revision', 'nav_menu_item' ) );

		return array_values( $post_types );
	}

	/**
	 * Determine whether to bypass save handlers for the current request.
	 *
	 * @param int     $post_id Current post ID.
	 * @param WP_Post $post    Current post object.
	 *
	 * @return bool
	 */
	private static function should_bail_on_save( int $post_id, WP_Post $post ): bool {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return true;
		}

		return ! current_user_can( 'edit_post', $post_id ) || 'auto-draft' === $post->post_status;
	}

	/**
	 * Enqueue the appropriate editor bundle.
	 *
	 * @param string $mode             Target editor mode.
	 * @param string $script_handle    Script handle.
	 * @param string $script_relative  Relative script path.
	 * @param array  $fallback_deps    Default dependencies when asset file missing.
	 * @param string $style_handle     Style handle.
	 * @param string $style_relative   Relative style path.
	 * @param string $asset_relative   Relative asset metadata path.
	 *
	 * @return void
	 */
	private static function enqueue_editor_bundle(
		string $mode,
		string $script_handle,
		string $script_relative,
		array $fallback_deps,
		string $style_handle,
		string $style_relative,
		string $asset_relative
	): void {
		$bundle = self::resolve_editor_bundle(
			$mode,
			$script_handle,
			$script_relative,
			$fallback_deps,
			$style_handle,
			$style_relative,
			$asset_relative
		);

		if ( ! file_exists( $bundle['script_path'] ) ) {
			return;
		}

		$asset_meta = self::resolve_asset_meta(
			$bundle['asset_path'],
			$bundle['fallback_dependencies'],
			$bundle['version_fallback']
		);

		wp_enqueue_script(
			$bundle['script_handle'],
			$bundle['script_url'],
			$asset_meta['dependencies'],
			$asset_meta['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				$bundle['script_handle'],
				'airygen-seo',
				$bundle['translations_path']
			);
		}

		self::enqueue_style_if_exists(
			$bundle['style_handle'],
			$bundle['style_path'],
			$bundle['style_url'],
			$asset_meta['version']
		);
		self::inject_editor_settings( $bundle['script_handle'], $mode );

		if ( 'classic' === $mode ) {
			wp_add_inline_script(
				$bundle['script_handle'],
				self::relocate_metabox_script(),
				'after'
			);
		}

		do_action( Constants::HOOK_EDITOR_ASSETS, $mode, $bundle['script_handle'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.
	}

	/**
	 * Resolve the current editor bundle, allowing premium plugins to override assets.
	 *
	 * @param string $mode            Target editor mode.
	 * @param string $script_handle   Script handle.
	 * @param string $script_relative Relative script path.
	 * @param array  $fallback_deps   Default dependencies when asset file missing.
	 * @param string $style_handle    Style handle.
	 * @param string $style_relative  Relative style path.
	 * @param string $asset_relative  Relative asset metadata path.
	 *
	 * @return array{
	 *   script_handle:string,
	 *   script_path:string,
	 *   script_url:string,
	 *   style_handle:string,
	 *   style_path:string,
	 *   style_url:string,
	 *   asset_path:string,
	 *   translations_path:string,
	 *   fallback_dependencies:array<int,string>,
	 *   version_fallback:string
	 * }
	 */
	private static function resolve_editor_bundle(
		string $mode,
		string $script_handle,
		string $script_relative,
		array $fallback_deps,
		string $style_handle,
		string $style_relative,
		string $asset_relative
	): array {
		$default_bundle = array(
			'script_handle'         => $script_handle,
			'script_path'           => self::plugin_path( $script_relative ),
			'script_url'            => self::plugin_url( $script_relative ),
			'style_handle'          => $style_handle,
			'style_path'            => self::plugin_path( $style_relative ),
			'style_url'             => self::plugin_url( $style_relative ),
			'asset_path'            => self::plugin_path( $asset_relative ),
			'translations_path'     => plugin_dir_path( AIRYGEN_PLUGIN_FILE ) . 'languages',
			'fallback_dependencies' => array_values( array_map( 'strval', $fallback_deps ) ),
			'version_fallback'      => AIRYGEN_VERSION,
		);

		$filtered_bundle = apply_filters( Constants::HOOK_EDITOR_BUNDLE, $default_bundle, $mode ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.
		if ( ! is_array( $filtered_bundle ) ) {
			return $default_bundle;
		}

		return array(
			'script_handle'         => self::filtered_bundle_string( $filtered_bundle, 'script_handle', $default_bundle['script_handle'] ),
			'script_path'           => self::filtered_bundle_string( $filtered_bundle, 'script_path', $default_bundle['script_path'] ),
			'script_url'            => self::filtered_bundle_string( $filtered_bundle, 'script_url', $default_bundle['script_url'] ),
			'style_handle'          => self::filtered_bundle_string( $filtered_bundle, 'style_handle', $default_bundle['style_handle'] ),
			'style_path'            => self::filtered_bundle_string( $filtered_bundle, 'style_path', $default_bundle['style_path'] ),
			'style_url'             => self::filtered_bundle_string( $filtered_bundle, 'style_url', $default_bundle['style_url'] ),
			'asset_path'            => self::filtered_bundle_string( $filtered_bundle, 'asset_path', $default_bundle['asset_path'] ),
			'translations_path'     => self::filtered_bundle_string( $filtered_bundle, 'translations_path', $default_bundle['translations_path'] ),
			'fallback_dependencies' => self::filtered_bundle_dependencies( $filtered_bundle['fallback_dependencies'] ?? null, $default_bundle['fallback_dependencies'] ),
			'version_fallback'      => self::filtered_bundle_string( $filtered_bundle, 'version_fallback', $default_bundle['version_fallback'] ),
		);
	}

	/**
	 * Normalize a filtered bundle string value.
	 *
	 * @param array<string, mixed> $bundle       Filtered bundle.
	 * @param string               $key          Bundle key.
	 * @param string               $default_value Default value.
	 *
	 * @return string
	 */
	private static function filtered_bundle_string( array $bundle, string $key, string $default_value ): string {
		$value = $bundle[ $key ] ?? null;
		if ( ! is_string( $value ) || '' === $value ) {
			return $default_value;
		}

		return $value;
	}

	/**
	 * Normalize a filtered dependency list.
	 *
	 * @param mixed            $dependencies Dependency candidate.
	 * @param array<int,string> $fallback     Default dependencies.
	 *
	 * @return array<int,string>
	 */
	private static function filtered_bundle_dependencies( $dependencies, array $fallback ): array {
		if ( ! is_array( $dependencies ) ) {
			return $fallback;
		}

		return array_values(
			array_map(
				'strval',
				$dependencies
			)
		);
	}

	/**
	 * Append shared settings for block/classic bundles.
	 *
	 * @param string $handle Script handle.
	 * @param string $mode   Editor mode.
	 *
	 * @return void
	 */
	private static function inject_editor_settings( string $handle, string $mode ): void {
		$settings = array(
			'mode'            => $mode,
			'currentBlogId'   => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1,
			'modules'         => ModuleSettings::get(),
			'siteUrl'         => trailingslashit( home_url( '/' ) ),
			'metaKeys'        => array(
				'postData'    => Constants::META_POST_DATA,
				'outputModes' => Constants::META_OUTPUT_MODES,
			),
			'restNonce'       => wp_create_nonce( 'wp_rest' ),
			'sessionCheckUrl' => rest_url( 'airygen/v1/session-check' ),
			'panelOrder'      => PanelOrder::get(),
			'panelVisibility' => PanelVisibility::get(),
			'topicCluster'    => array(
				'list'       => rest_url( 'airygen/v1/topic-cluster/list' ),
				'save'       => rest_url( 'airygen/v1/topic-cluster/save' ),
				'summary'    => rest_url( 'airygen/v1/topic-cluster/summary' ),
				'mindMapUrl' => admin_url( 'admin.php?page=airygen-topic-cluster' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
			),
		);

		$editor_config = apply_filters( Constants::HOOK_EDITOR_CONFIG, $settings ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		wp_add_inline_script(
			$handle,
			'window.AirygenEditor = Object.assign({}, window.AirygenEditor || {}, ' . wp_json_encode( $editor_config, JSON_HEX_TAG ) . ');',
			'before'
		);
	}

	/**
	 * Surface On-Page settings to the editor bundle for pixel accounting.
	 *
	 * @param array<string, mixed> $config Existing editor config.
	 *
	 * @return array<string, mixed>
	 */
	public static function extend_editor_config( array $config ): array {
		$settings         = Settings::get();
		$site_name        = get_bloginfo( 'name' );
		$site_description = get_bloginfo( 'description' );

		$config['onpage'] = array(
			'templates'        => $settings['templates'] ?? array(),
			'site_name'        => ! empty( $site_name ) ? $site_name : '',
			'site_description' => ! empty( $site_description ) ? $site_description : '',
		);

		return $config;
	}

	/**
	 * Enqueue a stylesheet if it exists.
	 *
	 * @param string $handle   Style handle.
	 * @param string $relative Relative path.
	 * @param string $version  Version string.
	 *
	 * @return void
	 */
	private static function enqueue_style_if_exists( string $handle, string $style_path, string $style_url, string $version ): void {
		if ( ! file_exists( $style_path ) ) {
			return;
		}

		wp_enqueue_style(
			$handle,
			$style_url,
			array(),
			$version
		);
	}

	/**
	 * Read a POSTed value and sanitize it.
	 *
	 * @param string $key Request key.
	 *
	 * @return string
	 */
	private static function read_post_value( string $key ): string {
		// phpcs:ignore
		$value = $_POST[ $key ] ?? null;

		if ( ! $value ) {
			return '';
		}

		$value = wp_unslash( $value );
		return sanitize_text_field( $value );
	}

	/**
	 * Resolve the absolute plugin path to a file.
	 *
	 * @param string $relative Relative path from plugin root.
	 *
	 * @return string
	 */
	private static function plugin_path( string $relative ): string {
		return plugin_dir_path( AIRYGEN_PLUGIN_FILE ) . ltrim( $relative, '/' );
	}

	/**
	 * Resolve the public plugin URL to a file.
	 *
	 * @param string $relative Relative path from plugin root.
	 *
	 * @return string
	 */
	private static function plugin_url( string $relative ): string {
		return plugins_url( ltrim( $relative, '/' ), AIRYGEN_PLUGIN_FILE );
	}

	/**
	 * Resolve asset metadata from a generated asset PHP file.
	 *
	 * @param string $asset_path             Absolute path to the asset metadata file.
	 * @param array  $fallback_dependencies  Default dependency list.
	 * @param string $version_fallback       Fallback version string.
	 *
	 * @return array{dependencies: array, version: string}
	 */
	private static function resolve_asset_meta( string $asset_path, array $fallback_dependencies, string $version_fallback ): array {
		if ( file_exists( $asset_path ) ) {
			$data = include $asset_path;
			if ( is_array( $data ) ) {
				return array(
					'dependencies' => $data['dependencies'] ?? $fallback_dependencies,
					'version'      => (string) ( $data['version'] ?? $version_fallback ),
				);
			}
		}

		return array(
			'dependencies' => $fallback_dependencies,
			'version'      => $version_fallback,
		);
	}

	/**
	 * Guarantee the dashboard metabox remains in the normal column for all users.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return void
	 */
	private static function register_meta_box_order_filter( string $post_type ): void {
		if ( isset( self::$order_filter_registered[ $post_type ] ) ) {
			return;
		}

		$filter_name = sprintf( 'get_user_option_meta-box-order_%s', $post_type );

		add_filter(
			$filter_name,
			static function ( $value ) {
				return self::ensure_metabox_in_normal_context( $value );
			}
		);

		self::$order_filter_registered[ $post_type ] = true;
	}

	/**
	 * Move the Airygen SEO dashboard metabox to the normal column within stored user preferences.
	 *
	 * @param mixed $value Current user option value.
	 *
	 * @return mixed
	 */
	private static function ensure_metabox_in_normal_context( $value ) {
		if ( empty( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$maybe = maybe_unserialize( $value );
			if ( false !== $maybe ) {
				$value = $maybe;
			}
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		$box_id   = self::CLASSIC_METABOX_ID;
		$contexts = array( 'side', 'advanced', 'normal' );

		foreach ( $contexts as $context ) {
			if ( ! isset( $value[ $context ] ) ) {
				continue;
			}

			$items = self::parse_order_items( $value[ $context ] );
			$items = self::remove_metabox_from_items( $items, $box_id );

			if ( 'normal' !== $context ) {
				$value[ $context ] = self::format_order_items( $items, $value[ $context ] );
				continue;
			}

			array_unshift( $items, $box_id );
			$value[ $context ] = self::format_order_items( $items, $value[ $context ] );
		}

		if ( ! isset( $value['normal'] ) ) {
			$value['normal'] = $box_id;
		}

		return $value;
	}

	/**
	 * Parse a stored order value into a normalized array of metabox IDs.
	 *
	 * @param mixed $input Stored order value.
	 *
	 * @return array<int, string>
	 */
	private static function parse_order_items( $input ): array {
		if ( is_array( $input ) ) {
			return array_values(
				array_filter(
					array_map( 'strval', $input ),
					static fn( string $item ): bool => '' !== $item
				)
			);
		}

		if ( is_string( $input ) ) {
			return array_values(
				array_filter(
					array_map(
						static fn( string $item ): string => trim( $item ),
						explode( ',', $input )
					),
					static fn( string $item ): bool => '' !== $item
				)
			);
		}

		return array();
	}

	/**
	 * Remove the dashboard metabox identifier from a list of order items.
	 *
	 * @param array<int, string> $items  Ordered metabox IDs.
	 * @param string             $box_id Target metabox ID.
	 *
	 * @return array<int, string>
	 */
	private static function remove_metabox_from_items( array $items, string $box_id ): array {
		return array_values(
			array_filter(
				$items,
				static fn( string $item ): bool => $item !== $box_id
			)
		);
	}

	/**
	 * Convert a normalized list of IDs back to the expected storage format.
	 *
	 * @param array<int, string> $items    Ordered metabox IDs.
	 * @param mixed              $original Original storage format.
	 *
	 * @return array<int, string>|string
	 */
	private static function format_order_items( array $items, $original ) {
		if ( is_array( $original ) ) {
			return $items;
		}

		return implode( ',', $items );
	}

	/**
	 * Inline script that repositions the metabox client-side when necessary.
	 *
	 * @return string
	 */
	private static function relocate_metabox_script(): string {
		$script = <<<'JS'
( function () {
	const BOX_ID = 'airygen_classic_editor_metabox';
	const MAX_ATTEMPTS = 10;

	const findNormalContainer = () =>
		document.getElementById( 'normal-sortables' ) ||
		document.querySelector( '.edit-post-meta-boxes-area.is-normal .meta-box-sortables' );

	const moveBox = ( attempt = 0 ) => {
		const box = document.getElementById( BOX_ID );
		const container = findNormalContainer();

		if ( ! box || ! container ) {
			if ( attempt < MAX_ATTEMPTS ) {
				window.setTimeout( () => moveBox( attempt + 1 ), 250 );
			}
			return;
		}

		if ( ! container.contains( box ) ) {
			container.prepend( box );
		}

		box.classList.remove( 'closed' );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => moveBox() );
	} else {
		moveBox();
	}

	window.addEventListener( 'load', () => moveBox() );
} )();
JS;

		return $script;
	}

	/**
	 * Determine whether the current request is using the block editor.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return bool
	 */
	private static function is_block_editor_request( string $post_type ): bool {
		if ( '' === $post_type ) {
			return false;
		}

		if ( DebugSettings::is_classic_forced() ) {
			return false;
		}

		if ( ! function_exists( 'use_block_editor_for_post_type' ) ) {
			return false;
		}

		if ( ! use_block_editor_for_post_type( $post_type ) ) {
			return false;
		}

		if ( isset( $_GET['classic-editor'] ) || isset( $_GET['classic-editor__forget'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && method_exists( $screen, 'is_block_editor' ) ) {
				return (bool) $screen->is_block_editor();
			}
		}

		return true;
	}
}
