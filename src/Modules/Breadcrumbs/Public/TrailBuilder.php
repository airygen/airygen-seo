<?php
/**
 * Builds breadcrumb trails from the current WP_Query context.
 *
 * @package Airygen\Modules\Breadcrumbs\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Breadcrumbs\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\Breadcrumbs\Admin\Settings;
use Airygen\Modules\Breadcrumbs\Domain\Trail;
use WP_Post;
use WP_Term;

/**
 * Generates breadcrumb trails from WordPress data.
 */
final class TrailBuilder {

	/**
	 * Build a breadcrumb trail for the current query.
	 *
	 * @return Trail|null
	 */
	public static function from_current_query(): ?Trail {
		if ( ! ModuleSettings::is_enabled( 'breadcrumbs' ) ) {
			return null;
		}

		$config = Settings::get();

		if ( empty( $config['manual_output_enabled'] ) && empty( $config['auto_injection_enabled'] ) ) {
			return null;
		}

		if ( is_front_page() && ! is_paged() ) {
			return null;
		}

		$items = array();

		if ( ! empty( $config['home']['display'] ) ) {
			$items[] = array(
				'label' => $config['home']['label'],
				'url'   => trailingslashit( $config['home']['url'] ?? home_url() ),
			);
		}

		$context_items = self::build_context_items( $config );
		$items         = array_merge( $items, $context_items );

		if ( empty( $items ) ) {
			return null;
		}

		if ( is_singular() && empty( $config['display']['showCurrent'] ) && count( $items ) > 1 ) {
			array_pop( $items );
		}

		if ( ! empty( $config['display']['showPagination'] ) && is_paged() ) {
			$current_page = max( 2, (int) get_query_var( 'paged', 1 ) );
			$items[]      = array(
				// translators: %s is the current page number in pagination.
				'label'          => sprintf( __( 'Page %s', 'airygen-seo' ), $current_page ),
				'url'            => '',
				'hide_in_schema' => true,
			);
		}

		/**
		 * Allow filtering the breadcrumb items before rendering.
		 *
		 * @param array<int, array<string, mixed>> $items Breadcrumb items.
		 * @param array<string, mixed>             $config Settings payload.
		 */
		$items = apply_filters( Constants::HOOK_BREADCRUMBS_ITEMS, $items, $config ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		$trail = new Trail( $items );
		return $trail->is_empty() ? null : $trail;
	}

	/**
	 * Build crumb items for the current query.
	 *
	 * @param array<string, mixed> $config Settings payload.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_context_items( array $config ): array {
		if ( is_404() ) {
			return array(
				array(
					'label' => $config['labels']['error'],
					'url'   => '',
				),
			);
		}

		if ( is_search() ) {
			return array(
				array(
					'label' => sprintf( $config['labels']['search'], get_search_query() ),
					'url'   => remove_query_arg( 'paged', get_search_link() ),
				),
			);
		}

		if ( is_home() && ! is_front_page() ) {
			return self::blog_items();
		}

		if ( is_singular() ) {
			return self::singular_items( $config );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return self::term_items( $config );
		}

		if ( is_post_type_archive() ) {
			return self::post_type_archive_items( $config );
		}

		if ( is_author() ) {
			$author = get_queried_object();

			return array(
				array(
					'label' => $author instanceof \WP_User ? $author->display_name : __( 'Author', 'airygen-seo' ),
					'url'   => $author ? get_author_posts_url( $author->ID ) : '',
				),
			);
		}

		if ( is_date() ) {
			return self::date_items();
		}

		return array();
	}

	/**
	 * Build crumb items for singular posts/pages.
	 *
	 * @param array<string, mixed> $config Settings payload.
	 * @return array<int, array<string, mixed>>
	 */
	private static function singular_items( array $config ): array {
		global $post;

		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$items     = array();
		$post_type = get_post_type_object( $post->post_type );

		if ( ! $post_type ) {
			return array();
		}

		if ( 'post' === $post->post_type && ! empty( $config['display']['showBlog'] ) ) {
			$items = array_merge( $items, self::blog_items() );
		} elseif ( 'post' !== $post->post_type && ! empty( $post_type->has_archive ) ) {
			$items[] = array(
				'label' => $post_type->labels->name,
				'url'   => get_post_type_archive_link( $post->post_type ),
			);
		}

		if ( self::is_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
				$items[] = array(
					'label' => get_the_title( $ancestor ),
					'url'   => get_permalink( $ancestor ),
				);
			}
		} elseif ( 'post' === $post->post_type ) {
			$items = array_merge( $items, self::primary_term_items( $post, $config ) );
		}

		$items[] = array(
			'label' => get_the_title( $post ),
			'url'   => get_permalink( $post ),
		);

		return $items;
	}

