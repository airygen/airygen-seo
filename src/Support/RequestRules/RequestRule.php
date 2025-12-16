<?php
/**
 * Helper for resolving request rule classes.
 *
 * @package Airygen\Support\RequestRules
 */

declare(strict_types=1);

namespace Airygen\Support\RequestRules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;

/**
 * Factory that instantiates rule objects and invokes them.
 */
final class RequestRule {

	/**
	 * Instantiate the given rule class and invoke it.
	 *
	 * @template T of object
	 *
	 * @param class-string<T> $rule_class Fully-qualified rule class implementing __invoke().
	 * @param mixed           ...$args    Optional arguments forwarded to the rule.
	 *
	 * @return mixed
	 *
	 * @throws InvalidArgumentException When the class does not exist or is not invokable.
	 */
	public static function revoke( string $rule_class, ...$args ) {
		if ( ! class_exists( $rule_class ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Request rule class %s does not exist.', esc_html( $rule_class ) )
			);
		}

		$instance = new $rule_class();

		if ( ! is_callable( $instance ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Request rule class %s is not invokable.', esc_html( $rule_class ) )
			);
		}

		return $instance( ...$args );
	}
}
