<?php
/**
 * Email channel for Notify.
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
 * Sends notifications by SMTP mailer using wp_mail() + the phpmailer_init action.
 *
 * PHPMailer is loaded by WordPress core when wp_mail() runs, so the plugin does
 * not have to load PHPMailer itself — that avoids any direct ABSPATH/WPINC
 * file paths and uses the API documented in the WordPress Plugin Handbook.
 */
final class EmailChannel implements ChannelInterface {

	/**
	 * SMTP config for the in-flight wp_mail() call.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $active_config = null;

	/**
	 * Captured failure message, if wp_mail_failed fires while sending.
	 *
	 * @var string
	 */
	private string $last_error = '';

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'email';
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate( array $settings ): array {
		$recipients = isset( $settings['channels']['email']['recipients'] ) && is_array( $settings['channels']['email']['recipients'] )
		? $settings['channels']['email']['recipients']
		: array();
		if ( empty( $recipients ) ) {
			return array(
				'ok'      => false,
				'message' => 'No recipients configured.',
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
				'message' => (string) ( $validation['message'] ?? 'Invalid email settings.' ),
			);
		}

		$recipients = is_array( $settings['channels']['email']['recipients'] )
		? $settings['channels']['email']['recipients']
		: array();

		$to = array();
		foreach ( $recipients as $recipient ) {
			if ( is_string( $recipient ) && '' !== trim( $recipient ) ) {
				$to[] = trim( $recipient );
			}
		}
		if ( empty( $to ) ) {
			return array(
				'ok'      => false,
				'message' => 'No valid recipients configured.',
			);
		}

		$this->active_config = $this->build_smtp_config( $settings );
		$this->last_error    = '';

		$body    = $this->build_html_template( $subject, $message, $this->active_config );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf(
				'From: %s <%s>',
				$this->active_config['from_name'],
				$this->active_config['from_email']
			),
		);

		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
		add_action( 'wp_mail_failed', array( $this, 'capture_failure' ) );

		$sent = wp_mail( $to, $subject, $body, $headers );

		remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
		remove_action( 'wp_mail_failed', array( $this, 'capture_failure' ) );

		$this->active_config = null;

		if ( ! $sent ) {
			$failure_message = '' !== $this->last_error ? $this->last_error : 'Failed to send email.';
			return array(
				'ok'      => false,
				'message' => $failure_message,
			);
		}

