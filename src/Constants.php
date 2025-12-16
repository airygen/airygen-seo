<?php
/**
 * The constants for this plugin.
 * Main propose is to avoid typos and make it easier to check with intellisense.
 */

declare(strict_types=1);

namespace Airygen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Airygen SEO plugin constants.
 */
class Constants {

	// The settings option name.
	const SETTING_NAME = 'airygen_settings';

	// The settings group name.
	const SETTING_GROUP = 'airygen_settings_group';

	// Plugin slug.
	const PLUGIN_SLUG = 'airygen-seo';

	// Keys adopted by wp_postmeta.
	const META_POST_DATA                         = '_airygen_post_data';
	const META_TITLE                             = '_airygen_title';
	const META_DESCRIPTION                       = '_airygen_description';
	const META_FOCUS_KEYPHRASE                   = '_airygen_focus_keyphrase';
	const META_FOCUS_LONG_TAIL                   = '_airygen_focus_long_tail';
	const META_AGENT_PROMPT                      = '_airygen_agent_prompt';
	const META_SCORE_CACHE                       = '_airygen_score_cache';
	const META_CANONICAL                         = '_airygen_canonical';
	const META_ROBOTS                            = '_airygen_robots';
	const META_OUTPUT_MODES                      = '_airygen_output_modes';
	const META_SCHEMA_ARTICLE_TYPE               = '_airygen_schema_article_type';
	const META_KEYPHRASES_INDEXED_AT             = '_airygen_keyphrases_indexed_at';
	const META_INDEXNOW_INITIAL_SUBMIT           = '_airygen_indexnow_initial_submit';
	const META_TOC_MODE_LEGACY                   = '_airygen_toc_mode';
	const META_FAQ_MODE_LEGACY                   = '_airygen_faq_mode';
	const META_TOPIC_EXPANSION_MODE_LEGACY       = '_airygen_topic_expansion_mode';
	const META_SOCIAL_OG_TITLE_LEGACY            = '_airygen_social_og_title';
	const META_SOCIAL_OG_DESCRIPTION_LEGACY      = '_airygen_social_og_description';
	const META_SOCIAL_OG_IMAGE_URL_LEGACY        = '_airygen_social_og_image_url';
	const META_SOCIAL_TWITTER_TITLE_LEGACY       = '_airygen_social_twitter_title';
	const META_SOCIAL_TWITTER_DESCRIPTION_LEGACY = '_airygen_social_twitter_description';
	const META_SOCIAL_TWITTER_IMAGE_URL_LEGACY   = '_airygen_social_twitter_image_url';
	const META_SOCIAL_TWITTER_USE_OG_LEGACY      = '_airygen_social_twitter_use_og';
	const META_YOAST_MIGRATED                    = '_airygen_yoast_migrated';
	const META_YOAST_REDIRECT_MIGRATED           = '_airygen_yoast_redirect_migrated';
	const META_RANK_MATH_MIGRATED                = '_airygen_rank_math_migrated';
	const META_AIOSEO_MIGRATED                   = '_airygen_aioseo_migrated';
	const META_SEOPRESS_MIGRATED                 = '_airygen_seopress_migrated';
	const META_SEOPRESS_REDIRECT_MIGRATED        = '_airygen_seopress_redirect_migrated';

	// Canonical override token to suppress canonical output (stored in wp_postmeta).
	const CANONICAL_NONE_TOKEN = '__airygen_none__';

	// Keys adopted by wp_termmeta.
	const META_TERM_TITLE       = '_airygen_term_title';
	const META_TERM_DESCRIPTION = '_airygen_term_description';
	const META_TERM_CANONICAL   = '_airygen_term_canonical';
	const META_TERM_ROBOTS      = '_airygen_term_robots';
	const META_TERM_LASTMOD     = '_airygen_term_lastmod';
	const META_TERM_WC_ROBOTS   = '_airygen_wc_term_robots';

	// Keys adopted by wp_usermeta.
	const USER_META_SOCIAL_PROFILES = '_airygen_social_profiles';

