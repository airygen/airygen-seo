<?php
/**
 * Storage helper for Broken Link Checker logs.
 *
 * @package Airygen\Modules\BrokenLinkChecker\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\BrokenLinkChecker\Domain;

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides paginated access to broken link logs.
 */
final class LogRepository {

	private const DEFAULT_PER_PAGE = 20;

	/**
	 * Allowed data source labels.
	 *
	 * @var array<int, string>
	 */
	private const DATA_SOURCES = array( 'db_cache', 'loop_cache', 'http_request' );

	/**
	 * Database adapter instance.
	 *
	 * @var WpDbAdapter
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param WpDbAdapter|null $db Optional adapter for testing.
	 */
	public function __construct( ?WpDbAdapter $db = null ) {
		$this->db = $db ?? new WpDbAdapter();
	}

	/**
	 * Retrieve a paginated list of log entries.
	 *
	 * @param int               $page     Page number (1-indexed).
	 * @param int               $per_page Items per page (defaults to 20, max 50).
	 * @param array<int,string> $statuses Status buckets (ok, redirect, error).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_logs( int $page, int $per_page = self::DEFAULT_PER_PAGE, array $statuses = array() ): array {
		$per_page = max( 1, min( 50, $per_page ) );
		$page     = max( 1, $page );
		$offset   = ( $page - 1 ) * $per_page;

		list( $status_sql, $status_params ) = $this->build_status_filter_clause( $statuses );

		$sql = sprintf(
			'SELECT link_id, post_id, url, status_code, status_label, error_message, data_source, checked_at, created_at FROM %s %s ORDER BY checked_at DESC, link_id DESC LIMIT %%d OFFSET %%d',
			$this->table(),
			$status_sql
		);

		$params  = array_merge( $status_params, array( $per_page, $offset ) );
		$results = $this->db->get_results( $sql, $params );
		if ( empty( $results ) ) {
			return array();
		}

		return array_map(
			static function ( $row ): array {
				return array(
					'id'            => isset( $row->link_id ) ? (int) $row->link_id : 0,
					'link_id'       => isset( $row->link_id ) ? (int) $row->link_id : 0,
					'post_id'       => isset( $row->post_id ) ? (int) $row->post_id : 0,
					'url'           => isset( $row->url ) ? (string) $row->url : '',
					'status_code'   => isset( $row->status_code ) ? (int) $row->status_code : null,
					'status_label'  => isset( $row->status_label ) ? (string) $row->status_label : null,
					'error_message' => isset( $row->error_message ) ? (string) $row->error_message : null,
					'data_source'   => isset( $row->data_source ) ? (string) $row->data_source : '',
					'checked_at'    => isset( $row->checked_at ) ? (string) $row->checked_at : '',
					'created_at'    => isset( $row->created_at ) ? (string) $row->created_at : '',
				);
			},
			$results
		);
	}

	/**
	 * Count total log entries for pagination.
	 *
	 * @param array<int,string> $statuses Status buckets (ok, redirect, error).
	 * @return int
	 */
	public function count_logs( array $statuses = array() ): int {
		list( $status_sql, $status_params ) = $this->build_status_filter_clause( $statuses );

		if ( '' === $status_sql ) {
			$sql    = sprintf( 'SELECT COUNT(*) FROM %s WHERE 1 = %%d', $this->table() );
			$params = array( 1 );
		} else {
			$sql    = sprintf( 'SELECT COUNT(*) FROM %s %s', $this->table(), $status_sql );
			$params = $status_params;
		}

		$total = $this->db->get_var( $sql, $params );

		return (int) $total;
	}

	/**
	 * Resolve the log table.
	 *
	 * @return string
	 */
	private function table(): string {
		return $this->db->table( Constants::TABLE_LINK_CHECKER_LOG );
	}

