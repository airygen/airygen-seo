<?php
/**
 * Stores llms.txt module settings.
 *
 * @package Airygen\Modules\LlmsTxt\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LlmsTxt\Admin;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\LlmsTxt\Infrastructure\RenderCache;
use Airygen\Modules\LlmsTxt\Public\Routes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option repository.
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_LLMS_TXT;

	/**
	 * Ensure option exists.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', 'no' );
		}
	}

	/**
	 * Get settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION_NAME, array() ) );
	}

	/**
	 * Sanitize preview payload without saving it.
	 *
	 * @param array<string,mixed> $value Raw value.
	 *
	 * @return array<string,mixed>
	 */
	public static function sanitize_preview( array $value ): array {
		return self::sanitize( $value );
	}

	/**
	 * Update settings.
	 *
	 * @param array<string,mixed> $value Raw value.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		$previous  = self::get();
		$sanitized = self::sanitize( $value );
		update_option( self::OPTION_NAME, $sanitized, 'no' );

		$prev_enabled = ! empty( $previous['enabled'] );
		$new_enabled  = ! empty( $sanitized['enabled'] );
		if ( $prev_enabled !== $new_enabled ) {
			if ( $new_enabled ) {
				Routes::add_rewrite_rules();
			}
			flush_rewrite_rules( false );
		}

		RenderCache::invalidate_all();
	}

	/**
	 * Defaults.
	 *
	 * @return array<string,mixed>
	 */
	private static function defaults(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = is_array( $post_types ) ? array_values( array_map( 'strval', $post_types ) ) : array( 'post', 'page' );

		return array(
			'enabled'                    => true,
			'custom_declaration'         => '',
			'auto_section_title'         => 'Additional content',
			'index_strategy'             => 'curated_plus_auto',
			'auto_topic_cluster_groups'  => false,
			'use_markdown_links'         => false,
			'add_to_sitemap'             => true,
			'exclude_noindex'            => true,
			'exclude_password_protected' => true,
			'min_word_count'             => 150,
			'sections'                   => array(),
			'extensions'                 => array(),
			'post_types'                 => $post_types,
		);
	}

	/**
	 * Sanitize value.
	 *
	 * @param mixed $value Raw option.
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize( $value ): array {
		$defaults = self::defaults();
		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$post_types = $defaults['post_types'];
		if ( isset( $value['post_types'] ) && is_array( $value['post_types'] ) ) {
			$allowed = get_post_types( array( 'public' => true ), 'names' );
			$allowed = is_array( $allowed ) ? array_values( array_map( 'strval', $allowed ) ) : array();
			$types   = array_values( array_unique( array_filter( array_map( 'strval', $value['post_types'] ) ) ) );
			$types   = array_values( array_filter( $types, static fn( string $type ): bool => in_array( $type, $allowed, true ) ) );
			if ( ! empty( $types ) ) {
				$post_types = $types;
			}
		}

		$index_strategy = isset( $value['index_strategy'] ) ? sanitize_key( (string) $value['index_strategy'] ) : (string) $defaults['index_strategy'];
		if ( ! in_array( $index_strategy, array( 'curated_only', 'curated_plus_auto', 'auto_only' ), true ) ) {
			$index_strategy = (string) $defaults['index_strategy'];
		}

		$min_word_count              = isset( $value['min_word_count'] ) ? (int) $value['min_word_count'] : (int) $defaults['min_word_count'];
		$min_word_count              = max( 0, min( 5000, $min_word_count ) );
		$modules                     = ModuleSettings::get();
		$topic_cluster_enabled       = isset( $modules['topicCluster'] ) ? (bool) $modules['topicCluster'] : false;
		$markdown_for_agents_enabled = isset( $modules['markdownForAgents'] ) ? (bool) $modules['markdownForAgents'] : false;

		$sections = array();
		if ( isset( $value['sections'] ) && is_array( $value['sections'] ) ) {
			foreach ( $value['sections'] as $index => $raw_section ) {
				if ( ! is_array( $raw_section ) ) {
					continue;
				}
				$section_id  = isset( $raw_section['id'] ) ? sanitize_key( (string) $raw_section['id'] ) : 'section_' . ( (int) $index + 1 );
				$title       = isset( $raw_section['title'] ) ? trim( sanitize_text_field( (string) $raw_section['title'] ) ) : '';
				$description = isset( $raw_section['description'] ) ? trim( sanitize_textarea_field( (string) $raw_section['description'] ) ) : '';
				$max_items   = isset( $raw_section['max_items'] ) ? (int) $raw_section['max_items'] : 20;
				$max_items   = max( 1, min( 100, $max_items ) );
				$hidden      = isset( $raw_section['hidden'] ) ? (bool) $raw_section['hidden'] : false;

				$post_ids = array();
				if ( isset( $raw_section['post_ids'] ) && is_array( $raw_section['post_ids'] ) ) {
					$post_ids = array_values(
						array_unique(
							array_filter(
								array_map( 'absint', $raw_section['post_ids'] ),
								static fn( int $post_id ): bool => $post_id > 0
							)
						)
					);
				}

				if ( '' === $section_id || '' === $title ) {
					continue;
				}

				$sections[] = array(
					'id'          => $section_id,
					'title'       => $title,
					'description' => $description,
					'post_ids'    => $post_ids,
					'max_items'   => $max_items,
					'hidden'      => $hidden,
				);
			}
		}

		$extensions = array();
		if ( isset( $value['extensions'] ) && is_array( $value['extensions'] ) ) {
			foreach ( $value['extensions'] as $index => $raw_extension ) {
				if ( ! is_array( $raw_extension ) ) {
					continue;
				}

				$extension_id                 = isset( $raw_extension['id'] ) ? sanitize_key( (string) $raw_extension['id'] ) : 'extension_' . ( (int) $index + 1 );
				$extension_title              = isset( $raw_extension['title'] ) ? trim( sanitize_text_field( (string) $raw_extension['title'] ) ) : 'Extension ' . ( (int) $index + 1 );
				$extension_description        = isset( $raw_extension['description'] ) ? trim( sanitize_textarea_field( (string) $raw_extension['description'] ) ) : '';
				$extension_path               = isset( $raw_extension['path'] ) ? trim( sanitize_text_field( (string) $raw_extension['path'] ) ) : '';
				$extension_path               = trim( $extension_path, '/' );
				$extension_custom_declaration = isset( $raw_extension['custom_declaration'] ) ? trim( sanitize_textarea_field( (string) $raw_extension['custom_declaration'] ) ) : '';
				if ( '' !== $extension_path && 1 !== preg_match( '/^[A-Za-z0-9]+(?:\/[A-Za-z0-9]+)*$/', $extension_path ) ) {
					$extension_path = '';
				}
				$extension_filename = isset( $raw_extension['filename'] ) ? trim( sanitize_text_field( (string) $raw_extension['filename'] ) ) : 'llms.txt';
				if ( ! in_array( $extension_filename, array( 'llms.txt', 'llms-small.txt', 'llms-full.txt' ), true ) ) {
					$extension_filename = 'llms.txt';
				}
				if ( '' === $extension_path && 'llms.txt' === $extension_filename ) {
					$extension_filename = 'llms-small.txt';
				}
				$extension_on   = isset( $raw_extension['enabled'] ) ? (bool) $raw_extension['enabled'] : true;
				$extension_rows = array();

				if ( isset( $raw_extension['sections'] ) && is_array( $raw_extension['sections'] ) ) {
					foreach ( $raw_extension['sections'] as $section_index => $raw_section ) {
						if ( ! is_array( $raw_section ) ) {
							continue;
						}
						$section_id  = isset( $raw_section['id'] ) ? sanitize_key( (string) $raw_section['id'] ) : 'section_' . ( (int) $section_index + 1 );
						$title       = isset( $raw_section['title'] ) ? trim( sanitize_text_field( (string) $raw_section['title'] ) ) : '';
						$description = isset( $raw_section['description'] ) ? trim( sanitize_textarea_field( (string) $raw_section['description'] ) ) : '';
						$max_items   = isset( $raw_section['max_items'] ) ? (int) $raw_section['max_items'] : 20;
						$max_items   = max( 1, min( 100, $max_items ) );
						$hidden      = isset( $raw_section['hidden'] ) ? (bool) $raw_section['hidden'] : false;

						$post_ids = array();
						if ( isset( $raw_section['post_ids'] ) && is_array( $raw_section['post_ids'] ) ) {
							$post_ids = array_values(
								array_unique(
									array_filter(
										array_map( 'absint', $raw_section['post_ids'] ),
										static fn( int $post_id ): bool => $post_id > 0
									)
								)
							);
						}

						if ( '' === $section_id || '' === $title ) {
							continue;
						}

						$extension_rows[] = array(
							'id'          => $section_id,
							'title'       => $title,
							'description' => $description,
							'post_ids'    => $post_ids,
							'max_items'   => $max_items,
							'hidden'      => $hidden,
						);
					}
				}

				if ( '' === $extension_id ) {
					continue;
				}

				$extensions[] = array(
					'id'                 => $extension_id,
					'title'              => '' !== $extension_title ? $extension_title : 'Extension ' . ( (int) $index + 1 ),
					'description'        => $extension_description,
					'path'               => $extension_path,
					'custom_declaration' => $extension_custom_declaration,
					'filename'           => $extension_filename,
					'enabled'            => $extension_on,
					'sections'           => $extension_rows,
				);
			}
		}

		return array(
			'enabled'                    => isset( $value['enabled'] ) ? (bool) $value['enabled'] : (bool) $defaults['enabled'],
			'custom_declaration'         => isset( $value['custom_declaration'] ) ? trim( (string) $value['custom_declaration'] ) : '',
			'auto_section_title'         => isset( $value['auto_section_title'] ) ? trim( sanitize_text_field( (string) $value['auto_section_title'] ) ) : (string) $defaults['auto_section_title'],
			'index_strategy'             => $index_strategy,
			'auto_topic_cluster_groups'  => $topic_cluster_enabled
				? ( isset( $value['auto_topic_cluster_groups'] ) ? (bool) $value['auto_topic_cluster_groups'] : (bool) $defaults['auto_topic_cluster_groups'] )
				: false,
			'use_markdown_links'         => $markdown_for_agents_enabled
				? ( isset( $value['use_markdown_links'] ) ? (bool) $value['use_markdown_links'] : (bool) $defaults['use_markdown_links'] )
				: false,
			'add_to_sitemap'             => isset( $value['add_to_sitemap'] ) ? (bool) $value['add_to_sitemap'] : (bool) $defaults['add_to_sitemap'],
			'exclude_noindex'            => isset( $value['exclude_noindex'] ) ? (bool) $value['exclude_noindex'] : (bool) $defaults['exclude_noindex'],
			'exclude_password_protected' => isset( $value['exclude_password_protected'] ) ? (bool) $value['exclude_password_protected'] : (bool) $defaults['exclude_password_protected'],
			'min_word_count'             => $min_word_count,
			'sections'                   => $sections,
			'extensions'                 => $extensions,
			'post_types'                 => $post_types,
		);
	}
}
