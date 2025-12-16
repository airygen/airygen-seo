<?php
/**
 * Registry for host admin pages and premium extensions.
 *
 * @package Airygen\Admin\Extensions
 */

declare(strict_types=1);

namespace Airygen\Admin\Extensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;

/**
 * Builds the list of host-owned and extension-provided admin pages.
 */
final class AdminPageRegistry {

	/**
	 * Return all enabled admin pages sorted by order.
	 *
	 * @return array<int, array{key:string,slug:string,title:string,capability:string,order:int}>
	 */
	public static function all(): array {
		$pages = array_merge(
			self::core_pages(),
			self::extension_pages()
		);

		usort(
			$pages,
			static function ( array $left, array $right ): int {
				if ( $left['order'] === $right['order'] ) {
					return strcmp( $left['key'], $right['key'] );
				}

				return $left['order'] <=> $right['order'];
			}
		);

		return $pages;
	}

	/**
	 * Return boot-safe page metadata for the admin app.
	 *
	 * @return array<int, array{key:string,slug:string,title:string,order:int}>
	 */
	public static function boot_payload(): array {
		return array_map(
			static fn( array $page ): array => array(
				'key'   => $page['key'],
				'slug'  => $page['slug'],
				'title' => $page['title'],
				'order' => $page['order'],
			),
			self::all()
		);
	}

	/**
	 * Get current page key from an admin hook suffix or page slug.
	 *
	 * @param string $hook Admin hook suffix.
	 *
	 * @return string|null
	 */
	public static function resolve_current_page( string $hook ): ?string {
		foreach ( self::all() as $page ) {
			if ( in_array( $hook, self::screen_ids_for_page( $page ), true ) ) {
				return $page['key'];
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_slug = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( '' === $current_slug ) {
			return null;
		}

		foreach ( self::all() as $page ) {
			if ( $page['slug'] === $current_slug ) {
				return $page['key'];
			}
		}

		return null;
	}

	/**
	 * Return all admin screen IDs covered by the host shell.
	 *
	 * @return array<int, string>
	 */
	public static function screen_ids(): array {
		$ids = array();

		foreach ( self::all() as $page ) {
			foreach ( self::screen_ids_for_page( $page ) as $screen_id ) {
				$ids[] = $screen_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Return all page slugs covered by the host shell.
	 *
	 * @return array<int, string>
	 */
	public static function page_slugs(): array {
		return array_values(
			array_unique(
				array_map(
					static fn( array $page ): string => $page['slug'],
					self::all()
				)
			)
		);
	}

	/**
	 * Return enabled core host pages.
	 *
	 * @return array<int, array{key:string,slug:string,title:string,capability:string,order:int}>
	 */
	private static function core_pages(): array {
		$pages = array(
			array(
				'key'        => 'dashboard',
				'slug'       => 'airygen-dashboard',
				'title'      => __( 'Dashboard', 'airygen-seo' ),
				'capability' => 'manage_options',
				'order'      => 10,
			),
			array(
				'key'        => 'settings',
				'slug'       => 'airygen-settings',
				'title'      => __( 'Settings', 'airygen-seo' ),
				'capability' => 'manage_options',
				'order'      => 20,
			),
			array(
				'key'        => 'topicCluster',
				'slug'       => 'airygen-topic-cluster',
				'title'      => __( 'Topic Cluster', 'airygen-seo' ),
				'capability' => 'manage_options',
				'order'      => 30,
			),
			array(
				'key'        => 'notify',
				'slug'       => 'airygen-notify',
				'title'      => __( 'Alerts', 'airygen-seo' ),
				'capability' => 'manage_options',
				'order'      => 40,
			),
			array(
				'key'        => 'migration',
				'slug'       => 'airygen-migration',
				'title'      => __( 'Migration', 'airygen-seo' ),
				'capability' => 'manage_options',
				'order'      => 50,
			),
		);

		return array_values(
			array_filter(
				$pages,
				static function ( array $page ): bool {
					if ( 'topicCluster' === $page['key'] ) {
						return ModuleSettings::is_enabled( 'topicCluster' );
					}

					if ( 'notify' === $page['key'] ) {
						return ModuleSettings::is_enabled( 'notify' );
					}

					return true;
				}
			)
		);
	}

	/**
	 * Normalize extension pages provided by premium add-ons.
	 *
	 * @return array<int, array{key:string,slug:string,title:string,capability:string,order:int}>
	 */
	private static function extension_pages(): array {
		$pages = apply_filters( Constants::HOOK_ADMIN_PAGES, array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		if ( ! is_array( $pages ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $pages as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}

			$key   = isset( $page['key'] ) && is_string( $page['key'] ) ? trim( $page['key'] ) : '';
			$slug  = isset( $page['slug'] ) && is_string( $page['slug'] ) ? trim( $page['slug'] ) : '';
			$title = isset( $page['title'] ) && is_string( $page['title'] ) ? trim( $page['title'] ) : '';

			if ( '' === $key || '' === $slug || '' === $title ) {
				continue;
			}

			$enabled = ! isset( $page['enabled'] ) || (bool) $page['enabled'];
			if ( ! $enabled ) {
				continue;
			}

			$normalized[] = array(
				'key'        => $key,
				'slug'       => $slug,
				'title'      => $title,
				'capability' => isset( $page['capability'] ) && is_string( $page['capability'] ) && '' !== trim( $page['capability'] )
					? trim( $page['capability'] )
					: 'manage_options',
				'order'      => isset( $page['order'] ) ? (int) $page['order'] : 100,
			);
		}

		return $normalized;
	}

	/**
	 * Build the possible admin screen IDs for a page entry.
	 *
	 * @param array{key:string,slug:string,title:string,capability:string,order:int} $page Page entry.
	 *
	 * @return array<int, string>
	 */
	private static function screen_ids_for_page( array $page ): array {
		if ( 'dashboard' === $page['key'] ) {
			return array(
				'toplevel_page_airygen-dashboard',
				'airygen-dashboard_page_airygen-dashboard',
				'airygen_page_airygen-dashboard',
				'airygen-seo_page_airygen-dashboard',
			);
		}

		return array(
			'airygen-dashboard_page_' . $page['slug'],
			'airygen_page_' . $page['slug'],
			'airygen-seo_page_' . $page['slug'],
		);
	}
}
