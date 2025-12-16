<?php
/**
 * Persistence layer for Airygen SEO link counter.
 *
 * @package Airygen\Modules\LinkCounter\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkCounter\Domain;

use Airygen\Constants;
use Airygen\Modules\LinkCounter\Runtime\PostTypes;
use Airygen\Support\Database\WpDbAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access helper encapsulating custom tables and caching behaviour.
 */
final class Storage {

	private const CACHE_COUNTS_GROUP = 'airygen_link_counts';
	private const CACHE_LINKS_GROUP  = 'airygen_links';

	/**
	 * Database adapter.
	 *
	 * @var WpDbAdapter
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param WpDbAdapter|null $db Optional adapter (useful for testing).
	 */
	public function __construct( ?WpDbAdapter $db = null ) {
		$this->db = $db ?? new WpDbAdapter();
	}

	/**
	 * Delete all stored links for the provided post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function cleanup( int $post_id ): void {
		$this->db->delete(
			$this->links_table(),
			array( 'post_id' => $post_id ),
			array( '%d' )
		);

		wp_cache_delete( $post_id, self::CACHE_LINKS_GROUP );
		wp_cache_delete( $post_id, self::CACHE_COUNTS_GROUP );
	}

	/**
	 * Persist a batch of links for a post.
	 *
	 * @param int              $post_id Post ID.
	 * @param array<int, Link> $links   Link collection.
	 * @return void
	 */
	public function save_links( int $post_id, array $links ): void {
		foreach ( $links as $link ) {
			if ( ! $link instanceof Link ) {
				continue;
			}

			$this->db->insert(
				$this->links_table(),
				array(
					'url'            => $link->get_url(),
					'post_id'        => $post_id,
					'target_post_id' => $link->get_target_post_id(),
					'type'           => $link->get_type(),
				),
				array( '%s', '%d', '%d', '%s' )
			);
		}

		wp_cache_delete( $post_id, self::CACHE_LINKS_GROUP );
	}

	/**
	 * Retrieve stored links for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, Link>
	 */
	public function get_links( int $post_id ): array {
		$cached = wp_cache_get( $post_id, self::CACHE_LINKS_GROUP );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$results = $this->db->get_results(
			sprintf( 'SELECT url, target_post_id, type FROM %s WHERE post_id = %%d', $this->links_table() ),
			array( $post_id )
		);

		if ( empty( $results ) ) {
			return array();
		}

		$links = array();
		foreach ( $results as $row ) {
			$links[] = new Link(
				(string) $row->url,
				(int) $row->target_post_id,
				(string) $row->type
			);
		}

		wp_cache_set( $post_id, $links, self::CACHE_LINKS_GROUP );

		return $links;
	}

