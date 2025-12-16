<?php
/**
 * Public hooks for robots controls.
 *
 * @package Airygen\Modules\Robots\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Robots\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Robots\Public\EmitRobotsMeta;
use Airygen\Modules\Robots\Public\RobotsTxtFilter;

/**
 * Registers public runtime hooks.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_head', array( EmitRobotsMeta::class, 'emit' ), 30 );
		add_filter( 'robots_txt', array( RobotsTxtFilter::class, 'filter' ), 10, 2 );
	}
}
