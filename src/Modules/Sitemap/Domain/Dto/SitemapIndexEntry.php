<?php
/**
 * DTO representing sitemap index entries.
 *
 * @package Airygen\Modules\Sitemap\Domain\Dto
 */

declare(strict_types=1);

namespace Airygen\Modules\Sitemap\Domain\Dto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds location and last modification timestamp for sitemap index items.
 */
final class SitemapIndexEntry {

	/**
	 * Location URL of the sitemap index entry.
	 *
	 * @var string
	 */
	private string $loc;

	/**
	 * Optional last modified timestamp.
	 *
	 * @var string|null
	 */
	private ?string $lastmod;

	/**
	 * Constructor.
	 *
	 * @param string      $loc     URL location.
	 * @param string|null $lastmod Last modified timestamp.
	 */
	public function __construct( string $loc, ?string $lastmod ) {
		$this->loc     = $loc;
		$this->lastmod = null === $lastmod ? null : self::sanitize( $lastmod );
	}

	/**
	 * Retrieve the URL location.
	 *
	 * @return string
	 */
	public function get_loc(): string {
		return $this->loc;
	}

	/**
	 * Retrieve the last modified timestamp.
	 *
	 * @return string|null
	 */
	public function get_lastmod(): ?string {
		return $this->lastmod;
	}

	/**
	 * Sanitize timestamp ensuring ISO 8601 output.
	 *
	 * @param string $timestamp Candidate timestamp.
	 *
	 * @return string
	 */
	private static function sanitize( string $timestamp ): string {
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\+\d{2}:\d{2}|Z)$/', $timestamp ) ) {
			return $timestamp;
		}

		$parsed = strtotime( $timestamp );
		if ( false === $parsed ) {
			$parsed = time();
		}

		return gmdate( 'c', $parsed );
	}
}
