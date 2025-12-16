<?php
/**
 * REST controller for All-in-One SEO Pack migrations.
 *
 * @package Airygen\Admin\Migration
 */

declare(strict_types=1);

namespace Airygen\Admin\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\Breadcrumbs\Admin\Settings as BreadcrumbsSettings;
use Airygen\Modules\OnPageSeo\Admin\Settings as OnPageSeoSettings;
use Airygen\Modules\Redirects\Admin\Settings as RedirectsSettings;
use Airygen\Modules\RssFeedSignature\Admin\Settings as RssFeedSignatureSettings;
use Airygen\Modules\SchemaMarkup\Admin\Settings as SchemaSettings;
use Airygen\Modules\SiteVerification\Admin\Settings as SiteVerificationSettings;
use Airygen\Modules\SocialCards\Admin\Settings as SocialSettings;
use Airygen\Support\Meta\PostData;
use WP_Query;
use WP_REST_Response;

/**
 * Handles migration-related REST requests for AIOSEO.
 */
final class AioseoRestController {

	private const BATCH_SIZE = 10;

	private const REDIRECT_CURSOR_OPTION = Constants::OPTION_AIOSEO_REDIRECT_CURSOR;

	/**
	 * Check whether the current user can manage migrations.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return migration status for AIOSEO.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_status(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'progress' => self::build_progress(),
				'settings' => array(
					'available' => self::has_aioseo_settings(),
				),
			)
		);
	}

	/**
	 * Import a batch of AIOSEO post meta.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_import(): WP_REST_Response {
		$processed = self::import_batch();

		return rest_ensure_response(
			array(
				'processed' => $processed,
				'progress'  => self::build_progress(),
			)
		);
	}

	/**
	 * Import AIOSEO global settings.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_import_settings(): WP_REST_Response {
		$summary = self::import_settings();

		return rest_ensure_response(
			array(
				'settings' => $summary,
			)
		);
	}

	/**
	 * Import AIOSEO redirect rules.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_import_redirects(): WP_REST_Response {
		$processed = self::import_redirects_batch();

		return rest_ensure_response(
			array(
				'processed' => $processed,
				'progress'  => self::build_redirects_progress(),
			)
		);
	}

	/**
	 * Return migration status for AIOSEO redirects.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_redirects_status(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'progress' => self::build_redirects_progress(),
			)
		);
	}

	/**
	 * Import a batch of posts that have not been migrated yet.
	 *
	 * @return int Number of posts processed.
	 */
	private static function import_batch(): int {
		$query = new WP_Query(
			array(
				'post_type'      => self::eligible_post_types(),
				'post_status'    => self::eligible_statuses(),
				'posts_per_page' => self::BATCH_SIZE,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Migration batch filter.
					array(
						'key'     => Constants::META_AIOSEO_MIGRATED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$processed = 0;
		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			self::migrate_post_meta( $post_id );
			update_post_meta( $post_id, Constants::META_AIOSEO_MIGRATED, gmdate( 'c' ) );
			++$processed;
		}

		return $processed;
	}

	/**
	 * Migrate AIOSEO post meta for a single post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private static function migrate_post_meta( int $post_id ): void {
		$record    = self::get_aioseo_post_record( $post_id );
		$post_data = PostData::get( $post_id );

		if ( '' === $post_data['title'] ) {
			$source_value = sanitize_text_field( self::source_meta_value( $record, $post_id, 'title' ) );
			if ( '' !== $source_value ) {
				$post_data['title'] = $source_value;
			}
		}

		if ( '' === $post_data['description'] ) {
			$source_value = sanitize_text_field( self::source_meta_value( $record, $post_id, 'description' ) );
			if ( '' !== $source_value ) {
				$post_data['description'] = $source_value;
			}
		}

		if ( '' === $post_data['canonical'] ) {
			$source_value = esc_url_raw( self::source_meta_value( $record, $post_id, 'canonical_url' ) );
			if ( '' !== $source_value ) {
				$post_data['canonical'] = $source_value;
			}
		}

		$current_robots = $post_data['robots'];
		if ( '' === (string) $current_robots ) {
			$robots = self::build_robots_directive( $record, $post_id );
			if ( '' !== $robots ) {
				$post_data['robots'] = $robots;
			}
		}

		$current_schema_type = $post_data['schemaArticleType'];
		if ( '' === (string) $current_schema_type ) {
			$schema_type = self::extract_schema_article_type( $record );
			if ( '' !== $schema_type ) {
				$post_data['schemaArticleType'] = $schema_type;
			}
		}

		PostData::save( $post_id, $post_data );

		KeyphraseMapper::apply(
			$post_id,
			array(
				$record['keyphrases'] ?? null,
				get_post_meta( $post_id, '_aioseo_keywords', true ),
				get_post_meta( $post_id, '_aioseop_keywords', true ),
			)
		);
	}

	/**
	 * Get source value from AIOSEO table row, with postmeta fallback.
	 *
	 * @param array<string, mixed> $record  AIOSEO row.
	 * @param int                  $post_id Post ID.
	 * @param string               $key     Source key.
	 *
	 * @return string
	 */
	private static function source_meta_value( array $record, int $post_id, string $key ): string {
		$value = self::get_string( $record, $key );
		if ( '' !== $value ) {
			return $value;
		}

		$fallback_map = array(
			'title'               => '_aioseo_title',
			'description'         => '_aioseo_description',
			'canonical_url'       => '_aioseo_canonical_url',
			'og_title'            => '_aioseo_og_title',
			'og_description'      => '_aioseo_og_description',
			'twitter_title'       => '_aioseo_twitter_title',
			'twitter_description' => '_aioseo_twitter_description',
		);

		if ( isset( $fallback_map[ $key ] ) ) {
			$fallback = trim( (string) get_post_meta( $post_id, $fallback_map[ $key ], true ) );
			if ( '' !== $fallback ) {
				return $fallback;
			}
		}

		$legacy_fallback_map = array(
			'title'         => '_aioseop_title',
			'description'   => '_aioseop_description',
			'canonical_url' => '_aioseop_custom_link',
		);

		if ( isset( $legacy_fallback_map[ $key ] ) ) {
			$legacy = trim( (string) get_post_meta( $post_id, $legacy_fallback_map[ $key ], true ) );
			if ( '' !== $legacy ) {
				return $legacy;
			}
		}

		if ( 'og_image_custom_url' === $key || 'og_image_url' === $key ) {
			$legacy_image = self::legacy_aioseop_opengraph_value( $post_id, 'aioseop_opengraph_settings_customimg' );
			if ( '' !== $legacy_image ) {
				return $legacy_image;
			}
		}

		if ( 'twitter_image_custom_url' === $key || 'twitter_image_url' === $key ) {
			$legacy_image = self::legacy_aioseop_opengraph_value( $post_id, 'aioseop_opengraph_settings_customimg_twitter' );
			if ( '' !== $legacy_image ) {
				return $legacy_image;
			}
		}

		return '';
	}

	/**
	 * Build robots directive from AIOSEO row.
	 *
	 * @param array<string, mixed> $record AIOSEO row.
	 * @param int                  $post_id Post ID.
	 *
	 * @return string
	 */
	private static function build_robots_directive( array $record, int $post_id ): string {
		if ( empty( $record ) ) {
			$legacy_noindex  = 'on' === strtolower( trim( (string) get_post_meta( $post_id, '_aioseop_noindex', true ) ) );
			$legacy_nofollow = 'on' === strtolower( trim( (string) get_post_meta( $post_id, '_aioseop_nofollow', true ) ) );

			if ( ! $legacy_noindex && ! $legacy_nofollow ) {
				return '';
			}

			$legacy_directives = array(
				$legacy_noindex ? 'noindex' : 'index',
				$legacy_nofollow ? 'nofollow' : 'follow',
			);

			return implode( ',', $legacy_directives );
		}

		$robots_default = self::is_truthy( $record['robots_default'] ?? true );
		$noindex        = self::is_truthy( $record['robots_noindex'] ?? false );
		$nofollow       = self::is_truthy( $record['robots_nofollow'] ?? false );

		$directives = array(
			$noindex ? 'noindex' : 'index',
			$nofollow ? 'nofollow' : 'follow',
		);

		$flag_map = array(
			'robots_noarchive'    => 'noarchive',
			'robots_noimageindex' => 'noimageindex',
			'robots_nosnippet'    => 'nosnippet',
			'robots_notranslate'  => 'notranslate',
		);

		foreach ( $flag_map as $flag_key => $directive ) {
			if ( self::is_truthy( $record[ $flag_key ] ?? false ) ) {
				$directives[] = $directive;
			}
		}

		$max_snippet = isset( $record['robots_max_snippet'] ) ? (int) $record['robots_max_snippet'] : null;
		if ( null !== $max_snippet && $max_snippet >= 0 ) {
			$directives[] = 'max-snippet:' . (string) $max_snippet;
		}

		$max_video = isset( $record['robots_max_videopreview'] ) ? (int) $record['robots_max_videopreview'] : null;
		if ( null !== $max_video && $max_video >= 0 ) {
			$directives[] = 'max-video-preview:' . (string) $max_video;
		}

		$max_image = self::get_string( $record, 'robots_max_imagepreview' );
		if ( '' !== $max_image ) {
			$directives[] = 'max-image-preview:' . $max_image;
		}

		$directives = array_values( array_unique( array_filter( $directives ) ) );
		$directive  = implode( ',', $directives );

		if ( $robots_default && 'index,follow' === $directive ) {
			return '';
		}

		if ( 'index,follow' === $directive ) {
			return '';
		}

		return $directive;
	}

	/**
	 * Extract post-level schema article type.
	 *
	 * @param array<string, mixed> $record AIOSEO row.
	 *
	 * @return string
	 */
	private static function extract_schema_article_type( array $record ): string {
		$schema_type = self::get_string( $record, 'schema_type' );
		if ( '' === $schema_type ) {
			return '';
		}

		$options = array();
		$raw     = $record['schema_type_options'] ?? null;
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$options = $decoded;
			}
		}

		if (
			isset( $options['article'] ) &&
			is_array( $options['article'] ) &&
			isset( $options['article']['articleType'] )
		) {
			$article_type = sanitize_text_field( (string) $options['article']['articleType'] );
			if ( '' !== $article_type ) {
				return $article_type;
			}
		}

		$type = sanitize_text_field( $schema_type );
		if ( '' === $type || 'default' === strtolower( $type ) ) {
			return '';
		}

		return $type;
	}

