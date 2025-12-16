<?php
/**
 * Adds a unified SEO overview column to post list tables.
 *
 * @package Airygen\Admin\PostListColumns
 */

declare(strict_types=1);

namespace Airygen\Admin\PostListColumns;

use Airygen\Constants;
use Airygen\Modules\LinkCounter\Domain\Storage;
use Airygen\Modules\LinkCounter\Runtime\PostTypes as LinkCounterPostTypes;
use Airygen\Modules\ScoreCalculator\Admin\Settings as ScoreCalculatorSettings;
use Airygen\Modules\TopicCluster\Admin\Settings as TopicClusterSettings;
use Airygen\Support\Database\WpDbAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders compact SEO health details in a single list-table column.
 */
final class SeoOverviewColumn {

	private const COLUMN_KEY = 'airygen_seo_overview';

	/**
	 * Link storage helper.
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Cached score payloads keyed by post ID.
	 *
	 * @var array<int, array{score: float|int, max: float|int}>
	 */
	private $score_cache = array();

	/**
	 * Cached link counts keyed by post ID.
	 *
	 * @var array<int, array<string, int>>
	 */
	private $link_counts_cache = array();

	/**
	 * Cached broken link counts keyed by post ID.
	 *
	 * @var array<int, int>
	 */
	private $broken_counts_cache = array();

	/**
	 * Cached topic cluster summaries keyed by post ID.
	 *
	 * @var array<int, array{group_name:string, level:string}>
	 */
	private $cluster_cache = array();

