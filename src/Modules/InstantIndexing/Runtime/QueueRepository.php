<?php
/**
 * Persistence helper for IndexNow events.
 *
 * @package Airygen\Modules\InstantIndexing\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Runtime;

use Airygen\Constants;
use Airygen\Modules\InstantIndexing\Domain\Event;
use Airygen\Modules\InstantIndexing\Domain\EventStatus;
use Airygen\Modules\InstantIndexing\Domain\QueueSummary;
use Airygen\Support\Database\WpDbAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides persistence helpers for IndexNow events.
 */
final class QueueRepository {

	/**
	 * Maximum number of retry attempts before marking an event as failed.
	 */
	private const MAX_ATTEMPTS = 5;

	/**
	 * Columns that can be updated via bulk_update_state.
	 *
	 * @var array<int, string>
	 */
	private const MUTABLE_COLUMNS = array(
		'status',
		'updated_at',
		'available_at',
		'last_error',
		'last_response',
		'attempts',
	);

	/**
	 * Database helper.
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
	 * Insert a new event into the queue.
	 *
	 * @param string $host   Host portion of the site.
	 * @param string $url    Canonical URL to ping.
	 * @param string $action One of add/update/delete.
	 * @param string $source Source identifier (auto, manual, backfill, cli).
	 * @return bool
	 */
	public function enqueue( string $host, string $url, string $action = 'update', string $source = 'auto' ): bool {
		$host   = strtolower( sanitize_text_field( $host ) );
		$url    = esc_url_raw( $url );
		$action = $this->sanitize_action( $action );
		$source = $this->sanitize_source( $source );

		if ( '' === $host || '' === $url ) {
			return false;
		}

		$now = current_time( 'mysql' );

		$result = $this->db->insert(
			$this->table(),
			array(
				'host'         => $host,
				'url'          => $url,
				'action'       => $action,
				'source'       => $source,
				'status'       => EventStatus::PENDING,
				'attempts'     => 0,
				'created_at'   => $now,
				'updated_at'   => $now,
				'available_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Bulk enqueue URLs.
	 *
	 * @param string            $host   Site host.
	 * @param array<int,string> $urls   URL list.
	 * @param string            $action Event action.
	 * @param string            $source Source identifier.
	 * @return int Number of queued rows.
	 */
	public function enqueue_many( string $host, array $urls, string $action = 'update', string $source = 'auto' ): int {
		$count = 0;
		foreach ( $urls as $url ) {
			if ( $this->enqueue( $host, (string) $url, $action, $source ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Claim a batch of pending events for processing.
	 *
	 * @param int $limit Maximum number of events.
	 * @return array<int, Event>
	 */
	public function claim_batch( int $limit ): array {
		$limit = max( 1, $limit );
		$table = $this->table();
		$now   = current_time( 'mysql' );

		$sql = sprintf(
			'SELECT id, host, url, action, source, status, attempts FROM %1$s WHERE status = %%s AND (available_at IS NULL OR available_at <= %%s) ORDER BY id ASC LIMIT %2$d',
			$table,
			$limit
		);

		$rows = $this->db->get_results( $sql, array( EventStatus::PENDING, $now ) );

		if ( empty( $rows ) ) {
			return array();
		}

		$ids = array_map(
			static function ( $row ): int {
				return (int) $row->id;
			},
			$rows
		);

		$this->bulk_update_state(
			$ids,
			array(
				'status'     => EventStatus::PROCESSING,
				'updated_at' => $now,
			)
		);

		$events = array();
		foreach ( $rows as $row ) {
			$events[] = new Event(
				(int) $row->id,
				(string) $row->host,
				(string) $row->url,
				(string) $row->action,
				(string) $row->source,
				(string) $row->status,
				(int) $row->attempts
			);
		}

		return $events;
	}

	/**
	 * Mark events as completed.
	 *
	 * @param array<int,int> $ids Event IDs.
	 * @return void
	 */
	public function mark_completed( array $ids ): void {
		if ( empty( $ids ) ) {
			return;
		}

		$this->bulk_update_state(
			$ids,
			array(
				'status'       => EventStatus::COMPLETED,
				'updated_at'   => current_time( 'mysql' ),
				'available_at' => null,
				'last_error'   => null,
			)
		);
	}

	/**
	 * Mark events as failed permanently.
	 *
	 * @param array<int,int> $ids   Event IDs.
	 * @param string         $error Error message to store.
	 * @return void
	 */
	public function mark_failed( array $ids, string $error ): void {
		if ( empty( $ids ) ) {
			return;
		}

		$this->bulk_update_state(
			$ids,
			array(
				'status'     => EventStatus::FAILED,
				'updated_at' => current_time( 'mysql' ),
				'last_error' => $error,
			)
		);
	}

	/**
	 * Release events back to pending with a future availability timestamp.
	 *
	 * @param array<int,int> $ids      Event IDs.
	 * @param string         $error    Error message.
	 * @param int            $delay_seconds Delay in seconds.
	 * @return void
	 */
	public function release_with_delay( array $ids, string $error, int $delay_seconds ): void {
		if ( empty( $ids ) ) {
			return;
		}

		$available_at = gmdate( 'Y-m-d H:i:s', time() + max( 1, $delay_seconds ) );

		$this->bulk_update_state(
			$ids,
			array(
				'status'       => EventStatus::PENDING,
				'available_at' => $available_at,
				'updated_at'   => current_time( 'mysql' ),
				'last_error'   => $error,
				'attempts'     => array( 'increment' => 1 ),
			)
		);
	}

	/**
	 * Store response metadata for given events.
	 *
	 * @param array<int,int>      $ids Event IDs.
	 * @param array<string,mixed> $response Response payload to json encode.
	 * @return void
	 */
	public function record_response( array $ids, array $response ): void {
		if ( empty( $ids ) ) {
			return;
		}

		$this->bulk_update_state(
			$ids,
			array(
				'last_response' => wp_json_encode( $response ),
			)
		);
	}

	/**
	 * Retrieve aggregate queue counts.
	 *
	 * @return QueueSummary
	 */
	public function summary(): QueueSummary {
		global $wpdb;

		$table  = $this->table();
		$counts = array(
			EventStatus::PENDING    => 0,
			EventStatus::PROCESSING => 0,
			EventStatus::FAILED     => 0,
			EventStatus::COMPLETED  => 0,
		);

		$sql = sprintf(
			'SELECT status, COUNT(*) AS total FROM %s GROUP BY status',
			$table
		);

		$results = $wpdb->get_results( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name comes from the plugin's sanitized table helper.

		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$status = isset( $row->status ) ? (string) $row->status : '';
				if ( isset( $counts[ $status ] ) ) {
					$counts[ $status ] = (int) $row->total;
				}
			}
		}

		return new QueueSummary(
			$counts[ EventStatus::PENDING ],
			$counts[ EventStatus::PROCESSING ],
			$counts[ EventStatus::FAILED ],
			$counts[ EventStatus::COMPLETED ]
		);
	}

	/**
	 * Retrieve recent events for audit/log surfaces.
	 *
	 * @param int $limit Maximum number of rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 10 ): array {
		global $wpdb;

		$limit = max( 1, $limit );
		$table = $this->table();

		$sql = sprintf(
			'SELECT id, url, status, source, last_error, last_response, updated_at FROM %s ORDER BY updated_at DESC LIMIT %%d',
			$table
		);

		$query = $wpdb->prepare( $sql, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name is sanitized before interpolation.

		$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Query string comes from wpdb::prepare().

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Resolve the fully-qualified events table name.
	 *
	 * @return string
	 */
	private function table(): string {
		return $this->db->table( Constants::TABLE_INDEXNOW_EVENTS );
	}

	/**
	 * Normalize an action name.
	 *
	 * @param string $action Raw action.
	 * @return string
	 */
	private function sanitize_action( string $action ): string {
		$allowed = array( 'add', 'update', 'delete' );
		$action  = strtolower( $action );
		return in_array( $action, $allowed, true ) ? $action : 'update';
	}

	/**
	 * Normalize the source identifier.
	 *
	 * @param string $source Raw source.
	 * @return string
	 */
	private function sanitize_source( string $source ): string {
		$source = strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $source ) ?? '' );
		return '' !== $source ? $source : 'auto';
	}

	/**
	 * Internal helper for batch updates.
	 *
	 * @param array<int,int>      $ids    IDs to update.
	 * @param array<string,mixed> $data   Column => value pairs. For columns that should be incremented, pass array('increment' => value).
	 * @return void
	 */
	private function bulk_update_state( array $ids, array $data ): void {
		$ids = array_map( 'intval', $ids );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return;
		}

		$table = $this->table();

		$allowed   = self::MUTABLE_COLUMNS;
		$set_parts = array();
		$values    = array();

		foreach ( $data as $column => $value ) {
			if ( ! in_array( $column, $allowed, true ) ) {
				continue;
			}

			if ( is_array( $value ) && isset( $value['increment'] ) ) {
				$increment   = (int) $value['increment'];
				$set_parts[] = sprintf( '%s = %s + %d', $column, $column, $increment );
			} elseif ( null === $value ) {
				$set_parts[] = sprintf( '%s = NULL', $column );
			} else {
				$set_parts[] = sprintf( '%s = %%s', $column );
				$values[]    = (string) $value;
			}
		}

		if ( empty( $set_parts ) ) {
			return;
		}

		$in_clause = implode( ',', $ids );
		$sql       = sprintf( 'UPDATE %s SET %s WHERE id IN (%s)', $table, implode( ', ', $set_parts ), $in_clause );

		$this->db->query( $sql, $values );
	}

	/**
	 * Retrieve the max attempt threshold.
	 *
	 * @return int
	 */
	public static function max_attempts(): int {
		return self::MAX_ATTEMPTS;
	}
}
