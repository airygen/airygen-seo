<?php
/**
 * Tests for Topic Cluster public output.
 *
 * @package AirygenTest\Modules\TopicCluster\Public
 */

declare(strict_types=1);

namespace AirygenTest\Modules\TopicCluster\Public;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\TopicCluster\Admin\Settings as TopicClusterSettings;
use Airygen\Modules\TopicCluster\Public\Hooks;
use Airygen\Support\Database\WpDbAdapter;
use AirygenTest\BaseTestCase;
use AirygenTest\Support\DatabaseHelpers;

/**
 * @covers \Airygen\Modules\TopicCluster\Public\Hooks
 */
final class HooksTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		DatabaseHelpers::ensure_custom_tables();
		DatabaseHelpers::truncate_custom_tables();
		ModuleSettings::ensure_exists();
		$modules                 = ModuleSettings::get();
		$modules['topicCluster'] = true;
		ModuleSettings::update( $modules );
		TopicClusterSettings::update(
			array(
				'manual_output_enabled'  => true,
				'auto_injection_enabled' => true,
				'post_types'             => array( 'post' ),
				'insert_position'        => 'after-content',
			)
		);
	}

	public function test_render_for_template_outputs_current_branch_markup(): void {
		$l1_id = self::factory()->post->create( array( 'post_title' => 'Japan Travel Guide' ) );
		$l2_id = self::factory()->post->create( array( 'post_title' => 'Osaka Budget' ) );
		$l3_id = self::factory()->post->create( array( 'post_title' => 'Namba Hotel Tips' ) );

		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );

		$adapter->insert(
			$table,
			array(
				'post_id'        => $l1_id,
				'level'          => 1,
				'parent_post_id' => null,
				'prev_post_id'   => null,
				'next_post_id'   => null,
				'group_id'       => 10,
				'root_id'        => $l1_id,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
		$adapter->insert(
			$table,
			array(
				'post_id'        => $l2_id,
				'level'          => 2,
				'parent_post_id' => $l1_id,
				'prev_post_id'   => null,
				'next_post_id'   => null,
				'group_id'       => 10,
				'root_id'        => $l1_id,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
		$adapter->insert(
			$table,
			array(
				'post_id'        => $l3_id,
				'level'          => 3,
				'parent_post_id' => $l2_id,
				'prev_post_id'   => null,
				'next_post_id'   => null,
				'group_id'       => 10,
				'root_id'        => $l1_id,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		$output = Hooks::render_for_template( $l2_id );

		$this->assertStringContainsString( 'airygen-topic-cluster', $output );
		$this->assertStringContainsString( 'Namba Hotel Tips', $output );
		$this->assertStringContainsString( 'This article is part of the <a class="airygen-topic-cluster__link"', $output );
		$this->assertStringContainsString( '>Japan Travel Guide</a> series. The links below expand on the topic.', $output );
		$this->assertStringNotContainsString( 'aria-current="page">Osaka Budget', $output );
		$this->assertStringNotContainsString( '>L1<', $output );
		$this->assertStringNotContainsString( '>L2<', $output );
		$this->assertStringNotContainsString( '>L3<', $output );
	}

	public function test_render_for_template_outputs_l1_as_series_links_only(): void {
		$l1_id = self::factory()->post->create( array( 'post_title' => 'Japan Travel Guide' ) );
		$l2_a  = self::factory()->post->create( array( 'post_title' => 'Tokyo Itinerary' ) );
		$l2_b  = self::factory()->post->create( array( 'post_title' => 'Osaka Itinerary' ) );
		$l3_id = self::factory()->post->create( array( 'post_title' => 'Shibuya Hotel Picks' ) );

		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );

		$this->insert_relation( $adapter, $table, $l1_id, 1, null, 11, $l1_id );
		$this->insert_relation( $adapter, $table, $l2_a, 2, $l1_id, 11, $l1_id );
		$this->insert_relation( $adapter, $table, $l2_b, 2, $l1_id, 11, $l1_id );
		$this->insert_relation( $adapter, $table, $l3_id, 3, $l2_a, 11, $l1_id );

		$output = Hooks::render_for_template( $l1_id );

		$this->assertStringContainsString( 'Explore the main articles in this series.', $output );
		$this->assertStringContainsString( 'Tokyo Itinerary', $output );
		$this->assertStringContainsString( 'Osaka Itinerary', $output );
		$this->assertStringNotContainsString( 'Shibuya Hotel Picks', $output );
	}

	public function test_render_for_template_outputs_l3_as_parent_reference_only(): void {
		$l1_id = self::factory()->post->create( array( 'post_title' => 'Japan Travel Guide' ) );
		$l2_id = self::factory()->post->create( array( 'post_title' => 'Osaka Budget' ) );
		$l3_id = self::factory()->post->create( array( 'post_title' => 'Namba Hotel Tips' ) );
		$l3_b  = self::factory()->post->create( array( 'post_title' => 'Umeda Transport Notes' ) );

		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );

		$this->insert_relation( $adapter, $table, $l1_id, 1, null, 12, $l1_id );
		$this->insert_relation( $adapter, $table, $l2_id, 2, $l1_id, 12, $l1_id );
		$this->insert_relation( $adapter, $table, $l3_id, 3, $l2_id, 12, $l1_id );
		$this->insert_relation( $adapter, $table, $l3_b, 3, $l2_id, 12, $l1_id );

		$output = Hooks::render_for_template( $l3_id );

		$this->assertStringContainsString( 'This article expands on <a class="airygen-topic-cluster__link"', $output );
		$this->assertStringContainsString( 'Osaka Budget', $output );
		$this->assertStringContainsString( 'Continue with the main article', $output );
		$this->assertStringNotContainsString( 'Japan Travel Guide', $output );
		$this->assertStringNotContainsString( 'Umeda Transport Notes', $output );
	}

	public function test_render_for_template_uses_custom_relation_description_settings(): void {
		$l1_id = self::factory()->post->create( array( 'post_title' => 'Japan Travel Guide' ) );
		$l2_id = self::factory()->post->create( array( 'post_title' => 'Osaka Budget' ) );
		$l3_id = self::factory()->post->create( array( 'post_title' => 'Namba Hotel Tips' ) );

		$adapter = new WpDbAdapter();
		$table   = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );

		$this->insert_relation( $adapter, $table, $l1_id, 1, null, 13, $l1_id );
		$this->insert_relation( $adapter, $table, $l2_id, 2, $l1_id, 13, $l1_id );
		$this->insert_relation( $adapter, $table, $l3_id, 3, $l2_id, 13, $l1_id );

		TopicClusterSettings::update(
			array(
				'manual_output_enabled'  => true,
				'auto_injection_enabled' => true,
				'post_types'             => array( 'post' ),
				'insert_position'        => 'after-content',
				'relation_text_l1'       => 'Explore every article in this collection.',
				'relation_text_l2'       => 'Continue through %s for the full journey.',
				'relation_text_l3'       => 'Read %s before this supporting article.',
			)
		);

		$l1_output = Hooks::render_for_template( $l1_id );
		$l2_output = Hooks::render_for_template( $l2_id );
		$l3_output = Hooks::render_for_template( $l3_id );

		$this->assertStringContainsString( 'Explore every article in this collection.', $l1_output );
		$this->assertStringContainsString( 'Continue through <a class="airygen-topic-cluster__link"', $l2_output );
		$this->assertStringContainsString( '>Japan Travel Guide</a> for the full journey.', $l2_output );
		$this->assertStringContainsString( 'Read <a class="airygen-topic-cluster__link"', $l3_output );
		$this->assertStringContainsString( '>Osaka Budget</a> before this supporting article.', $l3_output );
	}

	/**
	 * Insert topic cluster relation row.
	 *
	 * @param WpDbAdapter $adapter Adapter.
	 * @param string      $table Table name.
	 * @param int         $post_id Post ID.
	 * @param int         $level Level.
	 * @param int|null    $parent_post_id Parent post ID.
	 * @param int         $group_id Group ID.
	 * @param int         $root_id Root ID.
	 *
	 * @return void
	 */
	private function insert_relation(
		WpDbAdapter $adapter,
		string $table,
		int $post_id,
		int $level,
		?int $parent_post_id,
		int $group_id,
		int $root_id
	): void {
		$adapter->insert(
			$table,
			array(
				'post_id'        => $post_id,
				'level'          => $level,
				'parent_post_id' => $parent_post_id,
				'prev_post_id'   => null,
				'next_post_id'   => null,
				'group_id'       => $group_id,
				'root_id'        => $root_id,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
	}
}
