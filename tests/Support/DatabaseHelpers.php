<?php
/**
 * Utilities for preparing custom database tables used in tests.
 *
 * @package AirygenTest\Support
 */

declare(strict_types=1);

namespace AirygenTest\Support;

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;

use function dbDelta;

/**
 * Ensures plugin specific tables exist for integration tests.
 */
final class DatabaseHelpers {

	/**
	 * Track whether tables have been created.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Ensure all custom tables exist for integration tests.
	 *
	 * @return void
	 */
	public static function ensure_custom_tables(): void {
		if ( self::$initialized ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$adapter                = new WpDbAdapter();
		$charset_collate        = $adapter->collate();
		$links_table            = $adapter->table( Constants::TABLE_LINK_COUNTER_DATA );
		$meta_table             = $adapter->table( Constants::TABLE_LINK_COUNTER_META );
		$log_table              = $adapter->table( Constants::TABLE_LINK_CHECKER_LOG );
		$index_table            = $adapter->table( Constants::TABLE_INDEXNOW_EVENTS );
		$terms_table            = $adapter->table( Constants::TABLE_LINK_SUGGESTION_TERMS );
		$df_table               = $adapter->table( Constants::TABLE_LINK_SUGGESTION_DF );
		$topic_relations_table  = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$topic_groups_table     = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );
		$topic_candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );
		$not_found_logs_table   = $adapter->table( Constants::TABLE_404_LOGS );
		$not_found_redirects    = $adapter->table( Constants::TABLE_404_REDIRECTS );
		$not_found_events       = $adapter->table( Constants::TABLE_404_REDIRECT_EVENTS );
		$markdown_posts_table   = $adapter->table( Constants::TABLE_MARKDOWN_POSTS );

