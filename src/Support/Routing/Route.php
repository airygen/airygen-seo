<?php
/**
 * Fluent facade for building REST routes.
 *
 * @package Airygen\Support\Routing
 */

declare(strict_types=1);

namespace Airygen\Support\Routing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BadMethodCallException;
use WP_REST_Server;

/**
 * Provides Laravel-like methods to declare REST routes.
 */
final class Route {

	/**
	 * Collected route definitions.
	 *
	 * @var array<int,RouteDefinition>
	 */
	private static array $definitions = array();

	/**
	 * Register a group of routes via callback.
	 *
	 * @param callable $callback Callback receiving a Route instance.
	 * @return void
	 */
	public static function group( callable $callback ): void {
		$router = new self();
		$callback( $router );
	}

	/**
	 * Support static calls (e.g. Route::get()) by proxying to instance methods.
	 *
	 * @param string $name      Method name.
	 * @param array  $arguments Arguments passed to the static call.
	 * @return RouteDefinition
	 * @throws BadMethodCallException When the route method is not supported.
	 */
	public static function __callStatic( string $name, array $arguments ): RouteDefinition {
		$router = new self();

		if ( method_exists( $router, $name ) ) {
			return $router->{$name}( ...$arguments );
		}

		throw new BadMethodCallException(
			sprintf( 'Route method %s is not supported.', esc_html( $name ) )
		);
	}

	/**
	 * Instance helper for GET, matches static signature.
	 *
	 * @param string   $route    Route path.
	 * @param callable $callback Handler.
	 * @return RouteDefinition
	 */
	public function get( string $route, callable $callback ): RouteDefinition {
		return self::store( $route, WP_REST_Server::READABLE, $callback );
	}

	/**
	 * Instance helper for POST, matches static signature.
	 *
	 * @param string   $route    Route path.
	 * @param callable $callback Handler.
	 * @return RouteDefinition
	 */
	public function post( string $route, callable $callback ): RouteDefinition {
		return self::store( $route, WP_REST_Server::CREATABLE, $callback );
	}

	/**
	 * Instance helper for PUT.
	 *
	 * @param string   $route    Route path.
	 * @param callable $callback Handler.
	 * @return RouteDefinition
	 */
	public function put( string $route, callable $callback ): RouteDefinition {
		return self::store( $route, WP_REST_Server::EDITABLE, $callback );
	}

	/**
	 * Instance helper for PATCH.
	 *
	 * @param string   $route    Route path.
	 * @param callable $callback Handler.
	 * @return RouteDefinition
	 */
	public function patch( string $route, callable $callback ): RouteDefinition {
		return self::store( $route, WP_REST_Server::EDITABLE, $callback );
	}

	/**
	 * Instance helper for DELETE.
	 *
	 * @param string   $route    Route path.
	 * @param callable $callback Handler.
	 * @return RouteDefinition
	 */
	public function delete( string $route, callable $callback ): RouteDefinition {
		return self::store( $route, WP_REST_Server::DELETABLE, $callback );
	}

	/**
	 * Return all registered route definitions.
	 *
	 * @return array<int,RouteDefinition>
	 */
	public static function definitions(): array {
		return self::$definitions;
	}

	/**
	 * Remove all registered routes.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$definitions = array();
	}

	/**
	 * Internal helper to store definitions.
	 *
	 * @param string   $route    Route path.
	 * @param string   $methods  HTTP methods flag.
	 * @param callable $callback Handler callback.
	 * @return RouteDefinition
	 */
	private static function store( string $route, string $methods, callable $callback ): RouteDefinition {
		$definition          = RouteDefinition::make( $route, $methods, $callback );
		self::$definitions[] = $definition;

		return $definition;
	}
}
