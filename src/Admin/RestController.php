<?php
/**
 * REST controller for aggregating Airygen SEO admin settings.
 *
 * @package Airygen\Admin
 */

declare(strict_types=1);

namespace Airygen\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Admin\ContentBlocks\Order as ContentBlockOrder;
use Airygen\Admin\Modules\Order as ModuleOrder;
use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Admin\Panels\Order as PanelOrder;
use Airygen\Admin\Panels\Visibility as PanelVisibility;
use Airygen\Modules\AuthorSeo\Admin\Settings as AuthorSeoSettings;
use Airygen\Modules\Breadcrumbs\Admin\Settings as BreadcrumbsSettings;
use Airygen\Modules\BrokenLinkChecker\Admin\Settings as BrokenLinkSettings;
use Airygen\Modules\BrokenLinkChecker\Admin\StatusReporter as BrokenLinkStatusReporter;
use Airygen\Modules\CodeSnippetManager\Admin\Settings as CodeSnippetManagerSettings;
use Airygen\Modules\Hreflang\Admin\Settings as HreflangSettings;
use Airygen\Modules\ImageSeo\Admin\Settings as ImageSeoSettings;
use Airygen\Modules\InstantIndexing\Admin\Settings as InstantIndexingSettings;
use Airygen\Modules\InstantIndexing\Domain\EngineRegistry;
use Airygen\Modules\LinkCounter\Admin\StatusReporter as LinkCounterStatusReporter;
use Airygen\Modules\LlmsTxt\Admin\Settings as LlmsTxtSettings;
use Airygen\Modules\LocalSeo\Admin\Settings as LocalSeoSettings;
use Airygen\Modules\MarkdownForAgents\Admin\Settings as MarkdownForAgentsSettings;
use Airygen\Modules\NotFoundManager\Admin\Settings as NotFoundManagerSettings;
use Airygen\Modules\Notify\Admin\Settings as NotifySettings;
use Airygen\Modules\Notify\Admin\StatusReporter as NotifyStatusReporter;
use Airygen\Modules\OnPageSeo\Admin\Settings as OnPageSeoSettings;
use Airygen\Modules\Redirects\Admin\Settings as RedirectsSettings;
use Airygen\Modules\RelatedPosts\Admin\Settings as RelatedPostsSettings;
use Airygen\Modules\Robots\Admin\Settings as RobotsSettings;
use Airygen\Modules\RssFeedSignature\Admin\Settings as RssFeedSignatureSettings;
use Airygen\Modules\SchemaMarkup\Admin\Settings as SchemaSettings;
use Airygen\Modules\ScoreCalculator\Admin\Settings as ScoreCalculatorSettings;
use Airygen\Modules\Sitemap\Admin\Settings as SitemapSettings;
use Airygen\Modules\SiteVerification\Admin\Settings as SiteVerificationSettings;
use Airygen\Modules\SocialCards\Admin\Settings as SocialSettings;
use Airygen\Modules\TableOfContents\Admin\Settings as TocSettings;
use Airygen\Modules\TaxonomySeo\Admin\Settings as TaxonomySeoSettings;
use Airygen\Modules\TopicCluster\Admin\Settings as TopicClusterSettings;
use Airygen\Modules\WooCommerceSeo\Admin\Settings as WooCommerceSeoSettings;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_Post_Type;
use WP_REST_Request;
use WP_REST_Response;
use WP_Taxonomy;

/**
 * Exposes a consolidated settings endpoint for the SPA.
 */
final class RestController {

