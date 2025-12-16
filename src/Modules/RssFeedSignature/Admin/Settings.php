<?php
/**
 * Stores configuration for RSS Feed Signature output.
 *
 * @package Airygen\Modules\RssFeedSignature\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\RssFeedSignature\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists admin-configurable RSS signature settings.
 */
final class Settings {

	private const OPTION = Constants::OPTION_RSS_FEED_SIGNATURE;

	/**
	 * Ensure the option exists with defaults.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', 'no' );
			return;
		}

		self::update( (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Retrieve sanitized configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Persist sanitized settings.
	 *
	 * @param array<string, mixed> $value Raw option payload.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize input values against defaults.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize( $value ): array {
		$config    = self::defaults();
		$sanitized = is_array( $value ) ? $value : array();

		$config['enabled'] = isset( $sanitized['enabled'] )
		? (bool) $sanitized['enabled']
		: $config['enabled'];

		$config['before_content'] = self::sanitize_html_fragment(
			$sanitized['before_content'] ?? $config['before_content']
		);
		$config['after_content']  = self::sanitize_html_fragment(
			$sanitized['after_content'] ?? $config['after_content']
		);

		return $config;
	}

	/**
	 * Default configuration.
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults(): array {
		return array(
			'enabled'        => false,
			'before_content' => '',
			'after_content'  => '',
		);
	}

	/**
	 * Sanitize HTML signature fragments.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_html_fragment( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$normalized = trim( (string) $value );
		if ( '' === $normalized ) {
			return '';
		}
		$normalized = preg_replace( '#<\s*(script|style)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $normalized );
		$normalized = is_string( $normalized ) ? trim( $normalized ) : '';
		if ( '' === $normalized ) {
			return '';
		}

		$allowed_tags = array(
			'a'      => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'p'      => array(),
			'span'   => array( 'class' => true ),
		);

		$sanitized = wp_kses( $normalized, $allowed_tags );

		return mb_substr( $sanitized, 0, 2000 );
	}
}
