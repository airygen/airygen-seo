<?php
/**
 * Handles sitemap responses.
 *
 * @package Airygen\Modules\Sitemap\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Sitemap\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\LlmsTxt\Admin\Settings as LlmsTxtSettings;
use Airygen\Modules\LocalSeo\Admin\Settings as LocalSeoSettings;
use Airygen\Modules\Sitemap\Admin\Settings;
use Airygen\Modules\Sitemap\Domain\Dto\SitemapIndexEntry;
use Airygen\Modules\Sitemap\Domain\Dto\SitemapUrlEntry;
use Airygen\Modules\Sitemap\Domain\Service\BuildSitemap;
use WP_Query;
use WP_Term;

/**
 * Outputs sitemap XML based on query vars.
 */
final class Controller {

	private const QUERY_FLAG       = 'airygen_sitemap';
	private const QUERY_OBJECT     = 'airygen_sitemap_object';
	private const QUERY_PAGE       = 'airygen_sitemap_page';
	private const QUERY_STYLESHEET = 'airygen_sitemap_stylesheet';

	private const RESOURCE_POST_TYPE = 'post_type';
	private const RESOURCE_TAXONOMY  = 'taxonomy';

	/**
	 * Cached items-per-page value.
	 *
	 * @var int|null
	 */
	private static $items_per_page_cache = null;

	/**
	 * Register controller hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ), 0 );
	}

	/**
	 * Handle sitemap requests.
	 *
	 * @return void
	 */
	public static function handle_request(): void {
		global $wp_query;

		if ( ! isset( $wp_query->query_vars[ self::QUERY_FLAG ] ) ) {
			if ( self::maybe_handle_stylesheet_request() ) {
				return;
			}
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( self::is_local_kml_request_path( $request_uri ) ) {
				self::render_kml();
				return;
			}
			return;
		}

		$type = $wp_query->query_vars[ self::QUERY_FLAG ];

		switch ( $type ) {
			case 'index':
				self::render_index();
				break;
			case 'kml':
				self::render_kml();
				break;
			case 'content':
				$slug = isset( $wp_query->query_vars[ self::QUERY_OBJECT ] ) ? sanitize_key( $wp_query->query_vars[ self::QUERY_OBJECT ] ) : '';
				if ( '' === $slug ) {
					break;
				}

				$page = isset( $wp_query->query_vars[ self::QUERY_PAGE ] ) ? max( 1, (int) $wp_query->query_vars[ self::QUERY_PAGE ] ) : 1;
				self::render_resource( $slug, $page );
				break;
			case 'stylesheet':
				$target = isset( $wp_query->query_vars[ self::QUERY_STYLESHEET ] ) ? sanitize_key( $wp_query->query_vars[ self::QUERY_STYLESHEET ] ) : '';
				self::render_stylesheet( $target );
				break;
			default:
				break;
		}
	}

	/**
	 * Output sitemap index.
	 *
	 * @return void
	 */
	private static function render_index(): void {
		$entries = array();

		foreach ( self::enabled_post_types() as $slug ) {
			self::append_resource_entries( $entries, $slug, self::RESOURCE_POST_TYPE );
		}

		foreach ( self::enabled_taxonomies() as $slug ) {
			self::append_resource_entries( $entries, $slug, self::RESOURCE_TAXONOMY );
		}

		$kml_entry = self::build_local_kml_index_entry();
		if ( ! empty( $kml_entry ) ) {
			$entries[] = new SitemapIndexEntry(
				$kml_entry['loc'],
				$kml_entry['lastmod']
			);
		}

		foreach ( self::build_llms_index_entries() as $entry ) {
			$entries[] = $entry;
		}

		if ( empty( $entries ) ) {
			self::send_xml(
				'<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>',
				self::stylesheet_url( 'index' )
			);
			return;
		}

		self::send_xml(
			BuildSitemap::index( $entries ),
			self::stylesheet_url( 'index' )
		);
	}

