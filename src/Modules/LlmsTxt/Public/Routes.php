<?php
/**
 * Rewrite routes for llms.txt endpoints.
 *
 * @package Airygen\Modules\LlmsTxt\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\LlmsTxt\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers llms route query vars.
 */
final class Routes {

	private const QUERY_LLMS = 'airygen_llms';

	/**
	 * Register rewrites and query vars.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
	}

	/**
	 * Add rewrite rules.
	 *
	 * @return void
	 */
	public static function add_rewrite_rules(): void {
		add_rewrite_rule( 'llms\.txt$', 'index.php?' . self::QUERY_LLMS . '=1', 'top' );
	}

	/**
	 * Add query vars.
	 *
	 * @param array<int,string> $vars Query vars.
	 *
	 * @return array<int,string>
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_LLMS;
		return $vars;
	}

	/**
	 * Check llms index request.
	 *
	 * @return bool
	 */
	public static function is_llms_request(): bool {
		return '1' === (string) get_query_var( self::QUERY_LLMS, '' );
	}
}
