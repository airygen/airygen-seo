<?php
/**
 * Central registry for admin-facing feature hooks.
 *
 * @package Airygen\Admin
 */

declare(strict_types=1);

namespace Airygen\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Menu;
use Airygen\Admin\PostListColumns\Hooks as SeoOverviewColumnHooks;
use Airygen\Modules\AuthorSeo\Admin\Hooks as AuthorSeoAdminHooks;
use Airygen\Modules\Breadcrumbs\Admin\Hooks as BreadcrumbsHooks;
use Airygen\Modules\CodeSnippetManager\Admin\Hooks as CodeSnippetManagerHooks;
use Airygen\Modules\Hreflang\Admin\Hooks as HreflangHooks;
use Airygen\Modules\ImageSeo\Admin\Hooks as ImageSeoHooks;
use Airygen\Modules\InstantIndexing\Admin\Hooks as InstantIndexingHooks;
use Airygen\Modules\LinkSuggestions\Admin\Hooks as LinkSuggestionsAdminHooks;
use Airygen\Modules\LlmsTxt\Admin\Hooks as LlmsTxtHooks;
use Airygen\Modules\MarkdownForAgents\Admin\Hooks as MarkdownForAgentsHooks;
use Airygen\Modules\NotFoundManager\Admin\Hooks as NotFoundManagerHooks;
use Airygen\Modules\Notify\Admin\Hooks as NotifyHooks;
use Airygen\Modules\OnPageSeo\Admin\Hooks as OnPageSeoHooks;
use Airygen\Modules\Redirects\Admin\Hooks as RedirectsHooks;
use Airygen\Modules\RelatedPosts\Admin\Hooks as RelatedPostsHooks;
use Airygen\Modules\Robots\Admin\Hooks as RobotsHooks;
use Airygen\Modules\RssFeedSignature\Admin\Hooks as RssFeedSignatureHooks;
use Airygen\Modules\SchemaMarkup\Admin\Hooks as SchemaMarkupHooks;
use Airygen\Modules\ScoreCalculator\Admin\Hooks as ScoreHooks;
use Airygen\Modules\Sitemap\Admin\Hooks as SitemapHooks;
use Airygen\Modules\SiteVerification\Admin\Hooks as SiteVerificationHooks;
use Airygen\Modules\SitewideSeo\Admin\Hooks as SiteHealthSeoHooks;
use Airygen\Modules\SocialCards\Admin\Hooks as SocialCardsHooks;
use Airygen\Modules\TableOfContents\Admin\Hooks as TocHooks;
use Airygen\Modules\TaxonomySeo\Admin\Hooks as TaxonomySeoHooks;
use Airygen\Support\Debug\Logger;

/**
 * Wires all admin features in a feature-first architecture.
 */
final class Hooks {

	/**
	 * Fully qualified class names for available admin features.
	 *
	 * @var array<int, string>
	 */
	private const FEATURE_HOOKS = array(
		Menu::class,
		AuthorSeoAdminHooks::class,
		OnPageSeoHooks::class,
		ScoreHooks::class,
		SocialCardsHooks::class,
		BreadcrumbsHooks::class,
		ImageSeoHooks::class,
		SchemaMarkupHooks::class,
		RobotsHooks::class,
		SeoOverviewColumnHooks::class,
		LinkSuggestionsAdminHooks::class,
		HreflangHooks::class,
		SitemapHooks::class,
		CodeSnippetManagerHooks::class,
		SiteVerificationHooks::class,
		RedirectsHooks::class,
		RssFeedSignatureHooks::class,
		SiteHealthSeoHooks::class,
		InstantIndexingHooks::class,
		TocHooks::class,
		TaxonomySeoHooks::class,
		RelatedPostsHooks::class,
		NotFoundManagerHooks::class,
		NotifyHooks::class,
		MarkdownForAgentsHooks::class,
		LlmsTxtHooks::class,
	);

	/**
	 * Register all available admin features.
	 *
	 * @return void
	 */
	public static function register(): void {
		Logger::log( 'debug', 'Admin\\Hooks::register triggered.' );
		foreach ( self::FEATURE_HOOKS as $feature_class ) {
			if ( ! class_exists( $feature_class ) || ! method_exists( $feature_class, 'register' ) ) {
				self::log_debug( sprintf( 'admin feature skipped: %s', $feature_class ) );
				continue;
			}

			$feature_class::register();
			self::log_debug( sprintf( 'admin feature registered: %s', $feature_class ) );
		}
	}

	/**
	 * Log debug messages via Airygen logger.
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	private static function log_debug( string $message ): void {
		Logger::log( 'debug', '[admin] ' . $message );
	}
}
