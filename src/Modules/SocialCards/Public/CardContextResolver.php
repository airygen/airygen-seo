<?php
/**
 * Resolves shared context for social card emitters.
 *
 * @package Airygen\Modules\SocialCards\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\SocialCards\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\OnPageSeo\Domain\Service\BuildHeadMeta;
use Airygen\Modules\SocialCards\Admin\Settings;
use Airygen\Support\Meta\PostData;

/**
 * Helper for building social card input arrays.
 */
final class CardContextResolver {

	/**
	 * Build the normalized input array required by the domain layer.
	 *
	 * @param int $post_id Post identifier.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_input( int $post_id ): array {
		$meta     = self::resolve_head_meta( $post_id );
		$options  = Settings::get();
		$og       = isset( $options['og'] ) && is_array( $options['og'] ) ? $options['og'] : array();
		$twitter  = isset( $options['twitter'] ) && is_array( $options['twitter'] ) ? $options['twitter'] : array();
		$og_state = ! empty( $og['enabled'] );
		$tw_state = ! empty( $twitter['enabled'] );
		$og_title = $meta['title'];
		$og_desc  = $meta['description'];
		$tw_title = $meta['title'];
		$tw_desc  = $meta['description'];

		$images    = self::resolve_images( $post_id, $og, $twitter, $og_state, $tw_state );
		$site_name = get_bloginfo( 'name' );
		$site_name = is_string( $site_name ) ? $site_name : '';

		return array(
			'og_enabled'      => $og_state,
			'twitter_enabled' => $tw_state,
			'og'              => array(
				'title'               => $og_title,
				'description'         => $og_desc,
				'url'                 => $meta['canonical'],
				'type'                => 'article',
				'site_name'           => $site_name,
				'image'               => $images['og'],
				'fb_app_id'           => $og['fb_app_id'] ?? '',
				'fb_admins'           => $og['fb_admins'] ?? '',
				'publisher_url'       => $og['publisher_url'] ?? '',
				'domain_verification' => $og['domain_verification'] ?? '',
			),
			'twitter'         => array(
				'card_type'      => $twitter['card_type'] ?? 'summary_large_image',
				'title'          => $tw_title,
				'description'    => $tw_desc,
				'url'            => $meta['canonical'],
				'image'          => $images['twitter'],
				'site_handle'    => $twitter['site_handle'] ?? '',
				'creator_handle' => $twitter['creator_handle'] ?? '',
			),
		);
	}

	/**
	 * Resolve head metadata using the OnPage domain service.
	 *
	 * @param int $post_id Post identifier.
	 *
	 * @return array<string, string|null>
	 */
	private static function resolve_head_meta( int $post_id ): array {
		$post_data = PostData::get( $post_id );
		$dto       = BuildHeadMeta::for_post(
			array(
				'meta_title'       => $post_data['title'],
				'meta_description' => $post_data['description'],
				'post_title'       => get_the_title( $post_id ),
				'post_excerpt'     => get_post_field( 'post_excerpt', $post_id ),
				'permalink'        => get_permalink( $post_id ),
				'canonical'        => $post_data['canonical'],
				'robots'           => $post_data['robots'],
			)
		);

		return array(
			'title'       => $dto->get_title(),
			'description' => $dto->get_description(),
			'canonical'   => $dto->get_canonical(),
		);
	}

	/**
	 * Resolve Open Graph and Twitter image payloads.
	 *
	 * @param int   $post_id         Post identifier.
	 * @param array $og              Open Graph settings.
	 * @param array $twitter         Twitter settings.
	 * @param bool  $og_enabled      Whether Open Graph is enabled.
	 * @param bool  $twitter_enabled Whether Twitter cards are enabled.
	 *
	 * @return array{og:?array<string,mixed>,twitter:?array<string,mixed>}
	 */
	private static function resolve_images( int $post_id, array $og, array $twitter, bool $og_enabled, bool $twitter_enabled ): array {
		$custom_filter   = self::resolve_custom_image( $post_id );
		$custom          = $custom_filter;
		$featured        = self::resolve_featured_image( $post_id );
		$og_default      = $og_enabled ? self::resolve_og_default_image( $og ) : null;
		$twitter_default = self::resolve_twitter_default_image( $twitter );

		$og_candidates = array_filter( array( $custom, $featured, $og_default ) );
		$og_image      = self::first_image( $og_candidates );

		$twitter_image = null;
		if ( $twitter_enabled ) {
			$inherit = $og_enabled && ! empty( $twitter['inherit_og_image'] );
			if ( $inherit ) {
				$twitter_image = $og_image;
			} else {
				$candidates   = array();
				$candidates[] = $custom_filter;
				$candidates[] = $featured;
				if ( $og_enabled ) {
					$candidates[] = $og_default;
				}
				$candidates[]  = $twitter_default;
				$twitter_image = self::first_image( array_filter( $candidates ) );
			}
		}

		return array(
			'og'      => $og_image,
			'twitter' => $twitter_image,
		);
	}

