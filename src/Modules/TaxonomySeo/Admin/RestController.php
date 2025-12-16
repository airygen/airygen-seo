<?php
/**
 * REST controller for Taxonomy SEO previews.
 *
 * @package Airygen\Modules\TaxonomySeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\TaxonomySeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\TaxonomySeo\Domain\RenderTermTemplate;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term_Query;

/**
 * Exposes taxonomy preview helpers for admin settings UI.
 */
final class RestController {

	/**
	 * Permission callback.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return bool|WP_Error
	 */
	public static function can_preview( WP_REST_Request $request ) {
		unset( $request );

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_FORBIDDEN,
				__( 'You are not allowed to preview taxonomy SEO.', 'airygen-seo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle preview requests.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_preview( WP_REST_Request $request ) {
		$category_id = (int) $request->get_param( 'category' );
		$tag_id      = (int) $request->get_param( 'tag' );

		$categories = self::search_terms( 'category' );
		$tags       = self::search_terms( 'post_tag' );

		$selected_term = self::resolve_selected_term( $category_id, $tag_id, $categories, $tags );
		$head          = self::build_head_sample( $selected_term );

		return rest_ensure_response(
			array(
				'categories'         => array_values( $categories ),
				'tags'               => array_values( $tags ),
				'selectedCategoryId' => ( 'category' === $selected_term['taxonomy'] ) ? (int) $selected_term['id'] : 0,
				'selectedTagId'      => ( 'post_tag' === $selected_term['taxonomy'] ) ? (int) $selected_term['id'] : 0,
				'head'               => $head,
			)
		);
	}

	/**
	 * Load the latest taxonomy terms for preview.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return array<int, array{id:int,title:string}>
	 */
	private static function search_terms( string $taxonomy ): array {
		$query = new WP_Term_Query(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 10,
				'orderby'    => 'term_id',
				'order'      => 'DESC',
			)
		);

		if ( ! is_array( $query->terms ) || empty( $query->terms ) ) {
			return array();
		}

		$terms = array();
		foreach ( $query->terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$title = trim( (string) $term->name );
			if ( '' === $title ) {
				$title = '#' . (string) $term->term_id;
			}

			$terms[ (int) $term->term_id ] = array(
				'id'    => (int) $term->term_id,
				'title' => $title,
			);
		}

		return $terms;
	}

	/**
	 * Resolve selected term from request and available term pools.
	 *
	 * @param int                                       $category_id Requested category ID.
	 * @param int                                       $tag_id      Requested tag ID.
	 * @param array<int, array{id:int,title:string}>    $categories  Category options.
	 * @param array<int, array{id:int,title:string}>    $tags        Tag options.
	 * @return array{id:int,taxonomy:string}
	 */
	private static function resolve_selected_term( int $category_id, int $tag_id, array $categories, array $tags ): array {
		if ( $category_id > 0 && isset( $categories[ $category_id ] ) ) {
			return array(
				'id'       => $category_id,
				'taxonomy' => 'category',
			);
		}

		if ( $tag_id > 0 && isset( $tags[ $tag_id ] ) ) {
			return array(
				'id'       => $tag_id,
				'taxonomy' => 'post_tag',
			);
		}

		$category_ids = array_keys( $categories );
		if ( ! empty( $category_ids ) ) {
			return array(
				'id'       => (int) $category_ids[0],
				'taxonomy' => 'category',
			);
		}

		$tag_ids = array_keys( $tags );
		if ( ! empty( $tag_ids ) ) {
			return array(
				'id'       => (int) $tag_ids[0],
				'taxonomy' => 'post_tag',
			);
		}

		return array(
			'id'       => 0,
			'taxonomy' => '',
		);
	}

	/**
	 * Build head sample from selected term.
	 *
	 * @param array{id:int,taxonomy:string} $selected_term Selected term.
	 * @return array{title:string,description:string,canonical:string}
	 */
	private static function build_head_sample( array $selected_term ): array {
		$empty = array(
			'title'       => '',
			'description' => '',
			'canonical'   => '',
		);

		$term_id  = isset( $selected_term['id'] ) ? (int) $selected_term['id'] : 0;
		$taxonomy = isset( $selected_term['taxonomy'] ) ? (string) $selected_term['taxonomy'] : '';
		$term     = ( $term_id > 0 && '' !== $taxonomy ) ? get_term( $term_id, $taxonomy ) : null;
		$settings = Settings::get();

		if ( ! $term instanceof \WP_Term ) {
			return $empty;
		}

		$title_override = trim( (string) get_term_meta( $term_id, Constants::META_TERM_TITLE, true ) );
		$desc_override  = trim( (string) get_term_meta( $term_id, Constants::META_TERM_DESCRIPTION, true ) );
		$canonical      = trim( (string) get_term_meta( $term_id, Constants::META_TERM_CANONICAL, true ) );

		$title = $title_override;
		if ( '' === $title ) {
			$template = '';
			if ( isset( $settings['templates']['global']['title'] ) && is_string( $settings['templates']['global']['title'] ) ) {
				$template = $settings['templates']['global']['title'];
			}
			$title = self::render_template( $template, $settings, $term );
		}

		$description = $desc_override;
		if ( '' === $description ) {
			$template = '';
			if ( isset( $settings['templates']['global']['description'] ) && is_string( $settings['templates']['global']['description'] ) ) {
				$template = $settings['templates']['global']['description'];
			}
			$description = self::render_template( $template, $settings, $term );
			if ( '' === $description ) {
				$description = trim( wp_strip_all_tags( (string) $term->description ) );
			}
		}

		if ( '' === $canonical ) {
			$term_link = get_term_link( $term );
			if ( is_string( $term_link ) && ! is_wp_error( $term_link ) ) {
				$canonical = $term_link;
			}
		}

		return array(
			'title'       => $title,
			'description' => $description,
			'canonical'   => $canonical,
		);
	}

	/**
	 * Render taxonomy template.
	 *
	 * @param string               $template Template string.
	 * @param array<string, mixed> $settings Module settings.
	 * @param \WP_Term             $term     Term object.
	 * @return string
	 */
	private static function render_template( string $template, array $settings, \WP_Term $term ): string {
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
