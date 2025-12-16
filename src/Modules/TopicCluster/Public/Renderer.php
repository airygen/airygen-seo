<?php
/**
 * Topic Cluster front-end renderer.
 *
 * @package Airygen\Modules\TopicCluster\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\TopicCluster\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Support\Database\WpDbAdapter;

/**
 * Builds level-aware Topic Cluster markup.
 */
final class Renderer {

	/**
	 * Build front-end CSS for Topic Cluster output.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return string
	 */
	public static function build_css( array $settings ): string {
		$style                  = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();
		$border_style           = isset( $style['border_style'] ) ? (string) $style['border_style'] : 'solid';
		$border_color           = isset( $style['border_color'] ) ? (string) $style['border_color'] : '#cbd5e1';
		$border_width_top       = isset( $style['border_width_top'] ) ? (int) $style['border_width_top'] : 1;
		$border_width_right     = isset( $style['border_width_right'] ) ? (int) $style['border_width_right'] : 1;
		$border_width_bottom    = isset( $style['border_width_bottom'] ) ? (int) $style['border_width_bottom'] : 1;
		$border_width_left      = isset( $style['border_width_left'] ) ? (int) $style['border_width_left'] : 1;
		$border_radius          = isset( $style['border_radius'] ) ? (int) $style['border_radius'] : 8;
		$padding_top            = isset( $style['padding_top'] ) ? (int) $style['padding_top'] : 16;
		$padding_right          = isset( $style['padding_right'] ) ? (int) $style['padding_right'] : 16;
		$padding_bottom         = isset( $style['padding_bottom'] ) ? (int) $style['padding_bottom'] : 16;
		$padding_left           = isset( $style['padding_left'] ) ? (int) $style['padding_left'] : 16;
		$margin_top             = isset( $style['margin_top'] ) ? (int) $style['margin_top'] : 0;
		$margin_right           = isset( $style['margin_right'] ) ? (int) $style['margin_right'] : 0;
		$margin_bottom          = isset( $style['margin_bottom'] ) ? (int) $style['margin_bottom'] : 0;
		$margin_left            = isset( $style['margin_left'] ) ? (int) $style['margin_left'] : 0;
		$bg_color               = isset( $style['bg_color'] ) ? (string) $style['bg_color'] : '#f8fafc';
		$item_text              = isset( $style['item_text_color'] ) ? (string) $style['item_text_color'] : '#0f172a';
		$item_font_size         = isset( $style['item_font_size'] ) ? (int) $style['item_font_size'] : 16;
		$item_gap               = isset( $style['item_gap'] ) ? (int) $style['item_gap'] : 10;
		$item_weight            = ! empty( $style['item_bold'] ) ? '700' : '400';
		$item_font_style        = ! empty( $style['item_italic'] ) ? 'italic' : 'normal';
		$item_decoration        = ! empty( $style['item_underline'] ) ? 'underline' : 'none';
		$item_list_style        = isset( $style['item_list_style'] ) ? (string) $style['item_list_style'] : 'none';
		$header_container       = isset( $style['header_container'] ) && is_array( $style['header_container'] ) ? $style['header_container'] : array();
		$header_title           = isset( $style['header_title'] ) && is_array( $style['header_title'] ) ? $style['header_title'] : array();
		$header_border_top      = isset( $header_container['border_width_top'] ) ? (int) $header_container['border_width_top'] : 0;
		$header_border_right    = isset( $header_container['border_width_right'] ) ? (int) $header_container['border_width_right'] : 0;
		$header_border_bottom   = isset( $header_container['border_width_bottom'] ) ? (int) $header_container['border_width_bottom'] : 0;
		$header_border_left     = isset( $header_container['border_width_left'] ) ? (int) $header_container['border_width_left'] : 0;
		$header_border_radius   = isset( $header_container['border_radius'] ) ? (int) $header_container['border_radius'] : 0;
		$header_border_style    = isset( $header_container['border_style'] ) ? (string) $header_container['border_style'] : 'solid';
		$header_border_color    = isset( $header_container['border_color'] ) ? (string) $header_container['border_color'] : '#cbd5e1';
		$header_bg_color        = isset( $header_container['bg_color'] ) ? (string) $header_container['bg_color'] : 'transparent';
		$header_padding_top     = isset( $header_container['padding_top'] ) ? (int) $header_container['padding_top'] : 0;
		$header_padding_right   = isset( $header_container['padding_right'] ) ? (int) $header_container['padding_right'] : 0;
		$header_padding_bottom  = isset( $header_container['padding_bottom'] ) ? (int) $header_container['padding_bottom'] : 0;
		$header_padding_left    = isset( $header_container['padding_left'] ) ? (int) $header_container['padding_left'] : 0;
		$header_margin_top      = isset( $header_container['margin_top'] ) ? (int) $header_container['margin_top'] : 0;
		$header_margin_right    = isset( $header_container['margin_right'] ) ? (int) $header_container['margin_right'] : 0;
		$header_margin_bottom   = isset( $header_container['margin_bottom'] ) ? (int) $header_container['margin_bottom'] : 12;
		$header_margin_left     = isset( $header_container['margin_left'] ) ? (int) $header_container['margin_left'] : 0;
		$header_title_style     = isset( $header_title['font_style'] ) && is_array( $header_title['font_style'] ) ? $header_title['font_style'] : array();
		$header_title_color     = isset( $header_title['color'] ) ? (string) $header_title['color'] : '#0f172a';
		$header_title_size      = isset( $header_title['font_size'] ) ? (int) $header_title['font_size'] : 18;
		$header_title_weight    = ! empty( $header_title_style['bold'] ) ? '700' : '400';
		$header_title_italic    = ! empty( $header_title_style['italic'] ) ? 'italic' : 'normal';
		$header_title_underline = ! empty( $header_title_style['underline'] ) ? 'underline' : 'none';
		$marker_css             = self::build_marker_css( $item_list_style, $item_text );

		return ".airygen-topic-cluster__header{margin:{$header_margin_top}px {$header_margin_right}px {$header_margin_bottom}px {$header_margin_left}px;padding:{$header_padding_top}px {$header_padding_right}px {$header_padding_bottom}px {$header_padding_left}px;border-style:{$header_border_style};border-color:{$header_border_color};border-width:{$header_border_top}px {$header_border_right}px {$header_border_bottom}px {$header_border_left}px;border-radius:{$header_border_radius}px;background:{$header_bg_color};}" .
		".airygen-topic-cluster__title{margin:0;font-size:{$header_title_size}px;font-weight:{$header_title_weight};font-style:{$header_title_italic};text-decoration:{$header_title_underline};color:{$header_title_color};}" .
		".airygen-topic-cluster{margin:{$margin_top}px {$margin_right}px {$margin_bottom}px {$margin_left}px;border-style:{$border_style};border-color:{$border_color};border-width:{$border_width_top}px {$border_width_right}px {$border_width_bottom}px {$border_width_left}px;border-radius:{$border_radius}px;background:{$bg_color};padding:{$padding_top}px {$padding_right}px {$padding_bottom}px {$padding_left}px;}" .
		'.airygen-topic-cluster__intro{margin:0 0 12px;font-size:.925rem;line-height:1.6;color:#475569;}' .
		".airygen-topic-cluster__links{display:flex;flex-direction:column;gap:{$item_gap}px;}" .
		'.airygen-topic-cluster__item{display:flex;align-items:flex-start;gap:8px;}' .
		'.airygen-topic-cluster__item--parent{flex-direction:column;gap:6px;border:1px solid rgba(148,163,184,.35);border-radius:10px;background:rgba(255,255,255,.7);padding:12px;}' .
		'.airygen-topic-cluster__parent-label{font-size:.75rem;font-weight:600;letter-spacing:.02em;text-transform:uppercase;color:#64748b;}' .
		".airygen-topic-cluster__item-marker{flex:0 0 auto;display:inline-flex;min-width:1rem;justify-content:center;color:{$item_text};font-size:{$item_font_size}px;line-height:1.5;}" .
		".airygen-topic-cluster__link{color:{$item_text};font-size:{$item_font_size}px;font-weight:{$item_weight};font-style:{$item_font_style};text-decoration:{$item_decoration};text-underline-offset:2px;line-height:1.6;}" .
		".airygen-topic-cluster__link[aria-current=\"page\"]{color:{$item_text};font-weight:600;}" .
		$marker_css;
	}

