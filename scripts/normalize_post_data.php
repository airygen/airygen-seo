<?php
/**
 * Normalize legacy per-post SEO meta into _airygen_post_data for all sites.
 *
 * Usage:
 *   wp eval-file scripts/normalize_post_data.php --allow-root
 *
 * @package Airygen
 */

use Airygen\Constants;
use Airygen\Support\Meta\OutputModes;
use Airygen\Support\Meta\PostData;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( ! class_exists( 'Airygen\\Support\\Meta\\PostData' ) ) {
	WP_CLI::error( 'Airygen plugin is not loaded.' );
}

$site_ids = array( get_current_blog_id() );

if ( is_multisite() ) {
	$site_ids = array_map(
		static function ( WP_Site $site ): int {
			return (int) $site->blog_id;
		},
		get_sites(
			array(
				'number' => 0,
				'fields' => 'all',
			)
		)
	);
}

$legacy_keys = array(
	Constants::META_TITLE,
	Constants::META_DESCRIPTION,
	Constants::META_FOCUS_KEYPHRASE,
	Constants::META_FOCUS_LONG_TAIL,
	Constants::META_AGENT_PROMPT,
	Constants::META_CANONICAL,
	Constants::META_ROBOTS,
	Constants::META_SCHEMA_ARTICLE_TYPE,
);

$legacy_mode_keys = array(
	Constants::META_TOC_MODE_LEGACY,
	Constants::META_FAQ_MODE_LEGACY,
	Constants::META_TOPIC_EXPANSION_MODE_LEGACY,
);

$legacy_cleanup_keys = array(
	Constants::META_SOCIAL_OG_TITLE_LEGACY,
	Constants::META_SOCIAL_OG_DESCRIPTION_LEGACY,
	Constants::META_SOCIAL_OG_IMAGE_URL_LEGACY,
	Constants::META_SOCIAL_TWITTER_TITLE_LEGACY,
	Constants::META_SOCIAL_TWITTER_DESCRIPTION_LEGACY,
	Constants::META_SOCIAL_TWITTER_IMAGE_URL_LEGACY,
	Constants::META_SOCIAL_TWITTER_USE_OG_LEGACY,
);

$summary = array(
	'sites'    => count( $site_ids ),
	'posts'    => 0,
	'updated'  => 0,
	'cleaned'  => 0,
	'skipped'  => 0,
);

foreach ( $site_ids as $site_id ) {
	switch_to_blog( $site_id );

	global $wpdb;

	$tracked_keys  = array_merge( $legacy_keys, $legacy_mode_keys, $legacy_cleanup_keys );
	$placeholders  = implode( ',', array_fill( 0, count( $tracked_keys ), '%s' ) );
	$post_ids     = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off normalization script.
		$wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders)",
			$tracked_keys
		)
	);

	foreach ( $post_ids as $post_id ) {
		++$summary['posts'];

		$before = get_post_meta( (int) $post_id, Constants::META_POST_DATA, true );
		$after  = PostData::get( (int) $post_id );
		$json   = PostData::sanitize_meta_value( $after );

		if ( $before !== $json ) {
			PostData::save( (int) $post_id, $after );
			++$summary['updated'];
		} else {
			++$summary['skipped'];
		}

		$legacy_modes = array(
			'toc'            => (string) get_post_meta( (int) $post_id, Constants::META_TOC_MODE_LEGACY, true ),
			'faq'            => (string) get_post_meta( (int) $post_id, Constants::META_FAQ_MODE_LEGACY, true ),
			'topicExpansion' => (string) get_post_meta( (int) $post_id, Constants::META_TOPIC_EXPANSION_MODE_LEGACY, true ),
		);
		$has_legacy_modes = false;
		foreach ( $legacy_modes as $legacy_mode ) {
			if ( '' !== $legacy_mode ) {
				$has_legacy_modes = true;
				break;
			}
		}

		if ( $has_legacy_modes ) {
			$current_modes = OutputModes::get( (int) $post_id );
			foreach ( $legacy_modes as $key => $mode ) {
				if ( '' === $current_modes[ $key ] || 'auto' === $current_modes[ $key ] ) {
					$current_modes[ $key ] = $mode;
				}
			}
			OutputModes::save( (int) $post_id, $current_modes );
		}

		foreach ( $legacy_keys as $legacy_key ) {
			$deleted = delete_post_meta( (int) $post_id, $legacy_key );
			if ( $deleted ) {
				++$summary['cleaned'];
			}
		}

		foreach ( $legacy_mode_keys as $legacy_key ) {
			$deleted = delete_post_meta( (int) $post_id, $legacy_key );
			if ( $deleted ) {
				++$summary['cleaned'];
			}
		}

		foreach ( $legacy_cleanup_keys as $legacy_key ) {
			$deleted = delete_post_meta( (int) $post_id, $legacy_key );
			if ( $deleted ) {
				++$summary['cleaned'];
			}
		}
	}

	restore_current_blog();
}

WP_CLI::success(
	sprintf(
		'Normalized %1$d posts across %2$d sites. Updated %3$d rows, removed %4$d legacy meta entries, skipped %5$d unchanged rows.',
		$summary['posts'],
		$summary['sites'],
		$summary['updated'],
		$summary['cleaned'],
		$summary['skipped']
	)
);
