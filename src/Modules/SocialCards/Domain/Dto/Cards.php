<?php
/**
 * Aggregated DTO containing both OG and Twitter cards.
 *
 * @package Airygen\Modules\SocialCards\Domain\Dto
 */

declare(strict_types=1);

namespace Airygen\Modules\SocialCards\Domain\Dto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Container for social card DTOs.
 */
final class Cards {

	/**
	 * Open Graph card payload.
	 *
	 * @var OpenGraphCard|null
	 */
	private ?OpenGraphCard $open_graph;

	/**
	 * Twitter card payload.
	 *
	 * @var TwitterCard|null
	 */
	private ?TwitterCard $twitter;

	/**
	 * Constructor.
	 *
	 * @param OpenGraphCard|null $open_graph Open Graph data.
	 * @param TwitterCard|null   $twitter    Twitter card data.
	 */
	public function __construct( ?OpenGraphCard $open_graph, ?TwitterCard $twitter ) {
		$this->open_graph = $open_graph;
		$this->twitter    = $twitter;
	}

	/**
	 * Retrieve the Open Graph card.
	 *
	 * @return OpenGraphCard|null
	 */
	public function get_open_graph(): ?OpenGraphCard {
		return $this->open_graph;
	}

	/**
	 * Retrieve the Twitter card.
	 *
	 * @return TwitterCard|null
	 */
	public function get_twitter(): ?TwitterCard {
		return $this->twitter;
	}
}
