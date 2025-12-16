<?php
/**
 * Normalizes consolidated per-post output modes.
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
 * Handles read/write operations for consolidated output mode postmeta.
 */
final class OutputModes {

	/**
	 * Return normalized modes for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array{toc:string,faq:string,topicExpansion:string}
	 */
	public static function get( int $post_id ): array {
		return self::normalize( get_post_meta( $post_id, Constants::META_OUTPUT_MODES, true ) );
	}

	/**
	 * Return one normalized mode.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Mode key.
	 *
	 * @return string
	 */
	public static function get_mode( int $post_id, string $key ): string {
		$modes = self::get( $post_id );
		return $modes[ $key ] ?? 'auto';
	}

	/**
	 * Persist normalized modes as JSON.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $modes   Raw mode array.
	 *
	 * @return void
	 */
	public static function save( int $post_id, array $modes ): void {
		update_post_meta( $post_id, Constants::META_OUTPUT_MODES, wp_slash( self::sanitize_meta_value( $modes ) ) );
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
	 * @return array{toc:string,faq:string,topicExpansion:string}
	 */
	private static function normalize( $value ): array {
		$decoded = self::decode( $value );

		return array(
			'toc'            => self::sanitize_mode( $decoded['toc'] ?? null ),
			'faq'            => self::sanitize_mode( $decoded['faq'] ?? null ),
			'topicExpansion' => self::sanitize_mode( $decoded['topicExpansion'] ?? null ),
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
	 * Sanitize one mode string.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_mode( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return 'auto';
		}

		$mode = strtolower( (string) $value );
		return in_array( $mode, array( 'auto', 'manual', 'disabled' ), true ) ? $mode : 'auto';
	}
}
