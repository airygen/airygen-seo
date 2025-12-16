<?php
/**
 * Admin hooks for Topic Cluster module.
 *
 * @package Airygen\Modules\TopicCluster\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\TopicCluster\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Topic Cluster admin integrations.
 */
final class Hooks {

	/**
	 * Register Topic Cluster admin hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		// Unified SEO list table column is registered from Airygen\Admin\PostListColumns\Hooks.
	}
}
