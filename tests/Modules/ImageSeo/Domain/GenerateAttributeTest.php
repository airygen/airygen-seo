<?php
/**
 * Tests for the Image SEO attribute generator.
 *
 * @package AirygenTest\Modules\ImageSeo\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\ImageSeo\Domain;

use Airygen\Modules\ImageSeo\Domain\Dto\ImageContext;
use Airygen\Modules\ImageSeo\Domain\Service\GenerateAttribute;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\ImageSeo\Domain\Service\GenerateAttribute
 */
class GenerateAttributeTest extends TestCase {

	/**
	 * Ensure core tokens are replaced as expected.
	 *
	 * @return void
	 */
	public function test_generates_attribute_from_tokens(): void {
		$generator = new GenerateAttribute();
		$context   = new ImageContext( 'Test Post', 'example image', 'Attachment Title' );

		$result = $generator->generate( '%title% - %filename% (%image_title%)', $context );

		$this->assertSame( 'Test Post - example image (Attachment Title)', $result );
	}

	/**
	 * Ensure counters are tracked per token key.
	 *
	 * @return void
	 */
	public function test_counters_increment_per_key(): void {
		$generator = new GenerateAttribute();
		$context   = new ImageContext( 'Sample', 'file' );

		$first  = $generator->generate( '%title% %counter%', $context );
		$second = $generator->generate( '%title% %counter%', $context );
		$third  = $generator->generate( '%filename% %counter%', $context );

		$this->assertSame( 'Sample 1', $first );
		$this->assertSame( 'Sample 2', $second );
		$this->assertSame( 'file 3', $third );
	}

	/**
	 * Tokens that cannot be resolved should be removed.
	 *
	 * @return void
	 */
	public function test_unknown_tokens_removed(): void {
		$generator = new GenerateAttribute();
		$context   = new ImageContext( 'Demo', '' );

		$result = $generator->generate( 'Image %unknown% %counter% %counter% ', $context );

		$this->assertSame( 'Image 1 2', $result );
	}
}
