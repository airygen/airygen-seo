<?php
/**
 * Stores Score Calculator rule overrides.
 *
 * @package Airygen\Modules\ScoreCalculator\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\ScoreCalculator\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists customized scoring weights.
 */
final class Settings {

	private const OPTION = Constants::OPTION_SCORE_CALCULATOR;

	public const MIN_WEIGHT = 0;
	public const MAX_WEIGHT = 20;

	/**
	 * Ensure the option exists.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', 'no' );
			return;
		}

		self::update( (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Retrieve sanitized settings.
	 *
	 * @return array{
	 *   rules: array<string, float>,
	 *   postTypes: array<int, string>,
	 *   customRules: array<string, array<string, float|int>>
	 * }
	 */
	public static function get(): array {
		$value = get_option( self::OPTION, array() );

		return self::sanitize( $value );
	}

	/**
	 * Update stored settings.
	 *
	 * @param array<string, mixed> $value New value.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		$sanitized = self::sanitize( $value );
		update_option( self::OPTION, $sanitized, 'no' );
	}

	/**
	 * Apply overrides to the provided ruleset.
	 *
	 * @param array<string, mixed> $spec Rules specification.
	 *
	 * @return array<string, mixed>
	 */
	public static function apply_overrides( array $spec ): array {
		$settings     = self::get();
		$overrides    = $settings['rules'] ?? array();
		$custom_rules = isset( $settings['customRules'] ) && is_array( $settings['customRules'] )
		? $settings['customRules']
		: array();

		if ( empty( $overrides ) && empty( $custom_rules ) ) {
			return $spec;
		}

		if ( isset( $spec['rules'] ) && is_array( $spec['rules'] ) ) {
			$spec['rules'] = self::apply_to_group( $spec['rules'], $overrides, $custom_rules );
		}

		if ( isset( $spec['bonus'] ) && is_array( $spec['bonus'] ) ) {
			$spec['bonus'] = self::apply_to_group( $spec['bonus'], $overrides, $custom_rules );
		}

		return $spec;
	}

