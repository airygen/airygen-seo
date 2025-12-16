<?php
/**
 * Value object for a supported IndexNow search engine.
 *
 * @package Airygen\Modules\InstantIndexing\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a single IndexNow engine.
 */
final class Engine {

	/**
	 * Engine identifier (e.g. bing).
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Human readable label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Default endpoint URL.
	 *
	 * @var string
	 */
	private $default_endpoint;

	/**
	 * Constructor.
	 *
	 * @param string $slug             Engine slug.
	 * @param string $label            Human-readable label.
	 * @param string $default_endpoint Default endpoint URL.
	 */
	public function __construct( string $slug, string $label, string $default_endpoint ) {
		$this->slug             = $slug;
		$this->label            = $label;
		$this->default_endpoint = $default_endpoint;
	}

	/**
	 * Retrieve the engine slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Retrieve the label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Retrieve the default endpoint URL.
	 *
	 * @return string
	 */
	public function get_default_endpoint(): string {
		return $this->default_endpoint;
	}
}
