<?php
/**
 * Stores configuration for the Local SEO module.
 *
 * @package Airygen\Modules\LocalSeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LocalSeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists admin-configurable Local SEO settings.
 */
final class Settings {

	private const OPTION = Constants::OPTION_LOCAL_SEO;

	/**
	 * Ensure the option exists with defaults.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::default_config(), '', 'no' );
			return;
		}

		self::update( (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Retrieve sanitized configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Persist sanitized settings.
	 *
	 * @param array<string, mixed> $value Raw settings array.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize incoming values.
	 *
	 * @param mixed $value Raw option value.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize( $value ): array {
		$config = self::default_config();

		if ( ! is_array( $value ) ) {
			return $config;
		}

		if ( array_key_exists( 'enabled', $value ) ) {
			$config['enabled'] = (bool) $value['enabled'];
		}
		if ( isset( $value['layout_template'] ) && is_string( $value['layout_template'] ) ) {
			$layout_template = sanitize_key( $value['layout_template'] );
			if ( in_array( $layout_template, array( 'sidebar_left', 'sidebar_right', 'sidebar_left_header', 'sidebar_right_header' ), true ) ) {
				$config['layout_template'] = $layout_template;
			}
		}

		if ( isset( $value['business_type'] ) && is_string( $value['business_type'] ) ) {
			$allowed = self::business_types();
			if ( in_array( $value['business_type'], $allowed, true ) ) {
				$config['business_type'] = $value['business_type'];
			}
		}

		$text_fields = array(
			'business_name'                => 160,
			'legal_name'                   => 160,
			'image_url'                    => 300,
			'logo_url'                     => 300,
			'phone'                        => 80,
			'price_range'                  => 60,
			'price_range_custom'           => 60,
			'street_address'               => 200,
			'city'                         => 120,
			'region'                       => 120,
			'postal_code'                  => 40,
			'geo_region_code'              => 30,
			'geo_placename'                => 120,
			'service_catalog_name'         => 120,
			'layout_label_color'           => 20,
			'layout_value_color'           => 20,
			'layout_card_background_color' => 20,
			'footer_nap_text_color'        => 20,
		);

		foreach ( $text_fields as $key => $limit ) {
			if ( ! isset( $value[ $key ] ) || ! is_string( $value[ $key ] ) ) {
				continue;
			}
			$config[ $key ] = mb_substr( sanitize_text_field( $value[ $key ] ), 0, $limit );
		}
		if ( isset( $value['country'] ) && is_string( $value['country'] ) ) {
			$config['country'] = self::sanitize_country_code( $value['country'] );
		}
		if ( isset( $value['price_range_level'] ) && is_string( $value['price_range_level'] ) ) {
			$level = trim( $value['price_range_level'] );
			if ( in_array( $level, array( '$', '$$', '$$$', '$$$$' ), true ) ) {
				$config['price_range_level'] = $level;
			}
		}

		if ( isset( $value['opening_hours'] ) && is_string( $value['opening_hours'] ) ) {
			$config['opening_hours'] = mb_substr( trim( wp_strip_all_tags( $value['opening_hours'] ) ), 0, 1000 );
		}
		if ( isset( $value['special_hours'] ) && is_string( $value['special_hours'] ) ) {
			$config['special_hours'] = mb_substr( trim( wp_strip_all_tags( $value['special_hours'] ) ), 0, 4000 );
		}

		$latitude_is_numeric  = true;
		$longitude_is_numeric = true;
		if ( isset( $value['latitude'] ) ) {
			if ( is_numeric( $value['latitude'] ) ) {
				$config['latitude'] = (float) $value['latitude'];
			} else {
				$config['latitude']  = 0.0;
				$latitude_is_numeric = false;
			}
		}
		if ( isset( $value['longitude'] ) ) {
			if ( is_numeric( $value['longitude'] ) ) {
				$config['longitude'] = (float) $value['longitude'];
			} else {
				$config['longitude']  = 0.0;
				$longitude_is_numeric = false;
			}
		}
		if ( isset( $value['service_area_radius_km'] ) ) {
			$radius = is_numeric( $value['service_area_radius_km'] ) ? (float) $value['service_area_radius_km'] : 0.0;
			if ( $radius < 0 ) {
				$radius = 0.0;
			}
			if ( $radius > 500 ) {
				$radius = 500.0;
			}
			$config['service_area_radius_km'] = $radius;
		}
		if ( isset( $value['rating_value'] ) ) {
			$rating = is_numeric( $value['rating_value'] ) ? (float) $value['rating_value'] : 0.0;
			if ( $rating < 0 ) {
				$rating = 0.0;
			}
			if ( $rating > 5 ) {
				$rating = 5.0;
			}
			$config['rating_value'] = $rating;
		}

		$int_fields = array(
			'map_zoom'                   => array(
				'min' => 1,
				'max' => 21,
			),
			'review_count'               => array(
				'min' => 0,
				'max' => 999999999,
			),
			'layout_card_padding'        => array(
				'min' => 0,
				'max' => 64,
			),
			'layout_label_font_size'     => array(
				'min' => 10,
				'max' => 32,
			),
			'layout_value_font_size'     => array(
				'min' => 10,
				'max' => 40,
			),
			'layout_title_font_size'     => array(
				'min' => 16,
				'max' => 80,
			),
			'footer_nap_font_size'       => array(
				'min' => 10,
				'max' => 48,
			),
			'footer_nap_container_width' => array(
				'min' => 280,
				'max' => 1920,
			),
			'footer_nap_gap'             => array(
				'min' => 0,
				'max' => 48,
			),
			'footer_nap_margin_y'        => array(
				'min' => 0,
				'max' => 200,
			),
		);
		foreach ( $int_fields as $key => $range ) {
			if ( ! isset( $value[ $key ] ) ) {
				continue;
			}
			$number = is_numeric( $value[ $key ] ) ? (int) $value[ $key ] : 0;
			if ( $number < $range['min'] ) {
				$number = $range['min'];
			}
			if ( $number > $range['max'] ) {
				$number = $range['max'];
			}
			$config[ $key ] = $number;
		}

		if ( array_key_exists( 'enable_geo_tags', $value ) ) {
			$config['enable_geo_tags'] = (bool) $value['enable_geo_tags'];
		}
		if ( array_key_exists( 'footer_nap_enabled', $value ) ) {
			$config['footer_nap_enabled'] = (bool) $value['footer_nap_enabled'];
		}
		if ( array_key_exists( 'footer_nap_first_item_bold', $value ) ) {
			$config['footer_nap_first_item_bold'] = (bool) $value['footer_nap_first_item_bold'];
		}
		if ( array_key_exists( 'contact_auto_map_embed', $value ) ) {
			$config['contact_auto_map_embed'] = (bool) $value['contact_auto_map_embed'];
		}
		if ( array_key_exists( 'kml_in_sitemap', $value ) ) {
			$config['kml_in_sitemap'] = (bool) $value['kml_in_sitemap'];
		}
		if ( array_key_exists( 'contact_detailed_opening_hours', $value ) ) {
			$config['contact_detailed_opening_hours'] = (bool) $value['contact_detailed_opening_hours'];
		}
		if ( array_key_exists( 'show_vat_in_footer', $value ) ) {
			$config['show_vat_in_footer'] = (bool) $value['show_vat_in_footer'];
		}
		if ( array_key_exists( 'click_to_call_enabled', $value ) ) {
			$config['click_to_call_enabled'] = (bool) $value['click_to_call_enabled'];
		}
		if ( array_key_exists( 'vat_validate_checksum', $value ) ) {
			$config['vat_validate_checksum'] = (bool) $value['vat_validate_checksum'];
		}
		if ( array_key_exists( 'layout_show_card_border', $value ) ) {
			$config['layout_show_card_border'] = (bool) $value['layout_show_card_border'];
		}
		if ( array_key_exists( 'layout_label_uppercase', $value ) ) {
			$config['layout_label_uppercase'] = (bool) $value['layout_label_uppercase'];
		}
		if ( array_key_exists( 'layout_label_bold', $value ) ) {
			$config['layout_label_bold'] = (bool) $value['layout_label_bold'];
		}
		if ( array_key_exists( 'layout_label_italic', $value ) ) {
			$config['layout_label_italic'] = (bool) $value['layout_label_italic'];
		}
		$color_fields = array(
			'layout_label_color',
			'layout_value_color',
			'layout_card_background_color',
			'footer_nap_text_color',
		);
		foreach ( $color_fields as $field ) {
			if ( ! isset( $config[ $field ] ) || ! is_string( $config[ $field ] ) ) {
				continue;
			}
			$color = trim( $config[ $field ] );
			if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
				$config[ $field ] = (string) self::default_config()[ $field ];
			} else {
				$config[ $field ] = strtolower( $color );
			}
		}

		if ( isset( $value['service_catalog_items'] ) && is_array( $value['service_catalog_items'] ) ) {
			$items = array();
			foreach ( $value['service_catalog_items'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$name = '';
				if ( isset( $item['name'] ) && is_string( $item['name'] ) ) {
					$name = mb_substr( sanitize_text_field( $item['name'] ), 0, 120 );
				}

				$description = '';
				if ( isset( $item['description'] ) && is_string( $item['description'] ) ) {
					$description = mb_substr( sanitize_text_field( $item['description'] ), 0, 240 );
				}

				if ( '' === $name && '' === $description ) {
					continue;
				}

				$items[] = array(
					'name'        => $name,
					'description' => $description,
				);

				if ( count( $items ) >= 50 ) {
					break;
				}
			}
			$config['service_catalog_items'] = $items;
		}

		$list_fields = array(
			'service_area_cities'       => 120,
			'service_area_postal_codes' => 20,
		);
		foreach ( $list_fields as $key => $limit ) {
			if ( ! isset( $value[ $key ] ) || ! is_array( $value[ $key ] ) ) {
				continue;
			}

			$items = array();
			foreach ( $value[ $key ] as $item ) {
				if ( ! is_string( $item ) ) {
					continue;
				}
				$clean = mb_substr( sanitize_text_field( $item ), 0, $limit );
				if ( '' === $clean ) {
					continue;
				}
				$items[] = $clean;
				if ( count( $items ) >= 100 ) {
					break;
				}
			}

			$config[ $key ] = array_values( array_unique( $items ) );
		}

		if ( isset( $value['same_as_urls'] ) && is_array( $value['same_as_urls'] ) ) {
			$urls = array();
			foreach ( $value['same_as_urls'] as $url ) {
				if ( ! is_string( $url ) ) {
					continue;
				}

				$clean_url = esc_url_raw( trim( $url ) );
				if ( '' === $clean_url ) {
					continue;
				}

				$urls[] = $clean_url;
				if ( count( $urls ) >= 20 ) {
					break;
				}
			}

			$config['same_as_urls'] = array_values( array_unique( $urls ) );
		}
		if ( isset( $value['layout_order'] ) && is_array( $value['layout_order'] ) ) {
			$config['layout_order'] = self::sanitize_layout_order( $value['layout_order'] );
		}
		if ( isset( $value['footer_nap_text_align'] ) && is_string( $value['footer_nap_text_align'] ) ) {
			$text_align = sanitize_key( $value['footer_nap_text_align'] );
			if ( in_array( $text_align, array( 'left', 'center', 'right' ), true ) ) {
				$config['footer_nap_text_align'] = $text_align;
			}
		}
		if ( isset( $value['footer_nap_layout_order'] ) && is_array( $value['footer_nap_layout_order'] ) ) {
			$config['footer_nap_layout_order'] = self::sanitize_footer_nap_layout_order( $value['footer_nap_layout_order'] );
		}
		$has_custom_layout_order = isset( $value['layout_order'] ) && is_array( $value['layout_order'] );
		if ( isset( $value['layout_grid'] ) && is_array( $value['layout_grid'] ) ) {
			$config['layout_grid']  = self::sanitize_layout_grid( $value['layout_grid'], $config['layout_order'], $has_custom_layout_order );
			$config['layout_order'] = array_map(
				static function ( array $item ): string {
					return (string) $item['block_id'];
				},
				$config['layout_grid']
			);
		} else {
			$config['layout_grid'] = self::sanitize_layout_grid( array(), $config['layout_order'], true );
		}
		if ( isset( $value['branches'] ) && is_array( $value['branches'] ) ) {
			$config['branches'] = self::sanitize_branches( $value['branches'] );
		}

		if ( isset( $value['vat_id'] ) && is_string( $value['vat_id'] ) ) {
			$config['vat_id'] = self::sanitize_vat_id( $value['vat_id'] );
		}
		if ( ! self::has_valid_map_coordinates( $config, $latitude_is_numeric, $longitude_is_numeric ) ) {
			$config['kml_in_sitemap']         = false;
			$config['contact_auto_map_embed'] = false;
		}

		return $config;
	}

	/**
	 * Sanitize branch override settings.
	 *
	 * @param array<int, mixed> $branches Raw branches.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_branches( array $branches ): array {
		$result     = array();
		$index      = 0;
		$used_slugs = array();

		foreach ( $branches as $branch ) {
			if ( ! is_array( $branch ) ) {
				continue;
			}

			++$index;
			$id = '';
			if ( isset( $branch['id'] ) && is_string( $branch['id'] ) ) {
				$id = sanitize_key( $branch['id'] );
			}
			if ( '' === $id ) {
				$id = 'branch-' . (string) $index;
			}

			$label = '';
			if ( isset( $branch['label'] ) && is_string( $branch['label'] ) ) {
				$label = mb_substr( sanitize_text_field( $branch['label'] ), 0, 120 );
			}
			if ( '' === $label ) {
				$label = 'Branch ' . (string) $index;
			}
			$slug = '';
			if ( isset( $branch['slug'] ) && is_string( $branch['slug'] ) ) {
				$slug = sanitize_title( $branch['slug'] );
			}
			if ( '' === $slug ) {
				$slug = sanitize_title( $label );
			}
			if ( '' === $slug ) {
				$slug = 'branch-' . (string) $index;
			}
			$slug_base = $slug;
			$slug_i    = 2;
			while ( in_array( $slug, $used_slugs, true ) ) {
				$slug = $slug_base . '-' . (string) $slug_i;
				++$slug_i;
			}
			$used_slugs[] = $slug;

			$latitude          = isset( $branch['latitude'] ) && is_numeric( $branch['latitude'] ) ? (float) $branch['latitude'] : 0.0;
			$longitude         = isset( $branch['longitude'] ) && is_numeric( $branch['longitude'] ) ? (float) $branch['longitude'] : 0.0;
			$valid_coordinates = $latitude >= -90.0 && $latitude <= 90.0
			&& $longitude >= -180.0 && $longitude <= 180.0
			&& 0.0 !== $latitude
			&& 0.0 !== $longitude;

			$price_level = '$$';
			if ( isset( $branch['price_range_level'] ) && is_string( $branch['price_range_level'] ) ) {
				$raw_price_level = trim( $branch['price_range_level'] );
				if ( in_array( $raw_price_level, array( '$', '$$', '$$$', '$$$$' ), true ) ) {
					$price_level = $raw_price_level;
				}
			}
			$service_area_cities = array();
			if ( isset( $branch['service_area_cities'] ) && is_array( $branch['service_area_cities'] ) ) {
				foreach ( $branch['service_area_cities'] as $city ) {
					if ( ! is_string( $city ) ) {
						continue;
					}
					$clean_city = mb_substr( sanitize_text_field( $city ), 0, 120 );
					if ( '' === $clean_city ) {
						continue;
					}
					$service_area_cities[] = $clean_city;
					if ( count( $service_area_cities ) >= 100 ) {
						break;
					}
				}
				$service_area_cities = array_values( array_unique( $service_area_cities ) );
			}
			$service_area_postal_codes = array();
			if ( isset( $branch['service_area_postal_codes'] ) && is_array( $branch['service_area_postal_codes'] ) ) {
				foreach ( $branch['service_area_postal_codes'] as $code ) {
					if ( ! is_string( $code ) ) {
						continue;
					}
					$clean_code = mb_substr( sanitize_text_field( $code ), 0, 20 );
					if ( '' === $clean_code ) {
						continue;
					}
					$service_area_postal_codes[] = $clean_code;
					if ( count( $service_area_postal_codes ) >= 100 ) {
						break;
					}
				}
				$service_area_postal_codes = array_values( array_unique( $service_area_postal_codes ) );
			}
			$service_area_radius_km = isset( $branch['service_area_radius_km'] ) && is_numeric( $branch['service_area_radius_km'] )
			? (float) $branch['service_area_radius_km']
			: 0.0;
			if ( $service_area_radius_km < 0 ) {
				$service_area_radius_km = 0.0;
			}
			if ( $service_area_radius_km > 1000 ) {
				$service_area_radius_km = 1000.0;
			}

			$result[] = array(
				'id'                        => $id,
				'label'                     => $label,
				'slug'                      => $slug,
				'enabled'                   => ! empty( $branch['enabled'] ),
				'business_name'             => isset( $branch['business_name'] ) && is_string( $branch['business_name'] )
					? mb_substr( sanitize_text_field( $branch['business_name'] ), 0, 160 )
					: '',
				'phone'                     => isset( $branch['phone'] ) && is_string( $branch['phone'] )
					? mb_substr( sanitize_text_field( $branch['phone'] ), 0, 80 )
					: '',
				'image_url'                 => isset( $branch['image_url'] ) && is_string( $branch['image_url'] )
					? esc_url_raw( trim( $branch['image_url'] ) )
					: '',
				'street_address'            => isset( $branch['street_address'] ) && is_string( $branch['street_address'] )
					? mb_substr( sanitize_text_field( $branch['street_address'] ), 0, 200 )
					: '',
				'city'                      => isset( $branch['city'] ) && is_string( $branch['city'] )
					? mb_substr( sanitize_text_field( $branch['city'] ), 0, 120 )
					: '',
				'region'                    => isset( $branch['region'] ) && is_string( $branch['region'] )
					? mb_substr( sanitize_text_field( $branch['region'] ), 0, 120 )
					: '',
				'postal_code'               => isset( $branch['postal_code'] ) && is_string( $branch['postal_code'] )
					? mb_substr( sanitize_text_field( $branch['postal_code'] ), 0, 40 )
					: '',
				'country'                   => isset( $branch['country'] ) && is_string( $branch['country'] )
					? self::sanitize_country_code( $branch['country'] )
					: '',
				'latitude'                  => $latitude,
				'longitude'                 => $longitude,
				'opening_hours'             => isset( $branch['opening_hours'] ) && is_string( $branch['opening_hours'] )
					? mb_substr( trim( wp_strip_all_tags( $branch['opening_hours'] ) ), 0, 1000 )
					: '',
				'special_hours'             => isset( $branch['special_hours'] ) && is_string( $branch['special_hours'] )
					? mb_substr( trim( wp_strip_all_tags( $branch['special_hours'] ) ), 0, 2000 )
					: '',
				'service_area_cities'       => $service_area_cities,
				'service_area_postal_codes' => $service_area_postal_codes,
				'service_area_radius_km'    => $service_area_radius_km,
				'price_range_level'         => $price_level,
				'price_range_custom'        => isset( $branch['price_range_custom'] ) && is_string( $branch['price_range_custom'] )
					? mb_substr( sanitize_text_field( $branch['price_range_custom'] ), 0, 60 )
					: '',
				'contact_auto_map_embed'    => ! empty( $branch['contact_auto_map_embed'] ) && $valid_coordinates,
				'kml_in_sitemap'            => ! empty( $branch['kml_in_sitemap'] ) && $valid_coordinates,
				'geo_region_code'           => isset( $branch['geo_region_code'] ) && is_string( $branch['geo_region_code'] )
					? mb_substr( sanitize_text_field( $branch['geo_region_code'] ), 0, 30 )
					: '',
				'geo_placename'             => isset( $branch['geo_placename'] ) && is_string( $branch['geo_placename'] )
					? mb_substr( sanitize_text_field( $branch['geo_placename'] ), 0, 120 )
					: '',
			);

			if ( count( $result ) >= 30 ) {
				break;
			}
		}

		return $result;
	}

	/**
	 * Sanitize layout order for unified Local SEO shortcode blocks.
	 *
	 * @param array<int, mixed> $items Raw layout items.
	 * @return array<int, string>
	 */
	private static function sanitize_layout_order( array $items ): array {
		$allowed = self::layout_allowed_blocks();
		$result  = array();

		foreach ( $items as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			$key = sanitize_key( $item );
			if ( ! in_array( $key, $allowed, true ) || in_array( $key, $result, true ) ) {
				continue;
			}
			$result[] = $key;
		}

		return $result;
	}

