<?php
/**
 * Builds IndexNow submission payloads.
 *
 * @package Airygen\Modules\InstantIndexing\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helper for constructing IndexNow payloads.
 */
final class PayloadBuilder {

	/**
	 * Build the payload for a POST /indexnow submission.
	 *
	 * @param string            $host         Hostname portion of the site (e.g. example.com).
	 * @param array<int,string> $urls         List of canonical URLs to include.
	 * @param string            $key          IndexNow key.
	 * @param string|null       $key_location Optional fully-qualified URL pointing to the key file.
	 * @return array<string, mixed>
	 */
	public static function build( string $host, array $urls, string $key, ?string $key_location = null ): array {
		$filtered = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $url ): string {
							return trim( (string) $url );
						},
						$urls
					),
					static function ( string $url ): bool {
						return '' !== $url;
					}
				)
			)
		);

		$payload = array(
			'host'    => strtolower( $host ),
			'key'     => $key,
			'urlList' => $filtered,
		);

		if ( $key_location ) {
			$payload['keyLocation'] = $key_location;
		}

		return $payload;
	}
}
