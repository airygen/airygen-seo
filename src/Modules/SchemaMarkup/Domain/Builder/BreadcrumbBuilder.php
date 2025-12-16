<?php
/**
 * Builds BreadcrumbList schema nodes.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Builder
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds breadcrumb schema data.
 */
final class BreadcrumbBuilder {

	/**
	 * Build breadcrumb schema node.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $data Breadcrumb data.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function build( array $data ): ?array {
		$items = $data;
		$id    = null;
		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			$items = $data['items'];
			$id    = self::string_or_null( $data['id'] ?? null );
		}

		$list_items = array();
		$position   = 1;

		foreach ( $items as $item ) {
			$name = self::string_or_null( $item['name'] ?? null );
			$url  = self::string_or_null( $item['url'] ?? null );

			if ( null === $name ) {
				continue;
			}

			$list_item = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $name,
			);

			if ( null !== $url ) {
				$list_item['item'] = $url;
			}

			$list_items[] = $list_item;
			++$position;
		}

		if ( empty( $list_items ) ) {
			return null;
		}

		$node = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list_items,
		);
		if ( null !== $id ) {
			$node['@id'] = $id;
		}

		return $node;
	}

	/**
	 * Normalize string input.
	 *
	 * @param mixed $value Arbitrary value.
	 *
	 * @return string|null
	 */
	private static function string_or_null( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_scalar( $value ) ) {
			$value = trim( (string) $value );
			return '' === $value ? null : $value;
		}

		return null;
	}
}