	/**
	 * Build progress details for post meta migration.
	 *
	 * @return array<string, int|bool>
	 */
	private static function build_progress(): array {
		$total    = self::count_posts();
		$migrated = self::count_posts( true );

		if ( $total < $migrated ) {
			$migrated = $total;
		}

		$percent = 0;
		if ( $total > 0 ) {
			$percent = (int) round( ( $migrated / $total ) * 100 );
		}

		$completed = $total > 0 && $migrated >= $total;
		if ( 0 === $total ) {
			$completed = true;
		}

		return array(
			'total'     => $total,
			'migrated'  => $migrated,
			'remaining' => max( 0, $total - $migrated ),
			'percent'   => $percent,
			'completed' => $completed,
			'batchSize' => self::BATCH_SIZE,
		);
	}

	/**
	 * Check if AIOSEO settings are present.
	 *
	 * @return bool
	 */
	private static function has_aioseo_settings(): bool {
		$options = self::get_aioseo_options();
		return ! empty( $options );
	}

	/**
	 * Import AIOSEO global settings into Airygen options.
	 *
	 * @return array<string, mixed>
	 */
	private static function import_settings(): array {
		$source = self::get_aioseo_options();

		$onpage      = OnPageSeoSettings::get();
		$schema      = SchemaSettings::get();
		$social      = SocialSettings::get();
		$breadcrumbs = BreadcrumbsSettings::get();
		$webmaster   = SiteVerificationSettings::get();
		$rss         = RssFeedSignatureSettings::get();

		$onpage_changed      = false;
		$schema_changed      = false;
		$social_changed      = false;
		$breadcrumbs_changed = false;
		$webmaster_changed   = false;
		$rss_changed         = false;

		$search_global = $source['searchAppearance']['global'] ?? null;
		if ( is_array( $search_global ) ) {
			if ( isset( $search_global['separator'] ) ) {
				$separator = self::normalize_separator( (string) $search_global['separator'] );
				if ( '' !== $separator ) {
					$onpage['templates']['separator'] = $separator;
					$onpage_changed                   = true;
				}
			}

			if ( isset( $search_global['siteTitle'] ) ) {
				$onpage['templates']['global']['title'] = self::map_tokens( (string) $search_global['siteTitle'] );
				$onpage_changed                         = true;
			}

			if ( isset( $search_global['metaDescription'] ) ) {
				$onpage['templates']['global']['description'] = self::map_tokens( (string) $search_global['metaDescription'] );
				$onpage_changed                               = true;
			}

			$schema_source = $search_global['schema'] ?? null;
			if ( is_array( $schema_source ) ) {
				$site_represents = isset( $schema_source['siteRepresents'] ) ? (string) $schema_source['siteRepresents'] : '';
				if ( 'person' === strtolower( $site_represents ) ) {
					$schema['organization_type'] = 'Person';
					$person_name                 = isset( $schema_source['personName'] ) ? sanitize_text_field( (string) $schema_source['personName'] ) : '';
					if ( '' !== $person_name ) {
						$schema['organization_name'] = $person_name;
					}
					$person_logo = isset( $schema_source['personLogo'] ) ? esc_url_raw( (string) $schema_source['personLogo'] ) : '';
					if ( '' !== $person_logo ) {
						$schema['organization_logo_url'] = $person_logo;
					}
					$schema_changed = true;
				} else {
					$schema['organization_type'] = 'Organization';
					$org_name                    = isset( $schema_source['organizationName'] ) ? sanitize_text_field( (string) $schema_source['organizationName'] ) : '';
					if ( '' !== $org_name ) {
						$schema['organization_name'] = $org_name;
					}
					$org_logo = isset( $schema_source['organizationLogo'] ) ? esc_url_raw( (string) $schema_source['organizationLogo'] ) : '';
					if ( '' !== $org_logo ) {
						$schema['organization_logo_url'] = $org_logo;
					}
					$schema_changed = true;
				}
			}
		}

		$social_source = $source['social'] ?? null;
		if ( is_array( $social_source ) ) {
			$facebook_general = $social_source['facebook']['general'] ?? null;
			if ( is_array( $facebook_general ) ) {
				if ( isset( $facebook_general['enable'] ) ) {
					$social['og']['enabled'] = self::is_truthy( $facebook_general['enable'] );
					$social_changed          = true;
				}
				if ( isset( $facebook_general['defaultImagePosts'] ) ) {
					$social['og']['default_image_url'] = esc_url_raw( (string) $facebook_general['defaultImagePosts'] );
					$social_changed                    = true;
				}
			}

			$twitter_general = $social_source['twitter']['general'] ?? null;
			if ( is_array( $twitter_general ) ) {
				if ( isset( $twitter_general['enable'] ) ) {
					$social['twitter']['enabled'] = self::is_truthy( $twitter_general['enable'] );
					$social_changed               = true;
				}
				if ( isset( $twitter_general['useOgData'] ) ) {
					$social['twitter']['inherit_og_image'] = self::is_truthy( $twitter_general['useOgData'] );
					$social_changed                        = true;
				}
				if ( isset( $twitter_general['defaultCardType'] ) ) {
					$card_type = sanitize_text_field( (string) $twitter_general['defaultCardType'] );
					if ( 'summary' === $card_type || 'summary_large_image' === $card_type ) {
						$social['twitter']['card_type'] = $card_type;
						$social_changed                 = true;
					}
				}
				if ( isset( $twitter_general['defaultImagePosts'] ) ) {
					$social['twitter']['default_image_url'] = esc_url_raw( (string) $twitter_general['defaultImagePosts'] );
					$social_changed                         = true;
				}
			}

			$profile_url = $social_source['profiles']['urls']['twitterUrl'] ?? '';
			if ( is_string( $profile_url ) && '' !== trim( $profile_url ) ) {
				$handle = self::extract_twitter_handle( $profile_url );
				if ( '' !== $handle ) {
					$social['twitter']['site_handle'] = $handle;
					$social_changed                   = true;
				}
			}
		}

		$breadcrumbs_source = $source['breadcrumbs'] ?? null;
		if ( is_array( $breadcrumbs_source ) ) {
			if ( isset( $breadcrumbs_source['separator'] ) ) {
				$breadcrumbs['separator'] = (string) $breadcrumbs_source['separator'];
				$breadcrumbs_changed      = true;
			}
			if ( isset( $breadcrumbs_source['breadcrumbPrefix'] ) ) {
				$breadcrumbs['prefix'] = sanitize_text_field( (string) $breadcrumbs_source['breadcrumbPrefix'] );
				$breadcrumbs_changed   = true;
			}
			if ( isset( $breadcrumbs_source['homepageLink'] ) ) {
				$breadcrumbs['home']['display'] = self::is_truthy( $breadcrumbs_source['homepageLink'] );
				$breadcrumbs_changed            = true;
			}
			if ( isset( $breadcrumbs_source['homepageLabel'] ) ) {
				$breadcrumbs['home']['label'] = sanitize_text_field( (string) $breadcrumbs_source['homepageLabel'] );
				$breadcrumbs_changed          = true;
			}
			if ( isset( $breadcrumbs_source['archiveFormat'] ) ) {
				$breadcrumbs['labels']['archive'] = sanitize_text_field( (string) $breadcrumbs_source['archiveFormat'] );
				$breadcrumbs_changed              = true;
			}
			if ( isset( $breadcrumbs_source['searchResultFormat'] ) ) {
				$breadcrumbs['labels']['search'] = sanitize_text_field( (string) $breadcrumbs_source['searchResultFormat'] );
				$breadcrumbs_changed             = true;
			}
			if ( isset( $breadcrumbs_source['errorFormat404'] ) ) {
				$breadcrumbs['labels']['error'] = sanitize_text_field( (string) $breadcrumbs_source['errorFormat404'] );
				$breadcrumbs_changed            = true;
			}
			if ( isset( $breadcrumbs_source['showCurrentItem'] ) ) {
				$breadcrumbs['display']['showCurrent'] = self::is_truthy( $breadcrumbs_source['showCurrentItem'] );
				$breadcrumbs_changed                   = true;
			}
			if ( isset( $breadcrumbs_source['categoryFullHierarchy'] ) ) {
				$breadcrumbs['display']['showAncestors'] = self::is_truthy( $breadcrumbs_source['categoryFullHierarchy'] );
				$breadcrumbs_changed                     = true;
			}
			if ( isset( $breadcrumbs_source['showBlogHome'] ) ) {
				$breadcrumbs['display']['showBlog'] = self::is_truthy( $breadcrumbs_source['showBlogHome'] );
				$breadcrumbs_changed                = true;
			}
		}

		$webmaster_source = $source['siteVerification'] ?? $source['webmasterTools'] ?? null;
		if ( is_array( $webmaster_source ) ) {
			$webmaster_map = array(
				'google'    => 'google',
				'bing'      => 'bing',
				'yandex'    => 'yandex',
				'baidu'     => 'baidu',
				'pinterest' => 'pinterest',
			);

			foreach ( $webmaster_map as $source_key => $target_key ) {
				if ( ! isset( $webmaster_source[ $source_key ] ) ) {
					continue;
				}

				$value = sanitize_text_field( (string) $webmaster_source[ $source_key ] );
				if ( '' === $value ) {
					continue;
				}

				$webmaster[ $target_key ] = $value;
				$webmaster_changed        = true;
			}
		}

		$rss_source = $source['rssContent'] ?? null;
		if ( is_array( $rss_source ) ) {
			if ( isset( $rss_source['before'] ) ) {
				$rss['before_content'] = wp_kses_post( self::map_tokens( (string) $rss_source['before'] ) );
				$rss_changed           = true;
			}

			if ( isset( $rss_source['after'] ) ) {
				$rss['after_content'] = wp_kses_post( self::map_tokens( (string) $rss_source['after'] ) );
				$rss_changed          = true;
			}

			if ( $rss_changed ) {
				$rss['enabled'] = '' !== trim( (string) $rss['before_content'] ) || '' !== trim( (string) $rss['after_content'] );
			}
		}

		if ( $onpage_changed ) {
			OnPageSeoSettings::update( $onpage );
		}

		if ( $schema_changed ) {
			SchemaSettings::update( $schema );
		}

		if ( $social_changed ) {
			SocialSettings::update( $social );
		}

		if ( $breadcrumbs_changed ) {
			BreadcrumbsSettings::update( $breadcrumbs );
		}

		if ( $webmaster_changed ) {
			SiteVerificationSettings::update( $webmaster );
		}

		if ( $rss_changed ) {
			RssFeedSignatureSettings::update( $rss );
		}

		return array(
			'onpage'      => $onpage_changed,
			'schema'      => $schema_changed,
			'social'      => $social_changed,
			'breadcrumbs' => $breadcrumbs_changed,
			'webmaster'   => $webmaster_changed,
			'rss'         => $rss_changed,
		);
	}

