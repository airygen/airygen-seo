<?php
/**
 * Router script to enable WordPress to serve static files correctly in Docker environment.
 */

// phpcs:disable

$root        = __DIR__;
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path        = parse_url( $request_uri, PHP_URL_PATH ) ?? '/';
$path        = urldecode( $path );

/**
 * Resolve and validate a file path under document root.
 *
 * @param string $root_path Document root.
 * @param string $uri_path  Request path.
 *
 * @return string|null
 */
function ag_resolve_target( string $root_path, string $uri_path ): ?string {
	$target = realpath( $root_path . $uri_path );
	if ( ! $target ) {
		return null;
	}

	if ( strncmp( $target, $root_path, strlen( $root_path ) ) !== 0 ) {
		return null;
	}

	if ( is_dir( $target ) ) {
		$index = realpath( $target . '/index.php' );
		if ( $index && is_file( $index ) && strncmp( $index, $root_path, strlen( $root_path ) ) === 0 ) {
			return $index;
		}
	}

	return is_file( $target ) ? $target : null;
}

/**
 * Serve static file or prepare PHP script execution.
 *
 * @param string $file_path   Absolute file path.
 * @param string $script_path Script path for server globals.
 *
 * @return string|null PHP file path to require in global scope, or null for static response.
 */
function ag_serve_target( string $file_path, string $script_path ): ?string {
	if ( 'php' === strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) ) {
		$_SERVER['SCRIPT_FILENAME'] = $file_path;
		$_SERVER['SCRIPT_NAME']     = $script_path;
		$_SERVER['PHP_SELF']        = $script_path;
		return $file_path;
	}

	$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
	$mime_map  = array(
		'js'   => 'application/javascript; charset=UTF-8',
		'mjs'  => 'application/javascript; charset=UTF-8',
		'css'  => 'text/css; charset=UTF-8',
		'json' => 'application/json; charset=UTF-8',
		'map'  => 'application/json; charset=UTF-8',
		'svg'  => 'image/svg+xml',
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'woff' => 'font/woff',
		'woff2'=> 'font/woff2',
		'ttf'  => 'font/ttf',
		'eot'  => 'application/vnd.ms-fontobject',
		'ico'  => 'image/x-icon',
	);

	$mime = $mime_map[ $extension ] ?? null;
	if ( ! is_string( $mime ) || '' === $mime ) {
		$mime = function_exists( 'mime_content_type' ) ? mime_content_type( $file_path ) : null;
	}
	if ( is_string( $mime ) && '' !== $mime ) {
		header( 'Content-Type: ' . $mime );
	}
	readfile( $file_path );
	exit;

	return null;
}

// Normalize double slashes and prevent directory traversal.
$target = ag_resolve_target( $root, $path );
if ( $target ) {
	$php_file = ag_serve_target( $target, $path );
	if ( is_string( $php_file ) && '' !== $php_file ) {
		require $php_file;
		exit;
	}
}

// Multisite subdirectory mode may emit static asset URLs prefixed by site path
// like /ja/wp-includes/...; strip the first segment and map to real core paths.
// Keep wp-admin requests untouched so WordPress can resolve subsite context.
if ( preg_match( '#^/[^/]+/(wp-(?:includes|content)/.*)$#', $path, $matches ) ) {
	$rewritten_path = '/' . $matches[1];
	$rewritten      = ag_resolve_target( $root, $rewritten_path );
	if ( $rewritten ) {
		$php_file = ag_serve_target( $rewritten, $rewritten_path );
		if ( is_string( $php_file ) && '' !== $php_file ) {
			require $php_file;
			exit;
		}
	}
}

// In multisite subdirectory mode, admin URLs are /{site}/wp-admin/... .
// Execute the core admin script from /wp-admin/... while preserving REQUEST_URI.
if ( preg_match( '#^/[^/]+(/wp-admin(?:/.*)?)$#', $path, $matches ) ) {
	$rewritten_path = $matches[1];
	$rewritten      = ag_resolve_target( $root, $rewritten_path );
	if ( $rewritten ) {
		$php_file = ag_serve_target( $rewritten, $rewritten_path );
		if ( is_string( $php_file ) && '' !== $php_file ) {
			require $php_file;
			exit;
		}
	}
}

// Multisite subdirectory login/signup endpoints can be emitted as /{site}/wp-login.php
// and should map to core root scripts.
if ( preg_match( '#^/[^/]+/(wp-login\.php|wp-signup\.php|wp-activate\.php)$#', $path, $matches ) ) {
	$rewritten_path = '/' . $matches[1];
	$rewritten      = ag_resolve_target( $root, $rewritten_path );
	if ( $rewritten ) {
		$php_file = ag_serve_target( $rewritten, $rewritten_path );
		if ( is_string( $php_file ) && '' !== $php_file ) {
			require $php_file;
			exit;
		}
	}
}

$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';

require $root . '/index.php';
