<?php
/**
 * Immutable representation of the document content used for scoring.
 *
 * @package Airygen\Modules\ScoreCalculator\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\ScoreCalculator\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\ScoreCalculator\Domain\TitlePixelEstimator;
use Airygen\Support\Utils\Text;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Captures the text inputs required by the scoring rules.
 */
final class DocumentContext {

	/**
	 * Document title text.
	 *
	 * @var string
	 */
	private string $title;

	/**
	 * Meta description content.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * Full content body used for scoring (HTML).
	 *
	 * @var string
	 */
	private string $content;

	/**
	 * Focus keyphrase provided by the editor.
	 *
	 * @var string
	 */
	private string $focus_keyphrase;

	/**
	 * Long-tail keyphrases provided by the editor.
	 *
	 * @var array<int, string>
	 */
	private array $long_tail_keyphrases = array();

	/**
	 * Post slug.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Canonical URL override.
	 *
	 * @var string
	 */
	private string $canonical;

	/**
	 * Permalink for the post.
	 *
	 * @var string
	 */
	private string $permalink;

	/**
	 * Site host used to determine internal links.
	 *
	 * @var string
	 */
	private string $site_host;

	/**
	 * Site Health diagnostics score ratio (0-1).
	 *
	 * @var float
	 */
	private float $site_health_score;

	/**
	 * Cached plain text content.
	 *
	 * @var string
	 */
	private string $plain_content = '';

	/**
	 * Whether the content contains CJK scripts.
	 *
	 * @var bool
	 */
	private bool $is_cjk_language = false;

	/**
	 * Cached word count.
	 *
	 * @var int
	 */
	private int $word_count = 0;

	/**
	 * Cached sentence count.
	 *
	 * @var int
	 */
	private int $sentence_count = 0;

	/**
	 * Cached syllable count.
	 *
	 * @var int
	 */
	private int $syllable_count = 0;

	/**
	 * Cached focus occurrences.
	 *
	 * @var int
	 */
	private int $focus_occurrences = 0;

	/**
	 * Cached title pixel length.
	 *
	 * @var int|null
	 */
	private ?int $title_pixel_length = null;

	/**
	 * List of heading nodes (level => text).
	 *
	 * @var array<int, array{level:string,text:string}>
	 */
	private array $headings = array();

	/**
	 * List of subheading nodes (H2/H3).
	 *
	 * @var array<int, string>
	 */
	private array $sub_headings = array();

	/**
	 * List of image alt texts.
	 *
	 * @var array<int, string>
	 */
	private array $image_alts = array();

	/**
	 * List of links with href, rel tokens, and type classification.
	 *
	 * @var array<int, array{href:string,rel:array<int,string>,type:string}>
	 */
	private array $links = array();

	/**
	 * JSON-LD @type values discovered in content.
	 *
	 * @var array<string, bool>
	 */
	private array $json_ld_types = array();

	/**
	 * Optional override for Article JSON-LD presence.
	 *
	 * @var bool|null
	 */
	private ?bool $jsonld_article_override = null;

	/**
	 * Optional override for Breadcrumb JSON-LD presence.
	 *
	 * @var bool|null
	 */
	private ?bool $jsonld_breadcrumb_override = null;

	/**
	 * Optional override for meta title pixel length.
	 *
	 * @var float|null
	 */
	private ?float $meta_title_px_override = null;

	/**
	 * Optional override for meta description pixel length.
	 *
	 * @var float|null
	 */
	private ?float $meta_description_px_override = null;

	/**
	 * Factory helper for building a context instance.
	 *
	 * @param array<string, mixed> $data Input payload.
	 */
	public static function from_array( array $data ): self {
		return new self(
			self::to_string( $data['title'] ?? '' ),
			self::to_string( $data['description'] ?? '' ),
			self::to_string( $data['content'] ?? '' ),
			self::to_string( $data['focus_keyphrase'] ?? '' ),
			self::to_string_array( $data['long_tail_keyphrases'] ?? array() ),
			self::to_string( $data['slug'] ?? '' ),
			self::to_string( $data['canonical'] ?? '' ),
			self::to_string( $data['permalink'] ?? '' ),
			self::to_string( $data['site_host'] ?? '' ),
			self::ratio_or_zero( $data['site_health_score'] ?? null ),
			isset( $data['jsonld_article_present'] ) ? (bool) $data['jsonld_article_present'] : null,
			isset( $data['jsonld_breadcrumb_present'] ) ? (bool) $data['jsonld_breadcrumb_present'] : null,
			isset( $data['meta_title_length_px'] ) && is_numeric( $data['meta_title_length_px'] )
					? (float) $data['meta_title_length_px']
					: null,
			isset( $data['meta_description_length_px'] ) && is_numeric( $data['meta_description_length_px'] )
					? (float) $data['meta_description_length_px']
					: null
		);
	}

