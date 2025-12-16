<?php
/**
 * REST controller for Rank Math migrations.
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
 * Handles migration-related REST requests for Rank Math.
 */
final class RankMathRestController {

	private const BATCH_SIZE = 10;

	private const META_PREFIX = 'rank_math_';

	private const REDIRECT_CURSOR_OPTION = Constants::OPTION_RANK_MATH_REDIRECT_CURSOR;

	/**
	 * Check whether the current user can manage migrations.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return migration status for Rank Math.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_status(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'progress' => self::build_progress(),
				'settings' => array(
					'available' => self::has_rank_math_settings(),
				),
			)
		);
	}

	/**
	 * Import a batch of Rank Math post meta.
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
	 * Import Rank Math global settings.
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
	 * Import Rank Math redirect rules.
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
	 * Return migration status for Rank Math redirects.
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
						'key'     => Constants::META_RANK_MATH_MIGRATED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$processed = 0;
		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			self::migrate_post_meta( $post_id );
			update_post_meta( $post_id, Constants::META_RANK_MATH_MIGRATED, gmdate( 'c' ) );
			++$processed;
		}

		return $processed;
	}

	/**
	 * Migrate Rank Math post meta for a single post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private static function migrate_post_meta( int $post_id ): void {
		$post_data = PostData::get( $post_id );

		if ( '' === $post_data['title'] ) {
			$rank_value = get_post_meta( $post_id, self::META_PREFIX . 'title', true );
			if ( is_array( $rank_value ) ) {
				$rank_value = self::first_non_empty( $rank_value );
			}
			$rank_value = sanitize_text_field( (string) $rank_value );
			if ( '' !== $rank_value ) {
				$post_data['title'] = $rank_value;
			}
		}

		if ( '' === $post_data['description'] ) {
			$rank_value = get_post_meta( $post_id, self::META_PREFIX . 'description', true );
			if ( is_array( $rank_value ) ) {
				$rank_value = self::first_non_empty( $rank_value );
			}
			$rank_value = sanitize_text_field( (string) $rank_value );
			if ( '' !== $rank_value ) {
				$post_data['description'] = $rank_value;
			}
		}

		if ( '' === $post_data['canonical'] ) {
			$rank_value = esc_url_raw( (string) get_post_meta( $post_id, self::META_PREFIX . 'canonical_url', true ) );
			if ( '' !== $rank_value ) {
				$post_data['canonical'] = $rank_value;
			}
		}

		PostData::save( $post_id, $post_data );

		KeyphraseMapper::apply(
			$post_id,
			array(
				get_post_meta( $post_id, self::META_PREFIX . 'focus_keyword', true ),
			)
		);

		$current_robots = $post_data['robots'];
		if ( '' !== (string) $current_robots ) {
			return;
		}

		$robots = self::build_robots_directive( $post_id );
		if ( '' !== $robots ) {
			PostData::save_field( $post_id, 'robots', $robots );
		}
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
	 * Check if Rank Math settings are present.
	 *
	 * @return bool
	 */
	private static function has_rank_math_settings(): bool {
		$rank_titles = self::get_rank_math_option( array( 'rank-math-options-titles', 'rank_math_titles' ) );
		if ( is_array( $rank_titles ) && ! empty( $rank_titles ) ) {
			return true;
		}

		$rank_general = self::get_rank_math_option( array( 'rank-math-options-general', 'rank_math_general' ) );
		return is_array( $rank_general ) && ! empty( $rank_general );
	}

