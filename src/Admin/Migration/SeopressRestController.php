<?php
/**
 * REST controller for SEOPress migrations.
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
 * Handles migration-related REST requests for SEOPress.
 */
final class SeopressRestController {

	private const BATCH_SIZE = 10;

	/**
	 * Check whether the current user can manage migrations.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return migration status for SEOPress.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_status(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'progress' => self::build_progress(),
				'settings' => array(
					'available' => self::has_seopress_settings(),
				),
			)
		);
	}

	/**
	 * Import a batch of SEOPress post meta.
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
	 * Import SEOPress global settings.
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
	 * Import SEOPress redirects.
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
	 * Return redirects status for SEOPress.
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
	 * Import a batch of posts not migrated yet.
	 *
	 * @return int
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
						'key'     => Constants::META_SEOPRESS_MIGRATED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$processed = 0;
		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			self::migrate_post_meta( $post_id );
			update_post_meta( $post_id, Constants::META_SEOPRESS_MIGRATED, gmdate( 'c' ) );
			++$processed;
		}

		return $processed;
	}

	/**
	 * Migrate SEOPress post meta for a single post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private static function migrate_post_meta( int $post_id ): void {
		$post_data = PostData::get( $post_id );

		if ( '' === $post_data['title'] ) {
			$value = get_post_meta( $post_id, '_seopress_titles_title', true );
			if ( ! is_array( $value ) ) {
				$value = sanitize_text_field( trim( (string) $value ) );
				if ( '' !== $value ) {
					$post_data['title'] = $value;
				}
			}
		}

		if ( '' === $post_data['description'] ) {
			$value = get_post_meta( $post_id, '_seopress_titles_desc', true );
			if ( ! is_array( $value ) ) {
				$value = sanitize_text_field( trim( (string) $value ) );
				if ( '' !== $value ) {
					$post_data['description'] = $value;
				}
			}
		}

		if ( '' === $post_data['canonical'] ) {
			$value = get_post_meta( $post_id, '_seopress_robots_canonical', true );
			if ( ! is_array( $value ) ) {
				$value = esc_url_raw( trim( (string) $value ) );
				if ( '' !== $value ) {
					$post_data['canonical'] = $value;
				}
			}
		}

		PostData::save( $post_id, $post_data );

		KeyphraseMapper::apply(
			$post_id,
			array(
				get_post_meta( $post_id, '_seopress_analysis_target_kw', true ),
			)
		);

		$current_robots = $post_data['robots'];
		if ( '' === (string) $current_robots ) {
			$robots = self::build_robots_directive( $post_id );
			if ( '' !== $robots ) {
				PostData::save_field( $post_id, 'robots', $robots );
			}
		}
	}

	/**
	 * Build post migration progress.
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
	 * Build redirects migration progress.
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
	 * Check whether SEOPress settings exist.
	 *
	 * @return bool
	 */
	private static function has_seopress_settings(): bool {
		$titles = get_option( 'seopress_titles_option_name', array() );
		if ( is_array( $titles ) && ! empty( $titles ) ) {
			return true;
		}

		$social = get_option( 'seopress_social_option_name', array() );
		if ( is_array( $social ) && ! empty( $social ) ) {
			return true;
		}

		$advanced = get_option( 'seopress_advanced_option_name', array() );
		if ( is_array( $advanced ) && ! empty( $advanced ) ) {
			return true;
		}

		$pro = get_option( 'seopress_pro_option_name', array() );
		return is_array( $pro ) && ! empty( $pro );
	}

