<?php
/**
 * Rules for Score Calculator GET endpoint.
 *
 * @package Airygen\Support\RequestRules\ScoreCalculator
 */

declare(strict_types=1);

namespace Airygen\Support\RequestRules\ScoreCalculator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides schema for GET /score arguments.
 */
final class GetScore {

	/**
	 * Return the REST args schema.
	 *
	 * @return array<string,mixed>
	 */
	public function __invoke(): array {
		return array(
			'post'                       => array(
				'type'     => 'integer',
				'required' => true,
			),
			'meta_title_length_px'       => array(
				'type'     => 'number',
				'required' => false,
			),
			'meta_description_length_px' => array(
				'type'     => 'number',
				'required' => false,
			),
		);
	}
}
