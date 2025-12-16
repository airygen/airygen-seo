<?php
/**
 * Emits Open Graph tags.
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
 * Outputs Open Graph metadata to the document head.
 */
final class EmitOpenGraph {

	/**
	 * Emit Open Graph tags for singular content.
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

		$cards     = BuildCards::for_post( CardContextResolver::build_input( $post_id ) );
		$open_card = $cards->get_open_graph();

		if ( null === $open_card ) {
			return;
		}

		foreach ( $open_card->to_tags() as $property => $content ) {
			if ( '' === $content ) {
				continue;
			}

			printf(
				"<meta property=\"%s\" content=\"%s\" />\n",
				esc_attr( $property ),
				esc_attr( $content )
			);
		}
	}
}