	// Keys adopted by wp_options.
	const OPTION_MODULES                       = 'airygen_modules';
	const OPTION_MODULE_ORDER                  = 'airygen_module_order';
	const OPTION_PANEL_ORDER                   = 'airygen_panel_order';
	const OPTION_PANEL_VISIBILITY              = 'airygen_panel_visibility';
	const OPTION_DEBUG                         = 'airygen_debug';
	const OPTION_SITEWIDE_SEO_CACHE            = 'airygen_sitewide_seo_cache';
	const OPTION_AIOSEO_REDIRECT_CURSOR        = 'airygen_aioseo_redirect_cursor';
	const OPTION_RANK_MATH_REDIRECT_CURSOR     = 'airygen_rank_math_redirect_cursor';
	const OPTION_REDIRECT_LOG                  = 'airygen_redirects_log';
	const OPTION_NOTIFY_SETTINGS               = 'airygen_notify_settings';
	const OPTION_LLMS_TXT                      = 'airygen_llms_txt';
	const OPTION_MARKDOWN_FOR_AGENTS           = 'airygen_markdown_for_agents';
	const OPTION_BROKEN_LINK_CHECKER           = 'airygen_broken_link_checker';
	const OPTION_WIZARD_DISMISSED              = 'airygen_wizard_dismissed';
	const OPTION_SITE_VERIFICATION             = 'airygen_site_verification';
	const OPTION_SCHEMA                        = 'airygen_schema';
	const OPTION_INDEXNOW                      = 'airygen_indexnow';
	const OPTION_SITEMAP                       = 'airygen_sitemap';
	const OPTION_INDEXNOW_RESPONSES            = 'airygen_indexnow_responses';
	const OPTION_INDEXNOW_QUOTA                = 'airygen_indexnow_quota';
	const OPTION_CODE_SNIPPET_MANAGER          = 'airygen_code_snippet_manager';
	const OPTION_SCORE_CALCULATOR              = 'airygen_score_calculator';
	const OPTION_SCORE_RECALCULATE_STATE       = 'airygen_score_recalculate_state';
	const OPTION_SCORE_RECALCULATE_LAST_PREFIX = 'airygen_score_recalculate_last_';
	const OPTION_SCORE_RECALCULATE_DONE_PREFIX = 'airygen_score_recalculate_done_';
	const OPTION_IMAGE_SEO                     = 'airygen_image_seo';
	const OPTION_TOPIC_CLUSTER                 = 'airygen_topic_cluster';
	const OPTION_AUTHOR_SEO                    = 'airygen_author_seo';
	const OPTION_BREADCRUMBS                   = 'airygen_breadcrumbs';
	const OPTION_WOOCOMMERCE_SEO               = 'airygen_woocommerce_seo';
	const OPTION_ROBOTS                        = 'airygen_robots';
	const OPTION_LINK_SUGGESTIONS              = 'airygen_related_settings';
	const OPTION_LOCAL_SEO                     = 'airygen_local_seo';
	const OPTION_TOC                           = 'airygen_toc';
	const OPTION_TAXONOMY_SEO                  = 'airygen_taxonomy_seo';
	const OPTION_404_MANAGER_SETTINGS          = 'airygen_404_manager_settings';
	const OPTION_ONPAGE                        = 'airygen_onpage';
	const OPTION_SOCIAL                        = 'airygen_social';
	const OPTION_RSS_FEED_SIGNATURE            = 'airygen_rss_feed_signature';
	const OPTION_HREFLANG                      = 'airygen_hreflang';
	const OPTION_RELATED_POSTS                 = 'airygen_related_posts';
	const OPTION_CONTENT_BLOCK_ORDER           = 'airygen_content_block_order';
	const OPTION_UNINSTALL                     = 'airygen_uninstall';

	// Custom table storing individual link records.
	const TABLE_LINK_COUNTER_DATA = 'airygen_link_counter_data';

	// Custom table storing per-post link counts.
	const TABLE_LINK_COUNTER_META = 'airygen_link_counter_meta';

	// Custom table storing broken link checker logs.
	const TABLE_LINK_CHECKER_LOG = 'airygen_link_checker_log';

	// Custom table storing IndexNow submission events.
	const TABLE_INDEXNOW_EVENTS = 'airygen_indexnow_events';

	// Custom table storing keyphrase terms (stem/tf) per content.
	const TABLE_LINK_SUGGESTION_TERMS = 'airygen_link_terms';

	// Custom table storing document frequencies per stem.
	const TABLE_LINK_SUGGESTION_DF = 'airygen_link_terms_df';


