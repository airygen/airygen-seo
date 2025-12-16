<?php
/**
 * Dispatches notify digest messages through enabled channels.
 *
 * @package Airygen\Modules\Notify\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Modules\Notify\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\Notify\Infrastructure\Channels\ChannelRegistry;
use Airygen\Support\Database\WpDbAdapter;

/**
 * Shared notify digest dispatcher used by admin and runtime jobs.
 */
final class DigestDispatcher {

	/**
	 * Check whether any channel is enabled in settings.
	 *
	 * @param array<string,mixed> $settings Notify settings.
	 * @return bool
	 */
	public static function has_enabled_channel( array $settings ): bool {
		foreach ( array_keys( ChannelRegistry::all() ) as $key ) {
			$enabled = isset( $settings['channels'][ $key ]['enabled'] ) ? (bool) $settings['channels'][ $key ]['enabled'] : false;
			if ( $enabled ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Dispatch digest and append logs when channels are enabled.
	 *
	 * @param array<string,mixed> $settings Notify settings.
	 * @param string              $subject Digest subject.
	 * @param string              $message Digest message.
	 * @return array{ok:bool,results:array<int,array{channel:string,ok:bool,message:string}>}
	 */
	public static function dispatch( array $settings, string $subject, string $message ): array {
		$results         = array();
		$locale_switched = false;
		if ( function_exists( 'switch_to_locale' ) && function_exists( 'restore_previous_locale' ) ) {
			$locale          = (string) get_locale();
			$locale_switched = switch_to_locale( $locale );
		}

		try {
			$payload = self::build_digest_payload( $settings, $message );
			$subject = self::decorate_subject_with_status( $subject, $payload );

			foreach ( ChannelRegistry::all() as $key => $channel ) {
				$enabled = isset( $settings['channels'][ $key ]['enabled'] ) ? (bool) $settings['channels'][ $key ]['enabled'] : false;
				if ( ! $enabled ) {
					continue;
				}

				$result    = $channel->send( $settings, $subject, $payload );
				$results[] = array(
					'channel' => $key,
					'ok'      => isset( $result['ok'] ) ? (bool) $result['ok'] : false,
					'message' => isset( $result['message'] ) ? (string) $result['message'] : '',
				);
			}
		} finally {
			if ( $locale_switched ) {
				restore_previous_locale();
			}
		}

		if ( count( $results ) > 0 ) {
			self::append_log( $results );
			$retention_days = isset( $settings['logs']['retention_days'] ) ? (int) $settings['logs']['retention_days'] : 30;
			$retention_days = max( 1, min( 3650, $retention_days ) );
			$repository     = new LogRepository();
			$repository->purge_older_than_days( $retention_days );
		}

		return array(
			'ok'      => count( $results ) > 0,
			'results' => $results,
		);
	}

	/**
	 * Prefix digest subject with status emoji.
	 *
	 * @param string $subject Subject text.
	 * @param string $payload Digest payload.
	 * @return string
	 */
	private static function decorate_subject_with_status( string $subject, string $payload ): string {
		$trimmed_subject = trim( $subject );
		if ( '' === $trimmed_subject ) {
			$trimmed_subject = __( 'Daily digest', 'airygen-seo' );
		}

		// Prevent duplicated emoji when custom subject already contains one.
		if ( preg_match( '/^(✅|⚠️)\s/u', $trimmed_subject ) ) {
			return $trimmed_subject;
		}

		$has_alert = preg_match( '/Total records:\s*[1-9][0-9]*/i', $payload ) > 0;
		$prefix    = $has_alert ? '⚠️ ' : '✅ ';

		return $prefix . $trimmed_subject;
	}

	/**
	 * Append one dispatch log entry.
	 *
	 * @param array<int,array{channel:string,ok:bool,message:string}> $results Dispatch results.
	 * @return void
	 */
	private static function append_log( array $results ): void {
		$repository = new LogRepository();
		$repository->append( $results );
	}

	/**
	 * Build digest payload from enabled custom blocks.
	 *
	 * @param array<string,mixed> $settings Notify settings.
	 * @param string              $fallback Fallback message.
	 * @return string
	 */
	private static function build_digest_payload( array $settings, string $fallback ): string {
		$visible_blocks = array();
		if ( isset( $settings['custom']['visible_blocks'] ) && is_array( $settings['custom']['visible_blocks'] ) ) {
			foreach ( $settings['custom']['visible_blocks'] as $block_id ) {
				$key = sanitize_key( (string) $block_id );
				if ( in_array( $key, array( 'not_found_logs', 'broken_link_logs' ), true ) ) {
					$visible_blocks[] = $key;
				}
			}
		}

		$intro  = isset( $settings['message']['intro'] ) ? trim( (string) $settings['message']['intro'] ) : '';
		$footer = isset( $settings['message']['footer'] ) ? trim( (string) $settings['message']['footer'] ) : '';

		if ( empty( $visible_blocks ) ) {
			$parts = array();
			if ( '' !== $intro ) {
				$parts[] = $intro;
			}
			$parts[] = $fallback;
			if ( '' !== $footer ) {
				$parts[] = $footer;
			}
			return implode( "\n\n", $parts );
		}

		$sections = array();
		foreach ( $visible_blocks as $block_id ) {
			if ( 'not_found_logs' === $block_id ) {
				$sections[] = self::build_not_found_section();
				continue;
			}
			if ( 'broken_link_logs' === $block_id ) {
				$sections[] = self::build_broken_link_section();
			}
		}

		$sections = array_values( array_filter( $sections ) );
		$parts    = array();
		if ( '' !== $intro ) {
			$parts[] = $intro;
		}
		if ( empty( $sections ) ) {
			$parts[] = $fallback;
		} else {
			$parts[] = implode( "\n\n", $sections );
		}
		if ( '' !== $footer ) {
			$parts[] = $footer;
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Build 404 digest section from recent records.
	 *
	 * @return string
	 */
	private static function build_not_found_section(): string {
		$db       = new WpDbAdapter();
		$table    = $db->table( Constants::TABLE_404_LOGS );
		$timezone = wp_timezone();
		$cutoff   = ( new \DateTimeImmutable( 'now', $timezone ) )
			->sub( new \DateInterval( 'PT24H' ) )
			->format( 'Y-m-d H:i:s' );
		$sql      = sprintf(
			'SELECT url_path FROM %s WHERE last_seen_at >= %%s ORDER BY last_seen_at DESC LIMIT 100',
			$table
		);
		$rows     = $db->get_results( $sql, array( $cutoff ) );

		$lines   = array(
			sprintf(
				/* translators: %s is an HTTP status code and should remain numeric, e.g. 404. */
				__( '%s records', 'airygen-seo' ),
				'404'
			),
		);
		$entries = is_array( $rows ) ? $rows : array();
		if ( empty( $entries ) ) {
			$lines[] = __( 'No new records.', 'airygen-seo' );
			$lines[] = sprintf(
				/* translators: %d: number of records in the last 24 hours. */
				__( 'Total records: %d (last 24h)', 'airygen-seo' ),
				0
			);
			return implode( "\n", $lines );
		}

		$count = 0;
		foreach ( $entries as $row ) {
			$url = isset( $row->url_path ) ? trim( (string) $row->url_path ) : '';
			if ( '' === $url ) {
				continue;
			}
			$lines[] = sprintf( '- %s', $url );
			++$count;
		}
		$lines[] = sprintf(
			/* translators: %d: number of records in the last 24 hours. */
			__( 'Total records: %d (last 24h)', 'airygen-seo' ),
			$count
		);

		return implode( "\n", $lines );
	}

	/**
	 * Build broken-link digest section from recent records.
	 *
	 * @return string
	 */
	private static function build_broken_link_section(): string {
		$db     = new WpDbAdapter();
		$table  = $db->table( Constants::TABLE_LINK_CHECKER_LOG );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$sql    = sprintf(
			'SELECT url FROM %s WHERE checked_at >= %%s AND (status_label = %%s OR status_code = 0 OR status_code >= 400) ORDER BY checked_at DESC LIMIT 100',
			$table
		);
		$rows   = $db->get_results( $sql, array( $cutoff, 'error' ) );

		$lines   = array( __( 'Broken link records', 'airygen-seo' ) );
		$entries = is_array( $rows ) ? $rows : array();
		if ( empty( $entries ) ) {
			$lines[] = __( 'No new records.', 'airygen-seo' );
			$lines[] = sprintf(
				/* translators: %d: number of records in the last 24 hours. */
				__( 'Total records: %d (last 24h)', 'airygen-seo' ),
				0
			);
			return implode( "\n", $lines );
		}

		$count = 0;
		foreach ( $entries as $row ) {
			$url = isset( $row->url ) ? trim( (string) $row->url ) : '';
			if ( '' === $url ) {
				continue;
			}
			$lines[] = sprintf( '- %s', $url );
			++$count;
		}
		$lines[] = sprintf(
			/* translators: %d: number of records in the last 24 hours. */
			__( 'Total records: %d (last 24h)', 'airygen-seo' ),
			$count
		);

		return implode( "\n", $lines );
	}
}
