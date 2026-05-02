<?php
/**
 * Table repository for Markdown for Agents snapshots.
 *
 * @package Airygen\Modules\MarkdownForAgents\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Modules\MarkdownForAgents\Infrastructure;

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists rendered markdown content by post.
 */
final class MarkdownPostRepository {

	/**
	 * Database adapter.
	 *
	 * @var WpDbAdapter
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param WpDbAdapter|null $db Optional adapter.
	 */
	public function __construct( ?WpDbAdapter $db = null ) {
		$this->db = $db ?? new WpDbAdapter();
	}

	/**
	 * Ensure table exists.
	 *
	 * @return void
	 */
	public function ensure_table(): void {
		$table = $this->table();
		if ( $this->db->table_exists( $table ) ) {
			return;
		}

		$charset_collate = $this->db->collate();
		$sql             = "CREATE TABLE IF NOT EXISTS $table (
			id bigint(20) unsigned NOT NULL auto_increment,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(32) NOT NULL,
			post_status varchar(20) NOT NULL,
			locale varchar(10) NOT NULL default '',
			canonical_url text NULL,
			title text NOT NULL,
			excerpt longtext NULL,
			markdown_content longtext NOT NULL,
			frontmatter_yaml longtext NULL,
			content_hash char(64) NOT NULL,
			source_modified_gmt datetime NULL,
			last_synced_gmt datetime NOT NULL,
			is_deleted tinyint(1) NOT NULL default 0,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			KEY type_status (post_type, post_status),
			KEY last_synced_gmt (last_synced_gmt),
			KEY is_deleted (is_deleted)
		) $charset_collate;";

		$this->db->query( $sql );
	}

	/**
	 * Upsert one snapshot row.
	 *
	 * @param array<string,mixed> $row Row payload.
	 *
	 * @return void
	 */
	public function upsert( array $row ): void {
		$this->db->replace(
			$this->table(),
			array(
				'post_id'             => isset( $row['post_id'] ) ? (int) $row['post_id'] : 0,
				'post_type'           => isset( $row['post_type'] ) ? (string) $row['post_type'] : '',
				'post_status'         => isset( $row['post_status'] ) ? (string) $row['post_status'] : 'publish',
				'locale'              => isset( $row['locale'] ) ? (string) $row['locale'] : '',
				'canonical_url'       => isset( $row['canonical_url'] ) ? (string) $row['canonical_url'] : '',
				'title'               => isset( $row['title'] ) ? (string) $row['title'] : '',
				'excerpt'             => isset( $row['excerpt'] ) ? (string) $row['excerpt'] : '',
				'markdown_content'    => isset( $row['markdown_content'] ) ? (string) $row['markdown_content'] : '',
				'frontmatter_yaml'    => isset( $row['frontmatter_yaml'] ) ? (string) $row['frontmatter_yaml'] : '',
				'content_hash'        => isset( $row['content_hash'] ) ? (string) $row['content_hash'] : '',
				'source_modified_gmt' => isset( $row['source_modified_gmt'] ) ? (string) $row['source_modified_gmt'] : null,
				'last_synced_gmt'     => gmdate( 'Y-m-d H:i:s' ),
				'is_deleted'          => 0,
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
			)
		);
	}

	/**
	 * Mark a post snapshot as deleted.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function mark_deleted( int $post_id ): void {
		$this->db->query(
			'UPDATE ' . $this->table() . ' SET is_deleted = 1, last_synced_gmt = %s WHERE post_id = %d',
			array( gmdate( 'Y-m-d H:i:s' ), $post_id )
		);
	}

	/**
	 * Fetch one snapshot by post ID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_by_post_id( int $post_id ): ?array {
		$results = $this->db->get_results(
			'SELECT * FROM ' . $this->table() . ' WHERE post_id = %d LIMIT 1',
			array( $post_id ),
			ARRAY_A
		);

		if ( empty( $results ) || ! isset( $results[0] ) || ! is_array( $results[0] ) ) {
			return null;
		}

		return $results[0];
	}

	/**
	 * Fetch recent active snapshots.
	 *
	 * @param int $limit Max rows.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent_active( int $limit = 100 ): array {
		$limit = max( 1, min( 500, $limit ) );

		return $this->db->get_results(
			'SELECT post_id, post_type, title, canonical_url, markdown_content, frontmatter_yaml
			 FROM ' . $this->table() . '
			 WHERE is_deleted = 0 AND post_status = %s
			 ORDER BY post_id DESC
			 LIMIT %d',
			array( 'publish', $limit ),
			ARRAY_A
		);
	}

	/**
	 * Count active published snapshots.
	 *
	 * @return int
	 */
	public function count_active_published(): int {
		$value = $this->db->get_var(
			'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE is_deleted = 0 AND post_status = %s',
			array( 'publish' )
		);

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Fetch paginated snapshots.
	 *
	 * @param int         $page      Page number.
	 * @param int         $per_page  Rows per page.
	 * @param string|null $post_type Optional post type filter.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_snapshots( int $page = 1, int $per_page = 20, ?string $post_type = null ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$sql    = 'SELECT post_id, post_type, title, canonical_url, last_synced_gmt, content_hash
			FROM ' . $this->table() . '
			WHERE is_deleted = 0 AND post_status = %s';
		$params = array( 'publish' );

		if ( is_string( $post_type ) && '' !== $post_type ) {
			$sql     .= ' AND post_type = %s';
			$params[] = $post_type;
		}

		$sql     .= ' ORDER BY post_id DESC LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		return $this->db->get_results( $sql, $params, ARRAY_A );
	}

	/**
	 * Count snapshots with optional post type filter.
	 *
	 * @param string|null $post_type Optional post type filter.
	 *
	 * @return int
	 */
	public function count_snapshots( ?string $post_type = null ): int {
		$sql    = 'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE is_deleted = 0 AND post_status = %s';
		$params = array( 'publish' );

		if ( is_string( $post_type ) && '' !== $post_type ) {
			$sql     .= ' AND post_type = %s';
			$params[] = $post_type;
		}

		$value = $this->db->get_var( $sql, $params );
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Resolve table name.
	 *
	 * @return string
	 */
	private function table(): string {
		return $this->db->table( Constants::TABLE_MARKDOWN_POSTS );
	}
}
