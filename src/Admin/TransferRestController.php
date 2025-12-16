<?php
/**
 * REST controller for exporting and importing all plugin settings.
 *
 * @package Airygen\Admin
 */

declare(strict_types=1);

namespace Airygen\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Order as ModuleOrder;
use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Admin\Panels\Order as PanelOrder;
use Airygen\Admin\Panels\Visibility as PanelVisibility;
use Airygen\Constants;
use Airygen\Modules\AuthorSeo\Admin\Settings as AuthorSeoSettings;
use Airygen\Modules\Breadcrumbs\Admin\Settings as BreadcrumbsSettings;
use Airygen\Modules\BrokenLinkChecker\Admin\Settings as BrokenLinkSettings;
use Airygen\Modules\CodeSnippetManager\Admin\Settings as CodeSnippetManagerSettings;
use Airygen\Modules\Hreflang\Admin\Settings as HreflangSettings;
use Airygen\Modules\ImageSeo\Admin\Settings as ImageSeoSettings;
use Airygen\Modules\InstantIndexing\Admin\Settings as InstantIndexingSettings;
use Airygen\Modules\LlmsTxt\Admin\Settings as LlmsTxtSettings;
use Airygen\Modules\LocalSeo\Admin\Settings as LocalSeoSettings;
use Airygen\Modules\MarkdownForAgents\Admin\Settings as MarkdownForAgentsSettings;
use Airygen\Modules\NotFoundManager\Admin\Settings as NotFoundManagerSettings;
use Airygen\Modules\Notify\Admin\Settings as NotifySettings;
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
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles export and import of all plugin settings.
 */
final class TransferRestController {

	private const EXPORT_VERSION = '1.0';

	/**
	 * Check whether the current user can manage settings.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Export all plugin settings as a JSON snapshot.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_export(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'plugin'      => 'airygen-seo',
				'version'     => self::EXPORT_VERSION,
				'exported_at' => gmdate( 'c' ),
				'settings'    => self::build_export_settings(),
			)
		);
	}

	/**
	 * Import all plugin settings from a JSON snapshot.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_import( WP_REST_Request $request ) {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'invalid_payload',
				__( 'Invalid import payload.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$settings = isset( $body['settings'] ) && is_array( $body['settings'] )
		? $body['settings']
		: null;

		if ( null === $settings ) {
			return new WP_Error(
				'missing_settings',
				__( 'Missing settings key in import payload.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		self::import_settings( $settings );

		do_action( Constants::HOOK_TRANSFER_IMPORT_SETTINGS, $settings ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Build the transfer export settings payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_export_settings(): array {
		$hreflang               = HreflangSettings::get();
		$hreflang['manual_map'] = self::normalize_hreflang_map( $hreflang['manual_map'] ?? array() );

		$settings = array(
			'socialCards'        => SocialSettings::get(),
			'schemaMarkup'       => SchemaSettings::get(),
			'breadcrumbs'        => BreadcrumbsSettings::get(),
			'robots'             => RobotsSettings::get(),
			'imageSeo'           => ImageSeoSettings::get(),
			'hreflang'           => $hreflang,
			'sitemap'            => SitemapSettings::get(),
			'codeSnippetManager' => CodeSnippetManagerSettings::get(),
			'siteVerification'   => SiteVerificationSettings::get(),
			'rssFeedSignature'   => RssFeedSignatureSettings::get(),
			'redirects'          => RedirectsSettings::get_rules(),
			'brokenLinkChecker'  => BrokenLinkSettings::get(),
			'instantIndexing'    => InstantIndexingSettings::get(),
			'scoreCalculator'    => ScoreCalculatorSettings::get(),
			'modules'            => ModuleSettings::get(),
			'onPageSeo'          => OnPageSeoSettings::get(),
			'toc'                => TocSettings::get(),
			'moduleOrder'        => ModuleOrder::get(),
			'panelOrder'         => PanelOrder::get(),
			'panelVisibility'    => PanelVisibility::get(),
			'topicCluster'       => TopicClusterSettings::get(),
			'authorSeo'          => AuthorSeoSettings::get(),
			'taxonomySeo'        => TaxonomySeoSettings::get(),
			'wooCommerceSeo'     => WooCommerceSeoSettings::get(),
			'localSeo'           => LocalSeoSettings::get(),
			'relatedPosts'       => RelatedPostsSettings::get(),
			'notFoundManager'    => NotFoundManagerSettings::get(),
			'notify'             => NotifySettings::get(),
			'markdownForAgents'  => MarkdownForAgentsSettings::get(),
			'llmsTxt'            => LlmsTxtSettings::get(),
		);

		$filtered_settings = apply_filters( Constants::HOOK_TRANSFER_EXPORT_SETTINGS, $settings ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.
		if ( ! is_array( $filtered_settings ) ) {
			_doing_it_wrong(
				'Airygen\\Admin\\TransferRestController::build_export_settings',
				esc_html__( 'Transfer export settings filter must return an array.', 'airygen-seo' ),
				esc_html( self::EXPORT_VERSION )
			);
			return $settings;
		}

		return $filtered_settings;
	}

	/**
	 * Import the base transfer settings payload.
	 *
	 * @param array<string, mixed> $settings Import settings payload.
	 *
	 * @return void
	 */
	private static function import_settings( array $settings ): void {
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
			ModuleSettings::update( $settings['modules'] );
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
	}

	/**
	 * Return current uninstall preferences.
	 *
	 * On multisite the preferences are stored as a network option so that a
	 * single set of choices covers the entire network.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_get_uninstall(): WP_REST_Response {
		$raw   = is_multisite()
		? get_site_option( Constants::OPTION_UNINSTALL, array() )
		: get_option( Constants::OPTION_UNINSTALL, array() );
		$prefs = is_array( $raw ) ? $raw : array();

		return rest_ensure_response(
			array(
				'clearTables'  => ! empty( $prefs['clearTables'] ),
				'clearOptions' => ! empty( $prefs['clearOptions'] ),
				'clearMeta'    => ! empty( $prefs['clearMeta'] ),
			)
		);
	}

	/**
	 * Save uninstall preferences.
	 *
	 * On multisite the preferences are stored as a network option.
	 * Only a super admin may change network-level preferences.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_uninstall( WP_REST_Request $request ) {
		if ( is_multisite() && ! current_user_can( 'manage_network_options' ) ) {
			return new WP_Error(
				'forbidden',
				__( 'Only network administrators can change uninstall preferences.', 'airygen-seo' ),
				array( 'status' => 403 )
			);
		}

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'invalid_payload',
				__( 'Invalid uninstall preferences payload.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$prefs = array(
			'clearTables'  => ! empty( $body['clearTables'] ),
			'clearOptions' => ! empty( $body['clearOptions'] ),
			'clearMeta'    => ! empty( $body['clearMeta'] ),
		);

		if ( is_multisite() ) {
			update_site_option( Constants::OPTION_UNINSTALL, $prefs );
		} else {
			update_option( Constants::OPTION_UNINSTALL, $prefs, false );
		}

		return rest_ensure_response( array( 'ok' => true ) );
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
}
