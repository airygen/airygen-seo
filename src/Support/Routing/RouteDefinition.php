<?php
/**
 * Value object describing a REST route definition.
 *
 * @package Airygen\Support\Routing
 */

declare(strict_types=1);

namespace Airygen\Support\Routing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents a single REST route configuration.
 */
final class RouteDefinition {

	/**
	 * REST route path (e.g. /settings).
	 *
	 * @var string
	 */
	private string $route;

	/**
	 * HTTP method constant accepted by WordPress.
	 *
	 * @var string
	 */
	private string $methods;

	/**
	 * Callback that handles the request.
	 *
	 * @var callable
	 */
	private $callback;

	/**
	 * Optional permission callback for this route.
	 *
	 * @var callable|null
	 */
	private $permission_callback = null;

	/**
	 * Optional namespace override.
	 *
	 * @var string|null
	 */
	private ?string $namespace = null;

	/**
	 * Argument schema passed to register_rest_route.
	 *
	 * @var array<string,mixed>
	 */
	private array $args = array();

	/**
	 * Create a new route definition instance.
	 *
	 * @param string   $route    Route path.
	 * @param string   $methods  WP_REST_Server::* constant.
	 * @param callable $callback Handler callback.
	 * @return self
	 */
	public static function make( string $route, string $methods, callable $callback ): self {
		$instance           = new self();
		$instance->route    = $route;
		$instance->methods  = $methods;
		$instance->callback = $callback;

		return $instance;
	}

	/**
	 * Set the args schema.
	 *
	 * @param array<string,mixed> $args Argument schema.
	 * @return self
	 */
	public function args( array $args ): self {
		$this->args = $args;

		return $this;
	}

	/**
	 * Override the permission callback.
	 *
	 * @param callable $callback Permission callback.
	 * @return self
	 */
	public function permission( callable $callback ): self {
		$this->permission_callback = $callback;

		return $this;
	}

	/**
	 * Override the namespace.
	 *
	 * @param string $route_namespace REST namespace.
	 * @return self
	 */
	public function namespace( string $route_namespace ): self {
		$this->namespace = $route_namespace;

		return $this;
	}

	/**
	 * Override the HTTP methods flag.
	 *
	 * @param string $methods Methods string.
	 * @return self
	 */
	public function methods( string $methods ): self {
		$this->methods = $methods;

		return $this;
	}

	/**
	 * Get the route path.
	 *
	 * @return string
	 */
	public function route(): string {
		return $this->route;
	}

	/**
	 * Get the HTTP methods flag.
	 *
	 * @return string
	 */
	public function methods_flag(): string {
		return $this->methods;
	}

	/**
	 * Get the handler callback.
	 *
	 * @return callable
	 */
	public function callback(): callable {
		return $this->callback;
	}

	/**
	 * Get the namespace override, if any.
	 *
	 * @return string|null
	 */
	public function namespace_override(): ?string {
		return $this->namespace;
	}

	/**
	 * Get the permission callback override.
	 *
	 * @return callable|null
	 */
	public function permission_callback(): ?callable {
		return $this->permission_callback;
	}

	/**
	 * Get the registered arguments.
	 *
	 * @return array<string,mixed>
	 */
	public function args_schema(): array {
		return $this->args;
	}
}
