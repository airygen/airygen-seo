<?php
/**
 * DocumentContext unit tests.
 *
 * @package AirygenTest\Modules\ScoreCalculator\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\ScoreCalculator\Domain;

use Airygen\Modules\ScoreCalculator\Domain\DocumentContext;
use Airygen\Modules\ScoreCalculator\Domain\TitlePixelEstimator;
use AirygenTest\Support\ScoreContextFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\ScoreCalculator\Domain\DocumentContext
 */
class DocumentContextTest extends TestCase {

	/**
	 * Ensure heading metrics and keyword density are computed.
	 *
	 * @return void
	 */
	public function test_parses_heading_and_keyword_metrics(): void {
		$context = $this->make_context();

		$this->assertSame( 0, $context->get_h1_count() );
		$this->assertSame( 2, $context->get_subhead_count() );
		$this->assertSame( 100.0, $context->get_subhead_focus_percent() );
		$this->assertGreaterThan( 0.0, $context->get_keyword_density() );
		$this->assertTrue( $context->has_focus_in_subheads_context() );
	}

	/**
	 * Focus related boolean helpers should reflect the payload.
	 *
	 * @return void
	 */
	public function test_detects_focus_presence(): void {
		$context = $this->make_context();

		$this->assertTrue( $context->has_focus_keyphrase() );
		$this->assertTrue( $context->is_meta_description_contains_focus() );
		$this->assertTrue( $context->is_title_contains_focus() );
		$this->assertTrue( $context->is_intro_contains_focus() );
		$this->assertTrue( $context->is_snippet_unique() );
		$this->assertTrue( $context->is_meta_description_present() );
	}

	/**
	 * Images and links should be parsed from the HTML content.
	 *
	 * @return void
	 */
	public function test_image_and_link_metrics(): void {
		$context = $this->make_context();

		$this->assertSame( 2, $context->get_image_count() );
		$this->assertFalse( $context->is_all_images_have_alt() );
		$this->assertTrue( $context->is_any_image_alt_has_focus() );

		$this->assertSame( 1, $context->get_internal_links_count() );
		$this->assertSame( 2, $context->get_external_links_count() );
		$this->assertFalse( $context->is_rel_compliance() );
	}

	/**
	 * Slug and canonical validation should consider normalization.
	 *
	 * @return void
	 */
	public function test_slug_and_canonical_validation(): void {
		$context = $this->make_context();

		$this->assertSame( 3, $context->get_slug_word_count() );
		$this->assertTrue( $context->is_slug_contains_focus() );
		$this->assertTrue( $context->is_canonical_valid() );
		$this->assertTrue( $context->get_boolean_metric( 'canonical_valid' ) );
	}

	/**
	 * JSON-LD discovery flags should be surfaced.
	 *
	 * @return void
	 */
	public function test_jsonld_flags(): void {
		$context = $this->make_context();

		$this->assertTrue( $context->is_jsonld_article_present() );
		$this->assertTrue( $context->is_jsonld_breadcrumb_present() );
	}

	/**
	 * Numeric proxies should forward to the underlying metrics.
	 *
	 * @return void
	 */
	public function test_numeric_metric_proxy_and_pixel_length(): void {
		$context       = $this->make_context();
		$title_pixels  = TitlePixelEstimator::estimate( $context->get_title() );
		$description   = $context->get_description();
		$desc_length   = mb_strlen( (string) $description );
		$metric_length = $context->get_numeric_metric( 'meta_description_length' );

		$this->assertSame( $title_pixels, $context->get_title_length_px() );
		$this->assertSame( (float) $title_pixels, $context->get_numeric_metric( 'title_length_px' ) );
		$this->assertSame( (float) $desc_length, $metric_length );
		$this->assertSame( 0.0, $context->get_numeric_metric( 'non_existent' ) );
	}

	/**
	 * Boolean proxy should default to false for unknown keys.
	 *
	 * @return void
	 */
	public function test_boolean_metric_defaults_to_false(): void {
		$context = $this->make_context();

		$this->assertFalse( $context->get_boolean_metric( 'non_existent' ) );
	}

	/**
	 * Rel compliance should pass when every external link is secured.
	 *
	 * @return void
	 */
	public function test_rel_compliance_pass_condition(): void {
		$context = $this->make_context(
			array(
				'content' => $this->secure_link_content(),
			)
		);

		$this->assertTrue( $context->is_rel_compliance() );
	}

	/**
	 * Canonical hosts must match the configured site host.
	 *
	 * @return void
	 */
	public function test_canonical_invalid_when_hosts_differ(): void {
		$context = $this->make_context(
			array(
				'canonical' => 'https://othersite.example/test',
			)
		);

		$this->assertFalse( $context->is_canonical_valid() );
	}

	/**
	 * Word count helper should report the computed totals.
	 *
	 * @return void
	 */
	public function test_word_count_computation(): void {
		$context = $this->make_context();

		$this->assertGreaterThan( 50, $context->get_word_count() );
	}

	/**
	 * Helper for building document context instances.
	 *
	 * @param array<string,mixed> $overrides Optional overrides.
	 */
	private function make_context( array $overrides = array() ): DocumentContext {
		return ScoreContextFactory::make( $overrides );
	}

	/**
	 * Content fixture where every external link declares noopener/noreferrer.
	 */
	private function secure_link_content(): string {
		return <<<'HTML'
<h1>Focus Title Example</h1>
<p>Focus rich text that maintains more than enough words for scoring.</p>
<h2>Focus Heading</h2>
<h3>Another Focus Heading</h3>
<img src="hero.jpg" alt="Focus hero shot" />
<img src="detail.jpg" alt="Focus detail shot" />
<a href="https://example.net/secure" rel="noopener">External</a>
<a href="https://example.org/secure" rel="noreferrer">External Two</a>
<a href="/internal-link">Internal</a>
<script type="application/ld+json">{"@type":"Article"}</script>
<script type="application/ld+json">{"@type":"BreadcrumbList"}</script>
HTML;
	}
}
