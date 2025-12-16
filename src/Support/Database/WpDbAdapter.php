<?php
/**
 * Thin wrapper around wpdb to centralise prepare/query behaviour.
 *
 * @package Airygen\Support\Database
 */

declare(strict_types=1);

namespace Airygen\Support\Database;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides helper methods for performing prepared queries.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
 */
final class WpDbAdapter {

	/**
	 * Underlying wpdb instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $wpdb Optional wpdb instance (primarily for testing).
	 */
	public function __construct( ?wpdb $wpdb = null ) {
		$this->wpdb = $wpdb ?? $GLOBALS['wpdb']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Retrieve the WordPress table prefix.
	 *
	 * @return string
	 */
	public function prefix(): string {
		return $this->wpdb->prefix;
	}

	/**
	 * Retrieve the charset collate string for table creation.
	 *
	 * @return string
	 */
	public function collate(): string {
		return $this->wpdb->get_charset_collate();
	}

	/**
	 * Resolve a safe table name with the current prefix.
	 *
	 * @param string $suffix Table suffix within the Airygen SEO namespace.
	 * @return string
	 */
	public function table( string $suffix ): string {
		$sanitized = preg_replace( '/[^A-Za-z0-9_]/', '', $suffix );
		$sanitized = is_string( $sanitized ) ? $sanitized : '';
		return $this->prefix() . $sanitized;
	}

	/**
	 * Run a delete query.
	 *
	 * @param string $table  Table name.
	 * @param array  $where  Where array.
	 * @param array  $format Format array.
	 * @return int|false
	 */
	public function delete( string $table, array $where, array $format ) {
		return $this->wpdb->delete( $table, $where, $format );
	}

	/**
	 * Run an insert query.
	 *
	 * @param string $table  Table name.
	 * @param array  $data   Data to insert.
	 * @param array  $format Format array.
	 * @return int|false
	 */
	public function insert( string $table, array $data, array $format ) {
		return $this->wpdb->insert( $table, $data, $format );
	}

	/**
	 * Run a replace query.
	 *
	 * @param string $table  Table name.
	 * @param array  $data   Data to insert/replace.
	 * @param array  $format Format array.
	 * @return int|false
	 */
	public function replace( string $table, array $data, array $format ) {
		return $this->wpdb->replace( $table, $data, $format );
	}

	/**
	 * Execute a raw query.
	 *
	 * @param string $sql    SQL statement.
	 * @param array  $params Parameters for prepare.
	 * @return int|false
	 */
	public function query( string $sql, array $params = array() ) {
		if ( empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Callers are responsible for passing trusted SQL when no parameters are provided.
			return $this->wpdb->query( $sql );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $this->prepare( $sql, $params );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query string comes from wpdb::prepare().
		return $this->wpdb->query( $prepared );
	}

	/**
	 * Fetch multiple results.
	 *
	 * @param string $sql      SQL statement.
	 * @param array  $params   Parameters for prepare.
	 * @param string $output   Desired output type.
	 * @return array<int, mixed>
	 * @throws \InvalidArgumentException When parameters are missing for a prepared query.
	 */
	public function get_results( string $sql, array $params = array(), string $output = \OBJECT ): array {
		if ( empty( $params ) ) {
			// Enforce placeholders to avoid unprepared queries.
			throw new \InvalidArgumentException( 'Missing parameters for prepared statement. Use placeholders and pass parameters.' );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $this->wpdb->prepare( $sql, ...$params );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query string comes from wpdb::prepare().
		return $this->wpdb->get_results( $prepared, $output );
	}

	/**
	 * Fetch a single scalar value.
	 *
	 * @param string $sql    SQL statement.
	 * @param array  $params Parameters for prepare.
	 * @return mixed
	 * @throws \InvalidArgumentException When parameters are missing for a prepared query.
	 */
	public function get_var( string $sql, array $params = array() ) {
		if ( empty( $params ) ) {
			// Enforce placeholders to avoid unprepared queries.
			throw new \InvalidArgumentException( 'Missing parameters for prepared statement. Use placeholders and pass parameters.' );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $this->wpdb->prepare( $sql, ...$params );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query string comes from wpdb::prepare().
		return $this->wpdb->get_var( $prepared );
	}

	/**
	 * Prepare a statement with arguments.
	 *
	 * @param string $sql    SQL statement.
	 * @param array  $params Parameters to bind.
	 * @return string
	 */
	public function prepare( string $sql, array $params = array() ): string {
		if ( empty( $params ) ) {
			return $sql;
		}

		$args = array_merge( array( $sql ), $params );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return call_user_func_array( array( $this->wpdb, 'prepare' ), $args );
	}

	/**
	 * Check if a given table exists.
	 *
	 * @param string $table Table name (with prefix).
	 *
	 * @return bool
	 */
	public function table_exists( string $table ): bool {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$exists = $this->wpdb->get_var( $sql );

		return $exists === $table;
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
