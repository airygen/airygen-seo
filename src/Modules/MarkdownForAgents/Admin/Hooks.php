<?php
/**
 * Admin hooks for Markdown for Agents.
 *
 * @package Airygen\Modules\MarkdownForAgents\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\MarkdownForAgents\Admin;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\MarkdownForAgents\Application\MarkdownExporter;
use Airygen\Modules\MarkdownForAgents\Infrastructure\MarkdownPostRepository;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps settings and post-sync hooks.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'bootstrap' ) );
		add_filter( Constants::HOOK_EDITOR_CONFIG, array( __CLASS__, 'extend_editor_config' ) );
		add_action( 'save_post', array( __CLASS__, 'handle_save_post' ), 20, 3 );
		add_action( 'trashed_post', array( __CLASS__, 'handle_trashed_post' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'handle_trashed_post' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'handle_untrashed_post' ) );
	}

	/**
	 * Initialize option and table.
	 *
	 * @return void
	 */
	public static function bootstrap(): void {
		Settings::ensure_exists();
		( new MarkdownPostRepository() )->ensure_table();
	}

	/**
	 * Expose Markdown for Agents config to editor bundles.
	 *
	 * @param array<string,mixed> $config Existing editor config.
	 *
	 * @return array<string,mixed>
	 */
	public static function extend_editor_config( array $config ): array {
		$settings                    = Settings::get();
		$config['markdownForAgents'] = array(
			'promptsForAgents' => ! empty( $settings['prompts_for_agents'] ),
		);

		return $config;
	}

	/**
	 * Sync markdown snapshot when post is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is update.
	 *
	 * @return void
	 */
	public static function handle_save_post( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );
		if ( ! ModuleSettings::is_enabled( 'markdownForAgents' ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$allowed_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
		? array_values( array_filter( array_map( 'strval', $settings['post_types'] ) ) )
		: array();

		if ( ! in_array( (string) $post->post_type, $allowed_types, true ) ) {
			return;
		}

		$payload = MarkdownExporter::export( $post_id, $settings );
		if ( ! is_array( $payload ) ) {
			return;
		}

		( new MarkdownPostRepository() )->upsert( $payload );
	}

	/**
	 * Mark a snapshot as deleted.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function handle_trashed_post( int $post_id ): void {
		( new MarkdownPostRepository() )->mark_deleted( $post_id );
	}

	/**
	 * Re-sync after restoring from trash.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function handle_untrashed_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		self::handle_save_post( $post_id, $post, true );
	}
}
