<?php
/**
 * Bootstrap file for unit tests.
 *
 * @package airygen
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$tests_dir = getenv( 'WP_PHPUNIT__DIR' ) ? getenv( 'WP_PHPUNIT__DIR' ) : 'vendor/wp-phpunit/wp-phpunit';

require $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/airygen-seo.php';
		\AirygenTest\Support\DatabaseHelpers::ensure_custom_tables();
	}
);

require $tests_dir . '/includes/bootstrap.php';
