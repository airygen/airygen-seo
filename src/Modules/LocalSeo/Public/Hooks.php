<?php
/**
 * Public hooks for Local SEO output.
 *
 * @package Airygen\Modules\LocalSeo\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\LocalSeo\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\LocalSeo\Admin\Settings;
use Airygen\Support\Meta\PostData;

/**
 * Handles Local SEO schema/meta/shortcodes.
 */
final class Hooks {
	private const FILTER_SCHEMA_JSONLD_PAYLOAD = 'airygen_schema_jsonld_payload';
	private const SHORTCODE_LOCAL_SEO          = 'airygen_localseo';


	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'emit_head' ), 14 );
		add_action( 'wp_footer', array( __CLASS__, 'emit_footer_nap' ), 21 );
		add_filter( self::FILTER_SCHEMA_JSONLD_PAYLOAD, array( __CLASS__, 'merge_local_business_into_schema_payload' ), 20, 2 );
		add_shortcode( self::SHORTCODE_LOCAL_SEO, array( __CLASS__, 'render_localseo_shortcode' ) );
	}

	/**
	 * Enqueue frontend styles before WordPress prints the head.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() || is_feed() ) {
			return;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( ! empty( $settings['footer_nap_enabled'] ) ) {
			self::emit_footer_nap_css( $settings );
		}

		self::enqueue_local_business_css( $settings );
	}

	/**
	 * Emit Local SEO tags.
	 *
	 * @return void
	 */
	public static function emit_head(): void {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( self::should_emit_local_business_schema() && self::should_emit_inline_local_business_schema() ) {
			$schema = self::build_schema( $settings, self::should_emit_full_local_business_schema() );
			if ( ! empty( $schema ) ) {
				$json = wp_json_encode(
					$schema,
					JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
				);
				if ( false !== $json ) {
					wp_print_inline_script_tag( $json, array( 'type' => 'application/ld+json' ) );
				}
			}
		}

		$service_schema = self::build_service_schema( $settings );
		if ( ! empty( $service_schema ) ) {
			$json = wp_json_encode(
				$service_schema,
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			);
			if ( false !== $json ) {
				wp_print_inline_script_tag( $json, array( 'type' => 'application/ld+json' ) );
			}
		}

		if ( ! empty( $settings['enable_geo_tags'] ) ) {
			self::emit_geo_meta( $settings );
		}
	}

	/**
	 * Merge LocalBusiness payload into Schema Markup graph payload.
	 *
	 * @param array<string, mixed> $payload JSON-LD payload from Schema Markup module.
	 * @param array<string, mixed> $context Schema Markup runtime context.
	 * @return array<string, mixed>
	 */
	public static function merge_local_business_into_schema_payload( array $payload, array $context ): array {
		unset( $context );

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return $payload;
		}

		$local_business = self::build_schema( $settings, self::should_emit_full_local_business_schema() );
		if ( empty( $local_business ) ) {
			return $payload;
		}

		unset( $local_business['@context'] );
		$identity_id           = self::local_business_id();
		$local_business['@id'] = $identity_id;

		$context_url    = self::string_or_null( $payload['@context'] ?? null );
		$jsonld_context = null === $context_url ? 'https://schema.org' : $context_url;

		$graph = array();
		if ( isset( $payload['@graph'] ) && is_array( $payload['@graph'] ) ) {
			foreach ( $payload['@graph'] as $node ) {
				if ( is_array( $node ) ) {
					$graph[] = $node;
				}
			}
		}

		$organization_node = null;
		$filtered_graph    = array();
		foreach ( $graph as $node ) {
			$node_id = self::string_or_null( $node['@id'] ?? null );
			if ( null !== $node_id && $identity_id === $node_id ) {
				$organization_node = $node;
				continue;
			}

			if ( self::node_is_organization( $node ) ) {
				$organization_node = $node;
				continue;
			}

			if ( self::node_is_local_business( $node ) ) {
				continue;
			}

			if ( null !== $node_id && $identity_id === $node_id ) {
				continue;
			}

			$filtered_graph[] = $node;
		}

		$identity_node    = self::merge_identity_nodes( $organization_node, $local_business );
		$filtered_graph[] = $identity_node;
		$filtered_graph   = self::apply_identity_publisher_reference( $filtered_graph, $identity_id );

		return array(
			'@context' => $jsonld_context,
			'@graph'   => $filtered_graph,
		);
	}

	/**
	 * Decide whether LocalBusiness schema should be emitted for current request.
	 *
	 * @return bool
	 */
	private static function should_emit_local_business_schema(): bool {
		if ( is_feed() ) {
			return false;
		}

		return true;
	}

	/**
	 * Decide whether LocalBusiness should be emitted as standalone inline JSON-LD.
	 *
	 * When Schema Markup emitter is active during normal wp_head execution, LocalBusiness
	 * should be injected via graph merge filter to avoid duplicate scripts.
	 *
	 * @return bool
	 */
	private static function should_emit_inline_local_business_schema(): bool {
		if ( ! self::has_schema_markup_emitter() ) {
			return true;
		}

		return ! doing_action( 'wp_head' );
	}

	/**
	 * Decide whether LocalBusiness should emit full details for current request.
	 *
	 * Full payload appears on homepage and pages containing [airygen_localseo].
	 * Other pages only keep LocalBusiness identity/type.
	 *
	 * @return bool
	 */
	private static function should_emit_full_local_business_schema(): bool {
		if ( ! did_action( 'wp' ) ) {
			return true;
		}

		if ( is_front_page() || is_home() ) {
			return true;
		}

		if ( ! is_singular() ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof \WP_Post ) ) {
			return false;
		}

		$content = (string) $post->post_content;
		return has_shortcode( $content, self::SHORTCODE_LOCAL_SEO );
	}

	/**
	 * Detect whether Schema Markup module emitter is registered.
	 *
	 * @return bool
	 */
	private static function has_schema_markup_emitter(): bool {
		$callback = array( 'Airygen\\Modules\\SchemaMarkup\\Public\\EmitJsonLd', 'emit' );
		return false !== has_action( 'wp_head', $callback );
	}

	/**
	 * Emit NAP block in footer.
	 *
	 * @return void
	 */
	public static function emit_footer_nap(): void {
		if ( is_admin() || is_feed() ) {
			return;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) || empty( $settings['footer_nap_enabled'] ) ) {
			return;
		}

		$name        = trim( (string) ( $settings['business_name'] ?? '' ) );
		$legal_name  = trim( (string) ( $settings['legal_name'] ?? '' ) );
		$phone       = trim( (string) ( $settings['phone'] ?? '' ) );
		$street      = trim( (string) ( $settings['street_address'] ?? '' ) );
		$city        = trim( (string) ( $settings['city'] ?? '' ) );
		$region      = trim( (string) ( $settings['region'] ?? '' ) );
		$postal_code = trim( (string) ( $settings['postal_code'] ?? '' ) );
		$country     = trim( (string) ( $settings['country'] ?? '' ) );
		$vat_id      = trim( (string) ( $settings['vat_id'] ?? '' ) );
		$line_order  = self::resolve_footer_nap_layout_order( $settings );
		if ( '' !== $vat_id && ! empty( $settings['show_vat_in_footer'] ) && ! in_array( 'tax_id', $line_order, true ) ) {
			$line_order[] = 'tax_id';
		}
		$style            = self::resolve_footer_nap_style( $settings );
		$has_address      = '' !== $street || '' !== $city || '' !== $region || '' !== $postal_code || '' !== $country;
		$has_visible_line = false;
		foreach ( $line_order as $block_id ) {
			if ( 'business_name' === $block_id && '' !== $name ) {
				$has_visible_line = true;
				break;
			}
			if ( 'legal_name' === $block_id && '' !== $legal_name ) {
				$has_visible_line = true;
				break;
			}
			if ( 'phone' === $block_id && '' !== $phone ) {
				$has_visible_line = true;
				break;
			}
			if ( 'address' === $block_id && $has_address ) {
				$has_visible_line = true;
				break;
			}
			if ( 'tax_id' === $block_id && '' !== $vat_id && ! empty( $settings['show_vat_in_footer'] ) ) {
				$has_visible_line = true;
				break;
			}
		}
		if ( ! $has_visible_line ) {
			return;
		}

		echo '<div class="airygen-local-nap-wrap">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="airygen-local-nap" itemscope itemtype="https://schema.org/LocalBusiness">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<address class="airygen-local-nap__address">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$rendered_lines = 0;
		foreach ( $line_order as $block_id ) {
			$line_class = 'airygen-local-nap__line';
			if ( ! empty( $style['first_item_bold'] ) && 0 === $rendered_lines ) {
				$line_class .= ' airygen-local-nap__line--first';
			}
			if ( 'business_name' === $block_id ) {
				if ( '' === $name ) {
					continue;
				}
				echo '<div class="' . esc_attr( $line_class ) . '" itemprop="name">' . esc_html( $name ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				++$rendered_lines;
				continue;
			}

			if ( 'legal_name' === $block_id ) {
				if ( '' === $legal_name ) {
					continue;
				}
				echo '<div class="' . esc_attr( $line_class ) . '" itemprop="legalName">' . esc_html( $legal_name ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				++$rendered_lines;
				continue;
			}

			if ( 'phone' === $block_id ) {
				if ( '' === $phone ) {
					continue;
				}
				echo '<div class="' . esc_attr( $line_class ) . '"><span>' . esc_html__( 'TEL:', 'airygen-seo' ) . '</span><span itemprop="telephone">' . esc_html( $phone ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				++$rendered_lines;
				continue;
			}

			if ( 'address' === $block_id ) {
				if ( ! $has_address ) {
					continue;
				}
				$street_line = trim( $street . ' ' . $city );
				$locality    = '' !== $region ? $region : $city;
				echo '<div class="' . esc_attr( $line_class ) . '" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( '' !== $street_line ) {
					echo '<span itemprop="streetAddress">' . esc_html( $street_line ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				if ( '' !== $locality ) {
					echo '<span itemprop="addressLocality">' . esc_html( $locality ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				if ( '' !== $postal_code ) {
					echo '<span itemprop="postalCode">' . esc_html( $postal_code ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				if ( '' !== $country ) {
					echo '<meta itemprop="addressCountry" content="' . esc_attr( strtoupper( $country ) ) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				++$rendered_lines;
				continue;
			}

			if ( 'tax_id' === $block_id ) {
				if ( '' === $vat_id || empty( $settings['show_vat_in_footer'] ) ) {
					continue;
				}
				echo '<div class="' . esc_attr( $line_class ) . '"><span>' . esc_html__( 'Tax ID:', 'airygen-seo' ) . ' ' . esc_html( $vat_id ) . '</span><meta itemprop="taxID" content="' . esc_attr( $vat_id ) . '" /></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				++$rendered_lines;
				continue;
			}
		}
		echo '</address>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Resolve Footer NAP layout order.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<int, string>
	 */
	private static function resolve_footer_nap_layout_order( array $settings ): array {
		$allowed = array(
			'business_name',
			'legal_name',
			'phone',
			'address',
			'tax_id',
		);
		$order   = array();
		if ( isset( $settings['footer_nap_layout_order'] ) && is_array( $settings['footer_nap_layout_order'] ) ) {
			foreach ( $settings['footer_nap_layout_order'] as $item ) {
				if ( ! is_string( $item ) ) {
					continue;
				}
				$key = sanitize_key( $item );
				if ( ! in_array( $key, $allowed, true ) || in_array( $key, $order, true ) ) {
					continue;
				}
				$order[] = $key;
			}
		}

		if ( empty( $order ) ) {
			return array(
				'business_name',
				'phone',
				'address',
			);
		}

		return $order;
	}

	/**
	 * Emit Footer NAP styles for frontend output.
	 *
	 * @return void
	 */
	private static function emit_footer_nap_css( array $settings ): void {
		$style = self::resolve_footer_nap_style( $settings );
		$css   = ".airygen-local-nap-wrap{width:100%;}\n";
		$css  .= '.airygen-local-nap{margin:' . (string) $style['margin_y'] . 'px auto;width:100%;max-width:' . (string) $style['container_width'] . 'px;font-size:' . (string) $style['font_size'] . 'px;line-height:1.6;text-align:' . $style['text_align'] . ';color:' . $style['text_color'] . ";}\n";
		$css  .= '.airygen-local-nap__address{margin:0;font-style:normal;display:flex;flex-direction:row;gap:' . (string) $style['gap'] . 'px;flex-wrap:wrap;justify-content:' . $style['justify_content'] . ";}\n";
		$css  .= ".airygen-local-nap__line{margin:0;display:flex;gap:4px;}\n";
		$css  .= ".airygen-local-nap__line--first{font-weight:700;}\n";

		wp_register_style( 'airygen-local-nap-css', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- No external file.
		wp_enqueue_style( 'airygen-local-nap-css' );
		wp_add_inline_style( 'airygen-local-nap-css', $css );
	}

	/**
	 * Enqueue Local Business card styles before shortcodes are rendered.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 *
	 * @return void
	 */
	private static function enqueue_local_business_css( array $settings ): void {
		$layout_item_gap     = 12;
		$layout_card_padding = isset( $settings['layout_card_padding'] ) ? (int) $settings['layout_card_padding'] : 16;
		if ( $layout_card_padding < 0 ) {
			$layout_card_padding = 0;
		}
		if ( $layout_card_padding > 64 ) {
			$layout_card_padding = 64;
		}

		$layout_label_font_size = isset( $settings['layout_label_font_size'] ) ? (int) $settings['layout_label_font_size'] : 12;
		if ( $layout_label_font_size < 10 ) {
			$layout_label_font_size = 10;
		}
		if ( $layout_label_font_size > 32 ) {
			$layout_label_font_size = 32;
		}

		$layout_value_font_size = isset( $settings['layout_value_font_size'] ) ? (int) $settings['layout_value_font_size'] : 14;
		if ( $layout_value_font_size < 10 ) {
			$layout_value_font_size = 10;
		}
		if ( $layout_value_font_size > 40 ) {
			$layout_value_font_size = 40;
		}

		$layout_title_font_size = isset( $settings['layout_title_font_size'] ) ? (int) $settings['layout_title_font_size'] : 26;
		if ( $layout_title_font_size < 16 ) {
			$layout_title_font_size = 16;
		}
		if ( $layout_title_font_size > 80 ) {
			$layout_title_font_size = 80;
		}

		$layout_show_card_border = ! isset( $settings['layout_show_card_border'] ) || ! empty( $settings['layout_show_card_border'] );
		$layout_label_uppercase  = ! isset( $settings['layout_label_uppercase'] ) || ! empty( $settings['layout_label_uppercase'] );
		$layout_label_bold       = ! isset( $settings['layout_label_bold'] ) || ! empty( $settings['layout_label_bold'] );
		$layout_label_italic     = isset( $settings['layout_label_italic'] ) && ! empty( $settings['layout_label_italic'] );
		$layout_label_color      = isset( $settings['layout_label_color'] ) ? sanitize_hex_color( (string) $settings['layout_label_color'] ) : '';
		if ( ! is_string( $layout_label_color ) || '' === $layout_label_color ) {
			$layout_label_color = '#64748b';
		}

		$layout_value_color = isset( $settings['layout_value_color'] ) ? sanitize_hex_color( (string) $settings['layout_value_color'] ) : '';
		if ( ! is_string( $layout_value_color ) || '' === $layout_value_color ) {
			$layout_value_color = '#334155';
		}

		$layout_card_background_color = isset( $settings['layout_card_background_color'] ) ? sanitize_hex_color( (string) $settings['layout_card_background_color'] ) : '';
		if ( ! is_string( $layout_card_background_color ) || '' === $layout_card_background_color ) {
			$layout_card_background_color = '#ffffff';
		}

		$layout_label_transform_css = $layout_label_uppercase ? 'uppercase' : 'none';
		$layout_label_weight_css    = $layout_label_bold ? '700' : '500';
		$layout_label_style_css     = $layout_label_italic ? 'italic' : 'normal';

		$css  = '.airygen-local-business{background:#ffffff;color:' . $layout_value_color . ';font-size:' . $layout_value_font_size . 'px;line-height:1.55;border-radius:8px;' . ( $layout_show_card_border ? 'border:1px solid #e2e8f0;' : 'border:0;' ) . 'padding:16px;box-sizing:border-box;}';
		$css .= '.airygen-local-business__layout{display:grid;gap:' . $layout_item_gap . 'px;align-items:start;}';
		$css .= '.airygen-local-business__column{min-width:0;}';
		$css .= '.airygen-local-business__lane{display:grid;gap:' . $layout_item_gap . 'px;align-items:start;}';
		$css .= '.airygen-local-business__lane--header{margin-bottom:' . $layout_item_gap . 'px;}';
		$css .= '.airygen-local-business__item{border-radius:8px;background:' . $layout_card_background_color . ';border:0;padding:' . $layout_card_padding . 'px;box-sizing:border-box;}';
		$css .= '.airygen-local-business__label{margin:0 0 8px;font-size:' . $layout_label_font_size . 'px;font-weight:' . $layout_label_weight_css . ';font-style:' . $layout_label_style_css . ';letter-spacing:0.04em;text-transform:' . $layout_label_transform_css . ';color:' . $layout_label_color . ';}';
		$css .= '.airygen-local-business__label--sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}';
		$css .= '.airygen-local-business__business-name{margin:0;font-size:' . $layout_title_font_size . 'px;font-weight:700;line-height:1.1;}';
		$css .= '.airygen-local-business__item p:not(.airygen-local-business__business-name),.airygen-local-business__item li{color:' . $layout_value_color . ';font-size:' . $layout_value_font_size . 'px;}';
		$css .= '.airygen-local-business__item p{margin:0;}';
		$css .= '.airygen-local-business__item ul{margin:8px 0 0;padding-left:18px;}';
		$css .= '.airygen-local-business__item img,.airygen-local-business__item iframe{width:100%;max-width:100%;border:1px solid #e2e8f0;border-radius:8px;}';
		$css .= '.airygen-local-business__item iframe{min-height:220px;border:0;}';
		$css .= '.airygen-local-business__item--logo_url img{max-height:72px;width:auto;}';
		$css .= '@media (max-width:960px){.airygen-local-business__layout{grid-template-columns:1fr !important;}.airygen-local-business__lane{grid-template-columns:1fr !important;grid-template-rows:auto !important;}.airygen-local-business__item{grid-column:auto !important;grid-row:auto !important;}}';

		wp_register_style( 'airygen-local-business-css', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- No external file.
		wp_enqueue_style( 'airygen-local-business-css' );
		wp_add_inline_style( 'airygen-local-business-css', $css );
	}

	/**
	 * Resolve footer NAP style settings.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<string, int|string|bool>
	 */
	private static function resolve_footer_nap_style( array $settings ): array {
		$font_size = isset( $settings['footer_nap_font_size'] ) && is_numeric( $settings['footer_nap_font_size'] )
		? (int) $settings['footer_nap_font_size']
		: 12;
		if ( $font_size < 10 ) {
			$font_size = 10;
		}
		if ( $font_size > 48 ) {
			$font_size = 48;
		}

		$container_width = isset( $settings['footer_nap_container_width'] ) && is_numeric( $settings['footer_nap_container_width'] )
		? (int) $settings['footer_nap_container_width']
		: 960;
		if ( $container_width < 280 ) {
			$container_width = 280;
		}
		if ( $container_width > 1920 ) {
			$container_width = 1920;
		}
		$margin_y = isset( $settings['footer_nap_margin_y'] ) && is_numeric( $settings['footer_nap_margin_y'] )
		? (int) $settings['footer_nap_margin_y']
		: 10;
		if ( $margin_y < 0 ) {
			$margin_y = 0;
		}
		if ( $margin_y > 200 ) {
			$margin_y = 200;
		}
		$gap = isset( $settings['footer_nap_gap'] ) && is_numeric( $settings['footer_nap_gap'] )
		? (int) $settings['footer_nap_gap']
		: 4;
		if ( $gap < 0 ) {
			$gap = 0;
		}
		if ( $gap > 48 ) {
			$gap = 48;
		}
		$text_align = isset( $settings['footer_nap_text_align'] ) && is_string( $settings['footer_nap_text_align'] )
		? sanitize_key( $settings['footer_nap_text_align'] )
		: 'center';
		if ( ! in_array( $text_align, array( 'left', 'center', 'right' ), true ) ) {
			$text_align = 'center';
		}
		$justify_content = 'center';
		if ( 'left' === $text_align ) {
			$justify_content = 'flex-start';
		} elseif ( 'right' === $text_align ) {
			$justify_content = 'flex-end';
		}

		$text_color = isset( $settings['footer_nap_text_color'] ) && is_string( $settings['footer_nap_text_color'] )
		? strtolower( trim( $settings['footer_nap_text_color'] ) )
		: '#334155';
		if ( ! preg_match( '/^#[0-9a-f]{6}$/', $text_color ) ) {
			$text_color = '#334155';
		}

		return array(
			'font_size'       => $font_size,
			'container_width' => $container_width,
			'margin_y'        => $margin_y,
			'text_align'      => $text_align,
			'justify_content' => $justify_content,
			'text_color'      => $text_color,
			'gap'             => $gap,
			'first_item_bold' => ! empty( $settings['footer_nap_first_item_bold'] ),
		);
	}

	/**
	 * Inject tel: links into plain-text phone numbers in content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function inject_click_to_call_links( string $content ): string {
		if ( '' === $content || is_admin() || is_feed() ) {
			return $content;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) || empty( $settings['click_to_call_enabled'] ) ) {
			return $content;
		}

		return self::wrap_phone_numbers_in_html( $content );
	}

	/**
	 * Render unified Local SEO shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attrs.
	 * @return string
	 */
	public static function render_localseo_shortcode( array $atts ): string {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return '';
		}

		$atts     = shortcode_atts(
			array(
				'width'    => '100%',
				'height'   => '360',
				'zoom'     => '',
				'show_map' => '1',
				'branch'   => '',
			),
			$atts,
			self::SHORTCODE_LOCAL_SEO
		);
		$settings = self::resolve_shortcode_settings( $settings, $atts );

		$values               = self::build_local_info_values( $settings );
		$name                 = (string) $values['name'];
		$legal_name           = trim( (string) ( $settings['legal_name'] ?? '' ) );
		$phone                = (string) $values['phone'];
		$address              = (string) $values['address'];
		$show_map             = ! in_array( strtolower( trim( (string) $atts['show_map'] ) ), array( '0', 'false', 'no' ), true );
		$image_url            = trim( (string) ( $settings['image_url'] ?? '' ) );
		$logo_url             = trim( (string) ( $settings['logo_url'] ?? '' ) );
		$vat_id               = trim( (string) ( $settings['vat_id'] ?? '' ) );
		$resolved_price_range = self::resolve_price_range( $settings );
		$pricing              = self::to_readable_price_level( $resolved_price_range );
		if (
			'$$' === $resolved_price_range &&
			'' === trim( (string) ( $settings['price_range_custom'] ?? '' ) ) &&
			in_array( trim( (string) ( $settings['price_range'] ?? '' ) ), array( '', '$$' ), true )
		) {
			$pricing = '';
		}
		$service_areas_lines   = self::build_service_areas_lines( $settings );
		$service_catalog_items = self::build_service_catalog_display_items( $settings );
		$opening_hours_lines   = self::split_multiline_text( (string) ( $settings['opening_hours'] ?? '' ) );
		$special_hours_lines   = self::split_multiline_text( (string) ( $settings['special_hours'] ?? '' ) );
		$lat                   = isset( $settings['latitude'] ) ? (float) $settings['latitude'] : 0.0;
		$lng                   = isset( $settings['longitude'] ) ? (float) $settings['longitude'] : 0.0;
		$has_map               = $show_map && ( 0.0 !== $lat || 0.0 !== $lng );
		$map_embed_url         = '';
		if ( $has_map ) {
			$map_embed_url = sprintf( 'https://www.google.com/maps?q=%s&z=14&output=embed', rawurlencode( $lat . ',' . $lng ) );
		}

		if (
			'' === $name &&
			'' === $legal_name &&
			'' === $phone &&
			'' === $address &&
			'' === $map_embed_url &&
			'' === $image_url &&
			'' === $logo_url &&
			'' === $vat_id &&
			'' === $pricing &&
			empty( $service_areas_lines ) &&
			empty( $service_catalog_items ) &&
			empty( $opening_hours_lines ) &&
			empty( $special_hours_lines )
		) {
			return '';
		}

		$block_markup = array();
		if ( '' !== $name ) {
			$block_markup['business_name'] = '<p class="airygen-local-business__business-name">' . esc_html( $name ) . '</p>';
		}
		if ( '' !== $legal_name ) {
			$block_markup['legal_name'] = '<p>' . esc_html( $legal_name ) . '</p>';
		}
		if ( '' !== $address ) {
			$block_markup['address'] = '<p>' . esc_html( $address ) . '</p>';
		}
		if ( '' !== $phone ) {
			$block_markup['phone'] = '<p>' . esc_html( $phone ) . '</p>';
		}
		if ( '' !== $map_embed_url ) {
			$block_markup['map'] = '<iframe src="' . esc_url( $map_embed_url ) . '" title="' . esc_attr__( 'Map preview', 'airygen-seo' ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
		}
		if ( '' !== $image_url ) {
			$block_markup['image_url'] = '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr__( 'Business image', 'airygen-seo' ) . '" loading="lazy" />';
		}
		if ( '' !== $logo_url ) {
			$block_markup['logo_url'] = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr__( 'Business logo', 'airygen-seo' ) . '" loading="lazy" />';
		}
		if ( '' !== $vat_id ) {
			$block_markup['vat_id'] = '<p>' . esc_html( $vat_id ) . '</p>';
		}
		if ( '' !== $pricing ) {
			$block_markup['pricing'] = '<p>' . esc_html( $pricing ) . '</p>';
		}
		if ( ! empty( $service_areas_lines ) ) {
			$list = '';
			foreach ( $service_areas_lines as $line ) {
				$list .= '<li>' . esc_html( $line ) . '</li>';
			}
			$block_markup['service_areas'] = '<ul>' . $list . '</ul>';
		}
		if ( ! empty( $service_catalog_items ) ) {
			$list = '';
			foreach ( $service_catalog_items as $item ) {
				$list .= '<li>' . esc_html( $item ) . '</li>';
			}
			$block_markup['service_catalog'] = '<ul>' . $list . '</ul>';
		}
		if ( ! empty( $opening_hours_lines ) ) {
			$list = '';
			foreach ( $opening_hours_lines as $line ) {
				$list .= '<li>' . esc_html( self::format_opening_hours_line_for_display( $line ) ) . '</li>';
			}
			$block_markup['opening_hours'] = '<ul>' . $list . '</ul>';
		}
		if ( ! empty( $special_hours_lines ) ) {
			$list = '';
			foreach ( $special_hours_lines as $line ) {
				$list .= '<li>' . esc_html( self::format_special_hours_line_for_display( $line ) ) . '</li>';
			}
			$block_markup['special_hours'] = '<ul>' . $list . '</ul>';
		}

		$block_labels   = array(
			'business_name'   => esc_html__( 'Business name', 'airygen-seo' ),
			'legal_name'      => esc_html__( 'Legal name', 'airygen-seo' ),
			'address'         => esc_html__( 'Address', 'airygen-seo' ),
			'phone'           => esc_html__( 'Phone', 'airygen-seo' ),
			'map'             => esc_html__( 'Map', 'airygen-seo' ),
			'image_url'       => esc_html__( 'Image', 'airygen-seo' ),
			'logo_url'        => esc_html__( 'Logo', 'airygen-seo' ),
			'vat_id'          => esc_html__( 'Tax ID', 'airygen-seo' ),
			'pricing'         => esc_html__( 'Pricing', 'airygen-seo' ),
			'service_areas'   => esc_html__( 'Service Areas', 'airygen-seo' ),
			'service_catalog' => esc_html__( 'Service Catalog', 'airygen-seo' ),
			'opening_hours'   => esc_html__( 'Opening Hours', 'airygen-seo' ),
			'special_hours'   => esc_html__( 'Special Hours', 'airygen-seo' ),
		);
		$layout_grid    = self::resolve_layout_grid( $settings );
		$visible_blocks = array();
		foreach ( $layout_grid as $item ) {
			$block_key = (string) ( $item['block_id'] ?? '' );
			if ( '' === $block_key || ! isset( $block_markup[ $block_key ] ) ) {
				continue;
			}
			$visible_blocks[] = array(
				'block_id' => $block_key,
				'row'      => (int) ( $item['row'] ?? 1 ),
				'col'      => (int) ( $item['col'] ?? 1 ),
				'span'     => (int) ( $item['span'] ?? 1 ),
				'row_span' => (int) ( $item['row_span'] ?? 1 ),
				'label'    => (string) ( $block_labels[ $block_key ] ?? '' ),
				'html'     => (string) $block_markup[ $block_key ],
			);
		}
		if ( empty( $visible_blocks ) ) {
			return '';
		}

		$layout_template          = self::resolve_layout_template( $settings );
		$layout_item_gap          = 12;
		$template_columns         = self::resolve_layout_columns( $layout_template );
		$header_lane              = self::build_lane_layout_blocks( $visible_blocks, 'header', $layout_template );
		$sidebar_lane             = self::build_lane_layout_blocks( $visible_blocks, 'sidebar', $layout_template );
		$main_lane                = self::build_lane_layout_blocks( $visible_blocks, 'main', $layout_template );
		$is_sidebar_left_template = self::is_sidebar_left_template( $layout_template );
		$left_lane                = $is_sidebar_left_template ? $sidebar_lane : $main_lane;
		$right_lane               = $is_sidebar_left_template ? $main_lane : $sidebar_lane;
		$left_lane_class          = $is_sidebar_left_template ? 'sidebar' : 'main';
		$right_lane_class         = $is_sidebar_left_template ? 'main' : 'sidebar';
		$columns_style            = 'grid-template-columns:' .
		( $is_sidebar_left_template
		? $template_columns['sidebar_span'] . 'fr ' . $template_columns['main_span'] . 'fr'
		: $template_columns['main_span'] . 'fr ' . $template_columns['sidebar_span'] . 'fr' ) .
		';gap:' . $layout_item_gap . 'px;';

		$render_block = static function ( array $block ): string {
			$block_id   = (string) ( $block['block_id'] ?? '' );
			$label      = (string) ( $block['label'] ?? '' );
			$label_html = '';
			if ( in_array( $block_id, array( 'image_url', 'logo_url' ), true ) ) {
				$label_html = '<p class="airygen-local-business__label airygen-local-business__label--sr-only">' . esc_html( $label ) . '</p>';
			} elseif ( ! in_array( $block_id, array( 'map', 'business_name' ), true ) ) {
				$label_html = '<p class="airygen-local-business__label">' . esc_html( $label ) . '</p>';
			}
			$item_style = 'grid-column:' . (int) ( $block['display_col'] ?? 1 ) . ' / span ' . (int) ( $block['display_col_span'] ?? 1 ) . ';grid-row:' . (int) ( $block['display_row'] ?? 1 ) . ' / span ' . (int) ( $block['display_row_span'] ?? 1 ) . ';';

			return '<div class="airygen-local-business__item airygen-local-business__item--' . esc_attr( $block_id ) . '" style="' . esc_attr( $item_style ) . '">' . $label_html . (string) ( $block['html'] ?? '' ) . '</div>';
		};

		$html  = '';
		$html .= '<section class="airygen-local-business">';
		if ( self::template_has_header( $layout_template ) && ! empty( $header_lane['blocks'] ) ) {
			$html .= '<div class="airygen-local-business__lane airygen-local-business__lane--header" style="grid-template-columns:repeat(' . (int) ( $header_lane['columns'] ?? 5 ) . ', minmax(0, 1fr));grid-template-rows:repeat(' . (int) max( 1, (int) ( $header_lane['rows'] ?? 1 ) ) . ', minmax(0, auto));gap:' . $layout_item_gap . 'px;">';
			foreach ( (array) ( $header_lane['blocks'] ?? array() ) as $block ) {
				$html .= $render_block( $block );
			}
			$html .= '</div>';
		}
		$html .= '<div class="airygen-local-business__layout airygen-local-business__layout--' . esc_attr( $layout_template ) . '" style="' . esc_attr( $columns_style ) . '">';
		$html .= '<div class="airygen-local-business__column airygen-local-business__column--' . esc_attr( $left_lane_class ) . '">';
		$html .= '<div class="airygen-local-business__lane" style="grid-template-columns:repeat(' . (int) ( $left_lane['columns'] ?? 1 ) . ', minmax(0, 1fr));grid-template-rows:repeat(' . (int) max( 1, (int) ( $left_lane['rows'] ?? 1 ) ) . ', minmax(0, auto));gap:' . $layout_item_gap . 'px;">';
		foreach ( (array) ( $left_lane['blocks'] ?? array() ) as $block ) {
			$html .= $render_block( $block );
		}
		$html .= '</div></div>';
		$html .= '<div class="airygen-local-business__column airygen-local-business__column--' . esc_attr( $right_lane_class ) . '">';
		$html .= '<div class="airygen-local-business__lane" style="grid-template-columns:repeat(' . (int) ( $right_lane['columns'] ?? 1 ) . ', minmax(0, 1fr));grid-template-rows:repeat(' . (int) max( 1, (int) ( $right_lane['rows'] ?? 1 ) ) . ', minmax(0, auto));gap:' . $layout_item_gap . 'px;">';
		foreach ( (array) ( $right_lane['blocks'] ?? array() ) as $block ) {
			$html .= $render_block( $block );
		}
		$html .= '</div></div>';
		$html .= '</div>';
		$html .= '</section>';

		return $html;
	}

	/**
	 * Resolve Local SEO shortcode layout grid with fallback defaults.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<int, array<string, int|string>>
	 */
	private static function resolve_layout_grid( array $settings ): array {
		$allowed      = self::layout_allowed_blocks();
		$layout_order = array();

		if ( isset( $settings['layout_order'] ) && is_array( $settings['layout_order'] ) ) {
			foreach ( $settings['layout_order'] as $item ) {
				if ( ! is_string( $item ) ) {
					continue;
				}
				$key = sanitize_key( $item );
				if ( ! in_array( $key, $allowed, true ) || in_array( $key, $layout_order, true ) ) {
					continue;
				}
				$layout_order[] = $key;
			}
		}
		$grid     = array();
		$used     = array();
		$occupied = array();
		if ( isset( $settings['layout_grid'] ) && is_array( $settings['layout_grid'] ) ) {
			foreach ( $settings['layout_grid'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$raw_block_id = '';
				if ( isset( $item['block_id'] ) && is_string( $item['block_id'] ) ) {
					$raw_block_id = $item['block_id'];
				}
				$block_id = sanitize_key( $raw_block_id );
				if ( '' === $block_id || ! in_array( $block_id, $allowed, true ) || in_array( $block_id, $used, true ) ) {
					continue;
				}
				$row      = isset( $item['row'] ) ? (int) $item['row'] : 0;
				$col      = isset( $item['col'] ) ? (int) $item['col'] : 0;
				$span     = isset( $item['span'] ) ? (int) $item['span'] : 0;
				$row_span = isset( $item['row_span'] ) ? (int) $item['row_span'] : 1;
				if ( $row < 1 || $row > 15 || $col < 1 || $col > 5 ) {
					continue;
				}
				$max_span     = 6 - $col;
				$max_row_span = min( 5, 16 - $row );
				if ( $span < 1 || $span > $max_span || $row_span < 1 || $row_span > $max_row_span ) {
					continue;
				}

				for ( $row_offset = 0; $row_offset < $row_span; $row_offset++ ) {
					for ( $col_offset = 0; $col_offset < $span; $col_offset++ ) {
						$cell_key              = (string) ( $row + $row_offset ) . '-' . (string) ( $col + $col_offset );
						$occupied[ $cell_key ] = true;
					}
				}
				$used[] = $block_id;
				$grid[] = array(
					'block_id' => $block_id,
					'row'      => $row,
					'col'      => $col,
					'span'     => $span,
					'row_span' => $row_span,
				);
			}
		}

		if ( empty( $settings['layout_grid'] ) || ! is_array( $settings['layout_grid'] ) ) {
			foreach ( $layout_order as $block_id ) {
				if ( in_array( $block_id, $used, true ) ) {
					continue;
				}
				$placed = false;
				for ( $row = 1; $row <= 15; $row++ ) {
					for ( $col = 1; $col <= 5; $col++ ) {
						$cell_key = $row . '-' . $col;
						if ( isset( $occupied[ $cell_key ] ) ) {
							continue;
						}
						$occupied[ $cell_key ] = true;
						$grid[]                = array(
							'block_id' => $block_id,
							'row'      => $row,
							'col'      => $col,
							'span'     => 1,
							'row_span' => 1,
						);
						$placed                = true;
						break;
					}
					if ( $placed ) {
						break;
					}
				}
			}
		}

		usort(
			$grid,
			static function ( array $left, array $right ): int {
				$left_row  = (int) $left['row'];
				$right_row = (int) $right['row'];
				if ( $left_row === $right_row ) {
					return (int) $left['col'] <=> (int) $right['col'];
				}
				return $left_row <=> $right_row;
			}
		);

		return $grid;
	}

	/**
	 * Resolve layout template choice.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return string
	 */
	private static function resolve_layout_template( array $settings ): string {
		$template = isset( $settings['layout_template'] ) && is_string( $settings['layout_template'] )
		? sanitize_key( $settings['layout_template'] )
		: 'sidebar_left';

		if ( ! in_array( $template, array( 'sidebar_left', 'sidebar_right', 'sidebar_left_header', 'sidebar_right_header' ), true ) ) {
			return 'sidebar_left';
		}

		return $template;
	}

	/**
	 * Determine whether template includes header lane.
	 *
	 * @param string $template Layout template key.
	 * @return bool
	 */
	private static function template_has_header( string $template ): bool {
		return in_array( $template, array( 'sidebar_left_header', 'sidebar_right_header' ), true );
	}

	/**
	 * Determine whether template uses left sidebar proportions.
	 *
	 * @param string $template Layout template key.
	 * @return bool
	 */
	private static function is_sidebar_left_template( string $template ): bool {
		return in_array( $template, array( 'sidebar_left', 'sidebar_left_header' ), true );
	}

	/**
	 * Resolve template column spans.
	 *
	 * @param string $template Layout template key.
	 * @return array<string, int>
	 */
	private static function resolve_layout_columns( string $template ): array {
		if ( ! self::is_sidebar_left_template( $template ) ) {
			return array(
				'main_start'    => 1,
				'main_span'     => 3,
				'sidebar_start' => 4,
				'sidebar_span'  => 2,
			);
		}

		return array(
			'sidebar_start' => 1,
			'sidebar_span'  => 2,
			'main_start'    => 3,
			'main_span'     => 3,
		);
	}

	/**
	 * Resolve lane by item center.
	 *
	 * @param array<string, mixed> $item Layout item.
	 * @param string               $template Layout template.
	 * @return string
	 */
	private static function resolve_layout_lane( array $item, string $template ): string {
		$row      = isset( $item['row'] ) ? (int) $item['row'] : 1;
		$row_span = isset( $item['row_span'] ) ? (int) $item['row_span'] : 1;
		$col      = isset( $item['col'] ) ? (int) $item['col'] : 1;
		$span     = isset( $item['span'] ) ? (int) $item['span'] : 1;
		if ( self::template_has_header( $template ) && 1 === $row && 1 === $row_span ) {
			return 'header';
		}
		$center = (float) $col + ( (float) $span - 1.0 ) / 2.0;
		if ( self::is_sidebar_left_template( $template ) ) {
			return $center <= 2.5 ? 'sidebar' : 'main';
		}

		return $center >= 3.5 ? 'sidebar' : 'main';
	}

	/**
	 * Resolve start/span for a lane.
	 *
	 * @param string $lane     Lane key.
	 * @param string $template Layout template.
	 * @return array<string, int>
	 */
	private static function get_lane_columns( string $lane, string $template ): array {
		if ( 'header' === $lane ) {
			return array(
				'start' => 1,
				'span'  => 5,
			);
		}
		$template_columns = self::resolve_layout_columns( $template );
		if ( 'sidebar' === $lane ) {
			return array(
				'start' => (int) $template_columns['sidebar_start'],
				'span'  => (int) $template_columns['sidebar_span'],
			);
		}

		return array(
			'start' => (int) $template_columns['main_start'],
			'span'  => (int) $template_columns['main_span'],
		);
	}

	/**
	 * Build compacted lane blocks for render output.
	 *
	 * @param array<int, array<string, mixed>> $visible_blocks Visible block payload.
	 * @param string                           $lane Lane key.
	 * @param string                           $template Layout template.
	 * @return array<string, mixed>
	 */
	private static function build_lane_layout_blocks( array $visible_blocks, string $lane, string $template ): array {
		$lane_columns = self::get_lane_columns( $lane, $template );
		$lane_blocks  = array();
		foreach ( $visible_blocks as $block ) {
			if ( self::resolve_layout_lane( $block, $template ) !== $lane ) {
				continue;
			}
			$lane_blocks[] = $block;
		}

		$occupied_rows = array();
		foreach ( $lane_blocks as $block ) {
			$row      = isset( $block['row'] ) ? (int) $block['row'] : 1;
			$row_span = isset( $block['row_span'] ) ? (int) $block['row_span'] : 1;
			for ( $index = $row; $index < $row + $row_span; $index++ ) {
				$occupied_rows[ $index ] = true;
			}
		}

		$sorted_rows = array_keys( $occupied_rows );
		sort( $sorted_rows, SORT_NUMERIC );
		$row_map  = array();
		$position = 1;
		foreach ( $sorted_rows as $row ) {
			$row_map[ (int) $row ] = $position;
			++$position;
		}

		$normalized = array();
		foreach ( $lane_blocks as $block ) {
			$row                       = isset( $block['row'] ) ? (int) $block['row'] : 1;
			$row_span                  = isset( $block['row_span'] ) ? (int) $block['row_span'] : 1;
			$end_row                   = $row + $row_span - 1;
			$display_row               = isset( $row_map[ $row ] ) ? (int) $row_map[ $row ] : 1;
			$display_end_row           = isset( $row_map[ $end_row ] ) ? (int) $row_map[ $end_row ] : $display_row;
			$col                       = isset( $block['col'] ) ? (int) $block['col'] : 1;
			$span                      = isset( $block['span'] ) ? (int) $block['span'] : 1;
			$lane_col                  = max( 1, $col - (int) $lane_columns['start'] + 1 );
			$lane_max_span             = max( 1, (int) $lane_columns['span'] - $lane_col + 1 );
			$lane_span                 = max( 1, min( $lane_max_span, $span ) );
			$block['display_row']      = $display_row;
			$block['display_row_span'] = max( 1, $display_end_row - $display_row + 1 );
			$block['display_col']      = $lane_col;
			$block['display_col_span'] = $lane_span;
			$normalized[]              = $block;
		}

		usort(
			$normalized,
			static function ( array $left, array $right ): int {
				$left_row  = (int) ( $left['display_row'] ?? 1 );
				$right_row = (int) ( $right['display_row'] ?? 1 );
				if ( $left_row === $right_row ) {
					$left_col  = (int) ( $left['display_col'] ?? 1 );
					$right_col = (int) ( $right['display_col'] ?? 1 );
					return $left_col <=> $right_col;
				}
				return $left_row <=> $right_row;
			}
		);

		return array(
			'blocks'  => $normalized,
			'rows'    => count( $sorted_rows ),
			'columns' => (int) $lane_columns['span'],
		);
	}

	/**
	 * Local SEO shortcode layout block whitelist.
	 *
	 * @return array<int, string>
	 */
	private static function layout_allowed_blocks(): array {
		return array(
			'business_name',
			'legal_name',
			'address',
			'phone',
			'map',
			'image_url',
			'logo_url',
			'vat_id',
			'pricing',
			'service_areas',
			'service_catalog',
			'opening_hours',
			'special_hours',
		);
	}

	/**
	 * Build service-area plain text summary for shortcode output.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return string
	 */
	private static function build_service_areas_text( array $settings ): string {
		return implode( ' | ', self::build_service_areas_lines( $settings ) );
	}

	/**
	 * Build service-area lines for list display.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<int, string>
	 */
	private static function build_service_areas_lines( array $settings ): array {
		$parts = array();

		$cities       = isset( $settings['service_area_cities'] ) && is_array( $settings['service_area_cities'] )
		? $settings['service_area_cities']
		: array();
		$clean_cities = array();
		foreach ( $cities as $city ) {
			$name = trim( (string) $city );
			if ( '' !== $name ) {
				$clean_cities[] = $name;
			}
		}
		if ( ! empty( $clean_cities ) ) {
			$parts[] = implode( ', ', $clean_cities );
		}

		$codes       = isset( $settings['service_area_postal_codes'] ) && is_array( $settings['service_area_postal_codes'] )
		? $settings['service_area_postal_codes']
		: array();
		$clean_codes = array();
		foreach ( $codes as $code ) {
			$value = trim( (string) $code );
			if ( '' !== $value ) {
				$clean_codes[] = $value;
			}
		}
		if ( ! empty( $clean_codes ) ) {
			$parts[] = sprintf( '%s: %s', __( 'Postal', 'airygen-seo' ), implode( ', ', $clean_codes ) );
		}

		$radius = isset( $settings['service_area_radius_km'] ) ? (float) $settings['service_area_radius_km'] : 0.0;
		if ( $radius > 0 ) {
			$parts[] = sprintf( '%s: %s km', __( 'Radius', 'airygen-seo' ), (string) (int) round( $radius ) );
		}

		return $parts;
	}

	/**
	 * Build displayable service catalog item lines for shortcode output.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<int, string>
	 */
	private static function build_service_catalog_display_items( array $settings ): array {
		$raw_items = isset( $settings['service_catalog_items'] ) && is_array( $settings['service_catalog_items'] )
		? $settings['service_catalog_items']
		: array();
		$result    = array();

		foreach ( $raw_items as $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				continue;
			}
			$name        = trim( (string) ( $raw_item['name'] ?? '' ) );
			$description = trim( (string) ( $raw_item['description'] ?? '' ) );
			if ( '' === $name && '' === $description ) {
				continue;
			}
			if ( '' !== $name && '' !== $description ) {
				$result[] = $name . ': ' . $description;
				continue;
			}
			$result[] = '' !== $name ? $name : $description;
		}

		return $result;
	}

	/**
	 * Split multiline textarea value into clean text lines.
	 *
	 * @param string $value Raw text.
	 * @return array<int, string>
	 */
	private static function split_multiline_text( string $value ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		if ( false === $lines ) {
			return array();
		}

		$result = array();
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$result[] = $line;
			}
		}

		return $result;
	}

	/**
	 * Convert full-day opening line into friendly English label.
	 *
	 * @param string $line Opening-hours line.
	 * @return string
	 */
	private static function format_opening_hours_line_for_display( string $line ): string {
		$trimmed = trim( $line );
		if ( preg_match( '/^([A-Za-z]{2}(?:-[A-Za-z]{2})?)\s+00:00-23:59$/', $trimmed, $matches ) ) {
			return $matches[1] . ' Open 24 hours';
		}
		if ( '00:00-23:59' === $trimmed ) {
			return 'Open 24 hours';
		}

		return $trimmed;
	}

	/**
	 * Normalize special-hours separator spacing for UI display.
	 *
	 * @param string $line Special-hours line.
	 * @return string
	 */
	private static function format_special_hours_line_for_display( string $line ): string {
		$normalized = str_replace(
			array( '｜', '∣', '︱', '│' ),
			'|',
			trim( $line )
		);
		$normalized = preg_replace( '/[\x{2012}\x{2013}\x{2014}\x{2212}]/u', '-', $normalized );
		if ( ! is_string( $normalized ) ) {
			return trim( $line );
		}

		$parts = explode( '|', $normalized, 2 );
		if ( count( $parts ) < 2 ) {
			return trim( $normalized );
		}

		return trim( (string) $parts[0] ) . ' | ' . trim( (string) $parts[1] );
	}

	/**
	 * Render map iframe markup for unified Local SEO output.
	 *
	 * @param array<string, mixed> $atts Shortcode attrs.
	 * @return string
	 */
	public static function render_local_map_shortcode( array $atts ): string {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return '';
		}

		$atts     = shortcode_atts(
			array(
				'width'  => '100%',
				'height' => '360',
				'zoom'   => '',
				'branch' => '',
			),
			$atts,
			self::SHORTCODE_LOCAL_SEO
		);
		$settings = self::resolve_shortcode_settings( $settings, $atts );

		$lat = isset( $settings['latitude'] ) ? (float) $settings['latitude'] : 0.0;
		$lng = isset( $settings['longitude'] ) ? (float) $settings['longitude'] : 0.0;
		if ( 0.0 === $lat && 0.0 === $lng ) {
			return '';
		}

		$zoom = isset( $atts['zoom'] ) && is_numeric( $atts['zoom'] )
		? (int) $atts['zoom']
		: (int) ( $settings['map_zoom'] ?? 15 );

		if ( $zoom < 1 ) {
			$zoom = 1;
		}
		if ( $zoom > 21 ) {
			$zoom = 21;
		}

		$address = rawurlencode(
			trim(
				sprintf(
					'%s %s %s %s %s',
					(string) ( $settings['street_address'] ?? '' ),
					(string) ( $settings['city'] ?? '' ),
					(string) ( $settings['region'] ?? '' ),
					(string) ( $settings['postal_code'] ?? '' ),
					(string) ( $settings['country'] ?? '' )
				)
			)
		);

		$query = rawurlencode( sprintf( '%F,%F', $lat, $lng ) );
		if ( '' !== $address ) {
			$query = $address;
		}

		$src = sprintf( 'https://www.google.com/maps?q=%s&z=%d&output=embed', $query, $zoom );

		$width_attr  = isset( $atts['width'] ) ? sanitize_text_field( (string) $atts['width'] ) : '100%';
		$height_attr = isset( $atts['height'] ) ? sanitize_text_field( (string) $atts['height'] ) : '360';

		return sprintf(
			'<iframe src="%s" width="%s" height="%s" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>',
			esc_url( $src ),
			esc_attr( $width_attr ),
			esc_attr( $height_attr )
		);
	}

	/**
	 * Build Local SEO info values used by shortcode/block rendering.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<string, string>
	 */
	private static function build_local_info_values( array $settings ): array {
		return array(
			'name'        => (string) ( $settings['business_name'] ?? '' ),
			'phone'       => (string) ( $settings['phone'] ?? '' ),
			'address'     => trim(
				sprintf(
					'%s %s %s %s %s',
					(string) ( $settings['street_address'] ?? '' ),
					(string) ( $settings['city'] ?? '' ),
					(string) ( $settings['region'] ?? '' ),
					(string) ( $settings['postal_code'] ?? '' ),
					(string) ( $settings['country'] ?? '' )
				)
			),
			'city'        => (string) ( $settings['city'] ?? '' ),
			'region'      => (string) ( $settings['region'] ?? '' ),
			'postal_code' => (string) ( $settings['postal_code'] ?? '' ),
			'country'     => (string) ( $settings['country'] ?? '' ),
		);
	}

	/**
	 * Resolve settings for shortcode rendering, optionally using a branch override.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @param array<string, mixed> $atts     Shortcode attrs.
	 * @return array<string, mixed>
	 */
	private static function resolve_shortcode_settings( array $settings, array $atts ): array {
		$branch_slug = isset( $atts['branch'] ) ? sanitize_title( (string) $atts['branch'] ) : '';
		if ( '' === $branch_slug ) {
			return $settings;
		}

		$branches = isset( $settings['branches'] ) && is_array( $settings['branches'] )
		? $settings['branches']
		: array();

		foreach ( $branches as $branch ) {
			if ( ! is_array( $branch ) ) {
				continue;
			}
			$slug = isset( $branch['slug'] ) ? sanitize_title( (string) $branch['slug'] ) : '';
			if ( '' === $slug && isset( $branch['label'] ) ) {
				$slug = sanitize_title( (string) $branch['label'] );
			}
			if ( $slug !== $branch_slug ) {
				continue;
			}
			if ( isset( $branch['enabled'] ) && ! $branch['enabled'] ) {
				return $settings;
			}

			return self::apply_branch_overrides( $settings, $branch );
		}

		return $settings;
	}

	/**
	 * Apply branch overrides on top of main store settings.
	 *
	 * @param array<string, mixed> $settings Main store settings.
	 * @param array<string, mixed> $branch   Branch settings.
	 * @return array<string, mixed>
	 */
	private static function apply_branch_overrides( array $settings, array $branch ): array {
		$merged = $settings;

		$string_fields = array(
			'business_name',
			'phone',
			'image_url',
			'street_address',
			'city',
			'region',
			'postal_code',
			'country',
			'opening_hours',
			'special_hours',
			'geo_region_code',
			'geo_placename',
		);
		foreach ( $string_fields as $field ) {
			$value = isset( $branch[ $field ] ) ? trim( (string) $branch[ $field ] ) : '';
			if ( '' !== $value ) {
				$merged[ $field ] = $value;
			}
		}

		$lat = isset( $branch['latitude'] ) && is_numeric( $branch['latitude'] )
		? (float) $branch['latitude']
		: 0.0;
		$lng = isset( $branch['longitude'] ) && is_numeric( $branch['longitude'] )
		? (float) $branch['longitude']
		: 0.0;
		if ( 0.0 !== $lat || 0.0 !== $lng ) {
			$merged['latitude']  = $lat;
			$merged['longitude'] = $lng;
		}

		if ( isset( $branch['kml_in_sitemap'] ) ) {
			$merged['kml_in_sitemap'] = (bool) $branch['kml_in_sitemap'];
		}

		if ( isset( $branch['service_area_cities'] ) && is_array( $branch['service_area_cities'] ) && count( $branch['service_area_cities'] ) > 0 ) {
			$merged['service_area_cities'] = $branch['service_area_cities'];
		}
		if ( isset( $branch['service_area_postal_codes'] ) && is_array( $branch['service_area_postal_codes'] ) && count( $branch['service_area_postal_codes'] ) > 0 ) {
			$merged['service_area_postal_codes'] = $branch['service_area_postal_codes'];
		}
		if ( isset( $branch['service_area_radius_km'] ) && is_numeric( $branch['service_area_radius_km'] ) ) {
			$radius = (float) $branch['service_area_radius_km'];
			if ( $radius > 0 ) {
				$merged['service_area_radius_km'] = $radius;
			}
		}

		return $merged;
	}

	/**
	 * Build LocalBusiness schema payload.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<string, mixed>
	 */
	private static function build_schema( array $settings, bool $full = true ): array {
		$type = (string) ( $settings['business_type'] ?? 'LocalBusiness' );
		if ( '' === $type ) {
			$type = 'LocalBusiness';
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => $type,
			'@id'      => self::local_business_id(),
		);

		if ( ! $full ) {
			return $schema;
		}

		$name = trim( (string) ( $settings['business_name'] ?? '' ) );
		if ( '' === $name ) {
			return array();
		}
		$schema['name'] = $name;

		$image = trim( (string) ( $settings['image_url'] ?? '' ) );
		if ( '' !== $image ) {
			$schema['image'] = $image;
		}
		$logo = trim( (string) ( $settings['logo_url'] ?? '' ) );
		if ( '' !== $logo ) {
			$schema['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}

		$phone = trim( (string) ( $settings['phone'] ?? '' ) );
		if ( '' !== $phone ) {
			$schema['telephone'] = $phone;
		}
		$legal_name = trim( (string) ( $settings['legal_name'] ?? '' ) );
		if ( '' !== $legal_name ) {
			$schema['legalName'] = $legal_name;
		}

		$price_range = self::resolve_price_range( $settings );
		if ( '' !== $price_range ) {
			$schema['priceRange'] = $price_range;
		}
		$aggregate_rating = self::build_aggregate_rating( $settings );
		if ( ! empty( $aggregate_rating ) ) {
			$schema['aggregateRating'] = $aggregate_rating;
		}

		$same_as = self::build_same_as( $settings );
		if ( ! empty( $same_as ) ) {
			$schema['sameAs'] = $same_as;
		}

		$vat_id = trim( (string) ( $settings['vat_id'] ?? '' ) );
		if ( '' !== $vat_id ) {
			$schema['vatID'] = $vat_id;
			$schema['taxID'] = $vat_id;
		}

		$street = trim( (string) ( $settings['street_address'] ?? '' ) );
		$city   = trim( (string) ( $settings['city'] ?? '' ) );
		$region = trim( (string) ( $settings['region'] ?? '' ) );
		$postal = trim( (string) ( $settings['postal_code'] ?? '' ) );
		$county = trim( (string) ( $settings['country'] ?? '' ) );
		if ( '' !== $street || '' !== $city || '' !== $region || '' !== $postal || '' !== $county ) {
			$schema['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $street,
				'addressLocality' => $city,
				'addressRegion'   => $region,
				'postalCode'      => $postal,
				'addressCountry'  => $county,
			);
		}

		$lat = isset( $settings['latitude'] ) ? (float) $settings['latitude'] : 0.0;
		$lng = isset( $settings['longitude'] ) ? (float) $settings['longitude'] : 0.0;
		if ( 0.0 !== $lat || 0.0 !== $lng ) {
			$schema['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $lat,
				'longitude' => $lng,
			);
		}

		$area_served = self::build_area_served( $settings );
		if ( ! empty( $area_served ) ) {
			$schema['areaServed'] = $area_served;
		}

		$hours = self::parse_opening_hours( (string) ( $settings['opening_hours'] ?? '' ), true );
		if ( ! empty( $hours ) ) {
			$schema['openingHoursSpecification'] = $hours;
		}
		$special_hours = self::parse_special_hours( (string) ( $settings['special_hours'] ?? '' ) );
		if ( ! empty( $special_hours ) ) {
			$schema['specialOpeningHoursSpecification'] = $special_hours;
		}

		$catalog = self::build_offer_catalog( $settings );
		if ( ! empty( $catalog ) ) {
			$schema['hasOfferCatalog'] = $catalog;
		}

		return $schema;
	}

	/**
	 * Build Service schema for service pages.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<string, mixed>
	 */
	private static function build_service_schema( array $settings ): array {
		if ( ! is_singular() ) {
			return array();
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof \WP_Post ) ) {
			return array();
		}

		$is_service_post = 'service' === $post->post_type;
		$is_service_meta = 'Service' === PostData::get_field( $post->ID, 'schemaArticleType' );
		if ( ! $is_service_post && ! $is_service_meta ) {
			return array();
		}

		$name = trim( get_the_title( $post ) );
		if ( '' === $name ) {
			return array();
		}

		$description  = '';
		$catalog_item = self::find_catalog_item_by_name( $settings, $name );
		if ( ! empty( $catalog_item ) ) {
			$description = trim( (string) ( $catalog_item['description'] ?? '' ) );
		}
		if ( '' === $description ) {
			$description = trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
		}

		$schema = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'Service',
			'@id'              => trailingslashit( get_permalink( $post ) ) . '#service',
			'name'             => $name,
			'mainEntityOfPage' => get_permalink( $post ),
			'provider'         => array(
				'@id' => self::local_business_id(),
			),
		);

		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		$area_served = self::build_area_served( $settings );
		if ( ! empty( $area_served ) ) {
			$schema['areaServed'] = $area_served;
		}

		$price_range = self::resolve_price_range( $settings );
		if ( '' !== $price_range ) {
			$schema['offers'] = array(
				'@type' => 'Offer',
				'price' => $price_range,
			);
		}

		return $schema;
	}

	/**
	 * Find catalog item by service name.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @param string               $name     Service name.
	 * @return array<string, mixed>
	 */
	private static function find_catalog_item_by_name( array $settings, string $name ): array {
		$items = $settings['service_catalog_items'] ?? array();
		if ( ! is_array( $items ) ) {
			return array();
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item_name = trim( (string) ( $item['name'] ?? '' ) );
			if ( '' === $item_name ) {
				continue;
			}
			if ( strtolower( $item_name ) === strtolower( $name ) ) {
				return $item;
			}
		}

		return array();
	}

	/**
	 * Build stable LocalBusiness @id.
	 *
	 * @return string
	 */
	private static function local_business_id(): string {
		return trailingslashit( home_url( '/' ) ) . '#identity';
	}

	/**
	 * Merge Organization and LocalBusiness nodes into a single identity node.
	 *
	 * @param array<string, mixed>|null $organization Organization node from Schema graph.
	 * @param array<string, mixed>      $local        LocalBusiness node.
	 * @return array<string, mixed>
	 */
	private static function merge_identity_nodes( ?array $organization, array $local ): array {
		$identity = $local;
		if ( ! is_array( $organization ) || empty( $organization ) ) {
			return $identity;
		}

		$identity           = array_merge( $organization, $local );
		$identity['@id']    = self::local_business_id();
		$identity['@type']  = self::merge_schema_types( $organization['@type'] ?? null, $local['@type'] ?? null );
		$identity['sameAs'] = self::merge_url_list( $organization['sameAs'] ?? array(), $local['sameAs'] ?? array() );

		if ( empty( $identity['sameAs'] ) ) {
			unset( $identity['sameAs'] );
		}

		$image_values = array_merge(
			self::extract_image_values( $organization['image'] ?? null ),
			self::extract_image_values( $local['image'] ?? null )
		);
		$image_values = self::dedupe_strings( $image_values );
		if ( 1 === count( $image_values ) ) {
			$identity['image'] = $image_values[0];
		} elseif ( count( $image_values ) > 1 ) {
			$identity['image'] = $image_values;
		}

		$logo_node = self::logo_node_from_value( $local['logo'] ?? null );
		if ( null === $logo_node ) {
			$logo_node = self::logo_node_from_value( $organization['logo'] ?? null );
		}
		if ( null !== $logo_node ) {
			$identity['logo'] = $logo_node;
		}

		return $identity;
	}

	/**
	 * Force publisher references on supported nodes to the unified identity @id.
	 *
	 * @param array<int, array<string, mixed>> $graph       Graph nodes.
	 * @param string                            $identity_id Canonical identity @id.
	 * @return array<int, array<string, mixed>>
	 */
	private static function apply_identity_publisher_reference( array $graph, string $identity_id ): array {
		foreach ( $graph as $index => $node ) {
			$has_publisher_key = isset( $node['publisher'] );
			if ( ! $has_publisher_key && ! self::node_supports_publisher_reference( $node ) ) {
				continue;
			}

			$node['publisher'] = array(
				'@id' => $identity_id,
			);
			$graph[ $index ]   = $node;
		}

		return $graph;
	}

	/**
	 * Check whether a node supports `publisher` linkage.
	 *
	 * @param array<string, mixed> $node Graph node.
	 * @return bool
	 */
	private static function node_supports_publisher_reference( array $node ): bool {
		$types = self::node_types( $node );
		foreach ( $types as $type ) {
			if ( 'Article' === $type || 'NewsArticle' === $type || 'BlogPosting' === $type || 'TechArticle' === $type || 'Report' === $type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether node is Organization.
	 *
	 * @param array<string, mixed> $node Graph node.
	 * @return bool
	 */
	private static function node_is_organization( array $node ): bool {
		return in_array( 'Organization', self::node_types( $node ), true );
	}

	/**
	 * Determine whether node is LocalBusiness.
	 *
	 * @param array<string, mixed> $node Graph node.
	 * @return bool
	 */
	private static function node_is_local_business( array $node ): bool {
		return in_array( 'LocalBusiness', self::node_types( $node ), true );
	}

	/**
	 * Normalize schema node @type into string list.
	 *
	 * @param array<string, mixed> $node Graph node.
	 * @return array<int, string>
	 */
	private static function node_types( array $node ): array {
		$raw_types = $node['@type'] ?? null;
		if ( is_string( $raw_types ) ) {
			$type = trim( $raw_types );
			if ( '' === $type ) {
				return array();
			}
			return array( $type );
		}

		if ( ! is_array( $raw_types ) ) {
			return array();
		}

		$types = array();
		foreach ( $raw_types as $raw_type ) {
			if ( ! is_string( $raw_type ) ) {
				continue;
			}
			$type = trim( $raw_type );
			if ( '' === $type ) {
				continue;
			}
			if ( in_array( $type, $types, true ) ) {
				continue;
			}
			$types[] = $type;
		}

		return $types;
	}

	/**
	 * Merge schema @type values.
	 *
	 * @param mixed $organization_type Organization @type value.
	 * @param mixed $local_type        LocalBusiness @type value.
	 * @return string|array<int, string>
	 */
	private static function merge_schema_types( $organization_type, $local_type ) {
		$types = array();
		foreach ( array( $organization_type, $local_type ) as $source_type ) {
			if ( is_string( $source_type ) ) {
				$value = trim( $source_type );
				if ( '' !== $value && ! in_array( $value, $types, true ) ) {
					$types[] = $value;
				}
				continue;
			}
			if ( ! is_array( $source_type ) ) {
				continue;
			}
			foreach ( $source_type as $item ) {
				if ( ! is_string( $item ) ) {
					continue;
				}
				$value = trim( $item );
				if ( '' === $value || in_array( $value, $types, true ) ) {
					continue;
				}
				$types[] = $value;
			}
		}

		if ( empty( $types ) ) {
			return 'LocalBusiness';
		}

		if ( 1 === count( $types ) ) {
			return $types[0];
		}

		return $types;
	}

	/**
	 * Normalize logo value to ImageObject node.
	 *
	 * @param mixed $value Logo value.
	 * @return array<string, string>|null
	 */
	private static function logo_node_from_value( $value ): ?array {
		if ( is_array( $value ) ) {
			$url = self::string_or_null( $value['url'] ?? null );
			if ( null !== $url ) {
				return array(
					'@type' => 'ImageObject',
					'url'   => $url,
				);
			}
		}

		$url = self::string_or_null( $value );
		if ( null === $url ) {
			return null;
		}

		return array(
			'@type' => 'ImageObject',
			'url'   => $url,
		);
	}

	/**
	 * Extract image URLs from schema image field.
	 *
	 * @param mixed $image Image field value.
	 * @return array<int, string>
	 */
	private static function extract_image_values( $image ): array {
		if ( is_string( $image ) ) {
			$value = trim( $image );
			if ( '' === $value ) {
				return array();
			}
			return array( $value );
		}

		if ( ! is_array( $image ) ) {
			return array();
		}

		if ( isset( $image['url'] ) && is_scalar( $image['url'] ) ) {
			$value = trim( (string) $image['url'] );
			if ( '' === $value ) {
				return array();
			}
			return array( $value );
		}

		$values = array();
		foreach ( $image as $entry ) {
			$entry_value = self::extract_image_values( $entry );
			foreach ( $entry_value as $url ) {
				$values[] = $url;
			}
		}

		return $values;
	}

	/**
	 * Merge URL list fields without duplicates.
	 *
	 * @param mixed $first  First URL list.
	 * @param mixed $second Second URL list.
	 * @return array<int, string>
	 */
	private static function merge_url_list( $first, $second ): array {
		$values = array();
		foreach ( array( $first, $second ) as $source ) {
			if ( is_string( $source ) ) {
				$value = trim( $source );
				if ( '' !== $value ) {
					$values[] = $value;
				}
				continue;
			}
			if ( ! is_array( $source ) ) {
				continue;
			}
			foreach ( $source as $entry ) {
				if ( ! is_scalar( $entry ) ) {
					continue;
				}
				$value = trim( (string) $entry );
				if ( '' === $value ) {
					continue;
				}
				$values[] = $value;
			}
		}

		return self::dedupe_strings( $values );
	}

	/**
	 * De-duplicate string list preserving order.
	 *
	 * @param array<int, string> $values Input values.
	 * @return array<int, string>
	 */
	private static function dedupe_strings( array $values ): array {
		$unique = array();
		foreach ( $values as $value ) {
			if ( in_array( $value, $unique, true ) ) {
				continue;
			}
			$unique[] = $value;
		}

		return $unique;
	}

	/**
	 * Normalize scalar value into string.
	 *
	 * @param mixed $value Input value.
	 * @return string|null
	 */
	private static function string_or_null( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_scalar( $value ) ) {
			$string = trim( (string) $value );
			if ( '' !== $string ) {
				return $string;
			}
		}

		return null;
	}

	/**
	 * Build areaServed array from configured service areas.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_area_served( array $settings ): array {
		$areas = array();

		$cities = isset( $settings['service_area_cities'] ) && is_array( $settings['service_area_cities'] )
		? $settings['service_area_cities']
		: array();
		foreach ( $cities as $city ) {
			$name = trim( (string) $city );
			if ( '' === $name ) {
				continue;
			}
			$areas[] = array(
				'@type' => 'City',
				'name'  => $name,
			);
		}

		$postal_codes = isset( $settings['service_area_postal_codes'] ) && is_array( $settings['service_area_postal_codes'] )
		? $settings['service_area_postal_codes']
		: array();
		$country      = trim( (string) ( $settings['country'] ?? '' ) );
		foreach ( $postal_codes as $postal_code ) {
			$code = trim( (string) $postal_code );
			if ( '' === $code ) {
				continue;
			}
			$entry = array(
				'@type'      => 'PostalCode',
				'postalCode' => $code,
			);
			if ( '' !== $country ) {
				$entry['addressCountry'] = $country;
			}
			$areas[] = $entry;
		}

		$radius_km = isset( $settings['service_area_radius_km'] ) ? (float) $settings['service_area_radius_km'] : 0.0;
		$lat       = isset( $settings['latitude'] ) ? (float) $settings['latitude'] : 0.0;
		$lng       = isset( $settings['longitude'] ) ? (float) $settings['longitude'] : 0.0;
		if ( $radius_km > 0 && ( 0.0 !== $lat || 0.0 !== $lng ) ) {
			$areas[] = array(
				'@type'       => 'GeoCircle',
				'geoMidpoint' => array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => $lat,
					'longitude' => $lng,
				),
				'geoRadius'   => (string) (int) round( $radius_km * 1000 ),
			);
		}

		if ( empty( $areas ) ) {
			$fallback = trim(
				sprintf(
					'%s %s %s',
					(string) ( $settings['city'] ?? '' ),
					(string) ( $settings['region'] ?? '' ),
					(string) ( $settings['country'] ?? '' )
				)
			);
			if ( '' !== $fallback ) {
				$areas[] = array(
					'@type' => 'Place',
					'name'  => $fallback,
				);
			}
		}

		return $areas;
	}

	/**
	 * Build OfferCatalog payload from settings.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<string, mixed>
	 */
	private static function build_offer_catalog( array $settings ): array {
		$raw_items = $settings['service_catalog_items'] ?? array();
		if ( ! is_array( $raw_items ) ) {
			return array();
		}

		$items = array();
		foreach ( $raw_items as $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				continue;
			}

			$name = trim( (string) ( $raw_item['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}

			$service = array(
				'@type' => 'Service',
				'name'  => $name,
			);

			$description = trim( (string) ( $raw_item['description'] ?? '' ) );
			if ( '' !== $description ) {
				$service['description'] = $description;
			}

			$items[] = array(
				'@type'       => 'Offer',
				'itemOffered' => $service,
			);
		}

		if ( empty( $items ) ) {
			return array();
		}

		$catalog_name = trim( (string) ( $settings['service_catalog_name'] ?? '' ) );
		if ( '' === $catalog_name ) {
			$catalog_name = 'Services';
		}

		return array(
			'@type'           => 'OfferCatalog',
			'name'            => $catalog_name,
			'itemListElement' => $items,
		);
	}

	/**
	 * Parse opening hours lines into schema specs.
	 *
	 * Expected format per line: `Mo-Fr 09:00-18:00`
	 *
	 * @param string $raw Raw lines.
	 * @return array<int, array<string, mixed>>
	 */
	private static function parse_opening_hours( string $raw, bool $allow_multi_ranges = false ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		if ( false === $lines ) {
			return array();
		}

		$day_map = array(
			'Mo' => 'Monday',
			'Tu' => 'Tuesday',
			'We' => 'Wednesday',
			'Th' => 'Thursday',
			'Fr' => 'Friday',
			'Sa' => 'Saturday',
			'Su' => 'Sunday',
		);

		$rows = array();
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}

			$normalized_line = preg_replace( '/[\x{2012}\x{2013}\x{2014}\x{2212}]/u', '-', $line );
			if ( ! is_string( $normalized_line ) ) {
				continue;
			}

			if ( ! preg_match( '/^([A-Za-z]{2})(?:-([A-Za-z]{2}))?\s+(.+)$/', $normalized_line, $m ) ) {
				continue;
			}

			$from = isset( $m[1] ) ? (string) $m[1] : '';
			$to   = isset( $m[2] ) ? (string) $m[2] : '';
			if ( ! isset( $day_map[ $from ] ) ) {
				continue;
			}

			$days = self::expand_days( $from, $to, $day_map );
			if ( empty( $days ) ) {
				continue;
			}

			$ranges_raw = isset( $m[3] ) ? trim( (string) $m[3] ) : '';
			$segments   = $allow_multi_ranges
			? preg_split( '/\s*,\s*/', $ranges_raw )
			: array( $ranges_raw );
			if ( false === $segments ) {
				$segments = array( $ranges_raw );
			}

			foreach ( $segments as $segment ) {
				$segment = trim( (string) $segment );
				if ( ! preg_match( '/^([0-9]{2}:[0-9]{2})-([0-9]{2}:[0-9]{2})$/', $segment, $time_match ) ) {
					continue;
				}

				$rows[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $days,
					'opens'     => isset( $time_match[1] ) ? (string) $time_match[1] : '',
					'closes'    => isset( $time_match[2] ) ? (string) $time_match[2] : '',
				);
			}
		}

		return $rows;
	}

	/**
	 * Parse special opening hours lines into schema payload.
	 *
	 * Expected format per line:
	 * - YYYY-MM-DD|09:00-18:00
	 * - YYYY-MM-DD to YYYY-MM-DD|10:00-16:00
	 * - YYYY-MM-DD|closed
	 *
	 * @param string $raw Raw lines.
	 * @return array<int, array<string, mixed>>
	 */
	private static function parse_special_hours( string $raw ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		if ( false === $lines ) {
			return array();
		}

		$rows = array();

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}

			$line = str_replace(
				array( '｜', '∣', '︱', '│' ),
				'|',
				$line
			);
			$line = preg_replace( '/[\x{2012}\x{2013}\x{2014}\x{2212}]/u', '-', $line );
			if ( ! is_string( $line ) ) {
				continue;
			}

			$parts = explode( '|', $line, 2 );
			if ( count( $parts ) < 2 ) {
				continue;
			}

			$date_part = trim( (string) $parts[0] );
			$time_part = trim( strtolower( (string) $parts[1] ) );

			$date_match = array();
			if ( ! preg_match( '/^([0-9]{4}-[0-9]{2}-[0-9]{2})(?:\s+to\s+([0-9]{4}-[0-9]{2}-[0-9]{2}))?$/i', $date_part, $date_match ) ) {
				continue;
			}

			$valid_from    = isset( $date_match[1] ) ? (string) $date_match[1] : '';
			$valid_through = isset( $date_match[2] ) && '' !== (string) $date_match[2]
			? (string) $date_match[2]
			: $valid_from;

			$row = array(
				'@type'        => 'OpeningHoursSpecification',
				'validFrom'    => $valid_from,
				'validThrough' => $valid_through,
			);

			if ( 'closed' === $time_part ) {
				$row['opens']  = '00:00';
				$row['closes'] = '00:00';
				$rows[]        = $row;
				continue;
			}

			$time_match = array();
			if ( ! preg_match( '/^([0-9]{2}:[0-9]{2})-([0-9]{2}:[0-9]{2})$/', $time_part, $time_match ) ) {
				continue;
			}

			$row['opens']  = isset( $time_match[1] ) ? (string) $time_match[1] : '';
			$row['closes'] = isset( $time_match[2] ) ? (string) $time_match[2] : '';
			$rows[]        = $row;
		}

		return $rows;
	}

	/**
	 * Build aggregateRating payload when rating values are provided.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<string, mixed>
	 */
	private static function build_aggregate_rating( array $settings ): array {
		$rating  = isset( $settings['rating_value'] ) ? (float) $settings['rating_value'] : 0.0;
		$reviews = isset( $settings['review_count'] ) ? (int) $settings['review_count'] : 0;
		if ( $rating <= 0 || $reviews <= 0 ) {
			return array();
		}

		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => round( $rating, 1 ),
			'reviewCount' => $reviews,
		);
	}

	/**
	 * Resolve price range output from custom text or level fallback.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return string
	 */
	private static function resolve_price_range( array $settings ): string {
		$custom = trim( (string) ( $settings['price_range_custom'] ?? '' ) );
		if ( '' !== $custom ) {
			return $custom;
		}

		$level = trim( (string) ( $settings['price_range_level'] ?? '' ) );
		if ( in_array( $level, array( '$', '$$', '$$$', '$$$$' ), true ) ) {
			return $level;
		}

		return trim( (string) ( $settings['price_range'] ?? '' ) );
	}

	/**
	 * Convert symbolic price levels into human-readable labels.
	 *
	 * @param string $value Price text.
	 * @return string
	 */
	private static function to_readable_price_level( string $value ): string {
		$trimmed = trim( $value );
		if ( '$' === $trimmed ) {
			return (string) __( 'Budget', 'airygen-seo' );
		}
		if ( '$$' === $trimmed ) {
			return (string) __( 'Moderate', 'airygen-seo' );
		}
		if ( '$$$' === $trimmed ) {
			return (string) __( 'Premium', 'airygen-seo' );
		}
		if ( '$$$$' === $trimmed ) {
			return (string) __( 'Luxury', 'airygen-seo' );
		}

		return $trimmed;
	}

	/**
	 * Build sameAs URL list.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return array<int, string>
	 */
	private static function build_same_as( array $settings ): array {
		$urls = isset( $settings['same_as_urls'] ) && is_array( $settings['same_as_urls'] )
		? $settings['same_as_urls']
		: array();

		$result = array();
		foreach ( $urls as $url ) {
			$clean = esc_url_raw( trim( (string) $url ) );
			if ( '' === $clean ) {
				continue;
			}
			$result[] = $clean;
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * Convert phone-like text segments into tel links.
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	private static function wrap_phone_numbers_in_html( string $html ): string {
		if ( '' === $html ) {
			return $html;
		}

		$segments = preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $segments ) {
			return $html;
		}

		$inside_anchor = 0;
		$inside_script = 0;
		$inside_style  = 0;
		$output        = '';

		foreach ( $segments as $segment ) {
			if ( '' === $segment ) {
				continue;
			}

			if ( '<' === $segment[0] ) {
				if ( preg_match( '/^<\s*\/\s*([a-z0-9:-]+)/i', $segment, $close_match ) ) {
					$tag = strtolower( (string) $close_match[1] );
					if ( 'a' === $tag && $inside_anchor > 0 ) {
						--$inside_anchor;
					}
					if ( 'script' === $tag && $inside_script > 0 ) {
						--$inside_script;
					}
					if ( 'style' === $tag && $inside_style > 0 ) {
						--$inside_style;
					}
				} elseif ( preg_match( '/^<\s*([a-z0-9:-]+)/i', $segment, $open_match ) ) {
					$tag = strtolower( (string) $open_match[1] );
					if ( 'a' === $tag && ! str_starts_with( $segment, '</' ) ) {
						++$inside_anchor;
					}
					if ( 'script' === $tag && ! str_starts_with( $segment, '</' ) ) {
						++$inside_script;
					}
					if ( 'style' === $tag && ! str_starts_with( $segment, '</' ) ) {
						++$inside_style;
					}
				}

				$output .= $segment;
				continue;
			}

			if ( $inside_anchor > 0 || $inside_script > 0 || $inside_style > 0 ) {
				$output .= $segment;
				continue;
			}

			$output .= self::wrap_phone_numbers_in_text_segment( $segment );
		}

		return $output;
	}

	/**
	 * Convert phone-like tokens in plain text into tel links.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function wrap_phone_numbers_in_text_segment( string $text ): string {
		if ( '' === trim( $text ) ) {
			return $text;
		}

		return (string) preg_replace_callback(
			'/(?<![\w@])(\+?[0-9][0-9\-\s().]{5,}[0-9])(?![\w@])/u',
			static function ( array $matches ): string {
				$raw = isset( $matches[1] ) ? (string) $matches[1] : '';
				if ( '' === $raw ) {
					return $raw;
				}

				$digits = preg_replace( '/\D+/', '', $raw );
				if ( ! is_string( $digits ) ) {
					return $raw;
				}

				$length = strlen( $digits );
				if ( $length < 7 || $length > 15 ) {
					return $raw;
				}

				$normalized = preg_replace( '/[^0-9+]/', '', $raw );
				if ( ! is_string( $normalized ) || '' === $normalized ) {
					return $raw;
				}

				if ( str_contains( substr( $normalized, 1 ), '+' ) ) {
					$normalized = '+' . str_replace( '+', '', $normalized );
				}

				$href = 'tel:' . $normalized;
				return sprintf(
					'<a href="%s">%s</a>',
					esc_attr( $href ),
					esc_html( $raw )
				);
			},
			$text
		);
	}

	/**
	 * Expand day range into schema day names.
	 *
	 * @param string                $from    Start day code.
	 * @param string                $to      End day code.
	 * @param array<string, string> $day_map Day code map.
	 * @return array<int, string>
	 */
	private static function expand_days( string $from, string $to, array $day_map ): array {
		$days = array( $day_map[ $from ] );
		if ( '' !== $to && isset( $day_map[ $to ] ) ) {
			$keys       = array_keys( $day_map );
			$from_idx   = array_search( $from, $keys, true );
			$to_idx     = array_search( $to, $keys, true );
			$range_days = array();
			if ( false !== $from_idx && false !== $to_idx && $to_idx >= $from_idx ) {
				for ( $i = $from_idx; $i <= $to_idx; $i++ ) {
					$key          = $keys[ $i ];
					$range_days[] = $day_map[ $key ];
				}
			}
			if ( ! empty( $range_days ) ) {
				$days = $range_days;
			}
		}

		return $days;
	}

	/**
	 * Emit geo meta tags.
	 *
	 * @param array<string, mixed> $settings Local SEO settings.
	 * @return void
	 */
	private static function emit_geo_meta( array $settings ): void {
		$region = trim( (string) ( $settings['geo_region_code'] ?? '' ) );
		$place  = trim( (string) ( $settings['geo_placename'] ?? '' ) );
		$lat    = isset( $settings['latitude'] ) ? (float) $settings['latitude'] : 0.0;
		$lng    = isset( $settings['longitude'] ) ? (float) $settings['longitude'] : 0.0;

		if ( '' !== $region ) {
			printf( "<meta name=\"geo.region\" content=\"%s\" />\n", esc_attr( $region ) );
		}
		if ( '' !== $place ) {
			printf( "<meta name=\"geo.placename\" content=\"%s\" />\n", esc_attr( $place ) );
		}
		if ( 0.0 !== $lat || 0.0 !== $lng ) {
			printf(
				"<meta name=\"geo.position\" content=\"%s;%s\" />\n",
				esc_attr( (string) $lat ),
				esc_attr( (string) $lng )
			);
		}
	}
}
