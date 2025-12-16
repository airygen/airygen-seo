<?php
/**
 * Registers public hooks for social cards.
 *
 * @package Airygen\Modules\SocialCards\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\SocialCards\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SocialCards\Public\EmitOpenGraph;
use Airygen\Modules\SocialCards\Public\EmitTwitter;

/**
 * Hook registration for public runtime.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_head', array( EmitOpenGraph::class, 'emit' ), 15 );
		add_action( 'wp_head', array( EmitTwitter::class, 'emit' ), 16 );
	}
}
