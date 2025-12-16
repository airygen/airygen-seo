<?php
/**
 * Option storage for the Instant Indexing (IndexNow) module.
 *
 * @package Airygen\Modules\InstantIndexing\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Admin;

use Airygen\Constants;
use Airygen\Modules\InstantIndexing\Domain\EngineRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides getters/setters for the Instant Indexing settings option.
 */
final class Settings {

	private const OPTION = Constants::OPTION_INDEXNOW;

	/**
	 * Ensure option exists with defaults.
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::default_config(), '', 'no' );
		}
	}

	/**
	 * Retrieve sanitized configuration.
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Persist updated configuration.
	 *
	 * @param array<string, mixed> $value Raw payload.
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION, self::sanitize( $value ), 'no' );
	}

	/**
	 * Determine whether the module is enabled.
	 *
	 * @param array<string, mixed>|null $settings Optional settings payload.
	 * @return bool
	 */
	public static function is_enabled( ?array $settings = null ): bool {
		$settings = $settings ?? self::get();
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Retrieve enabled engines with endpoints.
	 *
	 * @param array<string, mixed>|null $settings Optional settings payload.
	 * @return array<string, string> Map of slug => endpoint URL.
	 */
	public static function enabled_engines( ?array $settings = null ): array {
		$settings = $settings ?? self::get();
		$engines  = isset( $settings['engines'] ) && is_array( $settings['engines'] ) ? $settings['engines'] : array();
		$enabled  = array();

		foreach ( $engines as $slug => $engine ) {
			if ( empty( $engine['enabled'] ) ) {
				continue;
			}

			$endpoint = isset( $engine['endpoint'] ) ? esc_url_raw( (string) $engine['endpoint'] ) : '';
			if ( '' === $endpoint ) {
				$defaults = EngineRegistry::default_endpoints();
				$endpoint = $defaults[ $slug ] ?? '';
			}

			if ( '' === $endpoint ) {
				continue;
			}

			$enabled[ $slug ] = $endpoint;
		}

		return $enabled;
	}

	/**
	 * Resolve the preferred batch size.
	 *
	 * @param array<string, mixed>|null $settings Optional settings payload.
	 * @return int
	 */
	public static function batch_size( ?array $settings = null ): int {
		$settings = $settings ?? self::get();
		$size     = isset( $settings['batch_size'] ) ? (int) $settings['batch_size'] : 100;
		return max( 10, min( 10000, $size ) );
	}

	/**
	 * Retrieve the configured daily quota.
	 *
	 * @param array<string, mixed>|null $settings Optional settings payload.
	 * @return int
	 */
	public static function max_events_per_day( ?array $settings = null ): int {
		$settings = $settings ?? self::get();
		$limit    = isset( $settings['max_events_per_day'] ) ? (int) $settings['max_events_per_day'] : 10000;
		return max( 0, $limit );
	}

	/**
	 * Compute the preferred key location URL.
	 *
	 * @param array<string, mixed>|null $settings Optional settings payload.
	 * @return string
	 */
	public static function key_location( ?array $settings = null ): string {
		$settings     = $settings ?? self::get();
		$key          = isset( $settings['key'] ) ? (string) $settings['key'] : '';
		$key_location = isset( $settings['key_location'] ) ? esc_url_raw( (string) $settings['key_location'] ) : '';

		if ( '' !== $key_location ) {
			return $key_location;
		}

		if ( '' === $key ) {
			return '';
		}

		$path = '/' . $key . '.txt';
		$url  = home_url( $path );

		return esc_url_raw( $url );
	}

	/**
	 * Sanitize stored configuration.
	 *
	 * @param mixed $value Raw option value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $value ): array {
		$config = self::default_config();

		if ( ! is_array( $value ) ) {
			return $config;
		}

		$config['enabled']     = ! empty( $value['enabled'] );
		$config['auto_submit'] = isset( $value['auto_submit'] ) ? (bool) $value['auto_submit'] : true;

		if ( isset( $value['key'] ) ) {
			$key           = preg_replace( '/[^A-Za-z0-9]/', '', (string) $value['key'] );
			$config['key'] = substr( $key ?? '', 0, 128 );
		}

		if ( isset( $value['key_location'] ) ) {
			$config['key_location'] = esc_url_raw( (string) $value['key_location'] );
		}

		if ( isset( $value['max_events_per_day'] ) ) {
			$config['max_events_per_day'] = max( 0, (int) $value['max_events_per_day'] );
		}

		if ( isset( $value['batch_size'] ) ) {
			$config['batch_size'] = max( 10, min( 10000, (int) $value['batch_size'] ) );
		}

		if ( isset( $value['engines'] ) && is_array( $value['engines'] ) ) {
			$config['engines'] = self::sanitize_engines( $value['engines'] );
		}

		if ( isset( $value['backfill'] ) && is_array( $value['backfill'] ) ) {
			$config['backfill'] = self::sanitize_backfill( $value['backfill'] );
		}

		return $config;
	}

	/**
	 * Default configuration skeleton.
	 */
	public static function default_config(): array {
		$defaults = array();
		foreach ( EngineRegistry::default_endpoints() as $slug => $endpoint ) {
			$defaults[ $slug ] = array(
				'enabled'  => 'bing' === $slug,
				'endpoint' => $endpoint,
			);
		}

		return array(
			'enabled'            => true,
			'auto_submit'        => true,
			'key'                => '',
			'key_location'       => '',
			'max_events_per_day' => 10000,
			'batch_size'         => 100,
			'engines'            => $defaults,
			'backfill'           => array(
				'post_types' => array(),
			),
		);
	}

	/**
	 * Sanitize engines payload.
	 *
	 * @param array<string, mixed> $value Raw engines data.
	 * @return array<string, array{enabled: bool, endpoint: string}>
	 */
	private static function sanitize_engines( array $value ): array {
		$defaults = EngineRegistry::default_endpoints();
		$engines  = array();

		$has_enabled = false;

		foreach ( $defaults as $slug => $endpoint ) {
			$current = $value[ $slug ] ?? array();
			$enabled = isset( $current['enabled'] ) ? (bool) $current['enabled'] : ( 'bing' === $slug );
			$url     = isset( $current['endpoint'] ) ? esc_url_raw( (string) $current['endpoint'] ) : $endpoint;
			if ( '' === $url ) {
				$url = $endpoint;
			}

			$engines[ $slug ] = array(
				'enabled'  => $enabled,
				'endpoint' => $url,
			);

			if ( $enabled ) {
				$has_enabled = true;
			}
		}

		if ( ! $has_enabled && isset( $engines['bing'] ) ) {
			$engines['bing']['enabled'] = true;
		}

		return $engines;
	}

	/**
	 * Sanitize backfill configuration.
	 *
	 * @param array<string, mixed> $backfill Raw value.
	 * @return array<string, mixed>
	 */
	private static function sanitize_backfill( array $backfill ): array {
		$post_types = array();
		if ( isset( $backfill['post_types'] ) && is_array( $backfill['post_types'] ) ) {
			foreach ( $backfill['post_types'] as $type ) {
				$slug = sanitize_key( (string) $type );
				if ( '' !== $slug ) {
					$post_types[] = $slug;
				}
			}
		}

		return array(
			'post_types' => array_values( array_unique( $post_types ) ),
		);
	}

	/**
	 * Generate a random IndexNow key.
	 *
	 * @param int $length Desired length (16-128).
	 * @return string
	 */
	public static function generate_key( int $length = 32 ): string {
		$length = max( 16, min( 128, $length ) );

		try {
			$bytes = random_bytes( (int) ceil( $length / 2 ) );
			$key   = substr( bin2hex( $bytes ), 0, $length );
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			$key = wp_generate_password( $length, false, false );
			$key = preg_replace( '/[^A-Za-z0-9]/', '', $key ?? '' );
		}

		return strtolower( $key ?? '' );
	}
}