	/**
	 * Import Rank Math global settings into Airygen options.
	 *
	 * @return array<string, mixed>
	 */
	private static function import_settings(): array {
		$rank_titles  = self::get_rank_math_option( array( 'rank-math-options-titles', 'rank_math_titles' ) );
		$rank_general = self::get_rank_math_option( array( 'rank-math-options-general', 'rank_math_general' ) );

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

		if ( is_array( $rank_titles ) ) {
			if ( isset( $rank_titles['title_separator'] ) ) {
				$separator = sanitize_text_field( (string) $rank_titles['title_separator'] );
				if ( '' !== $separator ) {
					$onpage['templates']['separator'] = $separator;
					$onpage_changed                   = true;
				}
			}

			if ( isset( $rank_titles['homepage_title'] ) ) {
				$onpage['templates']['global']['title'] = self::normalize_template(
					self::map_tokens( (string) $rank_titles['homepage_title'] )
				);
				$onpage_changed                         = true;
			}

			if ( isset( $rank_titles['homepage_description'] ) ) {
				$onpage['templates']['global']['description'] = self::normalize_template(
					self::map_tokens( (string) $rank_titles['homepage_description'] )
				);
				$onpage_changed                               = true;
			}

			foreach ( self::eligible_post_types() as $post_type ) {
				$title_key       = 'pt_' . $post_type . '_title';
				$description_key = 'pt_' . $post_type . '_description';

				if ( isset( $rank_titles[ $title_key ] ) ) {
					$onpage['templates']['postTypes'][ $post_type ]['title'] = self::normalize_template(
						self::map_tokens( (string) $rank_titles[ $title_key ] )
					);
					$onpage_changed = true;
				}

				if ( isset( $rank_titles[ $description_key ] ) ) {
					$onpage['templates']['postTypes'][ $post_type ]['description'] = self::normalize_template(
						self::map_tokens( (string) $rank_titles[ $description_key ] )
					);
					$onpage_changed = true;
				}

				$snippet_key = 'pt_' . $post_type . '_default_rich_snippet';
				$article_key = 'pt_' . $post_type . '_default_article_type';
				if ( isset( $rank_titles[ $snippet_key ] ) && 'article' === $rank_titles[ $snippet_key ] && isset( $rank_titles[ $article_key ] ) ) {
					$schema['post_type_defaults'][ $post_type ] = sanitize_text_field( (string) $rank_titles[ $article_key ] );
					$schema_changed                             = true;
				}
			}

			if ( isset( $rank_titles['knowledgegraph_name'] ) ) {
				$schema['organization_name'] = sanitize_text_field( (string) $rank_titles['knowledgegraph_name'] );
				$schema_changed              = true;
			}

			if ( isset( $rank_titles['knowledgegraph_type'] ) ) {
				$type                        = (string) $rank_titles['knowledgegraph_type'];
				$schema['organization_type'] = 'person' === $type ? 'Person' : 'Organization';
				$schema_changed              = true;
			}

			if ( isset( $rank_titles['knowledgegraph_logo'] ) ) {
				$logo_id = absint( $rank_titles['knowledgegraph_logo'] );
				if ( $logo_id > 0 ) {
					$schema['organization_logo_id'] = $logo_id;
					$schema_changed                 = true;
				}
			}

			if ( isset( $rank_titles['open_graph_image'] ) ) {
				$og_value = $rank_titles['open_graph_image'];
				if ( is_numeric( $og_value ) ) {
					$social['og']['default_image_id'] = absint( $og_value );
				} else {
					$social['og']['default_image_url'] = esc_url_raw( (string) $og_value );
				}
				$social_changed = true;
			}

			if ( isset( $rank_titles['open_graph_image_id'] ) ) {
				$social['og']['default_image_id'] = absint( $rank_titles['open_graph_image_id'] );
				$social_changed                   = true;
			}

			if ( isset( $rank_titles['twitter_card_type'] ) ) {
				$social['twitter']['card_type'] = sanitize_text_field( (string) $rank_titles['twitter_card_type'] );
				$social_changed                 = true;
			}
		}

		if ( is_array( $rank_general ) ) {
			$breadcrumbs         = self::apply_breadcrumbs_settings( $breadcrumbs, $rank_general );
			$breadcrumbs_changed = true;

			$webmaster_map = array(
				'google_verify'    => 'google',
				'bing_verify'      => 'bing',
				'yandex_verify'    => 'yandex',
				'baidu_verify'     => 'baidu',
				'pinterest_verify' => 'pinterest',
			);
			foreach ( $webmaster_map as $source_key => $target_key ) {
				if ( ! isset( $rank_general[ $source_key ] ) ) {
					continue;
				}

				$value = sanitize_text_field( (string) $rank_general[ $source_key ] );
				if ( '' === $value ) {
					continue;
				}

				$webmaster[ $target_key ] = $value;
				$webmaster_changed        = true;
			}

			if ( isset( $rank_general['rss_before_content'] ) ) {
				$rss['before_content'] = wp_kses_post( self::map_tokens( (string) $rank_general['rss_before_content'] ) );
				$rss_changed           = true;
			}

			if ( isset( $rank_general['rss_after_content'] ) ) {
				$rss['after_content'] = wp_kses_post( self::map_tokens( (string) $rank_general['rss_after_content'] ) );
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
	 * Import redirect rules from Rank Math redirections table.
	 *
	 * @return int
	 */
	private static function import_redirects_batch(): int {
		global $wpdb;

		$table = self::rank_math_redirections_table();
		if ( '' === $table ) {
			return 0;
		}

		$cursor = (int) get_option( self::REDIRECT_CURSOR_OPTION, 0 );

		$rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rank Math migration source table read.
			$wpdb->prepare(
				"SELECT id, sources, url_to, header_code, status FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- External source table name is validated before use.
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

			if ( isset( $row['status'] ) && 'active' !== (string) $row['status'] ) {
				continue;
			}

			$target = isset( $row['url_to'] ) ? esc_url_raw( (string) $row['url_to'] ) : '';
			if ( '' === $target ) {
				continue;
			}

			$status = isset( $row['header_code'] ) ? (int) $row['header_code'] : 301;

			$sources = array();
			if ( isset( $row['sources'] ) ) {
				$sources = maybe_unserialize( $row['sources'] );
				if ( ! is_array( $sources ) ) {
					$sources = maybe_unserialize( wp_unslash( (string) $row['sources'] ) );
				}
			}

			if ( ! is_array( $sources ) ) {
				continue;
			}

			foreach ( $sources as $source ) {
				if ( ! is_array( $source ) ) {
					continue;
				}
				$pattern    = isset( $source['pattern'] ) ? (string) $source['pattern'] : '';
				$comparison = isset( $source['comparison'] ) ? (string) $source['comparison'] : 'exact';

				$normalized = self::normalize_redirect_source( $pattern, $comparison );
				if ( '' === $normalized['source'] ) {
					continue;
				}

				if ( self::redirect_rule_exists( $existing, $normalized['source'], $normalized['type'] ) ) {
					continue;
				}

				$existing[] = array(
					'id'      => wp_generate_uuid4(),
					'type'    => $normalized['type'],
					'source'  => $normalized['source'],
					'target'  => $target,
					'status'  => $status,
					'enabled' => true,
					'note'    => 'Imported from Rank Math',
				);
			}
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
	 * Normalize Rank Math redirect source to Airygen rules.
	 *
	 * @param string $pattern Pattern value.
	 * @param string $comparison Comparison type.
	 *
	 * @return array{type: string, source: string}
	 */
	private static function normalize_redirect_source( string $pattern, string $comparison ): array {
		$pattern = trim( $pattern );
		if ( '' === $pattern ) {
			return array(
				'type'   => 'exact',
				'source' => '',
			);
		}

		$type   = 'exact';
		$source = $pattern;

		if ( 'regex' === $comparison ) {
			$type   = 'regex';
			$source = $pattern;
		} elseif ( 'contains' === $comparison ) {
			$type   = 'wildcard';
			$source = '*' . $pattern . '*';
		} elseif ( 'start' === $comparison ) {
			$type   = 'wildcard';
			$source = $pattern . '*';
		} elseif ( 'end' === $comparison ) {
			$type   = 'wildcard';
			$source = '*' . $pattern;
		}

		if ( '' !== $source && '/' !== $source[0] ) {
			$source = '/' . $source;
		}

		return array(
			'type'   => $type,
			'source' => $source,
		);
	}

	/**
	 * Count total Rank Math redirects.
	 *
	 * @return int
	 */
	private static function count_redirects_total(): int {
		global $wpdb;

		$table = self::rank_math_redirections_table();
		if ( '' === $table ) {
			return 0;
		}

		$total = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rank Math migration source table read.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- External source table name is validated before use.
				'active'
			)
		);

		return (int) $total;
	}

	/**
	 * Count migrated Rank Math redirects using the cursor.
	 *
	 * @return int
	 */
	private static function count_redirects_migrated(): int {
		global $wpdb;

		$table = self::rank_math_redirections_table();
		if ( '' === $table ) {
			return 0;
		}

		$cursor = (int) get_option( self::REDIRECT_CURSOR_OPTION, 0 );
		if ( 0 === $cursor ) {
			return 0;
		}

		$count = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rank Math migration source table read.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s AND id <= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- External source table name is validated before use.
				'active',
				$cursor
			)
		);

		return (int) $count;
	}

	/**
	 * Resolve the Rank Math redirections table name when available.
	 *
	 * @return string
	 */
	private static function rank_math_redirections_table(): string {
		global $wpdb;

		$table = $wpdb->prefix . 'rank_math_redirections';
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
	 * Build robots meta directive from Rank Math post meta.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private static function build_robots_directive( int $post_id ): string {
		$robots = get_post_meta( $post_id, self::META_PREFIX . 'robots', true );
		if ( '' === (string) $robots ) {
			return '';
		}

		if ( is_array( $robots ) ) {
			$robots = array_filter( array_map( 'sanitize_text_field', $robots ) );
			return implode( ', ', $robots );
		}

		return sanitize_text_field( (string) $robots );
	}

	/**
	 * Map Rank Math replacement variables to Airygen tokens.
	 *
	 * @param string $template Rank Math template.
	 *
	 * @return string
	 */
	private static function map_tokens( string $template ): string {
		$map = array(
			'%sitename%'  => '%site_name%',
			'%sitedesc%'  => '%site_description%',
			'%title%'     => '%post_title%',
			'%excerpt%'   => '%post_excerpt%',
			'%sep%'       => '%separator%',
			'%separator%' => '%separator%',
			'%page%'      => '',
		);

		return str_replace( array_keys( $map ), array_values( $map ), $template );
	}

	/**
	 * Normalize template strings after token mapping.
	 *
	 * @param string $template Template string.
	 *
	 * @return string
	 */
	private static function normalize_template( string $template ): string {
		$template = trim( $template );
		$template = preg_replace( '/\s+/', ' ', $template );

		return is_string( $template ) ? $template : '';
	}

	/**
	 * Apply Rank Math breadcrumbs settings.
	 *
	 * @param array<string, mixed> $current Current breadcrumbs config.
	 * @param array<string, mixed> $rank_general Rank Math general settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function apply_breadcrumbs_settings( array $current, array $rank_general ): array {
		if ( isset( $rank_general['breadcrumbs'] ) ) {
			$current['enabled'] = self::is_truthy( $rank_general['breadcrumbs'] );
		}

		if ( isset( $rank_general['breadcrumbs_separator'] ) ) {
			$current['separator'] = (string) $rank_general['breadcrumbs_separator'];
		}

		if ( isset( $rank_general['breadcrumbs_prefix'] ) ) {
			$current['prefix'] = sanitize_text_field( (string) $rank_general['breadcrumbs_prefix'] );
		}

		if ( isset( $rank_general['breadcrumbs_home'] ) ) {
			$current['home']['display'] = self::is_truthy( $rank_general['breadcrumbs_home'] );
		}

		if ( isset( $rank_general['breadcrumbs_home_label'] ) ) {
			$current['home']['label'] = sanitize_text_field( (string) $rank_general['breadcrumbs_home_label'] );
		}

		if ( isset( $rank_general['breadcrumbs_archive_format'] ) ) {
			$current['labels']['archive'] = sanitize_text_field( (string) $rank_general['breadcrumbs_archive_format'] );
		}

		if ( isset( $rank_general['breadcrumbs_search_format'] ) ) {
			$current['labels']['search'] = sanitize_text_field( (string) $rank_general['breadcrumbs_search_format'] );
		}

		if ( isset( $rank_general['breadcrumbs_404_label'] ) ) {
			$current['labels']['error'] = sanitize_text_field( (string) $rank_general['breadcrumbs_404_label'] );
		}

		if ( isset( $rank_general['breadcrumbs_remove_post_title'] ) ) {
			$current['display']['showCurrent'] = ! self::is_truthy( $rank_general['breadcrumbs_remove_post_title'] );
		}

		if ( isset( $rank_general['breadcrumbs_ancestor_categories'] ) ) {
			$current['display']['showAncestors'] = self::is_truthy( $rank_general['breadcrumbs_ancestor_categories'] );
		}

		return $current;
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
					'key'     => Constants::META_RANK_MATH_MIGRATED,
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

		$value = strtolower( (string) $value );

		return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Return the first non-empty string from a list.
	 *
	 * @param array<int, mixed> $values Values.
	 *
	 * @return string
	 */
	private static function first_non_empty( array $values ): string {
		foreach ( $values as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Retrieve Rank Math option payload from known option names.
	 *
	 * @param array<int, string> $option_names Candidate option names.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_rank_math_option( array $option_names ): array {
		foreach ( $option_names as $option_name ) {
			$value = get_option( $option_name, array() );
			if ( is_array( $value ) && ! empty( $value ) ) {
				return $value;
			}
		}

		return array();
	}
}
