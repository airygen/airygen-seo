<?php
/**
 * Repository for interacting with the link counter data table.
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
 * Provides helpers for selecting and updating link counter rows.
 */
final class LinkDataRepository {

	/**
	 * Database adapter instance.
	 *
	 * @var WpDbAdapter
	 */
	private $db;

	/**
	 * Cached table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 *
	 * @param WpDbAdapter|null $db Optional adapter (useful for testing).
	 */
	public function __construct( ?WpDbAdapter $db = null ) {
		$this->db    = $db ?? new WpDbAdapter();
		$this->table = $this->db->table( Constants::TABLE_LINK_COUNTER_DATA );
	}

	/**
	 * Fetch a batch of candidate links that need to be checked.
	 *
	 * @param int               $limit             Maximum links to return.
	 * @param int               $staleness_minutes Minimum minutes between rechecks.
	 * @param array<int,string> $link_types        Optional link type filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_candidates( int $limit, int $staleness_minutes, array $link_types = array() ): array {
		$limit             = max( 1, $limit );
		$staleness_minutes = max( 1, $staleness_minutes );

		$current       = time();
		$threshold     = gmdate( 'Y-m-d H:i:s', $current - ( $staleness_minutes * 60 ) );
		$allowed_types = array( 'external', 'internal' );
		$filtered      = array_values( array_intersect( array_map( 'strval', $link_types ), $allowed_types ) );
		$filter_types  = count( $filtered ) === 1 ? $filtered : array();

		// Keep the staleness OR conditions grouped so optional type filtering
		// applies to every candidate branch.
		$where        = 'WHERE (status_check = 0 OR last_status_checked_at IS NULL OR last_status_checked_at <= %s)';
		$params       = array( $threshold );
		$types_clause = '';
		if ( ! empty( $filter_types ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $filter_types ), '%s' ) );
			$types_clause = sprintf( ' AND type IN (%s)', $placeholders );
			$params       = array_merge( $params, $filter_types );
		}

		$sql = sprintf(
			' SELECT id, post_id, url, status_check, last_status_checked_at FROM %s %s%s ORDER BY (last_status_checked_at IS NULL) DESC, last_status_checked_at ASC, id ASC LIMIT %d',
			$this->table,
			$where,
			$types_clause,
			$limit
		);

		$results = $this->db->get_results( $sql, $params );

		if ( empty( $results ) ) {
			return array();
		}

		return array_map(
			static function ( $row ): array {
				return array(
					'id'           => isset( $row->id ) ? (int) $row->id : 0,
					'post_id'      => isset( $row->post_id ) ? (int) $row->post_id : 0,
					'url'          => isset( $row->url ) ? (string) $row->url : '',
					'status_check' => isset( $row->status_check ) ? (int) $row->status_check : 0,
					'last_checked' => isset( $row->last_status_checked_at ) ? (string) $row->last_status_checked_at : null,
				);
			},
			$results
		);
	}

	/**
	 * Persist the latest status for a link row.
	 *
	 * @param int    $link_id     Link counter primary key.
	 * @param int    $status      Status bucket.
	 * @param string $checked_at  Timestamp in MySQL datetime format.
	 * @return void
	 */
	public function update_status( int $link_id, int $status, string $checked_at ): void {
		$sql = sprintf(
			'UPDATE %s SET status_check = %%d, last_status_checked_at = %%s WHERE id = %%d',
			$this->table
		);

		$this->db->query(
			$sql,
			array(
				$status,
				$checked_at,
				$link_id,
			)
		);
	}
}
