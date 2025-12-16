<?php
/**
 * Template tags for rendering Airygen breadcrumbs.
 *
 * @package Airygen\Modules\Breadcrumbs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Breadcrumbs\Public\TrailRenderer;

if ( ! function_exists( 'airygen_get_breadcrumbs' ) ) {
	/**
	 * Retrieve the breadcrumb HTML for the current request.
	 *
	 * @param array<string, mixed> $args Rendering overrides.
	 * @return string
	 */
	function airygen_get_breadcrumbs( array $args = array() ): string {
		return TrailRenderer::render_current( $args );
	}
}

if ( ! function_exists( 'airygen_the_breadcrumbs' ) ) {
	/**
	 * Echo the breadcrumb markup for the current request.
	 *
	 * @param array<string, mixed> $args Rendering overrides.
	 * @return void
	 */
	function airygen_the_breadcrumbs( array $args = array() ): void {
		echo airygen_get_breadcrumbs( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'airygen_breadcrumbs' ) ) {
	/**
	 * Echo breadcrumbs (template tag alias).
	 *
	 * @param array<string, mixed> $args Rendering overrides.
	 * @return void
	 */
	function airygen_breadcrumbs( array $args = array() ): void {
		airygen_the_breadcrumbs( $args );
	}
}
