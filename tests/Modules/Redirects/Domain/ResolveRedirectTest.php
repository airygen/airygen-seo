<?php
/**
 * Tests for the redirect resolver service.
 *
 * @package AirygenTest\Modules\Redirects\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\Redirects\Domain;

use Airygen\Modules\Redirects\Domain\Service\ResolveRedirect;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\Redirects\Domain\Service\ResolveRedirect
 */
class ResolveRedirectTest extends TestCase {

	/**
	 * Exact matches should take priority over wildcard candidates.
	 *
	 * @return void
	 */
	public function test_exact_rules_take_priority(): void {
		$match = ResolveRedirect::from_path(
			'/old-page',
			array(
				array(
					'enabled' => true,
					'type'    => 'wildcard',
					'source'  => '/old-*',
					'target'  => '/wildcard',
				),
				array(
					'enabled' => true,
					'type'    => 'exact',
					'source'  => '/old-page',
					'target'  => '/exact',
					'status'  => 301,
				),
			)
		);

		$this->assertNotNull( $match );
		$this->assertSame( '/exact', $match->get_target() );
		$this->assertSame( 301, $match->get_status() );
	}

	/**
	 * Wildcard rules should replace asterisk tokens with the matched value.
	 *
	 * @return void
	 */
	public function test_wildcard_rules_replace_asterisk(): void {
		$match = ResolveRedirect::from_path(
			'/docs/getting-started/page',
			array(
				array(
					'enabled' => true,
					'type'    => 'wildcard',
					'source'  => '/docs/*/page',
					'target'  => '/guide/*/page',
				),
			)
		);

		$this->assertNotNull( $match );
		$this->assertSame( '/guide/getting-started/page', $match->get_target() );
	}

	/**
	 * Regex rules should respect capture replacements.
	 *
	 * @return void
	 */
	public function test_regex_rules_support_captures(): void {
		$match = ResolveRedirect::from_path(
			'/products/12345/',
			array(
				array(
					'enabled' => true,
					'type'    => 'regex',
					'source'  => '#^/products/(\d+)/?$#',
					'target'  => '/shop/$1',
					'status'  => 302,
				),
			)
		);

		$this->assertNotNull( $match );
		$this->assertSame( '/shop/12345', $match->get_target() );
		$this->assertSame( 302, $match->get_status() );
	}

	/**
	 * If no rules match the resolver should return null.
	 *
	 * @return void
	 */
	public function test_returns_null_when_no_rules_match(): void {
		$this->assertNull(
			ResolveRedirect::from_path(
				'/no-match',
				array(
					array(
						'enabled' => false,
						'type'    => 'exact',
						'source'  => '/no-match',
						'target'  => '/disabled',
					),
				)
			)
		);
	}
}
