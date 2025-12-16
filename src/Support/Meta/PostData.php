<?php
/**
 * Normalizes consolidated per-post SEO data.
 *
 * @package Airygen\Support\Meta
 */

declare(strict_types=1);

namespace Airygen\Support\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Handles read/write operations for consolidated post data postmeta.
 */
final class PostData {

	/**
	 * Return normalized post data for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array{
	 *   title:string,
	 *   description:string,
	 *   focusKeyphrase:string,
	 *   focusLongTail:string,
	 *   agentPrompt:string,
	 *   canonical:string,
	 *   robots:string,
	 *   schemaArticleType:string
	 * }
	 */
	public static function get( int $post_id ): array {
		return self::normalize( get_post_meta( $post_id, Constants::META_POST_DATA, true ) );
	}

	/**
	 * Return a single normalized field value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Field key.
	 *
	 * @return string
	 */
	public static function get_field( int $post_id, string $key ): string {
		$data = self::get( $post_id );
		return isset( $data[ $key ] ) && is_string( $data[ $key ] ) ? $data[ $key ] : '';
	}

	/**
	 * Persist normalized post data as JSON.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Raw payload.
	 *
	 * @return void
	 */
	public static function save( int $post_id, array $data ): void {
		update_post_meta( $post_id, Constants::META_POST_DATA, wp_slash( self::sanitize_meta_value( $data ) ) );
	}

	/**
	 * Persist one field while preserving existing values.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Field key.
	 * @param string $value   Field value.
	 *
	 * @return void
	 */
	public static function save_field( int $post_id, string $key, string $value ): void {
		$data = self::get( $post_id );
		if ( ! array_key_exists( $key, $data ) ) {
			return;
		}

		$data[ $key ] = self::sanitize_text( $value );
		self::save( $post_id, $data );
	}

	/**
	 * Sanitize a raw meta payload for register_post_meta.
	 *
	 * @param mixed $value Raw meta payload.
	 *
	 * @return string
	 */
	public static function sanitize_meta_value( $value ): string {
		$json = wp_json_encode( self::normalize( $value ) );
		if ( false === $json ) {
			return '{}';
		}

		return $json;
	}

	/**
	 * Normalize the stored payload.
	 *
	 * @param mixed $value Raw meta payload.
	 *
	 * @return array{
	 *   title:string,
	 *   description:string,
	 *   focusKeyphrase:string,
	 *   focusLongTail:string,
	 *   agentPrompt:string,
	 *   canonical:string,
	 *   robots:string,
	 *   schemaArticleType:string
	 * }
	 */
	private static function normalize( $value ): array {
		$decoded = self::decode( $value );

		return array(
			'title'             => self::sanitize_text( $decoded['title'] ?? null ),
			'description'       => self::sanitize_text( $decoded['description'] ?? null ),
			'focusKeyphrase'    => self::sanitize_text( $decoded['focusKeyphrase'] ?? null ),
			'focusLongTail'     => self::sanitize_text( $decoded['focusLongTail'] ?? null ),
			'agentPrompt'       => self::sanitize_textarea( $decoded['agentPrompt'] ?? null ),
			'canonical'         => self::sanitize_canonical( $decoded['canonical'] ?? null ),
			'robots'            => self::sanitize_text( $decoded['robots'] ?? null ),
			'schemaArticleType' => self::sanitize_text( $decoded['schemaArticleType'] ?? null ),
		);
	}

	/**
	 * Decode a JSON string or array payload.
	 *
	 * @param mixed $value Raw payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function decode( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && '' !== trim( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return array();
	}

	/**
	 * Sanitize a scalar into a plain string.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_text( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize a scalar into textarea-safe string.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_textarea( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Sanitize canonical URL while allowing the no-output token.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_canonical( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$canonical = (string) $value;
		if ( Constants::CANONICAL_NONE_TOKEN === $canonical ) {
			return $canonical;
		}

		return esc_url_raw( $canonical );
	}
}
