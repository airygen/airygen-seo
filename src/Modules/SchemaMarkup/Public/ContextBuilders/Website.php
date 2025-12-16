<?php
/**
 * WP-aware builder for website schema context.
 *
 * @package Airygen\Modules\SchemaMarkup\Public\ContextBuilders
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Public\ContextBuilders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SchemaMarkup\Domain\Contexts\WebsiteContext;

/**
 * Builds WebSite context from site metadata.
 */
final class Website {

	/**
	 * Build website context.
	 *
	 * @param string $site_name Site display name.
	 * @param string $site_url  Canonical site URL.
	 * @param string $locale    Site locale.
	 */
	public static function build( string $site_name, string $site_url, string $locale ): WebsiteContext {
		$search_url = esc_url_raw( add_query_arg( 's', '{search_term_string}', trailingslashit( $site_url ) ) );
		$action     = 'Search ' . $site_name;

		return WebsiteContext::from_values(
			$site_name,
			rtrim( $site_url, '/' ) . '/',
			$search_url,
			's',
			$action,
			$locale
		);
	}
}
