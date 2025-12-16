<?php
/**
 * Stores Redirects configuration.
 *
 * @package Airygen\Modules\Redirects\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Redirects\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;

/**
 * Redirect rules storage adapter.
 */
final class Settings {

	private const OPTION_LOG = Constants::OPTION_REDIRECT_LOG;

	/**
	 * Ensure rules option exists.
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_LOG, false ) ) {
			add_option( self::OPTION_LOG, array(), '', 'no' );
		}
	}

	/**
	 * Retrieve sanitized rules.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_rules(): array {
		$db    = new WpDbAdapter();
		$table = $db->table( Constants::TABLE_404_REDIRECTS );
		if ( ! $db->table_exists( $table ) ) {
			return self::default_rules();
		}

		$rows = $GLOBALS['wpdb']->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT id, source, source_type, target, http_code, enabled, reason FROM {$table} ORDER BY updated_at DESC, id DESC",
			\ARRAY_A
		);

		$rules = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$rules[] = array(
				'id'      => (string) ( $row['id'] ?? '' ),
				'type'    => self::sanitize_type( $row['source_type'] ?? 'exact' ),
				'source'  => self::sanitize_source_by_type( $row['source'] ?? '', $row['source_type'] ?? 'exact' ),
				'target'  => self::sanitize_target( $row['target'] ?? '' ),
				'status'  => self::sanitize_status( $row['http_code'] ?? 301 ),
				'enabled' => ! empty( $row['enabled'] ),
				'note'    => isset( $row['reason'] ) ? sanitize_text_field( (string) $row['reason'] ) : '',
			);
		}

		return array( 'rules' => $rules );
	}

	/**
	 * Persist rules.
	 *
	 * @param array<string, mixed> $value Raw value.
	 */
	public static function update_rules( array $value ): void {
		$sanitized = self::sanitize_rules( $value );
		$db        = new WpDbAdapter();
		$table     = $db->table( Constants::TABLE_404_REDIRECTS );

		if ( ! $db->table_exists( $table ) ) {
			return;
		}

		self::sync_table_rules( $sanitized['rules'] );
	}

	/**
	 * Retrieve 404 log data.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function get_log(): array {
		$log = get_option( self::OPTION_LOG, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Overwrite log storage.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $log Log data.
	 *
	 * @return void
	 */
	public static function update_log( array $log ): void {
		update_option( self::OPTION_LOG, $log, 'no' );
	}

	/**
	 * Default rules payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_rules(): array {
		return array(
			'rules' => array(),
		);
	}

	/**
	 * Sanitize rules payload.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize_rules( $value ): array {
		if ( ! is_array( $value ) ) {
			return self::default_rules();
		}

		$rules = array();
		if ( isset( $value['rules'] ) && is_array( $value['rules'] ) ) {
			foreach ( $value['rules'] as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}

				$rules[] = array(
					'id'      => isset( $rule['id'] ) && is_string( $rule['id'] ) ? $rule['id'] : wp_generate_uuid4(),
					'type'    => self::sanitize_type( $rule['type'] ?? 'exact' ),
					'source'  => self::sanitize_source_by_type( $rule['source'] ?? '', $rule['type'] ?? 'exact' ),
					'target'  => self::sanitize_target( $rule['target'] ?? '' ),
					'status'  => self::sanitize_status( $rule['status'] ?? 301 ),
					'enabled' => isset( $rule['enabled'] ) ? (bool) $rule['enabled'] : true,
					'note'    => isset( $rule['note'] ) ? sanitize_text_field( (string) $rule['note'] ) : '',
				);
			}
		}

		return array(
			'rules' => $rules,
		);
	}

	/**
	 * Sanitize redirect type value.
	 *
	 * @param mixed $type Raw redirect type.
	 *
	 * @return string
	 */
	private static function sanitize_type( $type ): string {
		$allowed = array( 'exact', 'wildcard', 'regex' );
		$type    = in_array( $type, $allowed, true ) ? $type : 'exact';
		return $type;
	}

	/**
	 * Sanitize source path/pattern.
	 *
	 * @param mixed $path Raw path or pattern.
	 *
	 * @return string
	 */
	private static function sanitize_path( $path ): string {
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return '';
		}

