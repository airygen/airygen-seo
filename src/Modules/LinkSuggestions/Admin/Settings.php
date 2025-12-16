<?php
/**
 * Settings handler for Related Posts / Link Suggestions.
 *
 * @package Airygen\Modules\LinkSuggestions\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Manages persistence and defaults for link suggestion settings.
 */
class Settings {

	private const OPTION_KEY     = Constants::OPTION_LINK_SUGGESTIONS;
	public const MAX_SUGGESTIONS = 5;

	/**
	 * Get settings with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$defaults = self::defaults();
		$stored   = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			return $defaults;
		}

		return array(
			'enabled'            => self::to_bool( $stored['enabled'] ?? $defaults['enabled'] ),
			'allowed_post_types' => self::sanitize_array( $stored['allowed_post_types'] ?? $defaults['allowed_post_types'] ),
			'max_suggestions'    => self::MAX_SUGGESTIONS,
		);
	}

	/**
	 * Update settings with sanitization.
	 *
	 * @param array<string,mixed> $payload Incoming payload.
	 *
	 * @return array<string,mixed> Sanitized settings.
	 */
	public static function update( array $payload ): array {
		$current = self::get();

		$sanitized = array(
			'enabled'            => self::to_bool( $payload['enabled'] ?? $current['enabled'] ),
			'allowed_post_types' => self::sanitize_array( $payload['allowed_post_types'] ?? $current['allowed_post_types'] ),
			'max_suggestions'    => self::MAX_SUGGESTIONS,
		);

		update_option( self::OPTION_KEY, $sanitized );

		return $sanitized;
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'            => false,
			'allowed_post_types' => array(),
			'max_suggestions'    => self::MAX_SUGGESTIONS,
		);
	}

	/**
	 * Cast value to boolean.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return bool
	 */
	private static function to_bool( $value ): bool {
		return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize array of strings.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return array<int,string>
	 */
	private static function sanitize_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $item ): string {
						return trim( (string) $item );
					},
					$value
				),
				static function ( string $item ): bool {
					return '' !== $item;
				}
			)
		);
	}
}
