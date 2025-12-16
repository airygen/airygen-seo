<?php
/**
 * Rules for the admin settings update endpoint.
 *
 * @package Airygen\Support\RequestRules\Admin\Settings
 */

declare(strict_types=1);

namespace Airygen\Support\RequestRules\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides schema for POST /settings payload.
 */
final class UpdateSettings {

	/**
	 * Return the REST args schema.
	 *
	 * @return array<string,mixed>
	 */
	public function __invoke(): array {
		return array(
			'settings' => array(
				'required' => true,
				'type'     => 'object',
			),
		);
	}
}
