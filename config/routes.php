<?php
/**
 * Central REST route definitions using the Route DSL.
 *
 * @package Airygen\Config
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Support\Routing\Route;
use Airygen\Admin\RestController as AdminSettingsController;
use Airygen\Admin\DebugRestController;
use Airygen\Modules\BrokenLinkChecker\Admin\RestController as BrokenLinksController;
use Airygen\Modules\InstantIndexing\Admin\RestController as InstantIndexingController;
use Airygen\Modules\LinkCounter\Admin\RestController as LinkCounterController;
use Airygen\Modules\ScoreCalculator\Admin\RestController as ScoreController;
use Airygen\Modules\SitewideSeo\Admin\RestController as SiteHealthController;
use Airygen\Modules\SchemaMarkup\Admin\RestController as SchemaPreviewController;
use Airygen\Modules\LinkSuggestions\Admin\RestController as LinkSuggestionsController;
use Airygen\Modules\WooCommerceSeo\Admin\RestController as WooCommerceSchemaPreviewController;
use Airygen\Modules\TaxonomySeo\Admin\RestController as TaxonomyPreviewController;
use Airygen\Admin\Migration\YoastRestController as YoastMigrationController;
use Airygen\Admin\Migration\RankMathRestController as RankMathMigrationController;
use Airygen\Admin\Migration\AioseoRestController as AioseoMigrationController;
use Airygen\Admin\Migration\SeopressRestController as SeopressMigrationController;
use Airygen\Modules\TopicCluster\Admin\RestController as TopicClusterController;
use Airygen\Modules\NotFoundManager\Admin\RestController as NotFoundManagerController;
use Airygen\Modules\Notify\Admin\RestController as NotifyController;
use Airygen\Modules\MarkdownForAgents\Admin\RestController as MarkdownForAgentsController;
use Airygen\Modules\LlmsTxt\Admin\RestController as LlmsTxtController;
use Airygen\Admin\Wizard\RestController as WizardRestController;
use Airygen\Admin\TransferRestController;
use Airygen\Support\RequestRules\Admin\Settings\UpdateSettings as UpdateSettingsRule;
use Airygen\Support\RequestRules\BrokenLinkChecker\GetLogs as GetBrokenLinkLogsRules;
use Airygen\Support\RequestRules\ScoreCalculator\GetScore as GetScoreRule;
use Airygen\Support\RequestRules\RequestRule;

Route::group(
	static function ( Route $router ): void {
		$router
			->get( '/session-check', array( AdminSettingsController::class, 'handle_session_check' ) )
			->permission( array( AdminSettingsController::class, 'can_access_session_check' ) );

		$router->get( '/settings', array( AdminSettingsController::class, 'handle_get' ) );

		$router
			->post( '/wizard/dismiss', array( WizardRestController::class, 'handle_dismiss' ) )
			->permission( array( WizardRestController::class, 'can_manage' ) );

		$router
			->post( '/settings', array( AdminSettingsController::class, 'handle_update' ) )
			->args( RequestRule::revoke( UpdateSettingsRule::class ) );

		$router
			->get( '/404-manager/logs', array( NotFoundManagerController::class, 'handle_logs' ) )
			->permission( array( NotFoundManagerController::class, 'can_manage' ) );
		$router
			->get( '/404-manager/stats', array( NotFoundManagerController::class, 'handle_stats' ) )
			->permission( array( NotFoundManagerController::class, 'can_manage' ) );
		$router
			->get( '/404-manager/settings', array( NotFoundManagerController::class, 'handle_get_settings' ) )
			->permission( array( NotFoundManagerController::class, 'can_manage' ) );
		$router
			->put( '/404-manager/settings', array( NotFoundManagerController::class, 'handle_update_settings' ) )
			->permission( array( NotFoundManagerController::class, 'can_manage' ) );
		$router
			->post( '/404-manager/logs/(?P<id>\\d+)/resolve', array( NotFoundManagerController::class, 'handle_resolve_log' ) )
			->permission( array( NotFoundManagerController::class, 'can_manage' ) );
		$router
			->post( '/404-manager/logs/(?P<id>\\d+)/ignore', array( NotFoundManagerController::class, 'handle_ignore_log' ) )
			->permission( array( NotFoundManagerController::class, 'can_manage' ) );
		$router
			->delete( '/404-manager/logs/(?P<id>\\d+)', array( NotFoundManagerController::class, 'handle_delete_log' ) )
			->permission( array( NotFoundManagerController::class, 'can_manage' ) );

		$router
			->get( '/notify/settings', array( NotifyController::class, 'handle_get_settings' ) )
			->permission( array( NotifyController::class, 'can_manage' ) );
		$router
			->put( '/notify/settings', array( NotifyController::class, 'handle_update_settings' ) )
			->permission( array( NotifyController::class, 'can_manage' ) );
		$router
			->post( '/notify/test/(?P<channel>[a-zA-Z0-9_-]+)', array( NotifyController::class, 'handle_test_channel' ) )
			->permission( array( NotifyController::class, 'can_manage' ) );
		$router
			->get( '/notify/logs', array( NotifyController::class, 'handle_logs' ) )
			->permission( array( NotifyController::class, 'can_manage' ) );
		$router
			->post( '/notify/send-now', array( NotifyController::class, 'handle_send_now' ) )
			->permission( array( NotifyController::class, 'can_manage' ) );

		$router
			->get( '/markdown-for-agents/export', array( MarkdownForAgentsController::class, 'handle_export' ) )
			->permission( array( MarkdownForAgentsController::class, 'can_manage' ) );
		$router
			->get( '/markdown-for-agents/preview', array( MarkdownForAgentsController::class, 'handle_preview' ) )
			->permission( array( MarkdownForAgentsController::class, 'can_manage' ) );
		$router
			->post( '/markdown-for-agents/rebuild', array( MarkdownForAgentsController::class, 'handle_rebuild' ) )
			->permission( array( MarkdownForAgentsController::class, 'can_manage' ) );
		$router
			->post( '/llms-txt/preview', array( LlmsTxtController::class, 'handle_preview' ) )
			->permission( array( LlmsTxtController::class, 'can_manage' ) );
		$router
			->get( '/llms-txt/posts', array( LlmsTxtController::class, 'handle_posts' ) )
			->permission( array( LlmsTxtController::class, 'can_manage' ) );
		$router
			->post( '/llms-txt/clear-cache', array( LlmsTxtController::class, 'handle_clear_cache' ) )
			->permission( array( LlmsTxtController::class, 'can_manage' ) );

		$router
			->get( '/markdown-for-agents/records', array( MarkdownForAgentsController::class, 'handle_records' ) )
			->permission( array( MarkdownForAgentsController::class, 'can_manage' ) );

		$router
			->get( '/score', array( ScoreController::class, 'handle_get_score' ) )
			->args( RequestRule::revoke( GetScoreRule::class ) )
			->permission( array( ScoreController::class, 'can_view_score' ) );
		$router
			->post( '/score/recalculate', array( ScoreController::class, 'handle_recalculate' ) )
			->permission( array( ScoreController::class, 'can_manage' ) );
		$router
			->post( '/score/recalculate-step', array( ScoreController::class, 'handle_recalculate_step' ) )
			->permission( array( ScoreController::class, 'can_manage' ) );
		$router
			->get( '/score/recalculate-status', array( ScoreController::class, 'handle_recalculate_status' ) )
			->permission( array( ScoreController::class, 'can_manage' ) );

		$router
			->get( '/link-counter/status', array( LinkCounterController::class, 'handle_status' ) )
			->permission( array( LinkCounterController::class, 'can_manage' ) );
		$router
			->post( '/link-counter/recheck', array( LinkCounterController::class, 'handle_recheck' ) )
			->permission( array( LinkCounterController::class, 'can_manage' ) );

		$router
			->get( '/broken-links/logs', array( BrokenLinksController::class, 'handle_logs' ) )
			->permission( array( BrokenLinksController::class, 'can_manage' ) )
			->args( RequestRule::revoke( GetBrokenLinkLogsRules::class ) );

		$router
			->get( '/indexnow/status', array( InstantIndexingController::class, 'handle_status' ) )
			->permission( array( InstantIndexingController::class, 'can_manage' ) );
		$router
			->post( '/indexnow/manual', array( InstantIndexingController::class, 'handle_manual' ) )
			->permission( array( InstantIndexingController::class, 'can_manage_cloud' ) );
		$router
			->post( '/indexnow/backfill', array( InstantIndexingController::class, 'handle_backfill' ) )
			->permission( array( InstantIndexingController::class, 'can_manage_cloud' ) );
		$router
			->post( '/indexnow/rotate-key', array( InstantIndexingController::class, 'handle_rotate_key' ) )
			->permission( array( InstantIndexingController::class, 'can_manage_cloud' ) );

		$router
			->get( '/transfer/export', array( TransferRestController::class, 'handle_export' ) )
			->permission( array( TransferRestController::class, 'can_manage' ) );
		$router
			->post( '/transfer/import', array( TransferRestController::class, 'handle_import' ) )
			->permission( array( TransferRestController::class, 'can_manage' ) );
		$router
			->get( '/transfer/uninstall', array( TransferRestController::class, 'handle_get_uninstall' ) )
			->permission( array( TransferRestController::class, 'can_manage' ) );
		$router
			->post( '/transfer/uninstall', array( TransferRestController::class, 'handle_update_uninstall' ) )
			->permission( array( TransferRestController::class, 'can_manage' ) );

		$router->get( '/debug', array( DebugRestController::class, 'handle_get' ) );
		$router->post( '/debug/enable', array( DebugRestController::class, 'handle_enable' ) );
		$router->post( '/debug/disable', array( DebugRestController::class, 'handle_disable' ) );
		$router->post( '/debug/clear', array( DebugRestController::class, 'handle_clear' ) );
		$router->post( '/debug/editor-mode', array( DebugRestController::class, 'handle_editor_mode' ) );
		$router->post( '/debug/level', array( DebugRestController::class, 'handle_level' ) );
		$router->get( '/debug/logs', array( DebugRestController::class, 'handle_log_view' ) );

		$router
			->get( '/site-health', array( SiteHealthController::class, 'handle_get' ) )
			->permission( array( SiteHealthController::class, 'can_view' ) );

		$router
			->get( '/schema/preview', array( SchemaPreviewController::class, 'handle_preview' ) )
			->permission( array( SchemaPreviewController::class, 'can_preview' ) )
			->args(
				array(
					'post' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->get( '/woocommerce/schema-preview', array( WooCommerceSchemaPreviewController::class, 'handle_preview' ) )
			->permission( array( WooCommerceSchemaPreviewController::class, 'can_preview' ) )
			->args(
				array(
					'q'       => array(
						'required' => false,
					),
					'product' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->get( '/taxonomy/preview', array( TaxonomyPreviewController::class, 'handle_preview' ) )
			->permission( array( TaxonomyPreviewController::class, 'can_preview' ) )
			->args(
				array(
					'category' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'tag'      => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->get( '/migration/yoast', array( YoastMigrationController::class, 'handle_status' ) )
			->permission( array( YoastMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/yoast/import', array( YoastMigrationController::class, 'handle_import' ) )
			->permission( array( YoastMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/yoast/settings', array( YoastMigrationController::class, 'handle_import_settings' ) )
			->permission( array( YoastMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/yoast/redirects', array( YoastMigrationController::class, 'handle_import_redirects' ) )
			->permission( array( YoastMigrationController::class, 'can_manage' ) );

		$router
			->get( '/migration/yoast/redirects', array( YoastMigrationController::class, 'handle_redirects_status' ) )
			->permission( array( YoastMigrationController::class, 'can_manage' ) );

		$router
			->get( '/migration/rankmath', array( RankMathMigrationController::class, 'handle_status' ) )
			->permission( array( RankMathMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/rankmath/import', array( RankMathMigrationController::class, 'handle_import' ) )
			->permission( array( RankMathMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/rankmath/settings', array( RankMathMigrationController::class, 'handle_import_settings' ) )
			->permission( array( RankMathMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/rankmath/redirects', array( RankMathMigrationController::class, 'handle_import_redirects' ) )
			->permission( array( RankMathMigrationController::class, 'can_manage' ) );

		$router
			->get( '/migration/rankmath/redirects', array( RankMathMigrationController::class, 'handle_redirects_status' ) )
			->permission( array( RankMathMigrationController::class, 'can_manage' ) );

		$router
			->get( '/migration/aioseo', array( AioseoMigrationController::class, 'handle_status' ) )
			->permission( array( AioseoMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/aioseo/import', array( AioseoMigrationController::class, 'handle_import' ) )
			->permission( array( AioseoMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/aioseo/settings', array( AioseoMigrationController::class, 'handle_import_settings' ) )
			->permission( array( AioseoMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/aioseo/redirects', array( AioseoMigrationController::class, 'handle_import_redirects' ) )
			->permission( array( AioseoMigrationController::class, 'can_manage' ) );

		$router
			->get( '/migration/aioseo/redirects', array( AioseoMigrationController::class, 'handle_redirects_status' ) )
			->permission( array( AioseoMigrationController::class, 'can_manage' ) );

		$router
			->get( '/migration/seopress', array( SeopressMigrationController::class, 'handle_status' ) )
			->permission( array( SeopressMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/seopress/import', array( SeopressMigrationController::class, 'handle_import' ) )
			->permission( array( SeopressMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/seopress/settings', array( SeopressMigrationController::class, 'handle_import_settings' ) )
			->permission( array( SeopressMigrationController::class, 'can_manage' ) );

		$router
			->post( '/migration/seopress/redirects', array( SeopressMigrationController::class, 'handle_import_redirects' ) )
			->permission( array( SeopressMigrationController::class, 'can_manage' ) );

		$router
			->get( '/migration/seopress/redirects', array( SeopressMigrationController::class, 'handle_redirects_status' ) )
			->permission( array( SeopressMigrationController::class, 'can_manage' ) );

		$router
			->get( '/link-suggestions/settings', array( LinkSuggestionsController::class, 'handle_get_settings' ) )
			->permission( array( LinkSuggestionsController::class, 'can_manage' ) );

		$router
			->post( '/link-suggestions/settings', array( LinkSuggestionsController::class, 'handle_update_settings' ) )
			->permission( array( LinkSuggestionsController::class, 'can_manage' ) );

		$router
			->post( '/link-suggestions/reindex', array( LinkSuggestionsController::class, 'handle_reindex' ) )
			->permission( array( LinkSuggestionsController::class, 'can_manage' ) );

		$router
			->get( '/link-suggestions/suggestions', array( LinkSuggestionsController::class, 'handle_get_suggestions' ) )
			->permission( array( LinkSuggestionsController::class, 'can_edit' ) )
			->args(
				array(
					'post' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->get( '/topic-cluster/list', array( TopicClusterController::class, 'handle_list' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'post' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->post( '/topic-cluster/save', array( TopicClusterController::class, 'handle_save' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'post'           => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'level'          => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'parent_post_id' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'group_id'       => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->get( '/topic-cluster/summary', array( TopicClusterController::class, 'handle_summary' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'post' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->get( '/topic-cluster/mindmap', array( TopicClusterController::class, 'handle_mindmap' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'group_id' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->get( '/topic-cluster/groups', array( TopicClusterController::class, 'handle_groups' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'page'     => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->post( '/topic-cluster/groups', array( TopicClusterController::class, 'handle_create_group' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'name'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				)
			);

		$router
			->post( '/topic-cluster/groups/(?P<id>\\d+)', array( TopicClusterController::class, 'handle_update_group' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'id'          => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'name'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				)
			);

		$router
			->delete( '/topic-cluster/groups/(?P<id>\\d+)', array( TopicClusterController::class, 'handle_delete_group' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->post( '/topic-cluster/groups/(?P<id>\\d+)/map', array( TopicClusterController::class, 'handle_update_group_map' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'id'  => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'map' => array(
						'required' => false,
					),
				)
			);

		$router
			->post( '/topic-cluster/groups/(?P<id>\\d+)/mindmap-sync', array( TopicClusterController::class, 'handle_sync_group_mindmap' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'id'         => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'items'      => array(
						'required' => false,
					),
					'candidates' => array(
						'required' => false,
					),
					'map'        => array(
						'required' => false,
					),
				)
			);

		$router
			->get( '/topic-cluster/groups/(?P<id>\\d+)/candidates', array( TopicClusterController::class, 'handle_group_candidates' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->get( '/topic-cluster/groups/(?P<id>\\d+)/candidates/search', array( TopicClusterController::class, 'handle_search_candidates' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'q'  => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				)
			);

		$router
			->post( '/topic-cluster/groups/(?P<id>\\d+)/candidates', array( TopicClusterController::class, 'handle_add_candidate' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'id'      => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->delete( '/topic-cluster/groups/(?P<id>\\d+)/candidates/(?P<candidate_id>\\d+)', array( TopicClusterController::class, 'handle_delete_candidate' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'id'           => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'candidate_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				)
			);

		$router
			->post( '/topic-cluster/relate', array( TopicClusterController::class, 'handle_relate' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'post'           => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'parent_post_id' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'relation'       => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'source_post_id' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'target_post_id' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'source_handle'  => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'target_handle'  => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				)
			);

		$router
			->post( '/topic-cluster/unrelate', array( TopicClusterController::class, 'handle_unrelate' ) )
			->permission( array( TopicClusterController::class, 'can_manage' ) )
			->args(
				array(
					'relation'      => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'child_post_id' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'left_post_id'  => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'right_post_id' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				)
			);
	}
);
