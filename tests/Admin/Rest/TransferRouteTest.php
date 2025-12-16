<?php
/**
 * Tests for transfer REST endpoints.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Constants;

/**
 * @coversNothing
 */
final class TransferRouteTest extends RestRouteTestCase {

	/**
	 * Remove temporary hook registrations between tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( Constants::HOOK_TRANSFER_EXPORT_SETTINGS );
		remove_all_actions( Constants::HOOK_TRANSFER_IMPORT_SETTINGS );

		parent::tear_down();
	}

	/**
	 * Ensure export allows extensions to append settings.
	 *
	 * @return void
	 */
	public function test_export_route_applies_extension_filter(): void {
		$this->acting_as_admin();

		add_filter(
			Constants::HOOK_TRANSFER_EXPORT_SETTINGS,
			static function ( array $settings ): array {
				$settings['extensionSetting'] = array(
					'enabled' => true,
				);

				return $settings;
			}
		);

		$response = $this->rest_get( '/airygen/v1/transfer/export' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame(
			array(
				'enabled' => true,
			),
			$data['settings']['extensionSetting'] ?? null
		);
	}

	/**
	 * Ensure import dispatches the extension action with raw settings.
	 *
	 * @return void
	 */
	public function test_import_route_dispatches_extension_action(): void {
		$this->acting_as_admin();

		$received = null;

		add_action(
			Constants::HOOK_TRANSFER_IMPORT_SETTINGS,
			static function ( array $settings ) use ( &$received ): void {
				$received = $settings['extensionSetting'] ?? null;
			},
			10,
			1
		);

		$response = $this->rest_post(
			'/airygen/v1/transfer/import',
			array(
				'settings' => array(
					'extensionSetting' => array(
						'enabled' => true,
					),
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'enabled' => true,
			),
			$received
		);
	}
}
