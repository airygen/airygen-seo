<?php
/**
 * Discord channel for Notify.
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
 * Sends notifications to Discord webhook.
 */
final class DiscordChannel implements ChannelInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'discord';
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate( array $settings ): array {
		$webhook = isset( $settings['channels']['discord']['webhook'] ) ? trim( (string) $settings['channels']['discord']['webhook'] ) : '';
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
				'message' => (string) ( $validation['message'] ?? 'Invalid discord settings.' ),
			);
		}

		$body     = array(
			'content' => $subject,
			'embeds'  => array(
				array(
					'description' => $this->build_embed_description( $message ),
					'color'       => $this->resolve_embed_color( $message ),
					'timestamp'   => gmdate( 'c' ),
				),
			),
		);
		$username = isset( $settings['channels']['discord']['username'] ) ? trim( (string) $settings['channels']['discord']['username'] ) : '';
		$avatar   = isset( $settings['channels']['discord']['avatar'] ) ? trim( (string) $settings['channels']['discord']['avatar'] ) : '';
		if ( '' !== $username ) {
			$body['username'] = $username;
		}
		if ( '' !== $avatar ) {
			$body['avatar_url'] = $avatar;
		}

		$response = wp_remote_post(
			(string) $settings['channels']['discord']['webhook'],
			array(
				'timeout' => 8,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
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
			'message' => 'Discord response code: ' . (string) $code,
		);
	}

	/**
	 * Build embed description from message and enforce Discord size limit.
	 *
	 * @param string $message Source message.
	 * @return string
	 */
	private function build_embed_description( string $message ): string {
		$normalized = trim( str_replace( array( "\r\n", "\r" ), "\n", $message ) );
		if ( '' === $normalized ) {
			return 'No details.';
		}

		$sections = preg_split( "/\n{2,}/", $normalized );
		if ( false === $sections ) {
			$sections = array( $normalized );
		}

		$rebuilt_sections = array();
		foreach ( $sections as $section ) {
			$section_text = trim( (string) $section );
			if ( '' === $section_text ) {
				continue;
			}
			$lines = array_values(
				array_filter(
					array_map( 'trim', explode( "\n", $section_text ) ),
					static fn( $line ) => '' !== (string) $line
				)
			);
			if ( empty( $lines ) ) {
				continue;
			}

			$rebuilt_sections[] = implode( "\n", $this->limit_section_records( $lines, 5 ) );
		}

		if ( ! empty( $rebuilt_sections ) ) {
			$normalized = implode( "\n\n", $rebuilt_sections );
		}

		$limit = 3900;
		if ( strlen( $normalized ) <= $limit ) {
			return $normalized;
		}

		return substr( $normalized, 0, $limit - 1 ) . '…';
	}

	/**
	 * Resolve embed color based on digest signal.
	 *
	 * @param string $message Source message.
	 * @return int
	 */
	private function resolve_embed_color( string $message ): int {
		if ( preg_match( '/Total records:\s*[1-9][0-9]*/i', $message ) ) {
			return 0xF59E0B;
		}

		return 0x22C55E;
	}

	/**
	 * Keep at most N record lines per section and append omission summary.
	 *
	 * @param array<int,string> $lines Section lines.
	 * @param int               $max   Max record lines.
	 * @return array<int,string>
	 */
	private function limit_section_records( array $lines, int $max ): array {
		$limited      = array();
		$record_count = 0;
		$omitted      = 0;

		foreach ( $lines as $line ) {
			$text = (string) $line;
			if ( str_starts_with( $text, '- ' ) ) {
				if ( $record_count < $max ) {
					$limited[] = $text;
				} else {
					++$omitted;
				}
				++$record_count;
				continue;
			}
			$limited[] = $text;
		}

		if ( $omitted > 0 ) {
			$limited[] = sprintf(
				/* translators: %d: omitted record count. */
				__( '... and %d more records omitted.', 'airygen-seo' ),
				$omitted
			);
		}

		return $limited;
	}
}
