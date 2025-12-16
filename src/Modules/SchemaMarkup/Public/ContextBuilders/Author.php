<?php
/**
 * Builds author schema context for author archive pages.
 *
 * @package Airygen\Modules\SchemaMarkup\Public\ContextBuilders
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Public\ContextBuilders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\AuthorSeo\Admin\Settings as AuthorSeoSettings;
use WP_User;

/**
 * Composes author schema context from current query.
 */
final class Author {

	/**
	 * Build author schema context for current query.
	 *
	 * @return array<string, mixed>
	 */
	public static function from_current_query(): array {
		if ( ! is_author() || ! ModuleSettings::is_enabled( 'authorSeo' ) ) {
			return array();
		}

		$settings = AuthorSeoSettings::get();
		if ( empty( $settings['enabled'] ) ) {
			return array();
		}

		$queried = get_queried_object();
		if ( ! $queried instanceof WP_User ) {
			return array();
		}

		$author_url = get_author_posts_url( (int) $queried->ID, $queried->user_nicename );
		$bio        = (string) get_the_author_meta( 'description', $queried->ID );

		$context = array(
			'@id'         => untrailingslashit( $author_url ) . '#author',
			'@type'       => 'Person',
			'name'        => (string) $queried->display_name,
			'url'         => $author_url,
			'description' => '' !== trim( $bio ) ? trim( $bio ) : null,
		);

		$same_as = self::resolve_social_profiles( (int) $queried->ID, $settings );
		if ( ! empty( $same_as ) ) {
			$context['sameAs'] = $same_as;
		}

		return $context;
	}

	/**
	 * Resolve sameAs URLs.
	 *
	 * @param int                  $user_id  User ID.
	 * @param array<string, mixed> $settings Author SEO settings.
	 *
	 * @return array<int, string>
	 */
	private static function resolve_social_profiles( int $user_id, array $settings ): array {
		$user_meta = get_user_meta( $user_id, Constants::USER_META_SOCIAL_PROFILES, true );
		$profiles  = self::normalize_profiles( $user_meta );
		if ( ! empty( $profiles ) ) {
			return $profiles;
		}

		if ( ! isset( $settings['social_profiles'] ) || ! is_array( $settings['social_profiles'] ) ) {
			return array();
		}

		return self::normalize_profiles( $settings['social_profiles'] );
	}

	/**
	 * Normalize social profile payload.
	 *
	 * @param mixed $profiles Raw profiles.
	 *
	 * @return array<int, string>
	 */
	private static function normalize_profiles( $profiles ): array {
		$values = array();

		if ( is_array( $profiles ) ) {
			foreach ( $profiles as $value ) {
				if ( ! is_string( $value ) ) {
					continue;
				}
				$values[] = $value;
			}
		} elseif ( is_string( $profiles ) ) {
			$split = preg_split( '/[\r\n,]+/', $profiles );
			if ( is_array( $split ) ) {
				$values = $split;
			}
		}

		$result = array();
		foreach ( $values as $value ) {
			$url = esc_url_raw( trim( (string) $value ) );
			if ( '' === $url || in_array( $url, $result, true ) ) {
				continue;
			}
			$result[] = $url;
		}

		return $result;
	}
}
