<?php
/**
 * Runtime filter that inserts missing image attributes.
 *
 * @package Airygen\Modules\ImageSeo\Public\Filters
 */

declare(strict_types=1);

namespace Airygen\Modules\ImageSeo\Public\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\ImageSeo\Domain\Dto\ImageContext;
use Airygen\Modules\ImageSeo\Domain\Service\GenerateAttribute;
use Airygen\Modules\ImageSeo\Settings\Repository;
use Airygen\Support\Meta\PostData;
use WP_Post;

/**
 * Adds alt/title attributes to rendered markup when missing.
 */
final class AddAttributes {

	private Repository $settings;
	private GenerateAttribute $generator;
	private bool $should_add_alt;
	private bool $should_add_title;
	private string $alt_template;
	private string $title_template;

	/**
	 * @param Repository        $settings  Settings repository.
	 * @param GenerateAttribute $generator Template engine.
	 */
	public function __construct( Repository $settings, GenerateAttribute $generator ) {
		$this->settings         = $settings;
		$this->generator        = $generator;
		$this->should_add_alt   = $settings->should_add_alt();
		$this->should_add_title = $settings->should_add_title();
		$this->alt_template     = $settings->alt_format();
		$this->title_template   = $settings->title_format();
	}

	/**
	 * Register WordPress filters.
	 *
	 * @return void
	 */
	public function hook(): void {
		add_filter( 'the_content', array( $this, 'filter_content' ), 9999 );
		add_filter( 'post_thumbnail_html', array( $this, 'filter_post_thumbnail' ), 9999, 5 );
		add_filter( 'woocommerce_single_product_image_thumbnail_html', array( $this, 'filter_woocommerce_thumbnail' ), 9999, 2 );
	}

	/**
	 * Filter post content.
	 *
	 * @param string $content Original HTML.
	 *
	 * @return string
	 */
	public function filter_content( string $content ): string {
		if ( ! $this->is_markup_candidate( $content ) ) {
			return $content;
		}

		$post_id = get_the_ID();

		return $this->process_markup( $content, is_int( $post_id ) && $post_id > 0 ? $post_id : null );
	}

	/**
	 * Filter featured image markup.
	 *
	 * @param string   $html      Original HTML.
	 * @param int      $post_id   Post ID.
	 * @param int      $thumbnail Attachment ID.
	 * @param string   $size      Requested size.
	 * @param string[] $attr      Attributes.
	 *
	 * @return string
	 */
	// phpcs:disable
	public function filter_post_thumbnail( string $html, int $post_id = 0, int $thumbnail = 0, string $size = '', array $attr = array() ): string {
		if ( ! $this->is_markup_candidate( $html ) ) {
			return $html;
		}

		return $this->process_markup( $html, $post_id > 0 ? $post_id : null );
	}
	// phpcs:enable

	/**
	 * Filter WooCommerce product thumbnails when available.
	 *
	 * @param string     $html              Image HTML.
	 * @param int|string $post_thumbnail_id Attachment ID.
	 *
	 * @return string
	 */
	public function filter_woocommerce_thumbnail( string $html, int|string $post_thumbnail_id = 0 ): string {
		if ( ! $this->is_markup_candidate( $html ) ) {
			return $html;
		}

		$post_id      = null;
		$thumbnail_id = 0;

		if ( is_int( $post_thumbnail_id ) ) {
			$thumbnail_id = $post_thumbnail_id;
		} elseif ( is_string( $post_thumbnail_id ) ) {
			$thumbnail_id = absint( $post_thumbnail_id );
		}

		if ( $thumbnail_id > 0 ) {
			$attachment = get_post( $thumbnail_id );
			if ( $attachment instanceof WP_Post && $attachment->post_parent > 0 ) {
				$post_id = (int) $attachment->post_parent;
			}
		}

		return $this->process_markup( $html, $post_id );
	}