	/**
	 * Constructor.
	 *
	 * @param Storage $storage Link storage helper.
	 */
	public function __construct( Storage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Register hooks for supported post types.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'setup_columns' ) );
	}

	/**
	 * Register unified column on supported post types.
	 *
	 * @return void
	 */
	public function setup_columns(): void {
		foreach ( self::supported_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Add SEO column after title/name where possible.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$new_columns = array();
		$inserted    = false;

		foreach ( $columns as $key => $label ) {
			if ( in_array( $key, array( 'airygen_link_counts', 'airygen_topic_cluster', 'seo_column' ), true ) ) {
				continue;
			}

			$new_columns[ $key ] = $label;

			if ( ! $inserted && in_array( $key, array( 'title', 'name' ), true ) ) {
				$new_columns[ self::COLUMN_KEY ] = __( 'SEO', 'airygen-seo' );
				$inserted                        = true;
			}
		}

		if ( ! $inserted ) {
			$new_columns[ self::COLUMN_KEY ] = __( 'SEO', 'airygen-seo' );
		}

		return $new_columns;
	}

	/**
	 * Render unified SEO summary for a row.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		$this->warm_caches();

		$score   = $this->score_cache[ $post_id ] ?? null;
		$counts  = $this->link_counts_cache[ $post_id ] ?? array(
			'internal_link_count' => 0,
			'external_link_count' => 0,
			'incoming_link_count' => 0,
		);
		$broken  = (int) ( $this->broken_counts_cache[ $post_id ] ?? 0 );
		$cluster = $this->cluster_cache[ $post_id ] ?? null;

		$score_markup = '&#8212;';
		if ( is_array( $score ) ) {
			$percentage = 0;
			if ( (float) $score['max'] > 0 ) {
				$percentage = (int) round( ( (float) $score['score'] / (float) $score['max'] ) * 100 );
			}
			$score_tone   = self::score_tone( $percentage );
			$score_markup = sprintf(
				'<span style="display:inline-flex;align-items:center;justify-content:center;min-width:44px;padding:2px 8px;border-radius:9999px;background:%1$s;color:%2$s;font-weight:700;font-size:12px;line-height:1.4;">%3$s</span>',
				esc_attr( $score_tone['background'] ),
				esc_attr( $score_tone['text'] ),
				esc_html( number_format_i18n( $percentage ) )
			);
		}

		$cluster_markup = '&#8212;';
		if ( is_array( $cluster ) ) {
			$cluster_markup = sprintf(
				'<span style="font-weight:600;">%1$s</span>',
				esc_html( $cluster['level'] )
			);
		}

		printf(
			'<div class="airygen-seo-overview" style="display:flex;flex-direction:column;gap:6px;line-height:1.35;">' .
				'<div><span style="display:inline-block;min-width:54px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">%1$s</span> <span style="font-weight:600;color:#0f172a;">%2$s</span></div>' .
				'<div><span style="display:inline-block;min-width:54px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">%3$s</span> ' .
					'<span style="color:#2563eb;font-weight:600;" title="%4$s">%5$s</span> / ' .
					'<span style="color:#f97316;font-weight:600;" title="%6$s">%7$s</span> / ' .
					'<span style="color:#16a34a;font-weight:600;" title="%8$s">%9$s</span> / ' .
					'<span style="color:#111827;font-weight:600;" title="%10$s">%11$s</span>' .
				'</div>' .
				'<div><span style="display:inline-block;min-width:54px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">%12$s</span> <span style="color:#0f172a;">%13$s</span></div>' .
			'</div>',
			esc_html__( 'Score', 'airygen-seo' ),
			wp_kses_post( $score_markup ),
			esc_html__( 'Links', 'airygen-seo' ),
			esc_attr__( 'Internal links', 'airygen-seo' ),
			esc_html( number_format_i18n( (int) $counts['internal_link_count'] ) ),
			esc_attr__( 'External links', 'airygen-seo' ),
			esc_html( number_format_i18n( (int) $counts['external_link_count'] ) ),
			esc_attr__( 'Incoming internal links', 'airygen-seo' ),
			esc_html( number_format_i18n( (int) $counts['incoming_link_count'] ) ),
			esc_attr__( 'Broken links', 'airygen-seo' ),
			esc_html( number_format_i18n( $broken ) ),
			esc_html__( 'Cluster', 'airygen-seo' ),
			wp_kses_post( $cluster_markup )
		);
	}

	/**
	 * Resolve badge colors matching Content Score panel thresholds.
	 *
	 * @param int $score Percentage score.
	 * @return array{background: string, text: string}
	 */
	private static function score_tone( int $score ): array {
		if ( $score < 60 ) {
			return array(
				'background' => '#feefed',
				'text'       => '#f35d4a',
			);
		}

		if ( $score < 80 ) {
			return array(
				'background' => '#fef6eb',
				'text'       => '#f8a738',
			);
		}

		return array(
			'background' => '#eefaf1',
			'text'       => '#51c975',
		);
	}

	/**
	 * Load all request-scoped caches once.
	 *
	 * @return void
	 */
	private function warm_caches(): void {
		if ( ! empty( $this->score_cache ) || ! empty( $this->link_counts_cache ) || ! empty( $this->broken_counts_cache ) || ! empty( $this->cluster_cache ) ) {
			return;
		}

		$post_ids = $this->current_post_ids();
		if ( empty( $post_ids ) ) {
			return;
		}

		$this->warm_score_cache( $post_ids );
		$this->link_counts_cache = $this->storage->get_counts_for_posts( $post_ids );
		$this->warm_broken_counts_cache( $post_ids );
		$this->warm_cluster_cache( $post_ids );
	}

	/**
	 * Resolve IDs shown on current list page.
	 *
	 * @return array<int, int>
	 */
	private function current_post_ids(): array {
		global $wp_query;

		if ( ! isset( $wp_query->posts ) || ! is_array( $wp_query->posts ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'intval', wp_list_pluck( $wp_query->posts, 'ID' ) )
			)
		);
	}

	/**
	 * Warm score cache from post meta.
	 *
	 * @param array<int, int> $post_ids Post IDs.
	 * @return void
	 */
	private function warm_score_cache( array $post_ids ): void {
		foreach ( $post_ids as $post_id ) {
			$cache = get_post_meta( $post_id, Constants::META_SCORE_CACHE, true );
			if ( ! is_array( $cache ) ) {
				continue;
			}

			if ( ! isset( $cache['score'] ) || ! isset( $cache['max'] ) || ! is_numeric( $cache['score'] ) || ! is_numeric( $cache['max'] ) ) {
				continue;
			}

			$this->score_cache[ $post_id ] = array(
				'score' => 0 + $cache['score'],
				'max'   => 0 + $cache['max'],
			);
		}
	}

	/**
	 * Warm broken-link counts from the broken-link log table.
	 *
	 * @param array<int, int> $post_ids Post IDs.
	 * @return void
	 */
	private function warm_broken_counts_cache( array $post_ids ): void {
		$adapter   = new WpDbAdapter();
		$log_table = $adapter->table( Constants::TABLE_LINK_CHECKER_LOG );
		if ( ! $adapter->table_exists( $log_table ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$params       = array_merge( $post_ids, array( 'client_error', 'server_error', 'error' ) );
		$rows         = $adapter->get_results(
			"SELECT post_id, COUNT(*) AS broken_count
			 FROM {$log_table}
			 WHERE post_id IN ({$placeholders})
			 AND (
			 	status_code = 0
			 	OR status_code >= 400
			 	OR status_label IN (%s, %s, %s)
			 )
			 GROUP BY post_id",
			$params,
			\ARRAY_A
		);

		foreach ( $rows as $row ) {
			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $post_id <= 0 ) {
				continue;
			}

			$this->broken_counts_cache[ $post_id ] = isset( $row['broken_count'] ) ? (int) $row['broken_count'] : 0;
		}
	}

	/**
	 * Warm topic cluster summaries for current posts.
	 *
	 * @param array<int, int> $post_ids Post IDs.
	 * @return void
	 */
	private function warm_cluster_cache( array $post_ids ): void {
		$adapter         = new WpDbAdapter();
		$relations_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$groups_table    = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );

		if ( ! $adapter->table_exists( $relations_table ) || ! $adapter->table_exists( $groups_table ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$rows         = $adapter->get_results(
			"SELECT r.post_id, r.level, g.name AS group_name
			 FROM {$relations_table} r
			 LEFT JOIN {$groups_table} g ON g.id = r.group_id
			 WHERE r.post_id IN ({$placeholders})",
			$post_ids,
			\ARRAY_A
		);

		foreach ( $rows as $row ) {
			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $post_id <= 0 ) {
				continue;
			}

			$group                           = isset( $row['group_name'] ) ? (string) $row['group_name'] : '';
			$this->cluster_cache[ $post_id ] = array(
				'group_name' => '' !== $group ? $group : __( 'Unassigned', 'airygen-seo' ),
				'level'      => self::to_level_label( isset( $row['level'] ) ? (int) $row['level'] : 0 ),
			);
		}
	}

	/**
	 * Resolve post types that should display the SEO overview column.
	 *
	 * @return array<int, string>
	 */
	private static function supported_post_types(): array {
		$post_types = array_merge(
			LinkCounterPostTypes::names(),
			self::topic_cluster_post_types(),
			self::score_post_types()
		);
		$post_types = array_values( array_unique( array_filter( array_map( 'strval', $post_types ) ) ) );

		return array_values(
			array_filter(
				$post_types,
				static function ( string $post_type ): bool {
					return '' !== $post_type && post_type_exists( $post_type );
				}
			)
		);
	}

	/**
	 * Topic Cluster scoped post types.
	 *
	 * @return array<int, string>
	 */
	private static function topic_cluster_post_types(): array {
		$settings = TopicClusterSettings::get();
		if ( ! isset( $settings['post_types'] ) || ! is_array( $settings['post_types'] ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'strval', $settings['post_types'] ) ) );
	}

	/**
	 * Score Calculator scoped post types.
	 *
	 * @return array<int, string>
	 */
	private static function score_post_types(): array {
		$settings = ScoreCalculatorSettings::get();
		if ( ! isset( $settings['postTypes'] ) || ! is_array( $settings['postTypes'] ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'strval', $settings['postTypes'] ) ) );
	}

	/**
	 * Convert numeric level to L1/L2/L3 label.
	 *
	 * @param int $level Numeric level.
	 * @return string
	 */
	private static function to_level_label( int $level ): string {
		if ( 1 === $level ) {
			return 'L1';
		}

		if ( 2 === $level ) {
			return 'L2';
		}

		if ( 3 === $level ) {
			return 'L3';
		}

		return __( 'Not set', 'airygen-seo' );
	}
}
