<?php
/**
 * Public hooks for llms.txt module.
 *
 * @package Airygen\Modules\LlmsTxt\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\LlmsTxt\Public;

use Airygen\Constants;
use Airygen\Modules\LlmsTxt\Admin\Settings;
use Airygen\Modules\LlmsTxt\Infrastructure\RenderCache;
use Airygen\Modules\MarkdownForAgents\Admin\Settings as MarkdownForAgentsSettings;
use Airygen\Support\Database\WpDbAdapter;
use Airygen\Support\Meta\PostData;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles llms endpoints output.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		Routes::register();
		add_action( 'parse_request', array( __CLASS__, 'handle_virtual_request' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_template_redirect' ), 0 );
	}

	/**
	 * Handle virtual llms requests before WordPress resolves 404 templates.
	 *
	 * @return void
	 */
	public static function handle_virtual_request(): void {
		self::maybe_output_llms_response();
	}

	/**
	 * Handle llms outputs.
	 *
	 * @return void
	 */
	public static function handle_template_redirect(): void {
		self::maybe_output_llms_response();
	}

	/**
	 * Output llms responses for matching requests.
	 *
	 * @return void
	 */
	private static function maybe_output_llms_response(): void {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( Routes::is_llms_request() || self::is_path_request( '/llms.txt' ) ) {
			nocache_headers();
			header( 'Content-Type: text/plain; charset=UTF-8' );
			// text/plain response body for llms.txt. Browsers/agents do not
			// interpret it as HTML; HTML escaping would mangle the spec output.
			echo self::render_base_content( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain response body; see comment above.
			exit;
		}

		$matched_extension_id = self::match_extension_request( $settings );
		if ( null !== $matched_extension_id ) {
			nocache_headers();
			header( 'Content-Type: text/plain; charset=UTF-8' );
			// text/plain response body for llms.txt extension files (same as above).
			echo self::render_extension_content( $settings, $matched_extension_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain response body; see comment above.
			exit;
		}
	}

	/**
	 * Render cached base llms.txt content.
	 *
	 * @param array<string,mixed> $settings Module settings.
	 *
	 * @return string
	 */
	private static function render_base_content( array $settings ): string {
		$cached = RenderCache::get( 'base' );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$content = self::build_llms_content( $settings );
		RenderCache::set( 'base', $content );
		return $content;
	}

	/**
	 * Render cached extension content.
	 *
	 * @param array<string,mixed> $settings     Module settings.
	 * @param string              $extension_id Extension identifier.
	 *
	 * @return string
	 */
	private static function render_extension_content( array $settings, string $extension_id ): string {
		$target = 'extension-' . sanitize_key( $extension_id );
		$cached = RenderCache::get( $target );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$content = self::build_extension_content( $settings, $extension_id );
		RenderCache::set( $target, $content );
		return $content;
	}

	/**
	 * Build /llms.txt content.
	 *
	 * @param array<string,mixed>|null $settings Optional settings.
	 *
	 * @return string
	 */
	public static function build_llms_content( ?array $settings = null ): string {
		$settings       = is_array( $settings ) ? $settings : Settings::get();
		$index_strategy = isset( $settings['index_strategy'] ) ? sanitize_key( (string) $settings['index_strategy'] ) : 'curated_plus_auto';
		if ( ! in_array( $index_strategy, array( 'curated_only', 'curated_plus_auto', 'auto_only' ), true ) ) {
			$index_strategy = 'curated_plus_auto';
		}

		$section_groups = self::collect_section_posts( $settings, array() );
		$manual_ids     = array();
		foreach ( $section_groups as $section ) {
			foreach ( $section['posts'] as $post ) {
				if ( $post instanceof WP_Post ) {
					$manual_ids[] = (int) $post->ID;
				}
			}
		}
		$topic_cluster_items = ! empty( $settings['auto_topic_cluster_groups'] )
		? self::collect_topic_cluster_items( $settings, $manual_ids )
		: array();
		$section_ids         = array();
		foreach ( $section_groups as $section ) {
			foreach ( $section['posts'] as $post ) {
				if ( $post instanceof WP_Post ) {
					$section_ids[] = (int) $post->ID;
				}
			}
		}
		foreach ( $topic_cluster_items as $topic_cluster_item ) {
			$section_ids[] = (int) $topic_cluster_item['post']->ID;
		}

		$auto_posts = 'curated_only' === $index_strategy
		? array()
		: self::collect_auto_posts( $settings, $section_ids );

		$total_count = count( $auto_posts );
		foreach ( $section_groups as $section ) {
			$total_count += count( $section['posts'] );
		}
		$total_count += count( $topic_cluster_items );

		$lines = self::build_agent_header_lines( $settings, $total_count );

		if ( ! empty( $topic_cluster_items ) ) {
			$items_by_group = array();
			foreach ( $topic_cluster_items as $topic_cluster_item ) {
				$group_id = (int) $topic_cluster_item['group_id'];
				if ( ! isset( $items_by_group[ $group_id ] ) ) {
					$items_by_group[ $group_id ] = array();
				}
				$items_by_group[ $group_id ][] = $topic_cluster_item;
			}

			foreach ( $items_by_group as $group_items ) {
				$l1_posts     = array();
				$l2_by_parent = array();
				$l3_by_parent = array();

				foreach ( $group_items as $group_item ) {
					$level          = (int) $group_item['level'];
					$parent_post_id = (int) $group_item['parent_post_id'];
					$post           = $group_item['post'];

					if ( 1 === $level ) {
						$l1_posts[] = $post;
						continue;
					}

					if ( 2 === $level ) {
						if ( ! isset( $l2_by_parent[ $parent_post_id ] ) ) {
							$l2_by_parent[ $parent_post_id ] = array();
						}
						$l2_by_parent[ $parent_post_id ][] = $post;
						continue;
					}

					if ( ! isset( $l3_by_parent[ $parent_post_id ] ) ) {
						$l3_by_parent[ $parent_post_id ] = array();
					}
					$l3_by_parent[ $parent_post_id ][] = $post;
				}

				foreach ( $l1_posts as $l1_post ) {
					$l1_url = self::get_post_canonical_url( $l1_post, $settings );
					if ( '' === $l1_url ) {
						continue;
					}

					if ( '' !== end( $lines ) ) {
						$lines[] = '';
					}
					$lines[]    = '## [' . self::normalize_title( $l1_post ) . '](' . $l1_url . ')';
					$l1_excerpt = self::build_post_excerpt( $l1_post );
					if ( '' !== $l1_excerpt ) {
						$lines[] = $l1_excerpt;
					}
					$lines[] = '';

					$l2_children = $l2_by_parent[ (int) $l1_post->ID ] ?? array();
					foreach ( $l2_children as $l2_post ) {
						$l2_url = self::get_post_canonical_url( $l2_post, $settings );
						if ( '' === $l2_url ) {
							continue;
						}

						$lines[]    = '### [' . self::normalize_title( $l2_post ) . '](' . $l2_url . ')';
						$l2_excerpt = self::build_post_excerpt( $l2_post );
						if ( '' !== $l2_excerpt ) {
							$lines[] = $l2_excerpt;
						}

						$l3_children = $l3_by_parent[ (int) $l2_post->ID ] ?? array();
						foreach ( $l3_children as $l3_post ) {
							$l3_url = self::get_post_canonical_url( $l3_post, $settings );
							if ( '' === $l3_url ) {
								continue;
							}
							$lines[] = '- [' . self::normalize_title( $l3_post ) . '](' . $l3_url . ')';
						}
						$lines[] = '';
					}
				}
			}
		}

		if ( ! empty( $section_groups ) ) {
			foreach ( $section_groups as $section ) {
				$title = isset( $section['title'] ) ? trim( (string) $section['title'] ) : '';
				if ( '' === $title ) {
					continue;
				}
				$lines[]     = '## ' . $title;
				$description = isset( $section['description'] ) ? trim( (string) $section['description'] ) : '';
				if ( '' !== $description ) {
					$lines[] = $description;
				}
				$section_posts = isset( $section['posts'] ) && is_array( $section['posts'] ) ? $section['posts'] : array();
				foreach ( $section_posts as $post ) {
					if ( ! $post instanceof WP_Post ) {
						continue;
					}
					$url = self::get_post_canonical_url( $post, $settings );
					if ( '' === $url ) {
						continue;
					}
					$lines[] = '- [' . self::normalize_title( $post ) . '](' . $url . ')';
				}
				$lines[] = '';
			}
		}

		if ( ! empty( $auto_posts ) ) {
			$auto_section_title = isset( $settings['auto_section_title'] ) && is_string( $settings['auto_section_title'] )
			? trim( $settings['auto_section_title'] )
			: '';
			$lines[]            = '## ' . ( '' !== $auto_section_title ? $auto_section_title : 'Additional content' );
			foreach ( $auto_posts as $post ) {
				$url = self::get_post_canonical_url( $post, $settings );
				if ( '' === $url ) {
					continue;
				}
				$lines[] = '- [' . self::normalize_title( $post ) . '](' . $url . ')';
			}
			$lines[] = '';
		}

		if ( empty( $section_groups ) && empty( $auto_posts ) && empty( $topic_cluster_items ) ) {
			$lines[] = '- No content matched the current LLMs.txt scope and exclusion rules.';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build extension preview content.
	 *
	 * @param array<string,mixed>|null $settings     Optional settings.
	 * @param string                   $extension_id Extension identifier.
	 *
	 * @return string
	 */
	public static function build_extension_content( ?array $settings, string $extension_id ): string {
		$settings   = is_array( $settings ) ? $settings : Settings::get();
		$extensions = isset( $settings['extensions'] ) && is_array( $settings['extensions'] )
		? $settings['extensions']
		: array();
		$extension  = null;

		foreach ( $extensions as $candidate ) {
			if ( is_array( $candidate ) && isset( $candidate['id'] ) && (string) $candidate['id'] === $extension_id ) {
				$extension = $candidate;
				break;
			}
		}

		if ( ! is_array( $extension ) ) {
			return self::build_llms_content( $settings );
		}

		$section_settings                      = array(
			'post_types'                 => $settings['post_types'] ?? array( 'post', 'page' ),
			'exclude_noindex'            => $settings['exclude_noindex'] ?? true,
			'exclude_password_protected' => $settings['exclude_password_protected'] ?? true,
			'min_word_count'             => $settings['min_word_count'] ?? 0,
			'use_markdown_links'         => $settings['use_markdown_links'] ?? false,
			'sections'                   => $extension['sections'] ?? array(),
		);
		$section_groups                        = self::collect_section_posts( $section_settings, array() );
		$title                                 = isset( $extension['title'] ) ? trim( (string) $extension['title'] ) : '';
		$description                           = isset( $extension['description'] ) ? trim( (string) $extension['description'] ) : '';
		$custom_declaration                    = isset( $extension['custom_declaration'] ) ? trim( (string) $extension['custom_declaration'] ) : '';
		$header_settings                       = $settings;
		$header_settings['custom_declaration'] = $custom_declaration;

		$lines = self::build_agent_header_lines( $header_settings, count( $section_groups ), false );

		if ( '' !== $title ) {
			$lines[] = '# ' . $title;
			if ( '' !== $description ) {
				$lines[] = '> ' . $description;
			}
			$lines[] = '';
		}

		foreach ( $section_groups as $section ) {
			$section_title = isset( $section['title'] ) ? trim( (string) $section['title'] ) : '';
			if ( '' === $section_title ) {
				continue;
			}
			$lines[]      = '## ' . $section_title;
			$section_desc = isset( $section['description'] ) ? trim( (string) $section['description'] ) : '';
			if ( '' !== $section_desc ) {
				$lines[] = $section_desc;
			}
			$section_posts = isset( $section['posts'] ) && is_array( $section['posts'] ) ? $section['posts'] : array();
			foreach ( $section_posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				$url = self::get_post_canonical_url( $post, $section_settings );
				if ( '' === $url ) {
					continue;
				}
				$lines[] = '- [' . self::normalize_title( $post ) . '](' . $url . ')';
			}
			$lines[] = '';
		}

		if ( empty( $section_groups ) ) {
			$lines[] = '- No content matched this extension selection.';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Match current request path to an enabled extension path.
	 *
	 * @param array<string,mixed> $settings Module settings.
	 *
	 * @return string|null
	 */
	private static function match_extension_request( array $settings ): ?string {
		$extensions   = isset( $settings['extensions'] ) && is_array( $settings['extensions'] )
		? $settings['extensions']
		: array();
		$current_path = self::current_request_path();
		if ( '' === $current_path ) {
			return null;
		}

		foreach ( $extensions as $extension ) {
			if ( ! is_array( $extension ) || empty( $extension['enabled'] ) || empty( $extension['id'] ) ) {
				continue;
			}
			$extension_path = self::build_extension_request_path( $extension );
			if ( '' !== $extension_path && $current_path === $extension_path ) {
				return (string) $extension['id'];
			}
		}

		return null;
	}

	/**
	 * Build request path for a single extension.
	 *
	 * @param array<string,mixed> $extension Extension settings.
	 *
	 * @return string
	 */
	private static function build_extension_request_path( array $extension ): string {
		$filename = isset( $extension['filename'] ) ? trim( (string) $extension['filename'] ) : '';
		if ( '' === $filename ) {
			return '';
		}

		$path = isset( $extension['path'] ) ? trim( (string) $extension['path'] ) : '';
		$path = trim( $path, '/' );

		return '' !== $path
		? '/' . $path . '/' . $filename
		: '/' . $filename;
	}

	/**
	 * Resolve current request path without query string.
	 *
	 * @return string
	 */
	private static function current_request_path(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
		: '';
		if ( '' === $request_uri ) {
			return '';
		}

		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return '';
		}

		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' );
		}

		return self::normalize_site_relative_path( $path );
	}

	/**
	 * Build machine-readable llms.txt header metadata.
	 *
	 * @param array<string,mixed> $settings             Module settings.
	 * @param int                 $total_count          Total indexed item count.
	 * @param bool                $include_site_heading Whether to include site heading block.
	 *
	 * @return array<int,string>
	 */
	private static function build_agent_header_lines( array $settings, int $total_count, bool $include_site_heading = true ): array {
		$site_url  = home_url( '/' );
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$locale    = str_replace( '_', '-', (string) get_locale() );

		$lines   = array();
		$lines[] = '---';
		$lines[] = 'site: ' . ( is_string( $site_url ) ? rtrim( $site_url, '/' ) : '' );
		$lines[] = 'site_name: ' . trim( wp_strip_all_tags( (string) $site_name ) );
		$lines[] = 'default_locale: ' . $locale;
		$lines[] = 'generated_at_utc: ' . gmdate( 'c' );
		if ( ! empty( $settings['custom_declaration'] ) ) {
			$lines[] = 'agent_note: ' . trim( (string) $settings['custom_declaration'] );
		}
		$lines[] = '---';
		$lines[] = '';

		if ( $include_site_heading ) {
			$lines[]        = '# ' . trim( wp_strip_all_tags( (string) $site_name ) );
			$site_desc_text = trim( wp_strip_all_tags( (string) $site_desc ) );
			if ( '' !== $site_desc_text ) {
				$lines[] = '> ' . $site_desc_text;
			}
			$lines[] = '';
		}

		return $lines;
	}

	/**
	 * Collect section groups based on configured post IDs.
	 *
	 * @param array<string,mixed> $settings    Module settings.
	 * @param array<int, int>     $exclude_ids Post IDs already used.
	 *
	 * @return array<int, array{title:string,description:string,posts:array<int,WP_Post>}>
	 */
	private static function collect_section_posts( array $settings, array $exclude_ids ): array {
		if ( ! isset( $settings['sections'] ) || ! is_array( $settings['sections'] ) ) {
			return array();
		}

		$used   = array_fill_keys( array_values( array_unique( array_map( 'intval', $exclude_ids ) ) ), true );
		$groups = array();

		foreach ( $settings['sections'] as $raw_section ) {
			if ( ! is_array( $raw_section ) ) {
				continue;
			}
			if ( ! empty( $raw_section['hidden'] ) ) {
				continue;
			}

			$title = isset( $raw_section['title'] ) ? trim( (string) $raw_section['title'] ) : '';
			if ( '' === $title ) {
				continue;
			}

			$description = isset( $raw_section['description'] ) ? trim( (string) $raw_section['description'] ) : '';
			$max_items   = isset( $raw_section['max_items'] ) ? (int) $raw_section['max_items'] : 20;
			$max_items   = max( 1, min( 100, $max_items ) );
			$post_ids    = isset( $raw_section['post_ids'] ) && is_array( $raw_section['post_ids'] )
			? array_values( array_filter( array_map( 'intval', $raw_section['post_ids'] ) ) )
			: array();

			$posts = array();
			foreach ( $post_ids as $post_id ) {
				if ( isset( $used[ $post_id ] ) ) {
					continue;
				}
				$post = get_post( $post_id );
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				if ( ! self::should_include_post( $post, $settings ) ) {
					continue;
				}
				$posts[]          = $post;
				$used[ $post_id ] = true;
				if ( count( $posts ) >= $max_items ) {
					break;
				}
			}

			$groups[] = array(
				'title'       => $title,
				'description' => $description,
				'posts'       => $posts,
			);
		}

		return $groups;
	}

	/**
	 * Collect Topic Cluster items in hierarchy order.
	 *
	 * @param array<string,mixed> $settings    Module settings.
	 * @param array<int, int>     $exclude_ids Post IDs already used.
	 *
	 * @return array<int, array{group_id:int,parent_post_id:int,post:WP_Post,level:int}>
	 */
	private static function collect_topic_cluster_items( array $settings, array $exclude_ids ): array {
		$adapter         = new WpDbAdapter();
		$relations_table = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		if ( ! $adapter->table_exists( $relations_table ) ) {
			return array();
		}

		$rows = $adapter->get_results(
			"SELECT r.group_id, r.post_id, r.level, r.parent_post_id
			 FROM {$relations_table} r
			 WHERE r.group_id > %d
			 ORDER BY r.group_id ASC, r.level ASC, r.parent_post_id ASC, r.post_id ASC",
			array( 0 ),
			\ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$used  = array_fill_keys( array_values( array_unique( array_map( 'intval', $exclude_ids ) ) ), true );
		$items = array();
		foreach ( $rows as $row ) {
			$group_id = isset( $row['group_id'] ) ? (int) $row['group_id'] : 0;
			$post_id  = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			$level    = isset( $row['level'] ) ? (int) $row['level'] : 0;
			if ( $group_id <= 0 || $post_id <= 0 || isset( $used[ $post_id ] ) ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post || ! self::should_include_post( $post, $settings ) ) {
				continue;
			}

			$items[]          = array(
				'group_id'       => $group_id,
				'parent_post_id' => isset( $row['parent_post_id'] ) ? (int) $row['parent_post_id'] : 0,
				'post'           => $post,
				'level'          => in_array( $level, array( 1, 2, 3 ), true ) ? $level : 3,
			);
			$used[ $post_id ] = true;
		}

		return $items;
	}

	/**
	 * Build post excerpt text for llms output.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return string
	 */
	private static function build_post_excerpt( WP_Post $post ): string {
		$excerpt = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		$content = trim( wp_strip_all_tags( (string) $post->post_content ) );
		if ( '' === $content ) {
			return '';
		}

		return wp_trim_words( $content, 40, '…' );
	}

	/**
	 * Collect automatically selected posts.
	 *
	 * @param array<string,mixed> $settings   Module settings.
	 * @param array<int, int>     $exclude_ids Curated IDs to skip.
	 *
	 * @return array<int, WP_Post>
	 */
	private static function collect_auto_posts( array $settings, array $exclude_ids ): array {
		$post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
		? array_values( array_filter( array_map( 'strval', $settings['post_types'] ) ) )
		: array( 'post', 'page' );
		if ( empty( $post_types ) ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( empty( $query->posts ) || ! is_array( $query->posts ) ) {
			return array();
		}

		$posts = array();
		foreach ( $query->posts as $item ) {
			if ( ! $item instanceof WP_Post ) {
				continue;
			}
			if ( in_array( (int) $item->ID, $exclude_ids, true ) ) {
				continue;
			}
			if ( self::should_include_post( $item, $settings ) ) {
				$posts[] = $item;
			}
		}

		return $posts;
	}

	/**
	 * Check whether post should be included based on V1 rules.
	 *
	 * @param WP_Post             $post     Post object.
	 * @param array<string,mixed> $settings Module settings.
	 *
	 * @return bool
	 */
	private static function should_include_post( WP_Post $post, array $settings ): bool {
		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		$allowed_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
		? array_values( array_filter( array_map( 'strval', $settings['post_types'] ) ) )
		: array( 'post', 'page' );
		if ( ! in_array( (string) $post->post_type, $allowed_types, true ) ) {
			return false;
		}

		if ( ! empty( $settings['exclude_password_protected'] ) && '' !== (string) $post->post_password ) {
			return false;
		}

		if ( ! empty( $settings['exclude_noindex'] ) ) {
			$robots = PostData::get_field( $post->ID, 'robots' );
			if ( is_string( $robots ) && str_contains( strtolower( $robots ), 'noindex' ) ) {
				return false;
			}
		}

		$min_word_count = isset( $settings['min_word_count'] ) ? (int) $settings['min_word_count'] : 0;
		$min_word_count = max( 0, $min_word_count );
		if ( $min_word_count > 0 ) {
			$content = wp_strip_all_tags( (string) $post->post_content );
			$words   = str_word_count( $content );
			if ( $words < $min_word_count ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolve canonical URL for a post.
	 *
	 * @param WP_Post             $post          Post object.
	 * @param array<string,mixed> $llms_settings Module settings.
	 *
	 * @return string
	 */
	private static function get_post_canonical_url( WP_Post $post, array $llms_settings ): string {
		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) ) {
			return '';
		}

		$url = trim( $permalink );
		if ( '' === $url ) {
			return '';
		}

		if ( empty( $llms_settings['use_markdown_links'] ) ) {
			return $url;
		}

		$markdown_settings = MarkdownForAgentsSettings::get();
		if ( empty( $markdown_settings['enabled'] ) ) {
			return $url;
		}

		$allowed_types = isset( $markdown_settings['post_types'] ) && is_array( $markdown_settings['post_types'] )
		? array_values( array_filter( array_map( 'strval', $markdown_settings['post_types'] ) ) )
		: array();
		if ( ! in_array( (string) $post->post_type, $allowed_types, true ) ) {
			return $url;
		}

		return add_query_arg( 'format', 'md', $url );
	}

	/**
	 * Normalize output title.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return string
	 */
	private static function normalize_title( WP_Post $post ): string {
		$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
		if ( '' !== $title ) {
			return $title;
		}

		return sprintf(
			/* translators: %d is the post ID. */
			__( 'Untitled post #%d', 'airygen-seo' ),
			(int) $post->ID
		);
	}

	/**
	 * Check current request path suffix.
	 *
	 * @param string $suffix Path suffix.
	 *
	 * @return bool
	 */
	private static function is_path_request( string $suffix ): bool {
		$uri_raw = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$uri     = is_string( $uri_raw ) ? sanitize_text_field( $uri_raw ) : '';
		if ( '' === $uri ) {
			return false;
		}
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}
		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' );
		}

		return self::normalize_site_relative_path( $path ) === $suffix;
	}

	/**
	 * Normalize a request path to be relative to the current site's home path.
	 *
	 * @param string $path Absolute request path.
	 *
	 * @return string
	 */
	private static function normalize_site_relative_path( string $path ): string {
		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' );
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( ! is_string( $home_path ) || '' === $home_path || '/' === $home_path ) {
			return $path;
		}

		$home_path = '/' . trim( $home_path, '/' );
		if ( str_starts_with( $path, $home_path . '/' ) ) {
			$relative = substr( $path, strlen( $home_path ) );
			return '' !== $relative ? $relative : '/';
		}

		if ( $path === $home_path ) {
			return '/';
		}

		return $path;
	}
}
