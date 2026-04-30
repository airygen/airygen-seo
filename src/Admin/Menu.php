<?php
/**
 * This class is responsible for the menu position of the plugin,
 * as well as the entry link to the settings page.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Airygen\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Extensions\AdminPageRegistry;
use Airygen\Constants;
use Airygen\Support\TemplateRenderer;
use DateTimeImmutable;
use DateTimeZone;

use function menu_page_url;
use function sanitize_text_field;
use function wp_unslash;

/**
 * Menu controller responsible for exposing the plugin settings entry.
 */
class Menu {

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::register();
	}

	/**
	 * Register admin hooks for the menu entry.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'create_options_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'suppress_notices' ), PHP_INT_MAX );
		add_filter( 'plugin_action_links_' . plugin_basename( AIRYGEN_PLUGIN_FILE ), array( __CLASS__, 'add_settings_link' ) );
	}

	/**
	 * Create the options page for the plugin.
	 *
	 * @return void
	 */
	public static function create_options_page(): void {
		$parent_slug = 'airygen-dashboard';
		$menu_icon   = 'data:image/svg+xml;base64,' . base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Static SVG data URI for admin menu icon.
			'<svg width="16" height="16" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet"><g transform="matrix(0.603828602702188,0,0,0.603828602702188,3.53115385058755,46.0)"><path class="airygen-plugin-icon-primary" fill="currentColor" fill-rule="evenodd" d="M 33.898438 -72.868360 L 68.566895 0.000000 L -0.770020 0.000000 Z M 28.698169 -43.721016 L 49.499243 0.000000 L 7.897094 0.000000 Z"></path><path fill="#00a0e9" d="M 21.764478 -29.147344 L 35.631860 0.000000 L 7.897094 0.000000 Z"></path></g></svg>'
		);

		add_menu_page(
			__( 'Airygen SEO', 'airygen-seo' ),
			__( 'Airygen SEO', 'airygen-seo' ),
			'manage_options',
			$parent_slug,
			array( __CLASS__, 'render_options_page' ),
			$menu_icon,
			58
		);

		foreach ( AdminPageRegistry::all() as $page ) {
			add_submenu_page(
				$parent_slug,
				$page['title'],
				$page['title'],
				$page['capability'],
				'dashboard' === $page['key'] ? $parent_slug : $page['slug'],
				array( __CLASS__, 'render_options_page' )
			);
		}
	}

	/**
	 * Render the options page for the plugin.
	 *
	 * @return void
	 */
	public static function render_options_page(): void {
		TemplateRenderer::render(
			'admin/options',
			array(
				'setting_group' => Constants::SETTING_GROUP,
				'page'          => 'airygen-options',
			)
		);
	}

	/**
	 * Append a Settings link to the plugin row.
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 *
	 * @return array<int, string>
	 */
	public static function add_settings_link( array $links ): array {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=airygen-settings' ) ),
			esc_html__( 'Settings', 'airygen-seo' )
		);

		return $links;
	}

	/**
	 * Enqueue SPA container assets when viewing the Airygen SEO settings page.
	 *
	 * @param string $hook Current admin screen hook.
	 *
	 * @return void
	 */
	public static function enqueue_assets( string $hook ): void {
		$current_page = self::resolve_current_page( $hook );
		if ( null === $current_page ) {
			return;
		}

		$script_path = self::plugin_path( 'build/admin/airygen-app.js' );
		if ( ! file_exists( $script_path ) ) {
			return;
		}

		$asset        = self::asset_meta( 'build/admin/airygen-app.asset.php' );
		$dependencies = self::script_dependencies( $asset );

		self::enqueue_styles();
		self::bootstrap_js_runtime();

		wp_enqueue_script(
			'airygen-admin-app',
			self::plugin_url( 'build/admin/airygen-app.js' ),
			$dependencies,
			$asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				'airygen-admin-app',
				'airygen-seo',
				plugin_dir_path( AIRYGEN_PLUGIN_FILE ) . 'languages'
			);
		}

		$yoast_active     = self::is_yoast_active();
		$rank_math_active = self::is_rank_math_active();
		$aioseo_active    = self::is_aioseo_active();
		$seopress_active  = self::is_seopress_active();
		$default_locale   = function_exists( 'get_user_locale' ) ? get_user_locale() : '';
		if ( '' === $default_locale ) {
			$default_locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		}

		$config = array(
			'restPath'            => '/airygen/v1/settings',
			'sessionCheckUrl'     => esc_url_raw( rest_url( 'airygen/v1/session-check' ) ),
			'restRoot'            => esc_url_raw( rest_url() ),
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'adminUrl'            => admin_url(),
			'logoutUrl'           => wp_logout_url( admin_url( 'admin.php?page=airygen-dashboard' ) ),
			'locale'              => $default_locale,
			'initialPage'         => $current_page,
			'extensionApiVersion' => Constants::EXTENSION_API_VERSION,
			'pageRegistry'        => AdminPageRegistry::boot_payload(),
			'debugRestPath'       => '/airygen/v1/debug',
			'debugEnablePath'     => '/airygen/v1/debug/enable',
			'debugDisablePath'    => '/airygen/v1/debug/disable',
			'debugEditorPath'     => '/airygen/v1/debug/editor-mode',
			'debugLevelPath'      => '/airygen/v1/debug/level',
			'notifyTimezones'     => self::build_timezone_options(),
			'migration'           => array(
				'yoastActive'    => $yoast_active,
				'rankMathActive' => $rank_math_active,
				'aioseoActive'   => $aioseo_active,
				'seoPressActive' => $seopress_active,
			),
			'assets'              => array(
				'logo'                  => self::plugin_url( 'assets/images/logo.png' ),
				'relatedPostsDemoImage' => self::plugin_url( 'assets/images/sample.jpg' ),
				'localSeoDemoImage'     => self::plugin_url( 'resources/assets/demo/local-business-1600x900.jpg' ),
				'localSeoDemoLogoImage' => self::plugin_url( 'resources/assets/demo/local-business-220x88.png' ),
			),
			'themeStylesheets'    => array_values(
				array_filter(
					array_unique(
						array(
							esc_url_raw( get_stylesheet_uri() ),
							esc_url_raw( trailingslashit( get_template_directory_uri() ) . 'style.css' ),
						)
					),
					static fn( $url ) => is_string( $url ) && '' !== $url
				)
			),
		);
		$config = apply_filters( Constants::HOOK_ADMIN_BOOT_PAYLOAD, $config, $current_page ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		wp_add_inline_script(
			'airygen-admin-app',
			'window.airygenAdmin = ' . wp_json_encode( $config, JSON_HEX_TAG ) . ';',
			'before'
		);
	}

	/**
	 * Hide third-party admin notices on plugin dashboard pages to keep the SPA clean.
	 *
	 * @return void
	 */
	public static function suppress_notices(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$ids = AdminPageRegistry::screen_ids();

		if ( ! in_array( $screen->id, $ids, true ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page  = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			$pages = AdminPageRegistry::page_slugs();

			if ( ! in_array( $page, $pages, true ) ) {
				return;
			}
		}

		// Hide third-party notices inside our settings screens.
		$handle = 'airygen-admin-suppress-notices';
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline-only style; no external file to version.
		wp_register_style( $handle, false );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			'#wpbody-content > .notice, #wpbody-content > .updated, #wpbody-content > .error, #wpbody-content > .update-nag{display:none;}'
		);
	}

	/**
	 * Resolve which SPA page should load for a given admin hook.
	 *
	 * @param string $hook Current admin hook.
	 *
	 * @return string|null
	 */
	private static function resolve_current_page( string $hook ): ?string {
		return AdminPageRegistry::resolve_current_page( $hook );
	}

	/**
	 * Detect whether a plugin is active site-wide or network-wide.
	 *
	 * @param string $plugin_file Plugin file relative to the plugins directory (e.g. 'wordpress-seo/wp-seo.php').
	 * @return bool
	 */
	private static function is_plugin_active_safe( string $plugin_file ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			// @phpstan-ignore-next-line requireOnce.fileNotFound -- Path resolved at runtime via ABSPATH.
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file ) ) {
			return true;
		}

		if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin_file ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Detect whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	private static function is_yoast_active(): bool {
		return defined( 'WPSEO_VERSION' ) || self::is_plugin_active_safe( 'wordpress-seo/wp-seo.php' );
	}

	/**
	 * Detect whether Rank Math is active.
	 *
	 * @return bool
	 */
	private static function is_rank_math_active(): bool {
		return defined( 'RANK_MATH_VERSION' ) || self::is_plugin_active_safe( 'rank-math/rank-math.php' );
	}

	/**
	 * Detect whether All in One SEO is active.
	 *
	 * @return bool
	 */
	private static function is_aioseo_active(): bool {
		return defined( 'AIOSEO_VERSION' ) || self::is_plugin_active_safe( 'all-in-one-seo-pack/all_in_one_seo_pack.php' );
	}

	/**
	 * Detect whether SEOPress is active.
	 *
	 * @return bool
	 */
	private static function is_seopress_active(): bool {
		return defined( 'SEOPRESS_VERSION' ) || self::is_plugin_active_safe( 'wp-seopress/seopress.php' );
	}

	/**
	 * Cached application configuration.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $app_config = null;

	/**
	 * Load config/app.php as an array.
	 *
	 * @return array<string, mixed>
	 *
	 * @phpstan-return array<string, mixed>
	 */
	private static function app_config(): array {
		if ( is_array( self::$app_config ) ) {
			return self::$app_config;
		}

		$file   = trailingslashit( AIRYGEN_PLUGIN_DIR ) . 'config/app.php';
		$config = array();

		if ( is_readable( $file ) ) {
			$data = require $file;
			if ( is_array( $data ) ) {
				$config = $data;
			}
		}

		self::$app_config = $config;

		return self::$app_config;
	}

	/**
	 * Enqueue shared Tailwind styles for the admin SPA.
	 *
	 * @return void
	 */
	private static function enqueue_styles(): void {
		$style_path = self::plugin_path( 'build/admin/app.tsx.css' );
		if ( ! file_exists( $style_path ) ) {
			return;
		}

		wp_enqueue_style(
			'airygen-admin-styles',
			self::plugin_url( 'build/admin/app.tsx.css' ),
			array(),
			(string) filemtime( $style_path )
		);

		$rtl_style_path = self::plugin_path( 'build/admin/app.tsx-rtl.css' );
		if ( file_exists( $rtl_style_path ) ) {
			wp_style_add_data( 'airygen-admin-styles', 'rtl', 'replace' );
		}
	}

	/**
	 * Normalize script dependencies for @wordpress/scripts builds.
	 *
	 * @param array<string, mixed> $asset Asset metadata.
	 *
	 * @return array<int, string>
	 */
	private static function script_dependencies( array $asset ): array {
		$dependencies = array();

		foreach ( $asset['dependencies'] as $dependency ) {
			if ( 'react-jsx-runtime' === $dependency ) {
				$dependencies[] = 'wp-element';
				continue;
			}

			$dependencies[] = $dependency;
		}

		return $dependencies;
	}

	/**
	 * Ensure the JSX runtime shims are present when @wordpress/scripts runs standalone.
	 *
	 * @return void
	 */
	private static function bootstrap_js_runtime(): void {
		wp_add_inline_script(
			'wp-element',
			'( function bootstrapJSXRuntime() {
				if ( ! window.wp || ! window.wp.element ) {
					setTimeout( bootstrapJSXRuntime, 0 );
					return;
				}

				var element = window.wp.element;

				if ( element.jsx && window.ReactJSXRuntime ) {
					return;
				}

				var assign = Object.assign || function assign( target ) {
					target = target || {};
					for ( var index = 1; index < arguments.length; index++ ) {
						var source = arguments[ index ];

						if ( ! source ) {
							continue;
						}

						for ( var key in source ) {
							if ( Object.prototype.hasOwnProperty.call( source, key ) ) {
								target[ key ] = source[ key ];
							}
						}
					}

					return target;
				};

				var createElement = element.createElement;
				var Fragment = element.Fragment;

				if ( ! createElement ) {
					return;
				}

				var factory = function jsxFactory( type, props, key ) {
					var finalProps = props ? assign( {}, props ) : {};

					if ( key !== undefined && key !== null ) {
						finalProps.key = key;
					}

					return createElement( type, finalProps );
				};

				element.jsx = factory;
				element.jsxs = factory;
				element.jsxDEV = factory;

				window.ReactJSXRuntime = {
					jsx: factory,
					jsxs: factory,
					jsxDEV: factory,
					Fragment: Fragment,
				};
			} )();',
			'after'
		);
	}

	/**
	 * Build timezone options for notify settings.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function build_timezone_options(): array {
		$now       = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$timezones = timezone_identifiers_list();
		$options   = array();

		foreach ( $timezones as $timezone_id ) {
			if ( ! is_string( $timezone_id ) || '' === $timezone_id ) {
				continue;
			}

			$timezone  = new DateTimeZone( $timezone_id );
			$offset    = $timezone->getOffset( $now );
			$options[] = array(
				'value' => $timezone_id,
				'label' => sprintf(
					'%s (UTC %s)',
					$timezone_id,
					self::format_timezone_offset( $offset )
				),
			);
		}

		return $options;
	}

	/**
	 * Convert seconds offset to a UTC label fragment.
	 *
	 * @param int $offset Offset in seconds.
	 *
	 * @return string
	 */
	private static function format_timezone_offset( int $offset ): string {
		$sign     = $offset < 0 ? '-' : '+';
		$absolute = abs( $offset );
		$hours    = (int) floor( $absolute / HOUR_IN_SECONDS );
		$minutes  = (int) floor( ( $absolute % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

		if ( 0 === $minutes ) {
			return sprintf( '%s%d', $sign, $hours );
		}

		return sprintf( '%s%d:%02d', $sign, $hours, $minutes );
	}

	/**
	 * Resolve an absolute plugin path for a relative file.
	 *
	 * @param string $relative Relative path to the file.
	 */
	private static function plugin_path( string $relative ): string {
		return plugin_dir_path( AIRYGEN_PLUGIN_FILE ) . ltrim( $relative, '/' );
	}

	/**
	 * Resolve a plugin URL for a relative asset.
	 *
	 * @param string $relative Relative path to the asset.
	 */
	private static function plugin_url( string $relative ): string {
		return plugins_url( ltrim( $relative, '/' ), AIRYGEN_PLUGIN_FILE );
	}

	/**
	 * Load asset metadata generated by @wordpress/scripts builds.
	 *
	 * @param string $relative Relative path to the asset metadata file.
	 *
	 * @return array{dependencies: array<int, string>, version: string}
	 */
	private static function asset_meta( string $relative ): array {
		$path = self::plugin_path( $relative );

		if ( file_exists( $path ) ) {
			$data = include $path;
			if ( is_array( $data ) ) {
				return array(
					'dependencies' => isset( $data['dependencies'] ) && is_array( $data['dependencies'] ) ? $data['dependencies'] : array(),
					'version'      => isset( $data['version'] ) ? (string) $data['version'] : AIRYGEN_VERSION,
				);
			}
		}

		return array(
			'dependencies' => array( 'wp-element' ),
			'version'      => AIRYGEN_VERSION,
		);
	}
}
