<?php
/**
 * Domain representation of breadcrumb items.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Contexts
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Contexts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds breadcrumb items for schema generation.
 */
final class BreadcrumbContext {

	/**
	 * Breadcrumb node identifier.
	 *
	 * @var string|null
	 */
	private ?string $id;

	/**
	 * Normalized breadcrumb items.
	 *
	 * @var array<int, array{name:string,url:?string}>
	 */
	private array $items;

	/**
	 * Internal constructor.
	 *
	 * @param string|null                                 $id    Breadcrumb node identifier.
	 * @param array<int, array{name:string,url:?string}> $items Normalized items.
	 */
	private function __construct( ?string $id, array $items ) {
		$this->id    = $id;
		$this->items = $items;
	}

	/**
	 * Create context from raw items.
	 *
	 * @param array<int, array<string, mixed>> $items Raw breadcrumb items.
	 * @param string|null                      $id    Breadcrumb node identifier.
	 */
	public static function from_items( array $items, ?string $id = null ): self {
		$normalized = array();

		foreach ( $items as $item ) {
			$name = isset( $item['name'] ) ? trim( (string) $item['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$url = isset( $item['url'] ) ? trim( (string) $item['url'] ) : '';

			$normalized[] = array(
				'name' => $name,
				'url'  => '' === $url ? null : $url,
			);
		}

		$normalized_id = null;
		if ( is_string( $id ) ) {
			$id = trim( $id );
			if ( '' !== $id ) {
				$normalized_id = $id;
			}
		}

		return new self( $normalized_id, $normalized );
	}

	/**
	 * Export breadcrumb items as plain array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'    => $this->id,
			'items' => $this->items,
		);
	}

	/**
	 * Whether breadcrumb has entries.
	 */
	public function is_empty(): bool {
		return empty( $this->items );
	}
}
