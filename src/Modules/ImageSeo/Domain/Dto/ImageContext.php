<?php
/**
 * Data transfer object describing the current image context.
 *
 * @package Airygen\Modules\ImageSeo\Domain\Dto
 */

declare(strict_types=1);

namespace Airygen\Modules\ImageSeo\Domain\Dto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries template variables required to build attributes.
 */
final class ImageContext {

	private string $post_title;
	private string $file_name;
	private string $attachment_title;
	private string $source_alt;
	private string $source_title;
	private string $focus_keyphrase;
	/**
	 * @var array<int, string>
	 */
	private array $long_tail_keyphrases = array();
	private string $separator;
	/**
	 * @var array<string, string>
	 */
	private array $custom_tokens = array();

	/**
	 * @param string      $post_title      Current post title.
	 * @param string      $file_name       File name (without extension).
	 * @param string      $attachment_title Attachment title, if available.
	 * @param string|null $source_alt      Existing alt attribute.
	 * @param string|null $source_title    Existing title attribute.
	 * @param string      $focus_keyphrase Focus keyphrase value.
	 * @param string[]    $long_tail       Long-tail keyphrases.
	 * @param string      $separator       Separator value.
	 * @param array       $custom_tokens   Custom token values.
	 */
	public function __construct(
		string $post_title,
		string $file_name = '',
		string $attachment_title = '',
		?string $source_alt = null,
		?string $source_title = null,
		string $focus_keyphrase = '',
		array $long_tail = array(),
		string $separator = '',
		array $custom_tokens = array()
	) {
		$this->post_title           = $this->sanitize( $post_title );
		$this->file_name            = $this->sanitize( $file_name );
		$this->attachment_title     = $this->sanitize( $attachment_title );
		$this->source_alt           = $this->sanitize( $source_alt ?? '' );
		$this->source_title         = $this->sanitize( $source_title ?? '' );
		$this->focus_keyphrase      = $this->sanitize( $focus_keyphrase );
		$this->long_tail_keyphrases = $this->sanitize_list( $long_tail );
		$this->separator            = $this->sanitize( $separator );
		$this->custom_tokens        = $this->sanitize_assoc( $custom_tokens );
	}

	/**
	 * Retrieve the post title.
	 *
	 * @return string
	 */
	public function get_post_title(): string {
		return $this->post_title;
	}

	/**
	 * Retrieve the image file name.
	 *
	 * @return string
	 */
	public function get_file_name(): string {
		return $this->file_name;
	}

	/**
	 * Retrieve the attachment title.
	 *
	 * @return string
	 */
	public function get_attachment_title(): string {
		return $this->attachment_title;
	}

	/**
	 * Retrieve the source alt attribute.
	 *
	 * @return string
	 */
	public function get_source_alt(): string {
		return $this->source_alt;
	}

	/**
	 * Retrieve the source title attribute.
	 *
	 * @return string
	 */
	public function get_source_title(): string {
		return $this->source_title;
	}

	/**
	 * Retrieve the focus keyphrase.
	 *
	 * @return string
	 */
	public function get_focus_keyphrase(): string {
		return $this->focus_keyphrase;
	}

	/**
	 * Retrieve long-tail keyphrases.
	 *
	 * @return array<int, string>
	 */
	public function get_long_tail_keyphrases(): array {
		return $this->long_tail_keyphrases;
	}

	/**
	 * Retrieve the separator.
	 *
	 * @return string
	 */
	public function get_separator(): string {
		if ( '' === $this->separator ) {
			return '';
		}

		return ' ' . $this->separator . ' ';
	}

	/**
	 * Retrieve a custom token value by key.
	 *
	 * @param string $key Token key (custom_1, custom_2, custom_3).
	 *
	 * @return string
	 */
	public function get_custom_token( string $key ): string {
		return $this->custom_tokens[ $key ] ?? '';
	}

	/**
	 * Sanitize arbitrary text.
	 *
	 * @param string $value Raw text.
	 *
	 * @return string
	 */
	private function sanitize( string $value ): string {
		$clean = wp_strip_all_tags( $value );
		$clean = preg_replace( '/\s+/', ' ', $clean );
		return trim( (string) $clean );
	}

	/**
	 * Sanitize a list of text values.
	 *
	 * @param array $values Raw values.
	 *
	 * @return array<int, string>
	 */
	private function sanitize_list( array $values ): array {
		$clean = array();
		foreach ( $values as $value ) {
			$value = $this->sanitize( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$clean[] = $value;
		}
		return $clean;
	}

	/**
	 * Sanitize associative token values.
	 *
	 * @param array $values Raw values.
	 *
	 * @return array<string, string>
	 */
	private function sanitize_assoc( array $values ): array {
		$clean = array();
		foreach ( $values as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			$clean[ $key ] = $this->sanitize( (string) $value );
		}
		return $clean;
	}
}
