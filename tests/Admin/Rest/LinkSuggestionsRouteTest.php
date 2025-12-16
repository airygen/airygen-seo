<?php
/**
 * REST tests for Link Suggestions admin endpoints.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

/**
 * @coversNothing
 */
final class LinkSuggestionsRouteTest extends RestRouteTestCase {

	/**
	 * Settings endpoint should be accessible for admins.
	 *
	 * @return void
	 */
	public function test_settings_endpoint_accessible_for_admins(): void {
		$this->acting_as_admin();

		$response = $this->rest_get( '/airygen/v1/link-suggestions/settings' );
		$this->assertSame( 200, $response->get_status() );
	}
}
