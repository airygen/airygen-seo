<?php
/**
 * Persistence for keyphrase terms and document frequencies.
 *
 * @package Airygen\Modules\LinkSuggestions\Persistence
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Persistence;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;

use wpdb;

use function absint;
use function array_diff;
use function array_keys;
use function array_merge;
use function current_time;
use function implode;
use function max;
use function trim;

/**
 * Stores stem/tf and maintains DF counts.
 */
class LinkTermsRepository {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Database adapter instance.
	 *
	 * @var WpDbAdapter
	 */
	private $adapter;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null        $wpdb    Database connection.
	 * @param WpDbAdapter|null $adapter Adapter helper.
	 */
	public function __construct( ?wpdb $wpdb = null, ?WpDbAdapter $adapter = null ) {
		$this->wpdb    = $wpdb ?? $GLOBALS['wpdb']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->adapter = $adapter ?? new WpDbAdapter( $this->wpdb );
	}

	/**
	 * Persist terms for a content item and adjust DF counts.
	 *
	 * @param int                 $content_id   Post/term ID.
	 * @param string              $content_type Post type / taxonomy.
	 * @param array<string,float> $terms       stem => tf.
	 *
	 * @return void
	 */
	public function save_terms( int $content_id, string $content_type, array $terms ): void {
		$terms          = $this->sanitize_terms( $terms );
		$table_terms    = $this->adapter->table( Constants::TABLE_LINK_SUGGESTION_TERMS );
		$table_df       = $this->adapter->table( Constants::TABLE_LINK_SUGGESTION_DF );
		$existing_terms = $this->get_terms_for_content( $content_id, $content_type );

		$existing_stems = array_keys( $existing_terms );
		$new_stems      = array_keys( $terms );

		$to_remove = array_map(
			static function ( $stem ): string {
				return (string) $stem;
			},
			array_diff( $existing_stems, $new_stems )
		);
		$to_add    = array_map(
			static function ( $stem ): string {
				return (string) $stem;
			},
			array_diff( $new_stems, $existing_stems )
		);

		$now = current_time( 'mysql' );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.IncorrectNumberOfReplacements,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.NoValueFound
		// Delete removed stems.
		if ( ! empty( $to_remove ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $to_remove ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.IncorrectNumberOfReplacements,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- table name sanitized via adapter, placeholders covered.
			$sql = $this->wpdb->prepare(
				"DELETE FROM {$table_terms} WHERE content_id = %d AND content_type = %s AND stem IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and placeholder list are built from sanitized values.
				array_merge( array( $content_id, $content_type ), $to_remove )
			);

			$this->wpdb->query( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Query string comes from wpdb::prepare().
		}

		// Insert/replace current stems.
		foreach ( $terms as $stem => $tf ) {
			$stem = (string) $stem;
			$this->adapter->replace(
				$table_terms,
				array(
					'content_id'   => $content_id,
					'content_type' => $content_type,
					'stem'         => $stem,
					'tf'           => $tf,
					'updated_at'   => $now,
				),
				array( '%d', '%s', '%s', '%f', '%s' )
			);
		}

		// Update df counts.
		foreach ( $to_add as $stem ) {
			$this->increment_df( $table_df, (string) $stem, 1, $now );
		}

		foreach ( $to_remove as $stem ) {
			$this->increment_df( $table_df, (string) $stem, -1, $now );
		}
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.IncorrectNumberOfReplacements,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.NoValueFound
	}

	/**
	 * Delete all terms for a content item and adjust DF.
	 *
	 * @param int    $content_id   Post/term ID.
	 * @param string $content_type Post type / taxonomy.
	 *
	 * @return void
	 */
	public function purge_content( int $content_id, string $content_type ): void {
		$current = $this->get_terms_for_content( $content_id, $content_type );
		if ( empty( $current ) ) {
			return;
		}

		$table_terms = $this->adapter->table( Constants::TABLE_LINK_SUGGESTION_TERMS );
		$table_df    = $this->adapter->table( Constants::TABLE_LINK_SUGGESTION_DF );
		$now         = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name sanitized via adapter.
		$sql = $this->wpdb->prepare(
			"DELETE FROM {$table_terms} WHERE content_id = %d AND content_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via adapter.
			$content_id,
			$content_type
		);

		$this->wpdb->query( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Query string comes from wpdb::prepare().

		foreach ( array_keys( $current ) as $stem ) {
			$this->increment_df( $table_df, (string) $stem, -1, $now );
		}
	}

	/**
	 * Fetch terms for a content item.
	 *
	 * @param int    $content_id   Content ID.
	 * @param string $content_type Content type.
	 *
	 * @return array<string,float>
	 */
	public function get_terms_for_content( int $content_id, string $content_type ): array {
		$table = $this->adapter->table( Constants::TABLE_LINK_SUGGESTION_TERMS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name sanitized via adapter.
		$sql = $this->wpdb->prepare(
			"SELECT stem, tf FROM {$table} WHERE content_id = %d AND content_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via adapter.
			$content_id,
			$content_type
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Query string comes from wpdb::prepare().

		$result = array();
		foreach ( (array) $rows as $row ) {
			$result[ $row['stem'] ] = (float) $row['tf'];
		}

		return $result;
	}

	/**
	 * Get DF counts for the given stems.
	 *
	 * @param array<int,string> $stems Stems to fetch.
	 *
	 * @return array<string,int> stem => doc_count
	 */
	public function get_df_for_stems( array $stems ): array {
		if ( empty( $stems ) ) {
			return array();
		}

		$stems = $this->sanitize_strings( $stems );
		$table = $this->adapter->table( Constants::TABLE_LINK_SUGGESTION_DF );

		$placeholders = implode( ',', array_fill( 0, count( $stems ), '%s' ) );
		// phpcs:disable
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.PlaceholderCountMatches -- table name and placeholders handled above.
		$sql = $this->wpdb->prepare(
			"SELECT stem, doc_count FROM {$table} WHERE stem IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and placeholder list are built from sanitized values.
			$stems
		);
		// phpcs:enable

		$rows = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Query string comes from wpdb::prepare().

		$result = array();
		foreach ( (array) $rows as $row ) {
			$result[ $row['stem'] ] = (int) $row['doc_count'];
		}

		return $result;
	}

	/**
	 * Find candidate content IDs sharing at least one stem.
	 *
	 * @param array<int,string> $stems             Stems to match.
	 * @param array<int,string> $post_types        Allowed post types.
	 * @param array<int,string> $allowed_statuses  Allowed post statuses.
	 * @param int               $limit             Max IDs to return.
	 *
	 * @return array<int,int>
	 */
	public function find_candidate_ids_by_stems( array $stems, array $post_types, array $allowed_statuses, int $limit = 1000 ): array {
		$stems            = $this->sanitize_strings( $stems );
		$post_types       = $this->sanitize_strings( $post_types );
		$allowed_statuses = $this->sanitize_strings( $allowed_statuses );

		if ( empty( $stems ) || empty( $post_types ) ) {
			return array();
		}

		$terms_table = $this->adapter->table( Constants::TABLE_LINK_SUGGESTION_TERMS );
		$posts_table = $this->wpdb->posts;

		$stem_placeholders      = implode( ',', array_fill( 0, count( $stems ), '%s' ) );
		$post_type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$status_sql             = '';
		$params                 = array_merge( $stems, $post_types );

		if ( ! empty( $allowed_statuses ) ) {
			$status_placeholders = implode( ',', array_fill( 0, count( $allowed_statuses ), '%s' ) );
			$status_sql          = " AND p.post_status IN ({$status_placeholders})";
			$params              = array_merge( $params, $allowed_statuses );
		}

		$params[] = $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			"SELECT DISTINCT t.content_id
			FROM {$terms_table} AS t
			INNER JOIN {$posts_table} AS p ON p.ID = t.content_id
			WHERE t.stem IN ({$stem_placeholders})
			AND t.content_type IN ({$post_type_placeholders})
			{$status_sql}
			LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and placeholder fragments are built from trusted values.
			$params
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		$ids = $this->wpdb->get_col( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Query string comes from wpdb::prepare().

		return array_map(
			static function ( $id ): int {
				return (int) $id;
			},
			(array) $ids
		);
	}

	/**
	 * Total number of distinct content items with stored terms.
	 *
	 * @return int
	 */
	public function total_documents(): int {
		$table = $this->adapter->table( Constants::TABLE_LINK_SUGGESTION_TERMS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is pre-sanitized via adapter.
		$count = $this->wpdb->get_var( "SELECT COUNT(DISTINCT CONCAT(content_type, ':', content_id)) FROM {$table}" ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via adapter.

		return (int) $count;
	}

	/**
	 * Count indexed content for a post type with allowed statuses.
	 *
	 * @param string $post_type Post type.
	 * @param array  $allowed_statuses Allowed statuses.
	 *
	 * @return int
	 */
	public function count_indexed_by_type( string $post_type, array $allowed_statuses ): int {
		$terms_table = $this->adapter->table( Constants::TABLE_LINK_SUGGESTION_TERMS );
		$posts_table = $this->wpdb->posts;

		$status_sql = '';
		$params     = array( $post_type );

		if ( ! empty( $allowed_statuses ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $allowed_statuses ), '%s' ) );
			$status_sql   = " AND p.post_status IN ({$placeholders})";
			$params       = array_merge( $params, $allowed_statuses );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(DISTINCT t.content_id)
			FROM {$terms_table} AS t
			INNER JOIN {$posts_table} AS p ON p.ID = t.content_id
			WHERE t.content_type = %s
			{$status_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and placeholder fragments are built from trusted values.
			$params
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		return (int) $this->wpdb->get_var( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Query string comes from wpdb::prepare().
	}

	/**
	 * Ensure tf values are numeric floats.
	 *
	 * @param array<string,mixed> $terms raw terms.
	 *
	 * @return array<string,float>
	 */
	private function sanitize_terms( array $terms ): array {
		$sanitized = array();
		foreach ( $terms as $stem => $tf ) {
			$stem = (string) $stem;
			if ( '' === $stem ) {
				continue;
			}

			$sanitized[ $stem ] = (float) $tf;
		}

		return $sanitized;
	}

	/**
	 * Increment (or decrement) df count for a stem.
	 *
	 * @param string $table_df DF table name.
	 * @param string $stem     Stem.
	 * @param int    $delta    Increment (can be negative).
	 * @param string $now      Timestamp.
	 *
	 * @return void
	 */
	private function increment_df( string $table_df, string $stem, int $delta, string $now ): void {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$existing = $this->wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query string comes from wpdb::prepare().
			$this->wpdb->prepare(
				"SELECT doc_count FROM {$table_df} WHERE stem = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via adapter.
				$stem
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		$current = $existing ? absint( $existing['doc_count'] ) : 0;
		$new     = max( 0, $current + $delta );

		$this->adapter->replace(
			$table_df,
			array(
				'stem'       => $stem,
				'doc_count'  => $new,
				'updated_at' => $now,
			),
			array( '%s', '%d', '%s' )
		);
	}

	/**
	 * Sanitize an array of strings.
	 *
	 * @param array<int,mixed> $items Items to sanitize.
	 *
	 * @return array<int,string>
	 */
	private function sanitize_strings( array $items ): array {
		$sanitized = array();
		foreach ( $items as $item ) {
			$item = trim( (string) $item );
			if ( '' === $item ) {
				continue;
			}
			$sanitized[] = $item;
		}

		return array_values( array_unique( $sanitized ) );
	}
}
