<?php
/**
 * DTO representing Open Graph metadata.
 *
 * @package Airygen\Modules\SocialCards\Domain\Dto
 */

declare(strict_types=1);

namespace Airygen\Modules\SocialCards\Domain\Dto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable Open Graph payload.
 */
final class OpenGraphCard {

	/**
	 * Open Graph title.
	 *
	 * @var string|null
	 */
	private ?string $title;

	/**
	 * Open Graph description.
	 *
	 * @var string|null
	 */
	private ?string $description;

	/**
	 * Canonical URL.
	 *
	 * @var string|null
	 */
	private ?string $url;

	/**
	 * Resource type (e.g. article).
	 *
	 * @var string|null
	 */
	private ?string $type;

	/**
	 * Image URL for previews.
	 *
	 * @var string|null
	 */
	private ?string $image;

	/**
	 * Image width hint.
	 *
	 * @var int|null
	 */
	private ?int $image_width;

	/**
	 * Image height hint.
	 *
	 * @var int|null
	 */
	private ?int $image_height;

	/**
	 * Facebook App ID.
	 *
	 * @var string|null
	 */
	private ?string $fb_app_id;

	/**
	 * Facebook admins (comma-separated IDs).
	 *
	 * @var string|null
	 */
	private ?string $fb_admins;

	/**
	 * Article publisher URL.
	 *
	 * @var string|null
	 */
	private ?string $publisher_url;

	/**
	 * Domain verification token.
	 *
	 * @var string|null
	 */
	private ?string $domain_verification;

	/**
	 * Site name used in OG tags.
	 *
	 * @var string|null
	 */
	private ?string $site_name;

	/**
	 * Constructor.
	 *
	 * @param string|null $title                OG title.
	 * @param string|null $description          OG description.
	 * @param string|null $url                  Canonical URL.
	 * @param string|null $type                 Resource type (e.g. article).
	 * @param string|null $image                Image URL.
	 * @param int|null    $image_width          Image width hint.
	 * @param int|null    $image_height         Image height hint.
	 * @param string|null $site_name            Site name.
	 * @param string|null $fb_app_id            Facebook App ID.
	 * @param string|null $fb_admins            Comma-separated Facebook admin IDs.
	 * @param string|null $publisher_url        Publisher page URL.
	 * @param string|null $domain_verification  Facebook domain verification token.
	 */
	public function __construct(
		?string $title,
		?string $description,
		?string $url,
		?string $type,
		?string $image,
		?int $image_width,
		?int $image_height,
		?string $site_name,
		?string $fb_app_id,
		?string $fb_admins,
		?string $publisher_url,
		?string $domain_verification
	) {
		$this->title               = $this->normalize( $title );
		$this->description         = $this->normalize( $description );
		$this->url                 = $this->normalize( $url );
		$this->type                = $this->normalize( $type );
		$this->image               = $this->normalize( $image );
		$this->image_width         = $this->normalize_dimension( $image_width );
		$this->image_height        = $this->normalize_dimension( $image_height );
		$this->site_name           = $this->normalize( $site_name );
		$this->fb_app_id           = $this->normalize( $fb_app_id );
		$this->fb_admins           = $this->normalize( $fb_admins );
		$this->publisher_url       = $this->normalize( $publisher_url );
		$this->domain_verification = $this->normalize( $domain_verification );
	}

	/**
	 * Retrieve the OG title.
	 *
	 * @return string|null
	 */
	public function get_title(): ?string {
		return $this->title;
	}

	/**
	 * Retrieve the OG description.
	 *
	 * @return string|null
	 */
	public function get_description(): ?string {
		return $this->description;
	}

	/**
	 * Retrieve the OG canonical URL.
	 *
	 * @return string|null
	 */
	public function get_url(): ?string {
		return $this->url;
	}

	/**
	 * Retrieve the OG type.
	 *
	 * @return string|null
	 */
	public function get_type(): ?string {
		return $this->type;
	}

	/**
	 * Retrieve the OG image URL.
	 *
	 * @return string|null
	 */
	public function get_image(): ?string {
		return $this->image;
	}

	/**
	 * Retrieve the site name.
	 *
	 * @return string|null
	 */
	public function get_site_name(): ?string {
		return $this->site_name;
	}

	/**
	 * Export as associative meta list.
	 *
	 * @return array<string, string>
	 */
	public function to_tags(): array {
		$tags = array();

		if ( $this->site_name ) {
			$tags['og:site_name'] = $this->site_name;
		}

		if ( $this->title ) {
			$tags['og:title'] = $this->title;
		}

		if ( $this->description ) {
			$tags['og:description'] = $this->description;
		}

		if ( $this->url ) {
			$tags['og:url'] = $this->url;
		}

		if ( $this->type ) {
			$tags['og:type'] = $this->type;
		}

		if ( $this->image ) {
			$tags['og:image'] = $this->image;
		}

		if ( $this->image_width ) {
			$tags['og:image:width'] = (string) $this->image_width;
		}

		if ( $this->image_height ) {
			$tags['og:image:height'] = (string) $this->image_height;
		}

		if ( $this->fb_app_id ) {
			$tags['fb:app_id'] = $this->fb_app_id;
		}

		if ( $this->fb_admins ) {
			$tags['fb:admins'] = $this->fb_admins;
		}

		if ( $this->publisher_url ) {
			$tags['article:publisher'] = $this->publisher_url;
		}

		if ( $this->domain_verification ) {
			$tags['facebook-domain-verification'] = $this->domain_verification;
		}

		return $tags;
	}

	/**
	 * Normalize incoming strings, trimming whitespace and empty values.
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
	 * Normalize dimension values.
	 *
	 * @param int|null $value Incoming value.
	 *
	 * @return int|null
	 */
	private function normalize_dimension( ?int $value ): ?int {
		if ( null === $value ) {
			return null;
		}

		$value = absint( $value );
		return $value > 0 ? $value : null;
	}
}
