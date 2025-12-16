<?php
/**
 * Data access for 404 logs.
 *
 * @package Airygen\Modules\NotFoundManager\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Modules\NotFoundManager\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;

/**
 * Repository for 404 log entries.
 */
final class LogRepository {

	/**
	 * Database adapter.
	 *
	 * @var WpDbAdapter
	 */
	private WpDbAdapter $db;

	/**
	 * Constructor.
	 *
	 * @param WpDbAdapter|null $db Adapter.
	 */
	public function __construct( ?WpDbAdapter $db = null ) {
		$this->db = $db ?? new WpDbAdapter();
	}

	/**
	 * Insert or aggregate a 404 row.
	 *
	 * @param string      $url_path Path only.
	 * @param string|null $query_hash Query hash or null.
	 * @param string|null $referer Referer.
	 * @param string|null $user_agent User-agent.
	 * @param bool        $allow_updates Whether existing row should be updated.
	 * @return void
	 */
	public function upsert( string $url_path, ?string $query_hash, ?string $referer, ?string $user_agent, bool $allow_updates = true ): void {
		$table = $this->table();

		$rows = $this->db->get_results(
			"SELECT id, hits FROM {$table} WHERE url_path = %s AND (query_hash = %s OR (query_hash IS NULL AND %s = '')) LIMIT 1",
			array( $url_path, (string) $query_hash, (string) $query_hash ),
			\ARRAY_A
		);

		$now = current_time( 'mysql' );
		if ( ! empty( $rows ) && isset( $rows[0]['id'] ) ) {
			if ( ! $allow_updates ) {
				return;
			}
			$id = (int) $rows[0]['id'];
			$this->db->query(
				"UPDATE {$table} SET hits = hits + 1, last_seen_at = %s, last_referer = %s, last_user_agent = %s WHERE id = %d",
				array( $now, (string) $referer, (string) $user_agent, $id )
			);
			return;
		}

		$this->db->query(
			"INSERT INTO {$table} (url_path, query_hash, hits, first_seen_at, last_seen_at, last_referer, last_user_agent, status) VALUES (%s, %s, 1, %s, %s, %s, %s, 'open')",
			array( $url_path, (string) $query_hash, $now, $now, (string) $referer, (string) $user_agent )
		);
	}

	/**
	 * List logs.
	 *
	 * @param int         $page Page number.
	 * @param int         $per_page Per-page.
	 * @param string      $status Status filter.
	 * @param string|null $search Search keyword.
	 * @return array<string,mixed>
	 */
	public function list( int $page, int $per_page, string $status = '', ?string $search = null ): array {
		$table  = $this->table();
		$page   = max( 1, $page );
		$limit  = max( 1, min( 200, $per_page ) );
		$offset = ( $page - 1 ) * $limit;

		$where_clauses = array();
		$params        = array();

		if ( '' !== $status && in_array( $status, array( 'open', 'ignored', 'resolved' ), true ) ) {
			$where_clauses[] = 'status = %s';
			$params[]        = $status;
		}

		if ( is_string( $search ) && '' !== trim( $search ) ) {
			$where_clauses[] = 'url_path LIKE %s';
			$params[]        = '%' . trim( $search ) . '%';
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		$count_sql = "SELECT COUNT(*) FROM {$table}{$where_sql}";
		if ( empty( $params ) ) {
			$total = (int) $GLOBALS['wpdb']->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$total = (int) $this->db->get_var( $count_sql, $params );
		}

		$list_sql    = "SELECT id, url_path, query_hash, hits, first_seen_at, last_seen_at, last_referer, last_user_agent, status, matched_redirect_id FROM {$table}{$where_sql} ORDER BY last_seen_at DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $limit, $offset ) );
		$rows        = $this->db->get_results( $list_sql, $list_params, \ARRAY_A );

		return array(
			'items'    => is_array( $rows ) ? $rows : array(),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $limit,
		);
	}

	/**
	 * Mark status for a row.
	 *
	 * @param int    $id Row id.
	 * @param string $status New status.
	 * @return bool
	 */
	public function mark_status( int $id, string $status ): bool {
		if ( ! in_array( $status, array( 'open', 'ignored', 'resolved' ), true ) ) {
			return false;
		}

		$updated = $this->db->query(
			"UPDATE {$this->table()} SET status = %s WHERE id = %d",
			array( $status, $id )
		);
		return false !== $updated;
	}

	/**
	 * Delete row by id.
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		$deleted = $this->db->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
		return false !== $deleted;
	}

	/**
	 * Purge old rows.
	 *
	 * @param int $retention_days Days to keep.
	 * @return int
	 */
	public function purge_older_than_days( int $retention_days ): int {
		$retention_days = max( 1, $retention_days );
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );

		$result = $this->db->query(
			"DELETE FROM {$this->table()} WHERE last_seen_at < %s",
			array( $cutoff )
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Get high-level stats.
	 *
	 * @return array<string,int>
	 */
	public function stats(): array {
		$table = $this->table();

		$total_urls = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_hits = (int) $GLOBALS['wpdb']->get_var( "SELECT COALESCE(SUM(hits), 0) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$open_urls  = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'open'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'total_urls' => $total_urls,
			'total_hits' => $total_hits,
			'open_urls'  => $open_urls,
		);
	}

	/**
	 * Resolve table name.
	 *
	 * @return string
	 */
	private function table(): string {
		return $this->db->table( Constants::TABLE_404_LOGS );
	}
}
