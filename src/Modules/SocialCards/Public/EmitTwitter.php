<?php
/**
 * Emits Twitter card tags.
 *
 * @package Airygen\Modules\SocialCards\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\SocialCards\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SocialCards\Domain\Service\BuildCards;
use Airygen\Modules\SocialCards\Public\CardContextResolver;

/**
 * Outputs Twitter card metadata to the document head.
 */
final class EmitTwitter {

	/**
	 * Emit Twitter card tags.
	 *
	 * @return void
	 */
	public static function emit(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$cards   = BuildCards::for_post( CardContextResolver::build_input( $post_id ) );
		$twitter = $cards->get_twitter();

		if ( null === $twitter ) {
			return;
		}

		foreach ( $twitter->to_tags() as $name => $content ) {
			if ( '' === $content ) {
				continue;
			}

			printf(
				"<meta name=\"%s\" content=\"%s\" />\n",
				esc_attr( $name ),
				esc_attr( $content )
			);
		}
	}
}
