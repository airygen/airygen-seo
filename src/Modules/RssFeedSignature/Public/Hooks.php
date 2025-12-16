<?php
/**
 * Public hooks for RSS Feed Signature.
 *
 * @package Airygen\Modules\RssFeedSignature\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\RssFeedSignature\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\RssFeedSignature\Admin\Settings;

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
		add_filter( 'the_content_feed', array( __CLASS__, 'filter_feed_content' ), 20, 2 );
		add_filter( 'the_excerpt_rss', array( __CLASS__, 'filter_feed_content' ), 20, 2 );
	}

	/**
	 * Inject configured signatures around feed content.
	 *
	 * @param mixed       $content Feed content.
	 * @param string|null $_feed_type Feed type.
	 *
	 * @return mixed
	 */
	public static function filter_feed_content( $content, ?string $_feed_type = null ) {
		unset( $_feed_type );

		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return $content;
		}

		$before = isset( $settings['before_content'] ) ? trim( (string) $settings['before_content'] ) : '';
		$after  = isset( $settings['after_content'] ) ? trim( (string) $settings['after_content'] ) : '';

		if ( '' === $before && '' === $after ) {
			return $content;
		}

		$parts = array();
		if ( '' !== $before ) {
			$parts[] = $before;
		}

		$parts[] = $content;

		if ( '' !== $after ) {
			$parts[] = $after;
		}

		return implode( "\n\n", $parts );
	}
}
