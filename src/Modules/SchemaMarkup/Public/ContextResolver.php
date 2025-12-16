<?php
/**
 * Resolves data required for schema JSON-LD.
 *
 * @package Airygen\Modules\SchemaMarkup\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SchemaMarkup\Admin\Settings;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\Article;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\Author;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\Breadcrumb;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\Organization;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\WebPage;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\Website;

/**
 * Prepares context for the schema domain service.
 */
final class ContextResolver {

	/**
	 * Build context for the current request.
	 *
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$options    = Settings::get();
		$visibility = self::resolve_visibility( $options );
		$site_url   = home_url( '/' );
		$site_name  = get_bloginfo( 'name' );
		$site_desc  = get_bloginfo( 'description' );
		$locale     = get_locale();

		$organization_context = null;
		if ( $visibility['organization'] || $visibility['article'] ) {
			$organization_context = Organization::build( $options, $site_name, $site_url );
		}

		$website_context = $visibility['website']
		? Website::build( $site_name, $site_url, $locale )
		: null;
		$webpage_context = WebPage::from_current_query( $site_name, $site_desc, $locale );

		$breadcrumb_context = $visibility['breadcrumb']
		? Breadcrumb::from_current_query( $site_name, $site_url )
		: null;

		$article_context = null;
		if ( $visibility['article'] ) {
			$article_context = Article::from_current_query(
				$options,
				$site_name,
				$site_desc,
				$organization_context ?? Organization::build( $options, $site_name, $site_url )
			);
		}

		$author_context = Author::from_current_query();

		return array(
			'organization' => ( $visibility['organization'] && $organization_context ) ? $organization_context->to_array() : array(),
			'website'      => ( $visibility['website'] && $website_context ) ? $website_context->to_array() : array(),
			'webpage'      => $webpage_context,
			'breadcrumb'   => ( $visibility['breadcrumb'] && $breadcrumb_context ) ? $breadcrumb_context->to_array() : array(),
			'article'      => ( $visibility['article'] && $article_context ) ? $article_context->to_array() : array(),
			'author'       => $author_context,
		);
	}

	/**
	 * Resolve visibility configuration from options.
	 *
	 * @param array<string, mixed> $options Schema options.
	 *
	 * @return array<string, bool>
	 */
	private static function resolve_visibility( array $options ): array {
		$defaults = array(
			'organization' => false,
			'website'      => false,
			'breadcrumb'   => false,
			'article'      => false,
		);

		if ( empty( $options['visibility'] ) || ! is_array( $options['visibility'] ) ) {
			return $defaults;
		}

		return array(
			'organization' => ! empty( $options['visibility']['organization'] ),
			'website'      => ! empty( $options['visibility']['website'] ),
			'breadcrumb'   => ! empty( $options['visibility']['breadcrumb'] ),
			'article'      => ! empty( $options['visibility']['article'] ),
		);
	}
}