	/**
	 * Import SEOPress settings into Airygen settings modules.
	 *
	 * @return array<string, mixed>
	 */
	private static function import_settings(): array {
		$source_titles   = get_option( 'seopress_titles_option_name', array() );
		$source_social   = get_option( 'seopress_social_option_name', array() );
		$source_advanced = get_option( 'seopress_advanced_option_name', array() );
		$source_pro      = get_option( 'seopress_pro_option_name', array() );

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

		if ( is_array( $source_titles ) ) {
			$separator = self::first_non_empty_option(
				$source_titles,
				array( 'seopress_titles_sep', 'separator' )
			);
			if ( '' !== $separator ) {
				$onpage['templates']['separator'] = sanitize_text_field( $separator );
				$onpage_changed                   = true;
			}

			$home_title = self::first_non_empty_option(
				$source_titles,
				array( 'seopress_titles_home_site_title', 'home_site_title' )
			);
			if ( '' !== $home_title ) {
				$onpage['templates']['global']['title'] = self::map_tokens( $home_title );
				$onpage_changed                         = true;
			}

			$home_desc = self::first_non_empty_option(
				$source_titles,
				array( 'seopress_titles_home_site_desc', 'home_site_desc' )
			);
			if ( '' !== $home_desc ) {
				$onpage['templates']['global']['description'] = self::map_tokens( $home_desc );
				$onpage_changed                               = true;
			}
		}

		if ( is_array( $source_social ) ) {
			$og_enabled = self::first_non_empty_option(
				$source_social,
				array( 'seopress_social_facebook_og', 'facebook_og' )
			);
			if ( '' !== $og_enabled ) {
				$social['og']['enabled'] = self::is_truthy( $og_enabled );
				$social_changed          = true;
			}

			$og_image = self::first_non_empty_option(
				$source_social,
				array( 'seopress_social_facebook_img', 'facebook_img' )
			);
			if ( '' !== $og_image ) {
				$social['og']['default_image_url'] = esc_url_raw( $og_image );
				$social_changed                    = true;
			}

			$twitter_enabled = self::first_non_empty_option(
				$source_social,
				array( 'seopress_social_twitter', 'twitter' )
			);
			if ( '' !== $twitter_enabled ) {
				$social['twitter']['enabled'] = self::is_truthy( $twitter_enabled );
				$social_changed               = true;
			}

			$twitter_card = self::first_non_empty_option(
				$source_social,
				array( 'seopress_social_twitter_card', 'twitter_card' )
			);
			if ( '' !== $twitter_card ) {
				$card_type = sanitize_text_field( $twitter_card );
				if ( 'summary' === $card_type || 'summary_large_image' === $card_type ) {
					$social['twitter']['card_type'] = $card_type;
					$social_changed                 = true;
				}
			}

			$org_name = self::first_non_empty_option(
				$source_social,
				array( 'seopress_social_knowledge_name', 'knowledge_name' )
			);
			if ( '' !== $org_name ) {
				$schema['organization_name'] = sanitize_text_field( $org_name );
				$schema_changed              = true;
			}

			$org_logo = self::first_non_empty_option(
				$source_social,
				array( 'seopress_social_knowledge_img', 'knowledge_img' )
			);
			if ( '' !== $org_logo ) {
				$schema['organization_logo_url'] = esc_url_raw( $org_logo );
				$schema_changed                  = true;
			}
		}

		if ( is_array( $source_advanced ) ) {
			$webmaster_map = array(
				'seopress_advanced_advanced_google'    => 'google',
				'seopress_advanced_advanced_bing'      => 'bing',
				'seopress_advanced_advanced_yandex'    => 'yandex',
				'seopress_advanced_advanced_baidu'     => 'baidu',
				'seopress_advanced_advanced_pinterest' => 'pinterest',
			);

			foreach ( $webmaster_map as $source_key => $target_key ) {
				if ( ! isset( $source_advanced[ $source_key ] ) ) {
					continue;
				}

				$value = sanitize_text_field( (string) $source_advanced[ $source_key ] );
				if ( '' === $value ) {
					continue;
				}

				$webmaster[ $target_key ] = $value;
				$webmaster_changed        = true;
			}

			$separator = self::first_non_empty_option(
				$source_advanced,
				array( 'seopress_advanced_breadcrumbs_sep', 'breadcrumbs_sep' )
			);
			if ( '' !== $separator ) {
				$breadcrumbs['separator'] = sanitize_text_field( $separator );
				$breadcrumbs_changed      = true;
			}

			$prefix = self::first_non_empty_option(
				$source_advanced,
				array( 'seopress_advanced_breadcrumbs_prefix', 'breadcrumbs_prefix' )
			);
			if ( '' !== $prefix ) {
				$breadcrumbs['prefix'] = sanitize_text_field( $prefix );
				$breadcrumbs_changed   = true;
			}

			$home_label = self::first_non_empty_option(
				$source_advanced,
				array( 'seopress_advanced_breadcrumbs_home', 'breadcrumbs_home' )
			);
			if ( '' !== $home_label ) {
				$breadcrumbs['home']['label'] = sanitize_text_field( $home_label );
				$breadcrumbs_changed          = true;
			}
		}

		if ( is_array( $source_pro ) ) {
			if ( isset( $source_pro['seopress_rss_before_html'] ) ) {
				$rss['before_content'] = wp_kses_post( self::map_tokens( (string) $source_pro['seopress_rss_before_html'] ) );
				$rss_changed           = true;
			}

			if ( isset( $source_pro['seopress_rss_after_html'] ) ) {
				$rss['after_content'] = wp_kses_post( self::map_tokens( (string) $source_pro['seopress_rss_after_html'] ) );
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
	 * Import redirects from SEOPress post meta into Airygen redirects.
	 *
	 * @return int
	 */
	private static function import_redirects_batch(): int {
		$query = new WP_Query(
			array(
				'post_type'      => self::eligible_post_types(),
				'post_status'    => self::eligible_statuses(),
				'posts_per_page' => self::BATCH_SIZE,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Migration redirect batch filter.
					'relation' => 'AND',
					array(
						'key'     => '_seopress_redirections_value',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => Constants::META_SEOPRESS_REDIRECT_MIGRATED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return 0;
		}

		$rules     = RedirectsSettings::get_rules();
		$existing  = isset( $rules['rules'] ) && is_array( $rules['rules'] ) ? $rules['rules'] : array();
		$processed = 0;

		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			++$processed;

			$enabled = get_post_meta( $post_id, '_seopress_redirections_enabled', true );
			if ( '' !== (string) $enabled && ! self::is_truthy( $enabled ) ) {
				update_post_meta( $post_id, Constants::META_SEOPRESS_REDIRECT_MIGRATED, gmdate( 'c' ) );
				continue;
			}

			$target = get_post_meta( $post_id, '_seopress_redirections_value', true );
			$target = is_array( $target ) ? '' : esc_url_raw( trim( (string) $target ) );
			if ( '' === $target ) {
				update_post_meta( $post_id, Constants::META_SEOPRESS_REDIRECT_MIGRATED, gmdate( 'c' ) );
				continue;
			}

			$source = self::normalize_source_path( (string) get_permalink( $post_id ) );
			if ( '' === $source ) {
				update_post_meta( $post_id, Constants::META_SEOPRESS_REDIRECT_MIGRATED, gmdate( 'c' ) );
				continue;
			}

			$status = (int) get_post_meta( $post_id, '_seopress_redirections_type', true );
			if ( ! in_array( $status, array( 301, 302, 307, 308 ), true ) ) {
				$status = 301;
			}

			if ( ! self::redirect_rule_exists( $existing, $source, 'exact' ) ) {
				$existing[] = array(
					'id'      => wp_generate_uuid4(),
					'type'    => 'exact',
					'source'  => $source,
					'target'  => $target,
					'status'  => $status,
					'enabled' => true,
					'note'    => 'Imported from SEOPress',
				);
			}

			update_post_meta( $post_id, Constants::META_SEOPRESS_REDIRECT_MIGRATED, gmdate( 'c' ) );
		}

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
	 * Build robots directive based on SEOPress flags.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private static function build_robots_directive( int $post_id ): string {
		$directives = array(
			self::is_truthy( get_post_meta( $post_id, '_seopress_robots_index', true ) ) ? 'noindex' : 'index',
			self::is_truthy( get_post_meta( $post_id, '_seopress_robots_follow', true ) ) ? 'nofollow' : 'follow',
		);

		if ( self::is_truthy( get_post_meta( $post_id, '_seopress_robots_snippet', true ) ) ) {
			$directives[] = 'nosnippet';
		}

		if ( self::is_truthy( get_post_meta( $post_id, '_seopress_robots_imageindex', true ) ) ) {
			$directives[] = 'noimageindex';
		}

		$directives = array_values( array_unique( array_filter( $directives ) ) );
		$robots     = implode( ',', $directives );

		if ( 'index,follow' === $robots ) {
			return '';
		}

		return $robots;
	}

	/**
	 * Count total redirects eligible for SEOPress migration.
	 *
	 * @return int
	 */
	private static function count_redirects_total(): int {
		$query = new WP_Query(
			array(
				'post_type'      => self::eligible_post_types(),
				'post_status'    => self::eligible_statuses(),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Migration redirects progress filter.
					array(
						'key'     => '_seopress_redirections_value',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Count migrated redirects.
	 *
	 * @return int
	 */
	private static function count_redirects_migrated(): int {
		$query = new WP_Query(
			array(
				'post_type'      => self::eligible_post_types(),
				'post_status'    => self::eligible_statuses(),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Migration redirects progress filter.
					array(
						'key'     => Constants::META_SEOPRESS_REDIRECT_MIGRATED,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Count eligible posts.
	 *
	 * @param bool $migrated_only Whether to count only migrated posts.
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
					'key'     => Constants::META_SEOPRESS_MIGRATED,
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
	 * Normalize permalink to redirect source path.
	 *
	 * @param string $permalink Post permalink.
	 *
	 * @return string
	 */
	private static function normalize_source_path( string $permalink ): string {
		$path = wp_parse_url( $permalink, PHP_URL_PATH );
		$path = is_string( $path ) ? trim( $path ) : '';

		if ( '' === $path ) {
			return '';
		}

		if ( '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		return $path;
	}

	/**
	 * Return first non-empty value from option keys.
	 *
	 * @param array<string, mixed> $source Source array.
	 * @param array<int, string>   $keys Candidate keys.
	 *
	 * @return string
	 */
	private static function first_non_empty_option( array $source, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! isset( $source[ $key ] ) || is_array( $source[ $key ] ) ) {
				continue;
			}

			$value = trim( (string) $source[ $key ] );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Determine if value should be treated as true.
	 *
	 * @param mixed $value Value.
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

		$normalized = strtolower( trim( (string) $value ) );
		return in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Convert SEOPress variables into Airygen template variables.
	 *
	 * @param string $template Source template.
	 *
	 * @return string
	 */
	private static function map_tokens( string $template ): string {
		$mapped = str_replace(
			array(
				'%%sitetitle%%',
				'%%tagline%%',
				'%%post_title%%',
				'%%post_excerpt%%',
				'%%sep%%',
				'%%page%%',
			),
			array(
				'%site_name%',
				'%site_description%',
				'%post_title%',
				'%post_excerpt%',
				'%separator%',
				'',
			),
			$template
		);

		$mapped = preg_replace( '/\s+/', ' ', trim( $mapped ) );
		return is_string( $mapped ) ? $mapped : '';
	}
}
