<?php
/**
 * Public hooks for Image SEO runtime behavior.
 *
 * @package Airygen\Modules\ImageSeo\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\ImageSeo\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\ImageSeo\Domain\Service\GenerateAttribute;
use Airygen\Modules\ImageSeo\Public\Filters\AddAttributes;
use Airygen\Modules\ImageSeo\Settings\Repository;

/**
 * Conditionally attaches runtime filters.
 */
final class Hooks {

	/**
	 * Tracks whether the filters already ran for the current request.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Register bootstrap callbacks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp', array( __CLASS__, 'maybe_boot' ), 9999 );
		add_action( 'rest_api_init', array( __CLASS__, 'maybe_boot' ), 9999 );
	}

	/**
	 * Initialize filters when settings allow it.
	 *
	 * @return void
	 */
	public static function maybe_boot(): void {
		if ( self::$booted ) {
			return;
		}

		$repository = Repository::from_settings();

		if ( ! $repository->is_active() ) {
			return;
		}

		$filter = new AddAttributes( $repository, new GenerateAttribute() );
		$filter->hook();

		self::$booted = true;
	}
}
