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
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer public API uses non-snake-case property names.
/**
 * Sends notifications by SMTP mailer.
 */
final class EmailChannel implements ChannelInterface {

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

		$config = $this->build_smtp_config( $settings );

		try {
			$mailer = $this->create_mailer( $config );
			$mailer->setFrom( $config['from_email'], $config['from_name'], false );

			foreach ( $recipients as $recipient ) {
				if ( ! is_string( $recipient ) || '' === trim( $recipient ) ) {
					continue;
				}
				$mailer->addAddress( trim( $recipient ) );
			}

			$mailer->Subject = $subject;
			$mailer->Body    = $this->build_html_template( $subject, $message, $config );
			$mailer->AltBody = $message;

			$sent = $mailer->send();
			if ( ! $sent ) {
				return array(
					'ok'      => false,
					'message' => 'Failed to send email.',
				);
			}

			return array(
				'ok'      => true,
				'message' => 'Email sent.',
			);
		} catch ( MailerException $exception ) {
			return array(
				'ok'      => false,
				'message' => 'Failed to send email. ' . trim( $exception->getMessage() ),
			);
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
	 * Create configured SMTP mailer instance.
	 *
	 * @param array<string,mixed> $config SMTP config.
	 * @return PHPMailer
	 * @throws MailerException When PHPMailer cannot be initialized.
	 */
	private function create_mailer( array $config ): PHPMailer {
		$this->ensure_phpmailer_loaded();

		$mailer = new PHPMailer( true );
		$mailer->isSMTP();
		$mailer->Host     = (string) $config['host'];
		$mailer->Port     = (int) $config['port'];
		$mailer->SMTPAuth = (bool) $config['auth'];
		$mailer->Username = (string) $config['username'];
		$mailer->Password = (string) $config['password'];
		$mailer->Timeout  = (int) $config['timeout'];
		$mailer->CharSet  = 'UTF-8';
		$mailer->isHTML( true );

		$secure = (string) $config['secure'];
		if ( 'tls' === $secure || 'ssl' === $secure ) {
			$mailer->SMTPSecure = $secure;
		}

		return $mailer;
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

	/**
	 * Ensure PHPMailer classes are available.
	 *
	 * @return void
	 */
	private function ensure_phpmailer_loaded(): void {
		if ( class_exists( PHPMailer::class ) ) {
			return;
		}

		// PHPMailer is bundled with WordPress core under wp-includes/PHPMailer/.
		// `ABSPATH . WPINC . '/PHPMailer/...'` is the path documented by Core
		// for plugins that need to load PHPMailer outside the wp_mail() flow.
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
	}
}
// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
