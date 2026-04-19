<?php
/**
 * Renders breadcrumb trails as HTML.
 *
 * @package Airygen\Modules\Breadcrumbs\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Breadcrumbs\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\Breadcrumbs\Admin\Settings;
use Airygen\Modules\Breadcrumbs\Domain\Trail;

/**
 * Handles HTML rendering for breadcrumb trails.
 */
final class TrailRenderer {

	/**
	 * Render the cached trail using the supplied arguments.
	 *
	 * @param array<string, mixed> $args Rendering overrides.
	 *
	 * @return string
	 */
	public static function render_current( array $args = array(), bool $respect_manual_output = true ): string {
		if ( ! ModuleSettings::is_enabled( 'breadcrumbs' ) ) {
			return '';
		}

		$settings = Settings::get();
		if ( $respect_manual_output && empty( $settings['manual_output_enabled'] ) ) {
			return '';
		}

		$trail = TrailStore::current();
		if ( null === $trail ) {
			$trail = TrailBuilder::from_current_query();
			TrailStore::prime( $trail );
		}

		if ( null === $trail || $trail->is_empty() ) {
			return '';
		}

		$defaults = array(
			'separator'   => $settings['separator'],
			'wrap_before' => '<nav aria-label="Breadcrumbs" class="airygen-breadcrumbs"><div class="airygen-breadcrumbs__list">',
			'wrap_after'  => '</div></nav>',
			'before'      => '<div class="airygen-breadcrumbs__item">',
			'after'       => '</div>',
			'prefix'      => $settings['prefix'] ?? '',
			'link_last'   => false,
		);

		$args = wp_parse_args( $args, $defaults );

		return self::render( $trail, $args );
	}

	/**
	 * Render a specific trail instance.
	 *
	 * @param Trail                $trail Trail data.
	 * @param array<string, mixed> $args  Rendering overrides.
	 *
	 * @return string
	 */
	public static function render( Trail $trail, array $args ): string {
		$items = $trail->items();
		if ( empty( $items ) ) {
			return '';
		}

		$wrap_before = is_string( $args['wrap_before'] ?? '' ) ? $args['wrap_before'] : '';
		$wrap_after  = is_string( $args['wrap_after'] ?? '' ) ? $args['wrap_after'] : '';
		$before      = is_string( $args['before'] ?? '' ) ? $args['before'] : '';
		$after       = is_string( $args['after'] ?? '' ) ? $args['after'] : '';
		$separator   = isset( $args['separator'] ) ? (string) $args['separator'] : '/';
		$prefix      = isset( $args['prefix'] ) ? (string) $args['prefix'] : '';
		$link_last   = ! empty( $args['link_last'] );

		$html = wp_kses_post( $wrap_before );

		if ( '' !== $prefix ) {
			$html .= '<span class="airygen-breadcrumbs__prefix">' . esc_html( $prefix ) . '</span>';
		}

		$last_index = count( $items ) - 1;

		foreach ( $items as $index => $item ) {
			$is_last = $index === $last_index;
			$label   = esc_html( $item['label'] );

			$html .= wp_kses_post( $before );

			if ( ! empty( $item['url'] ) && ( ! $is_last || $link_last ) ) {
				$html .= sprintf(
					'<a href="%s" class="airygen-breadcrumbs__link">%s</a>',
					esc_url( $item['url'] ),
					$label
				);
			} else {
				$html .= sprintf(
					'<span class="airygen-breadcrumbs__text">%s</span>',
					$label
				);
			}

			$html .= wp_kses_post( $after );

			if ( ! $is_last ) {
				$html .= sprintf(
					'<span class="airygen-breadcrumbs__separator">%s</span>',
					wp_kses_post( $separator )
				);
			}
		}

		$html .= wp_kses_post( $wrap_after );

		return $html;
	}
}
