<?php
/**
 * Stores dashboard panel ordering preferences.
 *
 * @package Airygen\Admin\Panels
 */

declare(strict_types=1);

namespace Airygen\Admin\Panels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists the drag-and-drop order for sidebar panels.
 */
final class Order {

	private const OPTION_NAME = Constants::OPTION_PANEL_ORDER;

	/**
	 * Cached panel order.
	 *
	 * @var array<int, string>|null
	 */
	private static $cache = null;

	/**
	 * Ensure the order option exists.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			$default = self::default_order();
			add_option( self::OPTION_NAME, $default, '', 'no' );
			self::$cache = $default;
			return;
		}

		$current   = get_option( self::OPTION_NAME, array() );
		$sanitized = self::sanitize( $current );

		if ( $current !== $sanitized ) {
			update_option( self::OPTION_NAME, $sanitized, 'no' );
		}

		self::$cache = $sanitized;
	}

	/**
	 * Retrieve stored order.
	 *
	 * @return array<int, string>
	 */
	public static function get(): array {
		if ( is_array( self::$cache ) ) {
			return self::$cache;
		}

		$value = get_option( self::OPTION_NAME, array() );

		self::$cache = self::sanitize( $value );

		return self::$cache;
	}

	/**
	 * Persist a new panel order.
	 *
	 * @param array<int, mixed> $value Raw order input.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		$sanitized = self::sanitize( $value );
		update_option( self::OPTION_NAME, $sanitized, 'no' );
		self::$cache = $sanitized;
	}

	/**
	 * Sanitize the order array.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<int, string>
	 */
	private static function sanitize( $value ): array {
		$order = array();
		$known = self::keys();

		if ( is_array( $value ) ) {
			foreach ( $value as $maybe ) {
				if ( is_string( $maybe ) && in_array( $maybe, $known, true ) && ! in_array( $maybe, $order, true ) ) {
					$order[] = $maybe;
				}
			}
		}

		foreach ( $known as $key ) {
			if ( ! in_array( $key, $order, true ) ) {
				$order[] = $key;
			}
		}

		return $order;
	}

	/**
	 * Default order of panels.
	 *
	 * @return array<int, string>
	 */
	private static function default_order(): array {
		return self::keys();
	}

	/**
	 * Known panel keys.
	 *
	 * @return array<int, string>
	 */
	public static function keys(): array {
		return array(
			'scoreCalculator',
			'serpSnippet',
			'keyphrases',
			'canonical',
			'schemaMarkup',
			'robots',
			'toc',
			'linkSuggestions',
			'promptsForAgents',
			'topicCluster',
		);
	}
}
