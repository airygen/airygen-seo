<?php
/**
 * REST endpoints for Topic Cluster.
 *
 * @package Airygen\Modules\TopicCluster\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\TopicCluster\Admin;

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestController {

	/**
	 * Check whether the current user can manage topic clusters.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Return L1/L2 cluster list with optional current post entry.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_list( WP_REST_Request $request ) {
		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$rows = $adapter->get_results(
			"SELECT post_id, level, parent_post_id, group_id, root_id
			 FROM {$table}
			 WHERE level IN (%d, %d)
			 ORDER BY group_id ASC, level ASC, post_id ASC",
			array( 1, 2 ),
			\ARRAY_A
		);

		$items = array();
		foreach ( $rows as $row ) {
			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $post_id <= 0 ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$items[] = array(
				'id'              => $post_id,
				'title'           => get_the_title( $post ),
				'level'           => self::level_code_to_label( (int) $row['level'] ),
				'parent_post_id'  => isset( $row['parent_post_id'] ) ? (int) $row['parent_post_id'] : null,
				'cluster_root_id' => isset( $row['root_id'] ) ? (int) $row['root_id'] : null,
			);
		}

		$current = null;
		$post_id = (int) $request->get_param( 'post' );
		if ( $post_id > 0 ) {
			$current_row = $adapter->get_results(
				"SELECT post_id, level, parent_post_id, group_id, root_id
				 FROM {$table}
				 WHERE post_id = %d
				 LIMIT 1",
				array( $post_id ),
				\ARRAY_A
			);

			if ( ! empty( $current_row ) ) {
				$entry   = $current_row[0];
				$current = array(
					'post_id'         => (int) $entry['post_id'],
					'level'           => self::level_code_to_label( (int) $entry['level'] ),
					'parent_post_id'  => isset( $entry['parent_post_id'] ) ? (int) $entry['parent_post_id'] : null,
					'cluster_root_id' => isset( $entry['root_id'] ) ? (int) $entry['root_id'] : null,
				);
			}
		}

		return new WP_REST_Response(
			array(
				'items'   => $items,
				'current' => $current,
			)
		);
	}

	/**
	 * Return paginated Topic Cluster groups.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_groups( WP_REST_Request $request ) {
		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster group table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		if ( $page < 1 ) {
			$page = 1;
		}

		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		$total_groups = (int) $adapter->get_var(
			"SELECT COUNT(*)
			 FROM {$table}
			 WHERE id >= %d",
			array( 0 )
		);

		$total_pages = $total_groups > 0 ? (int) ceil( $total_groups / $per_page ) : 1;
		if ( $page > $total_pages ) {
			$page = $total_pages;
		}

		$offset = ( $page - 1 ) * $per_page;
		$rows   = $adapter->get_results(
			"SELECT id, name, description, updated_at
			 FROM {$table}
			 ORDER BY updated_at DESC, id DESC
			 LIMIT %d OFFSET %d",
			array( $per_page, $offset ),
			\ARRAY_A
		);

		$stats            = array();
		$relations_table  = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$group_ids        = array();
		foreach ( $rows as $row ) {
			$group_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $group_id > 0 ) {
				$group_ids[] = $group_id;
			}
		}

		if ( ! empty( $group_ids ) && $adapter->table_exists( $relations_table ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
			$stats_rows   = $adapter->get_results(
				"SELECT group_id,
					MAX(CASE WHEN level = 1 THEN post_id ELSE 0 END) AS l1_post_id,
					SUM(CASE WHEN level = 1 THEN 1 ELSE 0 END) AS l1_count,
					SUM(CASE WHEN level = 2 THEN 1 ELSE 0 END) AS l2_count,
					SUM(CASE WHEN level = 3 THEN 1 ELSE 0 END) AS l3_count
				 FROM {$relations_table}
				 WHERE group_id IN ({$placeholders})
				 GROUP BY group_id",
				$group_ids,
				\ARRAY_A
			);

			foreach ( $stats_rows as $stats_row ) {
				$group_id = isset( $stats_row['group_id'] ) ? (int) $stats_row['group_id'] : 0;
				if ( $group_id <= 0 ) {
					continue;
				}
				$stats[ $group_id ] = array(
					'l1_post_id' => isset( $stats_row['l1_post_id'] ) ? (int) $stats_row['l1_post_id'] : 0,
					'l1_count'   => isset( $stats_row['l1_count'] ) ? (int) $stats_row['l1_count'] : 0,
					'l2_count'   => isset( $stats_row['l2_count'] ) ? (int) $stats_row['l2_count'] : 0,
					'l3_count'   => isset( $stats_row['l3_count'] ) ? (int) $stats_row['l3_count'] : 0,
				);
			}
		}

		$candidate_counts = array();
		if ( ! empty( $group_ids ) && $adapter->table_exists( $candidates_table ) ) {
			$placeholders   = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
			$candidate_rows = $adapter->get_results(
				"SELECT group_id, COUNT(*) AS total
				 FROM {$candidates_table}
				 WHERE group_id IN ({$placeholders})
				 GROUP BY group_id",
				$group_ids,
				\ARRAY_A
			);

			foreach ( $candidate_rows as $candidate_row ) {
				$group_id = isset( $candidate_row['group_id'] ) ? (int) $candidate_row['group_id'] : 0;
				if ( $group_id <= 0 ) {
					continue;
				}
				$candidate_counts[ $group_id ] = isset( $candidate_row['total'] ) ? (int) $candidate_row['total'] : 0;
			}
		}

		$items = array();
		foreach ( $rows as $row ) {
			$group_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $group_id <= 0 ) {
				continue;
			}

			$l1_count = isset( $stats[ $group_id ]['l1_count'] ) ? (int) $stats[ $group_id ]['l1_count'] : 0;
			$l2_count = isset( $stats[ $group_id ]['l2_count'] ) ? (int) $stats[ $group_id ]['l2_count'] : 0;
			$l3_count = isset( $stats[ $group_id ]['l3_count'] ) ? (int) $stats[ $group_id ]['l3_count'] : 0;
			$l1_post  = isset( $stats[ $group_id ]['l1_post_id'] ) ? (int) $stats[ $group_id ]['l1_post_id'] : 0;
			$pillar   = $l1_post > 0 ? get_post( $l1_post ) : null;

			$items[] = array(
				'group_id'         => $group_id,
				'group_name'       => isset( $row['name'] ) ? (string) $row['name'] : '',
				'description'      => isset( $row['description'] ) ? (string) $row['description'] : '',
				'l1_count'         => $l1_count,
				'pillar_title'     => $pillar ? get_the_title( $pillar ) : '',
				'pillar_edit'      => $pillar ? get_edit_post_link( $l1_post, 'raw' ) : '',
				'l2_count'         => $l2_count,
				'l3_count'         => $l3_count,
				'candidates_count' => isset( $candidate_counts[ $group_id ] ) ? (int) $candidate_counts[ $group_id ] : 0,
				'updated_at'       => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
				'total_members'    => $l1_count + $l2_count + $l3_count,
			);
		}

		return new WP_REST_Response(
			array(
				'groups'     => $items,
				'pagination' => array(
					'page'       => $page,
					'perPage'    => $per_page,
					'total'      => $total_groups,
					'totalPages' => $total_pages,
				),
			)
		);
	}

	/**
	 * Create a Topic Cluster group record.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_create_group( WP_REST_Request $request ) {
		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster group table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$name        = trim( (string) $request->get_param( 'name' ) );
		$description = trim( (string) $request->get_param( 'description' ) );

		if ( '' === $name ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_NAME_REQUIRED,
				__( 'Group name is required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$now = current_time( 'mysql' );

		$inserted = $adapter->insert(
			$table,
			array(
				'name'        => $name,
				'description' => $description,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_CREATE_FAILED,
				__( 'Unable to create group.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'id'          => isset( $GLOBALS['wpdb']->insert_id ) ? (int) $GLOBALS['wpdb']->insert_id : 0, // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				'name'        => $name,
				'description' => $description,
				'created_at'  => $now,
			),
			201
		);
	}

	/**
	 * Update a Topic Cluster group.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_group( WP_REST_Request $request ) {
		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster group table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$group_id = (int) $request->get_param( 'id' );
		if ( $group_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_INVALID_ID,
				__( 'A valid group ID is required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$name        = trim( (string) $request->get_param( 'name' ) );
		$description = trim( (string) $request->get_param( 'description' ) );

		if ( '' === $name ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_NAME_REQUIRED,
				__( 'Group name is required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$exists = (int) $adapter->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE id = %d",
			array( $group_id )
		);

		if ( $exists <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_NOT_FOUND,
				__( 'Group not found.', 'airygen-seo' ),
				array( 'status' => 404 )
			);
		}

		$now    = current_time( 'mysql' );
		$result = $adapter->query(
			"UPDATE {$table}
			 SET name = %s,
				 description = %s,
				 updated_at = %s
			 WHERE id = %d",
			array(
				$name,
				$description,
				$now,
				$group_id,
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_UPDATE_FAILED,
				__( 'Unable to update group.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'id'          => $group_id,
				'name'        => $name,
				'description' => $description,
				'updated_at'  => $now,
			)
		);
	}

	/**
	 * Update persisted mind map layout for a Topic Cluster group.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_group_map( WP_REST_Request $request ) {
		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster group table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$group_id = (int) $request->get_param( 'id' );
		if ( $group_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_INVALID_ID,
				__( 'A valid group ID is required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$exists = (int) $adapter->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE id = %d",
			array( $group_id )
		);
		if ( $exists <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_NOT_FOUND,
				__( 'Group not found.', 'airygen-seo' ),
				array( 'status' => 404 )
			);
		}

		$map = $request->get_param( 'map' );
		if ( null === $map ) {
			$map = array();
		}

		if ( is_string( $map ) ) {
			$decoded = json_decode( $map, true );
			$map     = is_array( $decoded ) ? $decoded : null;
		}

		if ( ! is_array( $map ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID,
				__( 'Map payload must be a JSON object.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$now     = current_time( 'mysql' );
		$encoded = wp_json_encode( $map );
		$updated = $adapter->query(
			"UPDATE {$table}
			 SET map_json = %s,
				 updated_at = %s
			 WHERE id = %d",
			array(
				false !== $encoded ? $encoded : '{}',
				$now,
				$group_id,
			)
		);

		if ( false === $updated ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_UPDATE_FAILED,
				__( 'Unable to save map layout.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'id'         => $group_id,
				'map'        => $map,
				'updated_at' => $now,
			)
		);
	}

	/**
	 * Persist draft mind map state for one Topic Cluster group.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_sync_group_mindmap( WP_REST_Request $request ) {
		$adapter          = new WpDbAdapter();
		$groups_table     = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );
		$relations_table  = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$group_id         = (int) $request->get_param( 'id' );

		if ( $group_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_INVALID_ID,
				__( 'A valid group ID is required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $adapter->table_exists( $groups_table ) || ! $adapter->table_exists( $relations_table ) || ! $adapter->table_exists( $candidates_table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster tables are not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$exists = (int) $adapter->get_var(
			"SELECT COUNT(*) FROM {$groups_table} WHERE id = %d",
			array( $group_id )
		);
		if ( $exists <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_NOT_FOUND,
				__( 'Group not found.', 'airygen-seo' ),
				array( 'status' => 404 )
			);
		}

		$items_payload      = $request->get_param( 'items' );
		$candidates_payload = $request->get_param( 'candidates' );
		$map_payload        = $request->get_param( 'map' );

		$items_payload      = is_array( $items_payload ) ? $items_payload : array();
		$candidates_payload = is_array( $candidates_payload ) ? $candidates_payload : array();
		$map_payload        = is_array( $map_payload ) ? $map_payload : array();

		$normalized_items = array();
		$item_ids         = array();
		$l1_ids           = array();

		foreach ( $items_payload as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$post_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
			$level   = isset( $item['level'] ) ? strtoupper( (string) $item['level'] ) : '';
			if ( $post_id <= 0 || ! in_array( $level, array( 'L1', 'L2', 'L3' ), true ) ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID,
					__( 'Mind map items contain invalid post data.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}

			if ( ! current_user_can( 'edit_post', $post_id ) || ! get_post( $post_id ) ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_FORBIDDEN,
					__( 'One or more posts cannot be edited.', 'airygen-seo' ),
					array( 'status' => 403 )
				);
			}

			$normalized_items[ $post_id ] = array(
				'id'             => $post_id,
				'level'          => $level,
				'parent_post_id' => isset( $item['parent_post_id'] ) ? (int) $item['parent_post_id'] : 0,
				'prev_post_id'   => isset( $item['prev_post_id'] ) ? (int) $item['prev_post_id'] : 0,
				'next_post_id'   => isset( $item['next_post_id'] ) ? (int) $item['next_post_id'] : 0,
			);
			$item_ids[]                   = $post_id;
			if ( 'L1' === $level ) {
				$l1_ids[] = $post_id;
			}
		}

		if ( ! empty( $normalized_items ) && 1 !== count( $l1_ids ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID,
				__( 'Each group must contain exactly one L1 topic before saving the mind map.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$root_post_id = ! empty( $l1_ids ) ? (int) $l1_ids[0] : 0;

		foreach ( $normalized_items as $post_id => $item ) {
			if ( 'L1' === $item['level'] ) {
				continue;
			}

			$parent_post_id = (int) $item['parent_post_id'];
			if ( $parent_post_id <= 0 || ! isset( $normalized_items[ $parent_post_id ] ) ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID,
					__( 'Every L2 or L3 topic must reference a valid parent in the same group.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}

			$parent_level = $normalized_items[ $parent_post_id ]['level'];
			if ( 'L2' === $item['level'] && 'L1' !== $parent_level ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID,
					__( 'L2 topics must reference an L1 parent.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}
			if ( 'L3' === $item['level'] && 'L2' !== $parent_level ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID,
					__( 'L3 topics must reference an L2 parent.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}
		}

		foreach ( $normalized_items as $post_id => $item ) {
			$prev_post_id = (int) $item['prev_post_id'];
			$next_post_id = (int) $item['next_post_id'];

			if ( $prev_post_id > 0 ) {
				if ( ! isset( $normalized_items[ $prev_post_id ] ) || $normalized_items[ $prev_post_id ]['level'] !== $item['level'] ) {
					return new WP_Error(
						ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID,
						__( 'Previous ordering links must point to the same level in the same group.', 'airygen-seo' ),
						array( 'status' => 400 )
					);
				}
				if ( 'L3' === $item['level'] && (int) $normalized_items[ $prev_post_id ]['parent_post_id'] !== (int) $item['parent_post_id'] ) {
					return new WP_Error(
						ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID,
						__( 'L3 ordering links must stay within the same parent topic.', 'airygen-seo' ),
						array( 'status' => 400 )
					);
				}
			}

			if ( $next_post_id > 0 ) {
				if ( ! isset( $normalized_items[ $next_post_id ] ) || $normalized_items[ $next_post_id ]['level'] !== $item['level'] ) {
					return new WP_Error(
						ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID,
						__( 'Next ordering links must point to the same level in the same group.', 'airygen-seo' ),
						array( 'status' => 400 )
					);
				}
				if ( 'L3' === $item['level'] && (int) $normalized_items[ $next_post_id ]['parent_post_id'] !== (int) $item['parent_post_id'] ) {
					return new WP_Error(
						ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID,
						__( 'L3 ordering links must stay within the same parent topic.', 'airygen-seo' ),
						array( 'status' => 400 )
					);
				}
			}
		}

		$normalized_candidates = array();
		foreach ( $candidates_payload as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}
			$post_id = isset( $candidate['post_id'] ) ? (int) $candidate['post_id'] : 0;
			if ( $post_id <= 0 || isset( $normalized_items[ $post_id ] ) ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) || ! get_post( $post_id ) ) {
				continue;
			}
			$normalized_candidates[ $post_id ] = $post_id;
		}

		$now = current_time( 'mysql' );

		foreach ( $item_ids as $post_id ) {
			$adapter->delete( $relations_table, array( 'post_id' => $post_id ), array( '%d' ) );
			$adapter->delete( $candidates_table, array( 'post_id' => $post_id ), array( '%d' ) );
		}

		$adapter->delete( $relations_table, array( 'group_id' => $group_id ), array( '%d' ) );
		$adapter->delete( $candidates_table, array( 'group_id' => $group_id ), array( '%d' ) );

		foreach ( $normalized_items as $item ) {
			$adapter->insert(
				$relations_table,
				array(
					'post_id'        => (int) $item['id'],
					'level'          => self::level_label_to_code( $item['level'] ),
					'parent_post_id' => (int) $item['parent_post_id'] > 0 ? (int) $item['parent_post_id'] : null,
					'group_id'       => $group_id,
					'root_id'        => $root_post_id,
					'prev_post_id'   => (int) $item['prev_post_id'] > 0 ? (int) $item['prev_post_id'] : null,
					'next_post_id'   => (int) $item['next_post_id'] > 0 ? (int) $item['next_post_id'] : null,
					'updated_at'     => $now,
				),
				array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
			);
		}

		foreach ( $normalized_candidates as $post_id ) {
			$adapter->insert(
				$candidates_table,
				array(
					'group_id'   => $group_id,
					'post_id'    => $post_id,
					'created_at' => $now,
				),
				array( '%d', '%d', '%s' )
			);
		}

		$encoded_map = wp_json_encode( $map_payload );
		$adapter->query(
			"UPDATE {$groups_table}
			 SET map_json = %s,
				 updated_at = %s
			 WHERE id = %d",
			array(
				false !== $encoded_map ? $encoded_map : '{}',
				$now,
				$group_id,
			)
		);

		return new WP_REST_Response(
			array(
				'id'         => $group_id,
				'items'      => array_values( $normalized_items ),
				'candidates' => array_map(
					static function ( int $post_id ): array {
						return array( 'post_id' => $post_id );
					},
					array_values( $normalized_candidates )
				),
				'map'        => $map_payload,
				'updated_at' => $now,
			)
		);
	}

	/**
	 * Delete a Topic Cluster group.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_delete_group( WP_REST_Request $request ) {
		$adapter    = new WpDbAdapter();
		$table      = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );
		$relations  = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$candidates = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$group_id   = (int) $request->get_param( 'id' );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster group table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		if ( $group_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_INVALID_ID,
				__( 'A valid group ID is required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$exists = (int) $adapter->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE id = %d",
			array( $group_id )
		);

		if ( $exists <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_NOT_FOUND,
				__( 'Group not found.', 'airygen-seo' ),
				array( 'status' => 404 )
			);
		}

		if ( $adapter->table_exists( $relations ) ) {
			$related_count = (int) $adapter->get_var(
				"SELECT COUNT(*) FROM {$relations} WHERE group_id = %d",
				array( $group_id )
			);
			if ( $related_count > 0 ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_NOT_EMPTY,
					__( 'You can only remove an empty group.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}
		}

		$deleted = $adapter->delete(
			$table,
			array( 'id' => $group_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_DELETE_FAILED,
				__( 'Unable to remove group.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		if ( $adapter->table_exists( $candidates ) ) {
			$adapter->delete(
				$candidates,
				array( 'group_id' => $group_id ),
				array( '%d' )
			);
		}

		return new WP_REST_Response(
			array(
				'id'      => $group_id,
				'deleted' => true,
			)
		);
	}

	/**
	 * Return candidates of a group.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_group_candidates( WP_REST_Request $request ) {
		$adapter          = new WpDbAdapter();
		$groups_table     = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );
		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$group_id         = (int) $request->get_param( 'id' );

		if ( $group_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_INVALID_ID,
				__( 'A valid group ID is required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $adapter->table_exists( $groups_table ) || ! $adapter->table_exists( $candidates_table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster tables are not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$exists = (int) $adapter->get_var(
			"SELECT COUNT(*) FROM {$groups_table} WHERE id = %d",
			array( $group_id )
		);
		if ( $exists <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_NOT_FOUND,
				__( 'Group not found.', 'airygen-seo' ),
				array( 'status' => 404 )
			);
		}

		$rows       = $adapter->get_results(
			"SELECT id, post_id
			 FROM {$candidates_table}
			 WHERE group_id = %d
			 ORDER BY id DESC",
			array( $group_id ),
			\ARRAY_A
		);
		$candidates = array();
		foreach ( $rows as $row ) {
			$candidate_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$post_id      = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $candidate_id <= 0 || $post_id <= 0 ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$candidates[] = array(
				'id'      => $candidate_id,
				'post_id' => $post_id,
				'title'   => get_the_title( $post ),
			);
		}

		return new WP_REST_Response(
			array(
				'candidates' => $candidates,
			)
		);
	}

	/**
	 * Search posts for candidate insertion.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_search_candidates( WP_REST_Request $request ) {
		$adapter          = new WpDbAdapter();
		$groups_table     = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );
		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$relations_table  = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$group_id         = (int) $request->get_param( 'id' );
		$query            = trim( (string) $request->get_param( 'q' ) );

		if ( $group_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_INVALID_ID,
				__( 'A valid group ID is required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $adapter->table_exists( $groups_table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster group table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$exists = (int) $adapter->get_var(
			"SELECT COUNT(*) FROM {$groups_table} WHERE id = %d",
			array( $group_id )
		);
		if ( $exists <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_NOT_FOUND,
				__( 'Group not found.', 'airygen-seo' ),
				array( 'status' => 404 )
			);
		}

		if ( '' === $query ) {
			return new WP_REST_Response(
				array(
					'items' => array(),
				)
			);
		}

		$exclude = array();
		if ( $adapter->table_exists( $relations_table ) ) {
			$relation_rows = $adapter->get_results(
				"SELECT post_id FROM {$relations_table} WHERE post_id > %d",
				array( 0 ),
				\ARRAY_A
			);
			foreach ( $relation_rows as $row ) {
				$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
				if ( $post_id > 0 ) {
					$exclude[ $post_id ] = true;
				}
			}
		}

		if ( $adapter->table_exists( $candidates_table ) ) {
			$candidate_rows = $adapter->get_results(
				"SELECT post_id FROM {$candidates_table} WHERE group_id = %d",
				array( $group_id ),
				\ARRAY_A
			);
			foreach ( $candidate_rows as $row ) {
				$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
				if ( $post_id > 0 ) {
					$exclude[ $post_id ] = true;
				}
			}
		}

		$search = get_posts(
			array(
				's'              => $query,
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 20,
				'fields'         => 'ids',
			)
		);

		$items = array();
		foreach ( $search as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || isset( $exclude[ $post_id ] ) ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$items[] = array(
				'post_id' => $post_id,
				'title'   => get_the_title( $post ),
			);
		}

		return new WP_REST_Response(
			array(
				'items' => $items,
			)
		);
	}

	/**
	 * Add a post into group candidates.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_add_candidate( WP_REST_Request $request ) {
		$adapter          = new WpDbAdapter();
		$groups_table     = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );
		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$relations_table  = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$group_id         = (int) $request->get_param( 'id' );
		$post_id          = (int) $request->get_param( 'post_id' );

		if ( $group_id <= 0 || $post_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_CANDIDATE_INVALID,
				__( 'A valid group and post are required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $adapter->table_exists( $groups_table ) || ! $adapter->table_exists( $candidates_table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster tables are not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$group_exists = (int) $adapter->get_var(
			"SELECT COUNT(*) FROM {$groups_table} WHERE id = %d",
			array( $group_id )
		);
		if ( $group_exists <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_GROUP_NOT_FOUND,
				__( 'Group not found.', 'airygen-seo' ),
				array( 'status' => 404 )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_CANDIDATE_POST_NOT_FOUND,
				__( 'Post not found.', 'airygen-seo' ),
				array( 'status' => 404 )
			);
		}

		if ( $adapter->table_exists( $relations_table ) ) {
			$related = (int) $adapter->get_var(
				"SELECT COUNT(*) FROM {$relations_table} WHERE post_id = %d",
				array( $post_id )
			);
			if ( $related > 0 ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_CANDIDATE_ALREADY_RELATED,
					__( 'This post is already linked in Topic Cluster relations.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}
		}

		$exists = (int) $adapter->get_var(
			"SELECT COUNT(*) FROM {$candidates_table} WHERE group_id = %d AND post_id = %d",
			array( $group_id, $post_id )
		);
		if ( $exists > 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_CANDIDATE_EXISTS,
				__( 'This post already exists in candidates.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$inserted = $adapter->insert(
			$candidates_table,
			array(
				'group_id'   => $group_id,
				'post_id'    => $post_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_CANDIDATE_CREATE_FAILED,
				__( 'Unable to add candidate.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'id'      => isset( $GLOBALS['wpdb']->insert_id ) ? (int) $GLOBALS['wpdb']->insert_id : 0, // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				'post_id' => $post_id,
				'title'   => get_the_title( $post ),
			),
			201
		);
	}

	/**
	 * Remove a candidate from a group.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_delete_candidate( WP_REST_Request $request ) {
		$adapter          = new WpDbAdapter();
		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$group_id         = (int) $request->get_param( 'id' );
		$candidate_id     = (int) $request->get_param( 'candidate_id' );

		if ( $group_id <= 0 || $candidate_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_CANDIDATE_INVALID,
				__( 'A valid group and candidate are required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $adapter->table_exists( $candidates_table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster candidate table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$deleted = $adapter->delete(
			$candidates_table,
			array(
				'id'       => $candidate_id,
				'group_id' => $group_id,
			),
			array(
				'%d',
				'%d',
			)
		);

		if ( false === $deleted ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_CANDIDATE_DELETE_FAILED,
				__( 'Unable to remove candidate.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'id'      => $candidate_id,
				'deleted' => true,
			)
		);
	}

	/**
	 * Return Topic Cluster summary for a post.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_summary( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post' );
		if ( $post_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_POST,
				__( 'A valid post ID is required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$groups  = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$current = self::fetch_entry( $adapter, $table, $post_id );
		if ( ! $current || empty( $current['level'] ) ) {
			return new WP_REST_Response( array( 'current' => null ) );
		}

		$level           = self::level_code_to_label( (int) $current['level'] );
		$cluster_root_id = isset( $current['root_id'] ) ? (int) $current['root_id'] : 0;
		$group_id        = isset( $current['group_id'] ) ? (int) $current['group_id'] : 0;
		$parent_id       = isset( $current['parent_post_id'] ) ? (int) $current['parent_post_id'] : 0;

		$l1_id = 0;
		$l2_id = 0;
		if ( 'L1' === $level ) {
			$l1_id = (int) $current['post_id'];
		} elseif ( 'L2' === $level ) {
			$l1_id = $parent_id;
			$l2_id = (int) $current['post_id'];
		} else {
			$l1_id = $cluster_root_id;
			$l2_id = $parent_id;
		}
		if ( $l1_id <= 0 && 'L1' === $level ) {
			$l1_id = (int) $current['post_id'];
		}

		$l1_title = $l1_id ? get_the_title( $l1_id ) : '';
		$l2_title = $l2_id ? get_the_title( $l2_id ) : '';

		$l1_edit = $l1_id ? get_edit_post_link( $l1_id, 'raw' ) : '';
		$l2_edit = $l2_id ? get_edit_post_link( $l2_id, 'raw' ) : '';

		$l2_count  = 0;
		$l3_count  = 0;
		$l3_for_l2 = 0;

		if ( $l1_id ) {
			$l2_count = (int) $adapter->get_var(
				"SELECT COUNT(*) FROM {$table} WHERE parent_post_id = %d AND level = %d",
				array( $l1_id, 2 )
			);
			$l3_count = (int) $adapter->get_var(
				"SELECT COUNT(*) FROM {$table} WHERE root_id = %d AND level = %d",
				array( $l1_id, 3 )
			);
		}

		if ( $l2_id ) {
			$l3_for_l2 = (int) $adapter->get_var(
				"SELECT COUNT(*) FROM {$table} WHERE parent_post_id = %d AND level = %d",
				array( $l2_id, 3 )
			);
		}

		$group_name = '';
		if ( $group_id > 0 && $adapter->table_exists( $groups ) ) {
			$found_name = $adapter->get_var(
				"SELECT name FROM {$groups} WHERE id = %d LIMIT 1",
				array( $group_id )
			);
			if ( is_string( $found_name ) ) {
				$group_name = $found_name;
			}
		}
		if ( '' === $group_name && $group_id > 0 ) {
			$group_name = sprintf(
				/* translators: %d is the Topic Cluster group ID. */
				__( 'Group #%d', 'airygen-seo' ),
				$group_id
			);
		}

		return new WP_REST_Response(
			array(
				'current' => array(
					'post_id' => $post_id,
					'level'   => $level,
				),
				'l1'      => array(
					'id'    => $l1_id,
					'title' => $l1_title,
					'edit'  => $l1_edit,
					'l2'    => $l2_count,
					'l3'    => $l3_count,
				),
				'l2'      => array(
					'id'    => $l2_id,
					'title' => $l2_title,
					'edit'  => $l2_edit,
					'l3'    => $l3_for_l2,
				),
				'group'   => array(
					'id'           => $group_id,
					'name'         => $group_name,
					'mind_map_url' => $group_id > 0 ? admin_url( 'admin.php?page=airygen-topic-cluster&tab=mindmap&group_id=' . $group_id ) : '',
				),
			)
		);
	}

	/**
	 * Return all Topic Cluster entries for the mind map.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_mindmap( WP_REST_Request $request ) {
		$adapter          = new WpDbAdapter();
		$table            = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$groups_table     = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );
		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$group_id         = (int) $request->get_param( 'group_id' );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		if ( $group_id > 0 ) {
			$rows = $adapter->get_results(
				"SELECT post_id, level, parent_post_id, prev_post_id, next_post_id, group_id, root_id
				 FROM {$table}
				 WHERE level IN (%d, %d, %d)
				   AND group_id = %d
				 ORDER BY group_id ASC, level ASC, post_id ASC",
				array( 1, 2, 3, $group_id ),
				\ARRAY_A
			);
		} else {
			$rows = $adapter->get_results(
				"SELECT post_id, level, parent_post_id, prev_post_id, next_post_id, group_id, root_id
				 FROM {$table}
				 WHERE level IN (%d, %d, %d)
				 ORDER BY group_id ASC, level ASC, post_id ASC",
				array( 1, 2, 3 ),
				\ARRAY_A
			);
		}

		$items = array();
		foreach ( $rows as $row ) {
			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $post_id <= 0 ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$parent_id       = isset( $row['parent_post_id'] ) ? (int) $row['parent_post_id'] : 0;
			$cluster_root_id = isset( $row['group_id'] ) ? (int) $row['group_id'] : 0;

			$items[] = array(
				'id'              => $post_id,
				'title'           => get_the_title( $post ),
				'level'           => self::level_code_to_label( (int) $row['level'] ),
				'parent_post_id'  => $parent_id > 0 ? $parent_id : null,
				'prev_post_id'    => isset( $row['prev_post_id'] ) ? (int) $row['prev_post_id'] : null,
				'next_post_id'    => isset( $row['next_post_id'] ) ? (int) $row['next_post_id'] : null,
				'cluster_root_id' => $cluster_root_id > 0 ? $cluster_root_id : null,
				'root_id'         => isset( $row['root_id'] ) ? (int) $row['root_id'] : null,
				'edit_url'        => get_edit_post_link( $post_id, 'raw' ),
			);
		}

		$candidates = array();
		if ( $adapter->table_exists( $candidates_table ) ) {
			if ( $group_id > 0 ) {
				$candidate_rows = $adapter->get_results(
					"SELECT id, group_id, post_id
					 FROM {$candidates_table}
					 WHERE group_id = %d
					 ORDER BY id DESC",
					array( $group_id ),
					\ARRAY_A
				);
			} else {
				$candidate_rows = $adapter->get_results(
					"SELECT id, group_id, post_id
					 FROM {$candidates_table}
					 WHERE group_id > %d
					 ORDER BY group_id ASC, id DESC",
					array( 0 ),
					\ARRAY_A
				);
			}

			foreach ( $candidate_rows as $row ) {
				$candidate_id       = isset( $row['id'] ) ? (int) $row['id'] : 0;
				$candidate_group_id = isset( $row['group_id'] ) ? (int) $row['group_id'] : 0;
				$post_id            = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
				if ( $candidate_id <= 0 || $candidate_group_id <= 0 || $post_id <= 0 ) {
					continue;
				}
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}
				$candidates[] = array(
					'id'       => $candidate_id,
					'group_id' => $candidate_group_id,
					'post_id'  => $post_id,
					'title'    => get_the_title( $post ),
					'edit_url' => get_edit_post_link( $post_id, 'raw' ),
				);
			}
		}

		$map = array();
		if ( $group_id > 0 && $adapter->table_exists( $groups_table ) ) {
			$map_json = $adapter->get_var(
				"SELECT map_json
				 FROM {$groups_table}
				 WHERE id = %d
				 LIMIT 1",
				array( $group_id )
			);
			if ( is_string( $map_json ) && '' !== $map_json ) {
				$decoded = json_decode( $map_json, true );
				if ( is_array( $decoded ) ) {
					$map = $decoded;
				}
			}
		}

		return new WP_REST_Response(
			array(
				'items'      => $items,
				'candidates' => $candidates,
				'map'        => $map,
			)
		);
	}

	/**
	 * Relate an existing post to a new parent in the cluster.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_relate( WP_REST_Request $request ) {
		$relation = strtolower( (string) $request->get_param( 'relation' ) );
		if ( 'order' === $relation ) {
			return self::handle_order_relate( $request );
		}

		$post_id   = (int) $request->get_param( 'post' );
		$parent_id = (int) $request->get_param( 'parent_post_id' );

		if ( $post_id <= 0 || $parent_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_POST,
				__( 'A valid post and parent ID are required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( $post_id === $parent_id ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_PARENT,
				__( 'The parent post must be different from the current post.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_FORBIDDEN,
				__( 'You are not allowed to edit this post.', 'airygen-seo' ),
				array( 'status' => 403 )
			);
		}

		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$current = self::fetch_entry( $adapter, $table, $post_id );
		if ( ! $current || empty( $current['level'] ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_ENTRY,
				__( 'This post is not assigned to a Topic Cluster level yet.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$parent = self::fetch_entry( $adapter, $table, $parent_id );
		if ( ! $parent || empty( $parent['level'] ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_PARENT,
				__( 'The selected parent is not part of a Topic Cluster.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$level = self::level_code_to_label( (int) $current['level'] );
		if ( 'L1' === $level ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_PARENT_NOT_ALLOWED,
				__( 'Pillar content cannot be re-parented.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( 'L2' === $level && 'L1' !== self::level_code_to_label( (int) $parent['level'] ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_PARENT,
				__( 'L2 topics must be linked to an L1 parent.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( 'L3' === $level && 'L2' !== self::level_code_to_label( (int) $parent['level'] ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_PARENT,
				__( 'L3 topics must be linked to an L2 parent.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$cluster_group_id = 0;
		$cluster_root_id  = 0;
		if ( 'L2' === $level ) {
			$cluster_group_id = isset( $parent['group_id'] ) ? (int) $parent['group_id'] : 0;
			$cluster_root_id  = isset( $parent['root_id'] ) ? (int) $parent['root_id'] : (int) $parent['post_id'];
		} else {
			$cluster_group_id = isset( $parent['group_id'] ) ? (int) $parent['group_id'] : 0;
			$cluster_root_id  = isset( $parent['root_id'] ) ? (int) $parent['root_id'] : 0;
			if ( $cluster_root_id <= 0 ) {
				$cluster_root_id = isset( $parent['parent_post_id'] ) ? (int) $parent['parent_post_id'] : 0;
			}
		}

		if ( $cluster_group_id <= 0 || $cluster_root_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_PARENT,
				__( 'Unable to resolve the parent cluster.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$now = current_time( 'mysql' );
		$adapter->query(
			"UPDATE {$table}
			 SET parent_post_id = %d,
				 group_id = %d,
				 root_id = %d,
				 updated_at = %s
			 WHERE post_id = %d",
			array(
				$parent_id,
				$cluster_group_id,
				$cluster_root_id,
				$now,
				$post_id,
			)
		);

		if ( 'L2' === $level ) {
			$adapter->query(
				"UPDATE {$table}
				 SET group_id = %d,
					 root_id = %d,
					 updated_at = %s
				 WHERE parent_post_id = %d AND level = %d",
				array(
					$cluster_group_id,
					$cluster_root_id,
					$now,
					$post_id,
					3,
				)
			);
		}

		return new WP_REST_Response(
			array(
				'post_id'         => $post_id,
				'level'           => $level,
				'parent_post_id'  => $parent_id,
				'cluster_root_id' => $cluster_root_id,
			)
		);
	}

	/**
	 * Remove hierarchy or ordering relation from mind map.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_unrelate( WP_REST_Request $request ) {
		$relation = strtolower( (string) $request->get_param( 'relation' ) );
		$adapter  = new WpDbAdapter();
		$table    = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		if ( 'order' === $relation ) {
			$left_post_id  = (int) $request->get_param( 'left_post_id' );
			$right_post_id = (int) $request->get_param( 'right_post_id' );
			if ( $left_post_id <= 0 || $right_post_id <= 0 ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_ORDER_NODES,
					__( 'Left and right nodes are required.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}
			if ( ! current_user_can( 'edit_post', $left_post_id ) || ! current_user_can( 'edit_post', $right_post_id ) ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_FORBIDDEN,
					__( 'You are not allowed to edit one of these posts.', 'airygen-seo' ),
					array( 'status' => 403 )
				);
			}

			$now = current_time( 'mysql' );
			$adapter->query(
				"UPDATE {$table} SET next_post_id = NULL, updated_at = %s WHERE post_id = %d AND next_post_id = %d",
				array( $now, $left_post_id, $right_post_id )
			);
			$adapter->query(
				"UPDATE {$table} SET prev_post_id = NULL, updated_at = %s WHERE post_id = %d AND prev_post_id = %d",
				array( $now, $right_post_id, $left_post_id )
			);

			return new WP_REST_Response(
				array(
					'relation' => 'order',
					'removed'  => true,
				)
			);
		}

		if ( 'hierarchy' === $relation ) {
			$child_post_id = (int) $request->get_param( 'child_post_id' );
			if ( $child_post_id <= 0 ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_POST,
					__( 'A valid child post ID is required.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}
			if ( ! current_user_can( 'edit_post', $child_post_id ) ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_FORBIDDEN,
					__( 'You are not allowed to edit this post.', 'airygen-seo' ),
					array( 'status' => 403 )
				);
			}

			$entry = self::fetch_entry( $adapter, $table, $child_post_id );
			if ( ! $entry || empty( $entry['level'] ) ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_ENTRY,
					__( 'This post is not assigned to Topic Cluster.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}

			$level = self::level_code_to_label( (int) $entry['level'] );
			if ( 'L1' === $level ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_PARENT,
					__( 'Pillar relation cannot be removed from mind map edge.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}

			$group_id = isset( $entry['group_id'] ) ? (int) $entry['group_id'] : 0;
			$adapter->delete( $table, array( 'post_id' => $child_post_id ), array( '%d' ) );

			$now = current_time( 'mysql' );
			$adapter->query(
				"UPDATE {$table} SET prev_post_id = NULL, updated_at = %s WHERE prev_post_id = %d",
				array( $now, $child_post_id )
			);
			$adapter->query(
				"UPDATE {$table} SET next_post_id = NULL, updated_at = %s WHERE next_post_id = %d",
				array( $now, $child_post_id )
			);

			if ( $group_id > 0 ) {
				$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
				if ( $adapter->table_exists( $candidates_table ) ) {
					$exists = (int) $adapter->get_var(
						"SELECT COUNT(*) FROM {$candidates_table} WHERE group_id = %d AND post_id = %d",
						array( $group_id, $child_post_id )
					);
					if ( $exists <= 0 ) {
						$adapter->insert(
							$candidates_table,
							array(
								'group_id'   => $group_id,
								'post_id'    => $child_post_id,
								'created_at' => $now,
							),
							array( '%d', '%d', '%s' )
						);
					}
				}
			}

			return new WP_REST_Response(
				array(
					'relation' => 'hierarchy',
					'removed'  => true,
				)
			);
		}

		return new WP_Error(
			ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_RELATION,
			__( 'Unsupported relation type.', 'airygen-seo' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Save left/right ordering relation (prev/next) between sibling nodes.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function handle_order_relate( WP_REST_Request $request ) {
		$source_post_id = (int) $request->get_param( 'source_post_id' );
		$target_post_id = (int) $request->get_param( 'target_post_id' );
		$source_handle  = (string) $request->get_param( 'source_handle' );
		$target_handle  = (string) $request->get_param( 'target_handle' );

		if ( $source_post_id <= 0 || $target_post_id <= 0 || $source_post_id === $target_post_id ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_ORDER_NODES,
				__( 'Two different nodes are required for ordering.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$left_post_id   = 0;
		$right_post_id  = 0;
		$source_is_next = 0 === strpos( $source_handle, 'next-out' );
		$target_is_prev = 0 === strpos( $target_handle, 'prev-in' );

		if ( $source_is_next && $target_is_prev ) {
			$left_post_id  = $source_post_id;
			$right_post_id = $target_post_id;
		} else {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_ORDER_HANDLES,
				__( 'Connect from next handle to prev handle for sibling order.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( ! current_user_can( 'edit_post', $left_post_id ) || ! current_user_can( 'edit_post', $right_post_id ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_FORBIDDEN,
				__( 'You are not allowed to edit one of these posts.', 'airygen-seo' ),
				array( 'status' => 403 )
			);
		}

		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$left  = self::fetch_entry( $adapter, $table, $left_post_id );
		$right = self::fetch_entry( $adapter, $table, $right_post_id );
		if ( ! $left || ! $right ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_ENTRY,
				__( 'Both nodes must be part of Topic Cluster.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( (int) $left['group_id'] !== (int) $right['group_id'] || (int) $left['level'] !== (int) $right['level'] ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_ORDER_SCOPE,
				__( 'Only sibling nodes in the same level and group can be ordered.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( 3 === (int) $left['level'] && (int) $left['parent_post_id'] !== (int) $right['parent_post_id'] ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_ORDER_SCOPE,
				__( 'L3 ordering is only allowed within the same parent L2.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$left_old_next  = isset( $left['next_post_id'] ) ? (int) $left['next_post_id'] : 0;
		$right_old_prev = isset( $right['prev_post_id'] ) ? (int) $right['prev_post_id'] : 0;
		$now            = current_time( 'mysql' );

		if ( $left_old_next > 0 && $left_old_next !== $right_post_id ) {
			$adapter->query(
				"UPDATE {$table} SET prev_post_id = NULL, updated_at = %s WHERE post_id = %d",
				array( $now, $left_old_next )
			);
		}

		if ( $right_old_prev > 0 && $right_old_prev !== $left_post_id ) {
			$adapter->query(
				"UPDATE {$table} SET next_post_id = NULL, updated_at = %s WHERE post_id = %d",
				array( $now, $right_old_prev )
			);
		}

		$adapter->query(
			"UPDATE {$table} SET next_post_id = %d, updated_at = %s WHERE post_id = %d",
			array( $right_post_id, $now, $left_post_id )
		);
		$adapter->query(
			"UPDATE {$table} SET prev_post_id = %d, updated_at = %s WHERE post_id = %d",
			array( $left_post_id, $now, $right_post_id )
		);

		return new WP_REST_Response(
			array(
				'left_post_id'  => $left_post_id,
				'right_post_id' => $right_post_id,
				'relation'      => 'order',
			)
		);
	}

	/**
	 * Save Topic Cluster settings for a post.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_save( WP_REST_Request $request ) {
		$post_id   = (int) $request->get_param( 'post' );
		$level     = strtoupper( (string) $request->get_param( 'level' ) );
		$parent    = $request->get_param( 'parent_post_id' );
		$group     = $request->get_param( 'group_id' );
		$parent_id = is_numeric( $parent ) ? (int) $parent : 0;
		$group_id  = is_numeric( $group ) ? (int) $group : 0;

		if ( $post_id <= 0 ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_POST,
				__( 'A valid post ID is required.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_FORBIDDEN,
				__( 'You are not allowed to edit this post.', 'airygen-seo' ),
				array( 'status' => 403 )
			);
		}

		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );

		if ( ! $adapter->table_exists( $table ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE,
				__( 'Topic Cluster table is not available yet.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		if ( '' === $level || 'NONE' === $level ) {
			$adapter->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
			return new WP_REST_Response( array( 'status' => 'cleared' ) );
		}

		if ( ! in_array( $level, array( 'L1', 'L2', 'L3' ), true ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_LEVEL,
				__( 'Invalid topic cluster level.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$cluster_group_id = 0;
		$cluster_root_id  = 0;
		$parent_post_id   = null;
		$current_entry    = self::fetch_entry( $adapter, $table, $post_id );

		if ( 'L1' === $level ) {
			if ( $group_id > 0 ) {
				$cluster_group_id = $group_id;
				$existing_l1      = (int) $adapter->get_var(
					"SELECT COUNT(*) FROM {$table} WHERE group_id = %d AND level = %d AND post_id != %d",
					array( $cluster_group_id, 1, $post_id )
				);
				if ( $existing_l1 > 0 ) {
					return new WP_Error(
						ErrorCodes::AIRYGEN_TOPIC_CLUSTER_L1_EXISTS,
						__( 'This group already has a pillar topic.', 'airygen-seo' ),
						array( 'status' => 400 )
					);
				}
			} else {
				$cluster_group_id = $current_entry && isset( $current_entry['group_id'] ) ? (int) $current_entry['group_id'] : $post_id;
			}
			$cluster_root_id = $post_id;
		} elseif ( 'L2' === $level ) {
			if ( $parent_id <= 0 || $parent_id === $post_id ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_PARENT,
					__( 'Select a valid L1 parent for this cluster.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}

			$parent = self::fetch_entry( $adapter, $table, $parent_id );
			if ( ! $parent || 'L1' !== self::level_code_to_label( (int) $parent['level'] ) ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_PARENT,
					__( 'The selected parent must be an L1 topic.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}

			$cluster_group_id = isset( $parent['group_id'] ) ? (int) $parent['group_id'] : 0;
			if ( $cluster_group_id <= 0 && $group_id > 0 ) {
				$cluster_group_id = $group_id;
			}
			$cluster_root_id = isset( $parent['root_id'] ) ? (int) $parent['root_id'] : (int) $parent['post_id'];
			if ( $cluster_group_id <= 0 || $cluster_root_id <= 0 ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_PARENT,
					__( 'Unable to resolve the parent cluster.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}
			$parent_post_id = (int) $parent['post_id'];
		} else {
			if ( $parent_id <= 0 || $parent_id === $post_id ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_MISSING_PARENT,
					__( 'Select a valid L2 parent for this support article.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}

			$parent = self::fetch_entry( $adapter, $table, $parent_id );
			if ( ! $parent || 'L2' !== self::level_code_to_label( (int) $parent['level'] ) ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_INVALID_PARENT,
					__( 'The selected parent must be an L2 topic.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}

			$cluster_group_id = isset( $parent['group_id'] ) ? (int) $parent['group_id'] : 0;
			if ( $cluster_group_id <= 0 && $group_id > 0 ) {
				$cluster_group_id = $group_id;
			}
			$cluster_root_id = isset( $parent['root_id'] ) ? (int) $parent['root_id'] : 0;
			if ( $cluster_root_id <= 0 ) {
				$cluster_root_id = isset( $parent['parent_post_id'] ) ? (int) $parent['parent_post_id'] : 0;
			}
			$parent_post_id = (int) $parent['post_id'];
		}

		if ( $current_entry && isset( $current_entry['level'] ) && self::level_code_to_label( (int) $current_entry['level'] ) !== $level ) {
			$child_count = $adapter->get_var(
				"SELECT COUNT(*) FROM {$table} WHERE parent_post_id = %d",
				array( $post_id )
			);
			if ( $child_count ) {
				return new WP_Error(
					ErrorCodes::AIRYGEN_TOPIC_CLUSTER_CHILDREN_LOCKED,
					__( 'This post already has child items. Remove them before changing the level.', 'airygen-seo' ),
					array( 'status' => 400 )
				);
			}
		}

		$now               = current_time( 'mysql' );
		$parent_post_db_id = $parent_post_id ? (int) $parent_post_id : 0;
		$existing_id       = $current_entry ? 1 : 0;

		$saved = false;
		if ( $existing_id ) {
			$saved = false !== $adapter->query(
				"UPDATE {$table}
				 SET level = %d,
					 parent_post_id = %d,
					 group_id = %d,
					 root_id = %d,
					 updated_at = %s
				 WHERE post_id = %d",
				array(
					self::level_label_to_code( $level ),
					$parent_post_db_id,
					$cluster_group_id,
					$cluster_root_id,
					$now,
					$post_id,
				)
			);
		} else {
			$saved = false !== $adapter->insert(
				$table,
				array(
					'post_id'        => $post_id,
					'level'          => self::level_label_to_code( $level ),
					'parent_post_id' => $parent_post_db_id,
					'group_id'       => $cluster_group_id,
					'root_id'        => $cluster_root_id,
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array(
					'%d',
					'%d',
					'%d',
					'%d',
					'%d',
					'%s',
					'%s',
				)
			);
		}

		if ( ! $saved ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_TOPIC_CLUSTER_SAVE_FAILED,
				__( 'Unable to save the Topic Cluster relation.', 'airygen-seo' ),
				array( 'status' => 500 )
			);
		}

		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		if ( $adapter->table_exists( $candidates_table ) ) {
			$adapter->delete(
				$candidates_table,
				array( 'post_id' => $post_id ),
				array( '%d' )
			);
		}

		return new WP_REST_Response(
			array(
				'post_id'         => $post_id,
				'level'           => $level,
				'parent_post_id'  => $parent_post_id,
				'cluster_root_id' => $cluster_root_id,
				'group_id'        => $cluster_group_id,
			)
		);
	}

	/**
	 * Fetch a Topic Cluster entry by post.
	 *
	 * @param WpDbAdapter $adapter Adapter.
	 * @param string      $table   Table name.
	 * @param int         $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	private static function fetch_entry( WpDbAdapter $adapter, string $table, int $post_id ): ?array {
		$rows = $adapter->get_results(
			"SELECT post_id, level, parent_post_id, prev_post_id, next_post_id, group_id, root_id
			 FROM {$table}
			 WHERE post_id = %d
			 LIMIT 1",
			array( $post_id ),
			\ARRAY_A
		);

		if ( empty( $rows ) ) {
			return null;
		}

		return $rows[0];
	}

	/**
	 * Convert stored level integer to API label.
	 *
	 * @param int $level Level code.
	 * @return string
	 */
	private static function level_code_to_label( int $level ): string {
		if ( 1 === $level ) {
			return 'L1';
		}

		if ( 2 === $level ) {
			return 'L2';
		}

		if ( 3 === $level ) {
			return 'L3';
		}

		return '';
	}

	/**
	 * Convert API level label to stored integer code.
	 *
	 * @param string $level Level label.
	 * @return int
	 */
	private static function level_label_to_code( string $level ): int {
		if ( 'L1' === $level ) {
			return 1;
		}

		if ( 'L2' === $level ) {
			return 2;
		}

		if ( 'L3' === $level ) {
			return 3;
		}

		return 0;
	}
}
