<?php
/**
 * Stores configuration for the Author SEO module.
 *
 * @package Airygen\Modules\AuthorSeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\AuthorSeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists admin-configurable Author SEO settings.
 */
final class Settings {

	private const OPTION = Constants::OPTION_AUTHOR_SEO;

	/**
	 * Ensure the option exists with defaults.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::default_config(), '', 'no' );
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
	 * @param array<string, mixed> $value Raw settings array.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize incoming values.
	 *
	 * @param mixed $value Raw option value.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize( $value ): array {
		$config = self::default_config();

		if ( ! is_array( $value ) ) {
			return $config;
		}

		if ( array_key_exists( 'enabled', $value ) ) {
			$config['enabled'] = (bool) $value['enabled'];
		}

		if ( array_key_exists( 'noindex_author_archives', $value ) ) {
			$config['noindex_author_archives'] = (bool) $value['noindex_author_archives'];
		}

		if ( isset( $value['title_template'] ) && is_string( $value['title_template'] ) ) {
			$config['title_template'] = mb_substr( sanitize_text_field( $value['title_template'] ), 0, 160 );
		}

		if ( isset( $value['description_template'] ) && is_string( $value['description_template'] ) ) {
			$config['description_template'] = mb_substr( sanitize_text_field( $value['description_template'] ), 0, 200 );
		}

		if ( isset( $value['separator'] ) && is_string( $value['separator'] ) ) {
			$config['separator'] = mb_substr( trim( sanitize_text_field( $value['separator'] ) ), 0, 10 );
		}

		if ( isset( $value['custom_tokens'] ) && is_array( $value['custom_tokens'] ) ) {
			$custom1 = '';
			$custom2 = '';
			$custom3 = '';

			if ( isset( $value['custom_tokens']['custom1'] ) && is_string( $value['custom_tokens']['custom1'] ) ) {
				$custom1 = mb_substr( sanitize_text_field( $value['custom_tokens']['custom1'] ), 0, 160 );
			}

			if ( isset( $value['custom_tokens']['custom2'] ) && is_string( $value['custom_tokens']['custom2'] ) ) {
				$custom2 = mb_substr( sanitize_text_field( $value['custom_tokens']['custom2'] ), 0, 160 );
			}

			if ( isset( $value['custom_tokens']['custom3'] ) && is_string( $value['custom_tokens']['custom3'] ) ) {
				$custom3 = mb_substr( sanitize_text_field( $value['custom_tokens']['custom3'] ), 0, 160 );
			}

			$config['custom_tokens'] = array(
				'custom1' => $custom1,
				'custom2' => $custom2,
				'custom3' => $custom3,
			);
		}

		if ( isset( $value['social_profiles'] ) && is_array( $value['social_profiles'] ) ) {
			$profiles = array();
			foreach ( $value['social_profiles'] as $profile ) {
				if ( ! is_string( $profile ) ) {
					continue;
				}
				$url = esc_url_raw( trim( $profile ) );
				if ( '' === $url ) {
					continue;
				}
				if ( in_array( $url, $profiles, true ) ) {
					continue;
				}
				$profiles[] = $url;
			}
			$config['social_profiles'] = $profiles;
		}

		return $config;
	}

	/**
	 * Default configuration.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_config(): array {
		return array(
			'enabled'                 => true,
			'noindex_author_archives' => false,
			'title_template'          => '%author_name% | %site_name%',
			'description_template'    => '%author_bio%',
			'separator'               => '|',
			'custom_tokens'           => array(
				'custom1' => '',
				'custom2' => '',
				'custom3' => '',
			),
			'social_profiles'         => array(),
		);
	}
}
