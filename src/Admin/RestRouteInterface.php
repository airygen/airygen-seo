<?php
/**
 * Shared interface for REST route registrars.
 *
 * @package Airygen\Admin
 */

declare(strict_types=1);

namespace Airygen\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface RestRouteInterface {
	public function register(): void;
}
