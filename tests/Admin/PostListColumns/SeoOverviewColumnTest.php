<?php
/**
 * Tests for the unified SEO overview list-table column.
 *
 * @package AirygenTest\Admin\PostListColumns
 */

declare(strict_types=1);

namespace AirygenTest\Admin\PostListColumns;

use Airygen\Admin\PostListColumns\SeoOverviewColumn;
use Airygen\Constants;
use Airygen\Modules\LinkCounter\Domain\Storage;
use Airygen\Modules\ScoreCalculator\Admin\Settings as ScoreCalculatorSettings;
use Airygen\Modules\TopicCluster\Admin\Settings as TopicClusterSettings;
use Airygen\Support\Database\WpDbAdapter;
use AirygenTest\BaseTestCase;
use AirygenTest\Support\DatabaseHelpers;

/**
 * Covers admin list-table rendering for the unified SEO column.
 */
final class SeoOverviewColumnTest extends BaseTestCase {

	/**
	 * Prepare custom tables for each test run.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		DatabaseHelpers::ensure_custom_tables();
		DatabaseHelpers::truncate_custom_tables();
	}

	/**
	 * Reset module scopes after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		TopicClusterSettings::update(
			array(
				'post_types' => array( 'post' ),
			)
		);
		ScoreCalculatorSettings::update(
			array(
				'postTypes' => array( 'post' ),
			)
		);

		parent::tearDown();
	}

	/**
	 * Ensure rendered output matches the accepted UI contract.
	 *
	 * @return void
	 */
	public function test_render_column_outputs_badge_links_and_cluster_level_only(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'SEO overview sample',
			)
		);

		update_post_meta(
			$post_id,
			Constants::META_SCORE_CACHE,
			array(
				'score'      => 45,
				'max'        => 105,
				'updated_at' => gmdate( 'c' ),
			)
		);

		$adapter = new WpDbAdapter();
		$adapter->replace(
			$adapter->table( Constants::TABLE_LINK_COUNTER_META ),
			array(
				'post_id'             => $post_id,
				'internal_link_count' => 3,
				'external_link_count' => 2,
				'incoming_link_count' => 1,
				'status'              => 'processed',
				'last_processed_at'   => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$adapter->insert(
			$adapter->table( Constants::TABLE_LINK_CHECKER_LOG ),
			array(
				'link_id'       => 9001,
				'post_id'       => $post_id,
				'url'           => 'https://example.com/broken',
				'status_code'   => 404,
				'status_label'  => 'client_error',
				'error_message' => '',
				'data_source'   => 'http_request',
				'checked_at'    => current_time( 'mysql' ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		$adapter->insert(
			$adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS ),
			array(
				'id'          => 77,
				'name'        => 'Japan travel cluster',
				'description' => '',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		$adapter->insert(
			$adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS ),
			array(
				'post_id'        => $post_id,
				'group_id'       => 77,
				'level'          => 2,
				'parent_post_id' => null,
				'root_id'        => $post_id,
				'prev_post_id'   => null,
				'next_post_id'   => null,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_query'] = (object) array(
			'posts' => array(
				get_post( $post_id ),
			),
		);

		$column = new SeoOverviewColumn( new Storage() );

		ob_start();
		$column->render_column( 'airygen_seo_overview', $post_id );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Score', $output );
		$this->assertStringContainsString( 'Links', $output );
		$this->assertStringContainsString( 'Cluster', $output );
		$this->assertStringContainsString( '>43<', $output );
		$this->assertStringNotContainsString( '%', $output );
		$this->assertStringContainsString( '#feefed', $output );
		$this->assertStringContainsString( '#f35d4a', $output );
		$this->assertStringContainsString( '>3<', $output );
		$this->assertStringContainsString( '>2<', $output );
		$this->assertStringContainsString( '>1<', $output );
		$this->assertStringContainsString( 'Broken links', $output );
		$this->assertStringContainsString( '>L2<', $output );
		$this->assertStringNotContainsString( 'Japan travel cluster', $output );
	}

	/**
	 * Ensure the new column replaces legacy topic/link columns.
	 *
	 * @return void
	 */
	public function test_add_column_removes_legacy_keys_and_inserts_seo_after_title(): void {
		$column  = new SeoOverviewColumn( new Storage() );
		$columns = $column->add_column(
			array(
				'cb'                    => '<input />',
				'title'                 => 'Title',
				'airygen_link_counts'   => 'Links',
				'airygen_topic_cluster' => 'Topic Cluster',
				'seo_column'            => 'SEO',
				'date'                  => 'Date',
			)
		);

		$keys = array_keys( $columns );

		$this->assertSame( array( 'cb', 'title', 'airygen_seo_overview', 'date' ), $keys );
		$this->assertSame( 'SEO', $columns['airygen_seo_overview'] );
	}
}
