<?php
/**
 * Registers public hooks for the OnPage SEO feature.
 *
 * @package Airygen\Modules\OnPageSeo\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\OnPageSeo\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\OnPageSeo\Public\HeadEmitter;

/**
 * Hook registration for public runtime.
 */
final class Hooks {

	/**
	 * Wire up hooks for the public context.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_head', array( HeadEmitter::class, 'emit' ), 5 );
		add_filter( 'pre_get_document_title', array( HeadEmitter::class, 'filter_document_title' ), 20 );
	}
}
