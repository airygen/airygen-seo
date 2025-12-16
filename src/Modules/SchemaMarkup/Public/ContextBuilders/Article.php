<?php
/**
 * Builds article schema context using WordPress data sources.
 *
 * @package Airygen\Modules\SchemaMarkup\Public\ContextBuilders
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Public\ContextBuilders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\AuthorSeo\Admin\Settings as AuthorSeoSettings;
use Airygen\Modules\OnPageSeo\Domain\Service\BuildHeadMeta;
use Airygen\Modules\SchemaMarkup\Domain\Contexts\ArticleContext;
use Airygen\Modules\SchemaMarkup\Domain\Contexts\OrganizationContext;
use Airygen\Support\Meta\PostData;

/**
 * Composes article schema context from the current query.
 */
final class Article {

	/**
	 * Build an Article context for the current singular query.
	 *
	 * @param array<string, mixed> $options       Schema settings options.
	 * @param string               $site_name     Site display name.
	 * @param string               $site_desc     Site description fallback.
	 * @param OrganizationContext  $organization  Organization context for publisher data.
	 *
	 * @return ArticleContext|null
	 */
	public static function from_current_query( array $options, string $site_name, string $site_desc, OrganizationContext $organization ): ?ArticleContext {
		if ( ! is_singular() ) {
			return null;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return null;
		}

		return self::from_post_id( $post_id, $options, $site_name, $site_desc, $organization );
	}

	/**
	 * Build an Article context for a specific post.
	 *
	 * @param int                  $post_id       Post identifier.
	 * @param array<string, mixed> $options      Schema settings options.
	 * @param string               $site_name    Site display name.
	 * @param string               $site_desc    Site description fallback.
	 * @param OrganizationContext  $organization Organization context for publisher data.
	 *
	 * @return ArticleContext|null
	 */
	public static function from_post_id( int $post_id, array $options, string $site_name, string $site_desc, OrganizationContext $organization ): ?ArticleContext {
		if ( $post_id <= 0 ) {
			return null;
		}

		$post_type_raw = get_post_type( $post_id );
		$post_type     = $post_type_raw ? (string) $post_type_raw : '';

		// When enabled, only the standard "post" type can emit Article schema.
		if ( ! empty( $options['article_only_post'] ) && 'post' !== $post_type ) {
			return null;
		}

		$post_data = PostData::get( $post_id );
		$meta      = BuildHeadMeta::for_post(
			array(
				'meta_title'       => $post_data['title'],
				'meta_description' => $post_data['description'],
				'post_title'       => get_the_title( $post_id ),
				'post_excerpt'     => get_post_field( 'post_excerpt', $post_id ),
				'permalink'        => get_permalink( $post_id ),
				'canonical'        => $post_data['canonical'],
				'robots'           => $post_data['robots'],
			)
		);

		$article_type = self::resolve_article_type( $options, $post_type );
		$post_excerpt = get_post_field( 'post_excerpt', $post_id );
		$post_excerpt = is_string( $post_excerpt ) ? trim( wp_strip_all_tags( $post_excerpt ) ) : '';

		$description = $meta->get_description();
		if ( null === $description || '' === $description ) {
			if ( '' !== $post_excerpt ) {
				$description = $post_excerpt;
			}
		}

		$canonical = $meta->get_canonical();
		if ( null === $canonical || '' === $canonical ) {
			$canonical = get_permalink( $post_id );
		}

		$published   = get_post_time( 'c', true, $post_id );
		$modified    = get_post_modified_time( 'c', true, $post_id );
		$author_id   = (int) get_post_field( 'post_author', $post_id );
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$image_id    = get_post_thumbnail_id( $post_id );
		$image       = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : get_the_post_thumbnail_url( $post_id, 'full' );
		$status      = get_post_status( $post_id );
		$author_url  = get_author_posts_url( $author_id );

		if ( false === $published || '' === $published ) {
			$published = null;
		}

		if ( false === $modified || '' === $modified ) {
			$modified = null;
		}

		if ( false === $image || null === $image ) {
			$image = '';
		}

		if ( '' === $author_name ) {
			$author_name = $site_name;
		}

		$article_data = array(
			'headline'      => $meta->get_title(),
			'description'   => $description,
			'url'           => $canonical,
			'datePublished' => $published,
			'dateModified'  => $modified,
			'status'        => $status,
			'image'         => $image,
		);

		$author = array(
			'name' => $author_name,
			'type' => 'Person',
			'url'  => $author_url ? $author_url : null,
		);
		if ( '' !== $author_url ) {
			$author['@id'] = untrailingslashit( $author_url ) . '#author';
		}

		$same_as = self::resolve_author_social_profiles( $author_id );
		if ( ! empty( $same_as ) ) {
			$author['sameAs'] = $same_as;
		}

		$include_author = self::should_include_author( $options );

		$publisher = $organization->to_publisher_fragment();
		if ( empty( $publisher['name'] ) ) {
			$publisher['name'] = $site_name;
		}

		return ArticleContext::from_payload(
			$article_type,
			$article_data,
			$include_author ? $author : array(),
			$publisher
		);
	}