	/**
	 * Import redirect rules from AIOSEO redirects table.
	 *
	 * @return int
	 */
	private static function import_redirects_batch(): int {
		global $wpdb;

		$table = self::aioseo_redirects_table();
		if ( '' === $table ) {
			return 0;
		}

		$cursor = (int) get_option( self::REDIRECT_CURSOR_OPTION, 0 );
		$rows   = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AIOSEO migration source table read.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- External source table name is validated before use.
				$cursor,
				self::BATCH_SIZE
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$processed = 0;
		$rules     = RedirectsSettings::get_rules();
		$existing  = isset( $rules['rules'] ) && is_array( $rules['rules'] ) ? $rules['rules'] : array();

		foreach ( $rows as $row ) {
			$id     = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$cursor = max( $cursor, $id );
			++$processed;

			$status_value = isset( $row['status'] ) ? strtolower( trim( (string) $row['status'] ) ) : '';
			if ( in_array( $status_value, array( 'inactive', 'disabled', '0' ), true ) ) {
				continue;
			}

			$source = self::first_non_empty_value(
				$row,
				array( 'source_url', 'source', 'url_from', 'from_url', 'source_path', 'path' )
			);
			$target = self::first_non_empty_value(
				$row,
				array( 'target_url', 'target', 'url_to', 'to_url', 'destination' )
			);

			$source = self::normalize_redirect_path( $source );
			$target = self::normalize_redirect_target( $target );

			if ( '' === $source || '' === $target ) {
				continue;
			}

			$type = self::is_truthy( $row['regex'] ?? false ) ? 'regex' : 'exact';
			if ( 'exact' === $type && false !== strpos( $source, '*' ) ) {
				$type = 'wildcard';
			}

			$http_status = 301;
			if ( isset( $row['http_code'] ) ) {
				$http_status = (int) $row['http_code'];
			} elseif ( isset( $row['status_code'] ) ) {
				$http_status = (int) $row['status_code'];
			} elseif ( isset( $row['code'] ) ) {
				$http_status = (int) $row['code'];
			}
			if ( ! in_array( $http_status, array( 301, 302, 307, 308 ), true ) ) {
				$http_status = 301;
			}

			if ( self::redirect_rule_exists( $existing, $source, $type ) ) {
				continue;
			}

			$existing[] = array(
				'id'      => wp_generate_uuid4(),
				'type'    => $type,
				'source'  => $source,
				'target'  => $target,
				'status'  => $http_status,
				'enabled' => true,
				'note'    => 'Imported from AIOSEO',
			);
		}

		update_option( self::REDIRECT_CURSOR_OPTION, $cursor, 'no' );

		if ( $processed > 0 ) {
			RedirectsSettings::update_rules(
				array(
					'rules' => $existing,
				)
			);
		}

		return $processed;
	}

