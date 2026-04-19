<?php
/**
 * Regression tests for the centralized route map.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

/**
 * @coversNothing
 */
final class RoutesConfigTest extends RestRouteTestCase {

	/**
	 * Ensure every route defined in config/routes.php is registered.
	 *
	 * @return void
	 */
	public function test_all_routes_are_registered(): void {
		$routes = $this->server->get_routes();

		$expected = array(
			'/airygen/v1/session-check',
			'/airygen/v1/settings',
			'/airygen/v1/404-manager/logs',
			'/airygen/v1/404-manager/stats',
			'/airygen/v1/404-manager/settings',
			'/airygen/v1/404-manager/logs/(?P<id>\\d+)/resolve',
			'/airygen/v1/404-manager/logs/(?P<id>\\d+)/ignore',
			'/airygen/v1/404-manager/logs/(?P<id>\\d+)',
			'/airygen/v1/notify/settings',
			'/airygen/v1/notify/test/(?P<channel>[a-zA-Z0-9_-]+)',
			'/airygen/v1/notify/logs',
			'/airygen/v1/notify/send-now',
			'/airygen/v1/markdown-for-agents/export',
			'/airygen/v1/markdown-for-agents/preview',
			'/airygen/v1/markdown-for-agents/rebuild',
			'/airygen/v1/markdown-for-agents/records',
			'/airygen/v1/llms-txt/preview',
			'/airygen/v1/llms-txt/posts',
			'/airygen/v1/llms-txt/clear-cache',
			'/airygen/v1/score',
			'/airygen/v1/score/recalculate',
			'/airygen/v1/score/recalculate-step',
			'/airygen/v1/score/recalculate-status',
			'/airygen/v1/schema/preview',
			'/airygen/v1/woocommerce/schema-preview',
			'/airygen/v1/taxonomy/preview',
			'/airygen/v1/link-counter/status',
			'/airygen/v1/link-counter/recheck',
			'/airygen/v1/broken-links/logs',
			'/airygen/v1/indexnow/status',
			'/airygen/v1/indexnow/manual',
			'/airygen/v1/indexnow/backfill',
			'/airygen/v1/indexnow/rotate-key',
			'/airygen/v1/debug',
			'/airygen/v1/debug/enable',
			'/airygen/v1/debug/disable',
			'/airygen/v1/debug/level',
			'/airygen/v1/site-health',
			'/airygen/v1/transfer/export',
			'/airygen/v1/transfer/import',
			'/airygen/v1/transfer/uninstall',
			'/airygen/v1/migration/yoast',
			'/airygen/v1/migration/yoast/import',
			'/airygen/v1/migration/yoast/settings',
			'/airygen/v1/migration/yoast/redirects',
			'/airygen/v1/migration/rankmath',
			'/airygen/v1/migration/rankmath/import',
			'/airygen/v1/migration/rankmath/settings',
			'/airygen/v1/migration/rankmath/redirects',
			'/airygen/v1/migration/aioseo',
			'/airygen/v1/migration/aioseo/import',
			'/airygen/v1/migration/aioseo/settings',
			'/airygen/v1/migration/aioseo/redirects',
			'/airygen/v1/migration/seopress',
			'/airygen/v1/migration/seopress/import',
			'/airygen/v1/migration/seopress/settings',
			'/airygen/v1/migration/seopress/redirects',
			'/airygen/v1/link-suggestions/settings',
			'/airygen/v1/link-suggestions/reindex',
			'/airygen/v1/link-suggestions/suggestions',
			'/airygen/v1/topic-cluster/list',
			'/airygen/v1/topic-cluster/save',
			'/airygen/v1/topic-cluster/summary',
			'/airygen/v1/topic-cluster/mindmap',
			'/airygen/v1/topic-cluster/groups',
			'/airygen/v1/topic-cluster/groups/(?P<id>\\d+)',
			'/airygen/v1/topic-cluster/groups/(?P<id>\\d+)/map',
			'/airygen/v1/topic-cluster/groups/(?P<id>\\d+)/mindmap-sync',
			'/airygen/v1/topic-cluster/groups/(?P<id>\\d+)/candidates',
			'/airygen/v1/topic-cluster/groups/(?P<id>\\d+)/candidates/search',
			'/airygen/v1/topic-cluster/groups/(?P<id>\\d+)/candidates/(?P<candidate_id>\\d+)',
			'/airygen/v1/topic-cluster/relate',
			'/airygen/v1/topic-cluster/unrelate',
		);

		foreach ( $expected as $route ) {
			$this->assertArrayHasKey(
				$route,
				$routes,
				sprintf( 'Failed asserting that %s is registered.', $route )
			);
		}
	}
}
