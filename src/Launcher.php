<?php
/**
 * Core launcher responsible for wiring Airygen SEO features.
 *
 * @package Airygen
 */

declare(strict_types=1);

namespace Airygen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Hooks as AdminHooks;
use Airygen\Modules\BrokenLinkChecker\Runtime\Hooks as BrokenLinkCheckerRuntimeHooks;
use Airygen\Modules\InstantIndexing\Runtime\Hooks as InstantIndexingRuntimeHooks;
use Airygen\Modules\LinkCounter\Runtime\Hooks as LinkCounterRuntimeHooks;
use Airygen\Modules\LinkSuggestions\Runtime\Hooks as LinkSuggestionsRuntimeHooks;
use Airygen\Public\Hooks as PublicHooks;
use Airygen\Support\Debug\Logger;
use Airygen\Support\Meta\RegisterPostMeta;
use Airygen\Support\Routing\Registrar as RouteRegistrar;
use Airygen\Support\Utils\Env;

/**
 * Bootstraps the plugin for both admin and public contexts.
 */
final class Launcher {

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public static function boot(): void {

		Logger::log(
			'debug',
			sprintf(
				'Launcher::boot request=%s admin=%s rest=%s cli=%s',
				isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'unknown',
				Env::is_admin_context() ? 'yes' : 'no',
				Env::is_rest_request() ? 'yes' : 'no',
				Env::is_cli() ? 'yes' : 'no'
			)
		);
		self::register_script_translation_path_fixes();
		RouteRegistrar::register();
		BrokenLinkCheckerRuntimeHooks::register();
		LinkCounterRuntimeHooks::register();
		LinkSuggestionsRuntimeHooks::register();
		InstantIndexingRuntimeHooks::register();
		RegisterPostMeta::register();

		$should_boot_admin = Env::is_admin_context();

		Logger::log(
			'debug',
			sprintf(
				'should_boot_admin=%s (admin=%s, rest=%s, cli=%s)',
				$should_boot_admin ? 'yes' : 'no',
				is_admin() ? 'yes' : 'no',
				Env::is_rest_request() ? 'yes' : 'no',
				Env::is_cli() ? 'yes' : 'no'
			)
		);

		if ( $should_boot_admin ) {
			self::register_feature_hooks(
				AdminHooks::class,
				Env::is_rest_request() ? 'rest' : 'admin'
			);
			return;
		}

		self::register_feature_hooks( PublicHooks::class, 'public' );
	}

	/**
	 * Normalize script translation lookup paths in multisite subdirectory mode.
	 *
	 * In /{site}/wp-admin requests, script URLs can include the site path prefix
	 * (for example /ja/wp-content/plugins/...). WordPress may then derive a
	 * relative path like wp-content/plugins/... which does not match our JSON hash
	 * source paths (build/...).
	 *
	 * @return void
	 */
	private static function register_script_translation_path_fixes(): void {
		add_filter(
			'load_script_textdomain_relative_path',
			static function ( $relative, string $src ) {
				if ( ! is_string( $relative ) || '' === $relative ) {
					return $relative;
				}

				$src_path = wp_parse_url( $src, PHP_URL_PATH );
				if ( ! is_string( $src_path ) || '' === $src_path ) {
					return $relative;
				}

				if ( str_ends_with( $src_path, '/wp-content/plugins/airygen-seo/build/admin/airygen-app.js' ) ) {
					return 'build/admin/airygen-app.js';
				}

				if ( str_ends_with( $src_path, '/wp-content/plugins/airygen-seo/build/block-editor/airygen-editor.js' ) ) {
					return 'build/block-editor/airygen-editor.js';
				}

				if ( str_ends_with( $src_path, '/wp-content/plugins/airygen-seo/build/classic-editor/airygen-editor.js' ) ) {
					return 'build/classic-editor/airygen-editor.js';
				}

				return $relative;
			},
			10,
			2
		);
	}

	/**
	 * Register feature hooks for admin or public contexts.
	 *
	 * @param string $hooks_class Fully qualified class name.
	 * @param string $context     Context label for debug logging.
	 *
	 * @return void
	 */
	private static function register_feature_hooks( string $hooks_class, string $context ): void {
		if ( class_exists( $hooks_class ) && method_exists( $hooks_class, 'register' ) ) {
			Logger::log( 'debug', 'registering ' . $context . ' hooks via ' . $hooks_class );
			$hooks_class::register();
			return;
		}

		Logger::log( 'debug', 'missing ' . $context . ' hooks (' . $hooks_class . ')' );
	}

	/**
	 * Helper to resolve the plugin file path.
	 *
	 * @return string
	 */
	private static function plugin_file(): string {
		return defined( 'AIRYGEN_PLUGIN_FILE' ) ? (string) AIRYGEN_PLUGIN_FILE : __FILE__;
	}

	/**
	 * Helper to resolve the plugin basename.
	 *
	 * @return string
	 */
	private static function plugin_basename(): string {
		return plugin_basename( self::plugin_file() );
	}
}
