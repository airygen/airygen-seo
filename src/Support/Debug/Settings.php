<?php
/**
 * Handles persistence of debug logging configuration.
 *
 * @package Airygen\Support\Debug
 */

declare(strict_types=1);

namespace Airygen\Support\Debug;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;

/**
 * Provides helpers for enabling/debug logging directory management.
 */
final class Settings {

	private const OPTION = Constants::OPTION_DEBUG;

	/**
	 * Retrieve the current debug configuration.
	 *
	 * @return array{enabled:bool,slug:string,force_classic:bool,level:string}
	 */
	public static function get_config(): array {
		$value = get_option( self::OPTION, array() );
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		return array(
			'enabled'       => ! empty( $value['enabled'] ),
			'slug'          => isset( $value['slug'] ) ? sanitize_key( (string) $value['slug'] ) : '',
			'force_classic' => ! empty( $value['force_classic'] ),
			'level'         => self::normalize_level( isset( $value['level'] ) ? (string) $value['level'] : 'info' ),
		);
	}

	/**
	 * Determine whether classic editor is forced for testing.
	 *
	 * @return bool
	 */
	public static function is_classic_forced(): bool {
		$config = self::get_config();
		return ! empty( $config['force_classic'] );
	}

	/**
	 * Enable/disable forcing classic editor.
	 *
	 * @param bool $enabled Whether classic editor is forced.
	 *
	 * @return void
	 */
	public static function set_force_classic( bool $enabled ): void {
		$value = get_option( self::OPTION, array() );
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		$value['force_classic'] = $enabled ? 1 : 0;
		update_option( self::OPTION, $value, 'no' );
	}

	/**
	 * Set debug log level.
	 *
	 * @param string $level Debug level (error|warning|info).
	 * @return void
	 */
	public static function set_level( string $level ): void {
		$value = get_option( self::OPTION, array() );
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		$value['level'] = self::normalize_level( $level );
		update_option( self::OPTION, $value, 'no' );
	}

	/**
	 * Resolve the absolute directory when debug logging is enabled.
	 *
	 * @return string|null
	 */
	public static function get_log_directory(): ?string {
		$config = self::get_config();
		if ( ! $config['enabled'] || '' === $config['slug'] ) {
			return null;
		}

		return self::build_directory_path( $config['slug'] );
	}

	/**
	 * Resolve the directory path regardless of enable state.
	 *
	 * @return string|null
	 */
	public static function get_directory_path(): ?string {
		$config = self::get_config();
		if ( '' === $config['slug'] ) {
			return null;
		}

		return self::build_directory_path( $config['slug'] );
	}

	/**
	 * Resolve the log file path for today.
	 *
	 * @return string|null
	 */
	public static function resolve_log_file(): ?string {
		$directory = self::ensure_directory();
		if ( ! $directory ) {
			return null;
		}

		return trailingslashit( $directory ) . gmdate( 'Y-m-d' ) . '-.log';
	}

	/**
	 * List available log files within the debug directory.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_logs(): array {
		$directory = self::get_directory_path();
		if ( ! $directory || ! is_dir( $directory ) ) {
			return array();
		}

		$files = glob( trailingslashit( $directory ) . '*.log' );
		if ( ! $files ) {
			return array();
		}

		$entries = array();

		foreach ( $files as $file ) {
			$basename = basename( $file );
			if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})-\.log$/', $basename, $matches ) ) {
				continue;
			}

			$date = $matches[1];
			$size = file_exists( $file ) ? (int) filesize( $file ) : 0;

			$entries[] = array(
				'date'       => $date,
				'filename'   => $basename,
				'size'       => $size,
				'human_size' => size_format( $size ),
			);
		}

		usort(
			$entries,
			static function ( $a, $b ): int {
				return strcmp( $b['date'], $a['date'] );
			}
		);

		return array_values( $entries );
	}

	/**
	 * Read the log content for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 *
	 * @return string|null
	 */
	public static function read_log( string $date ): ?string {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return null;
		}

		$directory = self::get_directory_path();
		if ( ! $directory ) {
			return null;
		}

		$file = trailingslashit( $directory ) . $date . '-.log';
		if ( ! file_exists( $file ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file );
		if ( false === $content ) {
			return null;
		}

		return $content;
	}

	/**
	 * Delete all debug log files.
	 *
	 * @return int
	 */
	public static function clear_logs(): int {
		$directory = self::get_directory_path();
		if ( ! $directory || ! is_dir( $directory ) ) {
			return 0;
		}

		$files = glob( trailingslashit( $directory ) . '*.log' );
		if ( ! $files ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $files as $file ) {
			if ( is_string( $file ) && file_exists( $file ) && wp_delete_file( $file ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Enable debug logging, generating a directory slug.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function enable() {
		$slug      = strtolower( wp_generate_password( 8, false, false ) );
		$directory = self::build_directory_path( $slug );
		$current   = self::get_config();

		if ( ! wp_mkdir_p( $directory ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_DEBUG_DIR,
				sprintf(
					/* translators: %s: directory path */
					__( 'Unable to create debug directory at %s.', 'airygen-seo' ),
					$directory
				)
			);
		}

		update_option(
			self::OPTION,
			array(
				'enabled'       => true,
				'slug'          => $slug,
				'force_classic' => $current['force_classic'] ? 1 : 0,
				'level'         => $current['level'],
			)
		);

		return array(
			'enabled'   => true,
			'slug'      => $slug,
			'directory' => $directory,
		);
	}

	/**
	 * Disable debug logging while preserving the existing directory slug.
	 *
	 * @return array<string, mixed>
	 */
	public static function disable(): array {
		$config = self::get_config();

		update_option(
			self::OPTION,
			array(
				'enabled'       => false,
				'slug'          => $config['slug'],
				'force_classic' => $config['force_classic'] ? 1 : 0,
				'level'         => $config['level'],
			)
		);

		return array(
			'enabled'   => false,
			'slug'      => $config['slug'],
			'directory' => null,
		);
	}

	/**
	 * Ensure the directory exists when debug logging is enabled.
	 *
	 * @return string|null
	 */
	private static function ensure_directory(): ?string {
		$directory = self::get_log_directory();
		if ( ! $directory ) {
			return null;
		}

		if ( file_exists( $directory ) ) {
			return $directory;
		}

		if ( wp_mkdir_p( $directory ) ) {
			return $directory;
		}

		return null;
	}

	/**
	 * Build a log directory path from the provided slug.
	 *
	 * @param string $slug Directory slug.
	 *
	 * @return string
	 */
	private static function build_directory_path( string $slug ): string {
		return trailingslashit( WP_CONTENT_DIR ) . 'airygen-log-' . $slug;
	}

	/**
	 * Normalize level value.
	 *
	 * @param string $level Raw level.
	 * @return string
	 */
	private static function normalize_level( string $level ): string {
		$level = strtolower( sanitize_key( $level ) );
		if ( in_array( $level, array( 'error', 'warning', 'info' ), true ) ) {
			return $level;
		}

		return 'info';
	}
}