	/**
	 * Output sitemap for a specific resource (post type or taxonomy).
	 *
	 * @param string $slug Resource slug.
	 * @param int    $page Page number.
	 *
	 * @return void
	 */
	private static function render_resource( string $slug, int $page ): void {
		$type = self::resolve_resource_type( $slug );

		if ( null === $type ) {
			status_header( 404 );
			self::render_empty();
			return;
		}

		$data = ( self::RESOURCE_POST_TYPE === $type )
		? self::fetch_post_type_page( $slug, $page )
		: self::fetch_taxonomy_page( $slug, $page );

		if ( empty( $data['items'] ) ) {
			status_header( 404 );
			self::render_empty();
			return;
		}

		$entries = array();

		foreach ( $data['items'] as $item ) {
			$entries[] = new SitemapUrlEntry(
				$item['loc'],
				$item['lastmod'] ?? null
			);
		}

		self::send_xml(
			BuildSitemap::urlset( $entries ),
			self::stylesheet_url( 'content' )
		);
	}

	/**
	 * Output local business KML.
	 *
	 * @return void
	 */
	private static function render_kml(): void {
		$local = LocalSeoSettings::get();
		if ( empty( $local['enabled'] ) ) {
			status_header( 404 );
			self::send_xml( self::empty_kml() );
			return;
		}

		$lat = isset( $local['latitude'] ) ? (float) $local['latitude'] : 0.0;
		$lng = isset( $local['longitude'] ) ? (float) $local['longitude'] : 0.0;
		if ( 0.0 === $lat && 0.0 === $lng ) {
			status_header( 404 );
			self::send_xml( self::empty_kml() );
			return;
		}

		$name = trim( (string) ( $local['business_name'] ?? '' ) );
		if ( '' === $name ) {
			$name = get_bloginfo( 'name' );
		}

		$address     = trim(
			sprintf(
				'%s %s %s %s %s',
				(string) ( $local['street_address'] ?? '' ),
				(string) ( $local['city'] ?? '' ),
				(string) ( $local['region'] ?? '' ),
				(string) ( $local['postal_code'] ?? '' ),
				(string) ( $local['country'] ?? '' )
			)
		);
		$description = '';
		if ( '' !== $address ) {
			$description = $address;
		}

		$coordinates = sprintf( '%F,%F,0', $lng, $lat );

		$kml = sprintf(
			'<?xml version="1.0" encoding="UTF-8"?>' .
			'<kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>%1$s</name><Placemark><name>%2$s</name><description>%3$s</description><Point><coordinates>%4$s</coordinates></Point></Placemark></Document></kml>',
			esc_xml( $name ),
			esc_xml( $name ),
			esc_xml( $description ),
			esc_xml( $coordinates )
		);

		nocache_headers();
		header( 'Content-Type: application/vnd.google-earth.kml+xml; charset=utf-8' );
		echo $kml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Append entries for the sitemap index.
	 *
	 * @param array<int, SitemapIndexEntry> $entries Entry collection to mutate.
	 * @param string                        $slug    Resource slug.
	 * @param string                        $type    Resource type.
	 *
	 * @return void
	 */
	private static function append_resource_entries( array &$entries, string $slug, string $type ): void {
		$page_data = ( self::RESOURCE_POST_TYPE === $type )
		? self::fetch_post_type_page( $slug, 1 )
		: self::fetch_taxonomy_page( $slug, 1 );

		if ( empty( $page_data['items'] ) || 0 === $page_data['max_pages'] ) {
			return;
		}

		$max_pages = max( 1, (int) $page_data['max_pages'] );

		for ( $page = 1; $page <= $max_pages; $page++ ) {
			$data = ( 1 === $page ) ? $page_data : (
				self::RESOURCE_POST_TYPE === $type
					? self::fetch_post_type_page( $slug, $page )
					: self::fetch_taxonomy_page( $slug, $page )
			);

			if ( empty( $data['items'] ) ) {
				continue;
			}

			$entries[] = new SitemapIndexEntry(
				self::resource_url( $slug, $page ),
				$data['lastmod']
			);
		}
	}

	/**
	 * Fetch a page of posts for a sitemap.
	 *
	 * @param string $slug Post type slug.
	 * @param int    $page Page number.
	 *
	 * @return array{items: array<int, array{loc: string, lastmod?: string|null}>, lastmod: string|null, max_pages: int}
	 */
	private static function fetch_post_type_page( string $slug, int $page ): array {
		$per_page = self::items_per_page();
		$query    = new WP_Query(
			array(
				'post_type'           => $slug,
				'post_status'         => 'publish',
				'orderby'             => 'modified',
				'order'               => 'DESC',
				'posts_per_page'      => $per_page,
				'paged'               => max( 1, $page ),
				'fields'              => 'ids',
				'no_found_rows'       => false,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => false,
			)
		);

		$post_ids = $query->posts;

		if ( empty( $post_ids ) ) {
			wp_reset_postdata();
			return array(
				'items'     => array(),
				'lastmod'   => null,
				'max_pages' => (int) $query->max_num_pages,
			);
		}

		$items   = array();
		$lastmod = null;

		foreach ( $post_ids as $post_id ) {
			if ( 'publish' !== get_post_status( $post_id ) ) {
				continue;
			}

			if ( get_post_field( 'post_password', $post_id ) ) {
				continue;
			}

			$permalink = get_permalink( $post_id );
			if ( ! $permalink ) {
				continue;
			}

			$modified = get_post_modified_time( 'c', true, $post_id );

			if ( null === $lastmod || ( $modified && $modified > $lastmod ) ) {
				$lastmod = $modified;
			}

			$items[] = array(
				'loc'     => $permalink,
				'lastmod' => null !== $modified ? $modified : null,
			);
		}

		wp_reset_postdata();

		return array(
			'items'     => $items,
			'lastmod'   => $lastmod,
			'max_pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Fetch a page of taxonomy terms for a sitemap.
	 *
	 * @param string $slug Taxonomy slug.
	 * @param int    $page Page number.
	 *
	 * @return array{items: array<int, array{loc: string, lastmod?: string|null}>, lastmod: string|null, max_pages: int}
	 */
	private static function fetch_taxonomy_page( string $slug, int $page ): array {
		$page          = max( 1, $page );
		$per_page      = self::items_per_page();
		$offset        = ( $page - 1 ) * $per_page;
		$settings      = self::settings();
		$exclude_empty = ! empty( $settings['exclude_empty_taxonomies'] );

		$terms = get_terms(
			array(
				'taxonomy'         => $slug,
				'hide_empty'       => $exclude_empty,
				'number'           => $per_page,
				'offset'           => $offset,
				'orderby'          => 'name',
				'order'            => 'ASC',
				'fields'           => 'all',
				'suppress_filters' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$items   = array();
		$lastmod = null;

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			if ( self::term_has_noindex( $term->term_id ) ) {
				continue;
			}

			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}

			$last_modified = get_term_meta( $term->term_id, Constants::META_TERM_LASTMOD, true );
			if ( ! is_string( $last_modified ) || '' === $last_modified ) {
				$last_modified = get_term_meta( $term->term_id, 'last_modification_time', true );
			}
			$last_modified = is_string( $last_modified ) && '' !== $last_modified ? $last_modified : null;

			if ( null === $lastmod || ( $last_modified && $last_modified > $lastmod ) ) {
				$lastmod = $last_modified;
			}

			$items[] = array(
				'loc'     => $link,
				'lastmod' => $last_modified,
			);
		}

		$total_terms = wp_count_terms(
			array(
				'taxonomy'   => $slug,
				'hide_empty' => $exclude_empty,
			)
		);

		$total_terms = is_wp_error( $total_terms ) ? count( $items ) : (int) $total_terms;
		$max_pages   = $total_terms > 0 ? (int) ceil( $total_terms / $per_page ) : 0;

		return array(
			'items'     => $items,
			'lastmod'   => $lastmod,
			'max_pages' => $max_pages,
		);
	}

	/**
	 * Resolve enabled post types.
	 *
	 * @return array<int, string>
	 */
	private static function enabled_post_types(): array {
		$settings = self::settings();
		$types    = isset( $settings['enabled_post_types'] ) && is_array( $settings['enabled_post_types'] )
		? array_values( array_filter( array_map( 'sanitize_key', $settings['enabled_post_types'] ) ) )
		: array();

		return $types;
	}

	/**
	 * Resolve enabled taxonomies.
	 *
	 * @return array<int, string>
	 */
	private static function enabled_taxonomies(): array {
		$settings   = self::settings();
		$taxonomies = isset( $settings['enabled_taxonomies'] ) && is_array( $settings['enabled_taxonomies'] )
		? array_values( array_filter( array_map( 'sanitize_key', $settings['enabled_taxonomies'] ) ) )
		: array();

		return $taxonomies;
	}

	/**
	 * Resolve resource type by slug.
	 *
	 * @param string $slug Resource slug.
	 *
	 * @return string|null
	 */
	private static function resolve_resource_type( string $slug ): ?string {
		if ( in_array( $slug, self::enabled_post_types(), true ) ) {
			return self::RESOURCE_POST_TYPE;
		}

		if ( in_array( $slug, self::enabled_taxonomies(), true ) ) {
			return self::RESOURCE_TAXONOMY;
		}

		return null;
	}

	/**
	 * Build sitemap URL for a given resource and page.
	 *
	 * @param string $slug Resource slug.
	 * @param int    $page Page number.
	 *
	 * @return string
	 */
	private static function resource_url( string $slug, int $page = 1 ): string {
		$page = max( 1, $page );
		$base = trailingslashit( home_url() ) . 'sitemap-' . $slug;

		return sprintf( '%s-%d.xml', $base, $page );
	}

	/**
	 * Send XML response with appropriate headers and exit.
	 *
	 * @param string $xml        XML payload.
	 * @param string $stylesheet Optional stylesheet URL.
	 *
	 * @return void
	 */
	private static function send_xml( string $xml, string $stylesheet = '' ): void {
		nocache_headers();
		header( 'Content-Type: application/xml; charset=utf-8' );

		if ( '' !== $stylesheet ) {
			if ( str_starts_with( $xml, '<?xml' ) ) {
				$declaration_end = strpos( $xml, '?>' );
				if ( false !== $declaration_end ) {
					$declaration = substr( $xml, 0, $declaration_end + 2 );
					$rest        = substr( $xml, $declaration_end + 2 );

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo rtrim( $declaration ) . "\n";
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo sprintf( '<?xml-stylesheet type="text/xsl" href="%s" ?>', esc_url_raw( $stylesheet ) ) . "\n";
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo ltrim( $rest );
					exit;
				}
			}

			// Fallback: emit stylesheet before XML.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo sprintf( '<?xml-stylesheet type="text/xsl" href="%s" ?>', esc_url_raw( $stylesheet ) ) . "\n";
		}

		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Output an empty XML response.
	 *
	 * @return void
	 */
	private static function render_empty(): void {
		self::send_xml(
			'<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
			self::stylesheet_url( 'content' )
		);
	}

	/**
	 * Render XSL stylesheets for sitemap output.
	 *
	 * @param string $target Target stylesheet key.
	 * @return void
	 */
	private static function render_stylesheet( string $target ): void {
		$content = self::stylesheet_content( $target );

		if ( '' === $content ) {
			status_header( 404 );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: text/xsl; charset=utf-8' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Retrieve items-per-page setting.
	 *
	 * @return int
	 */
	private static function items_per_page(): int {
		if ( null !== self::$items_per_page_cache ) {
			return self::$items_per_page_cache;
		}

		$options = self::settings();
		$value   = isset( $options['items_per_page'] ) ? (int) $options['items_per_page'] : 500;
		$value   = max( 500, min( 5000, $value ) );

		self::$items_per_page_cache = $value;

		return self::$items_per_page_cache;
	}

	/**
	 * Resolve XSL stylesheet URL for WP styling.
	 *
	 * @param string $type Stylesheet context (index or content).
	 * @return string
	 */
	private static function stylesheet_url( string $type ): string {
		$filename = ( 'index' === $type ) ? 'wp-sitemap-index.xsl' : 'wp-sitemap.xsl';
		return home_url( '/' . ltrim( $filename, '/' ) );
	}

	/**
	 * Load stylesheet contents from plugin resources.
	 *
	 * @param string $target Target stylesheet key.
	 * @return string
	 */
	private static function stylesheet_content( string $target ): string {
		switch ( $target ) {
			case 'index':
				$file = 'wp-sitemap-index.xsl';
				break;
			case 'content':
				$file = 'wp-sitemap.xsl';
				break;
			default:
				return '';
		}

		$plugin_dir = trailingslashit( dirname( __DIR__, 3 ) );
		if ( defined( 'AIRYGEN_PLUGIN_DIR' ) ) {
			$plugin_dir = trailingslashit( AIRYGEN_PLUGIN_DIR );
		}

		$path = $plugin_dir . 'resources/sitemaps/' . $file;

		if ( ! file_exists( $path ) ) {
			return '';
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return is_string( $contents ) ? $contents : '';
	}

	/**
	 * Build local.kml entry for sitemap index.
	 *
	 * @return array{loc: string, lastmod: string|null}
	 */
	private static function build_local_kml_index_entry(): array {
		$local = LocalSeoSettings::get();
		if ( empty( $local['enabled'] ) ) {
			return array();
		}
		if ( array_key_exists( 'kml_in_sitemap', $local ) && empty( $local['kml_in_sitemap'] ) ) {
			return array();
		}

		$lat = isset( $local['latitude'] ) ? (float) $local['latitude'] : 0.0;
		$lng = isset( $local['longitude'] ) ? (float) $local['longitude'] : 0.0;
		if ( 0.0 === $lat && 0.0 === $lng ) {
			return array();
		}

		return array(
			'loc'     => home_url( '/local.kml' ),
			'lastmod' => gmdate( 'c' ),
		);
	}

	/**
	 * Build llms.txt entries for sitemap index.
	 *
	 * @return array<int, SitemapIndexEntry>
	 */
	private static function build_llms_index_entries(): array {
		$settings = LlmsTxtSettings::get();
		if ( empty( $settings['enabled'] ) || empty( $settings['add_to_sitemap'] ) ) {
			return array();
		}

		$entries = array(
			new SitemapIndexEntry(
				home_url( '/llms.txt' ),
				gmdate( 'c' )
			),
		);

		$extensions = isset( $settings['extensions'] ) && is_array( $settings['extensions'] )
		? $settings['extensions']
		: array();

		foreach ( $extensions as $extension ) {
			if ( ! is_array( $extension ) || empty( $extension['enabled'] ) ) {
				continue;
			}

			$path = self::build_llms_extension_path( $extension );
			if ( '' === $path ) {
				continue;
			}

			$entries[] = new SitemapIndexEntry(
				home_url( $path ),
				gmdate( 'c' )
			);
		}

		return $entries;
	}

	/**
	 * Build public path for an llms.txt extension.
	 *
	 * @param array<string,mixed> $extension Extension settings.
	 *
	 * @return string
	 */
	private static function build_llms_extension_path( array $extension ): string {
		$filename = isset( $extension['filename'] ) ? trim( (string) $extension['filename'] ) : '';
		if ( '' === $filename ) {
			return '';
		}

		$path = isset( $extension['path'] ) ? trim( (string) $extension['path'] ) : '';
		$path = trim( $path, '/' );

		return ( '' !== $path )
		? '/' . $path . '/' . $filename
		: '/' . $filename;
	}

	/**
	 * Empty KML payload.
	 *
	 * @return string
	 */
	private static function empty_kml(): string {
		return '<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document></Document></kml>';
	}

	/**
	 * Handle stylesheet requests when rewrite rules have not been flushed.
	 *
	 * @return bool True when stylesheet was rendered.
	 */
	private static function maybe_handle_stylesheet_request(): bool {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $request_uri ) {
			return false;
		}

		$path = strtok( $request_uri, '?' );
		$path = trim( $path, '/' );

		if ( 'wp-sitemap-index.xsl' === $path ) {
			self::render_stylesheet( 'index' );
			return true;
		}

		if ( 'wp-sitemap.xsl' === $path ) {
			self::render_stylesheet( 'content' );
			return true;
		}

		return false;
	}

	/**
	 * Check whether request URI points to local.kml endpoint.
	 *
	 * @param string $request_uri Raw request URI.
	 * @return bool
	 */
	private static function is_local_kml_request_path( string $request_uri ): bool {
		if ( '' === $request_uri ) {
			return false;
		}

		$path = strtok( $request_uri, '?' );
		$path = trim( (string) $path, '/' );

		return 'local.kml' === $path;
	}

	/**
	 * Retrieve cached settings from the options table.
	 */
	private static function settings(): array {
		static $settings = null;

		if ( null === $settings ) {
			$settings = Settings::get();
		}

		return $settings;
	}

	/**
	 * Determine whether taxonomy term robots directives include noindex.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return bool
	 */
	private static function term_has_noindex( int $term_id ): bool {
		$robots = (string) get_term_meta( $term_id, Constants::META_TERM_ROBOTS, true );
		if ( '' === $robots ) {
			$robots = (string) get_term_meta( $term_id, Constants::META_TERM_WC_ROBOTS, true );
		}

		if ( '' === $robots ) {
			return false;
		}

		return str_contains( strtolower( $robots ), 'noindex' );
	}
}
