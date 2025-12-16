<?php
/**
 * Outputs head elements for OnPage SEO metadata.
 *
 * @package Airygen\Modules\OnPageSeo\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\OnPageSeo\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\OnPageSeo\Admin\Settings as OnPageSettings;
use Airygen\Modules\OnPageSeo\Domain\Dto\HeadMeta;
use Airygen\Modules\OnPageSeo\Domain\Service\BuildHeadMeta;
use Airygen\Support\Meta\PostData;

/**
 * Emits the <head> tags derived from stored metadata.
 */
final class HeadEmitter {

	/**
	 * Cached head metadata for the current request.
	 *
	 * @var HeadMeta|null
	 */
	private static $cached_meta = null;

	/**
	 * Cached settings array.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $cached_settings = null;

	/**
	 * Emit head tags for singular content.
	 *
	 * @return void
	 */
	public static function emit(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$head_meta = self::resolve_head_meta( $post_id );
		if ( null === $head_meta ) {
			return;
		}

		self::render_title( $head_meta->get_title() );
		self::render_meta_description( $head_meta->get_description() );
		self::render_canonical( $head_meta->get_canonical() );
		self::render_robots( $head_meta->get_robots() );
	}

	/**
	 * Render the <title> tag if the computed title is available.
	 *
	 * @param string|null $title Title value.
	 *
	 * @return void
	 */
	private static function render_title( ?string $title ): void {
		if ( null === $title || current_theme_supports( 'title-tag' ) ) {
			return;
		}

		if ( ! self::should_output( 'title' ) ) {
			return;
		}

		printf(
			"<title>%s</title>\n",
			esc_html( $title )
		);
	}

	/**
	 * Render the meta description tag when present.
	 *
	 * @param string|null $description Description value.
	 *
	 * @return void
	 */
	private static function render_meta_description( ?string $description ): void {
		if ( null === $description || ! self::should_output( 'description' ) ) {
			return;
		}

		printf(
			"<meta name=\"description\" content=\"%s\" />\n",
			esc_attr( $description )
		);
	}

	/**
	 * Render the canonical link tag when present.
	 *
	 * @param string|null $canonical Canonical URL.
	 *
	 * @return void
	 */
	private static function render_canonical( ?string $canonical ): void {
		if ( null === $canonical || ! self::should_output( 'canonical' ) ) {
			return;
		}

		printf(
			"<link rel=\"canonical\" href=\"%s\" />\n",
			esc_url( $canonical )
		);
	}

	/**
	 * Render the robots directives meta tag when explicitly set.
	 *
	 * @param string|null $robots Robots directives.
	 *
	 * @return void
	 */
	private static function render_robots( ?string $robots ): void {
		if ( null === $robots || ! self::should_output( 'robots' ) ) {
			return;
		}

		printf(
			"<meta name=\"robots\" content=\"%s\" />\n",
			esc_attr( $robots )
		);
	}

	/**
	 * Fetch an excerpt fallback for metadata decisions.
	 *
	 * @param int $post_id Post identifier.
	 *
	 * @return string
	 */
	private static function get_excerpt( int $post_id ): string {
		$excerpt = get_post_field( 'post_excerpt', $post_id );

		if ( ! empty( $excerpt ) ) {
			return (string) $excerpt;
		}

		$content = get_post_field( 'post_content', $post_id );
		return (string) wp_trim_words( wp_strip_all_tags( (string) $content ), 40 );
	}

	/**
	 * Filter document title output to ensure parity with emitted metadata.
	 *
	 * @param string $title Default title.
	 *
	 * @return string
	 */
	public static function filter_document_title( string $title ): string {
		if ( ! is_singular() || ! self::should_output( 'title' ) ) {
			return $title;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return $title;
		}

		$head_meta = self::resolve_head_meta( $post_id );
		if ( null === $head_meta || null === $head_meta->get_title() ) {
			return $title;
		}

		return $head_meta->get_title();
	}

	/**
	 * Resolve and cache the HeadMeta DTO for the current post.
	 *
	 * @param int $post_id Post identifier.
	 *
	 * @return HeadMeta|null
	 */
	private static function resolve_head_meta( int $post_id ): ?HeadMeta {
		if ( self::$cached_meta instanceof HeadMeta ) {
			return self::$cached_meta;
		}

		$post_data      = PostData::get( $post_id );
		$canonical_meta = $post_data['canonical'];
		$skip_canonical = Constants::CANONICAL_NONE_TOKEN === $canonical_meta;
		$permalink      = $skip_canonical ? null : get_permalink( $post_id );
		$post_type_raw  = get_post_type( $post_id );
		$post_type      = $post_type_raw ? (string) $post_type_raw : '';
		$settings       = self::settings();
		$custom_tokens  = isset( $settings['templates']['custom_tokens'] ) && is_array( $settings['templates']['custom_tokens'] )
		? $settings['templates']['custom_tokens']
		: array();

		$meta = array(
			'meta_title'       => $post_data['title'],
			'meta_description' => $post_data['description'],
			'post_title'       => get_the_title( $post_id ),
			'post_excerpt'     => self::get_excerpt( $post_id ),
			'permalink'        => $permalink,
			'canonical'        => $skip_canonical ? null : $canonical_meta,
			'robots'           => $post_data['robots'],
			'post_type'        => $post_type,
			'templates'        => isset( $settings['templates'] ) && is_array( $settings['templates'] ) ? $settings['templates'] : array(),
			'site_name'        => get_bloginfo( 'name' ),
			'site_description' => get_bloginfo( 'description' ),
			'separator'        => isset( $settings['templates']['separator'] ) ? (string) $settings['templates']['separator'] : '–',
			'custom_1'         => isset( $custom_tokens['custom_1'] ) ? (string) $custom_tokens['custom_1'] : '',
			'custom_2'         => isset( $custom_tokens['custom_2'] ) ? (string) $custom_tokens['custom_2'] : '',
			'custom_3'         => isset( $custom_tokens['custom_3'] ) ? (string) $custom_tokens['custom_3'] : '',
		);

		$meta = apply_filters( 'airygen_onpage_resolve_meta_payload', $meta, $post_id );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		self::$cached_meta = BuildHeadMeta::for_post( $meta );
		return self::$cached_meta;
	}

	/**
	 * Retrieve sanitized settings from the options layer.
	 *
	 * @return array<string, mixed>
	 */
	private static function settings(): array {
		if ( is_array( self::$cached_settings ) ) {
			return self::$cached_settings;
		}

		self::$cached_settings = OnPageSettings::get();
		return self::$cached_settings;
	}

	/**
	 * Determine whether a given head output is enabled.
	 *
	 * @param string $key Identifier (title, description, canonical, robots).
	 *
	 * @return bool
	 */
	private static function should_output( string $key ): bool {
		$settings = self::settings();

		return ! empty( $settings['output'][ $key ] );
	}
}
