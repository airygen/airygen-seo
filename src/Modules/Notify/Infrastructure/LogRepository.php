<?php
/**
 * Storage helper for Notify digest logs.
 *
 * @package Airygen\Modules\Notify\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Modules\Notify\Infrastructure;

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists and paginates notify digest logs.
 */
final class LogRepository {

	private const DEFAULT_PER_PAGE = 20;

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
	 * Ensure log table exists.
	 *
	 * @return void
	 */
	public function ensure_table(): void {
		$table = $this->table();
		if ( $this->db->table_exists( $table ) ) {
			return;
		}

		$upgrade_path = ABSPATH . 'wp-admin/includes/upgrade.php';
		if ( ! file_exists( $upgrade_path ) ) {
			return;
		}

		// @phpstan-ignore-next-line Path is provided by WordPress runtime.
		require_once $upgrade_path;

		$charset_collate = $this->db->collate();
		$sql             = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL auto_increment,
			run_at datetime NOT NULL,
			results_json longtext NULL,
			PRIMARY KEY  (id),
			KEY run_at (run_at)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Append one digest run log.
	 *
	 * @param array<int,array{channel:string,ok:bool,message:string}> $results Results payload.
	 * @return void
	 */
	public function append( array $results ): void {
		$this->ensure_table();

		$encoded = wp_json_encode( array_values( $results ) );
		if ( ! is_string( $encoded ) ) {
			$encoded = '[]';
		}

		$this->db->insert(
			$this->table(),
			array(
				'run_at'       => gmdate( 'Y-m-d H:i:s' ),
				'results_json' => $encoded,
			),
			array( '%s', '%s' )
		);
	}

	/**
	 * Fetch paginated logs.
	 *
	 * @param int $page Page number (1-indexed).
	 * @param int $per_page Items per page.
	 * @return array<int,array{timestamp:string,results:array<int,array{channel:string,ok:bool,message:string}>}>
	 */
	public function get_logs( int $page, int $per_page = self::DEFAULT_PER_PAGE ): array {
		$this->ensure_table();

		$per_page = max( 1, min( 50, $per_page ) );
		$page     = max( 1, $page );
		$offset   = ( $page - 1 ) * $per_page;

		$sql = sprintf(
			'SELECT id, run_at, results_json FROM %s WHERE id > %%d ORDER BY id DESC LIMIT %%d OFFSET %%d',
			$this->table()
		);

		$rows = $this->db->get_results( $sql, array( 0, $per_page, $offset ) );
		if ( empty( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( $row ): array {
				$timestamp = isset( $row->run_at ) ? (string) $row->run_at : '';
				$results   = array();
				if ( isset( $row->results_json ) ) {
					$decoded = json_decode( (string) $row->results_json, true );
					if ( is_array( $decoded ) ) {
						foreach ( $decoded as $item ) {
							if ( ! is_array( $item ) ) {
								continue;
							}
							$results[] = array(
								'channel' => isset( $item['channel'] ) ? (string) $item['channel'] : '',
								'ok'      => isset( $item['ok'] ) ? (bool) $item['ok'] : false,
								'message' => isset( $item['message'] ) ? (string) $item['message'] : '',
							);
						}
					}
				}

				return array(
					'timestamp' => $timestamp,
					'results'   => $results,
				);
			},
			$rows
		);
	}

	/**
	 * Count total log rows.
	 *
	 * @return int
	 */
	public function count_logs(): int {
		$this->ensure_table();

		$sql   = sprintf( 'SELECT COUNT(*) FROM %s WHERE id > %%d', $this->table() );
		$total = $this->db->get_var( $sql, array( 0 ) );

		return (int) $total;
	}

	/**
	 * Remove logs older than retention window.
	 *
	 * @param int $days Retention days.
	 * @return int
	 */
	public function purge_older_than_days( int $days ): int {
		$this->ensure_table();

		$days   = max( 1, $days );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$sql    = sprintf( 'DELETE FROM %s WHERE run_at < %%s', $this->table() );
		$result = $this->db->query( $sql, array( $cutoff ) );

		return is_int( $result ) ? $result : 0;
	}

	/**
	 * Resolve logs table name.
	 *
	 * @return string
	 */
	private function table(): string {
		return $this->db->table( Constants::TABLE_NOTIFY_LOGS );
	}
}
