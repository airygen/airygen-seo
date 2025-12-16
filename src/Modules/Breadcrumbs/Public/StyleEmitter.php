<?php
/**
 * Outputs breadcrumb inline styles.
 *
 * @package Airygen\Modules\Breadcrumbs\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Breadcrumbs\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Breadcrumbs\Admin\Settings;

/**
 * Emits inline CSS for breadcrumbs based on settings.
 */
final class StyleEmitter {

	/**
	 * Output inline styles in the document head.
	 *
	 * @return void
	 */
	public static function output(): void {
		$settings = Settings::get();
		if ( empty( $settings['manual_output_enabled'] ) && empty( $settings['auto_injection_enabled'] ) ) {
			return;
		}

		$style = isset( $settings['style'] ) && is_array( $settings['style'] )
		? $settings['style']
		: array();

		$font_size    = isset( $style['fontSize'] ) ? (int) $style['fontSize'] : 0;
		$text_color   = isset( $style['textColor'] ) ? (string) $style['textColor'] : '';
		$link_color   = isset( $style['linkColor'] ) ? (string) $style['linkColor'] : '';
		$underline    = ! empty( $style['underlineLinks'] );
		$border_width = isset( $style['borderWidth'] ) ? (int) $style['borderWidth'] : 0;
		$border_color = isset( $style['borderColor'] ) ? (string) $style['borderColor'] : '';
		$padding      = isset( $style['padding'] ) ? (int) $style['padding'] : 0;
		$bg_color     = isset( $style['bgColor'] ) ? (string) $style['bgColor'] : '';

		$rules = array();
		if ( $font_size > 0 ) {
			$rules[] = sprintf( 'font-size: %dpx;', $font_size );
		}
		if ( '' !== $text_color ) {
			$rules[] = sprintf( 'color: %s;', $text_color );
		}
		if ( $border_width > 0 ) {
			$rules[] = sprintf( 'border-width: %dpx;', $border_width );
			$rules[] = 'border-style: solid;';
		}
		if ( '' !== $border_color ) {
			$rules[] = sprintf( 'border-color: %s;', $border_color );
		}
		if ( $padding > 0 ) {
			$rules[] = sprintf( 'padding: %dpx;', $padding );
		}
		if ( '' !== $bg_color ) {
			$rules[] = sprintf( 'background: %s;', $bg_color );
		}

		$link_rules = array();
		if ( '' !== $link_color ) {
			$link_rules[] = sprintf( 'color: %s;', $link_color );
		}
		$link_rules[] = $underline ? 'text-decoration: underline;' : 'text-decoration: none;';

		if ( empty( $rules ) && empty( $link_rules ) ) {
			return;
		}

		$css  = '.airygen-breadcrumbs__list{display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem;}';
		$css .= '.airygen-breadcrumbs__item{display:inline-flex;align-items:center;}';
		$css .= '.airygen-breadcrumbs__separator{display:inline-flex;align-items:center;}';
		$css .= '.airygen-breadcrumbs__link,.airygen-breadcrumbs__text{display:inline-flex;align-items:center;}';
		if ( ! empty( $rules ) ) {
			$css .= sprintf( '.airygen-breadcrumbs{%s}', implode( '', $rules ) );
			if ( '' !== $text_color ) {
				$css .= sprintf( '.airygen-breadcrumbs__separator{color:%s;}', $text_color );
			}
		}
		if ( ! empty( $link_rules ) ) {
			$css .= sprintf( '.airygen-breadcrumbs a{%s}', implode( '', $link_rules ) );
		}

		if ( '' === $css ) {
			return;
		}

		wp_register_style( 'airygen-breadcrumbs-style', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- No external file.
		wp_enqueue_style( 'airygen-breadcrumbs-style' );
		wp_add_inline_style( 'airygen-breadcrumbs-style', $css );
	}
}
