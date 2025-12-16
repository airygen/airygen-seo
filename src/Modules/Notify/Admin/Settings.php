<?php
/**
 * Stores Notify module settings.
 *
 * @package Airygen\Modules\Notify\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Notify\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

use function in_array;

/**
 * Option repository for Notify settings.
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_NOTIFY_SETTINGS;

	/**
	 * Ensure option exists.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', 'no' );
		}
	}

	/**
	 * Get settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION_NAME, array() ) );
	}

	/**
	 * Update settings.
	 *
	 * @param array<string,mixed> $value Raw payload.
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION_NAME, self::sanitize( $value ), 'no' );
	}

	/**
	 * Defaults.
	 *
	 * @return array<string,mixed>
	 */
	private static function defaults(): array {
		$timezone = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC';
		if ( '' === $timezone || ! self::is_valid_timezone( $timezone ) ) {
			$timezone = 'UTC';
		}

		return array(
			'enabled'  => false,
			'custom'   => array(
				'visible_blocks' => array( 'not_found_logs', 'broken_link_logs' ),
				'hidden_blocks'  => array(),
			),
			'message'  => array(
				'subject' => 'Airygen SEO Daily Digest',
				'intro'   => 'This is a record summary generated from the modules you have subscribed to.',
				'footer'  => 'This is a system message. Please do not reply.',
			),
			'logs'     => array(
				'retention_days' => 30,
			),
			'schedule' => array(
				'timezone' => $timezone,
				'time'     => '09:00',
			),
			'channels' => array(
				'email'    => array(
					'enabled'    => false,
					'recipients' => array(),
					'smtp'       => array(
						'host'      => 'smtp.gmail.com',
						'port'      => 587,
						'auth'      => true,
						'secure'    => 'tls',
						'username'  => '',
						'password'  => '',
						'timeout'   => 10,
						'fromEmail' => '',
						'fromName'  => 'WordPress',
					),
				),
				'telegram' => array(
					'enabled'  => false,
					'botToken' => '',
					'chatId'   => '',
					'topicId'  => '',
				),
				'discord'  => array(
					'enabled'  => false,
					'webhook'  => '',
					'username' => '',
					'avatar'   => '',
				),
				'teams'    => array(
					'enabled' => false,
					'webhook' => '',
				),
			),
		);
	}

	/**
	 * Sanitize payload.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string,mixed>
	 */
	private static function sanitize( $value ): array {
		$defaults = self::defaults();
		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$timezone = isset( $value['schedule']['timezone'] ) ? sanitize_text_field( (string) $value['schedule']['timezone'] ) : (string) $defaults['schedule']['timezone'];
		if ( '' === $timezone || ! self::is_valid_timezone( $timezone ) ) {
			$timezone = 'UTC';
		}
		$time = isset( $value['schedule']['time'] ) ? (string) $value['schedule']['time'] : (string) $defaults['schedule']['time'];
		if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			$time = '09:00';
		}
		$retention_days = isset( $value['logs']['retention_days'] )
		? (int) $value['logs']['retention_days']
		: (int) $defaults['logs']['retention_days'];
		if ( $retention_days < 1 || $retention_days > 3650 ) {
			$retention_days = (int) $defaults['logs']['retention_days'];
		}

		$email_recipients = array();
		if ( isset( $value['channels']['email']['recipients'] ) && is_array( $value['channels']['email']['recipients'] ) ) {
			foreach ( $value['channels']['email']['recipients'] as $email ) {
				$email = sanitize_email( (string) $email );
				if ( '' === $email ) {
					continue;
				}
				$email_recipients[] = $email;
			}
		}

		$smtp_host = isset( $value['channels']['email']['smtp']['host'] )
		? sanitize_text_field( (string) $value['channels']['email']['smtp']['host'] )
		: (string) $defaults['channels']['email']['smtp']['host'];
		if ( '' === $smtp_host ) {
			$smtp_host = (string) $defaults['channels']['email']['smtp']['host'];
		}

		$smtp_port = isset( $value['channels']['email']['smtp']['port'] )
		? (int) $value['channels']['email']['smtp']['port']
		: (int) $defaults['channels']['email']['smtp']['port'];
		if ( $smtp_port < 1 || $smtp_port > 65535 ) {
			$smtp_port = (int) $defaults['channels']['email']['smtp']['port'];
		}

		$smtp_auth = isset( $value['channels']['email']['smtp']['auth'] )
		? (bool) $value['channels']['email']['smtp']['auth']
		: (bool) $defaults['channels']['email']['smtp']['auth'];

		$smtp_secure = isset( $value['channels']['email']['smtp']['secure'] )
		? sanitize_text_field( (string) $value['channels']['email']['smtp']['secure'] )
		: (string) $defaults['channels']['email']['smtp']['secure'];
		if ( 'tls' !== $smtp_secure && 'ssl' !== $smtp_secure ) {
			$smtp_secure = '';
		}

		$smtp_username = isset( $value['channels']['email']['smtp']['username'] )
		? sanitize_text_field( (string) $value['channels']['email']['smtp']['username'] )
		: (string) $defaults['channels']['email']['smtp']['username'];
		$smtp_password = isset( $value['channels']['email']['smtp']['password'] )
		? (string) $value['channels']['email']['smtp']['password']
		: (string) $defaults['channels']['email']['smtp']['password'];

		$smtp_timeout = isset( $value['channels']['email']['smtp']['timeout'] )
		? (int) $value['channels']['email']['smtp']['timeout']
		: (int) $defaults['channels']['email']['smtp']['timeout'];
		if ( $smtp_timeout < 1 || $smtp_timeout > 120 ) {
			$smtp_timeout = (int) $defaults['channels']['email']['smtp']['timeout'];
		}

		$smtp_from_email = isset( $value['channels']['email']['smtp']['fromEmail'] )
		? sanitize_email( (string) $value['channels']['email']['smtp']['fromEmail'] )
		: (string) $defaults['channels']['email']['smtp']['fromEmail'];
		if ( '' === $smtp_from_email ) {
			$smtp_from_email = (string) $defaults['channels']['email']['smtp']['fromEmail'];
		}

		$smtp_from_name = isset( $value['channels']['email']['smtp']['fromName'] )
		? sanitize_text_field( (string) $value['channels']['email']['smtp']['fromName'] )
		: (string) $defaults['channels']['email']['smtp']['fromName'];
		if ( '' === $smtp_from_name ) {
			$smtp_from_name = (string) $defaults['channels']['email']['smtp']['fromName'];
		}

		$known_custom_blocks = array( 'not_found_logs', 'broken_link_logs' );
		$visible_blocks      = array();
		if ( isset( $value['custom']['visible_blocks'] ) && is_array( $value['custom']['visible_blocks'] ) ) {
			foreach ( $value['custom']['visible_blocks'] as $block_key ) {
				$key = sanitize_key( (string) $block_key );
				if ( in_array( $key, $known_custom_blocks, true ) && ! in_array( $key, $visible_blocks, true ) ) {
					$visible_blocks[] = $key;
				}
			}
		}
		$hidden_blocks = array();
		if ( isset( $value['custom']['hidden_blocks'] ) && is_array( $value['custom']['hidden_blocks'] ) ) {
			foreach ( $value['custom']['hidden_blocks'] as $block_key ) {
				$key = sanitize_key( (string) $block_key );
				if (
					in_array( $key, $known_custom_blocks, true ) &&
					! in_array( $key, $hidden_blocks, true ) &&
					! in_array( $key, $visible_blocks, true )
				) {
					$hidden_blocks[] = $key;
				}
			}
		}
		foreach ( $known_custom_blocks as $block_key ) {
			if ( ! in_array( $block_key, $visible_blocks, true ) && ! in_array( $block_key, $hidden_blocks, true ) ) {
				$visible_blocks[] = $block_key;
			}
		}
		$message_subject = isset( $value['message']['subject'] )
		? sanitize_text_field( (string) $value['message']['subject'] )
		: (string) $defaults['message']['subject'];
		$message_intro   = isset( $value['message']['intro'] )
		? sanitize_textarea_field( (string) $value['message']['intro'] )
		: (string) $defaults['message']['intro'];
		$message_footer  = isset( $value['message']['footer'] )
		? sanitize_textarea_field( (string) $value['message']['footer'] )
		: (string) $defaults['message']['footer'];

		return array(
			'enabled'  => isset( $value['enabled'] ) ? (bool) $value['enabled'] : (bool) $defaults['enabled'],
			'custom'   => array(
				'visible_blocks' => $visible_blocks,
				'hidden_blocks'  => $hidden_blocks,
			),
			'message'  => array(
				'subject' => '' !== $message_subject ? $message_subject : (string) $defaults['message']['subject'],
				'intro'   => $message_intro,
				'footer'  => $message_footer,
			),
			'logs'     => array(
				'retention_days' => $retention_days,
			),
			'schedule' => array(
				'timezone' => $timezone,
				'time'     => $time,
			),
			'channels' => array(
				'email'    => array(
					'enabled'    => isset( $value['channels']['email']['enabled'] ) ? (bool) $value['channels']['email']['enabled'] : false,
					'recipients' => array_values( array_unique( $email_recipients ) ),
					'smtp'       => array(
						'host'      => $smtp_host,
						'port'      => $smtp_port,
						'auth'      => $smtp_auth,
						'secure'    => $smtp_secure,
						'username'  => $smtp_username,
						'password'  => $smtp_password,
						'timeout'   => $smtp_timeout,
						'fromEmail' => $smtp_from_email,
						'fromName'  => $smtp_from_name,
					),
				),
				'telegram' => array(
					'enabled'  => isset( $value['channels']['telegram']['enabled'] ) ? (bool) $value['channels']['telegram']['enabled'] : false,
					'botToken' => isset( $value['channels']['telegram']['botToken'] ) ? sanitize_text_field( (string) $value['channels']['telegram']['botToken'] ) : '',
					'chatId'   => isset( $value['channels']['telegram']['chatId'] ) ? sanitize_text_field( (string) $value['channels']['telegram']['chatId'] ) : '',
					'topicId'  => isset( $value['channels']['telegram']['topicId'] ) ? sanitize_text_field( (string) $value['channels']['telegram']['topicId'] ) : '',
				),
				'discord'  => array(
					'enabled'  => isset( $value['channels']['discord']['enabled'] ) ? (bool) $value['channels']['discord']['enabled'] : false,
					'webhook'  => isset( $value['channels']['discord']['webhook'] ) ? esc_url_raw( (string) $value['channels']['discord']['webhook'] ) : '',
					'username' => isset( $value['channels']['discord']['username'] ) ? sanitize_text_field( (string) $value['channels']['discord']['username'] ) : '',
					'avatar'   => isset( $value['channels']['discord']['avatar'] ) ? esc_url_raw( (string) $value['channels']['discord']['avatar'] ) : '',
				),
				'teams'    => array(
					'enabled' => isset( $value['channels']['teams']['enabled'] ) ? (bool) $value['channels']['teams']['enabled'] : false,
					'webhook' => isset( $value['channels']['teams']['webhook'] ) ? esc_url_raw( (string) $value['channels']['teams']['webhook'] ) : '',
				),
			),
		);
	}

	/**
	 * Validate IANA timezone identifier.
	 *
	 * @param string $timezone Timezone identifier.
	 *
	 * @return bool
	 */
	private static function is_valid_timezone( string $timezone ): bool {
		if ( '' === $timezone ) {
			return false;
		}

		return in_array( $timezone, timezone_identifiers_list(), true );
	}
}
