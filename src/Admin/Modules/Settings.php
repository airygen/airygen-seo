<?php
/**
 * Persist module enablement preferences for the admin dashboard.
 *
 * @package Airygen\Admin\Modules
 */

declare(strict_types=1);

namespace Airygen\Admin\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\LlmsTxt\Public\Routes as LlmsTxtRoutes;
use Airygen\Modules\Sitemap\Public\Routes as SitemapRoutes;

/**
 * Stores which feature modules are available in the settings SPA.
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_MODULES;

	/**
	 * Cached module configuration.
	 *
	 * @var array<string, bool>|null
	 */
	private static $cache = null;

	/**
	 * Ordered list of module keys exposed to the SPA.
	 *
	 * @var array<int, string>
	 */
	private const MODULE_KEYS = array(
		'onPageSeo',
		'social',
		'schema',
		'breadcrumbs',
		'robots',
		'toc',
		'imageSeo',
		'hreflang',
		'sitemap',
		'codeSnippetManager',
		'siteVerification',
		'rssFeedSignature',
		'linkCounter',
		'siteHealth',
		'scoreCalculator',
		'brokenLinkChecker',
		'redirects',
		'linkSuggestions',
		'instantIndexing',
		'topicCluster',
		'authorSeo',
		'taxonomySeo',
		'wooCommerceSeo',
		'localSeo',
		'relatedPosts',
		'notFoundManager',
		'notify',
		'markdownForAgents',
		'llmsTxt',
	);

	/**
	 * Ensure the option exists and includes all module keys.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::default_config(), '', 'no' );
			self::$cache = self::default_config();
			return;
		}

		$current  = get_option( self::OPTION_NAME, array() );
		$migrated = self::sanitize( $current );

		if ( $migrated !== $current ) {
			update_option( self::OPTION_NAME, $migrated, 'no' );
		}

		self::$cache = $migrated;
	}

	/**
	 * Retrieve sanitized module settings.
	 *
	 * @return array<string, bool>
	 */
	public static function get(): array {
		if ( is_array( self::$cache ) ) {
			return self::$cache;
		}

		$value = get_option( self::OPTION_NAME, array() );

		self::$cache = self::sanitize( $value );

		return self::$cache;
	}

	/**
	 * Persist sanitized module settings.
	 *
	 * @param array<string, mixed> $value Raw module values.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		$previous  = self::get();
		$sanitized = self::sanitize( $value );
		update_option( self::OPTION_NAME, $sanitized, 'no' );
		self::$cache = $sanitized;

		$prev_sitemap = isset( $previous['sitemap'] ) ? (bool) $previous['sitemap'] : true;
		$new_sitemap  = isset( $sanitized['sitemap'] ) ? (bool) $sanitized['sitemap'] : true;
		$prev_m4a     = isset( $previous['markdownForAgents'] ) ? (bool) $previous['markdownForAgents'] : true;
		$new_m4a      = isset( $sanitized['markdownForAgents'] ) ? (bool) $sanitized['markdownForAgents'] : true;
		$prev_llms    = isset( $previous['llmsTxt'] ) ? (bool) $previous['llmsTxt'] : true;
		$new_llms     = isset( $sanitized['llmsTxt'] ) ? (bool) $sanitized['llmsTxt'] : true;

		if ( $prev_sitemap !== $new_sitemap && $new_sitemap && class_exists( SitemapRoutes::class ) ) {
			SitemapRoutes::add_rewrite_rules();
		}

		if ( array_key_exists( 'sitemap', $sanitized ) ) {
			flush_rewrite_rules( false );
		}

		if ( $prev_m4a !== $new_m4a ) {
			flush_rewrite_rules( false );
		}

		if ( $prev_llms !== $new_llms ) {
			if ( $new_llms && class_exists( LlmsTxtRoutes::class ) ) {
				LlmsTxtRoutes::add_rewrite_rules();
			}
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Sanitize the stored option.
	 *
	 * @param mixed $value Raw option value.
	 *
	 * @return array<string, bool>
	 */
	public static function sanitize( $value ): array {
		$config = self::default_config();

		if ( ! is_array( $value ) ) {
			return $config;
		}

		foreach ( self::MODULE_KEYS as $key ) {
			if ( array_key_exists( $key, $value ) ) {
				$config[ $key ] = (bool) $value[ $key ];
			}
		}

		return $config;
	}

	/**
	 * Modules enabled by default on first install.
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_ON = array(
		'onPageSeo',
		'breadcrumbs',
		'sitemap',
		'imageSeo',
		'robots',
		'siteVerification',
		'linkCounter',
		'linkSuggestions',
		'scoreCalculator',
	);

	/**
	 * Default configuration: core modules enabled, others disabled.
	 *
	 * @return array<string, bool>
	 */
	private static function default_config(): array {
		$config = array();
		foreach ( self::MODULE_KEYS as $key ) {
			$config[ $key ] = in_array( $key, self::DEFAULT_ON, true );
		}

		return $config;
	}

	/**
	 * Determine if a module is currently enabled.
	 *
	 * @param string $key Module identifier (e.g. robots, sitemap).
	 * @return bool
	 */
	public static function is_enabled( string $key ): bool {
		$settings = self::get();

		if ( array_key_exists( $key, $settings ) ) {
			return (bool) $settings[ $key ];
		}

		return in_array( $key, self::MODULE_KEYS, true );
	}

	/**
	 * Expose the list of known module keys.
	 *
	 * @return array<int, string>
	 */
	public static function keys(): array {
		return self::MODULE_KEYS;
	}
}
