<?php
/**
 * Helper factory for creating ScoreCalculator document contexts in tests.
 *
 * @package AirygenTest\Support
 */

declare(strict_types=1);

namespace AirygenTest\Support;

use Airygen\Modules\ScoreCalculator\Domain\DocumentContext;

/**
 * Provides reusable context fixtures for ScoreCalculator unit tests.
 */
final class ScoreContextFactory {

	/**
	 * Build a document context with optional overrides.
	 *
	 * @param array<string, mixed> $overrides Override values for the base payload.
	 */
	public static function make( array $overrides = array() ): DocumentContext {
		$data = array_merge(
			array(
				'title'                => 'Focus Title Example',
				'description'          => 'Focus meta description keeps snippets unique.',
				'content'              => self::default_content(),
				'focus_keyphrase'      => 'Focus',
				'slug'                 => 'focus-title-example',
				'canonical'            => 'https://example.com/focus-title-example',
				'permalink'            => 'https://example.com/focus-title-example',
				'site_host'            => 'example.com',
				'core_web_vitals_good' => true,
			),
			$overrides
		);

		return DocumentContext::from_array( $data );
	}

	/**
	 * Default HTML fixture that exercises most metrics.
	 */
	private static function default_content(): string {
		return <<<'HTML'
<p>Focus introduction sentence. Focus keyword appears early to highlight focus. Another sentence extends the introduction to ensure we have enough words within the intro paragraph. Focus remains central to the page.</p>
<h2>Focus Tips Heading</h2>
<p>Focus friendly copy repeated multiple times to inflate word count and keep readability respectable. The focus topic should stay consistent. Additional filler describing context and readability measurement ensures the text surpasses fifty total words easily. Focus driven strategy remains important.</p>
<h3>Further Focus Insights</h3>
<p>Focus image alt text reference plus extra descriptive copy that balances readability. Another line referencing focus for density calculations and readability tests.</p>
<img src="hero.jpg" alt="Focus hero shot" />
<img src="blank.jpg" alt="" />
<a href="https://example.org/missing-rel">External Without Rel</a>
<a href="https://another.com/has-rel" rel="noopener noreferrer">External With Rel</a>
<a href="/internal-page">Internal Link</a>
<script type="application/ld+json">{"@type":"Article"}</script>
<script type="application/ld+json">{"@type":"BreadcrumbList"}</script>
HTML;
	}
}
