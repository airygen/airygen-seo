<?php
/**
 * Adds link metrics to the WordPress post list table.
 *
 * @package Airygen\Modules\LinkCounter\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkCounter\Admin;

use Airygen\Constants;
use Airygen\Modules\LinkCounter\Domain\Storage;
use Airygen\Modules\LinkCounter\Runtime\PostTypes;
use Airygen\Support\Database\WpDbAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles list-table column registration and rendering.
 */
final class PostListColumns {

	private const COLUMN_KEY = 'airygen_link_counts';

	/**
	 * Storage helper.
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Cache of counts for the current request.
	 *
	 * @var array<int, array<string,int>>
	 */
	private $counts_cache = array();

	/**
	 * Cache of broken link counts for the current request.
	 *
	 * @var array<int, int>
	 */
	private $broken_counts_cache = array();

	/**
	 * Constructor.
	 *
	 * @param Storage $storage Storage helper.
	 */
	public function __construct( Storage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Register hooks for supported post types.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'setup_columns' ) );
	}

	/**
	 * Hook into list table columns for each supported post type.
	 *
	 * @return void
	 */
	public function setup_columns(): void {
		foreach ( PostTypes::names() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Insert the link column into the post list table.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function add_column( array $columns ): array {
		$new_columns = array();
		$inserted    = false;

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( ! $inserted && in_array( $key, array( 'title', 'name' ), true ) ) {
				$new_columns[ self::COLUMN_KEY ] = $this->get_column_label();
				$inserted                        = true;
			}
		}

		if ( ! $inserted ) {
			$new_columns[ self::COLUMN_KEY ] = $this->get_column_label();
		}

		return $new_columns;
	}

	/**
	 * Render the link counts for a row.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		if ( empty( $this->counts_cache ) ) {
			$this->warm_counts_cache();
		}

		$counts = $this->counts_cache[ $post_id ] ?? array(
			'internal_link_count' => 0,
			'external_link_count' => 0,
			'incoming_link_count' => 0,
		);
		if ( empty( $this->broken_counts_cache ) ) {
			$this->warm_broken_counts_cache();
		}

		$internal = (int) $counts['internal_link_count'];
		$external = (int) $counts['external_link_count'];
		$incoming = (int) $counts['incoming_link_count'];
		$broken   = (int) ( $this->broken_counts_cache[ $post_id ] ?? 0 );

		printf(
			'<span class="airygen-link-counts" style="display:inline-flex;align-items:center;gap:6px;">' .
			'<span class="airygen-link-counts__values" aria-label="%1$s">' .
			'<span class="airygen-link-counts__value airygen-link-counts__value--internal" style="color:#2563eb;font-weight:600;" title="%2$s">%3$s</span>' .
			'<span class="airygen-link-counts__divider" aria-hidden="true"> / </span>' .
			'<span class="airygen-link-counts__value airygen-link-counts__value--external" style="color:#f97316;font-weight:600;" title="%4$s">%5$s</span>' .
			'<span class="airygen-link-counts__divider" aria-hidden="true"> / </span>' .
			'<span class="airygen-link-counts__value airygen-link-counts__value--incoming" style="color:#16a34a;font-weight:600;" title="%6$s">%7$s</span>' .
			'<span class="airygen-link-counts__divider" aria-hidden="true"> / </span>' .
			'<span class="airygen-link-counts__value airygen-link-counts__value--broken" style="color:#111827;font-weight:600;" title="%8$s">%9$s</span>' .
			'</span>' .
			'</span>',
			esc_attr__( 'Link counts listed in order: internal, external, incoming internal, broken links.', 'airygen-seo' ),
			esc_attr__( 'Internal links', 'airygen-seo' ),
			esc_html( number_format_i18n( $internal ) ),
			esc_attr__( 'External links', 'airygen-seo' ),
			esc_html( number_format_i18n( $external ) ),
			esc_attr__( 'Incoming internal links', 'airygen-seo' ),
			esc_html( number_format_i18n( $incoming ) ),
			esc_attr__( 'Broken links', 'airygen-seo' ),
			esc_html( number_format_i18n( $broken ) )
		);
	}

	/**
	 * Column header label with tooltip.
	 *
	 * @return string
	 */
	private function get_column_label(): string {
		return sprintf(
			'%1$s <span class="dashicons dashicons-editor-help airygen-link-counts__tip" title="%2$s" aria-label="%2$s"></span>',
			esc_html__( 'Links', 'airygen-seo' ),
			esc_attr__( 'Internal (blue) / External (orange) / Incoming (green) / Broken (black) links', 'airygen-seo' )
		);
	}

	/**
	 * Populate cached counts for posts displayed in the current list.
	 *
	 * @return void
	 */
	private function warm_counts_cache(): void {
		global $wp_query;

		if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
			$post_ids = array_map( 'intval', wp_list_pluck( $wp_query->posts, 'ID' ) );
		} else {
			$post_ids = array();
		}

		if ( empty( $post_ids ) ) {
			return;
		}

		$this->counts_cache = $this->storage->get_counts_for_posts( $post_ids );
	}

	/**
	 * Populate broken-link counts for posts displayed in the current list.
	 *
	 * @return void
	 */
	private function warm_broken_counts_cache(): void {
		global $wp_query;

		if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
			$post_ids = array_map( 'intval', wp_list_pluck( $wp_query->posts, 'ID' ) );
		} else {
			$post_ids = array();
		}

		$post_ids = array_values( array_filter( $post_ids ) );
		if ( empty( $post_ids ) ) {
			return;
		}

		$adapter   = new WpDbAdapter();
		$log_table = $adapter->table( Constants::TABLE_LINK_CHECKER_LOG );
		if ( ! $adapter->table_exists( $log_table ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$params       = array_merge( $post_ids, array( 'client_error', 'server_error', 'error' ) );
		$rows         = $adapter->get_results(
			"SELECT post_id, COUNT(*) AS broken_count
			 FROM {$log_table}
			 WHERE post_id IN ({$placeholders})
			 AND (
			 	status_code = 0
			 	OR status_code >= 400
			 	OR status_label IN (%s, %s, %s)
			 )
			 GROUP BY post_id",
			$params,
			\ARRAY_A
		);

		foreach ( $rows as $row ) {
			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $post_id <= 0 ) {
				continue;
			}

			$this->broken_counts_cache[ $post_id ] = isset( $row['broken_count'] ) ? (int) $row['broken_count'] : 0;
		}
	}
}
