<?php
/**
 * Domain representation of website schema metadata.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Contexts
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Contexts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encapsulates WebSite schema payload.
 */
final class WebsiteContext {

	/**
	 * Site name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Canonical URL.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Search action target URL.
	 *
	 * @var string
	 */
	private string $search_url;

	/**
	 * Query parameter name used for search.
	 *
	 * @var string
	 */
	private string $search_query_param;

	/**
	 * Human-friendly label for the search action.
	 *
	 * @var string
	 */
	private string $potential_action_name;

	/**
	 * Site language code.
	 *
	 * @var string|null
	 */
	private ?string $language;

	/**
	 * Instantiate the context.
	 *
	 * @param string      $name                  Site name.
	 * @param string      $url                   Canonical URL.
	 * @param string      $search_url            Search target URL.
	 * @param string      $search_query_param    Search query parameter name.
	 * @param string      $potential_action_name Search action label.
	 * @param string|null $language              Locale identifier.
	 */
	private function __construct( string $name, string $url, string $search_url, string $search_query_param, string $potential_action_name, ?string $language ) {
		$this->name                  = $name;
		$this->url                   = $url;
		$this->search_url            = $search_url;
		$this->search_query_param    = $search_query_param;
		$this->potential_action_name = $potential_action_name;
		$this->language              = $language;
	}

	/**
	 * Factory helper.
	 *
	 * @param string      $name                  Site name.
	 * @param string      $url                   Canonical URL.
	 * @param string      $search_url            Search target URL.
	 * @param string      $search_query_param    Search query parameter name.
	 * @param string      $potential_action_name Search action label.
	 * @param string|null $language              Locale identifier.
	 */
	public static function from_values( string $name, string $url, string $search_url, string $search_query_param, string $potential_action_name, ?string $language ): self {
		return new self( $name, $url, $search_url, $search_query_param, $potential_action_name, $language );
	}

	/**
	 * Convert to schema array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$payload = array(
			'name'                  => $this->name,
			'url'                   => $this->url,
			'search_url'            => $this->search_url,
			'search_query_param'    => $this->search_query_param,
			'potential_action_name' => $this->potential_action_name,
		);

		if ( null !== $this->language && '' !== $this->language ) {
			$payload['language'] = $this->language;
		}

		return $payload;
	}
}
