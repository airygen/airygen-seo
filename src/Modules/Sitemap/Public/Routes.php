<?php
/**
 * Registers sitemap rewrite rules.
 *
 * @package Airygen\Modules\Sitemap\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Sitemap\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declares rewrite rules and query vars for sitemap endpoints.
 */
final class Routes {

	private const QUERY_FLAG       = 'airygen_sitemap';
	private const QUERY_OBJECT     = 'airygen_sitemap_object';
	private const QUERY_PAGE       = 'airygen_sitemap_page';
	private const QUERY_STYLESHEET = 'airygen_sitemap_stylesheet';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
	}

	/**
	 * Add rewrite rules for sitemap endpoints.
	 *
	 * @return void
	 */
	public static function add_rewrite_rules(): void {
		add_rewrite_rule( 'sitemap\\.xml$', 'index.php?' . self::QUERY_FLAG . '=index', 'top' );
		add_rewrite_rule( 'local\\.kml$', 'index.php?' . self::QUERY_FLAG . '=kml', 'top' );
		add_rewrite_rule(
			'sitemap-([^/]+)-([0-9]+)\\.xml$',
			'index.php?' . self::QUERY_FLAG . '=content&' . self::QUERY_OBJECT . '=$matches[1]&' . self::QUERY_PAGE . '=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'wp-sitemap-index\\.xsl$',
			'index.php?' . self::QUERY_FLAG . '=stylesheet&' . self::QUERY_STYLESHEET . '=index',
			'top'
		);
		add_rewrite_rule(
			'wp-sitemap\\.xsl$',
			'index.php?' . self::QUERY_FLAG . '=stylesheet&' . self::QUERY_STYLESHEET . '=content',
			'top'
		);
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array<int, string> $vars Query vars.
	 *
	 * @return array<int, string>
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_FLAG;
		$vars[] = self::QUERY_OBJECT;
		$vars[] = self::QUERY_PAGE;
		$vars[] = self::QUERY_STYLESHEET;
		return $vars;
	}
}
