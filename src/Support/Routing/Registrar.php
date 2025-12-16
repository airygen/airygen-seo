<?php
/**
 * Registers all REST routes defined via the Route DSL.
 *
 * @package Airygen\Support\Routing
 */

declare(strict_types=1);

namespace Airygen\Support\Routing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Support\Debug\Logger;

/**
 * Bootstrapper that wires collected routes into WordPress.
 */
final class Registrar {

	private const DEFAULT_NAMESPACE = 'airygen/v1';

	/**
	 * Boot the registrar and schedule REST route registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		$definitions = self::load_definitions();

		add_action(
			'rest_api_init',
			static function () use ( $definitions ): void {
				$routes = apply_filters( Constants::HOOK_ROUTES, $definitions ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

				foreach ( $routes as $definition ) {
					if ( ! $definition instanceof RouteDefinition ) {
						continue;
					}

					self::register_definition( $definition );
				}
			}
		);
	}

	/**
	 * Load route definitions from config/routes.php.
	 *
	 * @return array<int,RouteDefinition>
	 */
	private static function load_definitions(): array {
		Route::flush();

		$routes_file = trailingslashit( AIRYGEN_PLUGIN_DIR ) . 'config/routes.php';
		if ( is_readable( $routes_file ) ) {
			require $routes_file;
		}

		return Route::definitions();
	}

	/**
	 * Register a single route definition with WordPress.
	 *
	 * @param RouteDefinition $definition Route definition.
	 * @return void
	 */
	private static function register_definition( RouteDefinition $definition ): void {
		$namespace = $definition->namespace_override() ?? self::DEFAULT_NAMESPACE;
		$args      = array(
			array(
				'methods'             => $definition->methods_flag(),
				'callback'            => $definition->callback(),
				'permission_callback' => $definition->permission_callback() ?? array( __CLASS__, 'default_permission' ),
				'args'                => $definition->args_schema(),
			),
		);

		register_rest_route(
			$namespace,
			$definition->route(),
			$args
		);

		self::maybe_debug( $namespace, $definition->route(), $definition->methods_flag() );
	}

	/**
	 * Default permission callback for Airygen REST routes.
	 *
	 * @return bool
	 */
	public static function default_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Log route registration when WP_DEBUG is enabled.
	 *
	 * @param string $route_namespace REST namespace.
	 * @param string $route           Route path.
	 * @param string $methods         HTTP methods flag.
	 * @return void
	 */
	private static function maybe_debug( string $route_namespace, string $route, string $methods ): void {
		Logger::log( 'debug', sprintf( 'registered %s %s%s', $methods, $route_namespace, $route ) );
	}
}