	/**
	 * Resolve a custom per-post social image.
	 *
	 * @param int $post_id Post identifier.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function resolve_custom_image( int $post_id ): ?array {
		$custom = apply_filters( Constants::HOOK_SOCIAL_CARDS_POST_IMAGE, null, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		if ( is_array( $custom ) && ! empty( $custom['url'] ) ) {
			return self::format_image_payload( (string) $custom['url'], $custom['width'] ?? null, $custom['height'] ?? null );
		}

		return null;
	}

	/**
	 * Resolve the featured image for the post.
	 *
	 * @param int $post_id Post identifier.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function resolve_featured_image( int $post_id ): ?array {
		$attachment_id = get_post_thumbnail_id( $post_id );
		if ( ! $attachment_id ) {
			return null;
		}

		return self::image_from_attachment( (int) $attachment_id );
	}

	/**
	 * Resolve the Open Graph default image from configuration.
	 *
	 * @param array $og Open Graph settings block.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function resolve_og_default_image( array $og ): ?array {
		$image = self::image_from_configuration( $og );
		if ( ! $image ) {
			return null;
		}

		$width  = isset( $og['image_width'] ) ? absint( $og['image_width'] ) : 0;
		$height = isset( $og['image_height'] ) ? absint( $og['image_height'] ) : 0;

		if ( $width > 0 ) {
			$image['width'] = $width;
		}

		if ( $height > 0 ) {
			$image['height'] = $height;
		}

		return $image;
	}

	/**
	 * Resolve the Twitter default image from configuration.
	 *
	 * @param array $twitter Twitter settings block.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function resolve_twitter_default_image( array $twitter ): ?array {
		return self::image_from_configuration( $twitter );
	}

	/**
	 * Fetch an image from configuration values.
	 *
	 * @param array $config Configuration block containing defaults.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function image_from_configuration( array $config ): ?array {
		if ( ! empty( $config['default_image_id'] ) ) {
			$image = self::image_from_attachment( (int) $config['default_image_id'] );
			if ( $image ) {
				return $image;
			}
		}

		if ( ! empty( $config['default_image_url'] ) ) {
			return self::format_image_payload( (string) $config['default_image_url'], null, null );
		}

		return null;
	}

	/**
	 * Build image payload from an attachment ID.
	 *
	 * @param int $attachment_id Attachment identifier.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function image_from_attachment( int $attachment_id ): ?array {
		$url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! $url ) {
			return null;
		}

		$width  = null;
		$height = null;

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $meta ) ) {
			if ( isset( $meta['width'] ) ) {
				$width = absint( $meta['width'] );
			}
			if ( isset( $meta['height'] ) ) {
				$height = absint( $meta['height'] );
			}
		}

		return self::format_image_payload( $url, $width, $height );
	}

	/**
	 * Format an image payload array.
	 *
	 * @param string   $url    Image URL.
	 * @param int|null $width  Width hint.
	 * @param int|null $height Height hint.
	 *
	 * @return array<string, mixed>
	 */
	private static function format_image_payload( string $url, $width, $height ): array {
		$payload = array( 'url' => trim( $url ) );
		$width   = is_numeric( $width ) ? absint( $width ) : 0;
		$height  = is_numeric( $height ) ? absint( $height ) : 0;

		if ( $width > 0 ) {
			$payload['width'] = $width;
		}

		if ( $height > 0 ) {
			$payload['height'] = $height;
		}

		return $payload;
	}

	/**
	 * Return the first valid image candidate.
	 *
	 * @param array<int, array<string, mixed>|null> $candidates Candidate list.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function first_image( array $candidates ): ?array {
		foreach ( $candidates as $candidate ) {
			if ( is_array( $candidate ) && ! empty( $candidate['url'] ) ) {
				return $candidate;
			}
		}

		return null;
	}
}
