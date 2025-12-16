<?php
/**
 * Represents a breadcrumb trail.
 *
 * @package Airygen\Modules\Breadcrumbs\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\Breadcrumbs\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized breadcrumb trail.
 */
final class Trail {

	/**
	 * @var array<int, array{label: string, url: ?string, hide_in_schema: bool}>
	 */
	private array $items;

	/**
	 * Trail constructor.
	 *
	 * @param array<int, array<string, mixed>> $items Raw items.
	 */
	public function __construct( array $items ) {
		$normalized = array();

		foreach ( $items as $item ) {
			if ( empty( $item['label'] ) ) {
				continue;
			}

			$label = (string) $item['label'];

			$url = null;
			if ( isset( $item['url'] ) && is_scalar( $item['url'] ) ) {
				$url = (string) $item['url'];
			}

			$normalized[] = array(
				'label'          => $label,
				'url'            => $url,
				'hide_in_schema' => ! empty( $item['hide_in_schema'] ),
			);
		}

		$this->items = $normalized;
	}

	/**
	 * Determine whether the trail contains items.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->items );
	}

	/**
	 * Retrieve all items.
	 *
	 * @return array<int, array{label: string, url: ?string, hide_in_schema: bool}>
	 */
	public function items(): array {
		return $this->items;
	}

	/**
	 * Export schema-safe items (excluding hidden entries).
	 *
	 * @return array<int, array{name: string, url: string}>
	 */
	public function to_schema_items(): array {
		$schema = array();

		foreach ( $this->items as $item ) {
			if ( $item['hide_in_schema'] ) {
				continue;
			}

			$schema[] = array(
				'name' => $item['label'],
				'url'  => $item['url'] ?? '',
			);
		}

		return $schema;
	}
}
