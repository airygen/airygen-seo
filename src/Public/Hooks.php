<?php
/**
 * Central registry for public-facing feature hooks.
 *
 * @package Airygen\Public
 */

declare(strict_types=1);

namespace Airygen\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\AuthorSeo\Public\Hooks as AuthorSeoHooks;
use Airygen\Modules\Breadcrumbs\Public\Hooks as BreadcrumbsHooks;
use Airygen\Modules\CodeSnippetManager\Public\Hooks as CodeSnippetManagerHooks;
use Airygen\Modules\Hreflang\Public\Hooks as HreflangHooks;
use Airygen\Modules\ImageSeo\Public\Hooks as ImageSeoHooks;
use Airygen\Modules\LlmsTxt\Public\Hooks as LlmsTxtHooks;
use Airygen\Modules\LocalSeo\Public\Hooks as LocalSeoHooks;
use Airygen\Modules\MarkdownForAgents\Public\Hooks as MarkdownForAgentsHooks;
use Airygen\Modules\NotFoundManager\Public\Hooks as NotFoundManagerHooks;
use Airygen\Modules\OnPageSeo\Public\Hooks as OnPageSeoHooks;
use Airygen\Modules\Redirects\Public\Hooks as RedirectsHooks;
use Airygen\Modules\RelatedPosts\Public\Hooks as RelatedPostsHooks;
use Airygen\Modules\Robots\Public\Hooks as RobotsHooks;
use Airygen\Modules\RssFeedSignature\Public\Hooks as RssFeedSignatureHooks;
use Airygen\Modules\SchemaMarkup\Public\Hooks as SchemaMarkupHooks;
use Airygen\Modules\Sitemap\Public\Hooks as SitemapHooks;
use Airygen\Modules\SiteVerification\Public\Hooks as SiteVerificationHooks;
use Airygen\Modules\SocialCards\Public\Hooks as SocialCardsHooks;
use Airygen\Modules\TableOfContents\Public\Hooks as TocHooks;
use Airygen\Modules\TaxonomySeo\Public\Hooks as TaxonomySeoHooks;
use Airygen\Modules\TopicCluster\Public\Hooks as TopicClusterHooks;
use Airygen\Modules\WooCommerceSeo\Public\Hooks as WooCommerceSeoHooks;
use Airygen\Public\AdminBar;

/**
 * Wires all public features in a feature-first architecture.
 */
final class Hooks {

	/**
	 * Fully qualified class names for available public features.
	 *
	 * @var array<int, string>
	 */
	private const FEATURE_HOOKS = array(
		AuthorSeoHooks::class,
		OnPageSeoHooks::class,
		SocialCardsHooks::class,
		ImageSeoHooks::class,
		SchemaMarkupHooks::class,
		RobotsHooks::class,
		BreadcrumbsHooks::class,
		TocHooks::class,
		TaxonomySeoHooks::class,
		HreflangHooks::class,
		SitemapHooks::class,
		CodeSnippetManagerHooks::class,
		SiteVerificationHooks::class,
		RssFeedSignatureHooks::class,
		RedirectsHooks::class,
		WooCommerceSeoHooks::class,
		LocalSeoHooks::class,
		TopicClusterHooks::class,
		RelatedPostsHooks::class,
		NotFoundManagerHooks::class,
		MarkdownForAgentsHooks::class,
		LlmsTxtHooks::class,
	);

	/**
	 * Mapping of feature classes to module toggle keys.
	 *
	 * @var array<string, string>
	 */
	private const FEATURE_MODULE_MAP = array(
		RobotsHooks::class             => 'robots',
		SitemapHooks::class            => 'sitemap',
		CodeSnippetManagerHooks::class => 'codeSnippetManager',
		SiteVerificationHooks::class   => 'siteVerification',
		RssFeedSignatureHooks::class   => 'rssFeedSignature',
		ImageSeoHooks::class           => 'imageSeo',
		BreadcrumbsHooks::class        => 'breadcrumbs',
		TocHooks::class                => 'toc',
		AuthorSeoHooks::class          => 'authorSeo',
		TaxonomySeoHooks::class        => 'taxonomySeo',
		WooCommerceSeoHooks::class     => 'wooCommerceSeo',
		LocalSeoHooks::class           => 'localSeo',
		TopicClusterHooks::class       => 'topicCluster',
		RelatedPostsHooks::class       => 'relatedPosts',
		NotFoundManagerHooks::class    => 'notFoundManager',
		MarkdownForAgentsHooks::class  => 'markdownForAgents',
		LlmsTxtHooks::class            => 'llmsTxt',
	);

	/**
	 * Register all available public features.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::FEATURE_HOOKS as $feature_class ) {
			if ( ! class_exists( $feature_class ) || ! method_exists( $feature_class, 'register' ) ) {
				self::maybe_debug( sprintf( 'public feature skipped: %s', $feature_class ) );
				continue;
			}

			if ( isset( self::FEATURE_MODULE_MAP[ $feature_class ] ) ) {
				$module_key = self::FEATURE_MODULE_MAP[ $feature_class ];
				if ( ! ModuleSettings::is_enabled( $module_key ) ) {
					self::maybe_debug(
						sprintf( 'public feature disabled via module toggle: %s', $feature_class )
					);
					continue;
				}
			}

			$feature_class::register();
			self::maybe_debug( sprintf( 'public feature registered: %s', $feature_class ) );
		}

		AdminBar::register();
	}

	/**
	 * Conditionally log debug messages when WP_DEBUG is enabled.
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	private static function maybe_debug( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[public] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
