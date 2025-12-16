<?php
/**
 * REST controller delivering SEO score calculations.
 *
 * @package Airygen\Modules\ScoreCalculator\Admin\Api
 */

declare(strict_types=1);

namespace Airygen\Modules\ScoreCalculator\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\OnPageSeo\Domain\Service\BuildHeadMeta;
use Airygen\Modules\SchemaMarkup\Admin\Settings as SchemaSettings;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\Article as SchemaArticle;
use Airygen\Modules\SchemaMarkup\Public\ContextBuilders\Organization as SchemaOrganization;
use Airygen\Modules\ScoreCalculator\Admin\RulesProvider;
use Airygen\Modules\ScoreCalculator\Admin\Settings as ScoreSettings;
use Airygen\Modules\ScoreCalculator\Domain\DocumentContext;
use Airygen\Modules\ScoreCalculator\Domain\ScoringEngine;
use Airygen\Modules\ScoreCalculator\Runtime\Hooks as ScoreRuntimeHooks;
use Airygen\Modules\SitewideSeo\Admin\Hooks as SiteHealthHooks;
use Airygen\Modules\SitewideSeo\Domain\Service\Evaluator as SiteHealthEvaluator;
use Airygen\Support\Errors\ErrorCodes;
use Airygen\Support\Meta\PostData;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API endpoint for scoring posts.
 */
final class RestController {

	private const ROUTE_NAMESPACE = 'airygen/v1';
	private const ROUTE_PATH      = '/score';

	/**
	 * Permission callback for score management actions.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Determine if the current user can view the score.
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_view_score( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post' );

		if ( $post_id <= 0 ) {
			return new WP_Error( ErrorCodes::AIRYGEN_INVALID_POST, __( 'Invalid post identifier.', 'airygen-seo' ), array( 'status' => 400 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( ErrorCodes::AIRYGEN_FORBIDDEN, __( 'You are not allowed to score this post.', 'airygen-seo' ), array( 'status' => 403 ) );
		}

		$post_type = (string) get_post_type( $post_id );
		$settings  = ScoreSettings::get();
		$scope     = isset( $settings['postTypes'] ) && is_array( $settings['postTypes'] ) ? $settings['postTypes'] : array();
		if ( '' !== $post_type && ! empty( $scope ) && ! in_array( $post_type, $scope, true ) ) {
			return new WP_Error( ErrorCodes::AIRYGEN_FORBIDDEN, __( 'Score Calculator is disabled for this post type.', 'airygen-seo' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Handle GET score requests.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_get_score( WP_REST_Request $request ) {
		$post_id             = (int) $request->get_param( 'post' );
		$meta_title_px       = $request->get_param( 'meta_title_length_px' );
		$meta_description_px = $request->get_param( 'meta_description_length_px' );

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( ErrorCodes::AIRYGEN_POST_NOT_FOUND, __( 'Post not found.', 'airygen-seo' ), array( 'status' => 404 ) );
		}

		$response = self::calculate_score_for_post( $post, $meta_title_px, $meta_description_px );

		return rest_ensure_response( $response );
	}

	/**
	 * Start REST-polled recalculation for currently scoped post types.
	 *
	 * @param WP_REST_Request $request Request payload.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_recalculate( WP_REST_Request $request ): WP_REST_Response {
		$settings     = ScoreSettings::get();
		$stored_scope = isset( $settings['postTypes'] ) && is_array( $settings['postTypes'] )
		? array_values( array_filter( array_map( 'strval', $settings['postTypes'] ) ) )
		: array();

		$requested  = $request->get_param( 'postTypes' );
		$post_types = is_array( $requested )
		? array_values( array_filter( array_map( 'strval', $requested ) ) )
		: $stored_scope;

		return rest_ensure_response(
			array(
				'status' => ScoreRuntimeHooks::start_recalculation( $post_types ),
			)
		);
	}

	/**
	 * Process next post in REST-polled recalculation queue.
	 *
	 * @param WP_REST_Request $request Request payload.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_recalculate_step( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		return rest_ensure_response(
			array(
				'status' => ScoreRuntimeHooks::process_next(),
			)
		);
	}

	/**
	 * Return queue status for score recalculation.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_recalculate_status(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'status' => ScoreRuntimeHooks::get_status(),
			)
		);
	}

	/**
	 * Provide configuration for the editor script.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_editor_config(): array {
		$spec = ScoreSettings::apply_overrides( RulesProvider::get() );

		return array(
			'root'     => rest_url( self::ROUTE_NAMESPACE . self::ROUTE_PATH ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'method'   => 'GET',
			'version'  => (string) ( $spec['version'] ?? 'unknown' ),
			'pack'     => (string) ( $spec['pack'] ?? 'airygen-score-pack' ),
			'language' => (string) ( $spec['language'] ?? 'auto' ),
		);
	}

	/**
	 * Calculate score payload for a post and persist score cache.
	 *
	 * @param WP_Post    $post                Post object.
	 * @param float|int  $meta_title_px       Optional title pixel override.
	 * @param float|int  $meta_description_px Optional description pixel override.
	 *
	 * @return array<string, mixed>
	 */
	public static function calculate_score_for_post( WP_Post $post, $meta_title_px = null, $meta_description_px = null ): array {
		$post_id = (int) $post->ID;
		$spec    = ScoreSettings::apply_overrides( RulesProvider::get() );
		$engine  = new ScoringEngine( $spec );
		$context = self::build_document_context( $post, $meta_title_px, $meta_description_px );
		$result  = $engine->score( $context );

		$response = self::round_floats(
			array(
				'post_id'  => $post_id,
				'pack'     => $result['pack'],
				'version'  => $result['version'],
				'language' => $result['language'],
				'base'     => $result['base'],
				'bonus'    => $result['bonus'],
				'total'    => $result['total'],
			)
		);

		self::persist_score_meta( $post_id, $response );
		return $response;
	}

