<?php
/**
 * Processes IndexNow queue batches and dispatches HTTP requests.
 *
 * @package Airygen\Modules\InstantIndexing\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Runtime;

use Airygen\Modules\InstantIndexing\Admin\Settings;
use Airygen\Modules\InstantIndexing\Domain\Event;
use Airygen\Modules\InstantIndexing\Domain\PayloadBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes the IndexNow submission queue.
 */
final class Processor {

	/**
	 * Maximum URLs per IndexNow payload.
	 */
	private const API_LIMIT = 10000;

	/**
	 * Queue access.
	 *
	 * @var QueueRepository
	 */
	private $queue;

	/**
	 * Quota helper.
	 *
	 * @var QuotaTracker
	 */
	private $quota;

	/**
	 * Response log helper.
	 *
	 * @var ResponseLogger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param QueueRepository $queue  Queue repository.
	 * @param QuotaTracker    $quota  Quota tracker.
	 * @param ResponseLogger  $logger Response logger.
	 */
	public function __construct( QueueRepository $queue, QuotaTracker $quota, ResponseLogger $logger ) {
		$this->queue  = $queue;
		$this->quota  = $quota;
		$this->logger = $logger;
	}

	/**
	 * Process a single batch of queued events.
	 *
	 * @return void
	 */
	public function process(): void {
		$settings = Settings::get();

		if ( ! Settings::is_enabled( $settings ) ) {
			return;
		}

		$events = $this->queue->claim_batch( Settings::batch_size( $settings ) );
		if ( empty( $events ) ) {
			return;
		}

		$key = isset( $settings['key'] ) ? trim( (string) $settings['key'] ) : '';
		if ( '' === $key ) {
			$this->release_events( $events, __( 'IndexNow key missing. Generate a key in Settings.', 'airygen-seo' ), HOUR_IN_SECONDS );
			return;
		}

		$engines = Settings::enabled_engines( $settings );
		if ( empty( $engines ) ) {
			$this->release_events( $events, __( 'No IndexNow engines are enabled.', 'airygen-seo' ), HOUR_IN_SECONDS );
			return;
		}

		$key_location = Settings::key_location( $settings );
		$host_groups  = $this->group_by_host( $events );
		$batch_size   = Settings::batch_size( $settings );
		$quota_limit  = Settings::max_events_per_day( $settings );

		foreach ( $host_groups as $host => $host_events ) {
			$chunks = $this->chunk_events( $host_events, min( self::API_LIMIT, $batch_size ) );

			foreach ( $chunks as $chunk ) {
				$ids  = array_map(
					static function ( Event $event ): int {
						return $event->get_id();
					},
					$chunk
				);
				$urls = array_map(
					static function ( Event $event ): string {
						return $event->get_url();
					},
					$chunk
				);

				if ( ! $this->quota->reserve( count( $urls ), $quota_limit ) ) {
					$delay = max( 60, $this->quota->seconds_until_reset() );
					$this->release_events( $chunk, __( 'Daily IndexNow quota reached.', 'airygen-seo' ), $delay );
					continue;
				}

				$payload = PayloadBuilder::build(
					$host,
					$urls,
					$key,
					'' !== $key_location ? $key_location : null
				);
				$result  = $this->dispatch_to_engines( $engines, $payload );

				$this->queue->record_response( $ids, $result['responses'] );
				$this->logger->append( $result['log_entries'] );

				if ( $result['success'] ) {
					$this->queue->mark_completed( $ids );
					continue;
				}

				$delay = $result['retry_after'] ?? 0;
				if ( $delay > 0 ) {
					$this->release_events( $chunk, $result['message'], $delay );
				} else {
					$this->fail_events( $chunk, $result['message'] );
				}
			}
		}
	}

	/**
	 * Group events by host.
	 *
	 * @param array<int, Event> $events Events list.
	 * @return array<string, array<int, Event>>
	 */
	private function group_by_host( array $events ): array {
		$groups = array();
		foreach ( $events as $event ) {
			if ( ! $event instanceof Event ) {
				continue;
			}

			$host = strtolower( $event->get_host() );
			if ( '' === $host ) {
				$parsed = wp_parse_url( home_url(), PHP_URL_HOST );
				$host   = is_string( $parsed ) ? $parsed : '';
			}

			if ( ! isset( $groups[ $host ] ) ) {
				$groups[ $host ] = array();
			}

			$groups[ $host ][] = $event;
		}

		return $groups;
	}

