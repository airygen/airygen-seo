<?php
/**
 * Emits the Schema.org JSON-LD markup.
 *
 * @package Airygen\Modules\SchemaMarkup\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SchemaMarkup\Domain\Service\BuildJsonLd;

/**
 * Outputs JSON-LD script to the document head.
 */
final class EmitJsonLd {
	/**
	 * Guard against duplicate JSON-LD output in the same request.
	 *
	 * @var bool
	 */
	private static $did_emit = false;

	/**
	 * Emit JSON-LD markup.
	 *
	 * @return void
	 */
	public static function emit(): void {
		if ( did_action( 'airygen_schema_jsonld_emitted' ) > 0 ) {
			return;
		}

		if ( self::$did_emit ) {
			return;
		}

		$context = ContextResolver::build();
		$payload = BuildJsonLd::from_context( $context );
		$payload = apply_filters( 'airygen_schema_jsonld_payload', $payload, $context );

		if ( empty( $payload ) ) {
			return;
		}

		$json = wp_json_encode( $payload );
		if ( false === $json ) {
			return;
		}

		wp_print_inline_script_tag(
			$json,
			array( 'type' => 'application/ld+json' )
		);

		self::$did_emit = true;
		do_action( 'airygen_schema_jsonld_emitted' );
	}
}
