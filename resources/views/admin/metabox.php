<?php
/**
 * The template for the metabox in the post editing page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div id="airygen-metabox">
	<div class="airygen-metabox__item">
		<div class="airygen-metabox__item-row">
			<label for="airygen-title"><?php esc_html_e( 'Title', 'airygen-seo' ); ?></label>
			<div class="airygen-metabox__seo-status">
				<span class="seo-status__count">
					<span class="seo-status__title--current">0</span>
					<span class="airygen-metabox__row--right__count__max">/ 60</span>
				</span>
			</div>
		</div>
		<div class="airygen-metabox__item-row">
			<input type="text" id="airygen-title" 
				name="airygen_title" value="<?php echo esc_attr( $data['title'] ); ?>" />
		</div>
	</div>
	<div class="airygen-metabox__item">
		<div class="airygen-metabox__item-row">
			<label for="airygen-description"><?php esc_html_e( 'Description', 'airygen-seo' ); ?></label>
			<div class="airygen-metabox__seo-status">
				<span class="seo-status__count">
					<span class="seo-status__description--current">0</span>
					<span class="airygen-metabox__row--right__count__max">/ 160</span>
				</span>
			</div>
		</div>
		<div class="airygen-metabox__item-row">
			<textarea id="airygen-description" name="airygen_description"><?php echo esc_textarea( $data['description'] ); ?></textarea>
		</div>
	</div>
	<?php wp_nonce_field( 'airygen_create_nonce', 'airygen_metabox_nonce' ); ?>
</div>
