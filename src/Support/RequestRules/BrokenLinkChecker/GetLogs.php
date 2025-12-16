<?php
/**
 * Input rules for Broken Link Checker logs endpoint.
 *
 * @package Airygen\Support\RequestRules\BrokenLinkChecker
 */

declare(strict_types=1);

namespace Airygen\Support\RequestRules\BrokenLinkChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides REST API argument schema via __invoke to mimic Laravel validators.
 */
final class GetLogs {

	/**
	 * Return the argument schema for the logs endpoint.
	 *
	 * @return array<string,mixed>
	 */
	public function __invoke(): array {
		return array(
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'validate_callback' => static function ( $value ): bool {
					return is_numeric( $value ) && (int) $value > 0;
				},
			),
			'statuses' => array(
				'type'              => 'string',
				'description'       => 'Comma-separated status buckets (ok,redirect,error)',
				'sanitize_callback' => static function ( $value ): string {
					return is_string( $value ) ? $value : '';
				},
			),
		);
	}
}
