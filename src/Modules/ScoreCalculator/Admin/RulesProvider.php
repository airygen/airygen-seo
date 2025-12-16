<?php
/**
 * Provides access to the SEO score ruleset configuration.
 *
 * @package Airygen\Modules\ScoreCalculator\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\ScoreCalculator\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper for reading the rule manifest from disk.
 */
final class RulesProvider {

	/**
	 * Load the processed rules specification.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$manifest = self::load_manifest();

		$spec = array(
			'rules'    => is_array( $manifest['rules'] ?? null ) ? $manifest['rules'] : array(),
			'bonus'    => is_array( $manifest['bonus'] ?? null ) ? $manifest['bonus'] : array(),
			'pack'     => (string) ( $manifest['pack'] ?? 'airygen-score-pack' ),
			'version'  => (string) ( $manifest['version'] ?? 'unknown' ),
			'language' => (string) ( $manifest['language'] ?? 'auto' ),
		);

		return self::translate_rules( $spec );
	}

	/**
	 * Load the raw manifest from disk.
	 *
	 * @return array<string, mixed>
	 */
	private static function load_manifest(): array {
		$path = self::rules_path();

		if ( ! file_exists( $path ) ) {
			return self::default_manifest();
		}

		$data = require $path;

		if ( ! is_array( $data ) ) {
			return self::default_manifest();
		}

		return $data;
	}

	/**
	 * Translate rule labels when possible.
	 *
	 * @param array<string, mixed> $spec Rules specification.
	 *
	 * @return array<string, mixed>
	 */
	private static function translate_rules( array $spec ): array {
		$translator = function_exists( '__' ) ? '__' : null;

		foreach ( array( 'rules', 'bonus' ) as $group ) {
			if ( ! isset( $spec[ $group ] ) || ! is_array( $spec[ $group ] ) ) {
				$spec[ $group ] = array();
				continue;
			}

			foreach ( $spec[ $group ] as &$rule ) {
				if ( ! isset( $rule['label'] ) || ! is_string( $rule['label'] ) ) {
					$rule['label'] = '';
				} elseif ( $translator && ! empty( $rule['text_domain'] ) ) {
					$rule['label'] = $translator( $rule['label'], (string) $rule['text_domain'] );
				}
			}
		}

		return $spec;
	}

	/**
	 * Default manifest when configuration cannot be loaded.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_manifest(): array {
		return array(
			'rules'    => array(),
			'bonus'    => array(),
			'pack'     => 'airygen-score-pack',
			'version'  => 'unknown',
			'language' => 'auto',
		);
	}

	/**
	 * Resolve the manifest path.
	 *
	 * @return string
	 */
	private static function rules_path(): string {
		return plugin_dir_path( AIRYGEN_PLUGIN_FILE ) . 'config/score_rules.php';
	}
}
