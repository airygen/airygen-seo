<?php
/**
 * Registers Action Scheduler jobs for keyphrase recompute and full reindex.
 *
 * @package Airygen\Modules\LinkSuggestions\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Runtime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\LinkSuggestions\Admin\Settings;
use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;
use Airygen\Modules\LinkSuggestions\Infrastructure\LocalKeyphraseClientFactory;
use Airygen\Modules\LinkSuggestions\Persistence\LinkTermsRepository;
use Airygen\Support\Meta\PostData;
use Throwable;
use WP_Post;
use WP_Query;

use function absint;
use function as_enqueue_async_action;
use function as_has_scheduled_action;
use function as_next_scheduled_action;
use function as_unschedule_all_actions;
use function do_action;
use function get_locale;
use function get_post;
use function get_post_meta;
use function get_post_status;
use function get_post_type;
use function get_post_types;
use function get_the_terms;
use function in_array;
use function is_wp_error;
use function preg_match_all;
use function preg_split;
use function sanitize_text_field;
use function time;
use function update_post_meta;
use function wp_strip_all_tags;

/**
 * Wires actions to Action Scheduler for async processing.
 */
class Jobs {

	private const HOOK_RECOMPUTE   = Constants::HOOK_LINK_SUGGESTIONS_RECOMPUTE_TF_ASYNC;
	private const HOOK_REINDEX_ALL = Constants::HOOK_LINK_SUGGESTIONS_REINDEX_ALL_ASYNC;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( Constants::HOOK_LINK_SUGGESTIONS_RECOMPUTE_TF, array( __CLASS__, 'queue_recompute' ), 10, 3 );
		add_action( self::HOOK_RECOMPUTE, array( __CLASS__, 'handle_recompute' ), 10, 1 );
		add_action( Constants::HOOK_LINK_SUGGESTIONS_REINDEX_ALL, array( __CLASS__, 'queue_reindex_all' ) );
		add_action( self::HOOK_REINDEX_ALL, array( __CLASS__, 'handle_reindex_all' ) );
	}

	/**
	 * Queue async recompute for a post.
	 *
	 * @param int     $post_id Post ID.
	 * @param int     $word_count Word count (unused for now).
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public static function queue_recompute( int $post_id, int $word_count, WP_Post $post ): void {
		if ( ! ModuleSettings::is_enabled( 'linkSuggestions' ) ) {
			return;
		}

		$settings = Settings::get();
		if ( ! $settings['enabled'] ) {
			return;
		}

		$allowed_types = ! empty( $settings['allowed_post_types'] ) ? (array) $settings['allowed_post_types'] : get_post_types( array( 'public' => true ) );

		if ( ! in_array( get_post_type( $post ), $allowed_types, true ) ) {
			return;
		}

		if ( 'trash' === get_post_status( $post ) ) {
			return;
		}

		if ( as_next_scheduled_action( self::HOOK_RECOMPUTE, array( $post_id ), 'airygen-seo' ) ) {
			return;
		}

		// Run immediately; Action Scheduler will handle async.
		as_enqueue_async_action( self::HOOK_RECOMPUTE, array( $post_id ), 'airygen-seo' );
	}

	/**
	 * Process recompute for a single post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function handle_recompute( int $post_id ): void {
		if ( ! ModuleSettings::is_enabled( 'linkSuggestions' ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$status = get_post_status( $post );
		if ( 'trash' === $status || 'auto-draft' === $status ) {
			return;
		}

		$settings = Settings::get();
		if ( ! $settings['enabled'] ) {
			return;
		}

		$allowed_types = ! empty( $settings['allowed_post_types'] ) ? (array) $settings['allowed_post_types'] : get_post_types( array( 'public' => true ) );

		if ( ! in_array( get_post_type( $post ), $allowed_types, true ) ) {
			return;
		}

		if ( 'trash' === get_post_status( $post ) ) {
			return;
		}

		$content_raw = self::strip_cjk_chars( (string) $post->post_content );
		$content     = wp_strip_all_tags( $content_raw );
		$content     = (string) $content;
		$focus       = self::extract_focus_keywords( $post_id );
		$headings    = self::extract_headings( $content_raw );

		$language = self::detect_language();
		$headings = self::strip_cjk_from_list( $headings );

		$post_data = PostData::get( $post_id );
		$request   = new KeyphraseRequest(
			array(
				'language'       => $language,
				'content'        => $content,
				'title'          => self::strip_cjk_chars( (string) $post->post_title ),
				'description'    => self::strip_cjk_chars( $post_data['description'] ),
				'focus_keywords' => $focus,
				'headings'       => $headings,
				'max_terms'      => 100,
			)
		);
		$client    = LocalKeyphraseClientFactory::for( $language );

		try {
			$dto = $client->fetch( $request );
		} catch ( Throwable $e ) {
			do_action( Constants::HOOK_LINK_SUGGESTIONS_KEYPHRASE_ERROR, $post_id, $e->getMessage() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.
			return;
		}

		$terms         = $dto->terms();
		$keyword_terms = array();
		if ( ! empty( $focus ) || ! empty( $headings ) ) {
			$keyword_terms = self::fallback_terms_from_keywords( array_merge( $focus, $headings ) );
		}

		if ( empty( $terms ) ) {
			$terms = $keyword_terms;
		} elseif ( ! empty( $keyword_terms ) ) {
			foreach ( $keyword_terms as $stem => $tf ) {
				if ( isset( $terms[ $stem ] ) ) {
					continue;
				}
				$terms[ $stem ] = $tf;
			}
		}

		$repository = new LinkTermsRepository();
		$repository->save_terms( $post_id, get_post_type( $post ), $terms );

		update_post_meta( $post_id, Constants::META_KEYPHRASES_INDEXED_AT, time() );
	}

	/**
	 * Queue a full reindex.
	 *
	 * @return void
	 */
	public static function queue_reindex_all(): void {
		if ( ! ModuleSettings::is_enabled( 'linkSuggestions' ) ) {
			return;
		}

		if ( self::is_action_scheduler_available() ) {
			as_unschedule_all_actions( self::HOOK_REINDEX_ALL, array(), 'airygen-seo' );
			if ( false === as_next_scheduled_action( self::HOOK_REINDEX_ALL, array(), 'airygen-seo' ) ) {
				as_enqueue_async_action( self::HOOK_REINDEX_ALL, array(), 'airygen-seo' );
			}
			return;
		}

		wp_schedule_single_event( time(), self::HOOK_REINDEX_ALL );
	}

	/**
	 * Handle full reindex.
	 *
	 * @return void
	 */
	public static function handle_reindex_all(): void {
		if ( ! ModuleSettings::is_enabled( 'linkSuggestions' ) ) {
			return;
		}

		$settings = Settings::get();
		if ( ! $settings['enabled'] ) {
			return;
		}

		$post_types = $settings['allowed_post_types'];
		if ( empty( $post_types ) ) {
			$post_types = get_post_types( array( 'public' => true ) );
		}

		$paged    = 1;
		$per_page = 200;
		$statuses = array_diff(
			get_post_stati( array( 'internal' => false ), 'names' ),
			array( 'trash', 'auto-draft' )
		);
		do {
			$query = new WP_Query(
				array(
					'post_type'      => $post_types,
					'post_status'    => $statuses,
					'posts_per_page' => $per_page,
					'paged'          => $paged,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( (array) $query->posts as $post_id ) {
				if ( as_has_scheduled_action( self::HOOK_RECOMPUTE, array( (int) $post_id ), 'airygen-seo' ) ) {
					continue;
				}
				as_enqueue_async_action( self::HOOK_RECOMPUTE, array( (int) $post_id ), 'airygen-seo' );
			}

			++$paged;
			$posts_count = count( (array) $query->posts );
		} while ( $posts_count === $per_page );
	}

	/**
	 * Check if Action Scheduler helpers are available.
	 *
	 * @return bool
	 */
	private static function is_action_scheduler_available(): bool {
		return function_exists( 'as_enqueue_async_action' )
		&& function_exists( 'as_next_scheduled_action' )
		&& function_exists( 'as_unschedule_all_actions' );
	}

	/**
	 * Detect language from content and site locale.
	 *
	 * @return string Language code.
	 */
	private static function detect_language(): string {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : '';
		if ( '' !== $locale ) {
			$language = strtolower( substr( $locale, 0, 2 ) );
			if ( in_array( $language, array( 'zh', 'ja', 'ko' ), true ) ) {
				return 'en';
			}
			return $language;
		}

		return 'en';
	}

	/**
	 * Remove CJK characters from a text string.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private static function strip_cjk_chars( string $content ): string {
		return (string) preg_replace( '/[\\p{Han}\\p{Hiragana}\\p{Katakana}\\p{Hangul}]+/u', '', $content );
	}

	/**
	 * Remove CJK characters from a list of strings.
	 *
	 * @param array<int,string> $items Items to sanitize.
	 * @return array<int,string>
	 */
	private static function strip_cjk_from_list( array $items ): array {
		$clean = array();
		foreach ( $items as $item ) {
			$item = self::strip_cjk_chars( (string) $item );
			$item = trim( $item );
			if ( '' === $item ) {
				continue;
			}
			$clean[] = $item;
		}

		return array_values( $clean );
	}

	/**
	 * Extract focus keywords from post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,string>
	 */
	private static function extract_focus_keywords( int $post_id ): array {
		$post_data     = PostData::get( $post_id );
		$raw           = $post_data['focusKeyphrase'];
		$long_tail_raw = $post_data['focusLongTail'];

		$long_tail_items = preg_split( '/[,\\n\\r]+/', (string) $long_tail_raw );
		$long_tail_items = is_array( $long_tail_items ) ? $long_tail_items : array();

		$keywords   = array();
		$taxonomies = self::extract_taxonomy_keywords( $post_id );
		$raw        = trim( $raw );
		if ( '' !== $raw ) {
			$keywords[] = sanitize_text_field( $raw );
		}

		foreach ( $long_tail_items as $item ) {
			$item = trim( (string) $item );
			if ( '' === $item ) {
				continue;
			}
			$keywords[] = sanitize_text_field( $item );
		}

		foreach ( $taxonomies as $item ) {
			$keywords[] = $item;
		}

		$unique = array();
		foreach ( $keywords as $keyword ) {
			$key = strtolower( $keyword );
			if ( '' === $key || isset( $unique[ $key ] ) ) {
				continue;
			}
			$unique[ $key ] = $keyword;
		}

		return array_values( $unique );
	}

	/**
	 * Extract taxonomy terms (categories and tags) for focus keywords.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,string>
	 */
	private static function extract_taxonomy_keywords( int $post_id ): array {
		$terms = array();
		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			$tax_terms = get_the_terms( $post_id, $taxonomy );
			if ( empty( $tax_terms ) || is_wp_error( $tax_terms ) ) {
				continue;
			}
			foreach ( $tax_terms as $term ) {
				if ( empty( $term->name ) ) {
					continue;
				}
				$terms[] = sanitize_text_field( (string) $term->name );
			}
		}

		return $terms;
	}

	/**
	 * Extract headings (H1-H6) text from HTML content.
	 *
	 * @param string $content Raw post content.
	 * @return array<int,string>
	 */
	private static function extract_headings( string $content ): array {
		if ( '' === $content ) {
			return array();
		}

		$matches = array();
		preg_match_all( '/<h[1-6][^>]*>(.*?)<\\/h[1-6]> /is', $content, $matches );

		if ( empty( $matches[1] ) ) {
			preg_match_all( '/<h[1-6][^>]*>(.*?)<\\/h[1-6]>/is', $content, $matches );
		}

		if ( empty( $matches[1] ) ) {
			return array();
		}

		$headings = array();

		foreach ( (array) $matches[1] as $heading ) {
			$text = wp_strip_all_tags( (string) $heading );
			$text = trim( $text );
			if ( '' === $text ) {
				continue;
			}
			$headings[] = $text;
		}

		return array_values( $headings );
	}

	/**
	 * Build a simple term frequency map from provided keywords.
	 *
	 * @param array<int,string> $keywords Keywords to tokenize.
	 * @return array<string,float>
	 */
	private static function fallback_terms_from_keywords( array $keywords ): array {
		$freq = array();
		foreach ( $keywords as $keyword ) {
			$tokens = preg_split( '/[^\\p{L}\\p{N}\']+/u', (string) $keyword );
			if ( ! is_array( $tokens ) ) {
				continue;
			}
			foreach ( $tokens as $token ) {
				$token = trim( (string) $token );
				if ( '' === $token ) {
					continue;
				}
				if ( function_exists( 'mb_strtolower' ) ) {
					$token = (string) mb_strtolower( $token, 'UTF-8' );
				} else {
					$token = strtolower( $token );
				}
				if ( isset( $freq[ $token ] ) ) {
					continue;
				}
				$freq[ $token ] = 5;
			}
		}

		return $freq;
	}
}
