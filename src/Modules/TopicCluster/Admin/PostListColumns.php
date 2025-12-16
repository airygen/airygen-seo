<?php
/**
 * Adds Topic Cluster metadata to post list tables.
 *
 * @package Airygen\Modules\TopicCluster\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\TopicCluster\Admin;

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Topic Cluster admin list-table columns.
 */
final class PostListColumns {

	private const COLUMN_KEY = 'airygen_topic_cluster';

	/**
	 * Cached map for current list table request.
	 *
	 * @var array<int, array{group_name:string, level:string}>
	 */
	private $cluster_cache = array();

	/**
	 * Register setup hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'setup_columns' ) );
	}

	/**
	 * Register columns for enabled scope post types.
	 *
	 * @return void
	 */
	public function setup_columns(): void {
		$settings   = Settings::get();
		$post_types = array();
		if ( isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ) {
			$post_types = array_values( array_filter( array_map( 'strval', $settings['post_types'] ) ) );
		}

		if ( empty( $post_types ) ) {
			return;
		}

		foreach ( $post_types as $post_type ) {
			if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
				continue;
			}

			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Add Topic Cluster column after title when possible.
	 *
	 * @param array<string, string> $columns Current list columns.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$new_columns = array();
		$inserted    = false;

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( ! $inserted && in_array( $key, array( 'title', 'name' ), true ) ) {
				$new_columns[ self::COLUMN_KEY ] = __( 'Topic Cluster', 'airygen-seo' );
				$inserted                        = true;
			}
		}

		if ( ! $inserted ) {
			$new_columns[ self::COLUMN_KEY ] = __( 'Topic Cluster', 'airygen-seo' );
		}

		return $new_columns;
	}

	/**
	 * Render Topic Cluster cell.
	 *
	 * @param string $column  Current column key.
	 * @param int    $post_id Row post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		if ( empty( $this->cluster_cache ) ) {
			$this->warm_cluster_cache();
		}

		$entry = $this->cluster_cache[ $post_id ] ?? null;
		if ( null === $entry ) {
			echo '&#8212;';
			return;
		}

		printf(
			'%1$s %2$s<br />%3$s %4$s',
			esc_html__( 'Group:', 'airygen-seo' ),
			esc_html( $entry['group_name'] ),
			esc_html__( 'Topic Level:', 'airygen-seo' ),
			esc_html( $entry['level'] )
		);
	}

	/**
	 * Load Topic Cluster info for currently displayed rows.
	 *
	 * @return void
	 */
	private function warm_cluster_cache(): void {
		global $wp_query;

		if ( ! isset( $wp_query->posts ) || ! is_array( $wp_query->posts ) ) {
			return;
		}

		$post_ids = array_map( 'intval', wp_list_pluck( $wp_query->posts, 'ID' ) );
		$post_ids = array_values( array_filter( $post_ids ) );

		if ( empty( $post_ids ) ) {
			return;
		}

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

			$level = self::to_level_label( isset( $row['level'] ) ? (int) $row['level'] : 0 );
			$group = isset( $row['group_name'] ) ? (string) $row['group_name'] : '';

			$this->cluster_cache[ $post_id ] = array(
				'group_name' => '' !== $group ? $group : __( 'Unassigned', 'airygen-seo' ),
				'level'      => $level,
			);
		}
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
