<?php
/**
 * Handling plugin activation and deactivation.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Airygen\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\InstantIndexing\Runtime\Hooks as InstantIndexingHooks;
use Airygen\Modules\LinkCounter\Runtime\Hooks as LinkCounterHooks;
use Airygen\Modules\LlmsTxt\Public\Routes as LlmsTxtRoutes;
use Airygen\Support\Database\WpDbAdapter;

/**
 * Metabox controller.
 */
class Activation {

	/**
	 * Constructor.
	 *
	 * @param string $base The base path for the plugin's entry file.
	 */
	public function __construct( string $base ) {
		register_activation_hook( $base, array( $this, 'activate' ) );
		register_deactivation_hook( $base, array( $this, 'deactivate' ) );
		add_action( 'wpmu_new_blog', array( $this, 'handle_new_blog' ), 10, 1 );
	}

	/**
	 * Method triggered upon plugin activation.
	 *
	 * @param bool $network_wide Whether the plugin is activated network-wide.
	 * @return void
	 */
	public function activate( bool $network_wide ) {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			wp_die(
				sprintf(
					/* translators: 1: current PHP version, 2: required PHP version */
					esc_html__( 'Airygen SEO requires PHP %2$s or higher. Your current PHP version is %1$s. Please upgrade PHP and try again.', 'airygen-seo' ),
					PHP_VERSION,
					'8.1'
				),
				esc_html__( 'Plugin Activation Error', 'airygen-seo' ),
				array( 'back_link' => true )
			);
		}

		if ( is_multisite() && $network_wide ) {
			$sites = get_sites(
				array(
					'number' => 0,
					'fields' => 'ids',
				)
			);

			foreach ( $sites as $site_id ) {
				$site_id = (int) $site_id;
				if ( $site_id <= 0 ) {
					continue;
				}

				switch_to_blog( $site_id );
				$this->setup_site();
				restore_current_blog();
			}

			return;
		}

