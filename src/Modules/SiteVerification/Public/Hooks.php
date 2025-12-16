<?php
/**
 * Public hooks for Site Verification.
 *
 * @package Airygen\Modules\SiteVerification\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\SiteVerification\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SiteVerification\Admin\Settings;

/**
 * Registers public runtime hooks.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_head', array( __CLASS__, 'emit_head' ), 26 );
	}

	/**
	 * Emit webmaster verification meta tags.
	 *
	 * @return void
	 */
	public static function emit_head(): void {
		$settings = Settings::get();

		$tag_map = array(
			'google'    => 'google-site-verification',
			'bing'      => 'msvalidate.01',
			'yandex'    => 'yandex-verification',
			'baidu'     => 'baidu-site-verification',
			'pinterest' => 'p:domain_verify',
		);

		foreach ( $tag_map as $key => $meta_name ) {
			$value = isset( $settings[ $key ] ) ? trim( (string) $settings[ $key ] ) : '';
			if ( '' === $value ) {
				continue;
			}

			printf(
				"<meta name=\"%s\" content=\"%s\" />\n",
				esc_attr( $meta_name ),
				esc_attr( $value )
			);
		}
	}
}
