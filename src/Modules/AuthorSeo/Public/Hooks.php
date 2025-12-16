<?php
/**
 * Public hooks for Author SEO output.
 *
 * @package Airygen\Modules\AuthorSeo\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\AuthorSeo\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\AuthorSeo\Admin\Settings;
use WP_User;

/**
 * Handles author archive SEO output.
 */
final class Hooks {
	/**
	 * User meta key for author-specific social profiles.
	 */
	private const USER_META_SOCIAL_PROFILES = Constants::USER_META_SOCIAL_PROFILES;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 30 );
		add_action( 'wp_head', array( __CLASS__, 'emit_head' ), 18 );
	}

	/**
	 * Filter author archive document title.
	 *
	 * @param string $title Existing title.
	 *
	 * @return string
	 */
	public static function filter_document_title( string $title ): string {
		if ( ! is_author() ) {
			return $title;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return $title;
		}

		$user = self::queried_user();
		if ( ! $user instanceof WP_User ) {
			return $title;
		}

		$resolved = self::resolve_title( $settings, $user );
		return '' !== $resolved ? $resolved : $title;
	}

	/**
	 * Emit author archive head tags.
	 *
	 * @return void
	 */
	public static function emit_head(): void {
		if ( ! is_author() ) {
			return;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$user = self::queried_user();
		if ( ! $user instanceof WP_User ) {
			return;
		}

		if ( ! current_theme_supports( 'title-tag' ) ) {
			$title = self::resolve_title( $settings, $user );
			if ( '' !== $title ) {
				printf( "<title>%s</title>\n", esc_html( $title ) );
			}
		}

		$description = self::resolve_description( $settings, $user );
		if ( '' !== $description ) {
			printf(
				"<meta name=\"description\" content=\"%s\" />\n",
				esc_attr( $description )
			);
		}

		$canonical = get_author_posts_url( (int) $user->ID, $user->user_nicename );
		if ( '' !== $canonical ) {
			printf(
				"<link rel=\"canonical\" href=\"%s\" />\n",
				esc_url( $canonical )
			);
		}

		if ( ModuleSettings::is_enabled( 'schema' ) ) {
			return;
		}

		$social = self::resolve_social_profiles( $settings, $user );

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Person',
			'@id'         => untrailingslashit( $canonical ) . '#author',
			'name'        => $user->display_name,
			'url'         => $canonical,
			'description' => $description,
		);

		if ( ! empty( $social ) ) {
			$schema['sameAs'] = $social;
		}

		$json = wp_json_encode( $schema );
		if ( false === $json ) {
			return;
		}

		wp_print_inline_script_tag( $json, array( 'type' => 'application/ld+json' ) );
	}

	/**
	 * Resolve current queried user object.
	 *
	 * @return WP_User|null
	 */
	private static function queried_user(): ?WP_User {
		$object = get_queried_object();
		if ( $object instanceof WP_User ) {
			return $object;
		}

		return null;
	}

	/**
	 * Resolve author page title from template.
	 *
	 * @param array<string, mixed> $settings Module settings.
	 * @param WP_User              $user     Queried user.
	 *
	 * @return string
	 */
	private static function resolve_title( array $settings, WP_User $user ): string {
		$template = isset( $settings['title_template'] ) ? (string) $settings['title_template'] : '';
		return self::render_template( $template, $user, $settings );
	}

	/**
	 * Resolve author page description from template.
	 *
	 * @param array<string, mixed> $settings Module settings.
	 * @param WP_User              $user     Queried user.
	 *
	 * @return string
	 */
	private static function resolve_description( array $settings, WP_User $user ): string {
		$template = isset( $settings['description_template'] ) ? (string) $settings['description_template'] : '';
		return self::render_template( $template, $user, $settings );
	}

	/**
	 * Render simple author tokens.
	 *
	 * @param string              $template Template value.
	 * @param WP_User             $user     Queried user.
	 * @param array<string, mixed> $settings Module settings.
	 *
	 * @return string
	 */
	private static function render_template( string $template, WP_User $user, array $settings ): string {
		$site_name  = (string) get_bloginfo( 'name' );
		$author_bio = (string) get_the_author_meta( 'description', $user->ID );
		$separator  = '';

		if ( isset( $settings['separator'] ) && is_string( $settings['separator'] ) ) {
			$trimmed = trim( $settings['separator'] );
			if ( '' !== $trimmed ) {
				$separator = ' ' . $trimmed . ' ';
			}
		}

		$custom_tokens = array(
			'custom1' => '',
			'custom2' => '',
			'custom3' => '',
		);

		if ( isset( $settings['custom_tokens'] ) && is_array( $settings['custom_tokens'] ) ) {
			if ( isset( $settings['custom_tokens']['custom1'] ) && is_string( $settings['custom_tokens']['custom1'] ) ) {
				$custom_tokens['custom1'] = $settings['custom_tokens']['custom1'];
			}
			if ( isset( $settings['custom_tokens']['custom2'] ) && is_string( $settings['custom_tokens']['custom2'] ) ) {
				$custom_tokens['custom2'] = $settings['custom_tokens']['custom2'];
			}
			if ( isset( $settings['custom_tokens']['custom3'] ) && is_string( $settings['custom_tokens']['custom3'] ) ) {
				$custom_tokens['custom3'] = $settings['custom_tokens']['custom3'];
			}
		}

		$replacements = array(
			'%author_name%' => $user->display_name,
			'%site_name%'   => $site_name,
			'%author_bio%'  => $author_bio,
			'%separator%'   => $separator,
			'%custom_1%'    => $custom_tokens['custom1'],
			'%custom_2%'    => $custom_tokens['custom2'],
			'%custom_3%'    => $custom_tokens['custom3'],
		);

		$output = strtr( $template, $replacements );
		return trim( wp_strip_all_tags( $output ) );
	}

	/**
	 * Resolve schema social profiles, with user meta taking precedence.
	 *
	 * @param array<string, mixed> $settings Module settings.
	 * @param WP_User              $user     Queried user.
	 *
	 * @return array<int, string>
	 */
	private static function resolve_social_profiles( array $settings, WP_User $user ): array {
		$author_profiles = self::normalize_social_profiles(
			get_user_meta( (int) $user->ID, self::USER_META_SOCIAL_PROFILES, true )
		);
		if ( ! empty( $author_profiles ) ) {
			return $author_profiles;
		}

		if ( ! isset( $settings['social_profiles'] ) || ! is_array( $settings['social_profiles'] ) ) {
			return array();
		}

		return self::normalize_social_profiles( $settings['social_profiles'] );
	}

	/**
	 * Normalize social profile payload into cleaned URL list.
	 *
	 * @param mixed $profiles Raw social profiles value.
	 *
	 * @return array<int, string>
	 */
	private static function normalize_social_profiles( $profiles ): array {
		$values = array();

		if ( is_array( $profiles ) ) {
			foreach ( $profiles as $profile ) {
				if ( ! is_string( $profile ) ) {
					continue;
				}
				$values[] = $profile;
			}
		} elseif ( is_string( $profiles ) ) {
			$split  = preg_split( '/[\r\n,]+/', $profiles );
			$values = false !== $split ? $split : array();
		}

		$normalized = array();
		foreach ( $values as $value ) {
			$url = esc_url_raw( trim( (string) $value ) );
			if ( '' === $url ) {
				continue;
			}
			if ( in_array( $url, $normalized, true ) ) {
				continue;
			}
			$normalized[] = $url;
		}

		return $normalized;
	}
}
