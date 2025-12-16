<?php
/**
 * Tests for the Robots domain service.
 *
 * @package AirygenTest\Modules\Robots\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\Robots\Domain;

use Airygen\Modules\Robots\Domain\Service\BuildRobots;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\Robots\Domain\Service\BuildRobots
 */
class BuildRobotsTest extends TestCase {

	/**
	 * Per-post directives should override global defaults.
	 *
	 * @return void
	 */
	public function test_per_post_overrides_global(): void {
		$directives = BuildRobots::for_entry(
			array(
				'global'   => 'index,follow',
				'per_post' => 'noindex,nofollow',
			)
		);

		$this->assertSame( 'noindex,nofollow', $directives->get_meta_directive() );
		$this->assertFalse( $directives->should_suppress_default() );
	}

	/**
	 * Search, attachment, and 404 templates should default to noindex when unset.
	 *
	 * @return void
	 */
	public function test_special_templates_force_noindex(): void {
		$directives = BuildRobots::for_entry(
			array(
				'is_search' => true,
			)
		);

		$this->assertSame( 'noindex,follow', $directives->get_meta_directive() );
	}

	/**
	 * Suppress flag should flow through to the DTO.
	 *
	 * @return void
	 */
	public function test_suppress_flag_respected(): void {
		$directives = BuildRobots::for_entry(
			array(
				'global'           => 'noindex,follow',
				'suppress_default' => true,
			)
		);

		$this->assertTrue( $directives->should_suppress_default() );
	}

	/**
	 * Robots.txt rules should merge base and additional lines while trimming whitespace.
	 *
	 * @return void
	 */
	public function test_builds_robots_txt_rules(): void {
		$rules = BuildRobots::for_robots_txt(
			array(
				'base_rules'       => array( "User-agent: *\n", '' ),
				'additional_rules' => array( 'Disallow: /private', '   ' ),
			)
		);

		$this->assertSame(
			array(
				'User-agent: *',
				'Disallow: /private',
			),
			$rules
		);
	}
}