	/**
	 * Fetch a single log entry by link ID.
	 *
	 * @param int $link_id Link counter ID.
	 * @return array<string,mixed>|null
	 */
	public function find_by_link_id( int $link_id ): ?array {
		$sql = sprintf(
			'SELECT link_id, post_id, url, status_code, status_label, error_message, data_source, checked_at, created_at FROM %s WHERE link_id = %%d LIMIT 1',
			$this->table()
		);

		$results = $this->db->get_results( $sql, array( $link_id ) );

		if ( empty( $results ) ) {
			return null;
		}

		$row = (array) $results[0];

		return array(
			'link_id'       => isset( $row['link_id'] ) ? (int) $row['link_id'] : 0,
			'post_id'       => isset( $row['post_id'] ) ? (int) $row['post_id'] : 0,
			'url'           => $row['url'] ?? '',
			'status_code'   => isset( $row['status_code'] ) ? (int) $row['status_code'] : null,
			'status_label'  => $row['status_label'] ?? '',
			'error_message' => $row['error_message'] ?? null,
			'data_source'   => $row['data_source'] ?? '',
			'checked_at'    => $row['checked_at'] ?? '',
			'created_at'    => $row['created_at'] ?? '',
		);
	}

	/**
	 * Insert or update a log entry.
	 *
	 * @param array<string,mixed> $data Row payload.
	 * @return void
	 */
	public function upsert( array $data ): void {
		$sql = sprintf(
			"INSERT INTO %s (link_id, post_id, url, status_code, status_label, error_message, data_source, checked_at, created_at) VALUES (%%d, %%d, %%s, %%d, %%s, %%s, %%s, %%s, %%s)\n\t\t\tON DUPLICATE KEY UPDATE status_code = VALUES(status_code), status_label = VALUES(status_label), error_message = VALUES(error_message), data_source = VALUES(data_source), checked_at = VALUES(checked_at), created_at = LEAST(created_at, VALUES(created_at))",
			$this->table()
		);

		$now         = gmdate( 'Y-m-d H:i:s' );
		$data_source = isset( $data['data_source'] ) ? (string) $data['data_source'] : 'http_request';
		if ( ! in_array( $data_source, self::DATA_SOURCES, true ) ) {
			$data_source = 'http_request';
		}

		$this->db->query(
			$sql,
			array(
				(int) ( $data['link_id'] ?? 0 ),
				(int) ( $data['post_id'] ?? 0 ),
				(string) ( $data['url'] ?? '' ),
				(int) ( $data['status_code'] ?? 0 ),
				(string) ( $data['status_label'] ?? '' ),
				(string) ( $data['error_message'] ?? '' ),
				$data_source,
				(string) ( $data['checked_at'] ?? $now ),
				(string) ( $data['created_at'] ?? $now ),
			)
		);
	}

	/**
	 * Remove log entries older than the retention window.
	 *
	 * @param int $days Retention window in days.
	 * @return int Number of rows deleted.
	 */
	public function purge_older_than_days( int $days ): int {
		$days   = max( 1, $days );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$sql = sprintf(
			'DELETE FROM %s WHERE checked_at < %%s',
			$this->table()
		);

		$result = $this->db->query( $sql, array( $cutoff ) );

		return is_int( $result ) ? $result : 0;
	}

	/**
	 * Build the SQL clause for status filtering.
	 *
	 * @param array<int,string> $statuses Allowed status buckets.
	 * @return array{0:string,1:array<int,mixed>}
	 */
	private function build_status_filter_clause( array $statuses ): array {
		if ( empty( $statuses ) ) {
			return array( '', array() );
		}

		$clauses = array();
		$params  = array();

		if ( in_array( 'ok', $statuses, true ) ) {
			$clauses[] = '(status_label = %s OR (status_label = %s AND (error_message IS NULL OR error_message = "")))';
			$params[]  = 'ok';
			$params[]  = 'unknown';
		}

		if ( in_array( 'redirect', $statuses, true ) ) {
			$clauses[] = 'status_label = %s';
			$params[]  = 'redirect';
		}

		if ( in_array( 'error', $statuses, true ) ) {
			$clauses[] = '(status_label IN (%s,%s,%s) OR (status_label = %s AND error_message <> ""))';
			$params[]  = 'client_error';
			$params[]  = 'server_error';
			$params[]  = 'error';
			$params[]  = 'unknown';
		}

		if ( empty( $clauses ) ) {
			return array( '', array() );
		}

		$sql = 'WHERE (' . implode( ' OR ', $clauses ) . ')';

		return array( $sql, $params );
	}
}
