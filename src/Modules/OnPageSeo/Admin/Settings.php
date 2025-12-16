<?php
/**
 * Stores configuration for the OnPage SEO module.
 *
 * @package Airygen\Modules\OnPageSeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\OnPageSeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists admin-configurable OnPage settings.
 */
final class Settings {

	private const OPTION = Constants::OPTION_ONPAGE;

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

		if ( isset( $value['output'] ) && is_array( $value['output'] ) ) {
			foreach ( $config['output'] as $key => $enabled ) {
				if ( array_key_exists( $key, $value['output'] ) ) {
					$config['output'][ $key ] = (bool) $value['output'][ $key ];
				}
			}
		}

		if ( isset( $value['templates'] ) && is_array( $value['templates'] ) ) {
			$config['templates']['global']        = self::sanitize_template_group(
				$value['templates']['global'] ?? array(),
				$config['templates']['global']
			);
			$config['templates']['separator']     = self::sanitize_separator(
				$value['templates']['separator'] ?? null,
				$config['templates']['separator']
			);
			$config['templates']['custom_tokens'] = self::sanitize_custom_tokens(
				$value['templates']['custom_tokens'] ?? array(),
				$config['templates']['custom_tokens']
			);

			if ( isset( $value['templates']['post_types'] ) && is_array( $value['templates']['post_types'] ) ) {
				$config['templates']['post_types'] = self::sanitize_post_type_templates(
					$value['templates']['post_types'],
					$config['templates']['post_types']
				);
			}
		}

		if ( empty( $config['templates']['separator'] ) && isset( $value['branding']['separator'] ) ) {
			$config['templates']['separator'] = self::sanitize_separator(
				$value['branding']['separator'],
				$config['templates']['separator']
			);
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
			'output'    => array(
				'title'       => true,
				'description' => true,
				'canonical'   => true,
				'robots'      => true,
			),
			'templates' => array(
				'global'        => array(
					'title'       => '%post_title% %separator% %site_name%',
					'description' => '%post_excerpt%',
				),
				'separator'     => '–',
				'custom_tokens' => array(
					'custom_1' => '',
					'custom_2' => '',
					'custom_3' => '',
				),
				'post_types'    => array(),
			),
		);
	}

	/**
	 * Sanitize template definitions for each post type.
	 *
	 * @param array<string, mixed>                 $templates Raw templates.
	 * @param array<string, array<string, string>> $defaults Default config.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function sanitize_post_type_templates( array $templates, array $defaults ): array {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		$config = array();
		foreach ( $templates as $post_type => $group ) {
			if ( ! is_string( $post_type ) || ! in_array( $post_type, $post_types, true ) ) {
				continue;
			}

			if ( ! is_array( $group ) ) {
				continue;
			}

			$sanitized = self::sanitize_template_group(
				$group,
				$defaults[ $post_type ] ?? array(
					'title'       => '',
					'description' => '',
				)
			);

			if ( '' === trim( $sanitized['title'] ) && '' === trim( $sanitized['description'] ) ) {
				continue;
			}

			$config[ $post_type ] = $sanitized;
		}

		return $config;
	}

	/**
	 * Sanitize a single template group.
	 *
	 * @param array<string, mixed>  $group           Raw template values.
	 * @param array<string, string> $default_values Default values.
	 *
	 * @return array<string, string>
	 */
	private static function sanitize_template_group( $group, array $default_values ): array {
		if ( ! is_array( $group ) ) {
			return $default_values;
		}

		return array(
			'title'       => isset( $group['title'] ) ? self::sanitize_template_value( $group['title'], $default_values['title'] ?? '' ) : ( $default_values['title'] ?? '' ),
			'description' => isset( $group['description'] ) ? self::sanitize_template_value( $group['description'], $default_values['description'] ?? '' ) : ( $default_values['description'] ?? '' ),
		);
	}

	/**
	 * Sanitize custom token values.
	 *
	 * @param mixed                $tokens   Raw token values.
	 * @param array<string, mixed> $defaults Default values.
	 *
	 * @return array<string, string>
	 */
	private static function sanitize_custom_tokens( $tokens, array $defaults ): array {
		$tokens = is_array( $tokens ) ? $tokens : array();

		$custom_1 = isset( $tokens['custom_1'] ) ? sanitize_text_field( (string) $tokens['custom_1'] ) : (string) $defaults['custom_1'];
		$custom_2 = isset( $tokens['custom_2'] ) ? sanitize_text_field( (string) $tokens['custom_2'] ) : (string) $defaults['custom_2'];
		$custom_3 = isset( $tokens['custom_3'] ) ? sanitize_text_field( (string) $tokens['custom_3'] ) : (string) $defaults['custom_3'];

		return array(
			'custom_1' => mb_substr( $custom_1, 0, 160 ),
			'custom_2' => mb_substr( $custom_2, 0, 160 ),
			'custom_3' => mb_substr( $custom_3, 0, 160 ),
		);
	}

	/**
	 * Normalize template string length.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Default string.
	 *
	 * @return string
	 */
	private static function sanitize_template_value( $value, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$normalized = trim( $value );

		return mb_substr( $normalized, 0, 180 );
	}

	/**
	 * Normalize separator values used in templates.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Default separator.
	 *
	 * @return string
	 */
	private static function sanitize_separator( $value, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$normalized = trim( $value );
		if ( '' === $normalized ) {
			return $fallback;
		}

		return mb_substr( $normalized, 0, 10 );
	}
}
