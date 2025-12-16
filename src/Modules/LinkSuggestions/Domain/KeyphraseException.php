<?php
/**
 * Exception for keyphrase operations.
 *
 * @package Airygen\Modules\LinkSuggestions\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

class KeyphraseException extends RuntimeException {
}
