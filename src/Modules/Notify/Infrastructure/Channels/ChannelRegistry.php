<?php
/**
 * Channel registry for Notify module.
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
 * Provides channel instances by key.
 */
final class ChannelRegistry {

	/**
	 * Build all channel instances.
	 *
	 * @return array<string,ChannelInterface>
	 */
	public static function all(): array {
		$channels = array(
			new EmailChannel(),
			new TelegramChannel(),
			new DiscordChannel(),
			new TeamsChannel(),
		);

		$map = array();
		foreach ( $channels as $channel ) {
			$map[ $channel->key() ] = $channel;
		}

		return $map;
	}

	/**
	 * Find channel by key.
	 *
	 * @param string $key Channel key.
	 * @return ChannelInterface|null
	 */
	public static function find( string $key ): ?ChannelInterface {
		$all = self::all();
		if ( isset( $all[ $key ] ) ) {
			return $all[ $key ];
		}

		return null;
	}
}
