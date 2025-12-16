<?php
/**
 * DTO representing Twitter card metadata.
 *
 * @package Airygen\Modules\SocialCards\Domain\Dto
 */

declare(strict_types=1);

namespace Airygen\Modules\SocialCards\Domain\Dto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable Twitter card payload.
 */
final class TwitterCard {

	/**
	 * Card type identifier.
	 *
	 * @var string
	 */
	private string $card_type;

	/**
	 * Optional title override.
	 *
	 * @var string|null
	 */
	private ?string $title;

	/**
	 * Optional description.
	 *
	 * @var string|null
	 */
	private ?string $description;

	/**
	 * Canonical URL for the card.
	 *
	 * @var string|null
	 */
	private ?string $url;

	/**
	 * Image URL to display.
	 *
	 * @var string|null
	 */
	private ?string $image;

	/**
	 * Twitter handle for the site.
	 *
	 * @var string|null
	 */
	private ?string $site;

	/**
	 * Twitter handle for the author.
	 *
	 * @var string|null
	 */
	private ?string $creator;

	/**
	 * Create a new Twitter card DTO.
	 *
	 * @param string|null $card_type  Card type.
	 * @param string|null $title      Card title.
	 * @param string|null $description Card description.
	 * @param string|null $url        Canonical URL.
	 * @param string|null $image      Image URL.
	 * @param string|null $site       Site handle.
	 * @param string|null $creator    Creator handle.
	 */
	public function __construct(
		?string $card_type,
		?string $title,
		?string $description,
		?string $url,
		?string $image,
		?string $site,
		?string $creator
	) {
		$this->card_type   = $this->normalize_card_type( $card_type );
		$this->title       = $this->normalize( $title );
		$this->description = $this->normalize( $description );
		$this->url         = $this->normalize( $url );
		$this->image       = $this->normalize( $image );
		$this->site        = $this->normalize_handle( $site );
		$this->creator     = $this->normalize_handle( $creator );
	}

	/**
	 * Retrieve the card type string.
	 *
	 * @return string
	 */
	public function get_card_type(): string {
		return $this->card_type;
	}

	/**
	 * Retrieve the card title.
	 *
	 * @return string|null
	 */
	public function get_title(): ?string {
		return $this->title;
	}

	/**
	 * Retrieve the card description.
	 *
	 * @return string|null
	 */
	public function get_description(): ?string {
		return $this->description;
	}

	/**
	 * Retrieve the canonical URL.
	 *
	 * @return string|null
	 */
	public function get_url(): ?string {
		return $this->url;
	}

	/**
	 * Retrieve the image URL.
	 *
	 * @return string|null
	 */
	public function get_image(): ?string {
		return $this->image;
	}

	/**
	 * Retrieve the site handle.
	 *
	 * @return string|null
	 */
	public function get_site(): ?string {
		return $this->site;
	}

	/**
	 * Retrieve the creator handle.
	 *
	 * @return string|null
	 */
	public function get_creator(): ?string {
		return $this->creator;
	}

	/**
	 * Export card metadata map.
	 *
	 * @return array<string, string>
	 */
	public function to_tags(): array {
		$tags = array(
			'twitter:card' => $this->card_type,
		);

		if ( $this->title ) {
			$tags['twitter:title'] = $this->title;
		}

		if ( $this->description ) {
			$tags['twitter:description'] = $this->description;
		}

		if ( $this->url ) {
			$tags['twitter:url'] = $this->url;
		}

		if ( $this->image ) {
			$tags['twitter:image'] = $this->image;
		}

		if ( $this->site ) {
			$tags['twitter:site'] = '@' . ltrim( $this->site, '@' );
		}

		if ( $this->creator ) {
			$tags['twitter:creator'] = '@' . ltrim( $this->creator, '@' );
		}

		return $tags;
	}

	/**
	 * Normalize arbitrary string input.
	 *
	 * @param string|null $value Input value.
	 *
	 * @return string|null
	 */
	private function normalize( ?string $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( $value );
		return '' === $value ? null : $value;
	}

	/**
	 * Normalize Twitter handle values.
	 *
	 * @param string|null $value Handle input.
	 *
	 * @return string|null
	 */
	private function normalize_handle( ?string $value ): ?string {
		$value = $this->normalize( $value );
		if ( null === $value ) {
			return null;
		}

		$value = ltrim( $value, '@' );
		if ( '' === $value ) {
			return null;
		}

		if ( ! preg_match( '/^[A-Za-z0-9_]{1,15}$/', $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Normalize card type.
	 *
	 * @param string|null $value Raw card type.
	 *
	 * @return string
	 */
	private function normalize_card_type( ?string $value ): string {
		$value = $this->normalize( $value );
		return in_array( $value, array( 'summary', 'summary_large_image' ), true )
		? $value
		: 'summary_large_image';
	}
}