	/**
	 * Determine whether a redirect rule already exists.
	 *
	 * @param array<int, array<string, mixed>> $rules Existing rules.
	 * @param string                           $source Source path.
	 * @param string                           $type Redirect type.
	 *
	 * @return bool
	 */
	private static function redirect_rule_exists( array $rules, string $source, string $type ): bool {
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			if ( isset( $rule['source'], $rule['type'] ) && $rule['source'] === $source && $rule['type'] === $type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build progress details for redirects migration.
	 *
	 * @return array<string, int|bool>
	 */
	private static function build_redirects_progress(): array {
		$total    = self::count_redirects_total();
		$migrated = self::count_redirects_migrated();

		if ( $total < $migrated ) {
			$migrated = $total;
		}

		$percent = 0;
		if ( $total > 0 ) {
			$percent = (int) round( ( $migrated / $total ) * 100 );
		}

		$completed = $total > 0 && $migrated >= $total;
		if ( 0 === $total ) {
			$completed = true;
		}

		return array(
			'total'     => $total,
			'migrated'  => $migrated,
			'remaining' => max( 0, $total - $migrated ),
			'percent'   => $percent,
			'completed' => $completed,
			'batchSize' => self::BATCH_SIZE,
		);
	}

	/**
	 * Count total AIOSEO redirects.
	 *
	 * @return int
	 */
	private static function count_redirects_total(): int {
		global $wpdb;

		$table = self::aioseo_redirects_table();
		if ( '' === $table ) {
			return 0;
		}

		$total = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- External source table name is validated before use.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE 1 = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- External source table name is validated before use.
				1
			)
		);

		return (int) $total;
	}

