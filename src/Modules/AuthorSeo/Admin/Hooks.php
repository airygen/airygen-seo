<?php
/**
 * Admin profile hooks for Author SEO.
 *
 * @package Airygen\Modules\AuthorSeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\AuthorSeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use WP_User;

/**
 * Handles Author SEO user profile fields.
 */
final class Hooks {

	/**
	 * User meta key for author-specific social profile URLs.
	 */
	private const USER_META_SOCIAL_PROFILES = Constants::USER_META_SOCIAL_PROFILES;

	/**
	 * Nonce action for profile save.
	 */
	private const NONCE_ACTION = 'airygen_author_seo_profile';

	/**
	 * Nonce field name.
	 */
	private const NONCE_FIELD = 'airygen_author_seo_profile_nonce';

	/**
	 * Register profile hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'show_user_profile', array( __CLASS__, 'render_profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_fields' ) );
	}

	/**
	 * Render custom Author SEO fields on profile page.
	 *
	 * @param WP_User $user User object.
	 *
	 * @return void
	 */
	public static function render_profile_fields( WP_User $user ): void {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$profiles_raw = get_user_meta( (int) $user->ID, self::USER_META_SOCIAL_PROFILES, true );
		$profiles     = self::normalize_social_profiles( $profiles_raw );
		$value        = implode( "\n", $profiles );
		?>
		<h2><?php esc_html_e( 'Airygen Author SEO', 'airygen-seo' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th>
					<label for="airygen-author-social-profiles"><?php esc_html_e( 'Author social profiles', 'airygen-seo' ); ?></label>
				</th>
				<td>
					<textarea
						name="airygen_author_social_profiles"
						id="airygen-author-social-profiles"
						rows="4"
						class="large-text"
						placeholder="https://x.com/username&#10;https://linkedin.com/in/username"
					><?php echo esc_textarea( $value ); ?></textarea>
					<p class="description">
		<?php esc_html_e( 'One URL per line. These links override the global Author SEO social profiles for this author.', 'airygen-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
	}

	/**
	 * Save Author SEO user profile fields.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return void
	 */
	public static function save_profile_fields( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		$raw      = isset( $_POST['airygen_author_social_profiles'] ) ? sanitize_textarea_field( wp_unslash( $_POST['airygen_author_social_profiles'] ) ) : '';
		$profiles = self::normalize_social_profiles( $raw );

		if ( empty( $profiles ) ) {
			delete_user_meta( $user_id, self::USER_META_SOCIAL_PROFILES );
			return;
		}

		update_user_meta( $user_id, self::USER_META_SOCIAL_PROFILES, $profiles );
	}

	/**
	 * Normalize social profile payload into cleaned URL list.
	 *
	 * @param mixed $profiles Raw payload.
	 *
	 * @return array<int, string>
	 */
	private static function normalize_social_profiles( $profiles ): array {
		$values = array();

		if ( is_array( $profiles ) ) {
			foreach ( $profiles as $profile ) {
				if ( ! is_string( $profile ) ) {
					continue;
				}
				$values[] = $profile;
			}
		} elseif ( is_string( $profiles ) ) {
			$split  = preg_split( '/[\r\n,]+/', $profiles );
			$values = false !== $split ? $split : array();
		}

		$normalized = array();
		foreach ( $values as $value ) {
			$url = esc_url_raw( trim( (string) $value ) );
			if ( '' === $url ) {
				continue;
			}

			if ( in_array( $url, $normalized, true ) ) {
				continue;
			}

			$normalized[] = $url;
		}

		return $normalized;
	}
}
