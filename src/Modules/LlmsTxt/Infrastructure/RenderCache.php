<?php
/**
 * File-based render cache for llms.txt outputs.
 *
 * @package Airygen\Modules\LlmsTxt\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Modules\LlmsTxt\Infrastructure;

use Airygen\Support\Debug\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles reading, writing, and invalidating llms.txt render cache files.
 */
final class RenderCache {

	/**
	 * Read cached content for a target.
	 *
	 * @param string $target Cache target.
	 *
	 * @return string|null
	 */
	public static function get( string $target ): ?string {
		$file = self::cache_file_path( $target );
		if ( '' === $file || ! is_readable( $file ) ) {
			return null;
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_string( $contents ) ? $contents : null;
	}

	/**
	 * Write cached content for a target.
	 *
	 * @param string $target  Cache target.
	 * @param string $content Rendered content.
	 *
	 * @return void
	 */
	public static function set( string $target, string $content ): void {
		$dir = self::cache_directory();
		if ( '' === $dir ) {
			return;
		}

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			Logger::warning( 'llms-cache', 'Failed to create llms cache directory.' );
			return;
		}

		$file = self::cache_file_path( $target );
		if ( '' === $file ) {
			return;
		}

		$temp_file = $file . '.tmp';
		$written   = file_put_contents( $temp_file, $content, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written ) {
			Logger::warning( 'llms-cache', 'Failed to write llms cache file.' );
			return;
		}

		if ( ! @rename( $temp_file, $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
			Logger::warning( 'llms-cache', 'Failed to finalize llms cache file.' );
		}
	}

	/**
	 * Delete all llms cache files for the current site.
	 *
	 * @return void
	 */
	public static function invalidate_all(): void {
		$dir = self::cache_directory();
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( trailingslashit( $dir ) . '*.txt' );
		if ( false === $files ) {
			Logger::warning( 'llms-cache', 'Failed to enumerate llms cache files for invalidation.' );
			return;
		}

		foreach ( $files as $file ) {
			if ( is_string( $file ) && is_file( $file ) ) {
				@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
	}

	/**
	 * Resolve current site cache directory.
	 *
	 * @return string
	 */
	private static function cache_directory(): string {
		$uploads = wp_get_upload_dir();
		$base    = isset( $uploads['basedir'] ) && is_string( $uploads['basedir'] ) ? $uploads['basedir'] : '';
		if ( '' === $base ) {
			return '';
		}

		return trailingslashit( $base ) . 'airygen-cache/llms/' . get_current_blog_id();
	}

	/**
	 * Resolve the cache file path for a target.
	 *
	 * @param string $target Cache target.
	 *
	 * @return string
	 */
	private static function cache_file_path( string $target ): string {
		$dir = self::cache_directory();
		if ( '' === $dir ) {
			return '';
		}

		if ( 'base' === $target ) {
			return trailingslashit( $dir ) . 'base.txt';
		}

		$target = sanitize_key( $target );
		if ( '' === $target ) {
			return '';
		}

		return trailingslashit( $dir ) . $target . '.txt';
	}
}