	/**
	 * Render Topic Cluster navigation for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function render( int $post_id ): string {
		$adapter  = new WpDbAdapter();
		$table    = $adapter->table( Constants::TABLE_TOPIC_CLUSTER_RELATIONS );
		$settings = \Airygen\Modules\TopicCluster\Admin\Settings::get();

		if ( ! $adapter->table_exists( $table ) ) {
			return '';
		}

		$current = self::fetch_entry( $adapter, $table, $post_id );
		if ( null === $current ) {
			return '';
		}

		$level = (int) $current['level'];
		if ( $level < 1 || $level > 3 ) {
			return '';
		}

		$view_model = self::build_view_model( $adapter, $table, $current, $post_id );
		if ( null === $view_model ) {
			return '';
		}

		$title_enabled = ! empty( $settings['title_enabled'] );
		$title_level   = isset( $settings['title_level'] ) ? (string) $settings['title_level'] : 'h2';
		$title_level   = in_array( $title_level, array( 'h2', 'h3', 'h4' ), true ) ? $title_level : 'h2';
		$title_text    = isset( $settings['title_text'] ) && is_string( $settings['title_text'] ) && '' !== trim( $settings['title_text'] )
		? $settings['title_text']
		: __( 'Featured topics', 'airygen-seo' );
		$item_style    = isset( $settings['style']['item_list_style'] ) ? (string) $settings['style']['item_list_style'] : 'none';
		$item_style    = in_array( $item_style, array( 'none', 'disc', 'decimal' ), true ) ? $item_style : 'none';

		ob_start();
		?>
		<?php if ( $title_enabled ) : ?>
			<div class="airygen-topic-cluster__header">
				<<?php echo esc_html( $title_level ); ?> class="airygen-topic-cluster__title"><?php echo esc_html( $title_text ); ?></<?php echo esc_html( $title_level ); ?>>
			</div>
		<?php endif; ?>
		<nav class="airygen-topic-cluster" aria-label="<?php echo esc_attr__( 'Topic cluster', 'airygen-seo' ); ?>">
			<p class="airygen-topic-cluster__intro"><?php echo wp_kses_post( (string) $view_model['intro'] ); ?></p>
			<div class="airygen-topic-cluster__links airygen-topic-cluster__links--<?php echo esc_attr( $item_style ); ?>">
		<?php foreach ( $view_model['items'] as $index => $item ) : ?>
			<?php if ( 'parent' === $view_model['variant'] ) : ?>
				<?php self::render_parent_item( $item, $post_id ); ?>
					<?php else : ?>
						<?php self::render_post_item( $item, $post_id, $item_style, $index + 1 ); ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</nav>
		<?php

		return trim( (string) ob_get_clean() );
	}

	/**
	 * Build UI view model for current level.
	 *
	 * @param WpDbAdapter         $adapter Adapter.
	 * @param string              $table Relation table.
	 * @param array<string, int>  $current Current relation entry.
	 * @param int                 $post_id Current post ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function build_view_model( WpDbAdapter $adapter, string $table, array $current, int $post_id ): ?array {
		$level    = (int) $current['level'];
		$l1_id    = self::resolve_l1_id( $current, $level );
		$l2_id    = self::resolve_l2_id( $current, $level );
		$l1_post  = $l1_id > 0 ? get_post( $l1_id ) : null;
		$l2_post  = $l2_id > 0 ? get_post( $l2_id ) : null;
		$settings = \Airygen\Modules\TopicCluster\Admin\Settings::get();

		if ( 1 === $level ) {
			$items = self::fetch_posts_by_parent( $adapter, $table, $post_id, 2 );
			return array(
				'intro'   => self::format_relation_intro(
					(string) ( $settings['relation_text_l1'] ?? 'Explore the main articles in this series.' )
				),
				'items'   => $items,
				'variant' => 'list',
			);
		}

		if ( 2 === $level ) {
			if ( ! $l1_post || ! $l2_post ) {
				return null;
			}

			$l1_link = sprintf(
				'<a class="airygen-topic-cluster__link" href="%1$s">%2$s</a>',
				esc_url( get_permalink( $l1_post ) ),
				esc_html( get_the_title( $l1_post ) )
			);
			$intro   = self::format_relation_intro(
				(string) ( $settings['relation_text_l2'] ?? 'This article is part of the %s series. The links below expand on the topic.' ),
				$l1_link
			);

			return array(
				'intro'   => $intro,
				'items'   => self::fetch_posts_by_parent( $adapter, $table, $post_id, 3 ),
				'variant' => 'list',
			);
		}

		if ( ! $l2_post ) {
			return null;
		}

		$parent_link = sprintf(
			'<a class="airygen-topic-cluster__link" href="%1$s">%2$s</a>',
			esc_url( get_permalink( $l2_post ) ),
			esc_html( get_the_title( $l2_post ) )
		);

		$intro = self::format_relation_intro(
			(string) ( $settings['relation_text_l3'] ?? 'This article expands on %s.' ),
			$parent_link
		);

		return array(
			'intro'   => $intro,
			'items'   => array( $l2_post ),
			'variant' => 'parent',
		);
	}

	/**
	 * Build CSS for list markers.
	 *
	 * @param string $style Marker style.
	 * @param string $text_color Marker color.
	 *
	 * @return string
	 */
	private static function build_marker_css( string $style, string $text_color ): string {
		if ( 'disc' === $style ) {
			return ".airygen-topic-cluster__links--disc .airygen-topic-cluster__item-marker::before{content:'•';}";
		}

		if ( 'decimal' === $style ) {
			return '.airygen-topic-cluster__links--decimal{counter-reset:topic-cluster-item;}' .
			'.airygen-topic-cluster__links--decimal .airygen-topic-cluster__item{counter-increment:topic-cluster-item;}' .
			".airygen-topic-cluster__links--decimal .airygen-topic-cluster__item-marker::before{content:counter(topic-cluster-item) '.';color:{$text_color};}";
		}

		return '.airygen-topic-cluster__item-marker{display:none;}';
	}

