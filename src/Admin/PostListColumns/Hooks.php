<?php
/**
 * Registers unified SEO overview post list column.
 *
 * @package Airygen\Admin\PostListColumns
 */

declare(strict_types=1);

namespace Airygen\Admin\PostListColumns;

use Airygen\Modules\LinkCounter\Domain\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps the SEO overview column in wp-admin list tables.
 */
final class Hooks {

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		$column = new SeoOverviewColumn( new Storage() );
		$column->register();
	}
}
