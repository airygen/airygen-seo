<?php
/**
 * This class is responsible for loading necessary assets.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Airygen\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset controller.
 */
class Assets {

	/**
	 * The plugin's base URL.
	 *
	 * @var string
	 */
	public $base_url = '';

	/**
	 * The plugin's asset version.
	 *
	 * @var string
	 */
	public $asset_version = '';

	/**
	 * Constructor.
	 *
	 * @param string $base_url      The plugin's base URL.
	 * @param string $asset_version The plugin's asset version.
	 */
	public function __construct( string $base_url, string $asset_version ) {
		$this->base_url      = $base_url;
		$this->asset_version = $asset_version;

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue the stylesheets.
	 *
	 * @param string $hook The current page hook suffix.
	 * @return void
	 */
	public function enqueue_styles( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_register_style(
			'airygen_admin_css',
			$this->base_url . 'resources/assets/css/style.css',
			false,
			$this->asset_version
		);

		wp_enqueue_style( 'airygen_admin_css' );
	}

	/**
	 * Enqueue the scripts.
	 *
	 * @param string $hook The current page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_register_script(
			'airygen_admin_js',
			$this->base_url . 'resources/assets/js/metabox.js',
			array(),
			$this->asset_version,
			true
		);

		wp_enqueue_script( 'airygen_admin_js' );
	}
}