	/**
	 * Determine whether markup should be processed.
	 *
	 * @param string $markup HTML fragment.
	 *
	 * @return bool
	 */
	private function is_markup_candidate( string $markup ): bool {
		if ( ! $this->should_add_alt && ! $this->should_add_title ) {
			return false;
		}

		return '' !== trim( $markup ) && str_contains( $markup, '<img' );
	}

	/**
	 * Process an HTML fragment and append attributes when needed.
	 *
	 * @param string   $html    HTML fragment.
	 * @param int|null $post_id Current post ID.
	 *
	 * @return string
	 */
	private function process_markup( string $html, ?int $post_id ): string {
		$placeholders = array();
		$working      = $this->strip_protected_blocks( $html, $placeholders );
		$post_title   = $this->resolve_post_title( $post_id );

		$updated = preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( array $matches ) use ( $post_id, $post_title ): string {
				if ( empty( $matches[0] ) ) {
					return '';
				}

				return $this->process_tag( $matches[0], $post_id, $post_title );
			},
			$working
		);

		if ( null === $updated ) {
			$updated = $working;
		}

		return $this->restore_protected_blocks( $updated, $placeholders );
	}

	/**
	 * Strip script/style blocks to avoid modifying their contents.
	 *
	 * @param string                $html         Original HTML.
	 * @param array<string, string> $placeholders Placeholder map to populate.
	 *
	 * @return string
	 */
	private function strip_protected_blocks( string $html, array &$placeholders ): string {
		$index = 0;

		$callback = static function ( array $matches ) use ( &$placeholders, &$index ): string {
			$token                  = sprintf( '##AIRYGEN_BLOCK_%d##', $index );
			$placeholders[ $token ] = $matches[0];
			++$index;
			return $token;
		};

		$result = preg_replace_callback( '/<(script|style)\b[^>]*>.*?<\/\1>/is', $callback, $html );

		return is_string( $result ) ? $result : $html;
	}

	/**
	 * Restore protected blocks to their original positions.
	 *
	 * @param string                $html         Modified HTML.
	 * @param array<string, string> $placeholders Placeholder map.
	 *
	 * @return string
	 */
	private function restore_protected_blocks( string $html, array $placeholders ): string {
		foreach ( $placeholders as $token => $original ) {
			$html = str_replace( $token, $original, $html );
		}

		return $html;
	}

	/**
	 * Process an individual <img> tag.
	 *
	 * @param string   $tag       Original tag.
	 * @param int|null $post_id   Related post ID.
	 * @param string   $post_title Related post title.
	 *
	 * @return string
	 */
	private function process_tag( string $tag, ?int $post_id, string $post_title ): string {
		$attributes = $this->parse_attributes( $tag );

		$existing_alt   = $attributes['alt'] ?? '';
		$existing_title = $attributes['title'] ?? '';

		$attachment_id    = $this->determine_attachment_id( $attributes );
		$attachment       = null;
		$attachment_title = '';

		if ( $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( $attachment instanceof WP_Post ) {
				$attachment_title = (string) get_the_title( $attachment );
				if ( ! $post_id && $attachment->post_parent > 0 ) {
					$post_id    = (int) $attachment->post_parent;
					$post_title = $this->resolve_post_title( $post_id );
				}
			}
		}

		$file_name = $this->determine_file_name( $attributes, $attachment_id );
		if ( '' === $file_name ) {
			$file_name = $post_title;
		}

		if ( '' === $post_title ) {
			$post_title = $this->resolve_post_title( $post_id );
		}

		$focus_keyphrase = '';
		$long_tail       = array();
		if ( $post_id ) {
			$focus_keyphrase = PostData::get_field( $post_id, 'focusKeyphrase' );
			$long_tail_raw   = PostData::get_field( $post_id, 'focusLongTail' );
			$long_tail       = $this->normalize_long_tail( $long_tail_raw );
		}

		$context = new ImageContext(
			$post_title,
			$file_name,
			$attachment_title,
			$existing_alt,
			$existing_title,
			$focus_keyphrase,
			$long_tail,
			$this->settings->separator(),
			$this->settings->custom_tokens()
		);

		$updated = $tag;

		if ( $this->should_add_alt ) {
			$updated = $this->ensure_attribute(
				$updated,
				'alt',
				$existing_alt,
				$this->alt_template,
				$context
			);
		}

		if ( $this->should_add_title ) {
			$updated = $this->ensure_attribute(
				$updated,
				'title',
				$existing_title,
				$this->title_template,
				$context
			);
		}

		return $updated;
	}

	/**
	 * Parse attributes from the tag.
	 *
	 * @param string $tag Tag markup.
	 *
	 * @return array<string, string>
	 */
	private function parse_attributes( string $tag ): array {
		$parsed = wp_kses_hair( $tag, wp_allowed_protocols() );
		$map    = array();

		foreach ( $parsed as $attribute ) {
			$name = strtolower( $attribute['name'] ?? '' );

			if ( '' === $name ) {
				continue;
			}

			$value        = isset( $attribute['value'] ) ? (string) $attribute['value'] : '';
			$map[ $name ] = html_entity_decode( $value, ENT_QUOTES );
		}

		return $map;
	}

	/**
	 * Resolve the most appropriate post title.
	 *
	 * @param int|null $post_id Post ID.
	 *
	 * @return string
	 */
	private function resolve_post_title( ?int $post_id ): string {
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				$title = get_the_title( $post );
				if ( is_string( $title ) && '' !== $title ) {
					return wp_strip_all_tags( $title );
				}
			}
		}

		$global_title = get_the_title();
		if ( is_string( $global_title ) && '' !== $global_title ) {
			return wp_strip_all_tags( $global_title );
		}

		$site_name = get_bloginfo( 'name', 'display' );
		if ( is_string( $site_name ) && '' !== $site_name ) {
			return wp_strip_all_tags( $site_name );
		}

		return 'Image';
	}

	/**
	 * Determine attachment ID from parsed attributes.
	 *
	 * @param array<string, string> $attributes Parsed attributes.
	 *
	 * @return int|null
	 */
	private function determine_attachment_id( array $attributes ): ?int {
		$candidates = array();

		foreach ( array( 'data-attachment-id', 'data-id', 'data-image-id', 'attachmentid' ) as $key ) {
			if ( isset( $attributes[ $key ] ) ) {
				$candidates[] = absint( $attributes[ $key ] );
			}
		}

		if ( isset( $attributes['class'] ) && preg_match( '/wp-image-(\d+)/', $attributes['class'], $match ) ) {
			$candidates[] = absint( $match[1] );
		}

		if ( isset( $attributes['id'] ) && preg_match( '/attachment[_-](\d+)/', $attributes['id'], $match ) ) {
			$candidates[] = absint( $match[1] );
		}

		$candidates = array_filter(
			$candidates,
			static function ( int $candidate ): bool {
				return $candidate > 0;
			}
		);

		if ( empty( $candidates ) ) {
			return null;
		}

		return (int) array_shift( $candidates );
	}

	/**
	 * Determine a descriptive filename.
	 *
	 * @param array<string, string> $attributes    Parsed attributes.
	 * @param int|null              $attachment_id Attachment ID.
	 *
	 * @return string
	 */
	private function determine_file_name( array $attributes, ?int $attachment_id ): string {
		$src_keys = array(
			'src',
			'data-src',
			'data-lazy-src',
			'data-original',
			'data-lazy',
			'data-image',
		);

		$source = '';

		foreach ( $src_keys as $key ) {
			if ( ! empty( $attributes[ $key ] ) ) {
				$source = $attributes[ $key ];
				break;
			}
		}

		if ( '' === $source && ! empty( $attributes['srcset'] ) ) {
			$source = $this->first_src_from_srcset( $attributes['srcset'] );
		}

		if ( '' === $source && $attachment_id ) {
			$attachment_url = wp_get_attachment_url( $attachment_id );
			$source         = is_string( $attachment_url ) ? $attachment_url : '';
		}

		return $this->file_name_from_url( $source );
	}

	/**
	 * Extract the first URL from a srcset string.
	 *
	 * @param string $srcset Raw srcset value.
	 *
	 * @return string
	 */
	private function first_src_from_srcset( string $srcset ): string {
		$parts = preg_split( '/\s*,\s*/', trim( $srcset ) );

		if ( empty( $parts ) ) {
			return '';
		}

		$first = trim( (string) $parts[0] );
		$first = preg_split( '/\s+/', $first );

		return is_array( $first ) ? (string) $first[0] : '';
	}

	/**
	 * Convert a URL into a readable file name.
	 *
	 * @param string $url Image URL.
	 *
	 * @return string
	 */
	private function file_name_from_url( string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return '';
		}

		$basename = function_exists( 'wp_basename' ) ? wp_basename( $path ) : basename( $path );
		$basename = (string) $basename;
		if ( '' === $basename ) {
			return '';
		}

		$filename = pathinfo( $basename, PATHINFO_FILENAME );
		$filename = preg_replace( '/[-_]+/', ' ', (string) $filename );
		$filename = preg_replace( '/\s+/', ' ', (string) $filename );

		return trim( (string) $filename );
	}

	/**
	 * Ensure an attribute exists and has a value.
	 *
	 * @param string       $tag           Tag HTML.
	 * @param string       $attribute     Attribute name.
	 * @param string       $existing      Existing attribute value.
	 * @param string       $template      Format template.
	 * @param ImageContext $context       Context data.
	 *
	 * @return string
	 */
	private function ensure_attribute(
		string $tag,
		string $attribute,
		string $existing,
		string $template,
		ImageContext $context
	): string {
		if ( '' !== trim( $existing ) ) {
			return $tag;
		}

		$value = $this->generator->generate( $template, $context );

		if ( '' === $value ) {
			return $tag;
		}

		return $this->apply_attribute( $tag, $attribute, $value );
	}

	/**
	 * Apply (or append) an attribute to the tag.
	 *
	 * @param string $tag       Tag markup.
	 * @param string $attribute Attribute name.
	 * @param string $value     Attribute value.
	 *
	 * @return string
	 */
	private function apply_attribute( string $tag, string $attribute, string $value ): string {
		$pattern = sprintf( '/\b(%s)\s*=\s*(["\'])(.*?)\2/i', preg_quote( $attribute, '/' ) );
		$escaped = esc_attr( $value );

		if ( preg_match( $pattern, $tag, $matches ) ) {
			$current = html_entity_decode( $matches[3], ENT_QUOTES );
			if ( '' !== trim( $current ) ) {
				return $tag;
			}

			$replacement = sprintf( '%s=%s%s%s', $matches[1], $matches[2], $escaped, $matches[2] );
			$result      = preg_replace( $pattern, $replacement, $tag, 1 );

			return is_string( $result ) ? $result : $tag;
		}

		return $this->insert_attribute( $tag, $attribute, $escaped );
	}

	/**
	 * Append a new attribute before the closing tag boundary.
	 *
	 * @param string $tag       Tag markup.
	 * @param string $attribute Attribute name.
	 * @param string $value     Escaped value.
	 *
	 * @return string
	 */
	private function insert_attribute( string $tag, string $attribute, string $value ): string {
		$insertion = sprintf( ' %s="%s"', $attribute, $value );

		if ( str_contains( $tag, '/>' ) ) {
			return str_replace( '/>', $insertion . ' />', $tag );
		}

		$pos = strrpos( $tag, '>' );
		if ( false === $pos ) {
			return $tag . $insertion;
		}

		return substr_replace( $tag, $insertion, $pos, 0 );
	}

	/**
	 * Normalize long-tail keyphrases into a list.
	 *
	 * @param string $raw Raw meta value.
	 *
	 * @return array<int, string>
	 */
	private function normalize_long_tail( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$parts = preg_split( '/[\\r\\n,]+/', $raw );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$clean = array();
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( '' === $part ) {
				continue;
			}
			$clean[] = $part;
		}

		return $clean;
	}
}
