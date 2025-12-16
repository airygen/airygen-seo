<?php
/**
 * Adds a quick-launch entry for Airygen SEO inside the WordPress front-end admin bar.
 *
 * @package Airygen\Public
 */

declare(strict_types=1);

namespace Airygen\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Admin_Bar;

/**
 * Handles rendering the Airygen SEO shortcut inside the front-end admin toolbar.
 */
final class AdminBar {

	/**
	 * Unique ID for the admin bar node.
	 */
	private const NODE_ID = 'airygen-toolbar';

	/**
	 * Inline SVG markup for the icon.
	 */
	private const ICON_MARKUP = "<?xml version='1.0' encoding='utf-8'?><svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 48 48' preserveAspectRatio='xMidYMid meet' version='1.1'><g transform='matrix(0.603828602702188,0,0,0.603828602702188,3.53115385058755,46.0)'><path fill='#ffffff' fill-rule='evenodd' d='M 33.898438 -72.868360 L 68.566895 0.000000 L -0.770020 0.000000 Z M 28.698169 -43.721016 L 49.499243 0.000000 L 7.897094 0.000000 Z' id='A_dark_outer110_white060' /><path fill='#00a0e9' d='M 21.764478 -29.147344 L 35.631860 0.000000 L 7.897094 0.000000 Z' id='A_blue040_leftAligned' /></g></svg>";

	/**
	 * Register the front-end hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		\add_action( 'admin_bar_menu', array( __CLASS__, 'add_toolbar_entry' ), 100 );
		\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'print_styles' ) );
	}

	/**
	 * Add the Airygen SEO shortcut to the admin bar.
	 *
	 * @param WP_Admin_Bar $admin_bar Admin bar instance.
	 *
	 * @return void
	 */
	public static function add_toolbar_entry( WP_Admin_Bar $admin_bar ): void {
		if ( ! self::should_show() ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => self::NODE_ID,
				'title' => sprintf(
					'<span class="ab-icon airygen-admin-bar-icon" aria-hidden="true">%1$s</span><span class="ab-label">%2$s</span>',
					self::ICON_MARKUP,
					\esc_html__( 'Airygen SEO', 'airygen-seo' )
				),
				'href'  => \admin_url( 'admin.php?page=airygen-dashboard' ),
				'meta'  => array(
					'class' => 'airygen-admin-bar-node',
					'title' => \esc_attr__( 'Open the Airygen SEO dashboard', 'airygen-seo' ),
				),
			)
		);
	}

	/**
	 * Output inline styles so the shortcut displays the custom SVG icon.
	 *
	 * @return void
	 */
	public static function print_styles(): void {
		if ( ! self::should_show() ) {
			return;
		}

		$node_id = \esc_attr( self::NODE_ID );
		$css     = '#wpadminbar .airygen-admin-bar-node .ab-item{display:flex;gap:4px;align-items:center;height:32px;line-height:32px;padding-top:0;padding-bottom:0;}#wpadminbar .airygen-admin-bar-node .ab-icon{display:inline-flex;align-items:center;justify-content:center;width:24px;height:32px;margin-right:0;}#wpadminbar .airygen-admin-bar-node .ab-icon svg{display:block;height:18px;width:auto;}#wpadminbar #' . $node_id . ' .ab-icon:before{content:"";}';

		\wp_register_style( 'airygen-admin-bar-icon', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- No external file.
		\wp_enqueue_style( 'airygen-admin-bar-icon' );
		\wp_add_inline_style( 'airygen-admin-bar-icon', $css );
	}

	/**
	 * Determine if the toolbar entry should be shown.
	 *
	 * @return bool
	 */
	private static function should_show(): bool {
		if ( \is_admin() ) {
			return false;
		}

		if ( ! \is_user_logged_in() || ! \is_admin_bar_showing() ) {
			return false;
		}

		return \current_user_can( 'manage_options' );
	}
}
