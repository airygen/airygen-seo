<?php
/**
 * Pure evaluator for Sitewide SEO diagnostics.
 *
 * @package Airygen\Modules\SitewideSeo\Domain\Service
 */

declare(strict_types=1);

namespace Airygen\Modules\SitewideSeo\Domain\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates inputs for the Sitewide SEO module.
 */
final class Evaluator {

	public const STATUS_GOOD        = 'good';
	public const STATUS_RECOMMENDED = 'recommended';
	public const STATUS_CRITICAL    = 'critical';

	/**
	 * Evaluate the core sitemap availability.
	 *
	 * @param array<string, mixed> $input Input payload.
	 *
	 * @return array{status:string,code:string,details:array<string,mixed>}
	 */
	public static function core_sitemap( array $input ): array {
		$enabled     = (bool) ( $input['sitemap_enabled'] ?? false );
		$http_status = isset( $input['http_status'] ) ? self::int_or_null( $input['http_status'] ) : null;
		$error       = self::string_or_null( $input['error'] ?? null );

		if ( ! $enabled ) {
			return self::result( self::STATUS_RECOMMENDED, 'disabled' );
		}

		if ( null !== $error ) {
			return self::result(
				self::STATUS_CRITICAL,
				'unreachable',
				array( 'error' => $error )
			);
		}

		if ( null === $http_status ) {
			return self::result( self::STATUS_RECOMMENDED, 'unknown' );
		}

		if ( $http_status >= 200 && $http_status < 300 ) {
			return self::result(
				self::STATUS_GOOD,
				'reachable',
				array( 'status' => $http_status )
			);
		}

		return self::result(
			self::STATUS_CRITICAL,
			'unexpected_status',
			array( 'status' => $http_status )
		);
	}

	/**
	 * Evaluate the Score Calculator REST endpoint status.
	 *
	 * @param array<string, mixed> $input Input payload.
	 *
	 * @return array{status:string,code:string,details:array<string,mixed>}
	 */
	public static function score_rest( array $input ): array {
		$route_registered = (bool) ( $input['route_registered'] ?? false );
		$response_code    = isset( $input['response_code'] ) ? self::int_or_null( $input['response_code'] ) : null;
		$missing_post     = (bool) ( $input['missing_post'] ?? false );
		$error            = self::string_or_null( $input['error'] ?? null );

		if ( ! $route_registered ) {
			return self::result( self::STATUS_CRITICAL, 'missing_route' );
		}

		if ( $missing_post ) {
			return self::result( self::STATUS_RECOMMENDED, 'no_posts' );
		}

		if ( null !== $error ) {
			return self::result(
				self::STATUS_CRITICAL,
				'request_failed',
				array( 'error' => $error )
			);
		}

		if ( null === $response_code ) {
			return self::result( self::STATUS_RECOMMENDED, 'unknown' );
		}

		if ( $response_code >= 200 && $response_code < 300 ) {
			return self::result(
				self::STATUS_GOOD,
				'ok',
				array( 'status' => $response_code )
			);
		}

		return self::result(
			self::STATUS_CRITICAL,
			'unexpected_status',
			array( 'status' => $response_code )
		);
	}

	/**
	 * Evaluate robots visibility configuration.
	 *
	 * @param array<string, mixed> $input Input payload.
	 *
	 * @return array{status:string,code:string,details:array<string,mixed>}
	 */
	public static function robots_visibility( array $input ): array {
		$environment       = strtolower( (string) ( $input['environment'] ?? 'production' ) );
		$blog_public       = (bool) ( $input['blog_public'] ?? true );
		$default_directive = strtolower( (string) ( $input['default_directive'] ?? '' ) );

		if ( 'production' !== $environment ) {
			return self::result(
				self::STATUS_GOOD,
				'non_production',
				array( 'environment' => $environment )
			);
		}

		if ( ! $blog_public ) {
			return self::result( self::STATUS_CRITICAL, 'blog_public_disabled' );
		}

		if ( self::directive_contains_noindex( $default_directive ) ) {
			return self::result( self::STATUS_CRITICAL, 'noindex_directive' );
		}

		return self::result( self::STATUS_GOOD, 'visible' );
	}