		$links_sql = "CREATE TABLE $links_table (
			id bigint(20) unsigned NOT NULL auto_increment,
			url varchar(255) NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			target_post_id bigint(20) unsigned NOT NULL,
			type varchar(8) NOT NULL,
			status_check int(3) NOT NULL default 0,
			last_status_checked_at datetime NULL default NULL,
			PRIMARY KEY  (id),
			KEY link_direction (post_id, type),
			KEY target_post_id (target_post_id)
		) $charset_collate;";

		$meta_sql = "CREATE TABLE $meta_table (
			post_id bigint(20) unsigned NOT NULL,
			internal_link_count int(10) unsigned NULL default 0,
			external_link_count int(10) unsigned NULL default 0,
			incoming_link_count int(10) unsigned NULL default 0,
			status varchar(20) NOT NULL default 'pending',
			last_processed_at datetime NULL default NULL,
			updated_at datetime NULL default NULL,
			PRIMARY KEY  (post_id),
			KEY status (status)
		) $charset_collate;";

		$log_sql = "CREATE TABLE $log_table (
			link_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			url varchar(255) NOT NULL,
			status_code smallint(5) NULL default NULL,
			status_label varchar(50) NULL default NULL,
			error_message text NULL,
			data_source varchar(20) NOT NULL default 'http_request',
			checked_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (link_id),
			KEY url (url),
			KEY post_status (post_id, status_code),
			KEY checked_at (checked_at)
		) $charset_collate;";

		$index_sql = "CREATE TABLE $index_table (
			id bigint(20) unsigned NOT NULL auto_increment,
			host varchar(191) NOT NULL,
			url text NOT NULL,
			action varchar(20) NOT NULL default 'update',
			source varchar(20) NOT NULL default 'auto',
			status varchar(20) NOT NULL default 'pending',
			attempts smallint(5) unsigned NOT NULL default 0,
			last_error text NULL,
			last_response longtext NULL,
			available_at datetime NULL default NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY host (host),
			KEY available_at (available_at)
		) $charset_collate;";

		dbDelta( $links_sql );
		dbDelta( $meta_sql );
		dbDelta( $log_sql );
		dbDelta( $index_sql );
		dbDelta(
			"CREATE TABLE $terms_table (
				id bigint(20) unsigned NOT NULL auto_increment,
				content_id bigint(20) unsigned NOT NULL,
				content_type varchar(20) NOT NULL,
				stem varchar(191) NOT NULL,
				tf double NOT NULL default 0,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY content_stem (content_id, content_type, stem),
				KEY stem (stem),
				KEY content (content_id, content_type)
			) $charset_collate;"
		);

		dbDelta(
			"CREATE TABLE $df_table (
				stem varchar(191) NOT NULL,
				doc_count int(10) unsigned NOT NULL default 0,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (stem)
			) $charset_collate;"
		);
		dbDelta(
			"CREATE TABLE $topic_relations_table (
				id bigint(20) unsigned NOT NULL auto_increment,
				post_id bigint(20) unsigned NOT NULL,
				level tinyint(1) unsigned NOT NULL COMMENT '1=L1,2=L2,3=L3',
				parent_post_id bigint(20) unsigned NULL default NULL,
				prev_post_id bigint(20) unsigned NULL default NULL,
				next_post_id bigint(20) unsigned NULL default NULL,
				group_id bigint(20) unsigned NOT NULL,
				root_id bigint(20) unsigned NOT NULL default 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY post_id (post_id),
				KEY group_id (group_id)
			) $charset_collate;"
		);
		dbDelta(
			"CREATE TABLE $topic_groups_table (
				id bigint(20) unsigned NOT NULL auto_increment,
				name varchar(191) NOT NULL,
				description text NULL,
				map_json longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;"
		);
		dbDelta(
			"CREATE TABLE $topic_candidates_table (
				id bigint(20) unsigned NOT NULL auto_increment,
				group_id bigint(20) unsigned NOT NULL,
				post_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY group_id (group_id),
				KEY post_id (post_id)
			) $charset_collate;"
		);
		dbDelta(
			"CREATE TABLE $not_found_logs_table (
				id bigint(20) unsigned NOT NULL auto_increment,
				url_path varchar(1024) NOT NULL,
				query_hash varchar(64) NULL default NULL,
				hits bigint(20) unsigned NOT NULL default 1,
				first_seen_at datetime NOT NULL,
				last_seen_at datetime NOT NULL,
				last_referer text NULL,
				last_user_agent text NULL,
				status varchar(20) NOT NULL default 'open',
				matched_redirect_id bigint(20) unsigned NULL default NULL,
				PRIMARY KEY  (id),
				KEY url_path (url_path(191)),
				KEY last_seen_at (last_seen_at),
				KEY status (status)
			) $charset_collate;"
		);
		dbDelta(
			"CREATE TABLE $not_found_redirects (
				id bigint(20) unsigned NOT NULL auto_increment,
				source text NOT NULL,
				source_type varchar(20) NOT NULL default 'exact',
				target text NOT NULL,
				http_code smallint(5) unsigned NOT NULL default 301,
				enabled tinyint(1) NOT NULL default 1,
				reason varchar(256) NULL default NULL,
				hits bigint(20) unsigned NOT NULL default 0,
				last_hit_at datetime NULL default NULL,
				created_by bigint(20) unsigned NULL default NULL,
				updated_by bigint(20) unsigned NULL default NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY enabled_source_type (enabled, source_type),
				KEY updated_at (updated_at)
			) $charset_collate;"
		);
		dbDelta(
			"CREATE TABLE $not_found_events (
				id bigint(20) unsigned NOT NULL auto_increment,
				redirect_id bigint(20) unsigned NOT NULL,
				event_type varchar(50) NOT NULL,
				payload_json longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY redirect_id (redirect_id),
				KEY event_type (event_type),
				KEY created_at (created_at)
			) $charset_collate;"
		);
		dbDelta(
			"CREATE TABLE $markdown_posts_table (
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
			) $charset_collate;"
		);

		self::$initialized = true;
	}

	/**
	 * Truncate custom tables so each test starts from a clean state.
	 *
	 * @return void
	 */
	public static function truncate_custom_tables(): void {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . Constants::TABLE_LINK_COUNTER_DATA,
			$wpdb->prefix . Constants::TABLE_LINK_COUNTER_META,
			$wpdb->prefix . Constants::TABLE_LINK_CHECKER_LOG,
			$wpdb->prefix . Constants::TABLE_INDEXNOW_EVENTS,
			$wpdb->prefix . Constants::TABLE_LINK_SUGGESTION_TERMS,
			$wpdb->prefix . Constants::TABLE_LINK_SUGGESTION_DF,
			$wpdb->prefix . Constants::TABLE_TOPIC_CLUSTER_RELATIONS,
			$wpdb->prefix . Constants::TABLE_TOPIC_CLUSTER_GROUPS,
			$wpdb->prefix . Constants::TABLE_TOPIC_CLUSTER_CANDIDATES,
			$wpdb->prefix . Constants::TABLE_404_LOGS,
			$wpdb->prefix . Constants::TABLE_404_REDIRECTS,
			$wpdb->prefix . Constants::TABLE_404_REDIRECT_EVENTS,
			$wpdb->prefix . Constants::TABLE_MARKDOWN_POSTS,
		);

		foreach ( $tables as $table ) {
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
		}
	}
}