	/**
	 * Session health check for editor bootstrapping.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_session_check(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'ok' => true,
			)
		);
	}

	/**
	 * Determine whether current user can access editor session check endpoint.
	 *
	 * @return bool
	 */
	public static function can_access_session_check(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Handle GET requests for settings.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_get(): WP_REST_Response {
		self::ensure_defaults();

		return rest_ensure_response( self::collect_settings() );
	}

	/**
	 * Handle POST/PUT requests for updating settings.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update( WP_REST_Request $request ) {
		self::ensure_defaults();

		$payload  = $request->get_json_params();
		$settings = is_array( $payload ) && isset( $payload['settings'] ) && is_array( $payload['settings'] )
		? $payload['settings']
		: null;

		if ( null === $settings ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_SETTINGS_INVALID_PAYLOAD,
				__( 'Invalid settings payload.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( isset( $settings['socialCards'] ) && is_array( $settings['socialCards'] ) ) {
			SocialSettings::update( $settings['socialCards'] );
		}

		if ( isset( $settings['schemaMarkup'] ) && is_array( $settings['schemaMarkup'] ) ) {
			SchemaSettings::update( $settings['schemaMarkup'] );
		}

		if ( isset( $settings['robots'] ) && is_array( $settings['robots'] ) ) {
			RobotsSettings::update( $settings['robots'] );
		}

		if ( isset( $settings['imageSeo'] ) && is_array( $settings['imageSeo'] ) ) {
			ImageSeoSettings::update( $settings['imageSeo'] );
		}

		if ( isset( $settings['hreflang'] ) && is_array( $settings['hreflang'] ) ) {
			HreflangSettings::update( $settings['hreflang'] );
		}

		if ( isset( $settings['sitemap'] ) && is_array( $settings['sitemap'] ) ) {
			SitemapSettings::update( $settings['sitemap'] );
		}

		if ( isset( $settings['codeSnippetManager'] ) && is_array( $settings['codeSnippetManager'] ) ) {
			CodeSnippetManagerSettings::update( $settings['codeSnippetManager'] );
		}

		if ( isset( $settings['siteVerification'] ) && is_array( $settings['siteVerification'] ) ) {
			SiteVerificationSettings::update( $settings['siteVerification'] );
		}

		if ( isset( $settings['rssFeedSignature'] ) && is_array( $settings['rssFeedSignature'] ) ) {
			RssFeedSignatureSettings::update( $settings['rssFeedSignature'] );
		}

		if ( isset( $settings['redirects'] ) && is_array( $settings['redirects'] ) ) {
			RedirectsSettings::update_rules(
				array(
					'rules' => isset( $settings['redirects']['rules'] ) && is_array( $settings['redirects']['rules'] )
						? $settings['redirects']['rules']
						: array(),
				)
			);
		}

		if ( isset( $settings['brokenLinkChecker'] ) && is_array( $settings['brokenLinkChecker'] ) ) {
			BrokenLinkSettings::update( $settings['brokenLinkChecker'] );
		}

		if ( isset( $settings['instantIndexing'] ) && is_array( $settings['instantIndexing'] ) ) {
			InstantIndexingSettings::update( $settings['instantIndexing'] );
		}

		if ( isset( $settings['scoreCalculator'] ) && is_array( $settings['scoreCalculator'] ) ) {
			ScoreCalculatorSettings::update( $settings['scoreCalculator'] );
		}

		if ( isset( $settings['modules'] ) && is_array( $settings['modules'] ) ) {
			ModuleSettings::update( self::enforce_module_dependencies( $settings['modules'] ) );
		}

		if ( isset( $settings['moduleOrder'] ) && is_array( $settings['moduleOrder'] ) ) {
			ModuleOrder::update( $settings['moduleOrder'] );
		}

		if ( isset( $settings['panelOrder'] ) && is_array( $settings['panelOrder'] ) ) {
			PanelOrder::update( $settings['panelOrder'] );
		}
		if ( isset( $settings['panelVisibility'] ) && is_array( $settings['panelVisibility'] ) ) {
			PanelVisibility::update( $settings['panelVisibility'] );
		}

		if ( isset( $settings['contentBlockOrder'] ) && is_array( $settings['contentBlockOrder'] ) ) {
			ContentBlockOrder::update( $settings['contentBlockOrder'] );
		}

		if ( isset( $settings['contentBlockGap'] ) || isset( $settings['contentBlockMarginTop'] ) ) {
			ContentBlockOrder::update_spacing(
				isset( $settings['contentBlockGap'] ) ? (int) $settings['contentBlockGap'] : ContentBlockOrder::get_gap(),
				isset( $settings['contentBlockMarginTop'] ) ? (int) $settings['contentBlockMarginTop'] : ContentBlockOrder::get_margin_top()
			);
		}

		if ( isset( $settings['onPageSeo'] ) && is_array( $settings['onPageSeo'] ) ) {
			OnPageSeoSettings::update( $settings['onPageSeo'] );
		}

		if ( isset( $settings['breadcrumbs'] ) && is_array( $settings['breadcrumbs'] ) ) {
			BreadcrumbsSettings::update( $settings['breadcrumbs'] );
		}

		if ( isset( $settings['toc'] ) && is_array( $settings['toc'] ) ) {
			TocSettings::update( $settings['toc'] );
		}

		if ( isset( $settings['topicCluster'] ) && is_array( $settings['topicCluster'] ) ) {
			TopicClusterSettings::update( $settings['topicCluster'] );
		}

		if ( isset( $settings['authorSeo'] ) && is_array( $settings['authorSeo'] ) ) {
			AuthorSeoSettings::update( $settings['authorSeo'] );
		}

		if ( isset( $settings['taxonomySeo'] ) && is_array( $settings['taxonomySeo'] ) ) {
			TaxonomySeoSettings::update( $settings['taxonomySeo'] );
		}

		if ( isset( $settings['wooCommerceSeo'] ) && is_array( $settings['wooCommerceSeo'] ) ) {
			WooCommerceSeoSettings::update( $settings['wooCommerceSeo'] );
		}

		if ( isset( $settings['localSeo'] ) && is_array( $settings['localSeo'] ) ) {
			LocalSeoSettings::update( $settings['localSeo'] );
		}
		if ( isset( $settings['relatedPosts'] ) && is_array( $settings['relatedPosts'] ) ) {
			RelatedPostsSettings::update( $settings['relatedPosts'] );
		}
		if ( isset( $settings['notFoundManager'] ) && is_array( $settings['notFoundManager'] ) ) {
			NotFoundManagerSettings::update( $settings['notFoundManager'] );
		}
		if ( isset( $settings['notify'] ) && is_array( $settings['notify'] ) ) {
			NotifySettings::update( $settings['notify'] );
		}
		if ( isset( $settings['markdownForAgents'] ) && is_array( $settings['markdownForAgents'] ) ) {
			MarkdownForAgentsSettings::update( $settings['markdownForAgents'] );
		}
		if ( isset( $settings['llmsTxt'] ) && is_array( $settings['llmsTxt'] ) ) {
			LlmsTxtSettings::update( $settings['llmsTxt'] );
		}

		do_action( Constants::HOOK_ADMIN_SETTINGS_UPDATE, $settings ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		return rest_ensure_response( self::collect_settings() );
	}

	/**
	 * Ensure all required options are created.
	 *
	 * @return void
	 */
	private static function ensure_defaults(): void {
		ModuleSettings::ensure_exists();
		ModuleOrder::ensure_exists();
		SocialSettings::ensure_exists();
		SchemaSettings::ensure_exists();
		BreadcrumbsSettings::ensure_exists();
		RobotsSettings::ensure_exists();
		ImageSeoSettings::ensure_exists();
		HreflangSettings::ensure_exists();
		SitemapSettings::ensure_exists();
		CodeSnippetManagerSettings::ensure_exists();
		SiteVerificationSettings::ensure_exists();
		RssFeedSignatureSettings::ensure_exists();
		RedirectsSettings::ensure_exists();
		BrokenLinkSettings::ensure_exists();
		InstantIndexingSettings::ensure_exists();
		OnPageSeoSettings::ensure_exists();
		TocSettings::ensure_exists();
		ScoreCalculatorSettings::ensure_exists();
		TopicClusterSettings::ensure_exists();
		AuthorSeoSettings::ensure_exists();
		TaxonomySeoSettings::ensure_exists();
		WooCommerceSeoSettings::ensure_exists();
		LocalSeoSettings::ensure_exists();
		RelatedPostsSettings::ensure_exists();
		NotFoundManagerSettings::ensure_exists();
		NotifySettings::ensure_exists();
		MarkdownForAgentsSettings::ensure_exists();
		LlmsTxtSettings::ensure_exists();
		PanelOrder::ensure_exists();
		PanelVisibility::ensure_exists();
		ContentBlockOrder::ensure_exists();
	}

	/**
	 * Collect settings and meta information for the SPA.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_settings(): array {
		$social              = SocialSettings::get();
		$schema              = SchemaSettings::get();
		$robots              = RobotsSettings::get();
		$image_seo           = ImageSeoSettings::get();
		$hreflang            = HreflangSettings::get();
		$sitemap             = SitemapSettings::get();
		$code_snippets       = CodeSnippetManagerSettings::get();
		$webmaster           = SiteVerificationSettings::get();
		$rss_feed_signature  = RssFeedSignatureSettings::get();
		$redirect            = RedirectsSettings::get_rules();
		$broken              = BrokenLinkSettings::get();
		$modules             = ModuleSettings::get();
		$indexnow            = InstantIndexingSettings::get();
		$toc                 = TocSettings::get();
		$topic               = TopicClusterSettings::get();
		$author              = AuthorSeoSettings::get();
		$taxonomy            = TaxonomySeoSettings::get();
		$woo                 = WooCommerceSeoSettings::get();
		$local               = LocalSeoSettings::get();
		$auto_related        = RelatedPostsSettings::get();
		$not_found_manager   = NotFoundManagerSettings::get();
		$notify              = NotifySettings::get();
		$markdown_for_agents = MarkdownForAgentsSettings::get();
		$llms_txt            = LlmsTxtSettings::get();

		$hreflang['manual_map'] = self::normalize_hreflang_map( $hreflang['manual_map'] ?? array() );

		$settings = array(
			'socialCards'           => $social,
			'schemaMarkup'          => $schema,
			'breadcrumbs'           => BreadcrumbsSettings::get(),
			'robots'                => $robots,
			'imageSeo'              => $image_seo,
			'hreflang'              => $hreflang,
			'sitemap'               => $sitemap,
			'codeSnippetManager'    => $code_snippets,
			'siteVerification'      => $webmaster,
			'rssFeedSignature'      => $rss_feed_signature,
			'redirects'             => $redirect,
			'brokenLinkChecker'     => $broken,
			'instantIndexing'       => $indexnow,
			'scoreCalculator'       => ScoreCalculatorSettings::get(),
			'modules'               => $modules,
			'onPageSeo'             => OnPageSeoSettings::get(),
			'toc'                   => $toc,
			'moduleOrder'           => ModuleOrder::get(),
			'panelOrder'            => PanelOrder::get(),
			'panelVisibility'       => PanelVisibility::get(),
			'contentBlockOrder'     => ContentBlockOrder::get(),
			'contentBlockGap'       => ContentBlockOrder::get_gap(),
			'contentBlockMarginTop' => ContentBlockOrder::get_margin_top(),
			'topicCluster'          => $topic,
			'authorSeo'             => $author,
			'taxonomySeo'           => $taxonomy,
			'wooCommerceSeo'        => $woo,
			'localSeo'              => $local,
			'relatedPosts'          => $auto_related,
			'notFoundManager'       => $not_found_manager,
			'notify'                => $notify,
			'markdownForAgents'     => $markdown_for_agents,
			'llmsTxt'               => $llms_txt,
		);
		$settings = apply_filters( Constants::HOOK_ADMIN_SETTINGS_PAYLOAD, $settings ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		$response = array(
			'settings'        => $settings,
			'wizardDismissed' => (bool) get_option( Constants::OPTION_WIZARD_DISMISSED, false ),
			'meta'            => array(
				'postTypes'         => self::available_post_types(),
				'taxonomies'        => self::available_taxonomies(),
				'redirectStatuses'  => self::redirect_status_options(),
				'redirectTypes'     => self::redirect_type_options(),
				'organizationTypes' => self::organization_type_options(),
				'articleTypes'      => self::article_type_options(),
				'schemaPostTypes'   => self::schema_post_type_options( $schema['post_type_defaults'] ?? array() ),
				'linkCounter'       => array(
					'status' => LinkCounterStatusReporter::get_status(),
				),
				'brokenLinkChecker' => array(
					'status' => BrokenLinkStatusReporter::get_status(),
				),
				'notify'            => array(
					'status' => NotifyStatusReporter::get_status(),
				),
				'instantIndexing'   => array(
					'engines' => self::indexnow_engine_meta(),
				),
				'scoreCalculator'   => ScoreCalculatorSettings::rules_meta(),
				'tocPreviewUrl'     => self::toc_preview_url(),
				'faqPreviewUrl'     => self::faq_preview_url(),
				'topicPreviewUrl'   => self::topic_preview_url(),
				'llmsBasePath'      => self::llms_base_path(),
				'wooCommerce'       => array(
					'active' => class_exists( 'WooCommerce' ),
				),
				'mediaImageSizes'   => self::media_image_sizes(),
			),
		);

		return $response;
	}

	/**
	 * Enforce module dependencies.
	 *
	 * @param array<string,mixed> $incoming Requested module state.
	 *
	 * @return array<string,mixed>
	 */
	private static function enforce_module_dependencies( array $incoming ): array {
		$link_enabled = isset( $incoming['linkSuggestions'] ) ? (bool) $incoming['linkSuggestions'] : true;
		if ( ! $link_enabled ) {
			$incoming['relatedPosts'] = false;
		}

		return $incoming;
	}

	/**
	 * Return available media image sizes.
	 *
	 * @return array<int,array{slug:string,label:string,width:int,height:int}>
	 */
	private static function media_image_sizes(): array {
		$sizes = get_intermediate_image_sizes();
		if ( ! is_array( $sizes ) ) {
			return array();
		}

		$additional_sizes = wp_get_additional_image_sizes();
		$items            = array();
		foreach ( $sizes as $size ) {
			if ( ! is_string( $size ) || '' === $size ) {
				continue;
			}

			$width  = 0;
			$height = 0;
			if ( isset( $additional_sizes[ $size ] ) && is_array( $additional_sizes[ $size ] ) ) {
				$width  = isset( $additional_sizes[ $size ]['width'] ) ? (int) $additional_sizes[ $size ]['width'] : 0;
				$height = isset( $additional_sizes[ $size ]['height'] ) ? (int) $additional_sizes[ $size ]['height'] : 0;
			} else {
				$width  = (int) get_option( "{$size}_size_w", 0 );
				$height = (int) get_option( "{$size}_size_h", 0 );
			}

			$items[] = array(
				'slug'   => $size,
				'label'  => ucwords( str_replace( array( '-', '_' ), ' ', $size ) ),
				'width'  => max( 0, $width ),
				'height' => max( 0, $height ),
			);
		}

		return $items;
	}

	/**
	 * Normalize hreflang manual map into an array of objects.
	 *
	 * @param array<string, string> $map Map of hreflang => URL.
	 *
	 * @return array<int, array{code: string, url: string}>
	 */
	private static function normalize_hreflang_map( array $map ): array {
		$normalized = array();

		foreach ( $map as $code => $url ) {
			$code = (string) $code;
			$url  = (string) $url;

			if ( '' === $code || '' === $url ) {
				continue;
			}

			$normalized[] = array(
				'code' => $code,
				'url'  => $url,
			);
		}

		return $normalized;
	}


	/**
	 * Retrieve available public post types.
	 *
	 * @return array<int, array{slug: string, label: string}>
	 */
	private static function available_post_types(): array {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		$choices = array();

		foreach ( $types as $type ) {
			if ( ! $type instanceof WP_Post_Type ) {
				continue;
			}
			if ( in_array( $type->name, array( 'wp_block', 'wp_navigation' ), true ) ) {
				continue;
			}
			$choices[] = array(
				'slug'  => $type->name,
				'label' => (string) $type->labels->singular_name,
			);
		}

		return $choices;
	}

	/**
	 * Build the TOC preview URL for the admin iframe.
	 *
	 * @return string
	 */
	private static function toc_preview_url(): string {
		$url = home_url( '/' );
		$url = add_query_arg( 'airygen_toc_preview', '1', $url );
		return esc_url_raw( $url );
	}

	/**
	 * Build the FAQ preview URL for the admin iframe.
	 *
	 * @return string
	 */
	private static function faq_preview_url(): string {
		$url = home_url( '/' );
		$url = add_query_arg( 'airygen_faq_preview', '1', $url );
		return esc_url_raw( $url );
	}

	/**
	 * Build the Topic Expansion preview URL for the admin iframe.
	 *
	 * @return string
	 */
	private static function topic_preview_url(): string {
		$url = home_url( '/' );
		$url = add_query_arg( 'airygen_topic_preview', '1', $url );
		return esc_url_raw( $url );
	}

	/**
	 * Build the site-relative base path for llms.txt display in admin.
	 *
	 * @return string
	 */
	private static function llms_base_path(): string {
		$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path || '/' === $path ) {
			return '';
		}

		return '/' . trim( $path, '/' );
	}

