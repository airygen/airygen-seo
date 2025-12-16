<?php
/**
 * Teams channel for Notify.
 *
 * @package Airygen\Modules\Notify\Infrastructure\Channels
 */

declare(strict_types=1);

namespace Airygen\Modules\Notify\Infrastructure\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Notify\Domain\Channel\ChannelInterface;

/**
 * Sends notifications to Teams webhook.
 */
final class TeamsChannel implements ChannelInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'teams';
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate( array $settings ): array {
		$webhook = isset( $settings['channels']['teams']['webhook'] ) ? trim( (string) $settings['channels']['teams']['webhook'] ) : '';
		if ( '' === $webhook ) {
			return array(
				'ok'      => false,
				'message' => 'Webhook URL is required.',
			);
		}
		return array( 'ok' => true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( array $settings, string $subject, string $message ): array {
		$validation = $this->validate( $settings );
		if ( empty( $validation['ok'] ) ) {
			return array(
				'ok'      => false,
				'message' => (string) ( $validation['message'] ?? 'Invalid teams settings.' ),
			);
		}

		$payload = array(
			'text' => $subject . "\n\n" . $message,
		);

		$response = wp_remote_post(
			(string) $settings['channels']['teams']['webhook'],
			array(
				'timeout' => 8,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		return array(
			'ok'      => $code >= 200 && $code < 300,
			'message' => 'Teams response code: ' . (string) $code,
		);
	}
}
