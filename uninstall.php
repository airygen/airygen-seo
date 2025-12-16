<?php
/**
 * Uninstall plugin.
 *
 * Runs when the plugin is deleted (not just deactivated) from the WordPress admin.
 * Respects the cleanup preferences stored in the airygen_uninstall option.
 * Supports both single-site and multisite (network) installations.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || die;

global $wpdb;

// On multisite the preferences are stored as a network option so a single
// set of choices covers the whole network.
$airygen_raw   = is_multisite() ? get_site_option( 'airygen_uninstall', array() ) : get_option( 'airygen_uninstall', array() );
$airygen_prefs = is_array( $airygen_raw ) ? $airygen_raw : array();

$airygen_clear_tables  = ! empty( $airygen_prefs['clearTables'] );
$airygen_clear_options = ! empty( $airygen_prefs['clearOptions'] );
$airygen_clear_meta    = ! empty( $airygen_prefs['clearMeta'] );

/**
 * Drop all custom Airygen tables for a given $wpdb prefix.
 *
 * @param string $prefix Table prefix (e.g. "wp_" or "wp_2_").
 * @return void
 */
$airygen_drop_tables_for_prefix = static function ( string $prefix ) use ( $wpdb ): void {
	$tables = array(
		$prefix . 'airygen_link_counter_data',
		$prefix . 'airygen_link_counter_meta',
		$prefix . 'airygen_link_checker_log',
		$prefix . 'airygen_indexnow_events',
		$prefix . 'airygen_link_terms',
		$prefix . 'airygen_link_terms_df',
		$prefix . 'airygen_ctr_bootser',
		$prefix . 'airygen_content_blueprints',
		$prefix . 'airygen_article_builders',
		$prefix . 'airygen_faq_expansions',
		$prefix . 'airygen_topic_expansions',
		$prefix . 'airygen_topic_cluster_relations',
		$prefix . 'airygen_topic_cluster_group',
		$prefix . 'airygen_topic_cluster_candidates',
		$prefix . 'airygen_404_logs',
		$prefix . 'airygen_redirects',
		$prefix . 'airygen_redirect_events',
		$prefix . 'airygen_notify_logs',
		$prefix . 'airygen_markdown_posts',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
};

/**
 * Clean up options / postmeta / termmeta for the currently active blog.
 *
 * @return void
 */
$airygen_cleanup_current_blog = static function () use ( $wpdb, $airygen_clear_tables, $airygen_clear_options, $airygen_clear_meta, $airygen_drop_tables_for_prefix ): void {
	// ── 1. Drop custom tables ─────────────────────────────────────────────────
	if ( $airygen_clear_tables ) {
		$airygen_drop_tables_for_prefix( $wpdb->prefix );
	}

	// ── 2. Delete wp_options entries ──────────────────────────────────────────
	if ( $airygen_clear_options ) {
		// // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'airygen\_%',
				'airygen-seo%'
			)
		);
	}

	// ── 3. Delete postmeta / termmeta ─────────────────────────────────────────
	if ( $airygen_clear_meta ) {
		// // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
				'\_airygen\_%'
			)
		);

		// // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
				'\_airygen\_%'
			)
		);
	}
};

if ( is_multisite() ) {
	// Iterate every blog in the network.
	$airygen_blog_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,   // no limit.
		)
	);

	// @phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	foreach ( $airygen_blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		$airygen_cleanup_current_blog();
		restore_current_blog();
	}

	// wp_usermeta is shared across the entire network – clean it once.
	if ( $airygen_clear_meta ) {
		// // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
				'\_airygen\_%'
			)
		);
	}

	// Remove the network-level preferences option itself.
	if ( $airygen_clear_options ) {
		delete_site_option( 'airygen_uninstall' );
	}
} else {
	// Single-site installation.
	$airygen_cleanup_current_blog();

	if ( $airygen_clear_meta ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
				'\_airygen\_%'
			)
		);
	}
}