	// Custom table storing topic cluster relationships.
	const TABLE_TOPIC_CLUSTER_RELATIONS = 'airygen_topic_cluster_relations';
	// Custom table storing topic cluster groups.
	const TABLE_TOPIC_CLUSTER_GROUPS = 'airygen_topic_cluster_group';
	// Custom table storing topic cluster candidate posts.
	const TABLE_TOPIC_CLUSTER_CANDIDATES = 'airygen_topic_cluster_candidates';
	// Custom table storing normalized 404 log rows.
	const TABLE_404_LOGS = 'airygen_404_logs';
	// Custom table storing redirect rules for 404 manager.
	const TABLE_404_REDIRECTS = 'airygen_redirects';
	// Custom table storing redirect mutation audit events.
	const TABLE_404_REDIRECT_EVENTS = 'airygen_redirect_events';
	// Custom table storing Notify digest logs.
	const TABLE_NOTIFY_LOGS = 'airygen_notify_logs';
	// Custom table storing generated markdown snapshots per post.
	const TABLE_MARKDOWN_POSTS = 'airygen_markdown_posts';

	// Filter/action hook names.
	const EXTENSION_API_VERSION                    = '1';
	const HOOK_ADMIN_BOOT_PAYLOAD                  = 'airygen_admin_boot_payload';
	const HOOK_ADMIN_PAGES                         = 'airygen_admin_pages';
	const HOOK_ADMIN_SETTINGS_PAYLOAD              = 'airygen_admin_settings_payload';
	const HOOK_ADMIN_SETTINGS_UPDATE               = 'airygen_admin_settings_update';
	const HOOK_BREADCRUMBS_ITEMS                   = 'airygen_breadcrumbs_items';
	const HOOK_BROKEN_LINK_CHECKER_CLEANUP         = 'airygen_broken_link_checker_cleanup';
	const HOOK_BROKEN_LINK_CHECKER_RUN             = 'airygen_broken_link_checker_run';
	const HOOK_EDITOR_CONFIG                       = 'airygen_editor_config';
	const HOOK_EDITOR_BUNDLE                       = 'airygen_editor_bundle';
	const HOOK_EDITOR_ASSETS                       = 'airygen_editor_assets';
	const HOOK_EXTERNAL_API_HOST                   = 'airygen_external_api_host';
	const HOOK_INDEXNOW_BACKFILL                   = 'airygen_indexnow_backfill';
	const HOOK_INDEXNOW_POST_TYPES                 = 'airygen_indexnow_post_types';
	const HOOK_INDEXNOW_PROCESS_QUEUE              = 'airygen_indexnow_process_queue';
	const HOOK_LINK_COUNTER_POST_TYPES             = 'airygen_link_counter_post_types';
	const HOOK_LINK_COUNTER_PROCESS_BACKLOG        = 'airygen_link_counter_process_backlog_async';
	const HOOK_LINK_SUGGESTIONS_KEYPHRASE_ERROR    = 'airygen_internal_links_keyphrase_error';
	const HOOK_LINK_SUGGESTIONS_RECOMPUTE_TF       = 'airygen_link_suggestions_recompute_term_frequency';
	const HOOK_LINK_SUGGESTIONS_RECOMPUTE_TF_ASYNC = 'airygen_link_suggestions_recompute_term_frequency_async';
	const HOOK_LINK_SUGGESTIONS_REINDEX_ALL        = 'airygen_link_suggestions_reindex_all';
	const HOOK_LINK_SUGGESTIONS_REINDEX_ALL_ASYNC  = 'airygen_link_suggestions_reindex_all_async';
	const HOOK_404_MANAGER_CLEANUP                 = 'airygen_404_manager_cleanup';
	const HOOK_NOTIFY_DAILY_DIGEST                 = 'airygen_notify_daily_digest';
	const HOOK_NOTIFY_LOG_CLEANUP                  = 'airygen_notify_log_cleanup';
	const HOOK_ROUTES                              = 'airygen_routes';
	const HOOK_SCORE_CONTEXT_DATA                  = 'airygen_score_context_data';
	const HOOK_SITE_HEALTH_SEARCH_CONSOLE_LINKED   = 'airygen_site_health_search_console_linked';
	const HOOK_SOCIAL_CARDS_POST_IMAGE             = 'airygen_social_cards_post_image';
	const HOOK_TAXONOMY_SEO_ASSETS                 = 'airygen_taxonomy_seo_assets';
	const HOOK_TAXONOMY_SEO_EDIT_FIELDS            = 'airygen_taxonomy_seo_edit_fields';
	const HOOK_TRANSFER_EXPORT_SETTINGS            = 'airygen_transfer_export_settings';
	const HOOK_TRANSFER_IMPORT_SETTINGS            = 'airygen_transfer_import_settings';
}
