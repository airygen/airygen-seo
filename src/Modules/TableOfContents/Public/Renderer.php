<?php
/**
 * Builds TOC HTML and injects it into content.
 *
 * @package Airygen\Modules\TableOfContents\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\TableOfContents\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\TableOfContents\Admin\Settings as TocSettings;
use Airygen\Modules\TableOfContents\Domain\TocBuilder;
use Airygen\Support\Meta\OutputModes;

/**
 * Render Table of Contents markup for content.
 */
final class Renderer {

	/**
	 * Generate TOC and content for a given HTML string.
	 *
	 * @param string               $content Content HTML.
	 * @param array<string, mixed> $settings TOC settings.
	 *
	 * @return array{content: string, toc: string, headings: int}
	 */
	public static function build( string $content, array $settings ): array {
		$levels = isset( $settings['levels'] ) && is_array( $settings['levels'] )
		? array_map( 'intval', $settings['levels'] )
		: array( 2, 3 );

		$prefix  = isset( $settings['anchor_prefix'] ) ? (string) $settings['anchor_prefix'] : 'toc-';
		$exclude = array();
		if ( isset( $settings['exclude_headings'] ) && is_string( $settings['exclude_headings'] ) ) {
			$exclude = array_filter( array_map( 'trim', explode( ',', $settings['exclude_headings'] ) ) );
		}

		$result         = TocBuilder::build( $content, $levels, $prefix, $exclude );
		$headings       = $result['headings'];
		$headings_count = count( $headings );

		if ( $headings_count < (int) ( $settings['min_headings'] ?? 0 ) ) {
			return array(
				'content'  => $result['content'],
				'toc'      => '',
				'headings' => $headings_count,
			);
		}

		$toc = self::render_toc( $headings, $settings );

		return array(
			'content'  => $result['content'],
			'toc'      => $toc,
			'headings' => $headings_count,
		);
	}

	/**
	 * Inject TOC into content according to position.
	 *
	 * @param string               $content Content HTML.
	 * @param array<string, mixed> $settings TOC settings.
	 *
	 * @return string
	 */
	public static function inject( string $content, array $settings ): string {
		$built = self::build( $content, $settings );
		if ( '' === $built['toc'] ) {
			return $built['content'];
		}

		$position = isset( $settings['position'] ) ? (string) $settings['position'] : 'after-first-paragraph';

		if ( 'before-content' === $position ) {
			return $built['toc'] . $built['content'];
		}

		if ( 'after-content' === $position ) {
			return $built['content'] . $built['toc'];
		}

		$closing = stripos( $built['content'], '</p>' );
		if ( false !== $closing ) {
			$closing += 4;
			return substr( $built['content'], 0, $closing ) . $built['toc'] . substr( $built['content'], $closing );
		}

		return $built['toc'] . $built['content'];
	}

