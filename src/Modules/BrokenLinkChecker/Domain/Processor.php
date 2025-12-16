<?php
/**
 * Coordinates Broken Link Checker processing.
 *
 * @package Airygen\Modules\BrokenLinkChecker\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\BrokenLinkChecker\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes batches of link checks and persists results.
 */
final class Processor {

	/**
	 * Link data repository.
	 *
	 * @var LinkDataRepository
	 */
	private $links;

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 */
	private $logs;

	/**
	 * HTTP client.
	 *
	 * @var HttpChecker
	 */
	private $http;

	/**
	 * Constructor.
	 *
	 * @param LinkDataRepository $links Link data repository.
	 * @param LogRepository      $logs  Log repository.
	 * @param HttpChecker        $http  HTTP checker.
	 */
	public function __construct(
		LinkDataRepository $links,
		LogRepository $logs,
		HttpChecker $http
	) {
		$this->links = $links;
		$this->logs  = $logs;
		$this->http  = $http;
	}

	/**
	 * Run a single batch.
	 *
	 * @param array<string,mixed> $settings Broken Link Checker settings.
	 * @return int Number of processed links.
	 */
	public function run( array $settings ): int {
		$limit                = max( 1, (int) ( $settings['max_requests_per_run'] ?? 5 ) );
		$interval             = max( 1, (int) ( $settings['check_interval_hours'] ?? 1 ) ) * 60;
		$link_types           = isset( $settings['link_types'] ) && is_array( $settings['link_types'] ) ? $settings['link_types'] : array();
		$candidates           = $this->links->get_candidates( $limit, $interval, $link_types );
		$redirects_as_warning = ! empty( $settings['treat_redirects_as_warning'] );
		$url_cache            = array();
		$stale_cutoff         = time() - ( $interval * 60 );

		if ( empty( $candidates ) ) {
			return 0;
		}

		foreach ( $candidates as $candidate ) {
			$link_id = (int) ( $candidate['id'] ?? 0 );
			if ( $link_id <= 0 ) {
				continue;
			}

			$url = isset( $candidate['url'] ) ? (string) $candidate['url'] : '';
			if ( '' === $url ) {
				continue;
			}

			$post_id = isset( $candidate['post_id'] ) ? (int) $candidate['post_id'] : 0;
			$now     = gmdate( 'Y-m-d H:i:s' );

			if ( isset( $url_cache[ $url ] ) ) {
				$cached        = $url_cache[ $url ];
				$cached_stamp  = isset( $cached['checked_at'] ) && '' !== $cached['checked_at'] ? (string) $cached['checked_at'] : $now;
				$cached_bucket = isset( $cached['bucket'] ) ? (int) $cached['bucket'] : 0;

				$this->logs->upsert(
					array(
						'link_id'       => $link_id,
						'post_id'       => $post_id,
						'url'           => $url,
						'status_code'   => $cached['status_code'] ?? 0,
						'status_label'  => $cached['status_label'] ?? '',
						'error_message' => $cached['error_message'] ?? '',
						'checked_at'    => $cached_stamp,
						'created_at'    => $cached['created_at'] ?? $cached_stamp,
						'data_source'   => 'loop_cache',
					)
				);

				$this->links->update_status( $link_id, $cached_bucket, $cached_stamp );
				continue;
			}

			$existing = $this->logs->find_by_link_id( $link_id );
			if ( $existing ) {
				$checked_at    = ! empty( $existing['checked_at'] ) ? (string) $existing['checked_at'] : '';
				$checked_at_ts = '' !== $checked_at ? strtotime( $checked_at . ' UTC' ) : 0;
				$is_fresh      = $checked_at_ts && $checked_at_ts > $stale_cutoff;

				if ( $is_fresh ) {
					$bucket           = $this->map_status_bucket(
						isset( $existing['status_code'] ) ? (int) $existing['status_code'] : null,
						$existing['error_message'] ?? null,
						$redirects_as_warning
					);
					$normalized_entry = array(
						'status_code'   => isset( $existing['status_code'] ) ? (int) $existing['status_code'] : null,
						'status_label'  => isset( $existing['status_label'] ) ? (string) $existing['status_label'] : null,
						'error_message' => $existing['error_message'] ?? null,
						'checked_at'    => '' !== $checked_at ? $checked_at : $now,
						'created_at'    => $existing['created_at'] ?? ( '' !== $checked_at ? $checked_at : $now ),
						'bucket'        => $bucket,
						'data_source'   => 'db_cache',
					);

					$url_cache[ $url ] = $normalized_entry;

					$this->logs->upsert(
						array(
							'link_id'       => $link_id,
							'post_id'       => $post_id,
							'url'           => $url,
							'status_code'   => $normalized_entry['status_code'] ?? 0,
							'status_label'  => $normalized_entry['status_label'] ?? '',
							'error_message' => $normalized_entry['error_message'] ?? '',
							'checked_at'    => $normalized_entry['checked_at'],
							'created_at'    => $normalized_entry['created_at'],
							'data_source'   => 'db_cache',
						)
					);

					$this->links->update_status( $link_id, $bucket, $normalized_entry['checked_at'] );
					continue;
				}
			}

			$result = $this->http->check( $url );
			if ( $redirects_as_warning && 3 === $result['bucket'] ) {
				$result['bucket'] = 2;
			}

			$result['data_source'] = 'http_request';
			$result['created_at']  = $result['checked_at'];

			$url_cache[ $url ] = $result;

			$this->logs->upsert(
				array(
					'link_id'       => $link_id,
					'post_id'       => $post_id,
					'url'           => $url,
					'status_code'   => $result['status_code'],
					'status_label'  => $result['status_label'],
					'error_message' => $result['error_message'],
					'checked_at'    => $result['checked_at'],
					'created_at'    => $result['created_at'],
					'data_source'   => $result['data_source'],
				)
			);

			$this->links->update_status( $link_id, $result['bucket'], $result['checked_at'] );
		}

		return count( $candidates );
	}

	/**
	 * Derive a bucket value from stored log data.
	 *
	 * @param int|null    $status HTTP status code.
	 * @param string|null $error  Error message.
	 * @param bool        $redirects_as_warning Whether redirects are treated as warnings.
	 * @return int
	 */
	private function map_status_bucket( ?int $status, ?string $error, bool $redirects_as_warning ): int {
		if ( null === $status ) {
			return ( null === $error || '' === $error ) ? 0 : 5;
		}

		if ( $status >= 200 && $status < 300 ) {
			return 2;
		}

		if ( $status >= 300 && $status < 400 ) {
			return $redirects_as_warning ? 2 : 3;
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
