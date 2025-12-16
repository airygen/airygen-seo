<?php
/**
 * Appends lines to robots.txt output.
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
 * Filters the robots.txt output.
 */
final class RobotsTxtFilter {

	/**
	 * Append custom robots rules.
	 *
	 * @param string $output    Existing robots.txt output.
	 * @param bool   $is_public Public flag for WP robots output.
	 *
	 * @return string
	 */
	public static function filter( string $output, bool $is_public ): string {
		unset( $is_public ); // Unused but required by hook signature.

		$context = ContextResolver::build_for_robots_txt();
		$lines   = BuildRobots::for_robots_txt( $context );

		if ( empty( $lines ) ) {
			return $output;
		}

		$output = rtrim( $output );
		if ( '' !== $output ) {
			$output .= "\n";
		}

		$output .= implode( "\n", $lines ) . "\n";

		return $output;
	}
}