	/**
	 * Count migrated AIOSEO redirects using cursor.
	 *
	 * @return int
	 */
	private static function count_redirects_migrated(): int {
		global $wpdb;

		$table = self::aioseo_redirects_table();
		if ( '' === $table ) {
			return 0;
		}

		$cursor = (int) get_option( self::REDIRECT_CURSOR_OPTION, 0 );
		if ( 0 === $cursor ) {
			return 0;
		}

		$count = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AIOSEO migration source table read.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE id <= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- External source table name is validated before use.
				$cursor
			)
		);

		return (int) $count;
	}

	/**
	 * Resolve AIOSEO redirects table name.
	 *
	 * @return string
	 */
	private static function aioseo_redirects_table(): string {
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_redirects';
		$found = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence probe.
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table )
			)
		);

		if ( ! empty( $found ) ) {
			return $table;
		}

		$wpdb->suppress_errors( true );
		$wpdb->get_var( "SELECT 1 FROM {$table} LIMIT 1" ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table readability probe.
		$error = $wpdb->last_error;
		$wpdb->suppress_errors( false );

		if ( '' === $error ) {
			return $table;
		}

		return '';
	}

	/**
	 * Count eligible posts for migration.
	 *
	 * @param bool $migrated_only Whether to count already migrated posts.
	 *
	 * @return int
	 */
	private static function count_posts( bool $migrated_only = false ): int {
		$args = array(
			'post_type'      => self::eligible_post_types(),
			'post_status'    => self::eligible_statuses(),
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => false,
		);

		if ( $migrated_only ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Migration progress filter.
				array(
					'key'     => Constants::META_AIOSEO_MIGRATED,
					'compare' => 'EXISTS',
				),
			);
		}

		$query = new WP_Query( $args );

		return (int) $query->found_posts;
	}

	/**
	 * Eligible post types for migration.
	 *
	 * @return array<int, string>
	 */
	private static function eligible_post_types(): array {
		$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment', 'revision', 'nav_menu_item' ) );

		return array_values( $post_types );
	}

	/**
	 * Eligible post statuses for migration.
	 *
	 * @return array<int, string>
	 */
	private static function eligible_statuses(): array {
		$statuses = get_post_stati( array( 'show_in_admin_all_list' => true ), 'names' );
		$statuses = array_diff( $statuses, array( 'trash', 'auto-draft' ) );

		return array_values( $statuses );
	}

	/**
	 * Retrieve AIOSEO options payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_aioseo_options(): array {
		$option = get_option( 'aioseo_options', array() );
		if ( is_array( $option ) ) {
			return $option;
		}

		if ( is_string( $option ) && '' !== trim( $option ) ) {
			$decoded = json_decode( $option, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return array();
	}

	/**
	 * Fetch AIOSEO per-post row.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_aioseo_post_record( int $post_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_posts';
		$found = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence probe.
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table )
			)
		);
		if ( empty( $found ) ) {
			$wpdb->suppress_errors( true );
			$wpdb->get_var( "SELECT 1 FROM {$table} LIMIT 1" ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table readability probe.
			$error = $wpdb->last_error;
			$wpdb->suppress_errors( false );
			if ( '' !== $error ) {
				return array();
			}
		}

		$row = $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration source table read.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- External source table name is validated before use.
				$post_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : array();
	}

	/**
	 * Read a scalar row key as string.
	 *
	 * @param array<string, mixed> $record Row data.
	 * @param string               $key    Key.
	 *
	 * @return string
	 */
	private static function get_string( array $record, string $key ): string {
		if ( ! array_key_exists( $key, $record ) ) {
			return '';
		}

		if ( is_array( $record[ $key ] ) || is_object( $record[ $key ] ) ) {
			return '';
		}

		return trim( (string) $record[ $key ] );
	}

	/**
	 * Read legacy AIOSEO Open Graph setting value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Legacy setting key.
	 *
	 * @return string
	 */
	private static function legacy_aioseop_opengraph_value( int $post_id, string $key ): string {
		$settings = get_post_meta( $post_id, '_aioseop_opengraph_settings', true );
		if ( is_string( $settings ) ) {
			$settings = maybe_unserialize( $settings );
		}

		if ( ! is_array( $settings ) || ! isset( $settings[ $key ] ) ) {
			return '';
		}

		return trim( (string) $settings[ $key ] );
	}

	/**
	 * Normalize AIOSEO separator value.
	 *
	 * @param string $separator Raw separator.
	 *
	 * @return string
	 */
	private static function normalize_separator( string $separator ): string {
		$decoded = html_entity_decode( $separator, ENT_QUOTES, 'UTF-8' );
		$decoded = trim( wp_strip_all_tags( $decoded ) );
		if ( '' === $decoded ) {
			return '';
		}

		return mb_substr( $decoded, 0, 10 );
	}

	/**
	 * Map AIOSEO smart tags to Airygen tokens.
	 *
	 * @param string $template Raw template.
	 *
	 * @return string
	 */
	private static function map_tokens( string $template ): string {
		$map = array(
			'#site_title'   => '%site_name%',
			'#tagline'      => '%site_description%',
			'#separator_sa' => '%separator%',
			'#post_title'   => '%post_title%',
			'#post_excerpt' => '%post_excerpt%',
		);

		return str_replace( array_keys( $map ), array_values( $map ), trim( $template ) );
	}

	/**
	 * Extract twitter handle from handle or profile URL.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private static function extract_twitter_handle( string $value ): string {
		$normalized = trim( $value );
		if ( '' === $normalized ) {
			return '';
		}

		$path = wp_parse_url( $normalized, PHP_URL_PATH );
		if ( is_string( $path ) && '' !== $path ) {
			$normalized = trim( $path, '/' );
		}

		$normalized = ltrim( $normalized, '@' );
		if ( '' === $normalized ) {
			return '';
		}

		if ( ! preg_match( '/^[A-Za-z0-9_]{1,15}$/', $normalized ) ) {
			return '';
		}

		return $normalized;
	}

	/**
	 * Return first non-empty value from row keys.
	 *
	 * @param array<string, mixed> $row  Row.
	 * @param array<int, string>   $keys Keys.
	 *
	 * @return string
	 */
	private static function first_non_empty_value( array $row, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! isset( $row[ $key ] ) ) {
				continue;
			}

			if ( is_array( $row[ $key ] ) || is_object( $row[ $key ] ) ) {
				continue;
			}

			$value = trim( (string) $row[ $key ] );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Normalize redirect source.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private static function normalize_redirect_path( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$parsed_path = wp_parse_url( $value, PHP_URL_PATH );
		if ( is_string( $parsed_path ) && '' !== $parsed_path ) {
			$value = $parsed_path;
		}

		if ( '/' !== $value[0] ) {
			$value = '/' . $value;
		}

		return $value;
	}

	/**
	 * Normalize redirect target.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private static function normalize_redirect_target( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strpos( $value, 'http://' ) || 0 === strpos( $value, 'https://' ) ) {
			return esc_url_raw( $value );
		}

		$parsed_path = wp_parse_url( $value, PHP_URL_PATH );
		if ( is_string( $parsed_path ) && '' !== $parsed_path ) {
			$value = $parsed_path;
		}

		if ( '/' !== $value[0] ) {
			$value = '/' . $value;
		}

		return $value;
	}

	/**
	 * Determine if a value should be treated as truthy.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return bool
	 */
	private static function is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return 1 === (int) $value;
		}

		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, array( '1', 'true', 'yes', 'on', 'active', 'enabled' ), true );
	}
}
