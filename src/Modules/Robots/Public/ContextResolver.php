<?php
/**
 * Resolves context for robots directives.
 *
 * @package Airygen\Modules\Robots\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Robots\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\AuthorSeo\Admin\Settings as AuthorSeoSettings;
use Airygen\Modules\Robots\Admin\Settings;
use Airygen\Support\Meta\PostData;

/**
 * Gathers input data for the robots domain service.
 */
final class ContextResolver {

	/**
	 * Build context array for current request.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_for_entry(): array {
		$options = Settings::get();

		$enable_default = isset( $options['enable_default_meta'] ) ? (bool) $options['enable_default_meta'] : true;
		$context        = array(
			'global'           => $options['default_directive'] ?? '',
			'suppress_default' => ! $enable_default,
			'is_search'        => is_search(),
			'is_attachment'    => is_attachment(),
			'is_404'           => is_404(),
			'is_author'        => is_author(),
			'author_noindex'   => false,
		);

		if ( $context['is_author'] && ModuleSettings::is_enabled( 'authorSeo' ) ) {
			$author_settings           = AuthorSeoSettings::get();
			$context['author_noindex'] = ! empty( $author_settings['enabled'] ) && ! empty( $author_settings['noindex_author_archives'] );
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id ) {
				$context['per_post'] = PostData::get_field( $post_id, 'robots' );
			}
		}

		if ( ( is_category() || is_tag() || is_tax() ) && empty( $context['per_post'] ) ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$context['per_post'] = get_term_meta( $term->term_id, Constants::META_TERM_ROBOTS, true );
			}
		}

		return $context;
	}

	/**
	 * Build context for robots.txt.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_for_robots_txt(): array {
		$options = Settings::get();

		return array(
			'base_rules'       => array(),
			'additional_rules' => $options['additional_rules'] ?? array(),
		);
	}
}