	/**
	 * Build the DocumentContext from a WP_Post instance.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return DocumentContext
	 */
	private static function build_document_context( WP_Post $post, $meta_title_px = null, $meta_description_px = null ): DocumentContext {
		$post_id          = $post->ID;
		$post_data        = PostData::get( $post_id );
		$meta_title       = $post_data['title'];
		$meta_description = $post_data['description'];
		$canonical        = $post_data['canonical'];
		$permalink        = (string) get_permalink( $post_id );

		$head_meta = BuildHeadMeta::for_post(
			array(
				'meta_title'       => $meta_title,
				'meta_description' => $meta_description,
				'post_title'       => get_the_title( $post_id ),
				'post_excerpt'     => get_post_field( 'post_excerpt', $post_id ),
				'permalink'        => $permalink,
				'canonical'        => $canonical,
				'robots'           => $post_data['robots'],
			)
		);

		$content_raw = (string) get_post_field( 'post_content', $post_id );
		$content     = apply_filters( 'the_content', $content_raw ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
		$site_host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$focus       = $post_data['focusKeyphrase'];
		$long_tail   = $post_data['focusLongTail'];

		$resolved_title = is_string( $meta_title ) ? trim( $meta_title ) : '';
		if ( '' === $resolved_title ) {
			$resolved_title = (string) ( get_the_title( $post_id ) ?? '' );
		}

		$context_data = array(
			'title'                      => $resolved_title,
			'description'                => $head_meta->get_description() ?? '',
			'content'                    => $content,
			'focus_keyphrase'            => $focus,
			'long_tail_keyphrases'       => $long_tail,
			'slug'                       => (string) get_post_field( 'post_name', $post_id ),
			'canonical'                  => $head_meta->get_canonical() ?? $canonical,
			'permalink'                  => $permalink,
			'site_host'                  => $site_host,
			'site_health_score'          => self::calculate_site_health_score(),
			'jsonld_article_present'     => self::resolve_schema_article_present( $post ),
			'jsonld_breadcrumb_present'  => self::resolve_schema_breadcrumb_present( $post ),
			'meta_title_length_px'       => is_numeric( $meta_title_px ) ? (float) $meta_title_px : null,
			'meta_description_length_px' => is_numeric( $meta_description_px ) ? (float) $meta_description_px : null,
		);

		$context_data = apply_filters( Constants::HOOK_SCORE_CONTEXT_DATA, $context_data, $post ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		return DocumentContext::from_array( $context_data );
	}

	/**
	 * Persist latest calculated score into post meta.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $response Score payload.
	 *
	 * @return void
	 */
	private static function persist_score_meta( int $post_id, array $response ): void {
		$total = isset( $response['total'] ) && is_array( $response['total'] ) ? $response['total'] : array();
		$score = isset( $total['score'] ) ? (float) $total['score'] : 0.0;
		$max   = isset( $total['max'] ) ? (float) $total['max'] : 0.0;
		$cache = array(
			'score'      => $score,
			'max'        => $max,
			'updated_at' => gmdate( 'c' ),
		);

		update_post_meta( $post_id, Constants::META_SCORE_CACHE, $cache );
	}

	/**
	 * Compute Site Health score ratio (passing diagnostics / eligible diagnostics).
	 */
	private static function calculate_site_health_score(): float {
		if ( ! class_exists( SiteHealthHooks::class ) || ! class_exists( SiteHealthEvaluator::class ) ) {
			return 0.0;
		}

		$results = SiteHealthHooks::get_results();
		if ( empty( $results ) || ! is_array( $results ) ) {
			return 0.0;
		}

		$excluded = array( 'score_rest', 'search_console' );
		$total    = 0;
		$good     = 0;

		foreach ( $results as $slug => $result ) {
			$slug = (string) $slug;
			if ( $slug && in_array( $slug, $excluded, true ) ) {
				continue;
			}

			if ( isset( $result['scoreEligible'] ) && false === $result['scoreEligible'] ) {
				continue;
			}

			++$total;
			$status = is_array( $result ) && isset( $result['status'] ) ? (string) $result['status'] : '';
			if ( SiteHealthEvaluator::STATUS_GOOD === $status ) {
				++$good;
			}
		}

		if ( 0 === $total ) {
			return 0.0;
		}

		return max( 0.0, min( 1.0, $good / $total ) );
	}

	/**
	 * Determine whether Schema Markup will output Article JSON-LD for the post.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return bool
	 */
	private static function resolve_schema_article_present( WP_Post $post ): bool {
		if ( ! class_exists( SchemaSettings::class ) || ! class_exists( SchemaArticle::class ) ) {
			return false;
		}

		if ( ! class_exists( ModuleSettings::class ) || ! ModuleSettings::is_enabled( 'schema' ) ) {
			return false;
		}

		$options    = SchemaSettings::get();
		$visibility = self::resolve_schema_visibility( $options );

		if ( empty( $visibility['article'] ) ) {
			return false;
		}

		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$site_url  = home_url( '/' );

		$organization_context = SchemaOrganization::build( $options, $site_name, $site_url );
		if ( null === $organization_context ) {
			return false;
		}

		$article_context = SchemaArticle::from_post_id(
			$post->ID,
			$options,
			$site_name,
			$site_desc,
			$organization_context
		);

		return null !== $article_context;
	}

	/**
	 * Determine whether Schema Markup will output Breadcrumb JSON-LD for the post.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return bool
	 */
	private static function resolve_schema_breadcrumb_present( WP_Post $post ): bool {
		if ( ! class_exists( SchemaSettings::class ) || ! class_exists( ModuleSettings::class ) ) {
			return false;
		}

		if ( ! ModuleSettings::is_enabled( 'schema' ) ) {
			return false;
		}

		$options    = SchemaSettings::get();
		$visibility = self::resolve_schema_visibility( $options );

		if ( empty( $visibility['breadcrumb'] ) ) {
			return false;
		}

		$front_page_id = (int) get_option( 'page_on_front', 0 );
		if ( 'page' === (string) get_option( 'show_on_front', 'posts' ) && $front_page_id > 0 ) {
			if ( $front_page_id === (int) $post->ID ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolve Schema Markup visibility configuration.
	 *
	 * @param array<string, mixed> $options Schema options.
	 *
	 * @return array<string, bool>
	 */
	private static function resolve_schema_visibility( array $options ): array {
		$defaults = array(
			'organization' => false,
			'website'      => false,
			'breadcrumb'   => false,
			'article'      => false,
		);

		if ( empty( $options['visibility'] ) || ! is_array( $options['visibility'] ) ) {
			return $defaults;
		}

		return array(
			'organization' => ! empty( $options['visibility']['organization'] ),
			'website'      => ! empty( $options['visibility']['website'] ),
			'breadcrumb'   => ! empty( $options['visibility']['breadcrumb'] ),
			'article'      => ! empty( $options['visibility']['article'] ),
		);
	}

	/**
	 * Round any float values in a mixed payload to two decimal places.
	 *
	 * @param mixed $value Payload to normalize.
	 * @return mixed
	 */
	private static function round_floats( $value ) {
		if ( is_array( $value ) ) {
			$normalized = array();
			foreach ( $value as $key => $item ) {
				$normalized[ $key ] = self::round_floats( $item );
			}
			return $normalized;
		}

		if ( is_float( $value ) ) {
			return round( $value, 2 );
		}

		return $value;
	}
}
