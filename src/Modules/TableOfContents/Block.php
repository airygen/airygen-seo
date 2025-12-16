<?php
/**
 * Register the Table of Contents block.
 *
 * @package Airygen\Modules\TableOfContents
 */

declare(strict_types=1);

namespace Airygen\Modules\TableOfContents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\TableOfContents\Public\Renderer;

/**
 * Registers the TOC block with a dynamic render callback.
 */
final class Block {

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'airygen/toc',
			array(
				'editor_script'   => 'airygen-editor-block',
				'render_callback' => array( Renderer::class, 'render_block' ),
			)
		);
	}
}
