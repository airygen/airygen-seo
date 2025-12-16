<?php
/**
 * Stores Markdown for Agents settings.
 *
 * @package Airygen\Modules\MarkdownForAgents\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\MarkdownForAgents\Admin;

use Airygen\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option repository.
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_MARKDOWN_FOR_AGENTS;

	/**
	 * Ensure option exists.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', 'no' );
		}
	}

	/**
	 * Get settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION_NAME, array() ) );
	}

	/**
	 * Update settings.
	 *
	 * @param array<string,mixed> $value Raw value.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		$sanitized = self::sanitize( $value );
		update_option( self::OPTION_NAME, $sanitized, 'no' );
	}

	/**
	 * Defaults.
	 *
	 * @return array<string,mixed>
	 */
	private static function defaults(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = is_array( $post_types ) ? array_values( array_map( 'strval', $post_types ) ) : array( 'post', 'page' );

		return array(
			'enabled'             => true,
			'prompts_for_agents'  => false,
			'include_frontmatter' => true,
			'post_types'          => $post_types,
		);
	}

	/**
	 * Sanitize value.
	 *
	 * @param mixed $value Raw option.
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize( $value ): array {
		$defaults = self::defaults();
		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$post_types = $defaults['post_types'];
		if ( isset( $value['post_types'] ) && is_array( $value['post_types'] ) ) {
			$allowed = get_post_types( array( 'public' => true ), 'names' );
			$allowed = is_array( $allowed ) ? array_values( array_map( 'strval', $allowed ) ) : array();
			$types   = array_values( array_unique( array_filter( array_map( 'strval', $value['post_types'] ) ) ) );
			$types   = array_values( array_filter( $types, static fn( string $type ): bool => in_array( $type, $allowed, true ) ) );
			if ( ! empty( $types ) ) {
				$post_types = $types;
			}
		}

		return array(
			'enabled'             => isset( $value['enabled'] ) ? (bool) $value['enabled'] : (bool) $defaults['enabled'],
			'prompts_for_agents'  => isset( $value['prompts_for_agents'] ) ? (bool) $value['prompts_for_agents'] : (bool) $defaults['prompts_for_agents'],
			'include_frontmatter' => isset( $value['include_frontmatter'] ) ? (bool) $value['include_frontmatter'] : (bool) $defaults['include_frontmatter'],
			'post_types'          => $post_types,
		);
	}
}
