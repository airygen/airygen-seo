<?php
/**
 * Registers Sitewide SEO diagnostics for Airygen SEO.
 *
 * @package Airygen\Modules\SitewideSeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\SitewideSeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\SitewideSeo\Domain\Service\Evaluator;
use Airygen\Support\Debug\Logger;
use WP_Error;

/**
 * Hooks Sitewide SEO into WordPress.
 */
final class Hooks {

	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Cached diagnostics for the current request.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $diagnostics = null;

	/**
	 * Timestamp for cached diagnostics.
	 *
	 * @var string|null
	 */
	private static $diagnostics_timestamp = null;

	/**
	 * Recursion guard.
	 *
	 * @var bool
	 */
	private static $is_calculating = false;

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( class_exists( ModuleSettings::class ) && ! ModuleSettings::is_enabled( 'siteHealth' ) ) {
			return;
		}

		add_filter( 'site_status_tests', array( __CLASS__, 'register_tests' ) );
		add_filter( 'debug_information', array( __CLASS__, 'add_debug_information' ) );
	}

	/**
	 * Register Site Health tests.
	 *
	 * @param array<string, mixed> $tests Existing tests.
	 *
	 * @return array<string, mixed>
	 */
	public static function register_tests( array $tests ): array {
		$tests['direct']['airygen_core_sitemap']      = self::direct_entry( __( 'Airygen SEO: Core sitemap availability', 'airygen-seo' ), 'core_sitemap' );
		$tests['direct']['airygen_score_rest']        = self::direct_entry( __( 'Airygen SEO: Score Calculator REST endpoint', 'airygen-seo' ), 'score_rest' );
		$tests['direct']['airygen_robots_visibility'] = self::direct_entry( __( 'Airygen SEO: Robots visibility', 'airygen-seo' ), 'robots_visibility' );
		$tests['direct']['airygen_permalink']         = self::direct_entry( __( 'Airygen SEO: Permalink structure', 'airygen-seo' ), 'permalink_structure' );
		$tests['direct']['airygen_ssl']               = self::direct_entry( __( 'Airygen SEO: SSL configuration', 'airygen-seo' ), 'ssl_status' );
		$tests['direct']['airygen_search_console']    = self::direct_entry( __( 'Airygen SEO: Search Console link', 'airygen-seo' ), 'search_console' );

		return $tests;
	}

	/**
	 * Add Airygen diagnostics to the Site Health Info tab.
	 *
	 * @param array<string, mixed> $info Existing debug sections.
	 *
	 * @return array<string, mixed>
	 */
	public static function add_debug_information( array $info ): array {
		$fields = array();
		foreach ( self::get_results() as $slug => $result ) {
			$fields[ $slug ] = self::debug_field( $result );
		}

		$info['airygen-seo'] = array(
			'label'       => __( 'Airygen SEO Diagnostics', 'airygen-seo' ),
			'description' => __( 'Status of Airygen SEO health checks, mirroring the Site Health Status page.', 'airygen-seo' ),
			'fields'      => $fields,
		);

		return $info;
	}

	/**
	 * Public accessor for diagnostics (used by the REST controller).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_results(): array {
		if ( is_array( self::$diagnostics ) ) {
			return self::$diagnostics;
		}

		if ( self::$is_calculating ) {
			return array();
		}

		$cached = self::get_cached_payload();
		if ( null !== $cached ) {
			self::$diagnostics           = $cached['tests'];
			self::$diagnostics_timestamp = $cached['timestamp'];
			return self::$diagnostics;
		}

		self::$is_calculating = true;

		$results = array(
			'core_sitemap'        => self::core_sitemap_result(),
			'robots_visibility'   => self::robots_visibility_result(),
			'permalink_structure' => self::permalink_result(),
			'ssl_status'          => self::ssl_result(),
			'search_console'      => self::search_console_result(),
		);

		self::$diagnostics           = $results;
		self::$is_calculating        = false;
		self::$diagnostics_timestamp = current_time( 'mysql' );

		self::store_cached_payload( $results, self::$diagnostics_timestamp );

		return self::$diagnostics;
	}

	/**
	 * Return the latest diagnostics timestamp (from cache or current run).
	 *
	 * @return string|null
	 */
	public static function get_results_timestamp(): ?string {
		if ( null !== self::$diagnostics_timestamp ) {
			return self::$diagnostics_timestamp;
		}

		$cached = self::get_cached_payload();
		if ( null !== $cached ) {
			self::$diagnostics_timestamp = $cached['timestamp'];
			return self::$diagnostics_timestamp;
		}

		return null;
	}

	/**
	 * Build a Site Health entry for the WP filter.
	 *
	 * @param string $label Entry label.
	 * @param string $slug  Diagnostic slug.
	 *
	 * @return array<string, mixed>
	 */
	private static function direct_entry( string $label, string $slug ): array {
		return array(
			'label' => $label,
			'test'  => static function () use ( $slug, $label ) {
				$results = self::get_results();

				return $results[ $slug ] ?? self::format_site_health_result(
					Evaluator::STATUS_RECOMMENDED,
					$label,
					__( 'Airygen SEO could not compute this diagnostic.', 'airygen-seo' ),
					array(),
					$slug
				);
			},
		);
	}

	/**
	 * Build the core sitemap result.
	 *
	 * @return array<string, mixed>
	 */
	private static function core_sitemap_result(): array {
		$use_airygen  = self::is_airygen_sitemap_enabled();
		$url          = $use_airygen ? home_url( '/sitemap.xml' ) : home_url( '/wp-sitemap.xml' );
		$status_code  = null;
		$error        = null;
		$is_enabled   = $use_airygen ? true : self::is_core_sitemap_enabled();
		$request_args = self::remote_args();

		Logger::log(
			'debug',
			sprintf(
				'Probing sitemap URL: %s (Airygen sitemap enabled: %s)',
				$url,
				$use_airygen ? 'yes' : 'no'
			)
		);

		if ( $is_enabled ) {
			$response = wp_remote_head( $url, $request_args );
			if ( $response instanceof WP_Error ) {
				$error = $response->get_error_message();
			} else {
				$status_code = (int) wp_remote_retrieve_response_code( $response );
				if ( 0 === $status_code || 405 === $status_code ) {
					$fallback = wp_remote_get( $url, $request_args );
					if ( $fallback instanceof WP_Error ) {
						$error = $fallback->get_error_message();
					} else {
						$status_code = (int) wp_remote_retrieve_response_code( $fallback );
					}
				}
			}
		}

		$evaluation = Evaluator::core_sitemap(
			array(
				'sitemap_enabled' => $is_enabled,
				'http_status'     => $status_code,
				'error'           => $error,
			)
		);

		if ( $use_airygen ) {
			$messages = array(
				'reachable'         => array(
					'label'       => __( 'Airygen sitemap is reachable', 'airygen-seo' ),
					'description' => __( 'Airygen SEO confirmed the custom sitemap endpoint responded successfully.', 'airygen-seo' ),
					'actions'     => array(
						array(
							'label' => __( 'Open sitemap', 'airygen-seo' ),
							'url'   => esc_url_raw( $url ),
						),
					),
				),
				'unreachable'       => array(
					'label'       => __( 'Sitemap request failed', 'airygen-seo' ),
					'description' => __( 'Airygen SEO could not reach the custom sitemap endpoint.', 'airygen-seo' ),
				),
				'unexpected_status' => array(
					'label'       => __( 'Airygen sitemap returned an unexpected status', 'airygen-seo' ),
					'description' => __( 'The custom sitemap endpoint responded, but did not return a 2xx status code.', 'airygen-seo' ),
					'actions'     => array(
						array(
							'label' => __( 'Open sitemap', 'airygen-seo' ),
							'url'   => esc_url_raw( $url ),
						),
					),
				),
				'disabled'          => array(
					'label'       => __( 'Airygen sitemap disabled', 'airygen-seo' ),
					'description' => __( 'The Airygen sitemap module is disabled. Enable it under Airygen SEO settings to serve /sitemap.xml.', 'airygen-seo' ),
				),
			);
		} else {
			$messages = array(
				'reachable'         => array(
					'label'       => __( 'WordPress sitemap is reachable', 'airygen-seo' ),
					'description' => __( 'Airygen SEO confirmed the core sitemap endpoint responded successfully.', 'airygen-seo' ),
					'actions'     => array(
						array(
							'label' => __( 'Open sitemap', 'airygen-seo' ),
							'url'   => esc_url_raw( $url ),
						),
					),
				),
				'disabled'          => array(
					'label'       => __( 'Core sitemap appears disabled', 'airygen-seo' ),
					'description' => __( 'WordPress reported that the core sitemap feature is disabled. If this is intentional, no action is required.', 'airygen-seo' ),
				),
				'unreachable'       => array(
					'label'       => __( 'Core sitemap request failed', 'airygen-seo' ),
					'description' => __( 'Airygen SEO could not reach the WordPress core sitemap endpoint.', 'airygen-seo' ),
				),
				'unexpected_status' => array(
					'label'       => __( 'Core sitemap returned an unexpected status', 'airygen-seo' ),
					'description' => __( 'The sitemap endpoint responded, but did not return a 2xx status code.', 'airygen-seo' ),
					'actions'     => array(
						array(
							'label' => __( 'Open sitemap', 'airygen-seo' ),
							'url'   => esc_url_raw( $url ),
						),
					),
				),
			);
		}

		return self::format_from_evaluation( 'core_sitemap', $evaluation, $messages, $error, $status_code );
	}

	/**
	 * Build the score REST result.
	 *
	 * @return array<string, mixed>
	 */
	private static function score_rest_result(): array {
		$server           = rest_get_server();
		$route_registered = $server ? array_key_exists( '/airygen/v1/score', $server->get_routes() ) : false;
		$post_id          = self::find_sample_post_id();
		$response_code    = null;
		$error            = null;

		if ( $route_registered && $post_id ) {
			$request = new \WP_REST_Request( 'GET', '/airygen/v1/score' );
			$request->set_param( 'post', $post_id );
			$response = rest_do_request( $request );
			if ( $response instanceof WP_Error ) {
				$error = $response->get_error_message();
			} elseif ( $response instanceof \WP_REST_Response ) {
				$response_code = (int) $response->get_status();
			}
		}

		$evaluation = Evaluator::score_rest(
			array(
				'route_registered' => $route_registered,
				'response_code'    => $response_code,
				'missing_post'     => null === $post_id,
				'error'            => $error,
			)
		);

		$messages = array(
			'ok'                => array(
				'label'       => __( 'Score Calculator endpoint is healthy', 'airygen-seo' ),
				'description' => __( 'Airygen SEO successfully queried the scoring endpoint.', 'airygen-seo' ),
			),
			'missing_route'     => array(
				'label'       => __( 'Score Calculator REST route missing', 'airygen-seo' ),
				'description' => __( 'The /airygen/v1/score route is not registered. Ensure the Score Calculator module is enabled.', 'airygen-seo' ),
			),
			'no_posts'          => array(
				'label'       => __( 'No published posts available for scoring', 'airygen-seo' ),
				'description' => __( 'Airygen SEO could not find a published post to test the scoring endpoint.', 'airygen-seo' ),
			),
			'request_failed'    => array(
				'label'       => __( 'Score Calculator endpoint returned an error', 'airygen-seo' ),
				'description' => __( 'The scoring endpoint responded with an error when requested.', 'airygen-seo' ),
			),
			'unexpected_status' => array(
				'label'       => __( 'Score Calculator endpoint returned an unexpected status', 'airygen-seo' ),
				'description' => __( 'The scoring endpoint responded, but did not return a 2xx HTTP status.', 'airygen-seo' ),
			),
			'unknown'           => array(
				'label'       => __( 'Score Calculator endpoint status unknown', 'airygen-seo' ),
				'description' => __( 'Airygen SEO could not confirm the scoring endpoint status.', 'airygen-seo' ),
			),
		);

		return self::format_from_evaluation( 'score_rest', $evaluation, $messages, $error, $response_code );
	}

	/**
	 * Build the robots visibility result.
	 *
	 * @return array<string, mixed>
	 */
	private static function robots_visibility_result(): array {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$blog_public = '1' === (string) get_option( 'blog_public', '1' );

		$config            = get_option( Constants::OPTION_ROBOTS, array() );
		$default_directive = '';
		if ( isset( $config['default_directive'] ) ) {
			$default_directive = (string) $config['default_directive'];
		}

		$evaluation = Evaluator::robots_visibility(
			array(
				'environment'       => $environment,
				'blog_public'       => $blog_public,
				'default_directive' => $default_directive,
			)
		);

		$messages = array(
			'visible'              => array(
				'label'       => __( 'Robots visibility is healthy', 'airygen-seo' ),
				'description' => __( 'Search engines are allowed to index this site.', 'airygen-seo' ),
			),
			'blog_public_disabled' => array(
				'label'       => __( 'WordPress is set to discourage indexing', 'airygen-seo' ),
				'description' => __( 'The “Discourage search engines from indexing” option is enabled. Disable it in Settings → Reading for production sites.', 'airygen-seo' ),
				'actions'     => array(
					array(
						'label' => __( 'Open Reading Settings', 'airygen-seo' ),
						'url'   => admin_url( 'options-reading.php' ),
					),
				),
			),
			'noindex_directive'    => array(
				'label'       => __( 'Airygen SEO default robots directive blocks indexing', 'airygen-seo' ),
				'description' => __( 'The Airygen SEO robots settings apply a noindex directive by default. Update the setting if this site should be indexable.', 'airygen-seo' ),
				'actions'     => array(
					array(
						'label' => __( 'Open Airygen SEO settings', 'airygen-seo' ),
						'url'   => admin_url( 'options-general.php?page=airygen-options' ),
					),
				),
			),
			'non_production'       => array(
				'label'       => __( 'Non-production environment detected', 'airygen-seo' ),
				'description' => __( 'Search engine visibility checks are informational outside production environments.', 'airygen-seo' ),
			),
		);

		return self::format_from_evaluation( 'robots_visibility', $evaluation, $messages );
	}

	/**
	 * Build the permalink result.
	 *
	 * @return array<string, mixed>
	 */
	private static function permalink_result(): array {
		$structure  = (string) get_option( 'permalink_structure', '' );
		$evaluation = Evaluator::permalink_structure(
			array(
				'structure' => $structure,
			)
		);

		$messages = array(
			'pretty'           => array(
				'label'       => __( 'Permalink structure includes post names', 'airygen-seo' ),
				'description' => __( 'Your permalinks include %postname%, which is ideal for SEO.', 'airygen-seo' ),
			),
			'plain'            => array(
				'label'       => __( 'Pretty permalinks are disabled', 'airygen-seo' ),
				'description' => __( 'Switch to a custom permalink structure instead of the default ?p=ID format.', 'airygen-seo' ),
				'actions'     => array(
					array(
						'label' => __( 'Open Permalink Settings', 'airygen-seo' ),
						'url'   => admin_url( 'options-permalink.php' ),
					),
				),
			),
			'missing_postname' => array(
				'label'       => __( 'Post names missing from permalinks', 'airygen-seo' ),
				'description' => __( 'Use a custom structure containing %postname% for better readability and SEO.', 'airygen-seo' ),
				'actions'     => array(
					array(
						'label' => __( 'Open Permalink Settings', 'airygen-seo' ),
						'url'   => admin_url( 'options-permalink.php' ),
					),
				),
			),
		);

		return self::format_from_evaluation( 'permalink_structure', $evaluation, $messages );
	}

	/**
	 * Build the SSL result.
	 *
	 * @return array<string, mixed>
	 */
	private static function ssl_result(): array {
		$response   = self::check_ssl( home_url( '/' ) );
		$evaluation = Evaluator::ssl_status(
			array(
				'status'  => $response['status'] ?? '',
				'message' => $response['message'] ?? null,
			)
		);
		$details    = isset( $evaluation['details'] ) && is_array( $evaluation['details'] )
		? $evaluation['details']
		: array();
		$extras     = array();

		if ( ! empty( $response['tls'] ) ) {
			$details['tls'] = (string) $response['tls'];
			$extras[]       = sprintf(
				/* translators: %s TLS version string. */
				__( 'TLS: %s.', 'airygen-seo' ),
				$details['tls']
			);
		}

		if ( ! empty( $response['expires_at'] ) ) {
			$details['expires_at'] = (string) $response['expires_at'];
			$extras[]              = sprintf(
				/* translators: %s Certificate expiration date. */
				__( 'Certificate expires: %s.', 'airygen-seo' ),
				$details['expires_at']
			);
		}

		if ( ! empty( $extras ) ) {
			$details_message       = isset( $details['message'] ) ? (string) $details['message'] : '';
			$details['message']    = trim( $details_message . ' ' . implode( ' ', $extras ) );
			$evaluation['details'] = $details;
		}

		$messages = array(
			'valid'       => array(
				'label'       => __( 'SSL certificate detected', 'airygen-seo' ),
				'description' => __( 'Airygen SEO detected a valid SSL configuration.', 'airygen-seo' ),
			),
			'warning'     => array(
				'label'       => __( 'Potential SSL issue detected', 'airygen-seo' ),
				'description' => __( 'The SSL check returned warnings. Inspect the certificate or HTTPS configuration.', 'airygen-seo' ),
			),
			'invalid'     => array(
				'label'       => __( 'SSL certificate appears invalid', 'airygen-seo' ),
				'description' => __( 'Airygen SEO could not verify a working SSL certificate for this site.', 'airygen-seo' ),
			),
			'api_unknown' => array(
				'label'       => __( 'SSL status unknown', 'airygen-seo' ),
				'description' => __( 'Airygen SEO could not determine the SSL status.', 'airygen-seo' ),
			),
		);

		return self::format_from_evaluation( 'ssl_status', $evaluation, $messages );
	}

	/**
	 * Perform an SSL diagnostic for the provided URL.
	 *
	 * @param string $site_url Absolute site URL under test.
	 *
	 * @return array<string, mixed>
	 */
	private static function check_ssl( string $site_url ): array {
		$raw_url = esc_url_raw( $site_url );
		if ( '' === $raw_url ) {
			return array(
				'status'  => 'invalid',
				'message' => 'Invalid site URL.',
			);
		}

		$parsed = wp_parse_url( $raw_url );
		if ( empty( $parsed['host'] ) ) {
			return array(
				'status'  => 'invalid',
				'message' => 'Unable to resolve site host.',
			);
		}

		$scheme     = isset( $parsed['scheme'] ) ? strtolower( (string) $parsed['scheme'] ) : '';
		$uses_https = 'https' === $scheme;
		$target_url = self::build_https_url( $parsed );

		if ( '' === $target_url ) {
			return array(
				'status'  => 'invalid',
				'message' => 'Unable to build HTTPS URL for SSL check.',
			);
		}

		$port        = isset( $parsed['port'] ) ? (int) $parsed['port'] : 443;
		$ssl_details = self::fetch_ssl_details( $parsed['host'], $port );

		$response = wp_remote_head(
			$target_url,
			array(
				'timeout'     => 3,
				'redirection' => 3,
				'sslverify'   => true,
				'user-agent'  => 'AirygenSEO/' . AIRYGEN_VERSION . ' (+' . home_url() . ')',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'     => 'error',
				'message'    => $response->get_error_message(),
				'tls'        => $ssl_details['tls'],
				'expires_at' => $ssl_details['expires_at'],
			);
		}

		if ( ! $uses_https ) {
			return array(
				'status'     => 'warning',
				'message'    => 'Site is not configured for HTTPS.',
				'tls'        => $ssl_details['tls'],
				'expires_at' => $ssl_details['expires_at'],
			);
		}

		return array(
			'status'     => 'ok',
			'message'    => 'SSL certificate verified.',
			'tls'        => $ssl_details['tls'],
			'expires_at' => $ssl_details['expires_at'],
		);
	}

	/**
	 * Build an HTTPS URL from a parsed URL array.
	 *
	 * @param array<string, mixed> $parsed Parsed URL.
	 *
	 * @return string
	 */
	private static function build_https_url( array $parsed ): string {
		$host = isset( $parsed['host'] ) ? (string) $parsed['host'] : '';
		if ( '' === $host ) {
			return '';
		}

		$path  = isset( $parsed['path'] ) ? (string) $parsed['path'] : '/';
		$query = isset( $parsed['query'] ) ? (string) $parsed['query'] : '';
		$port  = isset( $parsed['port'] ) ? (int) $parsed['port'] : 0;

		$port_part = '';
		if ( $port > 0 && 443 !== $port ) {
			$port_part = ':' . $port;
		}

		$url = 'https://' . $host . $port_part . $path;
		if ( '' !== $query ) {
			$url .= '?' . $query;
		}

		return $url;
	}

	/**
	 * Attempt to resolve TLS metadata for the given host.
	 *
	 * @param string $host Hostname.
	 * @param int    $port Port number.
	 *
	 * @return array{tls: string, expires_at: string}
	 */
	private static function fetch_ssl_details( string $host, int $port ): array {
		$details = array(
			'tls'        => '',
			'expires_at' => '',
		);

		$port = $port > 0 ? $port : 443;

		$context = stream_context_create(
			array(
				'ssl' => array(
					'capture_peer_cert' => true,
					'verify_peer'       => true,
					'verify_peer_name'  => true,
					'peer_name'         => $host,
					'SNI_enabled'       => true,
				),
			)
		);

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$client = @stream_socket_client(
			'ssl://' . $host . ':' . $port,
			$errno,
			$errstr,
			3,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( ! is_resource( $client ) ) {
			return $details;
		}

		$meta = stream_get_meta_data( $client );
		if ( isset( $meta['crypto'] ) && is_array( $meta['crypto'] ) ) {
			$details['tls'] = isset( $meta['crypto']['protocol'] ) ? (string) $meta['crypto']['protocol'] : '';
		}

		$params = stream_context_get_params( $client );
		if (
			function_exists( 'openssl_x509_parse' ) &&
			isset( $params['options']['ssl']['peer_certificate'] )
		) {
			$cert   = $params['options']['ssl']['peer_certificate'];
			$parsed = openssl_x509_parse( $cert );
			if ( is_array( $parsed ) && isset( $parsed['validTo_time_t'] ) ) {
				$details['expires_at'] = gmdate(
					'M d H:i:s Y \\G\\M\\T',
					(int) $parsed['validTo_time_t']
				);
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $client );

		return $details;
	}

	/**
	 * Build the Search Console result.
	 *
	 * @return array<string, mixed>
	 */
	private static function search_console_result(): array {
		$linked     = self::is_search_console_linked();
		$evaluation = Evaluator::search_console(
			array(
				'linked' => $linked,
			)
		);

		$messages = array(
			'linked'         => array(
				'label'       => __( 'Search Console linked', 'airygen-seo' ),
				'description' => __( 'Airygen SEO detected an active Google Search Console connection.', 'airygen-seo' ),
			),
			'not_configured' => array(
				'label'       => __( 'Search Console not linked', 'airygen-seo' ),
				'description' => __( 'Connect this site to Google Search Console for better indexing diagnostics. This feature is in development and coming soon.', 'airygen-seo' ),
			),
		);

		return self::format_from_evaluation( 'search_console', $evaluation, $messages );
	}

	/**
	 * Convert an evaluation into a Site Health result array.
	 *
	 * @param string                             $slug       Diagnostic slug.
	 * @param array<string, mixed>               $evaluation Evaluation payload.
	 * @param array<string, array<string,mixed>> $messages   Mapping of codes to label/description/actions.
	 * @param string|null                        $error      Optional error string.
	 * @param int|null                           $status     Optional HTTP status.
	 *
	 * @return array<string, mixed>
	 */
	private static function format_from_evaluation( string $slug, array $evaluation, array $messages, string $error = null, ?int $status = null ): array {
		$code    = $evaluation['code'] ?? 'unknown';
		$message = $messages[ $code ] ?? array(
			'label'       => __( 'Airygen SEO diagnostic', 'airygen-seo' ),
			'description' => __( 'Airygen SEO could not determine the diagnostic outcome.', 'airygen-seo' ),
		);

		if ( null !== $error ) {
			$message['description'] .= ' ' . sprintf(
				/* translators: %s Error message. */
				__( 'Error: %s.', 'airygen-seo' ),
				$error
			);
		}

		if ( null !== $status ) {
			$message['description'] .= ' ' . sprintf(
				/* translators: %d HTTP status code. */
				__( 'Status: %d.', 'airygen-seo' ),
				$status
			);
		}

		return self::format_site_health_result(
			$evaluation['status'] ?? Evaluator::STATUS_RECOMMENDED,
			$message['label'],
			$message['description'],
			$message['actions'] ?? array(),
			$slug,
			$evaluation
		);
	}

	/**
	 * Format a Site Health result payload.
	 *
	 * @param string                          $status      Status slug supported by Site Health.
	 * @param string                          $label       Result label.
	 * @param string                          $description Result description.
	 * @param array<int, array<string,mixed>> $actions Actions.
	 * @param string                          $slug        Diagnostic slug.
	 * @param array<string, mixed>            $evaluation  Evaluation payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function format_site_health_result( string $status, string $label, string $description, array $actions, string $slug, array $evaluation = array() ): array {
		$result = array(
			'status'      => $status,
			'label'       => $label,
			'description' => $description,
			'badge'       => self::badge(),
			'slug'        => $slug,
			'code'        => $evaluation['code'] ?? 'unknown',
			'details'     => $evaluation['details'] ?? array(),
		);

		if ( ! empty( $actions ) ) {
			$result['actions'] = $actions;
		}

		return $result;
	}

	/**
	 * Convert a Site Health result into a debug field.
	 *
	 * @param array<string, mixed> $result Result payload.
	 *
	 * @return array<string, string>
	 */
	private static function debug_field( array $result ): array {
		$status = ucfirst( (string) ( $result['status'] ?? '' ) );
		$label  = (string) ( $result['label'] ?? __( 'Diagnostic', 'airygen-seo' ) );
		$desc   = wp_strip_all_tags( (string) ( $result['description'] ?? '' ) );

		$value_parts = array();
		if ( '' !== $status ) {
			$value_parts[] = $status;
		}
		if ( '' !== $desc ) {
			$value_parts[] = $desc;
		}

		return array(
			'label' => $label,
			'value' => implode( ' — ', $value_parts ),
		);
	}

	/**
	 * Helper to compute the shared badge metadata.
	 *
	 * @return array<string, string>
	 */
	private static function badge(): array {
		return array(
			'label' => __( 'Airygen SEO', 'airygen-seo' ),
			'color' => 'blue',
		);
	}

	/**
	 * Determine whether the core sitemap feature is active.
	 *
	 * @return bool
	 */
	private static function is_core_sitemap_enabled(): bool {
		if ( ! function_exists( 'wp_sitemaps_get_server' ) ) {
			return false;
		}

		$server = wp_sitemaps_get_server();

		return $server && ! is_wp_error( $server );
	}

	/**
	 * Determine whether Airygen's sitemap module is enabled.
	 *
	 * @return bool
	 */
	private static function is_airygen_sitemap_enabled(): bool {
		if ( ! class_exists( ModuleSettings::class ) ) {
			return false;
		}

		return ModuleSettings::is_enabled( 'sitemap' );
	}

	/**
	 * Find an eligible published post or page for probing.
	 *
	 * @return int|null
	 */
	private static function find_sample_post_id(): ?int {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		$post_types = array_diff(
			$post_types,
			array(
				'attachment',
				'nav_menu_item',
				'revision',
			)
		);

		if ( empty( $post_types ) ) {
			return null;
		}

		$post_ids = get_posts(
			array(
				'post_type'   => $post_types,
				'post_status' => 'publish',
				'numberposts' => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'fields'      => 'ids',
			)
		);

		if ( empty( $post_ids ) ) {
			return null;
		}

		return (int) $post_ids[0];
	}

	/**
	 * Probe the homepage for head tag inspection.
	 *
	 * @return array<string, mixed>
	 */
	private static function probe_front_page(): array {
		$url      = home_url( '/' );
		$args     = self::remote_args();
		$response = wp_remote_get( $url, $args );

		if ( $response instanceof WP_Error ) {
			return array(
				'url'    => $url,
				'body'   => '',
				'status' => null,
				'error'  => $response->get_error_message(),
			);
		}

		return array(
			'url'    => $url,
			'body'   => (string) wp_remote_retrieve_body( $response ),
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'error'  => null,
		);
	}

	/**
	 * Shared remote request arguments.
	 *
	 * @return array<string, mixed>
	 */
	private static function remote_args(): array {
		return array(
			'timeout'     => 1,
			'redirection' => 0,
			'user-agent'  => 'Airygen SEO-SiteHealth/1.0 (+https://airygen.com)',
		);
	}

	/**
	 * Group result rows by post type.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows to group.
	 *
	 * @return array<string, int>
	 */
	private static function group_by_post_type( array $rows ): array {
		$grouped = array();

		foreach ( $rows as $row ) {
			$type = (string) ( $row['post_type'] ?? '' );
			if ( '' === $type ) {
				$type = 'unknown';
			}
			if ( ! isset( $grouped[ $type ] ) ) {
				$grouped[ $type ] = 0;
			}
			++$grouped[ $type ];
		}

		return $grouped;
	}

	/**
	 * Build a sample list of posts with edit links.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows to sample.
	 * @param int                              $limit Sample size.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function sample_posts( array $rows, int $limit = 5 ): array {
		$sample = array();
		$rows   = array_slice( $rows, 0, $limit );

		foreach ( $rows as $row ) {
			$post_id = isset( $row['ID'] ) ? (int) $row['ID'] : 0;
			if ( $post_id <= 0 ) {
				continue;
			}

			$edit_link = get_edit_post_link( $post_id, '' );

			$sample[] = array(
				'id'    => $post_id,
				'title' => get_the_title( $post_id ),
				'link'  => false !== $edit_link ? $edit_link : admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			);
		}

		return $sample;
	}

	/**
	 * List all public post types that can be analyzed.
	 *
	 * @return array<int, string>
	 */
	private static function public_post_types(): array {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		return array_values(
			array_diff(
				$post_types,
				array(
					'attachment',
					'nav_menu_item',
					'revision',
				)
			)
		);
	}

	/**
	 * Determine whether Search Console is linked.
	 *
	 * @return bool
	 */
	private static function is_search_console_linked(): bool {
		/**
		 * Allow integrations to report Search Console link status.
		 *
		 * @param bool $linked Whether the site is linked to Search Console.
		 */
		return (bool) apply_filters( Constants::HOOK_SITE_HEALTH_SEARCH_CONSOLE_LINKED, false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.
	}

	/**
	 * Remove the cached Site Health result so new checks appear immediately.
	 *
	 * @return void
	 */
	private static function flush_site_health_cache(): void {
		if ( is_multisite() ) {
			delete_site_transient( 'site_health_cached_result' );
			return;
		}

		delete_transient( 'site_health_cached_result' );
	}

	/**
	 * Read cached diagnostics payload from the options table.
	 *
	 * @return array{tests:array<string, array<string, mixed>>, timestamp:string}|null
	 */
	private static function get_cached_payload(): ?array {
		$cached = get_option( Constants::OPTION_SITEWIDE_SEO_CACHE );
		if ( ! is_array( $cached ) ) {
			return null;
		}

		$tests     = isset( $cached['tests'] ) && is_array( $cached['tests'] ) ? $cached['tests'] : null;
		$timestamp = isset( $cached['timestamp'] ) ? (string) $cached['timestamp'] : '';
		$generated = isset( $cached['generated_at'] ) ? (int) $cached['generated_at'] : 0;

		if ( empty( $tests ) || '' === $timestamp || $generated <= 0 ) {
			return null;
		}

		$age = time() - $generated;
		if ( $age < 0 || $age > self::CACHE_TTL ) {
			return null;
		}

		return array(
			'tests'     => $tests,
			'timestamp' => $timestamp,
		);
	}

	/**
	 * Store diagnostics payload into the options table.
	 *
	 * @param array<string, array<string, mixed>> $tests Tests payload.
	 * @param string                             $timestamp Timestamp string.
	 *
	 * @return void
	 */
	private static function store_cached_payload( array $tests, string $timestamp ): void {
		update_option(
			Constants::OPTION_SITEWIDE_SEO_CACHE,
			array(
				'tests'        => $tests,
				'timestamp'    => $timestamp,
				'generated_at' => time(),
			)
		);
	}
}