	/**
	 * Sanitize footer NAP layout order.
	 *
	 * @param array<int, mixed> $items Raw order list.
	 * @return array<int, string>
	 */
	private static function sanitize_footer_nap_layout_order( array $items ): array {
		$allowed = array(
			'business_name',
			'legal_name',
			'phone',
			'address',
			'tax_id',
		);
		$result  = array();
		foreach ( $items as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}

			$key = sanitize_key( $item );
			if ( ! in_array( $key, $allowed, true ) || in_array( $key, $result, true ) ) {
				continue;
			}

			$result[] = $key;
		}

		if ( empty( $result ) ) {
			return array(
				'business_name',
				'phone',
				'address',
			);
		}

		return $result;
	}

	/**
	 * Sanitize 5x15 layout grid with block width/height span.
	 *
	 * @param array<int, mixed>  $items Raw layout grid items.
	 * @param array<int, string> $layout_order Layout order fallback.
	 * @return array<int, array<string, int|string>>
	 */
	private static function sanitize_layout_grid( array $items, array $layout_order, bool $fill_missing_blocks ): array {
		$allowed  = self::layout_allowed_blocks();
		$result   = array();
		$used_ids = array();
		$occupied = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$raw_block_id = '';
			if ( isset( $item['block_id'] ) && is_string( $item['block_id'] ) ) {
				$raw_block_id = $item['block_id'];
			} elseif ( isset( $item['blockId'] ) && is_string( $item['blockId'] ) ) {
				$raw_block_id = $item['blockId'];
			}
			$block_id = sanitize_key( $raw_block_id );

			if ( '' === $block_id || ! in_array( $block_id, $allowed, true ) || in_array( $block_id, $used_ids, true ) ) {
				continue;
			}
			if ( ! isset( $item['row'] ) || ! isset( $item['col'] ) || ! isset( $item['span'] ) ) {
				continue;
			}
			if ( ! is_numeric( $item['row'] ) || ! is_numeric( $item['col'] ) || ! is_numeric( $item['span'] ) ) {
				continue;
			}

			$row      = (int) $item['row'];
			$col      = (int) $item['col'];
			$span     = (int) $item['span'];
			$row_span = 1;
			if ( isset( $item['row_span'] ) && is_numeric( $item['row_span'] ) ) {
				$row_span = (int) $item['row_span'];
			} elseif ( isset( $item['rowSpan'] ) && is_numeric( $item['rowSpan'] ) ) {
				$row_span = (int) $item['rowSpan'];
			}

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
					$key              = (string) ( $row + $row_offset ) . '-' . (string) ( $col + $col_offset );
					$occupied[ $key ] = true;
				}
			}

			$result[]   = array(
				'block_id' => $block_id,
				'row'      => $row,
				'col'      => $col,
				'span'     => $span,
				'row_span' => $row_span,
			);
			$used_ids[] = $block_id;
		}

		if ( $fill_missing_blocks || empty( $items ) ) {
			$fallback_order = array();
			foreach ( $layout_order as $block_id ) {
				if ( ! in_array( $block_id, $allowed, true ) || in_array( $block_id, $used_ids, true ) ) {
					continue;
				}
				$fallback_order[] = $block_id;
			}
			foreach ( $fallback_order as $block_id ) {
				$placed = false;
				for ( $row = 1; $row <= 15; $row++ ) {
					for ( $col = 1; $col <= 5; $col++ ) {
						$key = $row . '-' . $col;
						if ( isset( $occupied[ $key ] ) ) {
							continue;
						}
						$occupied[ $key ] = true;
						$result[]         = array(
							'block_id' => $block_id,
							'row'      => $row,
							'col'      => $col,
							'span'     => 1,
							'row_span' => 1,
						);
						$placed           = true;
						break;
					}
					if ( $placed ) {
						break;
					}
				}
			}
		}

		usort(
			$result,
			static function ( array $left, array $right ): int {
				$left_row  = (int) $left['row'];
				$right_row = (int) $right['row'];
				if ( $left_row === $right_row ) {
					return (int) $left['col'] <=> (int) $right['col'];
				}
				return $left_row <=> $right_row;
			}
		);

		return $result;
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
	 * Determine whether map-related features can be enabled from coordinate input.
	 *
	 * @param array<string, mixed> $config Sanitized settings.
	 * @param bool                 $latitude_is_numeric Whether latitude input was numeric.
	 * @param bool                 $longitude_is_numeric Whether longitude input was numeric.
	 * @return bool
	 */
	private static function has_valid_map_coordinates(
		array $config,
		bool $latitude_is_numeric,
		bool $longitude_is_numeric
	): bool {
		if ( ! $latitude_is_numeric || ! $longitude_is_numeric ) {
			return false;
		}

		$lat = isset( $config['latitude'] ) ? (float) $config['latitude'] : 0.0;
		$lng = isset( $config['longitude'] ) ? (float) $config['longitude'] : 0.0;

		if ( $lat < -90.0 || $lat > 90.0 ) {
			return false;
		}
		if ( $lng < -180.0 || $lng > 180.0 ) {
			return false;
		}
		if ( 0.0 === $lat || 0.0 === $lng ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitize Taiwan VAT ID.
	 *
	 * @param string $value Raw VAT ID value.
	 * @return string
	 */
	private static function sanitize_vat_id( string $value ): string {
		$vat_id = mb_substr( trim( sanitize_text_field( $value ) ), 0, 32 );
		if ( '' === $vat_id ) {
			return '';
		}

		return $vat_id;
	}

	/**
	 * Sanitize ISO-3166 Alpha-2 country code.
	 *
	 * @param string $value Raw country value.
	 * @return string
	 */
	private static function sanitize_country_code( string $value ): string {
		$code = strtoupper( trim( sanitize_text_field( $value ) ) );
		$code = preg_replace( '/[^A-Z]/', '', $code );
		if ( ! is_string( $code ) || ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
			return '';
		}

		return $code;
	}

	/**
	 * Allowed LocalBusiness schema types.
	 *
	 * @return array<int, string>
	 */
	public static function business_types(): array {
		return array(
			'LocalBusiness',
			'Organization',
			'Store',
			'ProfessionalService',
			'HomeAndConstructionBusiness',
			'Electrician',
			'Plumber',
			'GeneralContractor',
			'RoofingContractor',
			'HVACBusiness',
			'Locksmith',
			'HousePainter',
			'Carpenter',
			'AutoRepair',
			'HealthAndBeautyBusiness',
			'BeautySalon',
			'NailSalon',
			'Barbershop',
			'MedicalBusiness',
			'Restaurant',
			'FastFoodRestaurant',
			'CafeOrCoffeeShop',
			'Bakery',
			'Dentist',
			'MedicalClinic',
			'Physician',
			'Pediatric',
			'CommunityHealth',
			'Hospital',
			'Pharmacy',
			'LegalService',
			'RealEstateAgent',
		);
	}

	/**
	 * Get default configuration.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_config(): array {
		return array(
			'enabled'                        => false,
			'layout_template'                => 'sidebar_left',
			'layout_show_card_border'        => true,
			'layout_card_padding'            => 16,
			'layout_label_font_size'         => 12,
			'layout_label_color'             => '#64748b',
			'layout_label_uppercase'         => true,
			'layout_label_bold'              => true,
			'layout_label_italic'            => false,
			'layout_value_font_size'         => 14,
			'layout_value_color'             => '#334155',
			'layout_title_font_size'         => 26,
			'layout_card_background_color'   => '#ffffff',
			'business_type'                  => 'LocalBusiness',
			'business_name'                  => '',
			'image_url'                      => '',
			'phone'                          => '',
			'price_range'                    => '$$',
			'street_address'                 => '',
			'city'                           => '',
			'region'                         => '',
			'postal_code'                    => '',
			'country'                        => '',
			'latitude'                       => 0.0,
			'longitude'                      => 0.0,
			'opening_hours'                  => '',
			'enable_geo_tags'                => false,
			'geo_region_code'                => '',
			'geo_placename'                  => '',
			'map_zoom'                       => 15,
			'service_catalog_name'           => '',
			'service_catalog_items'          => array(),
			'layout_order'                   => array(
				'image_url',
				'map',
				'logo_url',
				'business_name',
				'service_catalog',
				'pricing',
				'address',
				'opening_hours',
				'service_areas',
				'special_hours',
				'legal_name',
				'vat_id',
				'phone',
			),
			'layout_grid'                    => array(
				array(
					'block_id' => 'image_url',
					'row'      => 1,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'map',
					'row'      => 2,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'logo_url',
					'row'      => 3,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'business_name',
					'row'      => 4,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'service_catalog',
					'row'      => 5,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'pricing',
					'row'      => 6,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'address',
					'row'      => 7,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'opening_hours',
					'row'      => 8,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'service_areas',
					'row'      => 9,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'special_hours',
					'row'      => 10,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'legal_name',
					'row'      => 11,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'vat_id',
					'row'      => 12,
					'col'      => 1,
					'span'     => 5,
					'row_span' => 1,
				),
				array(
					'block_id' => 'phone',
					'row'      => 13,
					'col'      => 1,
					'span'     => 1,
					'row_span' => 1,
				),
			),
			'footer_nap_layout_order'        => array(
				'business_name',
				'phone',
				'address',
			),
			'footer_nap_enabled'             => false,
			'footer_nap_font_size'           => 12,
			'footer_nap_text_color'          => '#334155',
			'footer_nap_text_align'          => 'center',
			'footer_nap_first_item_bold'     => true,
			'footer_nap_margin_y'            => 10,
			'footer_nap_gap'                 => 4,
			'footer_nap_container_width'     => 960,
			'contact_auto_map_embed'         => false,
			'kml_in_sitemap'                 => false,
			'contact_detailed_opening_hours' => false,
			'service_area_cities'            => array(),
			'service_area_postal_codes'      => array(),
			'service_area_radius_km'         => 0.0,
			'rating_value'                   => 0.0,
			'review_count'                   => 0,
			'same_as_urls'                   => array(),
			'logo_url'                       => '',
			'price_range_level'              => '$$',
			'price_range_custom'             => '',
			'vat_id'                         => '',
			'legal_name'                     => '',
			'show_vat_in_footer'             => false,
			'click_to_call_enabled'          => false,
			'special_hours'                  => '',
			'vat_validate_checksum'          => false,
			'branches'                       => array(),
		);
	}
}