	/**
	 * Format relation intro text with an optional link placeholder.
	 *
	 * @param string      $template Text template.
	 * @param string|null $link_html Linked title HTML.
	 *
	 * @return string
	 */
	private static function format_relation_intro( string $template, ?string $link_html = null ): string {
		if ( null !== $link_html && false !== strpos( $template, '%s' ) ) {
			return str_replace( '%s', $link_html, esc_html( $template ) );
		}

		return esc_html( $template );
	}

	/**
	 * Render one post item.
	 *
	 * @param \WP_Post $post Post object.
	 * @param int      $current_post_id Current post ID.
	 * @param string   $item_style Item style.
	 * @param int      $index Display index.
	 *
	 * @return void
	 */
	private static function render_post_item( \WP_Post $post, int $current_post_id, string $item_style, int $index ): void {
		unset( $index );
		$is_current = (int) $post->ID === $current_post_id;
		?>
		<div class="airygen-topic-cluster__item airygen-topic-cluster__item--<?php echo esc_attr( $item_style ); ?>">
			<span class="airygen-topic-cluster__item-marker" aria-hidden="true"></span>
		<?php if ( $is_current ) : ?>
				<span class="airygen-topic-cluster__link" aria-current="page"><?php echo esc_html( get_the_title( $post ) ); ?></span>
			<?php else : ?>
				<a class="airygen-topic-cluster__link" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
				<?php echo esc_html( get_the_title( $post ) ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render L3 parent reference card.
	 *
	 * @param \WP_Post $post Parent post object.
	 * @param int      $current_post_id Current post ID.
	 *
	 * @return void
	 */
	private static function render_parent_item( \WP_Post $post, int $current_post_id ): void {
		$is_current = (int) $post->ID === $current_post_id;
		?>
		<div class="airygen-topic-cluster__item airygen-topic-cluster__item--parent">
			<span class="airygen-topic-cluster__parent-label"><?php echo esc_html__( 'Continue with the main article', 'airygen-seo' ); ?></span>
		<?php if ( $is_current ) : ?>
				<span class="airygen-topic-cluster__link" aria-current="page"><?php echo esc_html( get_the_title( $post ) ); ?></span>
			<?php else : ?>
				<a class="airygen-topic-cluster__link" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
				<?php echo esc_html( get_the_title( $post ) ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Fetch relation entry by post.
	 *
	 * @param WpDbAdapter $adapter Adapter.
	 * @param string      $table Table name.
	 * @param int         $post_id Post ID.
	 *
	 * @return array<string, int>|null
	 */
	private static function fetch_entry( WpDbAdapter $adapter, string $table, int $post_id ): ?array {
		$rows = $adapter->get_results(
			"SELECT post_id, level, parent_post_id, root_id
			 FROM {$table}
			 WHERE post_id = %d
			 LIMIT 1",
			array( $post_id ),
			\ARRAY_A
		);

		if ( empty( $rows ) ) {
			return null;
		}

		return array(
			'post_id'        => isset( $rows[0]['post_id'] ) ? (int) $rows[0]['post_id'] : 0,
			'level'          => isset( $rows[0]['level'] ) ? (int) $rows[0]['level'] : 0,
			'parent_post_id' => isset( $rows[0]['parent_post_id'] ) ? (int) $rows[0]['parent_post_id'] : 0,
			'root_id'        => isset( $rows[0]['root_id'] ) ? (int) $rows[0]['root_id'] : 0,
		);
	}

	/**
	 * Fetch posts by parent and level.
	 *
	 * @param WpDbAdapter $adapter Adapter.
	 * @param string      $table Table name.
	 * @param int         $parent_post_id Parent post ID.
	 * @param int         $level Target level.
	 *
	 * @return array<int, \WP_Post>
	 */
	private static function fetch_posts_by_parent( WpDbAdapter $adapter, string $table, int $parent_post_id, int $level ): array {
		$rows = $adapter->get_results(
			"SELECT post_id
			 FROM {$table}
			 WHERE parent_post_id = %d AND level = %d",
			array( $parent_post_id, $level ),
			\ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$post_ids = array();
		foreach ( $rows as $row ) {
			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $post_id > 0 ) {
				$post_ids[] = $post_id;
			}
		}

		if ( empty( $post_ids ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'           => 'any',
				'post_status'         => 'publish',
				'post__in'            => $post_ids,
				'orderby'             => 'title',
				'order'               => 'ASC',
				'posts_per_page'      => count( $post_ids ),
				'ignore_sticky_posts' => true,
			)
		);

		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * Resolve L1 post ID.
	 *
	 * @param array<string, int> $current Current relation entry.
	 * @param int                $level Current level.
	 *
	 * @return int
	 */
	private static function resolve_l1_id( array $current, int $level ): int {
		if ( 1 === $level ) {
			return $current['post_id'];
		}

		if ( 2 === $level ) {
			return $current['parent_post_id'];
		}

		return $current['root_id'];
	}

	/**
	 * Resolve L2 post ID.
	 *
	 * @param array<string, int> $current Current relation entry.
	 * @param int                $level Current level.
	 *
	 * @return int
	 */
	private static function resolve_l2_id( array $current, int $level ): int {
		if ( 2 === $level ) {
			return $current['post_id'];
		}

		if ( 3 === $level ) {
			return $current['parent_post_id'];
		}

		return 0;
	}
}