	/**
	 * Save outgoing link counts.
	 *
	 * @param int               $post_id Post ID.
	 * @param array<string,int> $counts  Counts array.
	 * @return void
	 */
	public function save_counts( int $post_id, array $counts ): void {
		$this->upsert_meta(
			$post_id,
			array(
				'internal_link_count' => (int) ( $counts['internal_link_count'] ?? 0 ),
				'external_link_count' => (int) ( $counts['external_link_count'] ?? 0 ),
				'updated_at'          => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Mark a post as pending.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function mark_pending( int $post_id ): void {
		$this->upsert_meta(
			$post_id,
			array(
				'status'     => 'pending',
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Mark a post as currently being processed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function mark_processing( int $post_id ): void {
		$this->upsert_meta(
			$post_id,
			array(
				'status'     => 'processing',
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Mark a post as processed and record the timestamp.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function mark_processed( int $post_id ): void {
		$this->upsert_meta(
			$post_id,
			array(
				'status'            => 'processed',
				'last_processed_at' => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Mark a post as failed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function mark_failed( int $post_id ): void {
		$this->upsert_meta(
			$post_id,
			array(
				'status'     => 'failed',
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Retrieve pending post IDs.
	 *
	 * @param int $limit Maximum number of posts.
	 * @return array<int,int>
	 */
	public function get_pending_post_ids( int $limit = 10 ): array {
		$limit = max( 1, (int) $limit );
		$sql   = sprintf(
			'SELECT post_id FROM %s WHERE status = %%s ORDER BY updated_at ASC, post_id ASC LIMIT %d',
			$this->meta_table(),
			$limit
		);

		$rows = $this->db->get_results( $sql, array( 'pending' ) );
		if ( empty( $rows ) ) {
			$this->seed_pending_posts( $limit );
			$rows = $this->db->get_results( $sql, array( 'pending' ) );
		}

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( 'intval', wp_list_pluck( $rows, 'post_id' ) );
	}

	/**
	 * Determine if any posts are pending processing.
	 *
	 * @return bool
	 */
	public function has_pending_posts(): bool {
		$sql = sprintf( 'SELECT post_id FROM %s WHERE status = %%s LIMIT 1', $this->meta_table() );

		$has_pending = $this->db->get_var( $sql, array( 'pending' ) );
		if ( $has_pending ) {
			return true;
		}

		$this->seed_pending_posts( 5 );

		return (bool) $this->db->get_var( $sql, array( 'pending' ) );
	}

	/**
	 * Remove metadata row for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_meta( int $post_id ): void {
		$this->db->delete(
			$this->meta_table(),
			array( 'post_id' => $post_id ),
			array( '%d' )
		);

		wp_cache_delete( $post_id, self::CACHE_COUNTS_GROUP );
	}

	/**
	 * Update incoming link counts for the provided posts.
	 *
	 * @param int              $post_id Source post ID.
	 * @param array<int, Link> $links   Outgoing links.
	 * @return void
	 */
	public function update_incoming_links( int $post_id, array $links ): void {
		$post_ids = array( $post_id );
		foreach ( $links as $link ) {
			if ( ! $link instanceof Link ) {
				continue;
			}

			$target_id = $link->get_target_post_id();
			if ( $target_id > 0 ) {
				$post_ids[] = $target_id;
			}
		}

		$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );
		if ( empty( $post_ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$sql          = sprintf(
			'SELECT target_post_id AS post_id, COUNT(id) AS incoming FROM %s WHERE target_post_id IN (%s) GROUP BY target_post_id',
			$this->links_table(),
			$placeholders
		);

		$results = $this->db->get_results( $sql, $post_ids );
		$found   = array();
		$now     = current_time( 'mysql' );

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$post_target = (int) $row->post_id;
				$this->upsert_meta(
					$post_target,
					array(
						'incoming_link_count' => isset( $row->incoming ) ? (int) $row->incoming : 0,
						'updated_at'          => $now,
					)
				);
				$found[] = $post_target;
			}
		}

		$zero_ids = array_diff( $post_ids, $found );
		if ( ! empty( $zero_ids ) ) {
			foreach ( $zero_ids as $id ) {
				$this->upsert_meta(
					(int) $id,
					array(
						'incoming_link_count' => 0,
						'updated_at'          => $now,
					)
				);
			}
		}
	}

	/**
	 * Get counts for a collection of posts.
	 *
	 * @param array<int,int> $post_ids Post IDs.
	 * @return array<int, array<string,int>>
	 */
	public function get_counts_for_posts( array $post_ids ): array {
		$post_ids = array_values( array_filter( array_map( 'intval', $post_ids ) ) );
		if ( empty( $post_ids ) ) {
			return array();
		}

		$output       = array();
		$uncached_ids = array();

		foreach ( $post_ids as $post_id ) {
			$cached = wp_cache_get( $post_id, self::CACHE_COUNTS_GROUP );
			if ( false === $cached ) {
				$uncached_ids[] = $post_id;
			} else {
				$output[ $post_id ] = (array) $cached;
			}
		}

		if ( ! empty( $uncached_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $uncached_ids ), '%d' ) );
			$sql          = sprintf(
				'SELECT post_id, internal_link_count, external_link_count, incoming_link_count FROM %s WHERE post_id IN (%s)',
				$this->meta_table(),
				$placeholders
			);

			$results = $this->db->get_results( $sql, $uncached_ids );

			if ( ! empty( $results ) ) {
				foreach ( $results as $row ) {
					$post_id = (int) $row->post_id;
					$data    = array(
						'internal_link_count' => isset( $row->internal_link_count ) ? (int) $row->internal_link_count : 0,
						'external_link_count' => isset( $row->external_link_count ) ? (int) $row->external_link_count : 0,
						'incoming_link_count' => isset( $row->incoming_link_count ) ? (int) $row->incoming_link_count : 0,
					);

					$output[ $post_id ] = $data;
					wp_cache_set( $post_id, $data, self::CACHE_COUNTS_GROUP );
				}
			}

			$missing = array_diff( $uncached_ids, array_keys( $output ) );
			if ( ! empty( $missing ) ) {
				foreach ( $missing as $post_id ) {
					$data               = array(
						'internal_link_count' => 0,
						'external_link_count' => 0,
						'incoming_link_count' => 0,
					);
					$output[ $post_id ] = $data;
					wp_cache_set( $post_id, $data, self::CACHE_COUNTS_GROUP );
				}
			}
		}

		return $output;
	}

	/**
	 * Count the number of posts in a given processing status.
	 *
	 * @param string $status Status slug saved in the meta table.
	 * @return int
	 */
	public function count_posts_by_status( string $status ): int {
		$status = sanitize_key( $status );
		if ( '' === $status ) {
			return 0;
		}

		$sql   = sprintf( 'SELECT COUNT(*) FROM %s WHERE status = %%s', $this->meta_table() );
		$count = $this->db->get_var( $sql, array( $status ) );

		return $count ? (int) $count : 0;
	}

	/**
	 * Count how many posts are waiting to be processed.
	 *
	 * @return int
	 */
	public function count_pending_posts(): int {
		return $this->count_posts_by_status( 'pending' );
	}

	/**
	 * Count how many posts are currently marked as processing.
	 *
	 * @return int
	 */
	public function count_processing_posts(): int {
		return $this->count_posts_by_status( 'processing' );
	}

	/**
	 * Count how many posts have been processed.
	 *
	 * @return int
	 */
	public function count_processed_posts(): int {
		return $this->count_posts_by_status( 'processed' );
	}

	/**
	 * Count how many posts are marked as failed.
	 *
	 * @return int
	 */
	public function count_failed_posts(): int {
		return $this->count_posts_by_status( 'failed' );
	}

	/**
	 * Reset all tracked posts back to pending status.
	 *
	 * @return int Number of rows updated.
	 */
	public function reset_all_to_pending(): int {
		$sql = sprintf(
			'UPDATE %s SET status = %%s, updated_at = %%s WHERE status IN (%%s, %%s, %%s, %%s)',
			$this->meta_table()
		);

		$updated = $this->db->query(
			$sql,
			array(
				'pending',
				current_time( 'mysql' ),
				'pending',
				'processing',
				'processed',
				'failed',
			),
		);

		$this->seed_pending_posts( 250 );

		return $updated ? (int) $updated : 0;
	}

	/**
	 * Seed pending rows for posts without metadata.
	 *
	 * @param int $limit Maximum number of posts to seed.
	 * @return void
	 */
	private function seed_pending_posts( int $limit ): void {
		$post_types = PostTypes::names();
		if ( empty( $post_types ) ) {
			return;
		}

		$limit = max( 1, (int) $limit );

		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$posts_table  = $this->db->prefix() . 'posts';
		$meta_table   = $this->meta_table();
		$sql          = sprintf(
			"SELECT ID FROM %s AS p LEFT JOIN %s AS m ON m.post_id = p.ID WHERE m.post_id IS NULL AND p.post_type IN (%s) AND p.post_status IN ('publish', 'future', 'private') LIMIT %d",
			$posts_table,
			$meta_table,
			$placeholders,
			$limit
		);

		$results = $this->db->get_results( $sql, $post_types );
		if ( empty( $results ) ) {
			return;
		}

		foreach ( $results as $row ) {
			$post_id = isset( $row->ID ) ? (int) $row->ID : 0;
			if ( $post_id <= 0 ) {
				continue;
			}

			$this->mark_pending( $post_id );
		}
	}

	/**
	 * Upsert metadata row for a post.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $data    Column/value pairs.
	 * @return void
	 */
	private function upsert_meta( int $post_id, array $data ): void {
		if ( empty( $data ) ) {
			return;
		}

		$data = array_merge( array( 'post_id' => $post_id ), $data );

		$fields       = array_keys( $data );
		$placeholders = array();
		$values       = array();

		foreach ( $data as $value ) {
			if ( is_int( $value ) ) {
				$placeholders[] = '%d';
			} elseif ( is_float( $value ) ) {
				$placeholders[] = '%f';
			} else {
				$placeholders[] = '%s';
			}
			$values[] = $value;
		}

		$insert_columns = implode( ', ', $fields );
		$insert_values  = implode( ', ', $placeholders );

		$updates = array();
		foreach ( $fields as $field ) {
			if ( 'post_id' === $field ) {
				continue;
			}
			$updates[] = sprintf( '%s = VALUES(%s)', $field, $field );
		}

		if ( empty( $updates ) ) {
			$sql = sprintf(
				'INSERT IGNORE INTO %s (%s) VALUES (%s)',
				$this->meta_table(),
				$insert_columns,
				$insert_values
			);
		} else {
			$sql = sprintf(
				'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
				$this->meta_table(),
				$insert_columns,
				$insert_values,
				implode( ', ', $updates )
			);
		}

		$this->db->query( $sql, $values );
		wp_cache_delete( $post_id, self::CACHE_COUNTS_GROUP );
	}

	/**
	 * Resolve the fully qualified internal links table name.
	 *
	 * @return string
	 */
	private function links_table(): string {
		return $this->db->table( Constants::TABLE_LINK_COUNTER_DATA );
	}

	/**
	 * Resolve the fully qualified meta table name.
	 *
	 * @return string
	 */
	private function meta_table(): string {
		return $this->db->table( Constants::TABLE_LINK_COUNTER_META );
	}
}
