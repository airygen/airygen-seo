<?php
/**
 * Provides read-only access to Image SEO configuration.
 *
 * @package Airygen\Modules\ImageSeo\Settings
 */

declare(strict_types=1);

namespace Airygen\Modules\ImageSeo\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\ImageSeo\Admin\Settings;

/**
 * Lightweight wrapper around the Image SEO option.
 */
final class Repository {

	/**
	 * Cached configuration for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Stored configuration for this repository instance.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * @param array<string, mixed> $config Sanitized config.
	 */
	private function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Build a repository based on the latest stored option.
	 *
	 * @return self
	 */
	public static function from_settings(): self {
		if ( null === self::$cache ) {
			self::$cache = Settings::get();
		}

		return new self( self::$cache );
	}

	/**
	 * Determine whether Image SEO should run at all.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->should_add_alt() || $this->should_add_title();
	}

	/**
	 * Whether the runtime should add missing alt attributes.
	 *
	 * @return bool
	 */
	public function should_add_alt(): bool {
		return ! empty( $this->config['alt']['enabled'] );
	}

	/**
	 * Retrieve the format for generated alt attributes.
	 *
	 * @return string
	 */
	public function alt_format(): string {
		return isset( $this->config['alt']['format'] ) ? (string) $this->config['alt']['format'] : '';
	}

	/**
	 * Whether the runtime should add missing title attributes.
	 *
	 * @return bool
	 */
	public function should_add_title(): bool {
		return ! empty( $this->config['title']['enabled'] );
	}

	/**
	 * Retrieve the format for generated title attributes.
	 *
	 * @return string
	 */
	public function title_format(): string {
		return isset( $this->config['title']['format'] ) ? (string) $this->config['title']['format'] : '';
	}

	/**
	 * Retrieve the separator string for Image SEO templates.
	 *
	 * @return string
	 */
	public function separator(): string {
		return isset( $this->config['separator'] ) ? (string) $this->config['separator'] : '';
	}

	/**
	 * Retrieve custom token values.
	 *
	 * @return array<string, string>
	 */
	public function custom_tokens(): array {
		$tokens = isset( $this->config['custom_tokens'] ) && is_array( $this->config['custom_tokens'] )
		? $this->config['custom_tokens']
		: array();

		return array(
			'custom_1' => isset( $tokens['custom_1'] ) ? (string) $tokens['custom_1'] : '',
			'custom_2' => isset( $tokens['custom_2'] ) ? (string) $tokens['custom_2'] : '',
			'custom_3' => isset( $tokens['custom_3'] ) ? (string) $tokens['custom_3'] : '',
		);
	}
}
