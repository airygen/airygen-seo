<?php
/**
 * Generate an metabox at the bottom of the post editing page,
 * as well as handle the saving of settings.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Airygen\Admin;

use Airygen\Support\Meta\PostData;
use Airygen\Support\TemplateRenderer;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metabox controller.
 */
class Metabox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
	}

	/**
	 * Add the metabox to the post editing page.
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'airygen_post_metabox',
			__( 'Airygen SEO', 'airygen-seo' ),
			array( $this, 'render_meta_box' ),
			array( 'post', 'page' ),
			'normal',
			'default'
		);
	}

	/**
	 * Render the metabox in the post editing page.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( WP_Post $post ) {
		$post_data   = PostData::get( $post->ID );
		$title       = $post_data['title'];
		$description = $post_data['description'];

		TemplateRenderer::render(
			'admin/metabox',
			array(
				'title'       => $title,
				'description' => $description,
			)
		);
	}

	/**
	 * Save the metabox settings.
	 *
	 * @param int $post_id Current post ID.
	 */
	public function save_meta_box( int $post_id ) {
		if (
			! isset( $_POST['airygen_metabox_nonce'] ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['airygen_metabox_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'airygen_create_nonce' ) ) {
			return;
		}

		if ( isset( $_POST['airygen_title'] ) ) {
			$title = trim( sanitize_text_field( wp_unslash( $_POST['airygen_title'] ) ) );
			PostData::save_field( $post_id, 'title', $title );
		}

		if ( isset( $_POST['airygen_description'] ) ) {
			$description = trim( sanitize_text_field( wp_unslash( $_POST['airygen_description'] ) ) );
			PostData::save_field( $post_id, 'description', $description );
		}
	}
}
