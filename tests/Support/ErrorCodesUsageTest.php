<?php
/**
 * Enforce centralized WP_Error code constants.
 *
 * @package AirygenTest\Support
 */

declare(strict_types=1);

namespace AirygenTest\Support;

use AirygenTest\BaseTestCase;

/**
 * @coversNothing
 */
final class ErrorCodesUsageTest extends BaseTestCase {

	/**
	 * Ensure new WP_Error helpers do not use raw string codes.
	 *
	 * @return void
	 */
	public function test_error_codes_are_constant_references(): void {
		$root  = dirname( __DIR__, 2 );
		$files = $this->collect_php_files( $root . '/src' );

		$violations = array();
		foreach ( $files as $file ) {
			$content = $this->read_file_contents( $file );
			$lines   = preg_split( '/\R/', $content );
			if ( ! is_array( $lines ) ) {
				continue;
			}

			foreach ( $lines as $index => $line ) {
				if ( preg_match( "/\\bWP_Error\\s*\\(\\s*'[^']+'/", $line ) ) {
					$violations[] = $file . ':' . (string) ( $index + 1 ) . ' -> ' . trim( $line );
				}
				if ( preg_match( "/\\bto_wp_error\\s*\\(\\s*'[^']+'/", $line ) ) {
					$violations[] = $file . ':' . (string) ( $index + 1 ) . ' -> ' . trim( $line );
				}
			}
		}

		$this->assertSame(
			array(),
			$violations,
			"Found raw WP_Error code strings:\n" . implode( "\n", $violations )
		);
	}

	/**
	 * Collect all PHP files under a directory.
	 *
	 * @param string $root Root directory.
	 * @return array<int, string>
	 */
	private function collect_php_files( string $root ): array {
		$paths = array();
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root ) );
		foreach ( $it as $file ) {
			if ( ! $file instanceof \SplFileInfo ) {
				continue;
			}
			if ( ! $file->isFile() ) {
				continue;
			}
			if ( 'php' !== strtolower( (string) $file->getExtension() ) ) {
				continue;
			}
			$paths[] = (string) $file->getPathname();
		}
		sort( $paths );
		return $paths;
	}

	/**
	 * Read file content without relying on WordPress filesystem classes.
	 *
	 * @param string $path File path.
	 * @return string
	 */
	private function read_file_contents( string $path ): string {
		$handle  = new \SplFileObject( $path, 'r' );
		$content = '';
		while ( ! $handle->eof() ) {
			$content .= (string) $handle->fgets();
		}

		return $content;
	}
}
