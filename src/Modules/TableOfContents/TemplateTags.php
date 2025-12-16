<?php
/**
 * Template tags for TOC rendering.
 *
 * @package Airygen\Modules\TableOfContents
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\TableOfContents\Admin\Settings as TocSettings;
use Airygen\Modules\TableOfContents\Public\Hooks as TocHooks;
use Airygen\Modules\TableOfContents\Public\Renderer as TocRenderer;

if ( ! function_exists( 'airygen_get_toc' ) ) {
	/**
	 * Retrieve the TOC HTML for the current post.
	 *
	 * @return string
	 */
	function airygen_get_toc(): string {
		if ( ! ModuleSettings::is_enabled( 'toc' ) ) {
			return '';
		}

		$settings = TocSettings::get();
		if ( empty( $settings['manual_output_enabled'] ) ) {
			return '';
		}

		$post = get_post();
		if ( ! $post ) {
			return '';
		}

		remove_filter( 'the_content', array( TocHooks::class, 'inject_toc' ), 20 );
		$content = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
		add_filter( 'the_content', array( TocHooks::class, 'inject_toc' ), 20 );
		$built = TocRenderer::build( $content, $settings );

		return $built['toc'];
	}
}

if ( ! function_exists( 'airygen_the_toc' ) ) {
	/**
	 * Echo the TOC HTML for the current post.
	 *
	 * @return void
	 */
	function airygen_the_toc(): void {
		echo airygen_get_toc(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
