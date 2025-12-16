<?php
/**
 * Stores the breadcrumb trail for the current request.
 *
 * @package Airygen\Modules\Breadcrumbs\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Breadcrumbs\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Breadcrumbs\Domain\Trail;

/**
 * Manages the cached breadcrumb trail.
 */
final class TrailStore {

	/**
	 * Cached trail.
	 *
	 * @var Trail|null
	 */
	private static $current = null;

	/**
	 * Remember the current trail.
	 *
	 * @param Trail|null $trail Trail instance.
	 *
	 * @return void
	 */
	public static function prime( ?Trail $trail ): void {
		self::$current = $trail;
	}

	/**
	 * Retrieve the cached trail.
	 *
	 * @return Trail|null
	 */
	public static function current(): ?Trail {
		return self::$current;
	}
}
