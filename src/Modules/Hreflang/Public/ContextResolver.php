<?php
/**
 * Resolves context for hreflang alternates.
 *
 * @package Airygen\Modules\Hreflang\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Hreflang\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Hreflang\Admin\Settings;

/**
 * Gathers input for the hreflang domain service.
 */
final class ContextResolver {

	/**
	 * Build context array for current request.
	 *
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$options  = Settings::get();
		$post_id  = is_singular() ? get_queried_object_id() : 0;
		$self_url = self::current_url();

		return array(
			'post_id'           => $post_id,
			'self_url'          => $self_url,
			'manual_map'        => $options['manual_map'] ?? array(),
			'include_x_default' => (bool) ( $options['include_x_default'] ?? true ),
			'integrations'      => self::integrations( $post_id, $self_url ),
		);
	}

	/**
	 * Build list of integration resolvers.
	 *
	 * @param int    $post_id  Current post ID.
	 * @param string $self_url Current URL.
	 *
	 * @return array<int, array<string, callable>>
	 */
	private static function integrations( int $post_id, string $self_url ): array {
		$integrations = array();

		if ( self::is_wpml_active() ) {
			$integrations[] = array(
				'resolver' => static function () use ( $post_id, $self_url ): array {
					return self::resolve_wpml( $post_id, $self_url );
				},
			);
		}

		if ( self::is_polylang_active() ) {
			$integrations[] = array(
				'resolver' => static function () use ( $post_id ): array {
					return self::resolve_polylang( $post_id );
				},
			);
		}

		return $integrations;
	}

	/**
	 * Resolve alternates via WPML.
	 *
	 * @param int    $post_id  Current post ID.
	 * @param string $self_url Current URL.
	 *
	 * @return array<int, array{hreflang: string, url: string}>
	 */
	private static function resolve_wpml( int $post_id, string $self_url ): array {
		if ( ! self::is_wpml_active() ) {
			return array();
		}

		$alternates = array();

		$languages = apply_filters( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party WPML hook.
			'wpml_active_languages', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party WPML hook.
			null,
			array(
				'skip_missing' => 0,
				'orderby'      => 'code',
			)
		);

		if ( empty( $languages ) || ! is_array( $languages ) ) {
			return array();
		}

		foreach ( $languages as $lang ) {
			$code = isset( $lang['language_code'] ) ? strtolower( (string) $lang['language_code'] ) : '';
			if ( '' === $code ) {
				continue;
			}

			$url = '';

			if ( $post_id ) {
				$translated_id = apply_filters( 'wpml_object_id', $post_id, get_post_type( $post_id ), false, $code ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party WPML hook.
				if ( $translated_id ) {
					$permalink = get_permalink( $translated_id );
					if ( $permalink ) {
						$url = $permalink;
					}
				}
			} else {
				$maybe_url = apply_filters( 'wpml_permalink', $self_url, $code ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party WPML hook.
				if ( is_string( $maybe_url ) ) {
					$url = $maybe_url;
				}
			}

			if ( '' === $url ) {
				continue;
			}

			$alternates[] = array(
				'hreflang' => $code,
				'url'      => $url,
			);
		}

		return $alternates;
	}

	/**
	 * Resolve alternates via Polylang.
	 *
	 * @param int $post_id Current post ID.
	 *
	 * @return array<int, array{hreflang: string, url: string}>
	 */
	private static function resolve_polylang( int $post_id ): array {
		if ( ! self::is_polylang_active() ) {
			return array();
		}

		$alternates = array();
		$languages  = function_exists( 'pll_languages_list' ) ? pll_languages_list( array( 'fields' => 'slug' ) ) : array();

		if ( empty( $languages ) || ! is_array( $languages ) ) {
			return array();
		}

		foreach ( $languages as $slug ) {
			$code = strtolower( (string) $slug );
			if ( '' === $code ) {
				continue;
			}

			$url = '';

			if ( $post_id && function_exists( 'pll_get_post' ) ) {
				$translated_id = pll_get_post( $post_id, $code );
				if ( $translated_id ) {
					$permalink = get_permalink( $translated_id );
					if ( $permalink ) {
						$url = $permalink;
					}
				}
			} elseif ( function_exists( 'pll_home_url' ) ) {
				$home = pll_home_url( $code );
				if ( $home ) {
					$url = $home;
				}
			}

			if ( '' === $url && function_exists( 'pll_the_languages' ) ) {
				$raw = pll_the_languages(
					array(
						'raw'        => 1,
						'hide_empty' => 0,
					)
				);
				if ( isset( $raw[ $code ]['url'] ) ) {
					$url = $raw[ $code ]['url'];
				}
			}

			if ( '' === $url ) {
				continue;
			}

			$alternates[] = array(
				'hreflang' => $code,
				'url'      => $url,
			);
		}

		return $alternates;
	}

	/**
	 * Determine if WPML is active.
	 */
	private static function is_wpml_active(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' );
	}

	/**
	 * Determine if Polylang is active.
	 */
	private static function is_polylang_active(): bool {
		return function_exists( 'pll_languages_list' );
	}

	/**
	 * Resolve the current absolute URL.
	 */
	private static function current_url(): string {
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id ) {
				$permalink = get_permalink( $post_id );
				if ( $permalink ) {
					return $permalink;
				}
			}
		}

		$scheme      = is_ssl() ? 'https://' : 'http://';
		$host        = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) ) : '';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '/';

		if ( '' !== $host ) {
			return $scheme . $host . $request_uri;
		}

		return home_url( $request_uri );
	}
}
