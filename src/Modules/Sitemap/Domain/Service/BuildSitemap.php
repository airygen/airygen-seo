<?php
/**
 * Domain service for building sitemap XML.
 *
 * @package Airygen\Modules\Sitemap\Domain\Service
 */

declare(strict_types=1);

namespace Airygen\Modules\Sitemap\Domain\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Sitemap\Domain\Dto\SitemapIndexEntry;
use Airygen\Modules\Sitemap\Domain\Dto\SitemapUrlEntry;

/**
 * Renders XML strings for sitemap index and URL sets.
 */
final class BuildSitemap {

	/**
	 * Build sitemap index XML.
	 *
	 * @param array<int, SitemapIndexEntry> $entries Entries to include.
	 *
	 * @return string
	 */
	public static function index( array $entries ): string {
		$xml               = new \DOMDocument( '1.0', 'UTF-8' );
		$xml->formatOutput = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument API uses camelCase.

		$root = $xml->createElementNS( 'http://www.sitemaps.org/schemas/sitemap/0.9', 'sitemapindex' );
		$xml->appendChild( $root );

		foreach ( $entries as $entry ) {
			if ( ! $entry instanceof SitemapIndexEntry ) {
				continue;
			}

			$sitemap = $xml->createElement( 'sitemap' );

			$loc = $xml->createElement( 'loc', self::escape( $entry->get_loc() ) );
			$sitemap->appendChild( $loc );

			if ( $entry->get_lastmod() ) {
				$lastmod = $xml->createElement( 'lastmod', $entry->get_lastmod() );
				$sitemap->appendChild( $lastmod );
			}

			$root->appendChild( $sitemap );
		}

		return self::render( $xml );
	}

	/**
	 * Build a URL set sitemap XML.
	 *
	 * @param array<int, SitemapUrlEntry> $entries Entries to include.
	 *
	 * @return string
	 */
	public static function urlset( array $entries ): string {
		$xml               = new \DOMDocument( '1.0', 'UTF-8' );
		$xml->formatOutput = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument API uses camelCase.

		$root = $xml->createElementNS( 'http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset' );
		$xml->appendChild( $root );

		foreach ( $entries as $entry ) {
			if ( ! $entry instanceof SitemapUrlEntry ) {
				continue;
			}

			$url = $xml->createElement( 'url' );

			$loc = $xml->createElement( 'loc', self::escape( $entry->get_loc() ) );
			$url->appendChild( $loc );

			if ( $entry->get_lastmod() ) {
				$lastmod = $xml->createElement( 'lastmod', $entry->get_lastmod() );
				$url->appendChild( $lastmod );
			}

			$root->appendChild( $url );
		}

		return self::render( $xml );
	}

	/**
	 * Escape text nodes.
	 *
	 * @param string $value Input value.
	 *
	 * @return string
	 */
	private static function escape( string $value ): string {
		return htmlspecialchars( $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
	}

	/**
	 * Render a DOMDocument as XML string with header.
	 *
	 * @param \DOMDocument $xml Document instance.
	 *
	 * @return string
	 */
	private static function render( \DOMDocument $xml ): string {
		$result = $xml->saveXML();

		return false === $result ? '' : $result;
	}
}
