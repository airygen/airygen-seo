<?php
/**
 * Admin integrations for the Score Calculator feature.
 *
 * @package Airygen\Modules\ScoreCalculator\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\ScoreCalculator\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\ScoreCalculator\Admin\RestController as ScoreRestController;
use WP_Post;

/**
 * Registers admin-side hooks for scoring.
 */
final class Hooks {

	/**
	 * Hook registrations.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( Constants::HOOK_EDITOR_CONFIG, array( __CLASS__, 'extend_editor_config' ) );
	}

	/**
	 * Add Score API configuration to the editor bundle config.
	 *
	 * @param array<string, mixed> $config Editor config.
	 *
	 * @return array<string, mixed>
	 */
	public static function extend_editor_config( array $config ): array {
		$api = ScoreRestController::get_editor_config();
		if ( ! empty( $api ) ) {
			$config['scoreApi'] = $api;
		}
		$settings                  = Settings::get();
		$config['scoreCalculator'] = array(
			'postTypes'  => isset( $settings['postTypes'] ) && is_array( $settings['postTypes'] )
				? array_values( array_filter( array_map( 'strval', $settings['postTypes'] ) ) )
				: array(),
			'scoreCache' => self::current_score_cache(),
		);

		return $config;
	}

	/**
	 * Resolve the persisted score cache for the current editor post.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function current_score_cache(): ?array {
		$post = get_post();
		if ( ! $post instanceof WP_Post || $post->ID <= 0 ) {
			return null;
		}

		$cache = get_post_meta( $post->ID, Constants::META_SCORE_CACHE, true );
		if ( ! is_array( $cache ) ) {
			return null;
		}

		if ( ! isset( $cache['score'] ) || ! isset( $cache['max'] ) || ! is_numeric( $cache['score'] ) || ! is_numeric( $cache['max'] ) ) {
			return null;
		}

		return array(
			'post_id'  => $post->ID,
			'total'    => array(
				'score' => 0 + $cache['score'],
				'max'   => 0 + $cache['max'],
			),
			'base'     => array(
				'score' => 0 + $cache['score'],
				'max'   => 0 + $cache['max'],
			),
			'bonus'    => array(
				'score' => 0,
				'max'   => 0,
			),
			'version'  => '',
			'pack'     => '',
			'language' => '',
		);
	}
}
