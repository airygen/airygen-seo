<?php
/**
 * SEO score ruleset configuration.
 *
 * @package Airygen\ScoreCalculator
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'pack'     => 'seo-score-pack',
	'version'  => '1.0.0',
	'language' => 'auto',
	'rules'    => array(
		array(
			'id'     => 'title_length_px',
			'label'  => ( function_exists( '__' ) ? __( 'SEO title length', 'airygen-seo' ) : 'SEO title length' ),
			'weight' => 6,
			'type'   => 'title_length_px',
			'params' => array(
				'px_max'    => 580,
				'min_chars' => 15,
				'px_min'    => 350,
			),
		),
		array(
			'id'             => 'keyword_in_title',
			'label'          => ( function_exists( '__' ) ? __( 'Title includes focus keyphrase', 'airygen-seo' ) : 'Title includes focus keyphrase' ),
			'weight'         => 6,
			'type'           => 'boolean',
			'params'         => array(
				'field' => 'title_contains_focus',
			),
			'requires_focus' => true,
		),
		array(
			'id'     => 'meta_description_present',
			'label'  => ( function_exists( '__' ) ? __( 'Meta description present', 'airygen-seo' ) : 'Meta description present' ),
			'weight' => 2,
			'type'   => 'boolean',
			'params' => array(
				'field' => 'meta_description_present',
			),
		),
		array(
			'id'     => 'meta_description_length',
			'label'  => (
				function_exists( 'sprintf' ) && function_exists( '__' )
					? sprintf(
						/* translators: 1: minimum px width, 2: maximum px width. */
						__( 'Meta description width %1$d-%2$dpx', 'airygen-seo' ),
						640,
						920
					)
					: 'Meta description width 640-920px'
			),
			'weight' => 2,
			'type'   => 'range_between',
			'params' => array(
				'value_field' => 'meta_description_length_px',
				'min'         => 640,
				'max'         => 920,
			),
		),
		array(
			'id'             => 'meta_description_has_focus',
			'label'          => ( function_exists( '__' ) ? __( 'Meta description includes focus keyphrase', 'airygen-seo' ) : 'Meta description includes focus keyphrase' ),
			'weight'         => 2,
			'type'           => 'boolean',
			'params'         => array(
				'field' => 'meta_description_contains_focus',
			),
			'requires_focus' => true,
		),
		array(
			'id'     => 'snippet_unique',
			'label'  => ( function_exists( '__' ) ? __( 'Title and description are distinct', 'airygen-seo' ) : 'Title and description are distinct' ),
			'weight' => 1,
			'type'   => 'boolean',
			'params' => array(
				'field' => 'title_desc_unique',
			),
		),
		array(
			'id'     => 'h1_one',
			'label'  => ( function_exists( '__' ) ? __( 'Single H1 heading present', 'airygen-seo' ) : 'Single H1 heading present' ),
			'weight' => 4,
			'type'   => 'h1_one',
			'params' => array(),
		),
		array(
			'id'     => 'subheads_count',
			'label'  => (
				function_exists( 'sprintf' ) && function_exists( '__' )
					? sprintf(
						/* translators: 1: minimum heading count, 2: maximum heading count. */
						__( 'H2/H3 count between %1$d and %2$d', 'airygen-seo' ),
						2,
						8
					)
					: 'H2/H3 count between 2 and 8'
			),
			'weight' => 4,
			'type'   => 'count_between',
			'params' => array(
				'value_field' => 'subheads_count',
				'min'         => 2,
				'max'         => 8,
			),
		),
		array(
			'id'             => 'focus_in_subheads',
			'label'          => ( function_exists( '__' ) ? __( 'At least one focus keyphrase in an H2 or H3', 'airygen-seo' ) : 'At least one focus keyphrase in an H2 or H3' ),
			'weight'         => 4,
			'type'           => 'boolean',
			'params'         => array(
				'field' => 'subheads_focus_any',
			),
			'requires_focus' => true,
		),
		array(
			'id'             => 'intro_has_focus',
			'label'          => ( function_exists( '__' ) ? __( 'Intro includes focus keyphrase', 'airygen-seo' ) : 'Intro includes focus keyphrase' ),
			'weight'         => 3,
			'type'           => 'boolean',
			'params'         => array(
				'field' => 'intro_contains_focus',
			),
			'requires_focus' => true,
		),
		array(
			'id'             => 'keyword_density',
			'label'          => (
				function_exists( 'sprintf' ) && function_exists( '__' )
					? sprintf(
						/* translators: 1: ideal minimum density, 2: ideal maximum density. */
						__( 'Focus keyphrase density %1$s%%-%2$s%%', 'airygen-seo' ),
						'0.5',
						'2'
					)
					: 'Focus keyphrase density 0.5%-2%'
			),
			'weight'         => 8,
			'type'           => 'keyword_density',
			'params'         => array(
				'ideal_min' => 0.5,
				'ideal_max' => 2.0,
				'soft_min'  => 0.3,
				'soft_max'  => 2.5,
			),
			'requires_focus' => true,
		),
		array(
			'id'     => 'long_tail_density',
			'label'  => (
				function_exists( 'sprintf' ) && function_exists( '__' )
					? sprintf(
						/* translators: 1: per phrase minimum density, 2: per phrase maximum density, 3: total maximum density. */
						__( 'Long-tail keyphrase density %1$s%%-%2$s%% each, total <= %3$s%%', 'airygen-seo' ),
						'0.1',
						'0.5',
						'2'
					)
					: 'Long-tail keyphrase density 0.1%-0.5% each, total <= 2%'
			),
			'weight' => 4,
			'type'   => 'long_tail_density',
			'params' => array(
				'min'     => 0.1,
				'max'     => 0.5,
				'sum_max' => 2,
			),
		),
		array(
			'id'     => 'word_count',
			'label'  => (
				function_exists( 'sprintf' ) && function_exists( '__' )
					? sprintf(
						/* translators: %d is the minimum word count. */
						__( 'Word count at least %d', 'airygen-seo' ),
						300
					)
					: 'Word count at least 300'
			),
			'weight' => 4,
			'type'   => 'min_value',
			'params' => array(
				'value_field' => 'word_count',
				'min'         => 300,
			),
		),
		array(
			'id'     => 'readability_flesch',
			'label'  => (
				function_exists( 'sprintf' ) && function_exists( '__' )
					? sprintf(
						/* translators: %d is the minimum Flesch score. */
						__( 'Flesch reading ease >= %d (non-CJK)', 'airygen-seo' ),
						60
					)
					: 'Flesch reading ease >= 60 (non-CJK)'
			),
			'weight' => 8,
			'type'   => 'flesch_reading_ease',
			'params' => array(
				'min' => 60,
			),
		),
		array(
			'id'     => 'readability_cjk_sentences',
			'label'  => (
				function_exists( 'sprintf' ) && function_exists( '__' )
					? sprintf(
						/* translators: %d is the maximum CJK sentence length. */
						__( 'Average sentence length <= %d (CJK)', 'airygen-seo' ),
						60
					)
					: 'Average sentence length <= 60 (CJK)'
			),
			'weight' => 8,
			'type'   => 'avg_sentence_chars_cjk',
			'params' => array(
				'max'           => 60,
				'min_sentences' => 3,
			),
		),
		array(
			'id'     => 'has_image',
			'label'  => ( function_exists( '__' ) ? __( 'At least one image in content', 'airygen-seo' ) : 'At least one image in content' ),
			'weight' => 2,
			'type'   => 'min_value',
			'params' => array(
				'value_field' => 'image_count',
				'min'         => 1,
			),
		),
		array(
			'id'     => 'images_alt_all',
			'label'  => ( function_exists( '__' ) ? __( 'All images have alt text', 'airygen-seo' ) : 'All images have alt text' ),
			'weight' => 3,
			'type'   => 'boolean',
			'params' => array(
				'field' => 'all_images_have_alt',
			),
		),
		array(
			'id'     => 'long_tail_spacing',
			'label'  => (
				function_exists( 'sprintf' ) && function_exists( '__' )
					? sprintf(
						/* translators: %d is the minimum spacing distance. */
						__( 'Long-tail keyphrases spaced %d+ words/chars from other keyphrases', 'airygen-seo' ),
						50
					)
					: 'Long-tail keyphrases spaced 50+ words/chars from other keyphrases'
			),
			'weight' => 3,
			'type'   => 'boolean',
			'params' => array(
				'field'        => 'long_tail_spacing_ok',
				'min_distance' => 50,
			),
		),
		array(
			'id'             => 'images_alt_focus_any',
			'label'          => ( function_exists( '__' ) ? __( 'At least one image alt has focus keyphrase', 'airygen-seo' ) : 'At least one image alt has focus keyphrase' ),
			'weight'         => 3,
			'type'           => 'boolean',
			'params'         => array(
				'field' => 'any_image_alt_has_focus',
			),
			'requires_focus' => true,
		),
		array(
			'id'     => 'internal_links',
			'label'  => (
				function_exists( 'sprintf' ) && function_exists( '__' )
					? sprintf(
						/* translators: 1: minimum internal links, 2: maximum internal links. */
						__( 'Internal links between %1$d and %2$d', 'airygen-seo' ),
						1,
						3
					)
					: 'Internal links between 1 and 3'
			),
			'weight' => 6,
			'type'   => 'count_bracket_score',
			'params' => array(
				'value_field' => 'internal_links',
				'brackets'    => array(
					array(
						'min'   => 1,
						'max'   => 3,
						'score' => 1.0,
					),
					array(
						'min'   => 4,
						'max'   => 999,
						'score' => 0.67,
					),
				),
				'else'        => 0,
			),
		),
		array(
			'id'     => 'external_links',
			'label'  => (
				function_exists( 'sprintf' ) && function_exists( '__' )
					? sprintf(
						/* translators: %d is the minimum external links count. */
						__( 'External links at least %d', 'airygen-seo' ),
						1
					)
					: 'External links at least 1'
			),
			'weight' => 3,
			'type'   => 'min_value',
			'params' => array(
				'value_field' => 'external_links',
				'min'         => 1,
			),
		),
		array(
			'id'     => 'rel_attributes',
			'label'  => ( function_exists( '__' ) ? __( 'External links use rel attributes', 'airygen-seo' ) : 'External links use rel attributes' ),
			'weight' => 3,
			'type'   => 'boolean',
			'params' => array(
				'field' => 'rel_compliance',
			),
		),
		array(
			'id'     => 'slug_words',
			'label'  => ( function_exists( '__' ) ? __( 'Slug has 2–5 words', 'airygen-seo' ) : 'Slug has 2–5 words' ),
			'weight' => 3,
			'type'   => 'count_between',
			'params' => array(
				'value_field' => 'slug_words',
				'min'         => 2,
				'max'         => 5,
			),
		),
		array(
			'id'             => 'slug_has_focus',
			'label'          => ( function_exists( '__' ) ? __( 'Slug includes focus keyphrase', 'airygen-seo' ) : 'Slug includes focus keyphrase' ),
			'weight'         => 3,
			'type'           => 'boolean',
			'params'         => array(
				'field' => 'slug_contains_focus',
			),
			'requires_focus' => true,
		),
		array(
			'id'     => 'canonical_valid',
			'label'  => ( function_exists( '__' ) ? __( 'Canonical is valid', 'airygen-seo' ) : 'Canonical is valid' ),
			'weight' => 4,
			'type'   => 'boolean',
			'params' => array(
				'field' => 'canonical_valid',
			),
		),
		array(
			'id'     => 'jsonld_article',
			'label'  => ( function_exists( '__' ) ? __( 'Article structured data present', 'airygen-seo' ) : 'Article structured data present' ),
			'weight' => 3,
			'type'   => 'boolean',
			'params' => array(
				'field' => 'jsonld_article_present',
			),
		),
		array(
			'id'     => 'jsonld_breadcrumb',
			'label'  => ( function_exists( '__' ) ? __( 'Breadcrumb structured data present', 'airygen-seo' ) : 'Breadcrumb structured data present' ),
			'weight' => 2,
			'type'   => 'boolean',
			'params' => array(
				'field' => 'jsonld_breadcrumb_present',
			),
		),
	),
	'bonus'    => array(
		array(
			'id'     => 'site_health_good',
			'label'  => ( function_exists( '__' ) ? __( 'Sitewide SEO (scaled)', 'airygen-seo' ) : 'Sitewide SEO (scaled)' ),
			'weight' => 5,
			'type'   => 'scaled_score',
			'params' => array(
				'value_field' => 'site_health_score',
			),
		),
	),
);
