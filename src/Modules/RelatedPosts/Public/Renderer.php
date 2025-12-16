<?php
/**
 * Frontend renderer for Related Posts.
 *
 * @package Airygen\Modules\RelatedPosts\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\RelatedPosts\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders related post cards.
 */
final class Renderer {

	/**
	 * @param int                 $post_id  Current post ID.
	 * @param array<string,mixed> $settings Module settings.
	 *
	 * @return string
	 */
	public static function render( int $post_id, array $settings ): string {
		$preset = isset( $settings['display_preset'] ) ? (string) $settings['display_preset'] : '3x2';
		$limit  = self::preset_data_limit( $preset );

		$related_ids = RecommendationProvider::top_related_post_ids( $post_id, $limit );
		if ( empty( $related_ids ) ) {
			return '';
		}

		$posts = get_posts(
			array(
				'post_type'              => 'any',
				'post__in'               => $related_ids,
				'orderby'                => 'post__in',
				'posts_per_page'         => $limit,
				'post_status'            => 'publish',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
			)
		);
		if ( empty( $posts ) ) {
			return '';
		}

		$grid_style     = self::grid_style( (string) ( $settings['display_preset'] ?? '3x2' ) );
		$template       = (string) ( $settings['template'] ?? 'single_column' );
		$footer_columns = isset( $settings['footer_columns'] ) ? (int) $settings['footer_columns'] : 3;
		$footer_columns = max( 1, min( 3, $footer_columns ) );
		$footer_regions = self::footer_regions( $footer_columns );
		$order          = isset( $settings['block_order'] ) && is_array( $settings['block_order'] )
		? array_values( array_map( 'strval', $settings['block_order'] ) )
		: array( 'featured_image', 'title', 'excerpt', 'author', 'date' );
		if ( empty( $order ) ) {
			return '';
		}
		$regions       = isset( $settings['block_regions'] ) && is_array( $settings['block_regions'] )
		? $settings['block_regions']
		: array();
		$title_enabled = ! isset( $settings['title_enabled'] ) || ! empty( $settings['title_enabled'] );
		$title_text    = isset( $settings['title_text'] ) && is_string( $settings['title_text'] ) && '' !== trim( $settings['title_text'] )
		? $settings['title_text']
		: __( 'Related Posts', 'airygen-seo' );
		$title_level   = isset( $settings['title_level'] ) ? strtolower( (string) $settings['title_level'] ) : 'h2';
		if ( ! in_array( $title_level, array( 'h2', 'h3', 'h4' ), true ) ) {
			$title_level = 'h2';
		}

		ob_start();
		?>
		<section class="airygen-auto-related-posts" aria-label="<?php echo esc_attr__( 'Related Posts', 'airygen-seo' ); ?>">
		<?php if ( $title_enabled ) : ?>
				<<?php echo esc_html( $title_level ); ?> class="airygen-auto-related-posts__section-title"><?php echo esc_html( $title_text ); ?></<?php echo esc_html( $title_level ); ?>>
			<?php endif; ?>
			<div class="airygen-auto-related-posts__grid" style="<?php echo esc_attr( $grid_style ); ?>">
		<?php foreach ( $posts as $post ) : ?>
			<?php
			$post_id_item = (int) $post->ID;
			$title        = get_the_title( $post_id_item );
			$link         = get_permalink( $post_id_item );
			if ( ! is_string( $link ) || '' === $link ) {
				continue;
			}
			?>
					<article class="airygen-auto-related-posts__card airygen-auto-related-posts__card--<?php echo esc_attr( $template ); ?>">
			<?php if ( 'sidebar_left' === $template ) : ?>
							<div class="airygen-auto-related-posts__layout">
								<div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--left-sidebar">
				<?php self::render_region( 'left_sidebar', $order, $regions, $post_id_item, $title, $link, $settings ); ?>
								</div>
								<div class="airygen-auto-related-posts__main">
									<div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--header">
				<?php self::render_region( 'header', $order, $regions, $post_id_item, $title, $link, $settings ); ?>
									</div>
									<div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--body">
				<?php self::render_region( 'body', $order, $regions, $post_id_item, $title, $link, $settings ); ?>
									</div>
									<div class="airygen-auto-related-posts__footer-grid">
				<?php foreach ( $footer_regions as $footer_region ) : ?>
											<div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--<?php echo esc_attr( str_replace( '_', '-', $footer_region ) ); ?>">
					<?php self::render_region( $footer_region, $order, $regions, $post_id_item, $title, $link, $settings ); ?>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						<?php else : ?>
							<div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--header">
							<?php self::render_region( 'header', $order, $regions, $post_id_item, $title, $link, $settings ); ?>
							</div>
							<div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--body">
							<?php self::render_region( 'body', $order, $regions, $post_id_item, $title, $link, $settings ); ?>
							</div>
							<div class="airygen-auto-related-posts__footer-grid">
							<?php foreach ( $footer_regions as $footer_region ) : ?>
									<div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--<?php echo esc_attr( str_replace( '_', '-', $footer_region ) ); ?>">
								<?php self::render_region( $footer_region, $order, $regions, $post_id_item, $title, $link, $settings ); ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param string              $region Region key.
	 * @param array<int,string>   $order Block order.
	 * @param array<string,mixed> $regions Block regions.
	 * @param int                 $post_id Post ID.
	 * @param string              $title Post title.
	 * @param string              $link Post URL.
	 * @param array<string,mixed> $settings Module settings.
	 *
	 * @return void
	 */
	private static function render_region(
		string $region,
		array $order,
		array $regions,
		int $post_id,
		string $title,
		string $link,
		array $settings
	): void {
		foreach ( $order as $block_id ) {
			$block_region = isset( $regions[ $block_id ] ) && is_string( $regions[ $block_id ] )
			? (string) $regions[ $block_id ]
			: 'body';
			if ( 'footer' === $block_region ) {
				$block_region = 'footer_left';
			}
			if ( 'single_column' === (string) ( $settings['template'] ?? 'single_column' ) && 'left_sidebar' === $block_region ) {
				$block_region = 'body';
			}
			if ( $block_region !== $region ) {
				continue;
			}
			self::render_block( $block_id, $post_id, $title, $link, $settings );
		}
	}

	/**
	 * @param string              $block_id Block key.
	 * @param int                 $post_id Post ID.
	 * @param string              $title Post title.
	 * @param string              $link Post permalink.
	 * @param array<string,mixed> $settings Module settings.
	 *
	 * @return void
	 */
	private static function render_block( string $block_id, int $post_id, string $title, string $link, array $settings ): void {
		if ( 'featured_image' === $block_id ) {
			$size  = isset( $settings['featured_image_size'] ) ? (string) $settings['featured_image_size'] : 'medium';
			$image = get_the_post_thumbnail( $post_id, $size, array( 'class' => 'airygen-auto-related-posts__thumb' ) );
			if ( '' !== $image ) {
				echo '<a class="airygen-auto-related-posts__thumb-link" href="' . esc_url( $link ) . '">' . $image . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return;
		}

		if ( 'title' === $block_id ) {
			echo '<h3 class="airygen-auto-related-posts__title"><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h3>';
			return;
		}

		if ( 'excerpt' === $block_id ) {
			$excerpt = get_the_excerpt( $post_id );
			if ( '' === trim( $excerpt ) ) {
				return;
			}
			$max_chars = isset( $settings['excerpt_max_chars'] ) ? (int) $settings['excerpt_max_chars'] : 140;
			$max_chars = max( 30, min( 1000, $max_chars ) );
			$text      = wp_strip_all_tags( $excerpt );
			if ( mb_strlen( $text ) > $max_chars ) {
				$text = mb_substr( $text, 0, $max_chars ) . '...';
			}
			$fade_class = ! empty( $settings['excerpt_fade_mask'] ) ? ' airygen-auto-related-posts__excerpt--fade' : '';
			echo '<p class="airygen-auto-related-posts__excerpt' . esc_attr( $fade_class ) . '">' . esc_html( $text ) . '</p>';
			return;
		}

		if ( 'author' === $block_id ) {
			$author_name = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
			if ( '' !== trim( (string) $author_name ) ) {
				echo '<p class="airygen-auto-related-posts__author">' . esc_html( (string) $author_name ) . '</p>';
			}
			return;
		}

		if ( 'date' === $block_id ) {
			$timestamp = get_post_time( 'U', true, $post_id );
			if ( ! is_int( $timestamp ) || $timestamp <= 0 ) {
				return;
			}
			echo '<p class="airygen-auto-related-posts__date"><time datetime="' . esc_attr( gmdate( 'c', $timestamp ) ) . '">' . esc_html( wp_date( get_option( 'date_format' ), $timestamp ) ) . '</time></p>';
		}
	}

	/**
	 * @param string $preset Grid preset.
	 *
	 * @return string
	 */
	private static function grid_style( string $preset ): string {
		if ( '2x2' === $preset ) {
			return 'display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;';
		}
		if ( '4x2' === $preset ) {
			return 'display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;';
		}
		if ( '4x1' === $preset ) {
			return 'display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;';
		}
		if ( '1x4' === $preset ) {
			return 'display:grid;grid-template-columns:repeat(1,minmax(0,1fr));gap:16px;';
		}
		return 'display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;';
	}

	/**
	 * @param string $preset Display preset.
	 *
	 * @return int
	 */
	private static function preset_data_limit( string $preset ): int {
		if ( '4x2' === $preset ) {
			return 8;
		}
		if ( '2x2' === $preset || '4x1' === $preset || '1x4' === $preset ) {
			return 4;
		}
		return 6;
	}

	/**
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	public static function enqueue_styles( array $settings ): void {
		$grid_container              = isset( $settings['grid_container'] ) && is_array( $settings['grid_container'] ) ? $settings['grid_container'] : array();
		$post_container              = isset( $settings['post_container'] ) && is_array( $settings['post_container'] ) ? $settings['post_container'] : array();
		$header_container            = isset( $settings['header_container'] ) && is_array( $settings['header_container'] ) ? $settings['header_container'] : array();
		$header_title                = isset( $settings['header_title'] ) && is_array( $settings['header_title'] ) ? $settings['header_title'] : array();
		$grid_border_width_top       = isset( $grid_container['border_width_top'] ) ? (int) $grid_container['border_width_top'] : 0;
		$grid_border_width_right     = isset( $grid_container['border_width_right'] ) ? (int) $grid_container['border_width_right'] : 0;
		$grid_border_width_bottom    = isset( $grid_container['border_width_bottom'] ) ? (int) $grid_container['border_width_bottom'] : 0;
		$grid_border_width_left      = isset( $grid_container['border_width_left'] ) ? (int) $grid_container['border_width_left'] : 0;
		$grid_border_radius          = isset( $grid_container['border_radius'] ) ? (int) $grid_container['border_radius'] : 0;
		$grid_border_style           = isset( $grid_container['border_style'] ) ? (string) $grid_container['border_style'] : 'solid';
		$grid_border_color           = isset( $grid_container['border_color'] ) ? (string) $grid_container['border_color'] : '#e2e8f0';
		$grid_bg_color               = isset( $grid_container['bg_color'] ) ? (string) $grid_container['bg_color'] : 'transparent';
		$grid_padding_top            = isset( $grid_container['padding_top'] ) ? (int) $grid_container['padding_top'] : 0;
		$grid_padding_right          = isset( $grid_container['padding_right'] ) ? (int) $grid_container['padding_right'] : 0;
		$grid_padding_bottom         = isset( $grid_container['padding_bottom'] ) ? (int) $grid_container['padding_bottom'] : 0;
		$grid_padding_left           = isset( $grid_container['padding_left'] ) ? (int) $grid_container['padding_left'] : 0;
		$grid_gap                    = isset( $grid_container['gap'] ) ? (int) $grid_container['gap'] : 16;
		$post_border_width_top       = isset( $post_container['border_width_top'] ) ? (int) $post_container['border_width_top'] : 1;
		$post_border_width_right     = isset( $post_container['border_width_right'] ) ? (int) $post_container['border_width_right'] : 1;
		$post_border_width_bottom    = isset( $post_container['border_width_bottom'] ) ? (int) $post_container['border_width_bottom'] : 1;
		$post_border_width_left      = isset( $post_container['border_width_left'] ) ? (int) $post_container['border_width_left'] : 1;
		$post_border_radius          = isset( $post_container['border_radius'] ) ? (int) $post_container['border_radius'] : 0;
		$post_border_style           = isset( $post_container['border_style'] ) ? (string) $post_container['border_style'] : 'solid';
		$post_border_color           = isset( $post_container['border_color'] ) ? (string) $post_container['border_color'] : '#e2e8f0';
		$post_bg_color               = isset( $post_container['bg_color'] ) ? (string) $post_container['bg_color'] : '#ffffff';
		$post_padding_top            = isset( $post_container['padding_top'] ) ? (int) $post_container['padding_top'] : 12;
		$post_padding_right          = isset( $post_container['padding_right'] ) ? (int) $post_container['padding_right'] : 12;
		$post_padding_bottom         = isset( $post_container['padding_bottom'] ) ? (int) $post_container['padding_bottom'] : 12;
		$post_padding_left           = isset( $post_container['padding_left'] ) ? (int) $post_container['padding_left'] : 12;
		$post_gap                    = isset( $post_container['gap'] ) ? (int) $post_container['gap'] : 10;
		$title_size                  = isset( $settings['title_font_size'] ) ? (int) $settings['title_font_size'] : 18;
		$title_color                 = isset( $settings['title_color'] ) ? (string) $settings['title_color'] : '#0f172a';
		$title_bold                  = isset( $settings['title_bold'] ) ? (bool) $settings['title_bold'] : true;
		$title_italic                = isset( $settings['title_italic'] ) ? (bool) $settings['title_italic'] : false;
		$excerpt_size                = isset( $settings['excerpt_font_size'] ) ? (int) $settings['excerpt_font_size'] : 14;
		$excerpt_color               = isset( $settings['excerpt_color'] ) ? (string) $settings['excerpt_color'] : '#334155';
		$excerpt_fade                = isset( $settings['excerpt_fade_color'] ) ? (string) $settings['excerpt_fade_color'] : '#ffffff';
		$excerpt_mask_height         = isset( $settings['excerpt_mask_height'] ) ? (int) $settings['excerpt_mask_height'] : 40;
		$excerpt_mask_height         = max( 8, min( 200, $excerpt_mask_height ) );
		$author_size                 = isset( $settings['author_font_size'] ) ? (int) $settings['author_font_size'] : 13;
		$author_color                = isset( $settings['author_color'] ) ? (string) $settings['author_color'] : '#475569';
		$author_bold                 = isset( $settings['author_bold'] ) ? (bool) $settings['author_bold'] : false;
		$author_italic               = isset( $settings['author_italic'] ) ? (bool) $settings['author_italic'] : false;
		$image_radius                = isset( $settings['featured_image_radius'] ) ? (int) $settings['featured_image_radius'] : 4;
		$section_border_width_top    = isset( $header_container['border_width_top'] ) ? (int) $header_container['border_width_top'] : 0;
		$section_border_width_right  = isset( $header_container['border_width_right'] ) ? (int) $header_container['border_width_right'] : 0;
		$section_border_width_bottom = isset( $header_container['border_width_bottom'] ) ? (int) $header_container['border_width_bottom'] : 0;
		$section_border_width_left   = isset( $header_container['border_width_left'] ) ? (int) $header_container['border_width_left'] : 0;
		$section_border_radius       = isset( $header_container['border_radius'] ) ? (int) $header_container['border_radius'] : 0;
		$section_border_style        = isset( $header_container['border_style'] ) ? (string) $header_container['border_style'] : 'solid';
		$section_border_color        = isset( $header_container['border_color'] ) ? (string) $header_container['border_color'] : '#e2e8f0';
		$section_bg_color            = isset( $header_container['bg_color'] ) ? (string) $header_container['bg_color'] : 'transparent';
		$section_padding_top         = isset( $header_container['padding_top'] ) ? (int) $header_container['padding_top'] : 0;
		$section_padding_right       = isset( $header_container['padding_right'] ) ? (int) $header_container['padding_right'] : 0;
		$section_padding_bottom      = isset( $header_container['padding_bottom'] ) ? (int) $header_container['padding_bottom'] : 0;
		$section_padding_left        = isset( $header_container['padding_left'] ) ? (int) $header_container['padding_left'] : 0;
		$section_margin_top          = isset( $header_container['margin_top'] ) ? (int) $header_container['margin_top'] : 0;
		$section_margin_right        = isset( $header_container['margin_right'] ) ? (int) $header_container['margin_right'] : 0;
		$section_margin_bottom       = isset( $header_container['margin_bottom'] ) ? (int) $header_container['margin_bottom'] : 12;
		$section_margin_left         = isset( $header_container['margin_left'] ) ? (int) $header_container['margin_left'] : 0;
		$section_title_style         = isset( $header_title['font_style'] ) && is_array( $header_title['font_style'] ) ? $header_title['font_style'] : array();
		$section_title_color         = isset( $header_title['color'] ) ? (string) $header_title['color'] : '#0f172a';
		$section_title_size          = isset( $header_title['font_size'] ) ? (int) $header_title['font_size'] : 18;
		$footer_columns              = isset( $settings['footer_columns'] ) ? (int) $settings['footer_columns'] : 3;
		$footer_columns              = max( 1, min( 3, $footer_columns ) );
		$grid_border_width_top       = max( 0, min( 50, $grid_border_width_top ) );
		$grid_border_width_right     = max( 0, min( 50, $grid_border_width_right ) );
		$grid_border_width_bottom    = max( 0, min( 50, $grid_border_width_bottom ) );
		$grid_border_width_left      = max( 0, min( 50, $grid_border_width_left ) );
		$grid_border_radius          = max( 0, min( 64, $grid_border_radius ) );
		$grid_padding_top            = max( 0, min( 50, $grid_padding_top ) );
		$grid_padding_right          = max( 0, min( 50, $grid_padding_right ) );
		$grid_padding_bottom         = max( 0, min( 50, $grid_padding_bottom ) );
		$grid_padding_left           = max( 0, min( 50, $grid_padding_left ) );
		$grid_gap                    = max( 0, min( 64, $grid_gap ) );
		$post_border_width_top       = max( 0, min( 50, $post_border_width_top ) );
		$post_border_width_right     = max( 0, min( 50, $post_border_width_right ) );
		$post_border_width_bottom    = max( 0, min( 50, $post_border_width_bottom ) );
		$post_border_width_left      = max( 0, min( 50, $post_border_width_left ) );
		$post_border_radius          = max( 0, min( 64, $post_border_radius ) );
		$post_padding_top            = max( 0, min( 50, $post_padding_top ) );
		$post_padding_right          = max( 0, min( 50, $post_padding_right ) );
		$post_padding_bottom         = max( 0, min( 50, $post_padding_bottom ) );
		$post_padding_left           = max( 0, min( 50, $post_padding_left ) );
		$post_gap                    = max( 0, min( 64, $post_gap ) );
		$image_radius                = max( 0, min( 64, $image_radius ) );
		$section_border_width_top    = max( 0, min( 12, $section_border_width_top ) );
		$section_border_width_right  = max( 0, min( 12, $section_border_width_right ) );
		$section_border_width_bottom = max( 0, min( 12, $section_border_width_bottom ) );
		$section_border_width_left   = max( 0, min( 12, $section_border_width_left ) );
		$section_border_radius       = max( 0, min( 64, $section_border_radius ) );
		$section_padding_top         = max( 0, min( 64, $section_padding_top ) );
		$section_padding_right       = max( 0, min( 64, $section_padding_right ) );
		$section_padding_bottom      = max( 0, min( 64, $section_padding_bottom ) );
		$section_padding_left        = max( 0, min( 64, $section_padding_left ) );
		$section_margin_top          = max( 0, min( 64, $section_margin_top ) );
		$section_margin_right        = max( 0, min( 64, $section_margin_right ) );
		$section_margin_bottom       = max( 0, min( 64, $section_margin_bottom ) );
		$section_margin_left         = max( 0, min( 64, $section_margin_left ) );
		$section_title_size          = max( 10, min( 64, $section_title_size ) );
		$css                         = '.airygen-auto-related-posts__grid{gap:' . esc_html( (string) $grid_gap ) . 'px;padding:' . esc_html( (string) $grid_padding_top ) . 'px ' . esc_html( (string) $grid_padding_right ) . 'px ' . esc_html( (string) $grid_padding_bottom ) . 'px ' . esc_html( (string) $grid_padding_left ) . 'px;border-style:' . esc_html( $grid_border_style ) . ';border-color:' . esc_html( $grid_border_color ) . ';border-width:' . esc_html( (string) $grid_border_width_top ) . 'px ' . esc_html( (string) $grid_border_width_right ) . 'px ' . esc_html( (string) $grid_border_width_bottom ) . 'px ' . esc_html( (string) $grid_border_width_left ) . 'px;border-radius:' . esc_html( (string) $grid_border_radius ) . 'px;background:' . esc_html( $grid_bg_color ) . ';box-sizing:border-box;}';
		$css                        .= '.airygen-auto-related-posts__section-title{margin:' . esc_html( (string) $section_margin_top ) . 'px ' . esc_html( (string) $section_margin_right ) . 'px ' . esc_html( (string) $section_margin_bottom ) . 'px ' . esc_html( (string) $section_margin_left ) . 'px;padding:' . esc_html( (string) $section_padding_top ) . 'px ' . esc_html( (string) $section_padding_right ) . 'px ' . esc_html( (string) $section_padding_bottom ) . 'px ' . esc_html( (string) $section_padding_left ) . 'px;border-width:' . esc_html( (string) $section_border_width_top ) . 'px ' . esc_html( (string) $section_border_width_right ) . 'px ' . esc_html( (string) $section_border_width_bottom ) . 'px ' . esc_html( (string) $section_border_width_left ) . 'px;border-style:' . esc_html( $section_border_style ) . ';border-color:' . esc_html( $section_border_color ) . ';border-radius:' . esc_html( (string) $section_border_radius ) . 'px;background:' . esc_html( $section_bg_color ) . ';color:' . esc_html( $section_title_color ) . ';font-size:' . esc_html( (string) $section_title_size ) . 'px;font-weight:' . ( ! empty( $section_title_style['bold'] ) ? '700' : '400' ) . ';font-style:' . ( ! empty( $section_title_style['italic'] ) ? 'italic' : 'normal' ) . ';text-decoration:' . ( ! empty( $section_title_style['underline'] ) ? 'underline' : 'none' ) . ';}';
		$css                        .= '.airygen-auto-related-posts__card{padding:' . esc_html( (string) $post_padding_top ) . 'px ' . esc_html( (string) $post_padding_right ) . 'px ' . esc_html( (string) $post_padding_bottom ) . 'px ' . esc_html( (string) $post_padding_left ) . 'px;border-style:' . esc_html( $post_border_style ) . ';border-color:' . esc_html( $post_border_color ) . ';border-width:' . esc_html( (string) $post_border_width_top ) . 'px ' . esc_html( (string) $post_border_width_right ) . 'px ' . esc_html( (string) $post_border_width_bottom ) . 'px ' . esc_html( (string) $post_border_width_left ) . 'px;border-radius:' . esc_html( (string) $post_border_radius ) . 'px;background:' . esc_html( $post_bg_color ) . ';display:flex;flex-direction:column;gap:' . esc_html( (string) $post_gap ) . 'px;box-sizing:border-box;}';
		$css                        .= '.airygen-auto-related-posts__layout{display:grid;grid-template-columns:minmax(0,1fr) 3fr;gap:' . esc_html( (string) $post_gap ) . 'px;align-items:start;height:100%;}';
		$css                        .= '.airygen-auto-related-posts__main{display:flex;flex-direction:column;gap:' . esc_html( (string) $post_gap ) . 'px;}';
		$css                        .= '.airygen-auto-related-posts__card--sidebar_left .airygen-auto-related-posts__main{height:100%;}';
		$css                        .= '.airygen-auto-related-posts__card--sidebar_left .airygen-auto-related-posts__footer-grid{margin-top:auto;}';
		$css                        .= '.airygen-auto-related-posts__region{display:flex;flex-direction:column;gap:' . esc_html( (string) $post_gap ) . 'px;}';
		$css                        .= '.airygen-auto-related-posts__footer-grid{display:grid;grid-template-columns:repeat(' . esc_html( (string) $footer_columns ) . ',minmax(0,1fr));gap:' . esc_html( (string) $post_gap ) . 'px;align-items:start;}';
		$css                        .= '.airygen-auto-related-posts__region--footer-left{text-align:left;}';
		$css                        .= '.airygen-auto-related-posts__region--footer-center{text-align:center;}';
		$css                        .= '.airygen-auto-related-posts__region--footer-right{text-align:right;}';
		$css                        .= '.airygen-auto-related-posts__thumb-link{display:block;overflow:hidden;border-radius:' . esc_html( (string) $image_radius ) . 'px;}';
		$css                        .= '.airygen-auto-related-posts__thumb{display:block;max-width:100%;height:auto;border-radius:' . esc_html( (string) $image_radius ) . 'px;}';
		$css                        .= '.airygen-auto-related-posts__title{margin:0;font-size:' . esc_html( (string) $title_size ) . 'px;color:' . esc_html( $title_color ) . ';font-weight:' . ( $title_bold ? '700' : '400' ) . ';font-style:' . ( $title_italic ? 'italic' : 'normal' ) . ';}';
		$css                        .= '.airygen-auto-related-posts__title a{color:inherit;text-decoration:none;}';
		$css                        .= '.airygen-auto-related-posts__excerpt{margin:0;font-size:' . esc_html( (string) $excerpt_size ) . 'px;color:' . esc_html( $excerpt_color ) . ';line-height:1.5;}';
		$css                        .= '.airygen-auto-related-posts__excerpt--fade{position:relative;max-height:4.5em;overflow:hidden;}';
		$css                        .= '.airygen-auto-related-posts__excerpt--fade::after{content:"";position:absolute;left:0;right:0;bottom:0;height:' . esc_html( (string) $excerpt_mask_height ) . 'px;background:linear-gradient(to bottom,rgba(255,255,255,0),' . esc_html( $excerpt_fade ) . ');}';
		$css                        .= '.airygen-auto-related-posts__author{margin:0;font-size:' . esc_html( (string) $author_size ) . 'px;color:' . esc_html( $author_color ) . ';font-weight:' . ( $author_bold ? '700' : '400' ) . ';font-style:' . ( $author_italic ? 'italic' : 'normal' ) . ';}';
		$css                        .= '.airygen-auto-related-posts__date{margin:0;font-size:' . esc_html( (string) $author_size ) . 'px;color:' . esc_html( $author_color ) . ';font-weight:' . ( $author_bold ? '700' : '400' ) . ';font-style:' . ( $author_italic ? 'italic' : 'normal' ) . ';}';
		$css                        .= '@media(max-width:1024px){.airygen-auto-related-posts__grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;}.airygen-auto-related-posts__layout{grid-template-columns:1fr;}}';
		$css                        .= '@media(max-width:640px){.airygen-auto-related-posts__grid{grid-template-columns:repeat(1,minmax(0,1fr))!important;}}';

		wp_register_style( 'airygen-related-posts-inline', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- No external file.
		wp_enqueue_style( 'airygen-related-posts-inline' );
		wp_add_inline_style( 'airygen-related-posts-inline', $css );
	}

	/**
	 * @param int $footer_columns Footer column count.
	 *
	 * @return array<int,string>
	 */
	private static function footer_regions( int $footer_columns ): array {
		if ( 1 === $footer_columns ) {
			return array( 'footer_left' );
		}
		if ( 2 === $footer_columns ) {
			return array( 'footer_left', 'footer_right' );
		}

		return array( 'footer_left', 'footer_center', 'footer_right' );
	}
}