	/**
	 * Retrieve available public taxonomies.
	 *
	 * @return array<int, array{slug: string, label: string}>
	 */
	private static function available_taxonomies(): array {
		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		$choices = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $taxonomy instanceof WP_Taxonomy ) {
				continue;
			}

			$choices[] = array(
				'slug'  => $taxonomy->name,
				'label' => (string) $taxonomy->labels->singular_name,
			);
		}

		return $choices;
	}

	/**
	 * Options for redirect rule status selection.
	 *
	 * @return array<int, array{value: int, label: string}>
	 */
	private static function redirect_status_options(): array {
		return array(
			array(
				'value' => 301,
				'label' => '301',
			),
			array(
				'value' => 302,
				'label' => '302',
			),
			array(
				'value' => 307,
				'label' => '307',
			),
			array(
				'value' => 308,
				'label' => '308',
			),
		);
	}

	/**
	 * Options for redirect rule type selection.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function redirect_type_options(): array {
		return array(
			array(
				'value' => 'exact',
				'label' => __( 'Exact match', 'airygen-seo' ),
			),
			array(
				'value' => 'wildcard',
				'label' => __( 'Wildcard', 'airygen-seo' ),
			),
			array(
				'value' => 'regex',
				'label' => __( 'Regular expression', 'airygen-seo' ),
			),
		);
	}

	/**
	 * Suggested organization types for schema markup.
	 *
	 * @return array<int, string>
	 */
	private static function organization_type_options(): array {
		return array(
			'Organization',
			'Corporation',
			'EducationalOrganization',
			'GovernmentOrganization',
			'MedicalOrganization',
			'NGO',
			'NewsMediaOrganization',
			'LocalBusiness',
			'SportsOrganization',
		);
	}

	/**
	 * Suggested article types for schema markup.
	 *
	 * @return array<int, string>
	 */
	private static function article_type_options(): array {
		return array(
			'Article',
			'NewsArticle',
			'BlogPosting',
			'HowTo',
			'TechArticle',
		);
	}

	/**
	 * Build schema type options per post type.
	 *
	 * @param array<string, string> $selected Selected defaults keyed by post type.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function schema_post_type_options( array $selected ): array {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		$options = array();

		foreach ( $types as $type ) {
			if ( ! $type instanceof WP_Post_Type ) {
				continue;
			}

			$schema_options = self::options_for_post_type( $type );

			if ( empty( $schema_options ) ) {
				continue;
			}

			$slug_key        = sanitize_key( $type->name );
			$is_product_type = 'product' === $slug_key;
			$selected_value  = isset( $selected[ $slug_key ] ) ? (string) $selected[ $slug_key ] : '';
			if ( $is_product_type ) {
				$selected_value = 'Product';
			}

			$options[] = array(
				'slug'     => $type->name,
				'key'      => $slug_key,
				'label'    => (string) $type->labels->singular_name,
				'options'  => $schema_options,
				'selected' => $selected_value,
			);
		}

		return $options;
	}

	/**
	 * Determine schema choices for a post type.
	 *
	 * @param WP_Post_Type $type Post type object.
	 *
	 * @return array<int, array{value:string,label:string}>
	 */
	private static function options_for_post_type( WP_Post_Type $type ): array {
		$base_options = array(
			array(
				'value' => '',
				'label' => __( 'Use default article type', 'airygen-seo' ),
			),
			array(
				'value' => 'Article',
				'label' => __( 'Article', 'airygen-seo' ),
			),
			array(
				'value' => 'BlogPosting',
				'label' => __( 'Blog posting (BlogPosting)', 'airygen-seo' ),
			),
			array(
				'value' => 'NewsArticle',
				'label' => __( 'News article (NewsArticle)', 'airygen-seo' ),
			),
			array(
				'value' => 'TechArticle',
				'label' => __( 'Technical article (TechArticle)', 'airygen-seo' ),
			),
			array(
				'value' => 'ScholarlyArticle',
				'label' => __( 'Scholarly article (ScholarlyArticle)', 'airygen-seo' ),
			),
		);

		$slug         = strtolower( $type->name );
		$singular     = strtolower( (string) $type->labels->singular_name );
		$product_slug = 'product' === $slug;
		$is_course    = (bool) preg_match( '/(course|lesson)/', $slug . ' ' . $singular );

		if ( $product_slug ) {
			return array(
				array(
					'value' => 'Product',
					'label' => __( 'Product', 'airygen-seo' ),
				),
			);
		} elseif ( $is_course ) {
			$base_options = array_merge(
				array(
					array(
						'value' => 'Course',
						'label' => __( 'Course', 'airygen-seo' ),
					),
					array(
						'value' => 'CourseInstance',
						'label' => __( 'Course instance', 'airygen-seo' ),
					),
					array(
						'value' => 'HowTo',
						'label' => __( 'Step-by-step tutorial (HowTo)', 'airygen-seo' ),
					),
				),
				$base_options
			);
		}

		return $base_options;
	}
	/**
	 * Metadata for supported IndexNow engines.
	 *
	 * @return array<int, array{slug: string, label: string, endpoint: string}>
	 */
	private static function indexnow_engine_meta(): array {
		$options = array();
		foreach ( EngineRegistry::all() as $engine ) {
			$options[] = array(
				'slug'     => $engine->get_slug(),
				'label'    => $engine->get_label(),
				'endpoint' => $engine->get_default_endpoint(),
			);
		}
		return $options;
	}
}
