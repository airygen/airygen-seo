<?php
/**
 * Orchestrates WordPress hooks for the IndexNow module.
 *
 * @package Airygen\Modules\InstantIndexing\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Runtime;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\InstantIndexing\Admin\Settings;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates WP hooks for Instant Indexing runtime behaviours.
 */
final class Hooks {

	public const ACTION_GROUP    = 'airygen';
	public const PROCESS_ACTION  = Constants::HOOK_INDEXNOW_PROCESS_QUEUE;
	public const BACKFILL_ACTION = Constants::HOOK_INDEXNOW_BACKFILL;

	/**
	 * Queue storage helper.
	 *
	 * @var QueueRepository
	 */
	private $queue;

	/**
	 * Batch processor.
	 *
	 * @var Processor
	 */
	private $processor;

	/**
	 * Backfill job helper.
	 *
	 * @var BackfillJob
	 */
	private $backfill;

	/**
	 * Key responder for serving the {key}.txt file.
	 *
	 * @var KeyResponder
	 */
	private $key_responder;

	/**
	 * Post meta key tracking the last Instant Indexing submission timestamp.
	 */
	private const META_INITIAL_SUBMISSION = Constants::META_INDEXNOW_INITIAL_SUBMIT;

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		$queue     = new QueueRepository();
		$quota     = new QuotaTracker();
		$logger    = new ResponseLogger();
		$processor = new Processor( $queue, $quota, $logger );
		$backfill  = new BackfillJob( $queue );
		$responder = new KeyResponder();
		$self      = new self( $queue, $processor, $backfill, $responder );
		$self->setup_hooks();
	}

	/**
	 * Constructor.
	 *
	 * @param QueueRepository $queue         Queue repository instance.
	 * @param Processor       $processor     Queue processor.
	 * @param BackfillJob     $backfill      Backfill helper.
	 * @param KeyResponder    $key_responder Key responder.
	 */
	public function __construct( QueueRepository $queue, Processor $processor, BackfillJob $backfill, KeyResponder $key_responder ) {
		$this->queue         = $queue;
		$this->processor     = $processor;
		$this->backfill      = $backfill;
		$this->key_responder = $key_responder;
	}

	/**
	 * Wire WordPress hooks.
	 *
	 * @return void
	 */
	private function setup_hooks(): void {
		add_action( 'init', array( $this, 'bootstrap' ) );
		add_action( 'save_post', array( $this, 'handle_save_post' ), 50, 3 );
		add_action( 'before_delete_post', array( $this, 'handle_delete_post' ) );
		add_action( self::PROCESS_ACTION, array( $this, 'run_processor' ) );
		add_action( self::BACKFILL_ACTION, array( $this, 'handle_backfill_action' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'serve_key_file' ) );
	}

	/**
	 * Ensure defaults exist and queue processing if pending.
	 *
	 * @return void
	 */
	public function bootstrap(): void {
		if ( ! ModuleSettings::is_enabled( 'instantIndexing' ) ) {
			self::clear_scheduled_queue();
			return;
		}

		Settings::ensure_exists();
		if ( $this->queue->summary()->to_array()['pending'] > 0 ) {
			self::queue_queue_processing();
		}
	}

	/**
	 * Queue posts for submission after save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
		// phpcs:disable
	public function handle_save_post( int $post_id, $post, bool $update ): void {
		if ( ! $this->should_auto_submit() ) {
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

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! PostTypes::supports( $post->post_type ) ) {
			return;
		}

		$permalink = get_permalink( $post );
		if ( ! $permalink ) {
			return;
		}

		$host = $this->extract_host( $permalink );
		if ( '' === $host ) {
			return;
		}

		$last_submit = get_post_meta( $post_id, self::META_INITIAL_SUBMISSION, true );
		if ( ! empty( $last_submit ) ) {
			$settings      = Settings::get();
			$cooldown_days = max( 1, (int) ( $settings['retry_cooldown_days'] ?? 0 ) );
			$cooldown_time = strtotime( $last_submit ) + ( $cooldown_days * DAY_IN_SECONDS );
			if ( time() < $cooldown_time ) {
				return;
			}
		}

		if ( $this->queue->enqueue( $host, $permalink, 'update', 'auto' ) ) {
			update_post_meta( $post_id, self::META_INITIAL_SUBMISSION, current_time( 'mysql' ) );
			self::queue_queue_processing();
		}
	}
	// phpcs:enable

	/**
	 * Queue deletions for IndexNow.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_delete_post( int $post_id ): void {
		if ( ! $this->should_auto_submit() ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( ! PostTypes::supports( $post->post_type ) ) {
			return;
		}

		$permalink = get_permalink( $post );
		if ( ! $permalink ) {
			return;
		}

		$host = $this->extract_host( $permalink );
		if ( '' === $host ) {
			return;
		}

		if ( $this->queue->enqueue( $host, $permalink, 'delete', 'auto' ) ) {
			self::queue_queue_processing();
		}
	}

	/**
	 * Process the queue (triggered by cron/action scheduler).
	 *
	 * @return void
	 */
	public function run_processor(): void {
		if ( ! ModuleSettings::is_enabled( 'instantIndexing' ) || ! $this->has_key() ) {
			return;
		}

		$this->processor->process();
	}

	/**
	 * Handle asynchronous backfill batches.
	 *
	 * @param array<string, mixed> $args Action arguments.
	 * @return void
	 */
	public function handle_backfill_action( array $args ): void {
		if ( ! ModuleSettings::is_enabled( 'instantIndexing' ) ) {
			return;
		}

		$types = isset( $args['post_types'] ) && is_array( $args['post_types'] ) ? $args['post_types'] : array();
		$page  = isset( $args['page'] ) ? (int) $args['page'] : 1;
		$this->backfill->run_page( $types, $page );
	}

	/**
	 * Conditionally serve the {key}.txt response.
	 *
	 * @return void
	 */
	public function serve_key_file(): void {
		$this->key_responder->maybe_output();
	}

	/**
	 * Determine whether automatic submissions are enabled.
	 *
	 * @return bool
	 */
	private function should_auto_submit(): bool {
		if ( ! ModuleSettings::is_enabled( 'instantIndexing' ) ) {
			return false;
		}

		$settings = Settings::get();
		return Settings::is_enabled( $settings ) && ! empty( $settings['auto_submit'] ) && $this->has_key();
	}

	/**
	 * Whether a generated key exists (rotated at least once).
	 *
	 * @return bool
	 */
	private function has_key(): bool {
		$settings = Settings::get();
		$key      = isset( $settings['key'] ) ? (string) $settings['key'] : '';
		return '' !== $key;
	}

	/**
	 * Extract and normalize the host from a URL.
	 *
	 * @param string $url Absolute URL.
	 * @return string
	 */
	private function extract_host( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Ensure the queue processor is scheduled soon.
	 *
	 * @return void
	 */
	public static function queue_queue_processing(): void {
		if ( self::is_action_scheduler_available() ) {
			if ( false === as_next_scheduled_action( self::PROCESS_ACTION, array(), self::ACTION_GROUP ) ) {
				as_enqueue_async_action( self::PROCESS_ACTION, array(), self::ACTION_GROUP );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::PROCESS_ACTION ) ) {
			wp_schedule_single_event( time() + 60, self::PROCESS_ACTION );
		}
	}

	/**
	 * Remove all scheduled queue/backfill actions.
	 *
	 * @return void
	 */
	public static function clear_scheduled_queue(): void {
		if ( self::is_action_scheduler_available() ) {
			as_unschedule_all_actions( self::PROCESS_ACTION, array(), self::ACTION_GROUP );
			as_unschedule_all_actions( self::BACKFILL_ACTION, array(), self::ACTION_GROUP );
			return;
		}

		wp_clear_scheduled_hook( self::PROCESS_ACTION );
		wp_clear_scheduled_hook( self::BACKFILL_ACTION );
	}

	/**
	 * Whether Action Scheduler helpers are available.
	 *
	 * @return bool
	 */
	private static function is_action_scheduler_available(): bool {
		return function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_next_scheduled_action' );
	}
}
