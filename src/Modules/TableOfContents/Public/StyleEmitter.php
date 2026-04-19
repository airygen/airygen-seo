<?php
/**
 * Outputs inline styling for the Table of Contents.
 *
 * @package Airygen\Modules\TableOfContents\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\TableOfContents\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\TableOfContents\Admin\Settings;
use Airygen\Support\Utils\Css;

/**
 * Emits inline CSS for TOC styling.
 */
final class StyleEmitter {

	/**
	 * Output inline styles based on TOC settings.
	 *
	 * @return void
	 */
	public static function output(): void {
		$settings = Settings::get();

		if ( empty( $settings['manual_output_enabled'] ) && empty( $settings['auto_injection_enabled'] ) ) {
			return;
		}

		$style               = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();
		$preset              = isset( $style['preset'] ) ? (string) $style['preset'] : 'minimal';
		$border_style        = Css::sanitize_border_style( $style['border_style'] ?? 'solid', 'solid' );
		$border_color        = Css::sanitize_color( $style['border_color'] ?? '#e2e8f0', '#e2e8f0' );
		$border_radius       = isset( $style['border_radius'] ) ? (int) $style['border_radius'] : 0;
		$body_container      = isset( $style['body_container'] ) && is_array( $style['body_container'] ) ? $style['body_container'] : array();
		$border_width_top    = isset( $body_container['border_width_top'] ) ? (int) $body_container['border_width_top'] : 1;
		$border_width_right  = isset( $body_container['border_width_right'] ) ? (int) $body_container['border_width_right'] : 1;
		$border_width_bottom = isset( $body_container['border_width_bottom'] ) ? (int) $body_container['border_width_bottom'] : 1;
		$border_width_left   = isset( $body_container['border_width_left'] ) ? (int) $body_container['border_width_left'] : 1;
		$padding_top         = isset( $body_container['padding_top'] ) ? (int) $body_container['padding_top'] : 16;
		$padding_right       = isset( $body_container['padding_right'] ) ? (int) $body_container['padding_right'] : 16;
		$padding_bottom      = isset( $body_container['padding_bottom'] ) ? (int) $body_container['padding_bottom'] : 16;
		$padding_left        = isset( $body_container['padding_left'] ) ? (int) $body_container['padding_left'] : 16;
		$margin_top          = isset( $body_container['margin_top'] ) ? (int) $body_container['margin_top'] : 0;
		$margin_right        = isset( $body_container['margin_right'] ) ? (int) $body_container['margin_right'] : 0;
		$margin_bottom       = isset( $body_container['margin_bottom'] ) ? (int) $body_container['margin_bottom'] : 0;
		$margin_left         = isset( $body_container['margin_left'] ) ? (int) $body_container['margin_left'] : 0;
		$toc_padding         = isset( $style['toc_padding'] ) ? (int) $style['toc_padding'] : 12;
		$link_color          = Css::sanitize_color( $style['link_color'] ?? '#2563eb', '#2563eb' );
		$link_size           = isset( $style['link_size'] ) ? (int) $style['link_size'] : 14;
		$font_style          = isset( $style['font_style'] ) && is_array( $style['font_style'] ) ? $style['font_style'] : array();
		$bold                = ! empty( $font_style['bold'] );
		$italic              = ! empty( $font_style['italic'] );
		$underline           = ! empty( $font_style['underline'] );
		$bg_color            = Css::sanitize_color( $style['bg_color'] ?? '#ffffff', '#ffffff' );
		$header_container    = isset( $style['header_container'] ) && is_array( $style['header_container'] ) ? $style['header_container'] : array();
		$header_title        = isset( $style['header_title'] ) && is_array( $style['header_title'] ) ? $style['header_title'] : array();
		$header_title_style  = isset( $header_title['font_style'] ) && is_array( $header_title['font_style'] ) ? $header_title['font_style'] : array();
		$numbered            = ! empty( $settings['add_numbers'] );
		$collapsible         = ! empty( $settings['collapse_on_load'] );

		$css                        = '.airygen-toc{margin:' . max( 0, min( 48, $margin_top ) ) . 'px ' . max( 0, min( 48, $margin_right ) ) . 'px ' . max( 0, min( 48, $margin_bottom ) ) . 'px ' . max( 0, min( 48, $margin_left ) ) . 'px;padding:' . max( 0, min( 48, $padding_top ) ) . 'px ' . max( 0, min( 48, $padding_right ) ) . 'px ' . max( 0, min( 48, $padding_bottom ) ) . 'px ' . max( 0, min( 48, $padding_left ) ) . 'px;border-width:' . max( 0, min( 8, $border_width_top ) ) . 'px ' . max( 0, min( 8, $border_width_right ) ) . 'px ' . max( 0, min( 8, $border_width_bottom ) ) . 'px ' . max( 0, min( 8, $border_width_left ) ) . 'px;border-style:' . $border_style . ';border-color:' . $border_color . ';border-radius:' . $border_radius . 'px;background:' . $bg_color . ';width:100%;box-sizing:border-box;}';
		$header_padding_top         = isset( $header_container['padding_top'] ) ? (int) $header_container['padding_top'] : 0;
		$header_padding_right       = isset( $header_container['padding_right'] ) ? (int) $header_container['padding_right'] : 0;
		$header_padding_bottom      = isset( $header_container['padding_bottom'] ) ? (int) $header_container['padding_bottom'] : 0;
		$header_padding_left        = isset( $header_container['padding_left'] ) ? (int) $header_container['padding_left'] : 0;
		$header_margin_top          = isset( $header_container['margin_top'] ) ? (int) $header_container['margin_top'] : 0;
		$header_margin_right        = isset( $header_container['margin_right'] ) ? (int) $header_container['margin_right'] : 0;
		$header_margin_bottom       = isset( $header_container['margin_bottom'] ) ? (int) $header_container['margin_bottom'] : 12;
		$header_margin_left         = isset( $header_container['margin_left'] ) ? (int) $header_container['margin_left'] : 0;
		$header_border_width_top    = isset( $header_container['border_width_top'] ) ? (int) $header_container['border_width_top'] : 0;
		$header_border_width_right  = isset( $header_container['border_width_right'] ) ? (int) $header_container['border_width_right'] : 0;
		$header_border_width_bottom = isset( $header_container['border_width_bottom'] ) ? (int) $header_container['border_width_bottom'] : 0;
		$header_border_width_left   = isset( $header_container['border_width_left'] ) ? (int) $header_container['border_width_left'] : 0;
		$header_border_radius       = isset( $header_container['border_radius'] ) ? (int) $header_container['border_radius'] : 0;
		$header_border_style        = Css::sanitize_border_style( $header_container['border_style'] ?? 'solid', 'solid' );
		$header_border_color        = Css::sanitize_color( $header_container['border_color'] ?? '#e2e8f0', '#e2e8f0' );
		$header_bg                  = Css::sanitize_color( $header_container['bg_color'] ?? 'transparent', 'transparent' );
		$header_title_color         = Css::sanitize_color( $header_title['color'] ?? '#0f172a', '#0f172a' );
		$header_title_size          = isset( $header_title['font_size'] ) ? (int) $header_title['font_size'] : 18;
		$css                       .= '.airygen-toc-header{display:block;margin:' . max( 0, min( 48, $header_margin_top ) ) . 'px ' . max( 0, min( 48, $header_margin_right ) ) . 'px ' . max( 0, min( 48, $header_margin_bottom ) ) . 'px ' . max( 0, min( 48, $header_margin_left ) ) . 'px;padding:' . max( 0, min( 48, $header_padding_top ) ) . 'px ' . max( 0, min( 48, $header_padding_right ) ) . 'px ' . max( 0, min( 48, $header_padding_bottom ) ) . 'px ' . max( 0, min( 48, $header_padding_left ) ) . 'px;border-width:' . max( 0, min( 8, $header_border_width_top ) ) . 'px ' . max( 0, min( 8, $header_border_width_right ) ) . 'px ' . max( 0, min( 8, $header_border_width_bottom ) ) . 'px ' . max( 0, min( 8, $header_border_width_left ) ) . 'px;border-style:' . $header_border_style . ';border-color:' . $header_border_color . ';border-radius:' . max( 0, min( 48, $header_border_radius ) ) . 'px;background:' . $header_bg . ';color:' . $header_title_color . ';font-size:' . max( 10, min( 40, $header_title_size ) ) . 'px;font-weight:' . ( ! empty( $header_title_style['bold'] ) ? '700' : '400' ) . ';font-style:' . ( ! empty( $header_title_style['italic'] ) ? 'italic' : 'normal' ) . ';text-decoration:' . ( ! empty( $header_title_style['underline'] ) ? 'underline' : 'none' ) . ';}';
		$css                       .= '.airygen-toc__list,.airygen-toc__sublist{margin:0;padding-left:' . $toc_padding . 'px;}';
		$css                       .= '.airygen-toc__item{margin:0.25rem 0;}';
		$css                       .= '.airygen-toc__link{text-decoration:none;color:' . $link_color . ';font-size:' . $link_size . 'px;font-weight:' . ( $bold ? '700' : '400' ) . ';font-style:' . ( $italic ? 'italic' : 'normal' ) . ';}';
		$css                       .= self::preset_css( $preset, $border_style, $border_color, max( max( $border_width_top, $border_width_right ), max( $border_width_bottom, $border_width_left ) ) );

		if ( $underline ) {
			$css .= '.airygen-toc__link{text-decoration:underline;text-underline-offset:3px;}';
			$css .= '.airygen-toc__link:hover{text-decoration-thickness:2px;}';
		}

		if ( ! $numbered ) {
			$css .= '.airygen-toc__list,.airygen-toc__sublist{list-style:none;}';
		} else {
			$css .= '.airygen-toc__list{list-style:decimal;}';
			$css .= '.airygen-toc__sublist{list-style:lower-alpha;}';
			$css .= '.airygen-toc__sublist .airygen-toc__sublist{list-style:lower-roman;}';
		}

		if ( $collapsible ) {
			$css .= '.airygen-toc-header{cursor:pointer;}';
		}

		if ( ! empty( $settings['smooth_scroll'] ) ) {
			$css .= 'html{scroll-behavior:smooth;}';
		}

		wp_register_style( 'airygen-toc-styles', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- No external file.
		wp_enqueue_style( 'airygen-toc-styles' );
		wp_add_inline_style( 'airygen-toc-styles', $css );
	}

	/**
	 * Preset styling overrides.
	 *
	 * @param string $preset Preset slug.
	 * @param string $border_style Border style.
	 * @param string $border_color Border color.
	 * @param int    $border_width Border width.
	 *
	 * @return string
	 */
	private static function preset_css(
		string $preset,
		string $border_style,
		string $border_color,
		int $border_width
	): string {
		$preset = strtolower( $preset );

		if ( 'card' === $preset ) {
			return '.airygen-toc--preset-card{background:#f8fafc;border-radius:12px;box-shadow:0 1px 2px rgba(15,23,42,0.08);}';
		}

		if ( 'soft' === $preset ) {
			return '.airygen-toc--preset-soft{background:#f1f5f9;border-radius:10px;border-color:#cbd5f5;}';
		}

		if ( 'accent' === $preset ) {
			$border = sprintf( '%dpx %s %s', max( 3, $border_width ), $border_style, '#0ea5e9' );
			return '.airygen-toc--preset-accent{border:' . $border . ';border-left-width:' . max( 4, $border_width ) . 'px;}';
		}

		if ( 'compact' === $preset ) {
			return '.airygen-toc--preset-compact{font-size:0.95em;}'
			. '.airygen-toc--preset-compact .airygen-toc__item{margin:0.2rem 0;}';
		}

		if ( 'minimal' === $preset ) {
			return '.airygen-toc--preset-minimal{border-color:' . $border_color . ';}';
		}

		return '';
	}
}