	/**
	 * Resolve final article type for the given post type.
	 *
	 * @param array<string, mixed> $options  Parsed schema settings.
	 * @param string               $post_type Post type slug.
	 */
	private static function resolve_article_type( array $options, string $post_type ): string {
		$global_default = ! empty( $options['article_type'] ) ? (string) $options['article_type'] : 'Article';

		if ( '' === $post_type ) {
			return $global_default;
		}

		if ( ! empty( $options['post_type_defaults'] ) && is_array( $options['post_type_defaults'] ) ) {
			$map           = $options['post_type_defaults'];
			$post_type_key = sanitize_key( $post_type );

			if ( isset( $map[ $post_type_key ] ) && is_string( $map[ $post_type_key ] ) && '' !== $map[ $post_type_key ] ) {
				return $map[ $post_type_key ];
			}
		}

		return $global_default;
	}

	/**
	 * Determine whether author data should be included.
	 *
	 * @param array<string, mixed> $options Schema settings.
	 */
	private static function should_include_author( array $options ): bool {
		if ( isset( $options['article_show_author'] ) ) {
			return ! empty( $options['article_show_author'] );
		}

		return true;
	}

	/**
	 * Resolve author social profiles from Author SEO settings.
	 *
	 * @param int $author_id Author user ID.
	 *
	 * @return array<int, string>
	 */
	private static function resolve_author_social_profiles( int $author_id ): array {
		if ( $author_id <= 0 ) {
			return array();
		}

		$settings = AuthorSeoSettings::get();
		if ( empty( $settings['enabled'] ) ) {
			return array();
		}

		$user_meta = get_user_meta( $author_id, Constants::USER_META_SOCIAL_PROFILES, true );
		$profiles  = self::normalize_profiles( $user_meta );
		if ( ! empty( $profiles ) ) {
			return $profiles;
		}

		if ( ! isset( $settings['social_profiles'] ) || ! is_array( $settings['social_profiles'] ) ) {
			return array();
		}

		return self::normalize_profiles( $settings['social_profiles'] );
	}

	/**
	 * Normalize social profile payload into URL list.
	 *
	 * @param mixed $profiles Raw profiles.
	 *
	 * @return array<int, string>
	 */
	private static function normalize_profiles( $profiles ): array {
		$values = array();

		if ( is_array( $profiles ) ) {
			foreach ( $profiles as $profile ) {
				if ( ! is_string( $profile ) ) {
					continue;
				}
				$values[] = $profile;
			}
		} elseif ( is_string( $profiles ) ) {
			$split = preg_split( '/[\r\n,]+/', $profiles );
			if ( is_array( $split ) ) {
				$values = $split;
			}
		}

		$normalized = array();
		foreach ( $values as $value ) {
			$url = esc_url_raw( trim( (string) $value ) );
			if ( '' === $url ) {
				continue;
			}
			if ( in_array( $url, $normalized, true ) ) {
				continue;
			}
			$normalized[] = $url;
		}

		return $normalized;
	}
}
