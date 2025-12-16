<?php
/**
 * Registers admin hooks for Table of Contents.
 *
 * @package Airygen\Modules\TableOfContents\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\TableOfContents\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\TableOfContents\Block;
use Airygen\Support\Meta\OutputModes;

/**
 * Entry point for TOC admin hooks.
 */
final class Hooks {

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		Settings::ensure_exists();

		add_action( 'init', array( __CLASS__, 'register_post_meta' ) );
		add_action( 'init', array( Block::class, 'register' ) );
	}

	/**
	 * Register post meta for per-post TOC controls.
	 *
	 * @return void
	 */
	public static function register_post_meta(): void {
		register_post_meta(
			'',
			Constants::META_OUTPUT_MODES,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => array( OutputModes::class, 'sanitize_meta_value' ),
			)
		);
	}
}
