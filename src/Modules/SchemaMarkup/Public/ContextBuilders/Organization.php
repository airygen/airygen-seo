<?php
/**
 * WP-aware builder for organization schema context.
 *
 * @package Airygen\Modules\SchemaMarkup\Public\ContextBuilders
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Public\ContextBuilders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SchemaMarkup\Domain\Contexts\OrganizationContext;

/**
 * Builds organization context from WordPress settings.
 */
final class Organization {

	/**
	 * Build organization context from settings/options.
	 *
	 * @param array<string, mixed> $options   Schema settings.
	 * @param string               $site_name Site name fallback.
	 * @param string               $site_url  Canonical site URL.
	 */
	public static function build( array $options, string $site_name, string $site_url ): OrganizationContext {
		$name = self::resolve_name( $options, $site_name );
		$type = self::resolve_type( $options );
		$logo = self::resolve_logo( $options );

		return OrganizationContext::from_values( $name, $type, $site_url, $logo );
	}

	/**
	 * Resolve organization display name.
	 *
	 * @param array<string, mixed> $options  Schema options.
	 * @param string               $fallback Fallback name.
	 */
	private static function resolve_name( array $options, string $fallback ): string {
		if ( isset( $options['organization_name'] ) ) {
			$name = trim( (string) $options['organization_name'] );
			if ( '' !== $name ) {
				return $name;
			}
		}

		return $fallback;
	}

	/**
	 * Resolve organization schema type.
	 *
	 * @param array<string, mixed> $options Schema options.
	 */
	private static function resolve_type( array $options ): string {
		if ( isset( $options['organization_type'] ) ) {
			$type = trim( (string) $options['organization_type'] );
			if ( '' !== $type ) {
				return $type;
			}
		}

		return 'Organization';
	}

	/**
	 * Resolve organization logo URL.
	 *
	 * @param array<string, mixed> $options Schema options.
	 */
	private static function resolve_logo( array $options ): ?string {
		if ( ! empty( $options['organization_logo_url'] ) ) {
			$logo = trim( (string) $options['organization_logo_url'] );
			if ( '' !== $logo ) {
				return $logo;
			}
		}

		$logo_id = isset( $options['organization_logo_id'] ) ? (int) $options['organization_logo_id'] : 0;
		if ( $logo_id > 0 ) {
			$attachment = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $attachment ) {
				return $attachment;
			}
		}

		return null;
	}
}
