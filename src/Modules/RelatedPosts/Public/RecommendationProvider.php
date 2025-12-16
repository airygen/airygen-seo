<?php
/**
 * Recommendation provider for Related Posts.
 *
 * @package Airygen\Modules\RelatedPosts\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\RelatedPosts\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\LinkSuggestions\Admin\Settings as LinkSuggestionsSettings;
use Airygen\Modules\LinkSuggestions\Application\RecommendationService;
use Airygen\Modules\LinkSuggestions\Domain\SimilarityScorer;
use Airygen\Modules\LinkSuggestions\Persistence\LinkTermsRepository;

/**
 * Reads top related post IDs from Link Suggestions terms index.
 */
final class RecommendationProvider {

	/**
	 * @param int $post_id Current post ID.
	 * @param int $limit Max result count.
	 *
	 * @return array<int,int>
	 */
	public static function top_related_post_ids( int $post_id, int $limit ): array {
		if ( $post_id <= 0 || $limit <= 0 ) {
			return array();
		}

		$current_post = get_post( $post_id );
		if ( ! $current_post ) {
			return array();
		}

		$current_type = get_post_type( $current_post );
		if ( ! is_string( $current_type ) || '' === $current_type ) {
			return array();
		}

		$link_settings = LinkSuggestionsSettings::get();
		if ( empty( $link_settings['enabled'] ) ) {
			return array();
		}

		$allowed_types = isset( $link_settings['allowed_post_types'] ) && is_array( $link_settings['allowed_post_types'] )
		? array_values( array_filter( array_map( 'strval', $link_settings['allowed_post_types'] ) ) )
		: array();
		if ( empty( $allowed_types ) ) {
			$allowed_types = get_post_types( array( 'public' => true ), 'names' );
		}
		if ( empty( $allowed_types ) || ! in_array( $current_type, $allowed_types, true ) ) {
			return array();
		}

		$repository    = new LinkTermsRepository();
		$request_terms = $repository->get_terms_for_content( $post_id, $current_type );
		if ( empty( $request_terms ) ) {
			return array();
		}

		$public_statuses = get_post_stati( array( 'public' => true ), 'names' );

		$candidates = $repository->find_candidate_ids_by_stems(
			array_keys( $request_terms ),
			$allowed_types,
			$public_statuses,
			1000
		);
		if ( empty( $candidates ) ) {
			return array();
		}

		$service = new RecommendationService( $repository, new SimilarityScorer() );
		$scores  = $service->recommend(
			$post_id,
			$current_type,
			$request_terms,
			$candidates,
			$current_type,
			array( $post_id )
		);
		if ( empty( $scores ) ) {
			return array();
		}

		$ids = array_map(
			static function ( $id ): int {
				return (int) $id;
			},
			array_keys( $scores )
		);

		return array_slice( $ids, 0, $limit );
	}
}
