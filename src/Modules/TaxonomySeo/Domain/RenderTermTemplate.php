<?php
/**
 * Renders taxonomy template strings with token replacements.
 *
 * @package Airygen\Modules\TaxonomySeo\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\TaxonomySeo\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template renderer with no WordPress dependencies.
 */
final class RenderTermTemplate {

	/**
	 * Render a template with replacements.
	 *
	 * @param string               $template     Template input.
	 * @param array<string, mixed> $replacements Token map.
	 *
	 * @return string
	 */
	public static function render( string $template, array $replacements ): string {
		$normalized = array();
		foreach ( $replacements as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			$normalized[ $key ] = is_scalar( $value ) ? (string) $value : '';
		}

		$output = strtr( $template, $normalized );
		$output = (string) preg_replace( '/<[^>]*>/', '', $output );

		return trim( $output );
	}
}
