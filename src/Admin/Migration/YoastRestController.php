<?php
/**
 * REST controller for Yoast migrations.
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
 * Handles migration-related REST requests.
 */
final class YoastRestController {

	private const BATCH_SIZE   = 10;
	private const YOAST_PREFIX = '_yoast_wpseo_';

	/**
	 * Check whether the current user can manage migrations.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return migration status for Yoast.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_status(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'progress' => self::build_progress(),
				'settings' => array(
					'available' => self::has_yoast_settings(),
				),
			)
		);
	}

	/**
	 * Import a batch of Yoast post meta.
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
	 * Import Yoast global settings.
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
	 * Import Yoast redirect rules.
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
	 * Return migration status for Yoast redirects.
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
						'key'     => Constants::META_YOAST_MIGRATED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$processed = 0;
		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			self::migrate_post_meta( $post_id );
			update_post_meta( $post_id, Constants::META_YOAST_MIGRATED, gmdate( 'c' ) );
			++$processed;
		}

		return $processed;
	}

	/**
	 * Migrate Yoast post meta for a single post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private static function migrate_post_meta( int $post_id ): void {
		$post_data = PostData::get( $post_id );

		if ( '' === $post_data['title'] ) {
			$yoast_value = sanitize_text_field( (string) get_post_meta( $post_id, self::YOAST_PREFIX . 'title', true ) );
			if ( '' !== $yoast_value ) {
				$post_data['title'] = $yoast_value;
			}
		}

		if ( '' === $post_data['description'] ) {
			$yoast_value = sanitize_text_field( (string) get_post_meta( $post_id, self::YOAST_PREFIX . 'metadesc', true ) );
			if ( '' !== $yoast_value ) {
				$post_data['description'] = $yoast_value;
			}
		}

		if ( '' === $post_data['canonical'] ) {
			$yoast_value = esc_url_raw( (string) get_post_meta( $post_id, self::YOAST_PREFIX . 'canonical', true ) );
			if ( '' !== $yoast_value ) {
				$post_data['canonical'] = $yoast_value;
			}
		}

		if ( '' === $post_data['schemaArticleType'] ) {
			$yoast_value = sanitize_text_field( (string) get_post_meta( $post_id, self::YOAST_PREFIX . 'schema_article_type', true ) );
			if ( '' !== $yoast_value ) {
				$post_data['schemaArticleType'] = $yoast_value;
			}
		}

		PostData::save( $post_id, $post_data );

		KeyphraseMapper::apply(
			$post_id,
			array(
				get_post_meta( $post_id, self::YOAST_PREFIX . 'focuskw', true ),
				get_post_meta( $post_id, self::YOAST_PREFIX . 'focuskeywords', true ),
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
	 * Build an Airygen robots directive from Yoast meta.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private static function build_robots_directive( int $post_id ): string {
		$noindex  = (string) get_post_meta( $post_id, self::YOAST_PREFIX . 'meta-robots-noindex', true );
		$nofollow = (string) get_post_meta( $post_id, self::YOAST_PREFIX . 'meta-robots-nofollow', true );
		$advanced = get_post_meta( $post_id, self::YOAST_PREFIX . 'meta-robots-adv', true );

		$index_directive  = 'index';
		$follow_directive = 'follow';

		if ( '1' === $noindex ) {
			$index_directive = 'noindex';
		} elseif ( '2' === $noindex ) {
			$index_directive = 'index';
		}

		if ( '1' === $nofollow ) {
			$follow_directive = 'nofollow';
		}

		$directives = array( $index_directive, $follow_directive );

		$advanced_directives = array();
		if ( is_array( $advanced ) ) {
			$advanced_directives = $advanced;
		} elseif ( is_string( $advanced ) ) {
			$advanced_directives = array_map( 'trim', explode( ',', $advanced ) );
		}

		foreach ( $advanced_directives as $entry ) {
			$entry = strtolower( trim( (string) $entry ) );
			if ( '' === $entry ) {
				continue;
			}
			$directives[] = $entry;
		}

		$directives = array_values( array_unique( $directives ) );
		$directive  = implode( ',', $directives );

		if ( 'index,follow' === $directive ) {
			return '';
		}

		return $directive;
	}

	/**
	 * Build progress details.
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

		$completed = false;
		if ( 0 === $total ) {
			$completed = true;
		} elseif ( $migrated >= $total ) {
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
	 * Check if Yoast settings are present.
	 *
	 * @return bool
	 */
	private static function has_yoast_settings(): bool {
		$yoast_general = get_option( 'wpseo', array() );
		if ( is_array( $yoast_general ) && ! empty( $yoast_general ) ) {
			return true;
		}

		$yoast_titles = get_option( 'wpseo_titles', array() );
		if ( is_array( $yoast_titles ) && ! empty( $yoast_titles ) ) {
			return true;
		}

		$yoast_social = get_option( 'wpseo_social', array() );
		return is_array( $yoast_social ) && ! empty( $yoast_social );
	}