	/**
	 * Split events into payload-friendly chunks.
	 *
	 * @param array<int, Event> $events Events for a single host.
	 * @param int               $size   Chunk size.
	 * @return array<int, array<int, Event>>
	 */
	private function chunk_events( array $events, int $size ): array {
		$size = max( 1, $size );
		return array_chunk( $events, $size );
	}

	/**
	 * Attempt to send payload to all enabled engines.
	 *
	 * @param array<string, string> $engines Map of slug => endpoint.
	 * @param array<string, mixed>  $payload Payload to send.
	 * @return array{success: bool, message: string, retry_after?: int, responses: array<string, mixed>, log_entries: array<int, array<string, mixed>>}
	 */
	private function dispatch_to_engines( array $engines, array $payload ): array {
		$all_success = true;
		$retry_after = 0;
		$message     = '';
		$responses   = array();
		$log_entries = array();

		foreach ( $engines as $slug => $endpoint ) {
			$response           = $this->send_request( $endpoint, $payload );
			$responses[ $slug ] = $response;

			$log_entries[] = array(
				'engine'      => $slug,
				'status_code' => $response['code'],
				'success'     => $response['success'],
				'message'     => $response['message'],
				'timestamp'   => gmdate( 'c' ),
			);

			if ( ! $response['success'] ) {
				$all_success = false;
				$message     = $response['message'];

				if ( 429 === $response['code'] ) {
					$retry_after = max( $retry_after, $response['retry_after'] ?? 300 );
				} elseif ( $response['retry_after'] ?? 0 ) {
					$retry_after = max( $retry_after, (int) $response['retry_after'] );
				}
			}
		}

		return array(
			'success'     => $all_success,
			'message'     => $message,
			'retry_after' => $retry_after,
			'responses'   => $responses,
			'log_entries' => $log_entries,
		);
	}

	/**
	 * Perform the HTTP request.
	 *
	 * @param string               $endpoint Endpoint URL.
	 * @param array<string, mixed> $payload  Request body.
	 * @return array{success: bool, code: int|null, message: string, retry_after?: int}
	 */
	private function send_request( string $endpoint, array $payload ): array {
		$args = array(
			'method'      => 'POST',
			'timeout'     => 15,
			'headers'     => array(
				'Content-Type' => 'application/json; charset=utf-8',
			),
			'body'        => wp_json_encode( $payload ),
			'data_format' => 'body',
		);

		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'code'    => null,
				'message' => $response->get_error_message(),
			);
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$body    = (string) wp_remote_retrieve_body( $response );
		$success = $code >= 200 && $code < 300;
		$retry   = 0;

		$headers = wp_remote_retrieve_headers( $response );
		if ( isset( $headers['retry-after'] ) ) {
			$retry = (int) $headers['retry-after'];
		}

		$error_message = $success ? __( 'Submission accepted.', 'airygen-seo' ) : ( '' !== trim( $body ) ? $body : sprintf(
			/* translators: %d: HTTP status code. */
			__( 'HTTP %d received.', 'airygen-seo' ),
			$code
		) );

		return array(
			'success'     => $success,
			'code'        => $code,
			'message'     => $error_message,
			'retry_after' => $retry,
		);
	}

	/**
	 * Release events back to pending with a delay.
	 *
	 * @param array<int, Event> $events Events to release.
	 * @param string            $message Error message.
	 * @param int               $delay   Delay seconds.
	 * @return void
	 */
	private function release_events( array $events, string $message, int $delay ): void {
		$ids = array();
		foreach ( $events as $event ) {
			if ( ! $event instanceof Event ) {
				continue;
			}

			if ( $event->get_attempts() >= QueueRepository::max_attempts() ) {
				$this->queue->mark_failed( array( $event->get_id() ), $message );
				continue;
			}

			$ids[] = $event->get_id();
		}

		if ( ! empty( $ids ) ) {
			$this->queue->release_with_delay( $ids, $message, $delay );
		}
	}

	/**
	 * Mark events as failed.
	 *
	 * @param array<int, Event> $events Events to mark.
	 * @param string            $message Error message.
	 * @return void
	 */
	private function fail_events( array $events, string $message ): void {
		$ids = array();
		foreach ( $events as $event ) {
			if ( $event instanceof Event ) {
				$ids[] = $event->get_id();
			}
		}

		if ( ! empty( $ids ) ) {
			$this->queue->mark_failed( $ids, $message );
		}
	}
}
