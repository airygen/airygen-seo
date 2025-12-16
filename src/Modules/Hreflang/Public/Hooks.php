<?php
/**
 * Public hooks for hreflang alternates.
 *
 * @package Airygen\Modules\Hreflang\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Hreflang\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Hreflang\Public\EmitAlternates;

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
		add_action( 'wp_head', array( EmitAlternates::class, 'emit' ), 25 );
	}
}
