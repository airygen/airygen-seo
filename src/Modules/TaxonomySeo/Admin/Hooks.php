<?php
/**
 * Admin hooks for Taxonomy SEO fields.
 *
 * @package Airygen\Modules\TaxonomySeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\TaxonomySeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use WP_Taxonomy;

/**
 * Registers taxonomy edit fields and save handlers.
 */
final class Hooks {

	private const NONCE_ACTION = 'airygen_taxonomy_seo_term_save';
	private const NONCE_FIELD  = 'airygen_taxonomy_seo_nonce';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! ModuleSettings::is_enabled( 'taxonomySeo' ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'dispatch_extension_assets' ) );

		$taxonomies = Settings::available_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'render_add_fields' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'render_edit_fields' ), 10, 2 );
			add_action( "created_{$taxonomy}", array( __CLASS__, 'save_term_meta' ) );
			add_action( "edited_{$taxonomy}", array( __CLASS__, 'save_term_meta' ) );
		}
	}


	/**
	 * Render add-term fields.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public static function render_add_fields( string $taxonomy ): void {
		if ( ! self::is_taxonomy_enabled( $taxonomy ) ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		echo '<div class="form-field"><label for="airygen-term-title">' . esc_html__( 'Meta title', 'airygen-seo' ) . '</label>';
		echo '<input type="text" id="airygen-term-title" name="airygen_term_title" value="" />';
		echo '<p>' . esc_html__( 'Custom SEO title for this taxonomy page. Leave blank to use the default value from settings.', 'airygen-seo' ) . '</p></div>';

		echo '<div class="form-field"><label for="airygen-term-description">' . esc_html__( 'Meta description', 'airygen-seo' ) . '</label>';
		echo '<textarea id="airygen-term-description" name="airygen_term_description" rows="4"></textarea>';
		echo '<p>' . esc_html__( 'Short summary used in search results. Leave blank to use the default value from settings.', 'airygen-seo' ) . '</p></div>';

		echo '<div class="form-field"><label for="airygen-term-canonical">' . esc_html__( 'Canonical URL', 'airygen-seo' ) . '</label>';
		echo '<input type="url" id="airygen-term-canonical" name="airygen_term_canonical" value="" />';
		echo '<p>' . esc_html__( 'Leave empty to use the default taxonomy archive URL.', 'airygen-seo' ) . '</p></div>';

		echo '<div class="form-field"><label for="airygen-term-robots">' . esc_html__( 'Robots directives', 'airygen-seo' ) . '</label>';
		echo '<select id="airygen-term-robots" name="airygen_term_robots">';
		echo '<option value="">' . esc_html__( 'Use defaults', 'airygen-seo' ) . '</option>';
		echo '<option value="index,follow">index,follow</option>';
		echo '<option value="noindex,follow">noindex,follow</option>';
		echo '<option value="index,nofollow">index,nofollow</option>';
		echo '<option value="noindex,nofollow">noindex,nofollow</option>';
		echo '</select>';
		echo '</div>';
	}

	/**
	 * Render edit-term fields.
	 *
	 * @param \WP_Term $term     Term object.
	 * @param string   $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public static function render_edit_fields( \WP_Term $term, string $taxonomy ): void {
		if ( ! self::is_taxonomy_enabled( $taxonomy ) ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$title       = (string) get_term_meta( $term->term_id, Constants::META_TERM_TITLE, true );
		$description = (string) get_term_meta( $term->term_id, Constants::META_TERM_DESCRIPTION, true );
		$canonical   = (string) get_term_meta( $term->term_id, Constants::META_TERM_CANONICAL, true );
		$robots      = (string) get_term_meta( $term->term_id, Constants::META_TERM_ROBOTS, true );

		echo '<tr class="form-field">';
		echo '<th scope="row"><label for="airygen-term-title">' . esc_html__( 'Meta title', 'airygen-seo' ) . '</label></th>';
		echo '<td><input type="text" id="airygen-term-title" name="airygen_term_title" value="' . esc_attr( $title ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Custom SEO title for this taxonomy page. Leave blank to use the default value from settings.', 'airygen-seo' ) . '</p></td></tr>';

		echo '<tr class="form-field">';
		echo '<th scope="row"><label for="airygen-term-description">' . esc_html__( 'Meta description', 'airygen-seo' ) . '</label></th>';
		echo '<td><textarea id="airygen-term-description" name="airygen_term_description" rows="4" class="large-text">' . esc_textarea( $description ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Short summary used in search results. Leave blank to use the default value from settings.', 'airygen-seo' ) . '</p></td></tr>';

		echo '<tr class="form-field">';
		echo '<th scope="row"><label for="airygen-term-canonical">' . esc_html__( 'Canonical URL', 'airygen-seo' ) . '</label></th>';
		echo '<td><input type="url" id="airygen-term-canonical" name="airygen_term_canonical" value="' . esc_attr( $canonical ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Leave empty to use the default taxonomy archive URL.', 'airygen-seo' ) . '</p></td></tr>';

		echo '<tr class="form-field">';
		echo '<th scope="row"><label for="airygen-term-robots">' . esc_html__( 'Robots directives', 'airygen-seo' ) . '</label></th>';
		echo '<td><select id="airygen-term-robots" name="airygen_term_robots">';
		self::render_option( '', $robots, __( 'Use defaults', 'airygen-seo' ) );
		self::render_option( 'index,follow', $robots, 'index,follow' );
		self::render_option( 'noindex,follow', $robots, 'noindex,follow' );
		self::render_option( 'index,nofollow', $robots, 'index,nofollow' );
		self::render_option( 'noindex,nofollow', $robots, 'noindex,nofollow' );
		echo '</select></td></tr>';

		do_action( Constants::HOOK_TAXONOMY_SEO_EDIT_FIELDS, $term, $taxonomy ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.
	}

	/**
	 * Dispatch taxonomy SEO extension assets for the current term screen.
	 *
	 * @param string $hook Current admin hook.
	 *
	 * @return void
	 */
	public static function dispatch_extension_assets( string $hook ): void {
		$term = self::current_term_from_screen( $hook );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		do_action( Constants::HOOK_TAXONOMY_SEO_ASSETS, $term, (string) $term->taxonomy ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.
	}

	/**
	 * Save term meta values.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return void
	 */
	public static function save_term_meta( int $term_id ): void {
		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		if ( ! self::is_taxonomy_enabled( $term->taxonomy ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		$taxonomy_object = get_taxonomy( $term->taxonomy );
		if ( ! $taxonomy_object instanceof WP_Taxonomy ) {
			return;
		}

		$capability = isset( $taxonomy_object->cap->manage_terms ) ? (string) $taxonomy_object->cap->manage_terms : 'manage_categories';
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		$title       = self::posted_text( 'airygen_term_title' );
		$description = self::posted_textarea( 'airygen_term_description' );
		$canonical   = self::posted_url( 'airygen_term_canonical' );
		$robots      = self::posted_robots( 'airygen_term_robots' );

		self::persist_term_meta( $term_id, Constants::META_TERM_TITLE, $title );
		self::persist_term_meta( $term_id, Constants::META_TERM_DESCRIPTION, $description );
		self::persist_term_meta( $term_id, Constants::META_TERM_CANONICAL, $canonical );
		self::persist_term_meta( $term_id, Constants::META_TERM_ROBOTS, $robots );
		update_term_meta( $term_id, Constants::META_TERM_LASTMOD, gmdate( 'c' ) );
	}

	/**
	 * Determine whether a taxonomy is enabled in settings.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return bool
	 */
	private static function is_taxonomy_enabled( string $taxonomy ): bool {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		if ( ! isset( $settings['enabled_taxonomies'] ) || ! is_array( $settings['enabled_taxonomies'] ) ) {
			return false;
		}

		return in_array( $taxonomy, $settings['enabled_taxonomies'], true );
	}

	/**
	 * Resolve the current term from a taxonomy edit screen.
	 *
	 * @param string $hook Current admin hook.
	 *
	 * @return \WP_Term|null
	 */
	private static function current_term_from_screen( string $hook ): ?\WP_Term {
		if ( 'term.php' !== $hook ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen context.
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( (string) $_GET['taxonomy'] ) ) : '';
		if ( '' === $taxonomy || ! self::is_taxonomy_enabled( $taxonomy ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen context.
		$term_id = isset( $_GET['tag_ID'] ) ? absint( wp_unslash( (string) $_GET['tag_ID'] ) ) : 0;
		if ( $term_id <= 0 ) {
			return null;
		}

		$term = get_term( $term_id, $taxonomy );

		return $term instanceof \WP_Term ? $term : null;
	}

	/**
	 * Render select option.
	 *
	 * @param string $value    Option value.
	 * @param string $selected Selected value.
	 * @param string $label    Label text.
	 *
	 * @return void
	 */
	private static function render_option( string $value, string $selected, string $label ): void {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $value ),
			selected( $selected, $value, false ),
			esc_html( $label )
		);
	}

	/**
	 * Read and sanitize plain text input.
	 *
	 * @param string $key POST key.
	 *
	 * @return string
	 */
	private static function posted_text( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_term_meta().
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_term_meta().
		return mb_substr( sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ), 0, 180 );
	}

	/**
	 * Read and sanitize textarea input.
	 *
	 * @param string $key POST key.
	 *
	 * @return string
	 */
	private static function posted_textarea( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_term_meta().
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_term_meta().
		return mb_substr( sanitize_textarea_field( wp_unslash( (string) $_POST[ $key ] ) ), 0, 220 );
	}

	/**
	 * Read and sanitize URL input.
	 *
	 * @param string $key POST key.
	 *
	 * @return string
	 */
	private static function posted_url( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_term_meta().
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_term_meta().
		return esc_url_raw( trim( sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) ) );
	}

	/**
	 * Read and sanitize robots directive input.
	 *
	 * @param string $key POST key.
	 *
	 * @return string
	 */
	private static function posted_robots( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_term_meta().
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_term_meta().
		$value   = strtolower( trim( sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) ) );
		$allowed = array(
			'',
			'index,follow',
			'noindex,follow',
			'index,nofollow',
			'noindex,nofollow',
		);

		if ( ! in_array( $value, $allowed, true ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Persist term meta, deleting empty values.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Meta key.
	 * @param string $value   Meta value.
	 *
	 * @return void
	 */
	private static function persist_term_meta( int $term_id, string $key, string $value ): void {
		if ( '' === $value ) {
			delete_term_meta( $term_id, $key );
			return;
		}

		update_term_meta( $term_id, $key, $value );
	}
}
