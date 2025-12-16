<?php
/**
 * Tests for Topic Cluster REST endpoints.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;

/**
 * @coversNothing
 */
final class TopicClusterRouteTest extends RestRouteTestCase {

	/**
	 * Topic Cluster groups endpoint should remain accessible for admins.
	 *
	 * @return void
	 */
	public function test_groups_endpoint_accessible_for_admins(): void {
		$this->acting_as_admin();

		$response = $this->rest_get(
			'/airygen/v1/topic-cluster/groups',
			array(
				'page'     => 1,
				'per_page' => 20,
			)
		);
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Ensure list returns L1/L2 items and current post entry.
	 *
	 * @return void
	 */
	public function test_list_returns_items_and_current(): void {
		$this->acting_as_admin();

		$l1 = self::factory()->post->create( array( 'post_title' => 'Pillar Post' ) );
		$l2 = self::factory()->post->create( array( 'post_title' => 'Cluster Post' ) );

		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $l1,
				'level' => 'L1',
			)
		);

		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l2,
				'level'          => 'L2',
				'parent_post_id' => $l1,
			)
		);

		$response = $this->rest_get( '/airygen/v1/topic-cluster/list', array( 'post' => $l2 ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data['items'] ?? null );
		$this->assertNotEmpty( $data['items'] );
		$this->assertSame( $l2, $data['current']['post_id'] ?? 0 );
		$this->assertSame( 'L2', $data['current']['level'] ?? '' );
	}

	/**
	 * Ensure summary endpoint returns counts for L1.
	 *
	 * @return void
	 */
	public function test_summary_returns_counts(): void {
		$this->acting_as_admin();

		$l1 = self::factory()->post->create( array( 'post_title' => 'Pillar' ) );
		$l2 = self::factory()->post->create( array( 'post_title' => 'Cluster' ) );
		$l3 = self::factory()->post->create( array( 'post_title' => 'Support' ) );

		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $l1,
				'level' => 'L1',
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l2,
				'level'          => 'L2',
				'parent_post_id' => $l1,
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l3,
				'level'          => 'L3',
				'parent_post_id' => $l2,
			)
		);

		$response = $this->rest_get( '/airygen/v1/topic-cluster/summary', array( 'post' => $l1 ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'L1', $data['current']['level'] ?? '' );
		$this->assertSame( 1, $data['l1']['l2'] ?? 0 );
		$this->assertSame( 1, $data['l1']['l3'] ?? 0 );
	}

	/**
	 * Ensure mindmap endpoint returns all levels.
	 *
	 * @return void
	 */
	public function test_mindmap_returns_all_levels(): void {
		$this->acting_as_admin();

		$l1 = self::factory()->post->create( array( 'post_title' => 'Pillar' ) );
		$l2 = self::factory()->post->create( array( 'post_title' => 'Cluster' ) );
		$l3 = self::factory()->post->create( array( 'post_title' => 'Support' ) );

		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $l1,
				'level' => 'L1',
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l2,
				'level'          => 'L2',
				'parent_post_id' => $l1,
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l3,
				'level'          => 'L3',
				'parent_post_id' => $l2,
			)
		);

		$response = $this->rest_get( '/airygen/v1/topic-cluster/mindmap' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data['items'] ?? null );
		$levels = array_map(
			static function ( $item ) {
				return $item['level'] ?? '';
			},
			$data['items']
		);
		$this->assertContains( 'L1', $levels );
		$this->assertContains( 'L2', $levels );
		$this->assertContains( 'L3', $levels );
	}

	/**
	 * Ensure mindmap can be filtered by group_id and returns group candidates.
	 *
	 * @return void
	 */
	public function test_mindmap_supports_group_filter_and_candidates(): void {
		$this->acting_as_admin();

		$l1a = self::factory()->post->create( array( 'post_title' => 'Pillar A' ) );
		$l2a = self::factory()->post->create( array( 'post_title' => 'Cluster A' ) );
		$l1b = self::factory()->post->create( array( 'post_title' => 'Pillar B' ) );
		$l2b = self::factory()->post->create( array( 'post_title' => 'Cluster B' ) );

		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $l1a,
				'level' => 'L1',
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l2a,
				'level'          => 'L2',
				'parent_post_id' => $l1a,
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $l1b,
				'level' => 'L1',
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l2b,
				'level'          => 'L2',
				'parent_post_id' => $l1b,
			)
		);

		$candidate_post  = self::factory()->post->create( array( 'post_title' => 'Candidate A' ) );
		$adapter         = new WpDbAdapter();
		$candidate_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$adapter->insert(
			$candidate_table,
			array(
				'group_id'   => $l1a,
				'post_id'    => $candidate_post,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		$response = $this->rest_get(
			'/airygen/v1/topic-cluster/mindmap',
			array(
				'group_id' => $l1a,
			)
		);
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data['items'] ?? null );
		$this->assertIsArray( $data['candidates'] ?? null );
		$this->assertNotEmpty( $data['items'] );
		$this->assertNotEmpty( $data['candidates'] );

		foreach ( $data['items'] as $item ) {
			$this->assertSame( $l1a, (int) ( $item['cluster_root_id'] ?? 0 ) );
		}

		$this->assertSame( $candidate_post, (int) ( $data['candidates'][0]['post_id'] ?? 0 ) );
		$this->assertSame( $l1a, (int) ( $data['candidates'][0]['group_id'] ?? 0 ) );
	}

	/**
	 * Ensure group map layout can be saved and returned by mindmap endpoint.
	 *
	 * @return void
	 */
	public function test_group_map_layout_roundtrip(): void {
		$this->acting_as_admin();

		$group = $this->rest_post(
			'/airygen/v1/topic-cluster/groups',
			array(
				'name'        => 'Group A',
				'description' => 'Map test',
			)
		);
		$this->assertSame( 201, $group->get_status() );
		$group_id = (int) ( $group->get_data()['id'] ?? 0 );
		$this->assertGreaterThan( 0, $group_id );

		$payload = array(
			'nodes' => array(
				'10' => array(
					'x' => 123,
					'y' => 456,
				),
			),
		);

		$save = $this->rest_post(
			sprintf( '/airygen/v1/topic-cluster/groups/%d/map', $group_id ),
			array(
				'map' => $payload,
			)
		);
		$this->assertSame( 200, $save->get_status() );
		$this->assertEquals( $payload, $save->get_data()['map'] ?? array() );

		$response = $this->rest_get(
			'/airygen/v1/topic-cluster/mindmap',
			array(
				'group_id' => $group_id,
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertEquals( $payload, $response->get_data()['map'] ?? array() );
	}

	/**
	 * Ensure relate endpoint re-parents L2.
	 *
	 * @return void
	 */
	public function test_relate_updates_parent(): void {
		$this->acting_as_admin();

		$l1a = self::factory()->post->create( array( 'post_title' => 'Pillar A' ) );
		$l1b = self::factory()->post->create( array( 'post_title' => 'Pillar B' ) );
		$l2  = self::factory()->post->create( array( 'post_title' => 'Cluster' ) );

		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $l1a,
				'level' => 'L1',
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $l1b,
				'level' => 'L1',
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l2,
				'level'          => 'L2',
				'parent_post_id' => $l1a,
			)
		);

		$relate = $this->rest_post(
			'/airygen/v1/topic-cluster/relate',
			array(
				'post'           => $l2,
				'parent_post_id' => $l1b,
			)
		);
		$this->assertSame( 200, $relate->get_status() );

		$response = $this->rest_get( '/airygen/v1/topic-cluster/mindmap' );
		$data     = $response->get_data();
		$items    = array_filter(
			$data['items'],
			static function ( $item ) use ( $l2 ) {
				return (int) $item['id'] === $l2;
			}
		);
		$items    = array_values( $items );
		$this->assertSame( $l1b, $items[0]['parent_post_id'] ?? 0 );
	}

	/**
	 * Ensure invalid parent is rejected for L2.
	 *
	 * @return void
	 */
	public function test_save_requires_valid_parent_for_l2(): void {
		$this->acting_as_admin();

		$post_id  = self::factory()->post->create();
		$response = $this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $post_id,
				'level' => 'L2',
			)
		);

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Ensure groups endpoint returns paginated group rows.
	 *
	 * @return void
	 */
	public function test_groups_returns_paginated_rows(): void {
		$this->acting_as_admin();

		$l1a = self::factory()->post->create( array( 'post_title' => 'Pillar A' ) );
		$l1b = self::factory()->post->create( array( 'post_title' => 'Pillar B' ) );
		$l2  = self::factory()->post->create( array( 'post_title' => 'Cluster A1' ) );
		$l3  = self::factory()->post->create( array( 'post_title' => 'Support A1' ) );

		$this->rest_post(
			'/airygen/v1/topic-cluster/groups',
			array(
				'name'        => 'Group A',
				'description' => 'Group A description',
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/groups',
			array(
				'name'        => 'Group B',
				'description' => 'Group B description',
			)
		);

		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $l1a,
				'level' => 'L1',
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $l1b,
				'level' => 'L1',
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l2,
				'level'          => 'L2',
				'parent_post_id' => $l1a,
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l3,
				'level'          => 'L3',
				'parent_post_id' => $l2,
			)
		);

		$response = $this->rest_get(
			'/airygen/v1/topic-cluster/groups',
			array(
				'page'     => 1,
				'per_page' => 20,
			)
		);
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data['groups'] ?? null );
		$this->assertNotEmpty( $data['groups'] );
		$this->assertSame( 1, $data['pagination']['page'] ?? 0 );
		$this->assertSame( 20, $data['pagination']['perPage'] ?? 0 );
		$this->assertArrayHasKey( 'totalPages', $data['pagination'] ?? array() );
	}

	/**
	 * Ensure create group endpoint saves group metadata.
	 *
	 * @return void
	 */
	public function test_create_group_creates_row(): void {
		$this->acting_as_admin();

		$response = $this->rest_post(
			'/airygen/v1/topic-cluster/groups',
			array(
				'name'        => 'Cluster Plan A',
				'description' => 'Manual planning group.',
			)
		);

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'Cluster Plan A', $data['name'] ?? '' );
		$this->assertSame( 'Manual planning group.', $data['description'] ?? '' );
		$this->assertNotEmpty( $data['created_at'] ?? '' );
	}

	/**
	 * Ensure update group endpoint updates name and description.
	 *
	 * @return void
	 */
	public function test_update_group_updates_row(): void {
		$this->acting_as_admin();

		$this->rest_post(
			'/airygen/v1/topic-cluster/groups',
			array(
				'name'        => 'Cluster Plan B',
				'description' => 'Before update.',
			)
		);

		$list = $this->rest_get(
			'/airygen/v1/topic-cluster/groups',
			array(
				'page'     => 1,
				'per_page' => 20,
			)
		);
		$this->assertSame( 200, $list->get_status() );

		$groups   = $list->get_data()['groups'] ?? array();
		$group_id = (int) ( $groups[0]['group_id'] ?? 0 );
		$this->assertGreaterThan( 0, $group_id );

		$response = $this->rest_post(
			'/airygen/v1/topic-cluster/groups/' . $group_id,
			array(
				'name'        => 'Cluster Plan B (Updated)',
				'description' => 'After update.',
			)
		);
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'Cluster Plan B (Updated)', $data['name'] ?? '' );
		$this->assertSame( 'After update.', $data['description'] ?? '' );
	}

	/**
	 * Ensure remove group deletes only empty groups.
	 *
	 * @return void
	 */
	public function test_remove_group_requires_empty_group(): void {
		$this->acting_as_admin();

		$create = $this->rest_post(
			'/airygen/v1/topic-cluster/groups',
			array(
				'name'        => 'Group For Remove',
				'description' => '',
			)
		);
		$this->assertSame( 201, $create->get_status() );
		$group_id = (int) ( $create->get_data()['id'] ?? 0 );
		$this->assertGreaterThan( 0, $group_id );

		$post_id = self::factory()->post->create( array( 'post_title' => 'Linked pillar' ) );
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $post_id,
				'level' => 'L1',
			)
		);

		$adapter          = new WpDbAdapter();
		$relations_table  = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$adapter->query(
			"UPDATE {$relations_table} SET group_id = %d WHERE post_id = %d",
			array( $group_id, $post_id )
		);

		$blocked = $this->rest_delete( '/airygen/v1/topic-cluster/groups/' . $group_id );
		$this->assertSame( 400, $blocked->get_status() );

		$adapter->query(
			"DELETE FROM {$relations_table} WHERE group_id = %d",
			array( $group_id )
		);
		$adapter->insert(
			$candidates_table,
			array(
				'group_id'   => $group_id,
				'post_id'    => $post_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		$deleted = $this->rest_delete( '/airygen/v1/topic-cluster/groups/' . $group_id );
		$this->assertSame( 200, $deleted->get_status() );
		$this->assertTrue( (bool) ( $deleted->get_data()['deleted'] ?? false ) );
		$candidate_count = (int) $adapter->get_var(
			"SELECT COUNT(*) FROM {$candidates_table} WHERE group_id = %d",
			array( $group_id )
		);
		$this->assertSame( 0, $candidate_count );
	}

	/**
	 * Ensure candidate endpoints can search, add, list, and remove.
	 *
	 * @return void
	 */
	public function test_candidate_endpoints_manage_group_candidates(): void {
		$this->acting_as_admin();

		$create = $this->rest_post(
			'/airygen/v1/topic-cluster/groups',
			array(
				'name'        => 'Group For Candidates',
				'description' => '',
			)
		);
		$this->assertSame( 201, $create->get_status() );
		$group_id = (int) ( $create->get_data()['id'] ?? 0 );
		$this->assertGreaterThan( 0, $group_id );

		$searchable_post = self::factory()->post->create( array( 'post_title' => 'Candidate Post Alpha' ) );
		$related_post    = self::factory()->post->create( array( 'post_title' => 'Candidate Post Related' ) );
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $related_post,
				'level' => 'L1',
			)
		);

		$search = $this->rest_get(
			'/airygen/v1/topic-cluster/groups/' . $group_id . '/candidates/search',
			array(
				'q' => 'Candidate Post',
			)
		);
		$this->assertSame( 200, $search->get_status() );
		$items    = $search->get_data()['items'] ?? array();
		$post_ids = array_map(
			static function ( $item ) {
				return (int) ( $item['post_id'] ?? 0 );
			},
			$items
		);
		$this->assertContains( $searchable_post, $post_ids );
		$this->assertNotContains( $related_post, $post_ids );

		$add = $this->rest_post(
			'/airygen/v1/topic-cluster/groups/' . $group_id . '/candidates',
			array(
				'post_id' => $searchable_post,
			)
		);
		$this->assertSame( 201, $add->get_status() );

		$list = $this->rest_get( '/airygen/v1/topic-cluster/groups/' . $group_id . '/candidates' );
		$this->assertSame( 200, $list->get_status() );
		$candidates = $list->get_data()['candidates'] ?? array();
		$this->assertCount( 1, $candidates );
		$this->assertSame( $searchable_post, (int) ( $candidates[0]['post_id'] ?? 0 ) );
		$candidate_id = (int) ( $candidates[0]['id'] ?? 0 );
		$this->assertGreaterThan( 0, $candidate_id );

		$remove = $this->rest_delete(
			'/airygen/v1/topic-cluster/groups/' . $group_id . '/candidates/' . $candidate_id
		);
		$this->assertSame( 200, $remove->get_status() );
	}

	/**
	 * Ensure candidate configured as L1 stays in selected group.
	 *
	 * @return void
	 */
	public function test_save_with_group_id_assigns_l1_to_existing_group(): void {
		$this->acting_as_admin();

		$create = $this->rest_post(
			'/airygen/v1/topic-cluster/groups',
			array(
				'name'        => 'Group With Candidate',
				'description' => '',
			)
		);
		$this->assertSame( 201, $create->get_status() );
		$group_id = (int) ( $create->get_data()['id'] ?? 0 );
		$this->assertGreaterThan( 0, $group_id );

		$post_id          = self::factory()->post->create( array( 'post_title' => 'Candidate Pillar' ) );
		$adapter          = new WpDbAdapter();
		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$relations_table  = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );

		$adapter->insert(
			$candidates_table,
			array(
				'group_id'   => $group_id,
				'post_id'    => $post_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		$save = $this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'     => $post_id,
				'level'    => 'L1',
				'group_id' => $group_id,
			)
		);
		$this->assertSame( 200, $save->get_status() );

		$saved_group_id = (int) $adapter->get_var(
			"SELECT group_id FROM {$relations_table} WHERE post_id = %d",
			array( $post_id )
		);
		$this->assertSame( $group_id, $saved_group_id );

		$candidate_count = (int) $adapter->get_var(
			"SELECT COUNT(*) FROM {$candidates_table} WHERE post_id = %d",
			array( $post_id )
		);
		$this->assertSame( 0, $candidate_count );
	}

	/**
	 * Ensure unrelate endpoint clears prev/next ordering.
	 *
	 * @return void
	 */
	public function test_unrelate_order_clears_prev_next(): void {
		$this->acting_as_admin();

		$l1  = self::factory()->post->create( array( 'post_title' => 'Pillar' ) );
		$l2a = self::factory()->post->create( array( 'post_title' => 'L2 A' ) );
		$l2b = self::factory()->post->create( array( 'post_title' => 'L2 B' ) );

		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'  => $l1,
				'level' => 'L1',
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l2a,
				'level'          => 'L2',
				'parent_post_id' => $l1,
			)
		);
		$this->rest_post(
			'/airygen/v1/topic-cluster/save',
			array(
				'post'           => $l2b,
				'level'          => 'L2',
				'parent_post_id' => $l1,
			)
		);

		$link = $this->rest_post(
			'/airygen/v1/topic-cluster/relate',
			array(
				'relation'       => 'order',
				'source_post_id' => $l2a,
				'target_post_id' => $l2b,
				'source_handle'  => 'next-out',
				'target_handle'  => 'prev-in',
			)
		);
		$this->assertSame( 200, $link->get_status() );

		$unlink = $this->rest_post(
			'/airygen/v1/topic-cluster/unrelate',
			array(
				'relation'      => 'order',
				'left_post_id'  => $l2a,
				'right_post_id' => $l2b,
			)
		);
		$this->assertSame( 200, $unlink->get_status() );
	}

	/**
	 * Ensure mind map sync persists draft items, candidates, and layout for a group.
	 *
	 * @return void
	 */
	public function test_mindmap_sync_persists_group_draft_state(): void {
		$this->acting_as_admin();

		$group = $this->rest_post(
			'/airygen/v1/topic-cluster/groups',
			array(
				'name'        => 'Draft Group',
				'description' => 'Draft sync test',
			)
		);
		$this->assertSame( 201, $group->get_status() );
		$group_id = (int) ( $group->get_data()['id'] ?? 0 );
		$this->assertGreaterThan( 0, $group_id );

		$l1        = self::factory()->post->create( array( 'post_title' => 'Pillar Sync' ) );
		$l2        = self::factory()->post->create( array( 'post_title' => 'Cluster Sync' ) );
		$candidate = self::factory()->post->create( array( 'post_title' => 'Candidate Sync' ) );

		$sync = $this->rest_post(
			sprintf( '/airygen/v1/topic-cluster/groups/%d/mindmap-sync', $group_id ),
			array(
				'items'      => array(
					array(
						'id'             => $l1,
						'level'          => 'L1',
						'parent_post_id' => 0,
						'prev_post_id'   => 0,
						'next_post_id'   => 0,
					),
					array(
						'id'             => $l2,
						'level'          => 'L2',
						'parent_post_id' => $l1,
						'prev_post_id'   => 0,
						'next_post_id'   => 0,
					),
				),
				'candidates' => array(
					array(
						'post_id'  => $candidate,
						'group_id' => $group_id,
					),
				),
				'map'        => array(
					'nodes' => array(
						(string) $l1 => array(
							'x' => 120,
							'y' => 240,
						),
						(string) $l2 => array(
							'x' => 520,
							'y' => 360,
						),
					),
				),
			)
		);
		$this->assertSame( 200, $sync->get_status() );

		$response = $this->rest_get(
			'/airygen/v1/topic-cluster/mindmap',
			array(
				'group_id' => $group_id,
			)
		);
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data['items'] ?? array() );
		$this->assertCount( 1, $data['candidates'] ?? array() );
		$this->assertSame( $candidate, (int) ( $data['candidates'][0]['post_id'] ?? 0 ) );
		$this->assertSame( 120, (int) ( $data['map']['nodes'][ (string) $l1 ]['x'] ?? 0 ) );
		$this->assertSame( 360, (int) ( $data['map']['nodes'][ (string) $l2 ]['y'] ?? 0 ) );
	}
}