	/**
	 * Constructor.
	 *
	 * @param string             $title                Resolved title.
	 * @param string             $description          Resolved meta description.
	 * @param string             $content              Raw HTML content.
	 * @param string             $focus_keyphrase      Focus keyphrase value.
	 * @param array<int, string> $long_tail_keyphrases Long-tail keyphrases.
	 * @param string             $slug                 Post slug.
	 * @param string             $canonical            Canonical URL.
	 * @param string             $permalink            Post permalink.
	 * @param string             $site_host            Current site host.
	 * @param float              $site_health_score    Site Health diagnostics ratio.
	 */
	private function __construct(
		string $title,
		string $description,
		string $content,
		string $focus_keyphrase,
		array $long_tail_keyphrases,
		string $slug,
		string $canonical,
		string $permalink,
		string $site_host,
		float $site_health_score,
		?bool $jsonld_article_present,
		?bool $jsonld_breadcrumb_present,
		?float $meta_title_length_px,
		?float $meta_description_length_px
	) {
		$this->title                        = $title;
		$this->description                  = $description;
		$this->content                      = $content;
		$this->focus_keyphrase              = $focus_keyphrase;
		$this->long_tail_keyphrases         = $this->normalize_long_tail_keyphrases( $long_tail_keyphrases );
		$this->slug                         = $slug;
		$this->canonical                    = $canonical;
		$this->permalink                    = $permalink;
		$this->site_host                    = $site_host;
		$this->site_health_score            = $site_health_score;
		$this->jsonld_article_override      = $jsonld_article_present;
		$this->jsonld_breadcrumb_override   = $jsonld_breadcrumb_present;
		$this->meta_title_px_override       = $meta_title_length_px;
		$this->meta_description_px_override = $meta_description_length_px;

		$this->compute_plain_metrics();
		$this->parse_document();
	}

	/**
	 * Determine whether a focus keyphrase is present.
	 */
	public function has_focus_keyphrase(): bool {
		return '' !== $this->focus_keyphrase;
	}

	/**
	 * Retrieve the title in use.
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Retrieve the meta description in use.
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Retrieve word count.
	 */
	public function get_word_count(): int {
		if ( $this->is_cjk_language ) {
			return $this->count_cjk_characters( $this->plain_content );
		}

		return $this->word_count;
	}

	/**
	 * Retrieve title length in characters.
	 */
	public function get_title_length_chars(): int {
		return mb_strlen( $this->title );
	}

	/**
	 * Retrieve meta description length in characters.
	 */
	public function get_meta_description_length(): int {
		return mb_strlen( $this->description );
	}

	/**
	 * Retrieve meta description length in pixels (approximate).
	 */
	public function get_meta_description_length_px(): int {
		if ( null !== $this->meta_description_px_override ) {
			return (int) round( $this->meta_description_px_override );
		}

		return TitlePixelEstimator::estimate( $this->description );
	}

	/**
	 * Retrieve title length in pixels (approximate).
	 */
	public function get_title_length_px(): int {
		if ( null !== $this->meta_title_px_override ) {
			return (int) round( $this->meta_title_px_override );
		}

		if ( null === $this->title_pixel_length ) {
			$this->title_pixel_length = TitlePixelEstimator::estimate( $this->title );
		}

		return $this->title_pixel_length;
	}

	/**
	 * Determine if the meta description exists.
	 */
	public function is_meta_description_present(): bool {
		return '' !== $this->description;
	}

	/**
	 * Determine if the meta description contains the focus keyphrase.
	 */
	public function is_meta_description_contains_focus(): bool {
		return $this->contains_focus( $this->description );
	}

	/**
	 * Determine if the title contains the focus keyphrase.
	 */
	public function is_title_contains_focus(): bool {
		return $this->contains_focus( $this->title );
	}

	/**
	 * Determine if the content intro contains the focus keyphrase.
	 */
	public function is_intro_contains_focus(): bool {
		$intro = mb_substr( $this->plain_content, 0, 400 );
		return $this->contains_focus( $intro );
	}

	/**
	 * Determine if the snippet (title vs description) is unique enough.
	 */
	public function is_snippet_unique(): bool {
		if ( '' === $this->title || '' === $this->description ) {
			return false;
		}

		$title = mb_strtolower( $this->title );
		$desc  = mb_strtolower( $this->description );

		return $title !== $desc;
	}