	/**
	 * Build meta information for the admin UI.
	 *
	 * @return array<string, mixed>
	 */
	public static function rules_meta(): array {
		$spec               = RulesProvider::get();
		$effective_spec     = self::apply_overrides( $spec );
		$rules              = array();
		$default_weight_map = array();

		$collect_default_weights = static function ( array $items ) use ( &$default_weight_map ): void {
			foreach ( $items as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['id'] ) || ! isset( $rule['weight'] ) ) {
					continue;
				}
				$default_weight_map[ (string) $rule['id'] ] = (float) $rule['weight'];
			}
		};

		$collect_default_weights( $spec['rules'] ?? array() );
		$collect_default_weights( $spec['bonus'] ?? array() );

		$append = static function ( array $items, string $group ) use ( &$rules, $default_weight_map ): void {
			foreach ( $items as $rule ) {
				if ( empty( $rule['id'] ) || ! isset( $rule['weight'] ) ) {
					continue;
				}
				$rule_id        = (string) $rule['id'];
				$default_weight = isset( $default_weight_map[ $rule_id ] )
				? (float) $default_weight_map[ $rule_id ]
				: (float) $rule['weight'];

				$rules[] = array(
					'id'            => $rule_id,
					'label'         => (string) ( $rule['label'] ?? $rule_id ),
					'defaultWeight' => $default_weight,
					'group'         => $group,
					'requiresFocus' => ! empty( $rule['requires_focus'] ),
				);
			}
		};

		$append( $effective_spec['rules'] ?? array(), 'base' );
		$append( $effective_spec['bonus'] ?? array(), 'bonus' );

		$custom_rules_meta = self::build_custom_rules_meta( $spec, $effective_spec );

		return array(
			'rules'       => $rules,
			'customRules' => $custom_rules_meta,
			'minWeight'   => self::MIN_WEIGHT,
			'maxWeight'   => self::MAX_WEIGHT,
		);
	}

	/**
	 * Apply overrides to a group of rules.
	 *
	 * @param array<int, array<string, mixed>>             $group        Rules group.
	 * @param array<string, float>                         $overrides    Map of weight overrides.
	 * @param array<string, array<string, float|int>>      $custom_rules Map of custom rule values.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function apply_to_group( array $group, array $overrides, array $custom_rules ): array {
		foreach ( $group as &$rule ) {
			$identifier = isset( $rule['id'] ) && is_string( $rule['id'] ) ? $rule['id'] : null;
			if ( null === $identifier ) {
				continue;
			}

			if ( array_key_exists( $identifier, $overrides ) ) {
				$rule['weight'] = (float) $overrides[ $identifier ];
			}

			if ( array_key_exists( $identifier, $custom_rules ) && is_array( $custom_rules[ $identifier ] ) ) {
				$rule = self::apply_custom_rule_fields( $rule, $identifier, $custom_rules[ $identifier ] );
			}
		}

		return $group;
	}

	/**
	 * Sanitize the stored option shape.
	 *
	 * @param mixed $value Raw database value.
	 *
	 * @return array{
	 *   rules: array<string, float>,
	 *   postTypes: array<int, string>,
	 *   customRules: array<string, array<string, float|int>>
	 * }
	 */
	private static function sanitize( $value ): array {
		$defaults  = self::defaults();
		$sanitized = array(
			'rules'       => array(),
			'postTypes'   => $defaults['postTypes'],
			'customRules' => array(),
		);

		if ( ! is_array( $value ) ) {
			return $sanitized;
		}

		$post_types_value         = $value['postTypes'] ?? ( $value['post_types'] ?? $defaults['postTypes'] );
		$custom_rules_value       = $value['customRules'] ?? ( $value['custom_rules'] ?? array() );
		$sanitized['postTypes']   = self::sanitize_post_types( $post_types_value );
		$sanitized['customRules'] = self::sanitize_custom_rules( $custom_rules_value );

		if ( empty( $value['rules'] ) || ! is_array( $value['rules'] ) ) {
			return $sanitized;
		}

		foreach ( $value['rules'] as $id => $weight ) {
			if ( ! is_string( $id ) ) {
				continue;
			}

			$normalized_id = trim( $id );
			if ( '' === $normalized_id ) {
				continue;
			}

			$numeric = is_numeric( $weight ) ? (float) $weight : null;
			if ( null === $numeric ) {
				continue;
			}

			$sanitized['rules'][ $normalized_id ] = self::clamp_weight( $numeric );
		}

		return $sanitized;
	}

	/**
	 * Default option payload.
	 *
	 * @return array{
	 *   rules: array<string, float>,
	 *   postTypes: array<int, string>,
	 *   customRules: array<string, array<string, float|int>>
	 * }
	 */
	private static function defaults(): array {
		return array(
			'rules'       => array(),
			'postTypes'   => self::default_post_types(),
			'customRules' => array(),
		);
	}

	/**
	 * Resolve default post types for score support.
	 *
	 * @return array<int, string>
	 */
	private static function default_post_types(): array {
		$post_types = get_post_types(
			array(
				'show_ui' => true,
			),
			'names'
		);
		$post_types = array_diff(
			$post_types,
			array(
				'attachment',
				'revision',
				'nav_menu_item',
				'wp_block',
				'wp_navigation',
			)
		);
		$post_types = array_map( 'strval', $post_types );
		$post_types = array_values( array_unique( $post_types ) );

		if ( empty( $post_types ) ) {
			return array( 'post' );
		}

		return $post_types;
	}

	/**
	 * Sanitize scoped post types.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<int, string>
	 */
	private static function sanitize_post_types( $value ): array {
		$candidates = is_array( $value ) ? $value : array();
		$allowed    = self::default_post_types();
		$normalized = array();

		foreach ( $candidates as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$slug = sanitize_key( (string) $item );
			if ( '' === $slug || ! in_array( $slug, $allowed, true ) ) {
				continue;
			}
			$normalized[] = $slug;
		}

		$normalized = array_values( array_unique( $normalized ) );

		if ( empty( $normalized ) ) {
			return array( 'post' );
		}

		return $normalized;
	}

	/**
	 * Build editable rule metadata for the admin Custom tab.
	 *
	 * @param array<string, mixed> $default_spec  Base rules from config/score_rules.php.
	 * @param array<string, mixed> $effective_spec Rules after applying saved overrides.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_custom_rules_meta( array $default_spec, array $effective_spec ): array {
		$definitions = self::editable_rule_definitions();
		$output      = array();

		foreach ( $definitions as $rule_id => $definition ) {
			$default_rule = self::find_rule_by_id( $default_spec, $rule_id );
			$current_rule = self::find_rule_by_id( $effective_spec, $rule_id );

			if ( ! is_array( $default_rule ) || ! is_array( $current_rule ) ) {
				continue;
			}

			$default_params = isset( $default_rule['params'] ) && is_array( $default_rule['params'] ) ? $default_rule['params'] : array();
			$current_params = isset( $current_rule['params'] ) && is_array( $current_rule['params'] ) ? $current_rule['params'] : array();
			$field_rows     = array();

			$fields = isset( $definition['fields'] ) && is_array( $definition['fields'] )
			? $definition['fields']
			: array();

			foreach ( $fields as $field_key => $field_spec ) {
				if ( ! is_string( $field_key ) || ! is_array( $field_spec ) ) {
					continue;
				}

				$default_value = self::resolve_custom_field_value( $rule_id, $field_key, $default_params );
				$current_value = self::resolve_custom_field_value( $rule_id, $field_key, $current_params );

				$field_rows[] = array(
					'key'          => $field_key,
					'label'        => (string) ( $field_spec['label'] ?? $field_key ),
					'help'         => (string) ( $field_spec['help'] ?? '' ),
					'min'          => isset( $field_spec['min'] ) && is_numeric( $field_spec['min'] ) ? (float) $field_spec['min'] : null,
					'max'          => isset( $field_spec['max'] ) && is_numeric( $field_spec['max'] ) ? (float) $field_spec['max'] : null,
					'step'         => isset( $field_spec['step'] ) && is_numeric( $field_spec['step'] ) ? (float) $field_spec['step'] : null,
					'defaultValue' => $default_value,
					'value'        => $current_value,
				);
			}

			if ( empty( $field_rows ) ) {
				continue;
			}

			$definition_label = isset( $definition['title'] ) && is_string( $definition['title'] )
			? $definition['title']
			: '';

			$output[] = array(
				'id'     => $rule_id,
				'label'  => '' !== $definition_label ? $definition_label : (string) ( $default_rule['label'] ?? $rule_id ),
				'fields' => $field_rows,
			);
		}

		return $output;
	}

	/**
	 * Resolve a single rule by ID from a score spec.
	 *
	 * @param array<string, mixed> $spec    Score spec.
	 * @param string               $rule_id Rule ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function find_rule_by_id( array $spec, string $rule_id ): ?array {
		$groups = array( 'rules', 'bonus' );
		foreach ( $groups as $group ) {
			$items = isset( $spec[ $group ] ) && is_array( $spec[ $group ] ) ? $spec[ $group ] : array();
			foreach ( $items as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				$candidate_id = isset( $rule['id'] ) ? (string) $rule['id'] : '';
				if ( $candidate_id === $rule_id ) {
					return $rule;
				}
			}
		}

		return null;
	}

	/**
	 * Sanitize custom rule overrides.
	 *
	 * @param mixed $value Raw custom rules value.
	 *
	 * @return array<string, array<string, float|int>>
	 */
	private static function sanitize_custom_rules( $value ): array {
		$definitions = self::editable_rule_definitions();
		if ( ! is_array( $value ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $value as $rule_id => $rule_values ) {
			if ( ! is_string( $rule_id ) || ! isset( $definitions[ $rule_id ] ) || ! is_array( $rule_values ) ) {
				continue;
			}

			$field_specs       = isset( $definitions[ $rule_id ]['fields'] ) && is_array( $definitions[ $rule_id ]['fields'] )
			? $definitions[ $rule_id ]['fields']
			: array();
			$normalized_fields = array();

			foreach ( $rule_values as $field_key => $raw_value ) {
				if ( ! is_string( $field_key ) || ! isset( $field_specs[ $field_key ] ) ) {
					continue;
				}

				$field_spec = is_array( $field_specs[ $field_key ] ) ? $field_specs[ $field_key ] : array();
				$numeric    = is_numeric( $raw_value ) ? (float) $raw_value : null;
				if ( null === $numeric ) {
					continue;
				}

				$min = isset( $field_spec['min'] ) && is_numeric( $field_spec['min'] ) ? (float) $field_spec['min'] : null;
				$max = isset( $field_spec['max'] ) && is_numeric( $field_spec['max'] ) ? (float) $field_spec['max'] : null;
				if ( null !== $min && $numeric < $min ) {
					$numeric = $min;
				}
				if ( null !== $max && $numeric > $max ) {
					$numeric = $max;
				}

				$normalized_fields[ $field_key ] = ( isset( $field_spec['step'] ) && 1.0 === (float) $field_spec['step'] )
				? (int) round( $numeric )
				: $numeric;
			}

			if ( ! empty( $normalized_fields ) ) {
				$normalized[ $rule_id ] = $normalized_fields;
			}
		}

		return $normalized;
	}

	/**
	 * Apply custom parameter overrides to one rule.
	 *
	 * @param array<string, mixed>               $rule         Rule object.
	 * @param string                             $rule_id      Rule ID.
	 * @param array<string, float|int>           $custom_fields Saved custom values.
	 *
	 * @return array<string, mixed>
	 */
	private static function apply_custom_rule_fields( array $rule, string $rule_id, array $custom_fields ): array {
		$params = isset( $rule['params'] ) && is_array( $rule['params'] ) ? $rule['params'] : array();

		switch ( $rule_id ) {
			case 'title_length_px':
				$params['px_min']    = isset( $custom_fields['px_min'] ) ? (float) $custom_fields['px_min'] : ( $params['px_min'] ?? 350 );
				$params['px_max']    = isset( $custom_fields['px_max'] ) ? (float) $custom_fields['px_max'] : ( $params['px_max'] ?? 580 );
				$params['min_chars'] = isset( $custom_fields['min_chars'] ) ? (int) $custom_fields['min_chars'] : ( $params['min_chars'] ?? 15 );
				break;
			case 'meta_description_length':
				$params['min'] = isset( $custom_fields['min'] ) ? (float) $custom_fields['min'] : ( $params['min'] ?? 640 );
				$params['max'] = isset( $custom_fields['max'] ) ? (float) $custom_fields['max'] : ( $params['max'] ?? 920 );
				break;
			case 'subheads_count':
				$params['min'] = isset( $custom_fields['min'] ) ? (float) $custom_fields['min'] : ( $params['min'] ?? 2 );
				$params['max'] = isset( $custom_fields['max'] ) ? (float) $custom_fields['max'] : ( $params['max'] ?? 8 );
				break;
			case 'keyword_density':
				$params['ideal_min'] = isset( $custom_fields['ideal_min'] ) ? (float) $custom_fields['ideal_min'] : ( $params['ideal_min'] ?? 0.5 );
				$params['ideal_max'] = isset( $custom_fields['ideal_max'] ) ? (float) $custom_fields['ideal_max'] : ( $params['ideal_max'] ?? 2.0 );
				$params['soft_min']  = isset( $custom_fields['soft_min'] ) ? (float) $custom_fields['soft_min'] : ( $params['soft_min'] ?? 0.3 );
				$params['soft_max']  = isset( $custom_fields['soft_max'] ) ? (float) $custom_fields['soft_max'] : ( $params['soft_max'] ?? 2.5 );
				break;
			case 'long_tail_density':
				$params['min']     = isset( $custom_fields['min'] ) ? (float) $custom_fields['min'] : ( $params['min'] ?? 0.1 );
				$params['max']     = isset( $custom_fields['max'] ) ? (float) $custom_fields['max'] : ( $params['max'] ?? 0.5 );
				$params['sum_max'] = isset( $custom_fields['sum_max'] ) ? (float) $custom_fields['sum_max'] : ( $params['sum_max'] ?? 2.0 );
				break;
			case 'word_count':
				$params['min'] = isset( $custom_fields['min'] ) ? (float) $custom_fields['min'] : ( $params['min'] ?? 300 );
				break;
			case 'readability_flesch':
				$params['min'] = isset( $custom_fields['min'] ) ? (float) $custom_fields['min'] : ( $params['min'] ?? 60 );
				break;
			case 'readability_cjk_sentences':
				$params['max']           = isset( $custom_fields['max'] ) ? (float) $custom_fields['max'] : ( $params['max'] ?? 60 );
				$params['min_sentences'] = isset( $custom_fields['min_sentences'] ) ? (int) $custom_fields['min_sentences'] : ( $params['min_sentences'] ?? 3 );
				break;
			case 'long_tail_spacing':
				$params['min_distance'] = isset( $custom_fields['min_distance'] ) ? (int) $custom_fields['min_distance'] : ( $params['min_distance'] ?? 50 );
				break;
			case 'internal_links':
				$primary_min = isset( $custom_fields['min'] ) ? (int) $custom_fields['min'] : 1;
				$primary_max = isset( $custom_fields['max'] ) ? (int) $custom_fields['max'] : 3;
				$warn_min    = isset( $custom_fields['warn_min'] ) ? (int) $custom_fields['warn_min'] : ( $primary_max + 1 );
				if ( $warn_min <= $primary_max ) {
					$warn_min = $primary_max + 1;
				}
				$params['brackets'] = array(
					array(
						'min'   => $primary_min,
						'max'   => $primary_max,
						'score' => 1.0,
					),
					array(
						'min'   => $warn_min,
						'max'   => 999,
						'score' => 0.67,
					),
				);
				$params['else']     = 0;
				break;
			case 'external_links':
				$params['min'] = isset( $custom_fields['min'] ) ? (float) $custom_fields['min'] : ( $params['min'] ?? 1 );
				break;
		}

		$rule['params'] = $params;
		$rule['label']  = self::build_dynamic_label( $rule_id, $params, (string) ( $rule['label'] ?? '' ) );

		return $rule;
	}

	/**
	 * Build dynamic label text from effective rule parameters.
	 *
	 * @param string               $rule_id Rule ID.
	 * @param array<string, mixed> $params  Effective params.
	 * @param string               $fallback Fallback label.
	 *
	 * @return string
	 */
	private static function build_dynamic_label( string $rule_id, array $params, string $fallback ): string {
		switch ( $rule_id ) {
			case 'meta_description_length':
				$min = isset( $params['min'] ) ? (int) round( (float) $params['min'] ) : 0;
				$max = isset( $params['max'] ) ? (int) round( (float) $params['max'] ) : 0;
				return sprintf(
					/* translators: 1: minimum px width, 2: maximum px width. */
					__( 'Meta description width %1$d-%2$dpx', 'airygen-seo' ),
					$min,
					$max
				);
			case 'subheads_count':
				$min = isset( $params['min'] ) ? (int) round( (float) $params['min'] ) : 0;
				$max = isset( $params['max'] ) ? (int) round( (float) $params['max'] ) : 0;
				return sprintf(
					/* translators: 1: minimum heading count, 2: maximum heading count. */
					__( 'H2/H3 count between %1$d and %2$d', 'airygen-seo' ),
					$min,
					$max
				);
			case 'keyword_density':
				return sprintf(
					/* translators: 1: ideal minimum density, 2: ideal maximum density. */
					__( 'Focus keyphrase density %1$s%%-%2$s%%', 'airygen-seo' ),
					self::format_number( $params['ideal_min'] ?? 0 ),
					self::format_number( $params['ideal_max'] ?? 0 )
				);
			case 'long_tail_density':
				return sprintf(
					/* translators: 1: per phrase minimum density, 2: per phrase maximum density, 3: total maximum density. */
					__( 'Long-tail keyphrase density %1$s%%-%2$s%% each, total <= %3$s%%', 'airygen-seo' ),
					self::format_number( $params['min'] ?? 0 ),
					self::format_number( $params['max'] ?? 0 ),
					self::format_number( $params['sum_max'] ?? 0 )
				);
			case 'word_count':
				return sprintf(
					/* translators: %d is the minimum word count. */
					__( 'Word count at least %d', 'airygen-seo' ),
					(int) round( (float) ( $params['min'] ?? 0 ) )
				);
			case 'readability_flesch':
				return sprintf(
					/* translators: %d is the minimum Flesch score. */
					__( 'Flesch reading ease >= %d (non-CJK)', 'airygen-seo' ),
					(int) round( (float) ( $params['min'] ?? 0 ) )
				);
			case 'readability_cjk_sentences':
				return sprintf(
					/* translators: %d is the maximum CJK sentence length. */
					__( 'Average sentence length <= %d (CJK)', 'airygen-seo' ),
					(int) round( (float) ( $params['max'] ?? 0 ) )
				);
			case 'long_tail_spacing':
				return sprintf(
					/* translators: %d is the minimum spacing distance. */
					__( 'Long-tail keyphrases spaced %d+ words/chars from other keyphrases', 'airygen-seo' ),
					(int) round( (float) ( $params['min_distance'] ?? 0 ) )
				);
			case 'internal_links':
				$brackets = isset( $params['brackets'] ) && is_array( $params['brackets'] ) ? $params['brackets'] : array();
				$min      = isset( $brackets[0]['min'] ) ? (int) $brackets[0]['min'] : 0;
				$max      = isset( $brackets[0]['max'] ) ? (int) $brackets[0]['max'] : 0;
				return sprintf(
					/* translators: 1: minimum internal links, 2: maximum internal links. */
					__( 'Internal links between %1$d and %2$d', 'airygen-seo' ),
					$min,
					$max
				);
			case 'external_links':
				return sprintf(
					/* translators: %d is the minimum external links count. */
					__( 'External links at least %d', 'airygen-seo' ),
					(int) round( (float) ( $params['min'] ?? 0 ) )
				);
			default:
				return $fallback;
		}
	}

	/**
	 * Format a numeric value for rule labels.
	 *
	 * @param mixed $value Raw numeric value.
	 *
	 * @return string
	 */
	private static function format_number( $value ): string {
		$number = is_numeric( $value ) ? (float) $value : 0.0;
		$text   = rtrim( rtrim( sprintf( '%.2f', $number ), '0' ), '.' );
		return '' !== $text ? $text : '0';
	}

	/**
	 * Resolve one custom field value from a rule params array.
	 *
	 * @param string               $rule_id   Rule ID.
	 * @param string               $field_key Field key.
	 * @param array<string, mixed> $params    Rule params.
	 *
	 * @return float|int
	 */
	private static function resolve_custom_field_value( string $rule_id, string $field_key, array $params ) {
		if ( 'internal_links' === $rule_id ) {
			$brackets = isset( $params['brackets'] ) && is_array( $params['brackets'] ) ? $params['brackets'] : array();
			$primary  = isset( $brackets[0] ) && is_array( $brackets[0] ) ? $brackets[0] : array();
			$warn     = isset( $brackets[1] ) && is_array( $brackets[1] ) ? $brackets[1] : array();
			if ( 'min' === $field_key ) {
				return isset( $primary['min'] ) ? (int) $primary['min'] : 1;
			}
			if ( 'max' === $field_key ) {
				return isset( $primary['max'] ) ? (int) $primary['max'] : 3;
			}
			if ( 'warn_min' === $field_key ) {
				return isset( $warn['min'] ) ? (int) $warn['min'] : 4;
			}
		}

		if ( 'title_length_px' === $rule_id && 'min_chars' === $field_key ) {
			return isset( $params['min_chars'] ) ? (int) $params['min_chars'] : 15;
		}

		if ( 'readability_cjk_sentences' === $rule_id && 'min_sentences' === $field_key ) {
			return isset( $params['min_sentences'] ) ? (int) $params['min_sentences'] : 3;
		}

		if ( 'long_tail_spacing' === $rule_id && 'min_distance' === $field_key ) {
			return isset( $params['min_distance'] ) ? (int) $params['min_distance'] : 50;
		}

		return isset( $params[ $field_key ] ) && is_numeric( $params[ $field_key ] )
		? (float) $params[ $field_key ]
		: 0.0;
	}

	/**
	 * Editable custom rule definitions for admin UI and sanitization.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function editable_rule_definitions(): array {
		return array(
			'title_length_px'           => array(
				'title'  => __( 'SEO title width', 'airygen-seo' ),
				'fields' => array(
					'px_min'    => array(
						'label' => __( 'Min width', 'airygen-seo' ) . ' (px)',
						'help'  => __( 'Minimum SEO title pixel width before score fails.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 1200,
						'step'  => 1,
					),
					'px_max'    => array(
						'label' => __( 'Max width', 'airygen-seo' ) . ' (px)',
						'help'  => __( 'Maximum SEO title pixel width before score fails.', 'airygen-seo' ),
						'min'   => 100,
						'max'   => 1200,
						'step'  => 1,
					),
					'min_chars' => array(
						'label' => __( 'Minimum characters', 'airygen-seo' ),
						'help'  => __( 'Minimum SEO title character length.', 'airygen-seo' ),
						'min'   => 1,
						'max'   => 200,
						'step'  => 1,
					),
				),
			),
			'meta_description_length'   => array(
				'title'  => __( 'Meta description width', 'airygen-seo' ),
				'fields' => array(
					'min' => array(
						'label' => __( 'Min width', 'airygen-seo' ) . ' (px)',
						'help'  => __( 'Minimum meta description pixel width.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 2000,
						'step'  => 1,
					),
					'max' => array(
						'label' => __( 'Max width', 'airygen-seo' ) . ' (px)',
						'help'  => __( 'Maximum meta description pixel width.', 'airygen-seo' ),
						'min'   => 100,
						'max'   => 2000,
						'step'  => 1,
					),
				),
			),
			'subheads_count'            => array(
				'title'  => __( 'Subheading count (H2/H3)', 'airygen-seo' ),
				'fields' => array(
					'min' => array(
						'label' => __( 'Minimum count', 'airygen-seo' ),
						'help'  => __( 'Minimum number of H2/H3 headings.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 100,
						'step'  => 1,
					),
					'max' => array(
						'label' => __( 'Maximum count', 'airygen-seo' ),
						'help'  => __( 'Maximum number of H2/H3 headings.', 'airygen-seo' ),
						'min'   => 1,
						'max'   => 200,
						'step'  => 1,
					),
				),
			),
			'keyword_density'           => array(
				'title'  => __( 'Focus keyphrase density', 'airygen-seo' ),
				'fields' => array(
					'ideal_min' => array(
						'label' => __( 'Ideal min (%)', 'airygen-seo' ),
						'help'  => __( 'Lower bound for full score.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 10,
						'step'  => 0.1,
					),
					'ideal_max' => array(
						'label' => __( 'Ideal max (%)', 'airygen-seo' ),
						'help'  => __( 'Upper bound for full score.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 10,
						'step'  => 0.1,
					),
					'soft_min'  => array(
						'label' => __( 'Soft min (%)', 'airygen-seo' ),
						'help'  => __( 'Lower bound for warning score.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 10,
						'step'  => 0.1,
					),
					'soft_max'  => array(
						'label' => __( 'Soft max (%)', 'airygen-seo' ),
						'help'  => __( 'Upper bound for warning score.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 10,
						'step'  => 0.1,
					),
				),
			),
			'long_tail_density'         => array(
				'title'  => __( 'Long-tail keyphrase density', 'airygen-seo' ),
				'fields' => array(
					'min'     => array(
						'label' => __( 'Per-phrase min (%)', 'airygen-seo' ),
						'help'  => __( 'Minimum density for each long-tail phrase.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 10,
						'step'  => 0.1,
					),
					'max'     => array(
						'label' => __( 'Per-phrase max (%)', 'airygen-seo' ),
						'help'  => __( 'Maximum density for each long-tail phrase.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 10,
						'step'  => 0.1,
					),
					'sum_max' => array(
						'label' => __( 'Total max (%)', 'airygen-seo' ),
						'help'  => __( 'Maximum combined long-tail density.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 20,
						'step'  => 0.1,
					),
				),
			),
			'word_count'                => array(
				'title'  => __( 'Minimum word count', 'airygen-seo' ),
				'fields' => array(
					'min' => array(
						'label' => __( 'Minimum words', 'airygen-seo' ),
						'help'  => __( 'Minimum word count required for pass.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 10000,
						'step'  => 1,
					),
				),
			),
			'readability_flesch'        => array(
				'title'  => __( 'Readability score (Flesch)', 'airygen-seo' ),
				'fields' => array(
					'min' => array(
						'label' => __( 'Minimum score', 'airygen-seo' ),
						'help'  => __( 'Minimum Flesch reading ease score.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 120,
						'step'  => 1,
					),
				),
			),
			'readability_cjk_sentences' => array(
				'title'  => __( 'Readability for CJK content', 'airygen-seo' ),
				'fields' => array(
					'max'           => array(
						'label' => __( 'Maximum average sentence length', 'airygen-seo' ),
						'help'  => __( 'Maximum average sentence length for CJK content.', 'airygen-seo' ),
						'min'   => 1,
						'max'   => 400,
						'step'  => 1,
					),
					'min_sentences' => array(
						'label' => __( 'Minimum sentences required', 'airygen-seo' ),
						'help'  => __( 'Minimum sentence count before this rule applies.', 'airygen-seo' ),
						'min'   => 1,
						'max'   => 50,
						'step'  => 1,
					),
				),
			),
			'long_tail_spacing'         => array(
				'title'  => __( 'Long-tail spacing distance', 'airygen-seo' ),
				'fields' => array(
					'min_distance' => array(
						'label' => __( 'Minimum spacing (words/chars)', 'airygen-seo' ),
						'help'  => __( 'Minimum distance between long-tail phrases and focus terms.', 'airygen-seo' ),
						'min'   => 1,
						'max'   => 500,
						'step'  => 1,
					),
				),
			),
			'internal_links'            => array(
				'title'  => __( 'Internal link count', 'airygen-seo' ),
				'fields' => array(
					'min'      => array(
						'label' => __( 'Ideal range min', 'airygen-seo' ),
						'help'  => __( 'Minimum internal link count for full score.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 100,
						'step'  => 1,
					),
					'max'      => array(
						'label' => __( 'Ideal range max', 'airygen-seo' ),
						'help'  => __( 'Maximum internal link count for full score.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 100,
						'step'  => 1,
					),
					'warn_min' => array(
						'label' => __( 'Warning range min', 'airygen-seo' ),
						'help'  => __( 'Minimum internal links for warning score range.', 'airygen-seo' ),
						'min'   => 1,
						'max'   => 200,
						'step'  => 1,
					),
				),
			),
			'external_links'            => array(
				'title'  => __( 'External link count', 'airygen-seo' ),
				'fields' => array(
					'min' => array(
						'label' => __( 'Minimum links', 'airygen-seo' ),
						'help'  => __( 'Minimum external link count required for pass.', 'airygen-seo' ),
						'min'   => 0,
						'max'   => 100,
						'step'  => 1,
					),
				),
			),
		);
	}

	/**
	 * Clamp weight to the allowed range.
	 *
	 * @param float $value Proposed weight.
	 *
	 * @return float
	 */
	private static function clamp_weight( float $value ): float {
		return max( self::MIN_WEIGHT, min( self::MAX_WEIGHT, $value ) );
	}
}
