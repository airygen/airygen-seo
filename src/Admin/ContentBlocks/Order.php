<?php
/**
 * Stores article content block injection order and spacing settings.
 *
 * @package Airygen\Admin\ContentBlocks
 */

declare(strict_types=1);

namespace Airygen\Admin\ContentBlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists the drag-and-drop order and spacing for in-article content blocks.
 *
 * Storage format (wp_options):
 * {
 *   "order":     ["toc", "breadcrumbs", ...],
 *   "gap":       24,
 *   "marginTop": 16
 * }
 *
 * Migrates old plain-array format automatically.
 */
final class Order {

	private const OPTION_NAME = Constants::OPTION_CONTENT_BLOCK_ORDER;

	private const DEFAULT_GAP        = 24;
	private const DEFAULT_MARGIN_TOP = 16;

	/**
	 * Known content block keys, in their default order.
	 *
	 * @var array<int, string>
	 */
	private const BLOCK_KEYS = array(
		'toc',
		'breadcrumbs',
		'relatedPosts',
		'topicCluster',
		'deepFaq',
		'topicExpansion',
	);

	/**
	 * Cached full settings array.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $cache = null;

	/**
	 * Ensure the option exists, migrating old formats if needed.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		$stored = get_option( self::OPTION_NAME, false );

		if ( false === $stored ) {
			$default = self::build_default();
			add_option( self::OPTION_NAME, $default, '', 'no' );
			self::$cache = $default;
			return;
		}

		$normalized = self::normalize( $stored );

		if ( $normalized !== $stored ) {
			update_option( self::OPTION_NAME, $normalized, 'no' );
		}

		self::$cache = $normalized;
	}

	/**
	 * Retrieve stored block order array.
	 *
	 * @return array<int, string>
	 */
	public static function get(): array {
		return self::load()['order'];
	}

	/**
	 * Retrieve gap between consecutive blocks (px).
	 *
	 * @return int
	 */
	public static function get_gap(): int {
		return self::load()['gap'];
	}

	/**
	 * Retrieve margin between article content and first block (px).
	 *
	 * @return int
	 */
	public static function get_margin_top(): int {
		return self::load()['marginTop'];
	}

	/**
	 * Persist a new block order.
	 *
	 * @param array<int, mixed> $value Raw order input.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		$current          = self::load();
		$current['order'] = self::sanitize_order( $value );
		update_option( self::OPTION_NAME, $current, 'no' );
		self::$cache = $current;
	}

	/**
	 * Persist spacing settings.
	 *
	 * @param int $gap        Gap between blocks (px).
	 * @param int $margin_top Margin above first block (px).
	 *
	 * @return void
	 */
	public static function update_spacing( int $gap, int $margin_top ): void {
		$current              = self::load();
		$current['gap']       = max( 0, $gap );
		$current['marginTop'] = max( 0, $margin_top );
		update_option( self::OPTION_NAME, $current, 'no' );
		self::$cache = $current;
	}

	// ── Internals ─────────────────────────────────────────────────────────────

	/**
	 * Load and cache the full settings array.
	 *
	 * @return array<string, mixed>
	 */
	private static function load(): array {
		if ( is_array( self::$cache ) ) {
			return self::$cache;
		}

		$stored      = get_option( self::OPTION_NAME, array() );
		self::$cache = self::normalize( $stored );

		return self::$cache;
	}

	/**
	 * Normalize raw stored value to the expected shape.
	 * Handles migration from the old plain-array format.
	 *
	 * @param mixed $value Raw stored value.
	 *
	 * @return array<string, mixed>
	 */
	private static function normalize( $value ): array {
		$result = self::build_default();

		if ( ! is_array( $value ) ) {
			return $result;
		}

		// Old format: plain list of string keys
		if ( isset( $value[0] ) && is_string( $value[0] ) ) {
			$result['order'] = self::sanitize_order( $value );
			return $result;
		}

		// New format: associative
		if ( isset( $value['order'] ) ) {
			$result['order'] = self::sanitize_order( $value['order'] );
		}

		if ( isset( $value['gap'] ) ) {
			$result['gap'] = max( 0, (int) $value['gap'] );
		}

		if ( isset( $value['marginTop'] ) ) {
			$result['marginTop'] = max( 0, (int) $value['marginTop'] );
		}

		return $result;
	}

	/**
	 * Build the default settings array.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_default(): array {
		return array(
			'order'     => self::BLOCK_KEYS,
			'gap'       => self::DEFAULT_GAP,
			'marginTop' => self::DEFAULT_MARGIN_TOP,
		);
	}

	/**
	 * Sanitize a raw order array, filling in any missing keys.
	 *
	 * @param mixed $value Raw order input.
	 *
	 * @return array<int, string>
	 */
	private static function sanitize_order( $value ): array {
		$order = array();

		if ( is_array( $value ) ) {
			foreach ( $value as $maybe ) {
				if ( is_string( $maybe ) && in_array( $maybe, self::BLOCK_KEYS, true ) && ! in_array( $maybe, $order, true ) ) {
					$order[] = $maybe;
				}
			}
		}

		foreach ( self::BLOCK_KEYS as $key ) {
			if ( ! in_array( $key, $order, true ) ) {
				$order[] = $key;
			}
		}

		return $order;
	}
}
