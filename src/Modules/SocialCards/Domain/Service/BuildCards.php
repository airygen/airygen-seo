<?php
/**
 * Domain service generating social card DTOs.
 *
 * @package Airygen\Modules\SocialCards\Domain\Service
 */

declare(strict_types=1);

namespace Airygen\Modules\SocialCards\Domain\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SocialCards\Domain\Dto\Cards;
use Airygen\Modules\SocialCards\Domain\Dto\OpenGraphCard;
use Airygen\Modules\SocialCards\Domain\Dto\TwitterCard;

/**
 * Builds social card DTOs from normalized input.
 */
final class BuildCards {

	/**
	 * Construct cards for a post entity.
	 *
	 * @param array<string, mixed> $input Normalized inputs.
	 *
	 * @return Cards
	 */
	public static function for_post( array $input ): Cards {
		$og_enabled      = ! empty( $input['og_enabled'] );
		$twitter_enabled = ! empty( $input['twitter_enabled'] );

		$og_input      = is_array( $input['og'] ?? null ) ? $input['og'] : array();
		$twitter_input = is_array( $input['twitter'] ?? null ) ? $input['twitter'] : array();

		$open_graph = null;
		if ( $og_enabled ) {
			$og_image   = is_array( $og_input['image'] ?? null ) ? $og_input['image'] : array();
			$open_graph = new OpenGraphCard(
				self::string_or_null( $og_input['title'] ?? null ),
				self::string_or_null( $og_input['description'] ?? null ),
				self::string_or_null( $og_input['url'] ?? null ),
				self::string_or_null( $og_input['type'] ?? 'article' ) ?? 'article',
				self::string_or_null( $og_image['url'] ?? null ),
				self::int_or_null( $og_image['width'] ?? null ),
				self::int_or_null( $og_image['height'] ?? null ),
				self::string_or_null( $og_input['site_name'] ?? null ),
				self::string_or_null( $og_input['fb_app_id'] ?? null ),
				self::string_or_null( $og_input['fb_admins'] ?? null ),
				self::string_or_null( $og_input['publisher_url'] ?? null ),
				self::string_or_null( $og_input['domain_verification'] ?? null )
			);
		}

		$twitter = null;
		if ( $twitter_enabled ) {
			$twitter_image     = is_array( $twitter_input['image'] ?? null ) ? $twitter_input['image'] : array();
			$twitter_url       = self::string_or_null( $twitter_input['url'] ?? $og_input['url'] ?? null );
			$card_type         = self::string_or_null( $twitter_input['card_type'] ?? 'summary_large_image' ) ?? 'summary_large_image';
			$twitter_image_url = self::string_or_null( $twitter_image['url'] ?? null );

			if ( 'summary_large_image' === $card_type && null === $twitter_image_url ) {
				$card_type = 'summary';
			}

			$twitter = new TwitterCard(
				$card_type,
				self::string_or_null( $twitter_input['title'] ?? $og_input['title'] ?? null ),
				self::string_or_null( $twitter_input['description'] ?? $og_input['description'] ?? null ),
				$twitter_url,
				$twitter_image_url,
				self::string_or_null( $twitter_input['site_handle'] ?? null ),
				self::string_or_null( $twitter_input['creator_handle'] ?? null )
			);
		}

		return new Cards( $open_graph, $twitter );
	}

	/**
	 * Normalize arbitrary input as nullable string.
	 *
	 * @param mixed $value Arbitrary input.
	 *
	 * @return string|null
	 */
	private static function string_or_null( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_scalar( $value ) ) {
			$normalized = trim( (string) $value );
			return '' === $normalized ? null : $normalized;
		}

		return null;
	}

	/**
	 * Normalize arbitrary input as nullable integer.
	 *
	 * @param mixed $value Arbitrary input.
	 *
	 * @return int|null
	 */
	private static function int_or_null( $value ): ?int {
		if ( null === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			$int = absint( $value );
			return $int > 0 ? $int : null;
		}

		return null;
	}
}
