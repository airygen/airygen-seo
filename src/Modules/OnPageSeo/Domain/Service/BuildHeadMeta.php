<?php
/**
 * Domain service responsible for computing head metadata inputs.
 *
 * @package Airygen\Modules\OnPageSeo\Domain\Service
 */

declare(strict_types=1);

namespace Airygen\Modules\OnPageSeo\Domain\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\OnPageSeo\Domain\Dto\HeadMeta;

/**
 * Provides deterministic head metadata composition.
 */
final class BuildHeadMeta {

	/**
	 * Compute metadata for a singular post.
	 *
	 * @param array<string, mixed> $input Input payload.
	 */
	public static function for_post( array $input ): HeadMeta {
		$post_title       = self::string_or_null( $input['post_title'] ?? null );
		$post_excerpt     = self::string_or_null( $input['post_excerpt'] ?? null );
		$permalink        = self::string_or_null( $input['permalink'] ?? null );
		$meta_title       = self::string_or_null( $input['meta_title'] ?? null );
		$meta_description = self::string_or_null( $input['meta_description'] ?? null );
		$canonical        = self::string_or_null( $input['canonical'] ?? null );
		$robots           = self::string_or_null( $input['robots'] ?? null );
		$post_type        = self::string_or_null( $input['post_type'] ?? null );

		$templates = is_array( $input['templates'] ?? null ) ? $input['templates'] : array();

		$title       = $meta_title ?? self::render_template(
			self::select_template( $templates, $post_type, 'title' ),
			self::template_context( $input )
		) ?? $post_title ?? null;
		$description = $meta_description ?? self::render_template(
			self::select_template( $templates, $post_type, 'description' ),
			self::template_context( $input )
		) ?? $post_excerpt ?? null;
		$canonical   = $canonical ?? $permalink ?? null;

		if ( $robots ) {
			$normalized = strtolower( preg_replace( '/\s+/', '', $robots ) ?? '' );
			if ( 'index,follow' === $normalized || 'indexfollow' === $normalized ) {
				$robots = null;
			}
		}

		return new HeadMeta( $title, $description, $canonical, $robots );
	}

	/**
	 * Safely normalize arbitrary input as nullable string.
	 *
	 * @param mixed $value Arbitrary input value.
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
	 * Select a template string for the current context.
	 *
	 * @param array<string, mixed> $templates Templates configuration.
	 * @param string|null          $post_type Current post type.
	 * @param string               $field     Template key (title|description).
	 *
	 * @return string|null
	 */
	private static function select_template( array $templates, ?string $post_type, string $field ): ?string {
		if ( $post_type && ! empty( $templates['post_types'][ $post_type ][ $field ] ) ) {
			return (string) $templates['post_types'][ $post_type ][ $field ];
		}

		if ( ! empty( $templates['global'][ $field ] ) ) {
			return (string) $templates['global'][ $field ];
		}

		return null;
	}

	/**
	 * Build template context data.
	 *
	 * @param array<string, mixed> $input Original payload.
	 *
	 * @return array<string, string>
	 */
	private static function template_context( array $input ): array {
		$separator = self::string_or_null( $input['separator'] ?? '' );
		$separator = '' !== trim( (string) $separator ) ? trim( (string) $separator ) : '–';

		return array(
			'post_title'       => self::string_or_null( $input['post_title'] ?? '' ) ?? '',
			'post_excerpt'     => self::string_or_null( $input['post_excerpt'] ?? '' ) ?? '',
			'site_name'        => self::string_or_null( $input['site_name'] ?? '' ) ?? '',
			'site_description' => self::string_or_null( $input['site_description'] ?? '' ) ?? '',
			'separator'        => ' ' . $separator . ' ',
			'post_type'        => self::string_or_null( $input['post_type'] ?? '' ) ?? '',
			'custom_1'         => self::string_or_null( $input['custom_1'] ?? '' ) ?? '',
			'custom_2'         => self::string_or_null( $input['custom_2'] ?? '' ) ?? '',
			'custom_3'         => self::string_or_null( $input['custom_3'] ?? '' ) ?? '',
		);
	}

	/**
	 * Render a template string using the provided context.
	 *
	 * @param string|null           $template Template string.
	 * @param array<string, string> $context  Replacement context.
	 *
	 * @return string|null
	 */
	private static function render_template( ?string $template, array $context ): ?string {
		if ( null === $template ) {
			return null;
		}

		$tokens = array(
			'%post_title%'       => $context['post_title'],
			'%post_excerpt%'     => $context['post_excerpt'],
			'%site_name%'        => $context['site_name'],
			'%sitename%'         => $context['site_name'],
			'%site_description%' => $context['site_description'],
			'%separator%'        => $context['separator'],
			'%custom_1%'         => $context['custom_1'],
			'%custom_2%'         => $context['custom_2'],
			'%custom_3%'         => $context['custom_3'],
		);

		$rendered = strtr( $template, $tokens );
		$rendered = trim( preg_replace( '/\s+/', ' ', $rendered ) ?? '' );

		return '' === $rendered ? null : $rendered;
	}
}