		$this->setup_site();
	}

	/**
	 * Method triggered upon plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivate() {
		LinkCounterHooks::clear_scheduled_backlog();
		InstantIndexingHooks::clear_scheduled_queue();
		flush_rewrite_rules();

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[admin] Plugin deactivated.' );
	}

	/**
	 * Handle network site creation.
	 *
	 * @param int $blog_id The new blog ID.
	 * @return void
	 */
	public function handle_new_blog( int $blog_id ): void {
		if ( ! is_multisite() ) {
			return;
		}

		$blog_id = (int) $blog_id;
		if ( $blog_id <= 0 ) {
			return;
		}

		switch_to_blog( $blog_id );
		$this->setup_site();
		restore_current_blog();
	}

	/**
	 * Set up tables/options and queues for the current site.
	 *
	 * @return void
	 */
	private function setup_site(): void {
		$current = get_option( Constants::SETTING_NAME, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		update_option( Constants::SETTING_NAME, $current, 'no' );

		$this->create_link_counter_tables();
		$this->create_indexnow_tables();
		$this->create_link_suggestion_tables();
		$this->create_topic_cluster_tables();
		$this->create_not_found_manager_tables();
		$this->create_notify_tables();
		$this->create_markdown_for_agents_tables();

		LlmsTxtRoutes::add_rewrite_rules();

		LinkCounterHooks::queue_backlog_processing();
		InstantIndexingHooks::queue_queue_processing();

		flush_rewrite_rules();

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[admin] Plugin activated.' );
	}

	/**
	 * Create or update the custom link counter tables.
	 *
	 * @return void
	 */
	private function create_link_counter_tables(): void {
		if ( ! $this->require_upgrade_file() ) {
			return;
		}

		$adapter         = new WpDbAdapter();
		$charset_collate = $adapter->collate();
		$links_table     = $adapter->table( Constants::TABLE_LINK_COUNTER_DATA );
		$meta_table      = $adapter->table( Constants::TABLE_LINK_COUNTER_META );
		$log_table       = $adapter->table( Constants::TABLE_LINK_CHECKER_LOG );

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
			data_source varchar(20) NOT NULL default 'http_request' COMMENT 'db_cache, loop_cache, http_request',
			checked_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (link_id),
			KEY url (url),
			KEY post_status (post_id, status_code),
			KEY checked_at (checked_at)
		) $charset_collate;";

		dbDelta( $links_sql );
		dbDelta( $meta_sql );
		dbDelta( $log_sql );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[admin] Link counter tables created or updated.' );
	}

	/**
	 * Create or update the IndexNow events table.
	 *
	 * @return void
	 */
	private function create_indexnow_tables(): void {
		if ( ! $this->require_upgrade_file() ) {
			return;
		}

		$adapter         = new WpDbAdapter();
		$charset_collate = $adapter->collate();
		$events_table    = $adapter->table( Constants::TABLE_INDEXNOW_EVENTS );

		$events_sql = "CREATE TABLE $events_table (
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

		dbDelta( $events_sql );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[admin] IndexNow tables created or updated.' );
	}

	/**
	 * Create or update link suggestion tables (terms and df).
	 *
	 * @return void
	 */
	private function create_link_suggestion_tables(): void {
		if ( ! $this->require_upgrade_file() ) {
			return;
		}

		$adapter         = new WpDbAdapter();
		$charset_collate = $adapter->collate();

		$terms_table = $adapter->table( Constants::TABLE_LINK_SUGGESTION_TERMS );
		$df_table    = $adapter->table( Constants::TABLE_LINK_SUGGESTION_DF );

		$terms_sql = "CREATE TABLE $terms_table (
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
		) $charset_collate;";

		$df_sql = "CREATE TABLE $df_table (
			stem varchar(191) NOT NULL,
			doc_count int(10) unsigned NOT NULL default 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (stem)
		) $charset_collate;";

		dbDelta( $terms_sql );
		dbDelta( $df_sql );
	}

	/**
	 * Create or update the topic cluster tables.
	 *
	 * @return void
	 */
	private function create_topic_cluster_tables(): void {
		if ( ! $this->require_upgrade_file() ) {
			return;
		}

		$adapter          = new WpDbAdapter();
		$charset_collate  = $adapter->collate();
		$relations_table  = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$groups_table     = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_GROUPS );
		$candidates_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_CANDIDATES );

		$relations_sql = "CREATE TABLE $relations_table (
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
		) $charset_collate;";

		$groups_sql = "CREATE TABLE $groups_table (
			id bigint(20) unsigned NOT NULL auto_increment,
			name varchar(191) NOT NULL,
			description text NULL,
			map_json longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$candidates_sql = "CREATE TABLE $candidates_table (
			id bigint(20) unsigned NOT NULL auto_increment,
			group_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY group_id (group_id),
			KEY post_id (post_id)
		) $charset_collate;";

		dbDelta( $relations_sql );
		dbDelta( $groups_sql );
		dbDelta( $candidates_sql );
	}

	/**
	 * Create or update 404 manager tables.
	 *
	 * @return void
	 */
	private function create_not_found_manager_tables(): void {
		if ( ! $this->require_upgrade_file() ) {
			return;
		}

		$adapter               = new WpDbAdapter();
		$charset_collate       = $adapter->collate();
		$logs_table            = $adapter->table( Constants::TABLE_404_LOGS );
		$redirects_table       = $adapter->table( Constants::TABLE_404_REDIRECTS );
		$redirect_events_table = $adapter->table( Constants::TABLE_404_REDIRECT_EVENTS );

		$logs_sql = "CREATE TABLE $logs_table (
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
		) $charset_collate;";

		$redirects_sql = "CREATE TABLE $redirects_table (
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
		) $charset_collate;";

		$events_sql = "CREATE TABLE $redirect_events_table (
			id bigint(20) unsigned NOT NULL auto_increment,
			redirect_id bigint(20) unsigned NOT NULL,
			event_type varchar(50) NOT NULL,
			payload_json longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY redirect_id (redirect_id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $logs_sql );
		dbDelta( $redirects_sql );
		dbDelta( $events_sql );
	}

	/**
	 * Create or update Notify tables.
	 *
	 * @return void
	 */
	private function create_notify_tables(): void {
		if ( ! $this->require_upgrade_file() ) {
			return;
		}

		$adapter         = new WpDbAdapter();
		$charset_collate = $adapter->collate();
		$logs_table      = $adapter->table( Constants::TABLE_NOTIFY_LOGS );

		$logs_sql = "CREATE TABLE $logs_table (
			id bigint(20) unsigned NOT NULL auto_increment,
			run_at datetime NOT NULL,
			results_json longtext NULL,
			PRIMARY KEY  (id),
			KEY run_at (run_at)
		) $charset_collate;";

		dbDelta( $logs_sql );
	}

	/**
	 * Create or update Markdown for Agents tables.
	 *
	 * @return void
	 */
	private function create_markdown_for_agents_tables(): void {
		if ( ! $this->require_upgrade_file() ) {
			return;
		}

		$adapter         = new WpDbAdapter();
		$charset_collate = $adapter->collate();
		$table           = $adapter->table( Constants::TABLE_MARKDOWN_POSTS );

		$sql = "CREATE TABLE $table (
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
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Require the WordPress upgrade helpers when available.
	 *
	 * @return bool
	 */
	private function require_upgrade_file(): bool {
		if ( function_exists( 'dbDelta' ) ) {
			return true;
		}

		// `wp-admin/includes/upgrade.php` is the canonical location of dbDelta()
		// in WordPress core; this is the path documented by the Plugin Handbook
		// for plugins that need dbDelta during activation.
		// @phpstan-ignore-next-line requireOnce.fileNotFound -- Path resolved at runtime via ABSPATH.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		return function_exists( 'dbDelta' );
	}
}