	/**
	 * Evaluate permalink structure health.
	 *
	 * @param array<string, mixed> $input Input payload.
	 *
	 * @return array{status:string,code:string,details:array<string,mixed>}
	 */
	public static function permalink_structure( array $input ): array {
		$structure = (string) ( $input['structure'] ?? '' );
		$structure = trim( $structure );

		if ( '' === $structure ) {
			return self::result( self::STATUS_CRITICAL, 'plain' );
		}

		if ( false === strpos( $structure, '%postname%' ) ) {
			return self::result(
				self::STATUS_CRITICAL,
				'missing_postname',
				array( 'structure' => $structure )
			);
		}

		return self::result(
			self::STATUS_GOOD,
			'pretty',
			array( 'structure' => $structure )
		);
	}

	/**
	 * Evaluate SSL status.
	 *
	 * @param array<string, mixed> $input Input payload.
	 *
	 * @return array{status:string,code:string,details:array<string,mixed>}
	 */
	public static function ssl_status( array $input ): array {
		$status  = strtolower( (string) ( $input['status'] ?? '' ) );
		$message = self::string_or_null( $input['message'] ?? null );

		switch ( $status ) {
			case 'ok':
			case 'valid':
				return self::result(
					self::STATUS_GOOD,
					'valid',
					array( 'message' => $message )
				);
			case 'warning':
				return self::result(
					self::STATUS_RECOMMENDED,
					'warning',
					array( 'message' => $message )
				);
			case 'error':
			case 'invalid':
				return self::result(
					self::STATUS_CRITICAL,
					'invalid',
					array( 'message' => $message )
				);
			default:
				return self::result(
					self::STATUS_RECOMMENDED,
					'api_unknown',
					array( 'message' => $message )
				);
		}
	}

	/**
	 * Evaluate Search Console link status.
	 *
	 * @param array<string, mixed> $input Input payload.
	 *
	 * @return array{status:string,code:string,details:array<string,mixed>}
	 */
	public static function search_console( array $input ): array {
		$linked = (bool) ( $input['linked'] ?? false );

		if ( $linked ) {
			return self::result( self::STATUS_GOOD, 'linked' );
		}

		return self::result( self::STATUS_RECOMMENDED, 'not_configured' );
	}

	/**
	 * Helper to normalize result payloads.
	 *
	 * @param string               $status  One of the STATUS_* constants.
	 * @param string               $code    Short identifier for the evaluation.
	 * @param array<string, mixed> $details Optional detail payload.
	 *
	 * @return array{status:string,code:string,details:array<string,mixed>}
	 */
	private static function result( string $status, string $code, array $details = array() ): array {
		return array(
			'status'  => $status,
			'code'    => $code,
			'details' => $details,
		);
	}

	/**
	 * Cast a scalar to an int or null.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int|null
	 */
	private static function int_or_null( $value ): ?int {
		if ( null === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		return null;
	}

	/**
	 * Normalize a string-ish value to either a trimmed string or null.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string|null
	 */
	private static function string_or_null( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_scalar( $value ) ) {
			$value = trim( (string) $value );
			return '' === $value ? null : $value;
		}

		return null;
	}

	/**
	 * Determine whether a directive string implies noindex.
	 *
	 * @param string $directive Directive string.
	 *
	 * @return bool
	 */
	private static function directive_contains_noindex( string $directive ): bool {
		if ( '' === $directive ) {
			return false;
		}

		$directives = array_map( 'trim', explode( ',', $directive ) );

		foreach ( $directives as $single ) {
			if ( 'noindex' === $single || 'none' === $single ) {
				return true;
			}
		}

		return false;
	}
}
