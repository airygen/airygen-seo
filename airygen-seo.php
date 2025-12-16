<?php
/**
 * Plugin Name: Airygen SEO
 * Plugin URI:  https://www.airygen.com/en/airygen-seo/introduction
 * Description: Airygen SEO is a modular SEO toolkit for WordPress sites that need more than title and meta fields. It combines on-page SEO controls, structured data, technical SEO, internal link workflows, and automation tools in one plugin while keeping the editing experience inside WordPress.
 * Version:     0.0.0
 * Author:      Airygen Team
 * Author URI:  https://www.airygen.com/
 * License:     GPL 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: airygen-seo
 * Domain Path: /languages
 *
 * @package airygen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	$airygen_as_bootstrap = __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
	if ( is_readable( $airygen_as_bootstrap ) ) {
		require_once $airygen_as_bootstrap;
	}
}

define( 'AIRYGEN_PLUGIN_FILE', __FILE__ );
define( 'AIRYGEN_PLUGIN_DIR', __DIR__ );
define( 'AIRYGEN_VERSION', '0.0.0' );

new \Airygen\Admin\Activation( AIRYGEN_PLUGIN_FILE );


add_action(
	'plugins_loaded',
	static function (): void {
		\Airygen\Launcher::boot();
	}
);
