<?php
/**
 * Generates attribute values based on configurable templates.
 *
 * @package Airygen\Modules\ImageSeo\Domain\Service
 */

declare(strict_types=1);

namespace Airygen\Modules\ImageSeo\Domain\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\ImageSeo\Domain\Dto\ImageContext;

/**
 * Expand template tokens into attribute strings.
 */
final class GenerateAttribute {

	/**
	 * Tracks counter values per token key.
	 *
	 * @var array<string, int>
	 */
	private array $counters = array();
	/**
	 * Reset internal counters (mainly useful for tests).
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->counters = array();
	}

	/**
	 * Generate an attribute from the provided template and context.
	 *
	 * @param string       $template Template string (with %tokens%).
	 * @param ImageContext $context  Contextual data.
	 *
	 * @return string
	 */
	public function generate( string $template, ImageContext $context ): string {
		$template = trim( $template );

		if ( '' === $template ) {
			return '';
		}

		$pattern = '/%([a-z_\*]+)(?:\(([^)]*)\))?%/i';

		$result = preg_replace_callback(
			$pattern,
			function ( array $matches ) use ( $context ): string {
				$token    = strtolower( $matches[1] ?? '' );
				$argument = isset( $matches[2] ) ? strtolower( $matches[2] ) : '';

				return $this->resolve_token( $token, $argument, $context );
			},
			$template
		);

		if ( null === $result ) {
			return '';
		}

		$result = preg_replace( '/\s+/', ' ', $result );

		return trim( (string) $result );
	}

	/**
	 * Resolve a token into its final value.
	 *
	 * @param string       $token    Token name (lowercase).
	 * @param string       $argument Optional token argument.
	 * @param ImageContext $context  Contextual data.
	 *
	 * @return string
	 */
	private function resolve_token( string $token, string $argument, ImageContext $context ): string {
		switch ( $token ) {
			case 'title':
			case 'post_title':
				return $context->get_post_title();

			case 'filename':
				return $context->get_file_name();

			case 'image_title':
			case 'attachment_title':
				return $context->get_attachment_title();

			case 'separator':
				return $context->get_separator();

			case 'focus_keyphase':
			case 'focus_keyphrase':
				return $context->get_focus_keyphrase();

			case 'longtail_keyphase_*':
			case 'longtail_keyphrase_*':
				return $this->resolve_long_tail( $context );

			case 'custom_1':
			case 'custom_2':
			case 'custom_3':
				return $context->get_custom_token( $token );

			case 'counter':
				return (string) $this->next_count( 'default' );

			default:
				return '';
		}
	}

	/**
	 * Choose a random long-tail keyphrase for this run.
	 *
	 * @param ImageContext $context Context data.
	 *
	 * @return string
	 */
	private function resolve_long_tail( ImageContext $context ): string {
		$phrases = $context->get_long_tail_keyphrases();
		if ( empty( $phrases ) ) {
			return '';
		}

		$index = $this->next_count( 'longtail' ) - 1;
		if ( ! isset( $phrases[ $index ] ) ) {
			return '';
		}

		return (string) $phrases[ $index ];
	}

	/**
	 * Increment and retrieve the counter for a given key.
	 *
	 * @param string $key Counter identifier.
	 *
	 * @return int
	 */
	private function next_count( string $key ): int {
		if ( ! isset( $this->counters[ $key ] ) ) {
			$this->counters[ $key ] = 1;
			return 1;
		}

		++$this->counters[ $key ];

		return $this->counters[ $key ];
	}
}