	/**
	 * Render TOC HTML from headings.
	 *
	 * @param array<int, array{level: int, id: string, text: string}> $headings Heading entries.
	 * @param array<string, mixed>                                   $settings TOC settings.
	 *
	 * @return string
	 */
	private static function render_toc( array $headings, array $settings ): string {
		$title_enabled = ! isset( $settings['title_enabled'] ) || ! empty( $settings['title_enabled'] );
		$title         = isset( $settings['title'] ) && '' !== trim( (string) $settings['title'] )
		? (string) $settings['title']
		: 'Table of contents';
		$title_level   = isset( $settings['title_level'] ) ? strtolower( (string) $settings['title_level'] ) : 'h2';
		if ( ! in_array( $title_level, array( 'h2', 'h3', 'h4' ), true ) ) {
			$title_level = 'h2';
		}

		$numbered    = ! empty( $settings['add_numbers'] );
		$collapsible = ! empty( $settings['collapse_on_load'] );

		$classes = array( 'airygen-toc' );
		if ( isset( $settings['style']['preset'] ) && is_string( $settings['style']['preset'] ) && '' !== $settings['style']['preset'] ) {
			$classes[] = 'airygen-toc--preset-' . sanitize_html_class( $settings['style']['preset'] );
		}
		if ( $numbered ) {
			$classes[] = 'airygen-toc--numbered';
		}
		if ( $collapsible ) {
			$classes[] = 'airygen-toc--collapsed';
		}

		$min_level = null;
		foreach ( $headings as $heading ) {
			$level = (int) $heading['level'];
			if ( null === $min_level || $level < $min_level ) {
				$min_level = $level;
			}
		}
		if ( null === $min_level ) {
			$min_level = 2;
		}

		$list_html  = '';
		$current    = $min_level;
		$list_html .= '<ol class="airygen-toc__list">';
		$first      = true;
		$open_li    = false;

		foreach ( $headings as $heading ) {
			$level = (int) $heading['level'];
			$id    = esc_attr( (string) $heading['id'] );
			$text  = esc_html( (string) $heading['text'] );

			if ( $open_li ) {
				if ( $level > $current ) {
					while ( $level > $current ) {
						$list_html .= '<ol class="airygen-toc__sublist">';
						++$current;
					}
					$open_li = false;
				} elseif ( $level < $current ) {
					$list_html .= '</li>';
					while ( $level < $current ) {
						$list_html .= '</ol></li>';
						--$current;
					}
					$open_li = false;
				} elseif ( ! $first ) {
					$list_html .= '</li>';
					$open_li    = false;
				}
			} elseif ( $level > $current ) {
				while ( $level > $current ) {
					$list_html .= '<ol class="airygen-toc__sublist">';
					++$current;
				}
			} elseif ( $level < $current ) {
				while ( $level < $current ) {
					$list_html .= '</ol>';
					--$current;
				}
			}

			$list_html .= sprintf(
				'<li class="airygen-toc__item"><a class="airygen-toc__link" href="#%s">%s</a>',
				$id,
				$text
			);
			$first      = false;
			$open_li    = true;
		}

		if ( $open_li ) {
			$list_html .= '</li>';
		}

		while ( $current > $min_level ) {
			$list_html .= '</ol></li>';
			--$current;
		}

		$list_html .= '</ol>';

		if ( $collapsible ) {
			$summary_html = $title_enabled
			? sprintf(
				'<summary class="airygen-toc-header"><span class="airygen-toc-header__text airygen-toc-header__text--%1$s">%2$s</span></summary>',
				esc_attr( $title_level ),
				esc_html( $title )
			)
			: '<summary class="airygen-toc-header" aria-label="' . esc_attr__( 'Table of contents', 'airygen-seo' ) . '"></summary>';

			return sprintf(
				'<details class="airygen-toc-collapsible">%s<div class="%s"><nav class="airygen-toc__nav" aria-label="Table of contents">%s</nav></div></details>',
				$summary_html,
				esc_attr( implode( ' ', $classes ) ),
				$list_html
			);
		}

		$title_html = $title_enabled
		? sprintf(
			'<%1$s class="airygen-toc-header">%2$s</%1$s>',
			esc_html( $title_level ),
			esc_html( $title )
		)
		: '';

		return sprintf(
			'%s<div class="%s"><nav class="airygen-toc__nav" aria-label="Table of contents">%s</nav></div>',
			$title_html,
			esc_attr( implode( ' ', $classes ) ),
			$list_html
		);
	}

	/**
	 * Render callback for the TOC block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return string
	 */
	public static function render_block( array $attributes ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by render_callback signature.
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

		$mode = OutputModes::get_mode( (int) $post->ID, 'toc' );
		if ( 'disabled' === $mode ) {
			return '';
		}

		if ( 'manual' !== $mode && 'auto' !== $mode ) {
			return '';
		}

		$content = self::render_content_without_toc_block( $post->post_content );
		if ( '' === $content ) {
			return '';
		}

		$built = self::build( $content, $settings );
		return $built['toc'];
	}

	/**
	 * Render content without any airygen/toc blocks to avoid recursion.
	 *
	 * @param string $content Raw post content.
	 *
	 * @return string
	 */
	private static function render_content_without_toc_block( string $content ): string {
		if ( ! function_exists( 'parse_blocks' ) ) {
			return $content;
		}

		$blocks = parse_blocks( $content );
		$blocks = array_values(
			array_filter(
				$blocks,
				static function ( $block ): bool {
					return empty( $block['blockName'] ) || 'airygen/toc' !== $block['blockName'];
				}
			)
		);

		$html = '';
		foreach ( $blocks as $block ) {
			$html .= render_block( $block );
		}

		return $html;
	}
}