		return array(
			'ok'      => true,
			'message' => 'Email sent.',
		);
	}

	/**
	 * `phpmailer_init` callback — applies SMTP settings to the core PHPMailer
	 * instance that wp_mail() created. Because we never instantiate PHPMailer
	 * ourselves, no `wp-includes/PHPMailer/*.php` require_once is needed.
	 *
	 * @param mixed $phpmailer The PHPMailer instance passed by reference.
	 * @return void
	 */
	public function configure_phpmailer( $phpmailer ): void {
		if ( null === $this->active_config || ! is_object( $phpmailer ) ) {
			return;
		}

		$config = $this->active_config;

		// PHPMailer's public API uses non-snake-case property names.
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->isSMTP();
		$phpmailer->Host     = (string) $config['host'];
		$phpmailer->Port     = (int) $config['port'];
		$phpmailer->SMTPAuth = (bool) $config['auth'];
		$phpmailer->Username = (string) $config['username'];
		$phpmailer->Password = (string) $config['password'];
		$phpmailer->Timeout  = (int) $config['timeout'];
		$phpmailer->CharSet  = 'UTF-8';
		$phpmailer->isHTML( true );

		$secure = (string) $config['secure'];
		if ( 'tls' === $secure || 'ssl' === $secure ) {
			$phpmailer->SMTPSecure = $secure;
		} else {
			$phpmailer->SMTPSecure  = '';
			$phpmailer->SMTPAutoTLS = false;
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * `wp_mail_failed` callback — capture the SMTP error so we can return it.
	 *
	 * @param mixed $error WP_Error instance from wp_mail().
	 * @return void
	 */
	public function capture_failure( $error ): void {
		if ( $error instanceof \WP_Error ) {
			$message          = $error->get_error_message();
			$this->last_error = is_string( $message ) && '' !== trim( $message )
			? 'Failed to send email. ' . trim( $message )
			: 'Failed to send email.';
		}
	}

	/**
	 * Build SMTP configuration from saved email settings.
	 *
	 * @param array<string,mixed> $settings Notify settings.
	 * @return array<string,mixed>
	 */
	private function build_smtp_config( array $settings ): array {
		$default_from_email = (string) get_option( 'admin_email', '' );
		if ( ! is_email( $default_from_email ) || false !== strpos( strtolower( $default_from_email ), '@localhost' ) ) {
			$default_from_email = 'wordpress@example.test';
		}

		$default_from_name = (string) get_bloginfo( 'name' );
		if ( '' === trim( $default_from_name ) ) {
			$default_from_name = 'WordPress';
		}

		$smtp = isset( $settings['channels']['email']['smtp'] ) && is_array( $settings['channels']['email']['smtp'] )
		? $settings['channels']['email']['smtp']
		: array();

		$host       = isset( $smtp['host'] ) && is_string( $smtp['host'] ) ? trim( $smtp['host'] ) : 'smtp.gmail.com';
		$port       = isset( $smtp['port'] ) ? (int) $smtp['port'] : 587;
		$auth       = isset( $smtp['auth'] ) ? (bool) $smtp['auth'] : true;
		$secure     = isset( $smtp['secure'] ) && is_string( $smtp['secure'] ) ? trim( strtolower( $smtp['secure'] ) ) : 'tls';
		$username   = isset( $smtp['username'] ) && is_string( $smtp['username'] ) ? trim( $smtp['username'] ) : '';
		$password   = isset( $smtp['password'] ) && is_string( $smtp['password'] ) ? $smtp['password'] : '';
		$timeout    = isset( $smtp['timeout'] ) ? (int) $smtp['timeout'] : 10;
		$from_email = isset( $smtp['fromEmail'] ) && is_string( $smtp['fromEmail'] ) ? trim( $smtp['fromEmail'] ) : $default_from_email;
		$from_name  = isset( $smtp['fromName'] ) && is_string( $smtp['fromName'] ) ? trim( $smtp['fromName'] ) : $default_from_name;

		$normalized_secure = '';
		if ( 'tls' === $secure || 'ssl' === $secure ) {
			$normalized_secure = $secure;
		}
		$normalized_auth = $auth;
		if ( true === $normalized_auth && ( '' === $username || '' === $password ) ) {
			$normalized_auth = false;
		}

		return array(
			'host'       => '' !== $host ? $host : 'smtp.gmail.com',
			'port'       => $port > 0 ? $port : 587,
			'auth'       => (bool) $normalized_auth,
			'secure'     => $normalized_secure,
			'username'   => $username,
			'password'   => $password,
			'timeout'    => $timeout > 0 ? $timeout : 10,
			'from_email' => is_email( $from_email )
				? $from_email
				: ( '' !== $username && is_email( $username ) ? $username : 'wordpress@example.test' ),
			'from_name'  => '' !== $from_name
				? $from_name
				: 'WordPress',
		);
	}

	/**
	 * Build a styled HTML template for digest emails.
	 *
	 * @param string              $subject Email subject.
	 * @param string              $message Plain text digest content.
	 * @param array<string,mixed> $config  SMTP config.
	 * @return string
	 */
	private function build_html_template( string $subject, string $message, array $config ): string {
		$site_name  = (string) get_bloginfo( 'name' );
		$site_name  = '' !== trim( $site_name ) ? $site_name : 'WordPress';
		$brand_name = isset( $config['from_name'] ) && is_string( $config['from_name'] ) && '' !== trim( $config['from_name'] )
		? trim( $config['from_name'] )
		: $site_name;
		$content    = $this->render_html_content( $message );

		$rendered = $this->render_email_template(
			array(
				'subject' => $subject,
				'brand'   => $brand_name,
				'site'    => $site_name,
				'content' => $content,
			)
		);
		if ( '' !== $rendered ) {
			return $rendered;
		}

		return '<html><body><pre>' . esc_html( $message ) . '</pre></body></html>';
	}

	/**
	 * Render plain digest text into email-friendly HTML blocks.
	 *
	 * @param string $message Plain digest content.
	 * @return string
	 */
	private function render_html_content( string $message ): string {
		$normalized = str_replace( array( "\r\n", "\r" ), "\n", trim( $message ) );
		if ( '' === $normalized ) {
			return '<p style="margin:0 0 12px;">No content.</p>';
		}

		$sections = preg_split( "/\n{2,}/", $normalized );
		if ( false === $sections ) {
			$sections = array( $normalized );
		}
		$html = array();

		foreach ( $sections as $section ) {
			$section = trim( (string) $section );
			if ( '' === $section ) {
				continue;
			}

			$lines = array_values(
				array_filter(
					array_map( 'trim', explode( "\n", $section ) ),
					static fn( $line ) => '' !== (string) $line
				)
			);
			if ( empty( $lines ) ) {
				continue;
			}

			$title = '';
			if ( isset( $lines[0] ) && '-' !== substr( (string) $lines[0], 0, 1 ) ) {
				$shifted_title = array_shift( $lines );
				$title         = is_string( $shifted_title ) ? $shifted_title : '';
			}

			if ( '' !== $title ) {
				$html[] = '<p style="margin:30px 0 10px;font-size:16px;line-height:1.5;color:#0f172a;font-weight:700;">' . esc_html( $title ) . '</p>';
			}

			$bullet_lines = array_values(
				array_filter(
					$lines,
					static fn( $line ) => is_string( $line ) && str_starts_with( $line, '- ' )
				)
			);
			if ( ! empty( $bullet_lines ) ) {
				$items = array();
				foreach ( $bullet_lines as $line ) {
					$items[] = '<li style="margin:0 0 6px;font-size:13px;font-style:normal;">' . esc_html( trim( substr( $line, 2 ) ) ) . '</li>';
				}
				$html[] = '<ul style="margin:8px 0 12px 20px;padding:0;">' . implode( '', $items ) . '</ul>';
			}

			$plain_lines = array_values(
				array_filter(
					$lines,
					static fn( $line ) => is_string( $line ) && ! str_starts_with( $line, '- ' )
				)
			);
			if ( empty( $plain_lines ) ) {
				continue;
			}

			$paragraph = esc_html( implode( "\n", $plain_lines ) );
			$paragraph = nl2br( $paragraph );
			$html[]    = '<p style="margin:0 0 12px;font-size:13px;font-style:normal;">' . $paragraph . '</p>';
			$html[]    = '<hr style="border:none;border-top:1px solid #e2e8f0;margin:36px 0;">';
		}

		return implode( '', $html );
	}

	/**
	 * Render email template view file.
	 *
	 * @param array<string,string> $data Template payload.
	 * @return string
	 */
	private function render_email_template( array $data ): string {
		if ( ! defined( 'AIRYGEN_PLUGIN_DIR' ) ) {
			return '';
		}

		$template = trailingslashit( AIRYGEN_PLUGIN_DIR ) . 'resources/views/admin/notify/email-template.php';
		if ( ! file_exists( $template ) ) {
			return '';
		}

		ob_start();
		require $template;
		$output = ob_get_clean();

		return is_string( $output ) ? $output : '';
	}
}
