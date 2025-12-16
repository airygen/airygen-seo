<?php
/**
 * Public hooks for schema markup feature.
 *
 * @package Airygen\Modules\SchemaMarkup\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SchemaMarkup\Public\EmitJsonLd;

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
		$callback = array( EmitJsonLd::class, 'emit' );
		if ( false !== has_action( 'wp_head', $callback ) ) {
			return;
		}

		add_action( 'wp_head', $callback, 20 );
	}
}
