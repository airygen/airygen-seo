<?php
/**
 * Domain representation of organization schema data.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Contexts
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Contexts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds organization metadata for schema emission.
 */
final class OrganizationContext {

	/**
	 * Organization display name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Schema type for the organization.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Canonical URL for the organization.
	 *
	 * @var string|null
	 */
	private ?string $url;

	/**
	 * Logo URL if available.
	 *
	 * @var string|null
	 */
	private ?string $logo;

	/**
	 * Instantiate the context.
	 *
	 * @param string      $name Organization name.
	 * @param string      $type Organization schema type.
	 * @param string|null $url  Canonical URL.
	 * @param string|null $logo Logo URL.
	 */
	private function __construct( string $name, string $type, ?string $url, ?string $logo ) {
		$this->name = $name;
		$this->type = $type;
		$this->url  = $url;
		$this->logo = $logo;
	}

	/**
	 * Create the context.
	 *
	 * @param string      $name Organization name.
	 * @param string      $type Organization schema type.
	 * @param string|null $url  Canonical URL.
	 * @param string|null $logo Logo URL.
	 */
	public static function from_values( string $name, string $type, ?string $url, ?string $logo ): self {
		return new self( $name, $type, $url, $logo );
	}

	/**
	 * Full organization payload for schema graph.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$payload = array(
			'name' => $this->name,
			'type' => $this->type,
		);

		if ( null !== $this->url && '' !== $this->url ) {
			$payload['url'] = $this->url;
		}

		if ( null !== $this->logo && '' !== $this->logo ) {
			$payload['logo'] = $this->logo;
		}

		return $payload;
	}

	/**
	 * Publisher fragment used by Article schema.
	 *
	 * @return array<string, string>
	 */
	public function to_publisher_fragment(): array {
		$publisher = array(
			'name' => $this->name,
			'type' => $this->type,
		);

		if ( null !== $this->logo && '' !== $this->logo ) {
			$publisher['logo'] = $this->logo;
		}

		return $publisher;
	}

	/**
	 * Expose organization URL.
	 */
	public function get_url(): ?string {
		return $this->url;
	}
}
