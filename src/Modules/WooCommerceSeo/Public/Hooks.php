<?php
/**
 * Public hooks for WooCommerce SEO.
 *
 * @package Airygen\Modules\WooCommerceSeo\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\WooCommerceSeo\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;

/**
 * Registers WooCommerce SEO runtime hooks.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'airygen_onpage_resolve_meta_payload', array( ProductMetaResolver::class, 'filter_onpage_meta_payload' ), 10, 2 );
		add_filter( 'woocommerce_structured_data_type_for_page', array( __CLASS__, 'filter_woocommerce_structured_data_types' ), 20, 1 );
		add_action( 'wp', array( __CLASS__, 'disable_woocommerce_breadcrumb_schema_action' ), 20 );
	}

	/**
	 * Remove WooCommerce BreadcrumbList output when Airygen Breadcrumbs is enabled.
	 *
	 * @param mixed $types Structured-data types for the current page.
	 *
	 * @return mixed
	 */
	public static function filter_woocommerce_structured_data_types( $types ) {
		if ( ! ModuleSettings::is_enabled( 'breadcrumbs' ) ) {
			return $types;
		}

		if ( ! is_array( $types ) ) {
			return $types;
		}

		$filtered = array();
		foreach ( $types as $type ) {
			if ( ! is_string( $type ) ) {
				$filtered[] = $type;
				continue;
			}

			if ( 'breadcrumblist' === strtolower( trim( $type ) ) ) {
				continue;
			}

			$filtered[] = $type;
		}

		return $filtered;
	}

	/**
	 * Hard fallback: remove WooCommerce breadcrumb schema action when Airygen Breadcrumbs is enabled.
	 *
	 * @return void
	 */
	public static function disable_woocommerce_breadcrumb_schema_action(): void {
		if ( ! ModuleSettings::is_enabled( 'breadcrumbs' ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$woocommerce = WC();
		if ( ! is_object( $woocommerce ) || ! isset( $woocommerce->structured_data ) ) {
			return;
		}

		$structured_data = $woocommerce->structured_data;
		if ( ! is_object( $structured_data ) ) {
			return;
		}

		remove_action( 'woocommerce_breadcrumb', array( $structured_data, 'generate_breadcrumblist_data' ), 10 );
	}
}
