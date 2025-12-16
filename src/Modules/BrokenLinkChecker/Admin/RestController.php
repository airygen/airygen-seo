<?php
/**
 * REST endpoints for Broken Link Checker tooling.
 *
 * @package Airygen\Modules\BrokenLinkChecker\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\BrokenLinkChecker\Admin;

use Airygen\Modules\BrokenLinkChecker\Domain\LogRepository;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin REST endpoints for the Broken Link Checker module.
 */
final class RestController {

	private const PER_PAGE       = 20;
	private const STATUS_FILTERS = array( 'ok', 'redirect', 'error' );

	/**
	 * Permission callback for Broken Link Checker REST endpoints.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle log fetch requests.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public static function handle_logs( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = self::PER_PAGE;
		$statuses = self::normalize_status_filters( (string) $request->get_param( 'statuses' ) );

		$repository  = new LogRepository();
		$logs        = $repository->get_logs( $page, $per_page, $statuses );
		$total       = $repository->count_logs( $statuses );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );

		$normalized = array_map( array( __CLASS__, 'normalize_entry' ), $logs );

		return rest_ensure_response(
			array(
				'logs'       => $normalized,
				'pagination' => array(
					'page'       => $page,
					'perPage'    => $per_page,
					'totalPages' => $total_pages,
					'totalItems' => $total,
				),
			)
		);
	}

	/**
	 * Normalize a single log row for API output.
	 *
	 * @param array<string,mixed> $entry Raw entry.
	 * @return array<string,mixed>
	 */
	private static function normalize_entry( array $entry ): array {
		$post_id    = (int) ( $entry['post_id'] ?? 0 );
		$post       = $post_id > 0 ? get_post( $post_id ) : null;
		$post_title = $post instanceof \WP_Post ? get_the_title( $post ) : '';

		return array(
			'id'           => (int) ( $entry['link_id'] ?? $entry['id'] ?? 0 ),
			'postId'       => $post_id,
			'postTitle'    => $post_title ? $post_title : __( '(no title)', 'airygen-seo' ),
			'postEditLink' => $post_id > 0 ? wp_specialchars_decode( (string) get_edit_post_link( $post_id ) ) : null,
			'postViewLink' => $post_id > 0 ? get_permalink( $post_id ) : null,
			'url'          => (string) ( $entry['url'] ?? '' ),
			'statusCode'   => isset( $entry['status_code'] ) ? (int) $entry['status_code'] : null,
			'statusLabel'  => isset( $entry['status_label'] ) ? (string) $entry['status_label'] : null,
			'errorMessage' => isset( $entry['error_message'] ) ? (string) $entry['error_message'] : null,
			'dataSource'   => isset( $entry['data_source'] ) ? (string) $entry['data_source'] : '',
			'checkedAt'    => self::format_timestamp( (string) ( $entry['checked_at'] ?? '' ) ),
			'createdAt'    => self::format_timestamp( (string) ( $entry['created_at'] ?? '' ) ),
		);
	}

	/**
	 * Format MySQL datetime values to RFC3339 strings.
	 *
	 * @param string $value MySQL datetime string.
	 * @return string|null
	 */
	private static function format_timestamp( string $value ): ?string {
		if ( '' === $value ) {
			return null;
		}

		$timestamp = mysql2date( 'U', $value, false );
		if ( ! $timestamp ) {
			return null;
		}

		return gmdate( 'c', (int) $timestamp );
	}

	/**
	 * Normalize the incoming statuses parameter.
	 *
	 * @param string $raw Raw comma separated list.
	 * @return array<int,string>
	 */
	private static function normalize_status_filters( string $raw ): array {
		$parts = array_filter(
			array_map(
				static function ( string $value ): string {
					return strtolower( trim( $value ) );
				},
				explode( ',', $raw )
			)
		);

		$filtered = array_values(
			array_intersect( self::STATUS_FILTERS, $parts )
		);

		return ! empty( $filtered ) ? $filtered : self::STATUS_FILTERS;
	}
}
