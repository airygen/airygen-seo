<?php
/**
 * Shared hooks for link counting (runs in both admin and public contexts).
 *
 * @package Airygen\Modules\LinkCounter\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkCounter\Runtime;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\LinkCounter\Domain\ContentProcessor;
use Airygen\Modules\LinkCounter\Domain\Storage;
use Airygen\Support\Debug\Logger;
use Throwable;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers global hooks for the link counter feature.
 */
final class Hooks {

	public const ACTION_HOOK  = Constants::HOOK_LINK_COUNTER_PROCESS_BACKLOG;
	public const ACTION_GROUP = 'airygen';

	/**
	 * Content processor instance.
	 *
	 * @var ContentProcessor
	 */
	private $processor;

	/**
	 * Storage helper.
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Bootstrap feature hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		$self = new self();
		$self->setup_hooks();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->processor = new ContentProcessor();
		$this->storage   = new Storage();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function setup_hooks(): void {
		add_action( 'save_post', array( $this, 'handle_save_post' ), 20, 3 );
		add_action( 'delete_post', array( $this, 'handle_delete_post' ), 10, 1 );
		add_action( self::ACTION_HOOK, array( $this, 'process_backlog' ) );
		add_action( 'init', array( $this, 'schedule_backlog_if_needed' ) );
	}

	/**
	 * Process post content and persist link counts after save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function handle_save_post( int $post_id, $post, bool $update ): void {
		unset( $update );

		if ( ! ModuleSettings::is_enabled( 'linkCounter' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post = $post instanceof WP_Post ? $post : get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( ! PostTypes::supports( $post->post_type ) ) {
			return;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
			return;
		}

		$this->storage->mark_pending( $post_id );
		if ( self::queue_backlog_processing() ) {
			return;
		}

		// Fallback when Action Scheduler is not available.
		$this->storage->mark_processing( $post_id );
		try {
			$this->processor->process( $post_id, $post->post_content ?? '' );
		} catch ( Throwable $error ) {
			$this->storage->mark_failed( $post_id );
			Logger::error(
				'link-counter',
				array(
					'message' => 'Failed processing post in fallback mode',
					'post_id' => $post_id,
					'error'   => $error->getMessage(),
				)
			);
		}
	}

	/**
	 * Clean up stored data when a post is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_delete_post( int $post_id ): void {
		if ( ! ModuleSettings::is_enabled( 'linkCounter' ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( ! PostTypes::supports( $post->post_type ) ) {
			return;
		}

		$existing_links = $this->processor->get_stored_internal_links( $post_id );

		$this->storage->cleanup( $post_id );
		$this->storage->update_incoming_links( $post_id, $existing_links );
		$this->storage->delete_meta( $post_id );
	}

	/**
	 * Ensure the backlog event is scheduled when unprocessed posts remain.
	 *
	 * @return void
	 */
	public function schedule_backlog_if_needed(): void {
		if ( ! ModuleSettings::is_enabled( 'linkCounter' ) ) {
			self::clear_scheduled_backlog();
			return;
		}

		if ( ! $this->storage->has_pending_posts() ) {
			self::clear_scheduled_backlog();
			return;
		}

		if ( ! self::queue_backlog_processing() ) {
			// Fallback for environments without Action Scheduler.
			$this->process_backlog();
		}
	}

	/**
	 * Process a batch of pending posts that have not yet been analysed.
	 *
	 * @return void
	 */
	public function process_backlog(): void {
		if ( ! ModuleSettings::is_enabled( 'linkCounter' ) ) {
			self::clear_scheduled_backlog();
			return;
		}

		$post_ids = $this->storage->get_pending_post_ids( 10 );

		if ( empty( $post_ids ) ) {
			self::clear_scheduled_backlog();
			return;
		}

		foreach ( $post_ids as $post_id ) {
			$this->storage->mark_processing( $post_id );
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				$this->storage->delete_meta( $post_id );
				continue;
			}

			if ( ! PostTypes::supports( $post->post_type ) || in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
				$this->storage->delete_meta( $post_id );
				continue;
			}

			try {
				$this->processor->process( $post_id, $post->post_content ?? '' );
			} catch ( Throwable $error ) {
				$this->storage->mark_failed( $post_id );
				Logger::error(
					'link-counter',
					array(
						'message' => 'Failed processing post in backlog batch',
						'post_id' => $post_id,
						'error'   => $error->getMessage(),
					)
				);
			}
		}

		if ( $this->storage->has_pending_posts() ) {
			if ( ! self::queue_backlog_processing() ) {
				$this->process_backlog();
			}
		} else {
			self::clear_scheduled_backlog();
		}
	}

	/**
	 * Ensure the backlog processor runs soon.
	 *
	 * @return bool
	 */
	public static function queue_backlog_processing(): bool {
		if ( ! self::is_action_scheduler_available() ) {
			return false;
		}

		if ( false === as_next_scheduled_action( self::ACTION_HOOK, array(), self::ACTION_GROUP ) ) {
			as_enqueue_async_action( self::ACTION_HOOK, array(), self::ACTION_GROUP );
		}

		return true;
	}

	/**
	 * Remove any queued backlog runs.
	 *
	 * @return void
	 */
	public static function clear_scheduled_backlog(): void {
		if ( self::is_action_scheduler_available() ) {
			as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );
		}
	}

	/**
	 * Check if Action Scheduler helpers are available.
	 *
	 * @return bool
	 */
	public static function is_action_scheduler_available(): bool {
		return function_exists( 'as_enqueue_async_action' )
		&& function_exists( 'as_next_scheduled_action' )
		&& function_exists( 'as_unschedule_all_actions' );
	}
}
