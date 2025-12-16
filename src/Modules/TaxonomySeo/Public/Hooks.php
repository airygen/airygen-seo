<?php
/**
 * Public hooks for taxonomy SEO output.
 *
 * @package Airygen\Modules\TaxonomySeo\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\TaxonomySeo\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\TaxonomySeo\Admin\Settings;
use Airygen\Modules\TaxonomySeo\Domain\RenderTermTemplate;
use WP_Term;

/**
 * Handles taxonomy archive SEO output.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 30 );
		add_action( 'wp_head', array( __CLASS__, 'emit_head' ), 8 );
	}

	/**
	 * Filter taxonomy document title.
	 *
	 * @param string $title Existing title.
	 *
	 * @return string
	 */
	public static function filter_document_title( string $title ): string {
		$term = self::queried_term();
		if ( ! $term instanceof WP_Term ) {
			return $title;
		}

		$settings = Settings::get();
		if ( ! self::is_active_for_term( $settings, $term ) ) {
			return $title;
		}

		$resolved = self::resolve_title( $settings, $term );
		if ( '' === $resolved ) {
			return $title;
		}

		return $resolved;
	}

	/**
	 * Emit taxonomy archive head tags.
	 *
	 * @return void
	 */
	public static function emit_head(): void {
		$term = self::queried_term();
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$settings = Settings::get();
		if ( ! self::is_active_for_term( $settings, $term ) ) {
			return;
		}

		if ( ! current_theme_supports( 'title-tag' ) ) {
			$title = self::resolve_title( $settings, $term );
			if ( '' !== $title ) {
				printf( "<title>%s</title>\n", esc_html( $title ) );
			}
		}

		$description = self::resolve_description( $settings, $term );
		if ( '' !== $description ) {
			printf(
				"<meta name=\"description\" content=\"%s\" />\n",
				esc_attr( $description )
			);
		}

		$canonical = self::resolve_canonical( $term );
		if ( '' !== $canonical ) {
			printf(
				"<link rel=\"canonical\" href=\"%s\" />\n",
				esc_url( $canonical )
			);
		}
	}

	/**
	 * Resolve queried term object.
	 *
	 * @return WP_Term|null
	 */
	private static function queried_term(): ?WP_Term {
		if ( ! is_category() && ! is_tag() && ! is_tax() ) {
			return null;
		}

		$object = get_queried_object();
		if ( ! $object instanceof WP_Term ) {
			return null;
		}

		return $object;
	}

	/**
	 * Determine if module applies for this taxonomy.
	 *
	 * @param array<string, mixed> $settings Module settings.
	 * @param WP_Term              $term     Queried term.
	 *
	 * @return bool
	 */
	private static function is_active_for_term( array $settings, WP_Term $term ): bool {
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		if ( ! isset( $settings['enabled_taxonomies'] ) || ! is_array( $settings['enabled_taxonomies'] ) ) {
			return false;
		}

		return in_array( $term->taxonomy, $settings['enabled_taxonomies'], true );
	}

	/**
	 * Resolve title for taxonomy page.
	 *
	 * @param array<string, mixed> $settings Module settings.
	 * @param WP_Term              $term     Queried term.
	 *
	 * @return string
	 */
	private static function resolve_title( array $settings, WP_Term $term ): string {
		$override = (string) get_term_meta( $term->term_id, Constants::META_TERM_TITLE, true );
		if ( '' !== trim( $override ) ) {
			return trim( wp_strip_all_tags( $override ) );
		}

		$template = '';
		if ( isset( $settings['templates']['global']['title'] ) && is_string( $settings['templates']['global']['title'] ) ) {
			$template = $settings['templates']['global']['title'];
		}

		return self::render_template( $template, $settings, $term );
	}

	/**
	 * Resolve description for taxonomy page.
	 *
	 * @param array<string, mixed> $settings Module settings.
	 * @param WP_Term              $term     Queried term.
	 *
	 * @return string
	 */
	private static function resolve_description( array $settings, WP_Term $term ): string {
		$override = (string) get_term_meta( $term->term_id, Constants::META_TERM_DESCRIPTION, true );
		if ( '' !== trim( $override ) ) {
			return trim( wp_strip_all_tags( $override ) );
		}

		$template = '';
		if ( isset( $settings['templates']['global']['description'] ) && is_string( $settings['templates']['global']['description'] ) ) {
			$template = $settings['templates']['global']['description'];
		}

		$rendered = self::render_template( $template, $settings, $term );
		if ( '' !== $rendered ) {
			return $rendered;
		}

		return trim( wp_strip_all_tags( (string) $term->description ) );
	}

	/**
	 * Resolve canonical URL for taxonomy page.
	 *
	 * @param WP_Term $term Queried term.
	 *
	 * @return string
	 */
	private static function resolve_canonical( WP_Term $term ): string {
		$override = (string) get_term_meta( $term->term_id, Constants::META_TERM_CANONICAL, true );
		if ( '' !== trim( $override ) ) {
			return esc_url_raw( $override );
		}

		$link = get_term_link( $term );
		if ( is_wp_error( $link ) || ! is_string( $link ) ) {
			return '';
		}

		return $link;
	}

	/**
	 * Render template tokens.
	 *
	 * @param string               $template Template string.
	 * @param array<string, mixed> $settings Module settings.
	 * @param WP_Term              $term     Queried term.
	 *
	 * @return string
	 */
	private static function render_template( string $template, array $settings, WP_Term $term ): string {
		if ( '' === trim( $template ) ) {
			return '';
		}

		$separator = '–';
		if ( isset( $settings['templates']['separator'] ) && is_string( $settings['templates']['separator'] ) ) {
			$candidate = trim( $settings['templates']['separator'] );
			if ( '' !== $candidate ) {
				$separator = $candidate;
			}
		}

		$custom_1 = '';
		$custom_2 = '';
		$custom_3 = '';
		if ( isset( $settings['templates']['custom_tokens'] ) && is_array( $settings['templates']['custom_tokens'] ) ) {
			$tokens = $settings['templates']['custom_tokens'];
			if ( isset( $tokens['custom_1'] ) && is_string( $tokens['custom_1'] ) ) {
				$custom_1 = $tokens['custom_1'];
			}
			if ( isset( $tokens['custom_2'] ) && is_string( $tokens['custom_2'] ) ) {
				$custom_2 = $tokens['custom_2'];
			}
			if ( isset( $tokens['custom_3'] ) && is_string( $tokens['custom_3'] ) ) {
				$custom_3 = $tokens['custom_3'];
			}
		}

		return RenderTermTemplate::render(
			$template,
			array(
				'%term_name%'        => $term->name,
				'%term_description%' => trim( wp_strip_all_tags( (string) $term->description ) ),
				'%site_name%'        => (string) get_bloginfo( 'name' ),
				'%separator%'        => ' ' . $separator . ' ',
				'%custom_1%'         => $custom_1,
				'%custom_2%'         => $custom_2,
				'%custom_3%'         => $custom_3,
			)
		);
	}
}
