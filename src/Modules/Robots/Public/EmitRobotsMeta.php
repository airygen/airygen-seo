<?php
/**
 * Emits robots meta directives.
 *
 * @package Airygen\Modules\Robots\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Robots\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Robots\Domain\Service\BuildRobots;
use Airygen\Modules\Robots\Public\ContextResolver;

/**
 * Outputs the robots meta tag.
 */
final class EmitRobotsMeta {

	/**
	 * Emit robots meta tag when directives are present.
	 *
	 * @return void
	 */
	public static function emit(): void {
		$directives = BuildRobots::for_entry( ContextResolver::build_for_entry() );

		if ( $directives->should_suppress_default() ) {
			return;
		}

		$meta = $directives->get_meta_directive();
		if ( null === $meta ) {
			return;
		}

		printf(
			"<meta name=\"robots\" content=\"%s\" />\n",
			esc_attr( $meta )
		);
	}
}