		return '/' === $path[0] ? $path : '/' . $path;
	}

	/**
	 * Sanitize source based on match type.
	 *
	 * @param mixed $source Raw source.
	 * @param mixed $type Raw type.
	 * @return string
	 */
	private static function sanitize_source_by_type( $source, $type ): string {
		$resolved_type = self::sanitize_type( $type );
		$source        = trim( (string) $source );

		if ( '' === $source ) {
			return '';
		}

		if ( 'regex' === $resolved_type ) {
			return $source;
		}

		return self::sanitize_path( $source );
	}

	/**
	 * Sanitize target URL/path.
	 *
	 * @param mixed $target Raw target value.
	 *
	 * @return string
	 */
	private static function sanitize_target( $target ): string {
		$target = trim( (string) $target );
		if ( '' === $target ) {
			return '';
		}

		if ( 0 === strpos( $target, 'http://' ) || 0 === strpos( $target, 'https://' ) ) {
			return esc_url_raw( $target );
		}

		return '/' === $target[0] ? $target : '/' . $target;
	}

	/**
	 * Sanitize HTTP status.
	 *
	 * @param mixed $status Raw status code.
	 *
	 * @return int
	 */
	private static function sanitize_status( $status ): int {
		$status  = (int) $status;
		$allowed = array( 301, 302, 307, 308 );

		return in_array( $status, $allowed, true ) ? $status : 301;
	}

	/**
	 * Replace table rules with the provided list.
	 *
	 * @param array<int, array<string, mixed>> $rules Sanitized rules.
	 * @return void
	 */
	private static function sync_table_rules( array $rules ): void {
		$db    = new WpDbAdapter();
		$table = $db->table( Constants::TABLE_404_REDIRECTS );
		$now   = current_time( 'mysql' );
		$user  = (int) get_current_user_id();

		$existing_rows = $GLOBALS['wpdb']->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT id FROM {$table}",
			\ARRAY_A
		);
		$existing_ids  = array();
		foreach ( is_array( $existing_rows ) ? $existing_rows : array() as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) ) {
				$existing_ids[] = (int) $row['id'];
			}
		}

		$keep_ids = array();
		foreach ( $rules as $rule ) {
			$rule_id = isset( $rule['id'] ) ? (string) $rule['id'] : '';
			$id      = ctype_digit( $rule_id ) ? (int) $rule_id : 0;

			$data = array(
				'source'      => (string) ( $rule['source'] ?? '' ),
				'source_type' => self::sanitize_type( $rule['type'] ?? 'exact' ),
				'target'      => (string) ( $rule['target'] ?? '' ),
				'http_code'   => self::sanitize_status( $rule['status'] ?? 301 ),
				'enabled'     => ! empty( $rule['enabled'] ) ? 1 : 0,
				'reason'      => isset( $rule['note'] ) ? sanitize_text_field( (string) $rule['note'] ) : '',
				'updated_by'  => $user > 0 ? $user : null,
				'updated_at'  => $now,
			);

			if ( $id > 0 && in_array( $id, $existing_ids, true ) ) {
				$db->query(
					"UPDATE {$table} SET source = %s, source_type = %s, target = %s, http_code = %d, enabled = %d, reason = %s, updated_by = %d, updated_at = %s WHERE id = %d",
					array(
						$data['source'],
						$data['source_type'],
						$data['target'],
						$data['http_code'],
						$data['enabled'],
						$data['reason'],
						(int) $data['updated_by'],
						$data['updated_at'],
						$id,
					)
				);
				$keep_ids[] = $id;
				continue;
			}

			$db->insert(
				$table,
				array(
					'source'      => $data['source'],
					'source_type' => $data['source_type'],
					'target'      => $data['target'],
					'http_code'   => $data['http_code'],
					'enabled'     => $data['enabled'],
					'reason'      => $data['reason'],
					'hits'        => 0,
					'last_hit_at' => null,
					'created_by'  => $user > 0 ? $user : null,
					'updated_by'  => $user > 0 ? $user : null,
					'created_at'  => $now,
					'updated_at'  => $now,
				),
				array(
					'%s',
					'%s',
					'%s',
					'%d',
					'%d',
					'%s',
					'%d',
					'%s',
					'%d',
					'%d',
					'%s',
					'%s',
				)
			);
			$new_id = (int) $GLOBALS['wpdb']->insert_id; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $new_id > 0 ) {
				$keep_ids[] = $new_id;
			}
		}

		if ( empty( $keep_ids ) ) {
			$db->query( "DELETE FROM {$table}" );
			return;
		}

		$keep_ids = array_values( array_unique( array_map( 'intval', $keep_ids ) ) );
		$ids_sql  = implode( ',', $keep_ids );
		$db->query( "DELETE FROM {$table} WHERE id NOT IN ({$ids_sql})" );
	}
}
