<?php
/**
 * Channel interface for Notify module.
 *
 * @package Airygen\Modules\Notify\Domain\Channel
 */

declare(strict_types=1);

namespace Airygen\Modules\Notify\Domain\Channel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for all notification channels.
 */
interface ChannelInterface {

	/**
	 * Channel key.
	 *
	 * @return string
	 */
	public function key(): string;

	/**
	 * Validate channel configuration.
	 *
	 * @param array<string,mixed> $settings Full notify settings.
	 * @return array<string,mixed>
	 */
	public function validate( array $settings ): array;

	/**
	 * Send message.
	 *
	 * @param array<string,mixed> $settings Full notify settings.
	 * @param string              $subject Notification subject.
	 * @param string              $message Notification body.
	 * @return array<string,mixed>
	 */
	public function send( array $settings, string $subject, string $message ): array;
}