	/**
	 * Retrieve the number of H1 headings.
	 */
	public function get_h1_count(): int {
		$count = 0;
		foreach ( $this->headings as $heading ) {
			if ( 'h1' === $heading['level'] ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Retrieve count of H2/H3 headings.
	 */
	public function get_subhead_count(): int {
		return count( $this->sub_headings );
	}

	/**
	 * Retrieve percentage of subheadings containing the focus keyphrase.
	 */
	public function get_subhead_focus_percent(): float {
		$total = count( $this->sub_headings );
		if ( 0 === $total ) {
			return 0.0;
		}

		$matches = 0;
		foreach ( $this->sub_headings as $heading ) {
			if ( $this->contains_focus( $heading ) ) {
				++$matches;
			}
		}

		return ( $matches / $total ) * 100.0;
	}

	/**
	 * Determine if any H2/H3 contains the focus keyphrase.
	 */
	public function has_focus_in_subhead(): bool {
		if ( empty( $this->sub_headings ) ) {
			return false;
		}

		foreach ( $this->sub_headings as $heading ) {
			if ( $this->contains_focus( $heading ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieve keyword density (percentage).
	 */
	public function get_keyword_density(): float {
		if ( $this->word_count <= 0 || $this->focus_occurrences <= 0 ) {
			return 0.0;
		}

		return ( $this->focus_occurrences / $this->word_count ) * 100.0;
	}

	/**
	 * Retrieve long-tail density per phrase (percentage) and total.
	 *
	 * @return array{totals: array<string, float>, sum: float}
	 */
	public function get_long_tail_density(): array {
		$densities = array();

		if ( $this->word_count <= 0 || empty( $this->long_tail_keyphrases ) ) {
			return array(
				'totals' => $densities,
				'sum'    => 0.0,
			);
		}

		foreach ( $this->long_tail_keyphrases as $phrase ) {
			$count                = $this->count_phrase_occurrences( $this->plain_content, $phrase );
			$densities[ $phrase ] = $count > 0
			? ( $count / $this->word_count ) * 100.0
			: 0.0;
		}

		$sum = array_sum( $densities );

		return array(
			'totals' => $densities,
			'sum'    => $sum,
		);
	}

	/**
	 * Retrieve the Flesch reading ease value.
	 */
	public function get_flesch_reading_ease(): float {
		if ( $this->word_count <= 0 || $this->sentence_count <= 0 ) {
			return 0.0;
		}

		$words_per_sentence = $this->word_count / $this->sentence_count;
		$syllables_per_word = $this->syllable_count > 0 ? $this->syllable_count / $this->word_count : 0.0;
		$score              = 206.835 - ( 1.015 * $words_per_sentence ) - ( 84.6 * $syllables_per_word );

		return max( 0.0, min( 120.0, $score ) );
	}

	/**
	 * Retrieve image count.
	 */
	public function get_image_count(): int {
		return count( $this->image_alts );
	}

	/**
	 * Retrieve average characters per sentence (excluding whitespace).
	 */
	public function get_avg_chars_per_sentence(): float {
		$sentences = $this->split_sentences( $this->plain_content );
		$count     = count( $sentences );

		if ( 0 === $count ) {
			return 0.0;
		}

		$total_chars = 0;
		foreach ( $sentences as $sentence ) {
			$normalized   = preg_replace( '/[\s\r\n]+/u', '', $sentence );
			$total_chars += $normalized ? mb_strlen( $normalized ) : 0;
		}

		return $count > 0 ? $total_chars / $count : 0.0;
	}

	/**
	 * Expose sentence count.
	 */
	public function get_sentence_count(): int {
		return $this->sentence_count;
	}

	/**
	 * Whether the document is considered CJK based on content.
	 */
	public function is_cjk_language(): bool {
		return $this->is_cjk_language;
	}

	/**
	 * Determine if all images have alt text.
	 */
	public function is_all_images_have_alt(): bool {
		if ( empty( $this->image_alts ) ) {
			return false;
		}

		foreach ( $this->image_alts as $alt ) {
			if ( '' === $alt ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine if any image alt text includes the focus keyphrase.
	 */
	public function is_any_image_alt_has_focus(): bool {
		foreach ( $this->image_alts as $alt ) {
			if ( $this->contains_focus( $alt ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Count internal links.
	 */
	public function get_internal_links_count(): int {
		return $this->count_links_by_type( 'internal' );
	}

	/**
	 * Count external links.
	 */
	public function get_external_links_count(): int {
		return $this->count_links_by_type( 'external' );
	}

	/**
	 * Determine if rel attributes are compliant for external links.
	 */
	public function is_rel_compliance(): bool {
		$external_links = array_filter(
			$this->links,
			static function ( array $link ): bool {
				return 'external' === $link['type'];
			}
		);

		if ( empty( $external_links ) ) {
			return true;
		}

		foreach ( $external_links as $link ) {
			$rel_tokens = $link['rel'];
			$has_token  = false;
			foreach ( $rel_tokens as $token ) {
				$lower = strtolower( $token );
				if ( in_array( $lower, array( 'noopener', 'noreferrer', 'nofollow' ), true ) ) {
					$has_token = true;
					break;
				}
			}

			if ( ! $has_token ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Retrieve slug word count.
	 */
	public function get_slug_word_count(): int {
		if ( '' === $this->slug ) {
			return 0;
		}

		$parts = preg_split( '/[-_]+/', $this->slug );
		if ( ! is_array( $parts ) ) {
			return 0;
		}

		$words = array_filter(
			$parts,
			static function ( $part ): bool {
				return '' !== trim( (string) $part );
			}
		);

		return count( $words );
	}

	/**
	 * Determine if slug contains the focus keyphrase.
	 */
	public function is_slug_contains_focus(): bool {
		if ( '' === $this->focus_keyphrase || '' === $this->slug ) {
			return false;
		}

		$needle = $this->normalize_slug( $this->focus_keyphrase );
		$slug   = $this->normalize_slug( $this->slug );

		if ( '' === $needle || '' === $slug ) {
			return false;
		}

		return false !== strpos( $slug, $needle );
	}

	/**
	 * Determine if canonical is valid.
	 */
	public function is_canonical_valid(): bool {
		if ( '' === $this->canonical ) {
			return true;
		}

		if ( false === filter_var( $this->canonical, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$canonical_host = (string) parse_url( $this->canonical, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( '' === $canonical_host ) {
			return false;
		}

		if ( '' === $this->site_host ) {
			return true;
		}

		return $canonical_host === $this->site_host;
	}

	/**
	 * Determine if Article JSON-LD exists.
	 */
	public function is_jsonld_article_present(): bool {
		if ( null !== $this->jsonld_article_override ) {
			return $this->jsonld_article_override;
		}

		foreach ( array_keys( $this->json_ld_types ) as $type ) {
			if ( false !== stripos( (string) $type, 'article' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if Breadcrumb JSON-LD exists.
	 */
	public function is_jsonld_breadcrumb_present(): bool {
		if ( null !== $this->jsonld_breadcrumb_override ) {
			return $this->jsonld_breadcrumb_override;
		}

		return isset( $this->json_ld_types['BreadcrumbList'] );
	}

	/**
	 * Retrieve keyword density denominator availability.
	 */
	public function has_focus_in_subheads_context(): bool {
		return 0 !== count( $this->sub_headings );
	}

	/**
	 * Determine if any long-tail keyphrase exists.
	 */
	public function has_long_tail_keyphrases(): bool {
		return count( $this->long_tail_keyphrases ) > 0;
	}

	/**
	 * Determine if meta description contains value.
	 */
	public function get_meta_description(): string {
		return $this->description;
	}

	/**
	 * Compute numeric metric by key.
	 *
	 * @param string $key Metric identifier.
	 */
	public function get_numeric_metric( string $key ): float {
		switch ( $key ) {
			case 'title_length_px':
				return (float) $this->get_title_length_px();
			case 'meta_description_length':
				return (float) $this->get_meta_description_length();
			case 'meta_description_length_px':
				return (float) $this->get_meta_description_length_px();
			case 'subheads_count':
				return (float) $this->get_subhead_count();
			case 'subheads_focus_percent':
				return $this->get_subhead_focus_percent();
			case 'keyword_density':
				return $this->get_keyword_density();
			case 'long_tail_density_sum':
				return $this->get_long_tail_density()['sum'];
			case 'long_tail_density_max':
				$densities = $this->get_long_tail_density()['totals'];
				return empty( $densities ) ? 0.0 : (float) max( $densities );
			case 'avg_sentence_chars':
				return $this->get_avg_chars_per_sentence();
			case 'word_count':
				return (float) $this->word_count;
			case 'flesch_reading_ease':
				return $this->get_flesch_reading_ease();
			case 'image_count':
				return (float) $this->get_image_count();
			case 'internal_links':
				return (float) $this->get_internal_links_count();
			case 'external_links':
				return (float) $this->get_external_links_count();
			case 'slug_words':
				return (float) $this->get_slug_word_count();
			case 'site_health_score':
				return $this->site_health_score;
			default:
				return 0.0;
		}
	}

	/**
	 * Compute boolean metric by key.
	 *
	 * @param string $key Metric identifier.
	 */
	public function get_boolean_metric( string $key ): bool {
		switch ( $key ) {
			case 'title_contains_focus':
				return $this->is_title_contains_focus();
			case 'meta_description_present':
				return $this->is_meta_description_present();
			case 'meta_description_contains_focus':
				return $this->is_meta_description_contains_focus();
			case 'snippet_unique':
			case 'title_desc_unique':
				return $this->is_snippet_unique();
			case 'intro_contains_focus':
				return $this->is_intro_contains_focus();
			case 'all_images_have_alt':
				return $this->is_all_images_have_alt();
			case 'any_image_alt_has_focus':
				return $this->is_any_image_alt_has_focus();
			case 'rel_compliance':
				return $this->is_rel_compliance();
			case 'slug_contains_focus':
				return $this->is_slug_contains_focus();
			case 'subheads_focus_any':
				return $this->has_focus_in_subhead();
			case 'long_tail_spacing_ok':
				return $this->is_long_tail_spacing_ok( 50 );
			case 'canonical_valid':
				return $this->is_canonical_valid();
			case 'jsonld_article_present':
				return $this->is_jsonld_article_present();
			case 'jsonld_breadcrumb_present':
				return $this->is_jsonld_breadcrumb_present();
			default:
				return false;
		}
	}

	/**
	 * Evaluate long-tail spacing with configurable minimum distance.
	 *
	 * @param int $min_distance Minimum distance in words/chars.
	 *
	 * @return bool
	 */
	public function is_long_tail_spacing_ok_with_distance( int $min_distance ): bool {
		$distance = max( 1, $min_distance );

		return $this->is_long_tail_spacing_ok( $distance );
	}

	/**
	 * Normalize a ratio input to the 0-1 range.
	 *
	 * @param mixed $value Arbitrary input value.
	 */
	private static function ratio_or_zero( $value ): float {
		if ( is_numeric( $value ) ) {
			$float = (float) $value;
			if ( is_finite( $float ) ) {
				return max( 0.0, min( 1.0, $float ) );
			}
		}

		return 0.0;
	}

	/**
	 * Normalize arbitrary input as trimmed string.
	 *
	 * @param mixed $value Arbitrary input value.
	 */
	private static function to_string( $value ): string {
		if ( null === $value ) {
			return '';
		}

		if ( is_string( $value ) || is_numeric( $value ) ) {
			return trim( (string) $value );
		}

		return '';
	}

	/**
	 * Normalize an input as an array of strings.
	 *
	 * @param mixed $value Arbitrary input.
	 *
	 * @return array<int, string>
	 */
	private static function to_string_array( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $value as $entry ) {
			$normalized[] = self::to_string( $entry );
		}

		return $normalized;
	}

	/**
	 * Parse the HTML document to populate metrics.
	 */
	private function parse_document(): void {
		$this->headings      = array();
		$this->sub_headings  = array();
		$this->image_alts    = array();
		$this->links         = array();
		$this->json_ld_types = array();

		$html = trim( $this->content );
		if ( '' === $html ) {
			return;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();

		if ( ! $loaded ) {
			return;
		}

		$xpath = new DOMXPath( $dom );

		foreach ( array( 'h1', 'h2', 'h3' ) as $tag ) {
			$nodes = $xpath->query( sprintf( '//%s', $tag ) );
			if ( ! $nodes ) {
				continue;
			}

			foreach ( $nodes as $node ) {
				if ( $node instanceof DOMElement ) {
					$text = trim( $node->textContent ?? '' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					if ( '' === $text ) {
						continue;
					}

					$this->headings[] = array(
						'level' => $tag,
						'text'  => $text,
					);

					if ( 'h2' === $tag || 'h3' === $tag ) {
						$this->sub_headings[] = $text;
					}
				}
			}
		}

		$images = $xpath->query( '//img' );
		if ( $images ) {
			foreach ( $images as $image ) {
				if ( $image instanceof DOMElement ) {
					$this->image_alts[] = trim( (string) $image->getAttribute( 'alt' ) );
				}
			}
		}

		$links = $xpath->query( '//a[@href]' );
		if ( $links ) {
			foreach ( $links as $link ) {
				if ( $link instanceof DOMElement ) {
					$href = trim( (string) $link->getAttribute( 'href' ) );
					if ( '' === $href ) {
						continue;
					}

					$type       = $this->classify_link( $href );
					$rel        = trim( (string) $link->getAttribute( 'rel' ) );
					$rel_tokens = preg_split( '/\s+/', $rel );
					if ( ! is_array( $rel_tokens ) ) {
						$rel_tokens = array();
					}
					$rel_tokens = array_filter( $rel_tokens );

					$this->links[] = array(
						'href' => $href,
						'rel'  => $rel_tokens,
						'type' => $type,
					);
				}
			}
		}

		$scripts = $xpath->query( '//script[@type="application/ld+json"]' );
		if ( $scripts ) {
			foreach ( $scripts as $script ) {
				if ( $script instanceof DOMElement ) {
					$this->extract_jsonld_types( $script->textContent ?? '' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				}
			}
		}
	}

	/**
	 * Compute derived metrics from plain text content.
	 */
	private function compute_plain_metrics(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Domain service must remain framework-agnostic.
		$this->plain_content     = trim( strip_tags( $this->content ) );
		$this->is_cjk_language   = Text::is_cjk( $this->plain_content, 30 );
		$this->word_count        = $this->count_words( $this->plain_content );
		$this->sentence_count    = $this->count_sentences( $this->plain_content );
		$this->syllable_count    = $this->count_syllables( $this->plain_content );
		$this->focus_occurrences = $this->count_focus_occurrences( $this->plain_content );
	}

	/**
	 * Count words.
	 *
	 * @param string $text Input text.
	 */
	private function count_words( string $text ): int {
		if ( '' === $text ) {
			return 0;
		}

		$words = preg_split( '/[\s\r\n\t]+/u', $text );
		if ( ! is_array( $words ) ) {
			return 0;
		}

		return count( array_filter( $words, static fn( $word ) => '' !== trim( (string) $word ) ) );
	}

	/**
	 * Count CJK characters (excluding whitespace).
	 *
	 * @param string $text Input text.
	 */
	private function count_cjk_characters( string $text ): int {
		if ( '' === $text ) {
			return 0;
		}

		$cleaned = preg_replace( '/[\s\r\n\t]+/u', '', $text );
		if ( null === $cleaned ) {
			return 0;
		}

		return mb_strlen( $cleaned );
	}

	/**
	 * Count sentences using punctuation heuristics.
	 *
	 * @param string $text Input text.
	 */
	private function count_sentences( string $text ): int {
		$sentences = $this->split_sentences( $text );
		$count     = count( $sentences );

		if ( 0 === $count ) {
			return 0;
		}

		return max( 1, $count );
	}

	/**
	 * Count syllables using a basic heuristic.
	 *
	 * @param string $text Input text.
	 */
	private function count_syllables( string $text ): int {
		if ( '' === $text ) {
			return 0;
		}

		$text  = mb_strtolower( preg_replace( '/[^a-z\p{L}\s]/u', ' ', $text ) ?? '' );
		$words = preg_split( '/\s+/u', trim( $text ) );
		if ( ! is_array( $words ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( '' === $word ) {
				continue;
			}

			$count += $this->count_syllables_in_word( $word );
		}

		return $count;
	}

	/**
	 * Count focus keyphrase occurrences in text.
	 *
	 * @param string $text Input text.
	 */
	private function count_focus_occurrences( string $text ): int {
		if ( '' === $this->focus_keyphrase || '' === $text ) {
			return 0;
		}

		$pattern = '/' . preg_quote( $this->focus_keyphrase, '/' ) . '/iu';
		$count   = preg_match_all( $pattern, $text );

		return is_int( $count ) ? $count : 0;
	}

	/**
	 * Classify a link as internal or external.
	 *
	 * @param string $href Link href value.
	 */
	private function classify_link( string $href ): string {
		$host = (string) parse_url( $href, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url

		if ( '' === $host ) {
			return 'internal';
		}

		if ( '' !== $this->site_host && $this->normalize_host( $host ) === $this->normalize_host( $this->site_host ) ) {
			return 'internal';
		}

		return 'external';
	}

	/**
	 * Extract JSON-LD types recursively.
	 *
	 * @param string $json JSON-LD string.
	 */
	private function extract_jsonld_types( string $json ): void {
		$json = trim( $json );
		if ( '' === $json ) {
			return;
		}

		$decoded = json_decode( $json, true );
		if ( null === $decoded ) {
			return;
		}

		$this->collect_jsonld_types( $decoded );
	}

	/**
	 * Recursively collect @type values.
	 *
	 * @param mixed $node JSON node.
	 */
	private function collect_jsonld_types( $node ): void {
		if ( is_array( $node ) ) {
			if ( isset( $node['@type'] ) ) {
				$types = $node['@type'];
				if ( is_array( $types ) ) {
					foreach ( $types as $type ) {
						$this->json_ld_types[ (string) $type ] = true;
					}
				} else {
					$this->json_ld_types[ (string) $types ] = true;
				}
			}

			foreach ( $node as $value ) {
				$this->collect_jsonld_types( $value );
			}
		}
	}

	/**
	 * Count syllables for a single word using heuristic.
	 *
	 * @param string $word Input word.
	 */
	private function count_syllables_in_word( string $word ): int {
		$word = preg_replace( '/[^a-z\p{L}]/u', '', $word ) ?? '';
		if ( '' === $word ) {
			return 0;
		}

		$vowel_groups = preg_split( '/([^aeiouy]+)/i', $word, -1, PREG_SPLIT_NO_EMPTY );
		$count        = $vowel_groups ? count( $vowel_groups ) : 0;

		if ( preg_match( '/e$/i', $word ) ) {
			--$count;
		}

		return max( 1, $count );
	}

	/**
	 * Determine if text contains focus keyphrase.
	 *
	 * @param string $haystack Text to inspect.
	 */
	private function contains_focus( string $haystack ): bool {
		if ( '' === $this->focus_keyphrase || '' === $haystack ) {
			return false;
		}

		return false !== mb_stripos( $haystack, $this->focus_keyphrase );
	}

	/**
	 * Count case-insensitive occurrences of a phrase in text.
	 *
	 * @param string $text   Text to search.
	 * @param string $phrase Phrase to count.
	 */
	private function count_phrase_occurrences( string $text, string $phrase ): int {
		$phrase = trim( $phrase );
		if ( '' === $phrase || '' === $text ) {
			return 0;
		}

		$escaped = preg_quote( $phrase, '/' );
		if ( '' === $escaped ) {
			return 0;
		}

		if ( ! preg_match_all( '/' . $escaped . '/ui', $text, $matches ) ) {
			return 0;
		}

		return isset( $matches[0] ) ? count( $matches[0] ) : 0;
	}

	/**
	 * Split text into sentences using common Latin and CJK punctuation.
	 *
	 * @param string $text Input text.
	 *
	 * @return array<int, string>
	 */
	private function split_sentences( string $text ): array {
		if ( '' === $text ) {
			return array();
		}

		$parts = preg_split( '/(?<!\\d)[.!?](?!\\d\\S)|[。！？｡．]+/u', $text );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$sentences = array();
		foreach ( $parts as $part ) {
			$trimmed = trim( (string) $part );
			if ( '' !== $trimmed ) {
				$sentences[] = $trimmed;
			}
		}

		return $sentences;
	}

	/**
	 * Normalize a slug-like string for comparisons.
	 *
	 * @param string $value Raw value.
	 */
	private function normalize_slug( string $value ): string {
		$value = mb_strtolower( $value );
		$value = preg_replace( '/[^a-z0-9\s-]/u', '', $value ) ?? '';
		$value = preg_replace( '/[\s-]+/u', '-', $value ) ?? '';

		return trim( (string) $value, '-' );
	}

	/**
	 * Normalize long-tail keyphrases into a unique, trimmed list.
	 *
	 * @param array<int, string> $phrases Raw phrases.
	 *
	 * @return array<int, string>
	 */
	private function normalize_long_tail_keyphrases( array $phrases ): array {
		$normalized = array();

		foreach ( $phrases as $phrase ) {
			$clean = trim( (string) $phrase );
			if ( '' === $clean ) {
				continue;
			}
			if ( in_array( $clean, $normalized, true ) ) {
				continue;
			}
			$normalized[] = $clean;
		}

		return $normalized;
	}

	/**
	 * Determine if long-tail keyphrases are spaced 50+ words from other keyphrases.
	 */
	private function is_long_tail_spacing_ok( int $min_distance ): bool {
		if ( empty( $this->long_tail_keyphrases ) ) {
			return false;
		}

		if ( $this->is_cjk_language ) {
			return $this->is_long_tail_spacing_ok_cjk( $min_distance );
		}

		$tokens = $this->tokenize_words( $this->plain_content );
		if ( empty( $tokens ) ) {
			return false;
		}

		$focus_positions = $this->find_phrase_positions( $tokens, $this->focus_keyphrase );

		foreach ( $this->long_tail_keyphrases as $phrase ) {
			$positions = $this->find_phrase_positions( $tokens, $phrase );
			foreach ( $positions as $pos ) {
				foreach ( $focus_positions as $focus_pos ) {
					if ( abs( $pos - $focus_pos ) < $min_distance ) {
						return false;
					}
				}

				foreach ( $this->long_tail_keyphrases as $other_phrase ) {
					if ( $other_phrase === $phrase ) {
						continue;
					}
					$other_positions = $this->find_phrase_positions( $tokens, $other_phrase );
					foreach ( $other_positions as $other_pos ) {
						if ( abs( $pos - $other_pos ) < $min_distance ) {
							return false;
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * CJK-safe spacing check using character offsets.
	 */
	private function is_long_tail_spacing_ok_cjk( int $min_distance ): bool {
		$text = preg_replace( '/[\s\r\n\t]+/u', '', $this->plain_content );
		if ( null === $text || '' === $text ) {
			return false;
		}

		$focus_positions     = $this->find_phrase_positions_chars( $text, $this->focus_keyphrase );
		$long_tail_positions = array();

		foreach ( $this->long_tail_keyphrases as $phrase ) {
			$long_tail_positions[ $phrase ] = $this->find_phrase_positions_chars( $text, $phrase );
		}

		foreach ( $this->long_tail_keyphrases as $phrase ) {
			$positions = $long_tail_positions[ $phrase ] ?? array();
			if ( empty( $positions ) ) {
				continue;
			}

			foreach ( $positions as $pos ) {
				foreach ( $focus_positions as $focus_pos ) {
					if ( abs( $pos - $focus_pos ) < $min_distance ) {
						return false;
					}
				}

				foreach ( $this->long_tail_keyphrases as $other_phrase ) {
					if ( $other_phrase === $phrase ) {
						continue;
					}
					$other_positions = $long_tail_positions[ $other_phrase ] ?? array();
					foreach ( $other_positions as $other_pos ) {
						if ( abs( $pos - $other_pos ) < $min_distance ) {
							return false;
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * Tokenize plain text into word array.
	 *
	 * @param string $text Input text.
	 *
	 * @return array<int, string>
	 */
	private function tokenize_words( string $text ): array {
		if ( '' === $text ) {
			return array();
		}

		$tokens = preg_split( '/[\s\r\n\t]+/u', $text );
		if ( ! is_array( $tokens ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'trim', $tokens ),
				static fn( $token ): bool => '' !== $token
			)
		);
	}

	/**
	 * Find starting positions of a phrase in the token stream.
	 *
	 * @param array<int, string> $tokens Word tokens.
	 * @param string             $phrase Phrase to search.
	 *
	 * @return array<int, int>
	 */
	private function find_phrase_positions( array $tokens, string $phrase ): array {
		$phrase = trim( $phrase );
		if ( '' === $phrase ) {
			return array();
		}

		$phrase_tokens = $this->tokenize_words( $phrase );
		if ( empty( $phrase_tokens ) ) {
			return array();
		}

		$positions  = array();
		$needle_len = count( $phrase_tokens );
		$hay_len    = count( $tokens );

		for ( $i = 0; $i <= $hay_len - $needle_len; ++$i ) {
			$match = true;
			for ( $j = 0; $j < $needle_len; ++$j ) {
				if ( 0 !== strcasecmp( $tokens[ $i + $j ], $phrase_tokens[ $j ] ) ) {
					$match = false;
					break;
				}
			}

			if ( $match ) {
				$positions[] = $i;
			}
		}

		return $positions;
	}

	/**
	 * Find positions of a phrase in a character stream (CJK-friendly).
	 *
	 * @param string $text   Text with whitespace removed.
	 * @param string $phrase Phrase to search.
	 *
	 * @return array<int, int>
	 */
	private function find_phrase_positions_chars( string $text, string $phrase ): array {
		$phrase = preg_replace( '/[\s\r\n\t]+/u', '', $phrase );
		if ( null === $phrase || '' === $phrase ) {
			return array();
		}

		$positions  = array();
		$offset     = 0;
		$needle_len = mb_strlen( $phrase );

		if ( 0 === $needle_len ) {
			return array();
		}

		$pos = mb_stripos( $text, $phrase, $offset );
		while ( false !== $pos ) {
			$positions[] = (int) $pos;
			$offset      = $pos + $needle_len;
			$pos         = mb_stripos( $text, $phrase, $offset );
		}

		return $positions;
	}

	/**
	 * Normalize a hostname for comparisons.
	 *
	 * @param string $host Raw host value.
	 */
	private function normalize_host( string $host ): string {
		$host = strtolower( trim( $host ) );

		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * Count links by type.
	 *
	 * @param string $type Link classification type.
	 */
	private function count_links_by_type( string $type ): int {
		$count = 0;
		foreach ( $this->links as $link ) {
			if ( $type === $link['type'] ) {
				++$count;
			}
		}

		return $count;
	}
}