	/**
	 * Build crumb items for taxonomy archives.
	 *
	 * @param array<string, mixed> $config Settings payload.
	 * @return array<int, array<string, mixed>>
	 */
	private static function term_items( array $config ): array {
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return array();
		}

		$items = array();

		if ( 'post' === get_query_var( 'post_type' ) || is_category() || is_tag() ) {
			if ( ! empty( $config['display']['showBlog'] ) ) {
				$items = array_merge( $items, self::blog_items() );
			}
		}

		if ( ! empty( $config['display']['showAncestors'] ) && is_taxonomy_hierarchical( $term->taxonomy ) ) {
			foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, $term->taxonomy );
				if ( $ancestor instanceof WP_Term ) {
					$items[] = array(
						'label' => self::format_archive_label( $config, $ancestor->name ),
						'url'   => get_term_link( $ancestor ),
					);
				}
			}
		}

		$items[] = array(
			'label' => self::format_archive_label( $config, $term->name ),
			'url'   => get_term_link( $term ),
		);

		return $items;
	}

	/**
	 * Build crumbs for primary taxonomy term assigned to a post.
	 *
	 * @param WP_Post              $post   Current post.
	 * @param array<string, mixed> $config Settings payload.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function primary_term_items( WP_Post $post, array $config ): array {
		$items = array();
		$terms = get_the_category( $post );

		if ( empty( $terms ) || ! is_array( $terms ) ) {
			return $items;
		}

		$primary = $terms[0];

		if ( ! empty( $config['display']['showAncestors'] ) ) {
			foreach ( array_reverse( get_ancestors( $primary->term_id, 'category' ) ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, 'category' );
				if ( $ancestor instanceof WP_Term ) {
					$items[] = array(
						'label' => self::format_archive_label( $config, $ancestor->name ),
						'url'   => get_term_link( $ancestor ),
					);
				}
			}
		}

		$items[] = array(
			'label' => self::format_archive_label( $config, $primary->name ),
			'url'   => get_term_link( $primary ),
		);

		return $items;
	}

	/**
	 * Build crumbs for date archives.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function date_items(): array {
		$items = array();

		if ( is_year() || is_month() || is_day() ) {
			$year = get_query_var( 'year' );
			if ( $year ) {
				$items[] = array(
					'label' => (string) $year,
					'url'   => get_year_link( (int) $year ),
				);
			}
		}

		if ( is_month() || is_day() ) {
			$month = get_query_var( 'monthnum' );
			if ( $month ) {
				$items[] = array(
					'label' => wp_strip_all_tags( single_month_title( ' ', false ) ),
					'url'   => get_month_link( (int) get_query_var( 'year' ), (int) $month ),
				);
			}
		}

		if ( is_day() ) {
			$day = get_query_var( 'day' );
			if ( $day ) {
				$items[] = array(
					'label' => (string) $day,
					'url'   => get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) $day ),
				);
			}
		}

		return $items;
	}

	/**
	 * Crumbs for the posts page (blog) when configured via Settings > Reading.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function blog_items(): array {
		$blog_id = (int) get_option( 'page_for_posts' );
		if ( 0 === $blog_id ) {
			return array();
		}

		return array(
			array(
				'label' => get_the_title( $blog_id ),
				'url'   => get_permalink( $blog_id ),
			),
		);
	}

	/**
	 * Crumbs for post type archives.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function post_type_archive_items( array $config ): array {
		$post_type = get_query_var( 'post_type' );
		if ( empty( $post_type ) ) {
			$post_type = get_post_type();
		}

		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		}

		if ( ! $post_type ) {
			return array();
		}

		$type_object = get_post_type_object( $post_type );
		if ( ! $type_object || empty( $type_object->has_archive ) ) {
			return array();
		}

		return array(
			array(
				'label' => sprintf( $config['labels']['archive'], $type_object->labels->name ),
				'url'   => get_post_type_archive_link( $post_type ),
			),
		);
	}

	/**
	 * Determine if the post type is hierarchical.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private static function is_hierarchical( string $post_type ): bool {
		$type_object = get_post_type_object( $post_type );
		return $type_object && $type_object->hierarchical;
	}

	/**
	 * Format archive labels respecting user preferences.
	 *
	 * @param array<string, mixed> $config Settings payload.
	 * @param string               $base   Default label.
	 *
	 * @return string
	 */
	private static function format_archive_label( array $config, string $base ): string {
		if ( ! empty( $config['display']['hideTaxonomy'] ) ) {
			return $base;
		}

		return sprintf( $config['labels']['archive'], $base );
	}
}
