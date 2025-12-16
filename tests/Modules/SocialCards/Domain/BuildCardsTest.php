<?php
/**
 * Tests for the Social Cards builder.
 *
 * @package AirygenTest\Modules\SocialCards\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\SocialCards\Domain;

use Airygen\Modules\SocialCards\Domain\Service\BuildCards;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\SocialCards\Domain\Service\BuildCards
 */
class BuildCardsTest extends TestCase {

	/**
	 * When both features are disabled the DTO should return null payloads.
	 *
	 * @return void
	 */
	public function test_returns_empty_cards_when_disabled(): void {
		$cards = BuildCards::for_post( array() );

		$this->assertNull( $cards->get_open_graph() );
		$this->assertNull( $cards->get_twitter() );
	}

	/**
	 * Open Graph inputs should be normalized and retain optional dimensions.
	 *
	 * @return void
	 */
	public function test_builds_open_graph_payload(): void {
		$cards = BuildCards::for_post(
			array(
				'og_enabled' => true,
				'og'         => array(
					'title'       => 'Post Title',
					'description' => 'Post Description',
					'url'         => 'https://example.com/post',
					'type'        => '',
					'image'       => array(
						'url'    => 'https://example.com/image.jpg',
						'width'  => '1200',
						'height' => '630',
					),
					'site_name'   => 'Example',
					'fb_app_id'   => '123',
					'fb_admins'   => '1,2',
				),
			)
		);

		$og = $cards->get_open_graph();
		$this->assertNotNull( $og );
		$tags = $og->to_tags();
		$this->assertSame( 'Post Title', $tags['og:title'] );
		$this->assertSame( 'Post Description', $tags['og:description'] );
		$this->assertSame( 'https://example.com/post', $tags['og:url'] );
		$this->assertSame( 'article', $tags['og:type'] );
		$this->assertSame( 'https://example.com/image.jpg', $tags['og:image'] ?? null );
		$this->assertSame( '1200', $tags['og:image:width'] );
		$this->assertSame( '630', $tags['og:image:height'] );
		$this->assertSame( 'Example', $tags['og:site_name'] );
		$this->assertSame( '123', $tags['fb:app_id'] );
		$this->assertSame( '1,2', $tags['fb:admins'] );
	}

	/**
	 * Twitter inputs should inherit OG defaults and downgrade summary cards without an image.
	 *
	 * @return void
	 */
	public function test_builds_twitter_payload_with_downgraded_card_type(): void {
		$cards = BuildCards::for_post(
			array(
				'og_enabled'      => true,
				'og'              => array(
					'title'       => 'OG Title',
					'description' => 'OG Description',
					'url'         => 'https://example.com/post',
				),
				'twitter_enabled' => true,
				'twitter'         => array(
					'card_type'      => 'summary_large_image',
					'site_handle'    => 'AirygenHQ',
					'creator_handle' => '@writer',
				),
			)
		);

		$twitter = $cards->get_twitter();
		$this->assertNotNull( $twitter );
		$this->assertSame( 'summary', $twitter->get_card_type() );
		$this->assertSame( 'OG Title', $twitter->get_title() );
		$this->assertSame( 'OG Description', $twitter->get_description() );
		$this->assertSame( 'https://example.com/post', $twitter->get_url() );
		$this->assertNull( $twitter->get_image() );

		$this->assertSame(
			array(
				'twitter:card'        => 'summary',
				'twitter:title'       => 'OG Title',
				'twitter:description' => 'OG Description',
				'twitter:url'         => 'https://example.com/post',
				'twitter:site'        => '@AirygenHQ',
				'twitter:creator'     => '@writer',
			),
			$twitter->to_tags()
		);
	}
}
