<?php
/**
 * Emits hreflang alternate link tags.
 *
 * @package Airygen\Modules\Hreflang\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Hreflang\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Hreflang\Domain\Service\ResolveAlternates;
use Airygen\Modules\Hreflang\Public\ContextResolver;

/**
 * Outputs alternate language links.
 */
final class EmitAlternates {

	/**
	 * Emit hreflang link tags.
	 *
	 * @return void
	 */
	public static function emit(): void {
		$context    = ContextResolver::build();
		$alternates = ResolveAlternates::for_entry( $context );

		if ( empty( $alternates ) ) {
			return;
		}

		foreach ( $alternates as $alternate ) {
			$code = $alternate['hreflang'] ?? '';
			$url  = $alternate['url'] ?? '';

			if ( '' === $code || '' === $url ) {
				continue;
			}

			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $code ),
				esc_url( $url )
			);
		}
	}
}
