<?php
/**
 * Contract for fetching keyphrases.
 *
 * @package Airygen\Modules\LinkSuggestions\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface KeyphraseClientInterface {

	/**
	 * Fetch keyphrases for a given request.
	 *
	 * @param KeyphraseRequest $request Request payload.
	 *
	 * @return KeyphraseDto
	 */
	public function fetch( KeyphraseRequest $request ): KeyphraseDto;
}