	/**
	 * Import Yoast global settings into Airygen options.
	 *
	 * @return array<string, mixed>
	 */
	private static function import_settings(): array {
		$yoast_general = get_option( 'wpseo', array() );
		$yoast_titles  = get_option( 'wpseo_titles', array() );
		$yoast_social  = get_option( 'wpseo_social', array() );

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

		if ( is_array( $yoast_general ) ) {
			$webmaster_map = array(
				'googleverify' => 'google',
				'msverify'     => 'bing',
				'yandexverify' => 'yandex',
				'baiduverify'  => 'baidu',
			);

			foreach ( $webmaster_map as $source_key => $target_key ) {
				if ( ! isset( $yoast_general[ $source_key ] ) ) {
					continue;
				}

				$value = sanitize_text_field( (string) $yoast_general[ $source_key ] );
				if ( '' === $value ) {
					continue;
				}

				$webmaster[ $target_key ] = $value;
				$webmaster_changed        = true;
			}
		}

		if ( is_array( $yoast_titles ) ) {
			if ( isset( $yoast_titles['separator'] ) ) {
				$separator = self::map_separator( (string) $yoast_titles['separator'] );
				if ( '' !== $separator ) {
					$onpage['templates']['separator'] = $separator;
					$onpage_changed                   = true;
				}
			}

			if ( isset( $yoast_titles['title-home-wpseo'] ) ) {
				$onpage['templates']['global']['title'] = self::map_tokens( (string) $yoast_titles['title-home-wpseo'] );
				$onpage_changed                         = true;
			}

			if ( isset( $yoast_titles['metadesc-home-wpseo'] ) ) {
				$onpage['templates']['global']['description'] = self::map_tokens( (string) $yoast_titles['metadesc-home-wpseo'] );
				$onpage_changed                               = true;
			}

			if ( isset( $yoast_titles['company_name'] ) ) {
				$schema['organization_name'] = sanitize_text_field( (string) $yoast_titles['company_name'] );
				$schema_changed              = true;
			} elseif ( isset( $yoast_titles['person_name'] ) ) {
				$schema['organization_name'] = sanitize_text_field( (string) $yoast_titles['person_name'] );
				$schema['organization_type'] = 'Person';
				$schema_changed              = true;
			}

			if ( isset( $yoast_titles['company_logo_id'] ) ) {
				$schema['organization_logo_id'] = absint( $yoast_titles['company_logo_id'] );
				$schema_changed                 = true;
			} elseif ( isset( $yoast_titles['person_logo_id'] ) ) {
				$schema['organization_logo_id'] = absint( $yoast_titles['person_logo_id'] );
				$schema_changed                 = true;
			}

			$schema_defaults = self::extract_schema_post_type_defaults( $yoast_titles );
			if ( ! empty( $schema_defaults ) ) {
				$schema['post_type_defaults'] = array_merge(
					$schema['post_type_defaults'] ?? array(),
					$schema_defaults
				);
				$schema_changed               = true;
			}

			$breadcrumbs         = self::apply_breadcrumbs_settings( $breadcrumbs, $yoast_titles );
			$breadcrumbs_changed = true;

			if ( isset( $yoast_titles['rssbefore'] ) ) {
				$rss['before_content'] = wp_kses_post( self::map_tokens( (string) $yoast_titles['rssbefore'] ) );
				$rss_changed           = true;
			}

			if ( isset( $yoast_titles['rssafter'] ) ) {
				$rss['after_content'] = wp_kses_post( self::map_tokens( (string) $yoast_titles['rssafter'] ) );
				$rss_changed          = true;
			}

			if ( $rss_changed ) {
				$rss['enabled'] = '' !== trim( (string) $rss['before_content'] ) || '' !== trim( (string) $rss['after_content'] );
			}
		}

		if ( is_array( $yoast_social ) ) {
			$social         = self::apply_social_settings( $social, $yoast_social );
			$social_changed = true;
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
	 * Import redirect rules from Yoast per-post metadata.
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
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Redirect migration filter.
					array(
						'key'     => self::YOAST_PREFIX . 'redirect',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => Constants::META_YOAST_REDIRECT_MIGRATED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$processed = 0;
		$rules     = RedirectsSettings::get_rules();
		$existing  = isset( $rules['rules'] ) && is_array( $rules['rules'] ) ? $rules['rules'] : array();

		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			$target  = get_post_meta( $post_id, self::YOAST_PREFIX . 'redirect', true );
			$target  = esc_url_raw( (string) $target );
			if ( '' === $target ) {
				update_post_meta( $post_id, Constants::META_YOAST_REDIRECT_MIGRATED, gmdate( 'c' ) );
				continue;
			}

			$permalink = get_permalink( $post_id );
			if ( ! is_string( $permalink ) || '' === $permalink ) {
				update_post_meta( $post_id, Constants::META_YOAST_REDIRECT_MIGRATED, gmdate( 'c' ) );
				continue;
			}

			$source = wp_parse_url( $permalink, PHP_URL_PATH );
			if ( ! is_string( $source ) || '' === $source ) {
				update_post_meta( $post_id, Constants::META_YOAST_REDIRECT_MIGRATED, gmdate( 'c' ) );
				continue;
			}

			$source = '/' . ltrim( $source, '/' );

			$exists = false;
			foreach ( $existing as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				if ( isset( $rule['source'] ) && $rule['source'] === $source ) {
					$exists = true;
					break;
				}
			}

			if ( ! $exists ) {
				$existing[] = array(
					'id'      => wp_generate_uuid4(),
					'type'    => 'exact',
					'source'  => $source,
					'target'  => $target,
					'status'  => 301,
					'enabled' => true,
					'note'    => 'Imported from Yoast',
				);
			}

			update_post_meta( $post_id, Constants::META_YOAST_REDIRECT_MIGRATED, gmdate( 'c' ) );
			++$processed;
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
	 * Build progress details for redirects migration.
	 *
	 * @return array<string, int|bool>
	 */
	private static function build_redirects_progress(): array {
		$total    = self::count_redirect_posts();
		$migrated = self::count_redirect_posts( true );

		if ( $total < $migrated ) {
			$migrated = $total;
		}

		$percent = 0;
		if ( $total > 0 ) {
			$percent = (int) round( ( $migrated / $total ) * 100 );
		}

		return array(
			'total'     => $total,
			'migrated'  => $migrated,
			'remaining' => max( 0, $total - $migrated ),
			'percent'   => $percent,
			'completed' => $total > 0 && $migrated >= $total,
			'batchSize' => self::BATCH_SIZE,
		);
	}

	/**
	 * Count posts that have Yoast redirect data.
	 *
	 * @param bool $migrated_only Whether to count only migrated posts.
	 *
	 * @return int
	 */
	private static function count_redirect_posts( bool $migrated_only = false ): int {
		$args = array(
			'post_type'      => self::eligible_post_types(),
			'post_status'    => self::eligible_statuses(),
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => false,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Redirect counting filter.
				array(
					'key'     => self::YOAST_PREFIX . 'redirect',
					'compare' => 'EXISTS',
				),
			),
		);

		if ( $migrated_only ) {
			$args['meta_query'][] = array(
				'key'     => Constants::META_YOAST_REDIRECT_MIGRATED,
				'compare' => 'EXISTS',
			);
		}

		$query = new WP_Query( $args );

		return (int) $query->found_posts;
	}

	/**
	 * Map Yoast title separators to display characters.
	 *
	 * @param string $separator Separator key.
	 *
	 * @return string
	 */
	private static function map_separator( string $separator ): string {
		$map = array(
			'sc-dash'   => '-',
			'sc-ndash'  => '–',
			'sc-mdash'  => '—',
			'sc-colon'  => ':',
			'sc-middot' => '·',
			'sc-bull'   => '•',
			'sc-star'   => '*',
			'sc-smstar' => '⋆',
			'sc-pipe'   => '|',
			'sc-tilde'  => '~',
			'sc-laquo'  => '«',
			'sc-raquo'  => '»',
			'sc-lt'     => '<',
			'sc-gt'     => '>',
		);

		if ( isset( $map[ $separator ] ) ) {
			return $map[ $separator ];
		}

		return $separator;
	}

	/**
	 * Map Yoast replacement variables to Airygen tokens.
	 *
	 * @param string $template Yoast template.
	 *
	 * @return string
	 */
	private static function map_tokens( string $template ): string {
		$map = array(
			'%%sitename%%' => '%site_name%',
			'%%sitedesc%%' => '%site_description%',
			'%%title%%'    => '%post_title%',
			'%%excerpt%%'  => '%post_excerpt%',
			'%%sep%%'      => '%separator%',
		);

		return str_replace( array_keys( $map ), array_values( $map ), $template );
	}

	/**
	 * Extract schema post type defaults from Yoast options.
	 *
	 * @param array<string, mixed> $yoast_titles Yoast title options.
	 *
	 * @return array<string, string>
	 */
	private static function extract_schema_post_type_defaults( array $yoast_titles ): array {
		$defaults = array();

		foreach ( $yoast_titles as $key => $value ) {
			if ( 0 !== strpos( (string) $key, 'schema-article-type-' ) ) {
				continue;
			}

			$post_type = substr( (string) $key, strlen( 'schema-article-type-' ) );
			$post_type = sanitize_key( $post_type );
			if ( '' === $post_type ) {
				continue;
			}

			$schema_type = sanitize_text_field( (string) $value );
			if ( '' === $schema_type ) {
				continue;
			}

			$defaults[ $post_type ] = $schema_type;
		}

		return $defaults;
	}

	/**
	 * Apply Yoast social defaults to Airygen config.
	 *
	 * @param array<string, mixed> $current Current Airygen config.
	 * @param array<string, mixed> $yoast_social Yoast social config.
	 *
	 * @return array<string, mixed>
	 */
	private static function apply_social_settings( array $current, array $yoast_social ): array {
		if ( isset( $yoast_social['opengraph'] ) ) {
			$current['og']['enabled'] = self::is_truthy( $yoast_social['opengraph'] );
		}

		if ( isset( $yoast_social['og_default_image'] ) ) {
			$current['og']['default_image_url'] = esc_url_raw( (string) $yoast_social['og_default_image'] );
		}

		if ( isset( $yoast_social['og_default_image_id'] ) ) {
			$current['og']['default_image_id'] = absint( $yoast_social['og_default_image_id'] );
		}

		if ( isset( $yoast_social['twitter'] ) ) {
			$current['twitter']['enabled'] = self::is_truthy( $yoast_social['twitter'] );
		}

		if ( isset( $yoast_social['twitter_card_type'] ) ) {
			$current['twitter']['card_type'] = sanitize_text_field( (string) $yoast_social['twitter_card_type'] );
		}

		if ( isset( $yoast_social['twitter_site'] ) ) {
			$current['twitter']['site_handle'] = sanitize_text_field( (string) $yoast_social['twitter_site'] );
		}

		return $current;
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

	/**
	 * Apply Yoast breadcrumbs settings.
	 *
	 * @param array<string, mixed> $current Current breadcrumbs config.
	 * @param array<string, mixed> $yoast_titles Yoast titles config.
	 *
	 * @return array<string, mixed>
	 */
	private static function apply_breadcrumbs_settings( array $current, array $yoast_titles ): array {
		if ( isset( $yoast_titles['breadcrumbs-enable'] ) ) {
			$current['enabled'] = (bool) $yoast_titles['breadcrumbs-enable'];
		}

		if ( isset( $yoast_titles['breadcrumbs-sep'] ) ) {
			$current['separator'] = (string) $yoast_titles['breadcrumbs-sep'];
		}

		if ( isset( $yoast_titles['breadcrumbs-prefix'] ) ) {
			$current['prefix'] = sanitize_text_field( (string) $yoast_titles['breadcrumbs-prefix'] );
		}

		if ( isset( $yoast_titles['breadcrumbs-home'] ) ) {
			$current['home']['label'] = sanitize_text_field( (string) $yoast_titles['breadcrumbs-home'] );
		}

		if ( isset( $yoast_titles['breadcrumbs-archiveprefix'] ) ) {
			$current['labels']['archive'] = sanitize_text_field( (string) $yoast_titles['breadcrumbs-archiveprefix'] );
		}

		if ( isset( $yoast_titles['breadcrumbs-searchprefix'] ) ) {
			$current['labels']['search'] = sanitize_text_field( (string) $yoast_titles['breadcrumbs-searchprefix'] );
		}

		if ( isset( $yoast_titles['breadcrumbs-404crumb'] ) ) {
			$current['labels']['error'] = sanitize_text_field( (string) $yoast_titles['breadcrumbs-404crumb'] );
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
					'key'     => Constants::META_YOAST_MIGRATED,
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
}
