<?php
/**
 * Classic editor metabox for OnPage SEO fields.
 *
 * @package Airygen\Modules\OnPageSeo\Admin\Fields
 */

declare(strict_types=1);

namespace Airygen\Modules\OnPageSeo\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Support\Meta\PostData;
use WP_Post;

/**
 * Renders the classic editor metabox UI.
 */
final class Metabox {

	/**
	 * Render the metabox markup.
	 *
	 * @param WP_Post $post Current post.
	 *
	 * @return void
	 */
	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'airygen_onpage_save', 'airygen_onpage_nonce' );

		$post_data   = PostData::get( $post->ID );
		$title       = $post_data['title'];
		$description = $post_data['description'];
		$keyphrase   = $post_data['focusKeyphrase'];

		?>
		<div class="airygen-metabox">
			<p>
				<label for="airygen_title"><strong><?php esc_html_e( 'SEO Title', 'airygen-seo' ); ?></strong></label>
				<input type="text" name="airygen_title" id="airygen_title" class="widefat" value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<p>
				<label for="airygen_description"><strong><?php esc_html_e( 'Meta Description', 'airygen-seo' ); ?></strong></label>
				<textarea name="airygen_description" id="airygen_description" rows="4" class="widefat"><?php echo esc_textarea( $description ); ?></textarea>
			</p>
			<p>
				<label for="airygen_focus_keyphrase"><strong><?php esc_html_e( 'Focus Keyphrase', 'airygen-seo' ); ?></strong></label>
				<input type="text" name="airygen_focus_keyphrase" id="airygen_focus_keyphrase" class="widefat" value="<?php echo esc_attr( $keyphrase ); ?>" />
			</p>
		</div>
		<?php
	}
}
