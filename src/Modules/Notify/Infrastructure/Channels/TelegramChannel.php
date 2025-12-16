<?php
/**
 * Telegram channel for Notify.
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
 * Sends notifications to Telegram Bot API.
 */
final class TelegramChannel implements ChannelInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'telegram';
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate( array $settings ): array {
		$token = isset( $settings['channels']['telegram']['botToken'] ) ? trim( (string) $settings['channels']['telegram']['botToken'] ) : '';
		$chat  = isset( $settings['channels']['telegram']['chatId'] ) ? trim( (string) $settings['channels']['telegram']['chatId'] ) : '';

		if ( '' === $token || '' === $chat ) {
			return array(
				'ok'      => false,
				'message' => 'Bot token and chat id are required.',
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
				'message' => (string) ( $validation['message'] ?? 'Invalid telegram settings.' ),
			);
		}

		$token   = (string) $settings['channels']['telegram']['botToken'];
		$chat_id = (string) $settings['channels']['telegram']['chatId'];
		$topic   = isset( $settings['channels']['telegram']['topicId'] ) ? trim( (string) $settings['channels']['telegram']['topicId'] ) : '';

		$payload = array(
			'chat_id' => $chat_id,
			'text'    => $subject . "\n\n" . $message,
		);
		if ( '' !== $topic ) {
			$payload['message_thread_id'] = $topic;
		}

		$response = wp_remote_post(
			sprintf( 'https://api.telegram.org/bot%s/sendMessage', rawurlencode( $token ) ),
			array(
				'timeout' => 8,
				'body'    => $payload,
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
			'message' => 'Telegram response code: ' . (string) $code,
		);
	}
}
