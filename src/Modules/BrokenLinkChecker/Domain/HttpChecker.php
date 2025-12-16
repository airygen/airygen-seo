<?php
/**
 * Performs HTTP requests for the Broken Link Checker.
 *
 * @package Airygen\Modules\BrokenLinkChecker\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\BrokenLinkChecker\Domain;

use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wrapper around wp_remote_* calls providing normalized results.
 */
final class HttpChecker {

	/**
	 * Settings payload from the admin option.
	 *
	 * @var array<string,mixed>
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Broken Link Checker settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Perform a request and return the outcome.
	 *
	 * @param string $url Target URL.
	 * @return array{status_code:int|null,status_label:string,error_message:?string,bucket:int,checked_at:string}
	 */
	public function check( string $url ): array {
		$args = $this->build_request_args();

		$response = wp_remote_head( $url, $args );

		$code  = null;
		$error = null;

		if ( $response instanceof WP_Error ) {
			$error = $response->get_error_message();
		} else {
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 0 === $code || 405 === $code ) {
				$args['method'] = 'GET';
				$response       = wp_remote_get( $url, $args );
				if ( $response instanceof WP_Error ) {
					$error = $response->get_error_message();
					$code  = null;
				} else {
					$code = (int) wp_remote_retrieve_response_code( $response );
				}
			}
		}

		$label  = $this->status_label( $code, $error );
		$bucket = $this->status_bucket( $code, $error );

		return array(
			'status_code'   => $code,
			'status_label'  => $label,
			'error_message' => $error,
			'bucket'        => $bucket,
			'checked_at'    => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Build request arguments for the HTTP call.
	 *
	 * @return array<string,mixed>
	 */
	private function build_request_args(): array {
		$operation_timeout  = isset( $this->settings['operation_timeout_seconds'] )
		? max( 1, (int) $this->settings['operation_timeout_seconds'] )
		: 20;
		$connection_timeout = isset( $this->settings['connection_timeout_seconds'] )
		? max( 1, (int) $this->settings['connection_timeout_seconds'] )
		: 2;

		$args = array(
			'timeout'             => $operation_timeout,
			'redirection'         => 3,
			'user-agent'          => $this->choose_user_agent(),
			'headers'             => array(),
			'sslverify'           => apply_filters( 'https_ssl_verify', true ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
			'limit_response_size' => 1024,
		);

		// Requests (the HTTP transport) honours connect_timeout when provided.
		if ( ! isset( $args['connect_timeout'] ) ) {
			$args['connect_timeout'] = $connection_timeout;
		}

		return $args;
	}

	/**
	 * Pick a realistic browser user agent so remote servers treat requests as organic traffic.
	 *
	 * @return string
	 */
	private function choose_user_agent(): string {
		$agents = array(
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Safari/605.1.15',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/121.0.0.0 Safari/537.36',
		);

		try {
			$index = random_int( 0, count( $agents ) - 1 );
		} catch ( Throwable $exception ) {
			$index = 0;
		}

		return $agents[ $index ];
	}

	/**
	 * Determine a human readable status label.
	 *
	 * @param int|null    $status HTTP status code.
	 * @param string|null $error  Error message if request failed.
	 * @return string
	 */
	private function status_label( ?int $status, ?string $error ): string {
		if ( null !== $error && '' !== $error ) {
			return 'error';
		}

		if ( null === $status ) {
			return 'unknown';
		}

		if ( $status >= 200 && $status < 300 ) {
			return 'ok';
		}

		if ( $status >= 300 && $status < 400 ) {
			return 'redirect';
		}

		if ( $status >= 400 && $status < 500 ) {
			return 'client_error';
		}

		if ( $status >= 500 ) {
			return 'server_error';
		}

		return 'unknown';
	}

	/**
	 * Map HTTP results to the internal status bucket.
	 *
	 * @param int|null    $status HTTP status code.
	 * @param string|null $error  Error string if present.
	 * @return int
	 */
	private function status_bucket( ?int $status, ?string $error ): int {
		if ( null === $status ) {
			return ( null === $error || '' === $error ) ? 0 : 5;
		}

		if ( $status >= 200 && $status < 300 ) {
			return 2;
		}

		if ( $status >= 300 && $status < 400 ) {
			return 3;
		}

		if ( $status >= 400 && $status < 500 ) {
			return 4;
		}

		if ( $status >= 500 ) {
			return 5;
		}

		return 0;
	}
}
