<?php
/**
 * Integration tests for the breadcrumb trail builder.
 *
 * @package AirygenTest\Modules\Breadcrumbs\Public
 */

declare(strict_types=1);

namespace AirygenTest\Modules\Breadcrumbs\Public;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\Breadcrumbs\Admin\Settings as BreadcrumbSettings;
use Airygen\Modules\Breadcrumbs\Public\TrailBuilder;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\Breadcrumbs\Public\TrailBuilder
 */
final class TrailBuilderTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		$modules                = ModuleSettings::get();
		$modules['breadcrumbs'] = true;
		ModuleSettings::update( $modules );
		BreadcrumbSettings::update(
			array(
				'enabled' => true,
				'home'    => array(
					'display' => true,
					'label'   => 'Home',
					'url'     => trailingslashit( home_url() ),
				),
				'display' => array(
					'showCurrent'   => true,
					'showAncestors' => true,
					'showBlog'      => false,
				),
			)
		);
	}

	public function test_builds_trail_for_hierarchical_pages(): void {
		$parent = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent',
			)
		);

		$child = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Child',
				'post_parent' => $parent,
			)
		);

		$this->go_to( get_permalink( $child ) );

		$trail = TrailBuilder::from_current_query();
		$this->assertNotNull( $trail );

		$items = $trail->items();
		$this->assertCount( 3, $items );
		$this->assertSame( 'Home', $items[0]['label'] );
		$this->assertSame( 'Parent', $items[1]['label'] );
		$this->assertSame( 'Child', $items[2]['label'] );
	}

	public function test_respects_show_current_toggle(): void {
		BreadcrumbSettings::update(
			array(
				'display' => array(
					'showCurrent'   => false,
					'showAncestors' => true,
				),
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Landing',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$trail = TrailBuilder::from_current_query();
		$this->assertNotNull( $trail );

		$items = $trail->items();
		$this->assertCount( 1, $items );
		$this->assertSame( 'Home', $items[0]['label'] );
	}

	public function test_service_singular_trail_includes_archive_and_current_item(): void {
		register_post_type(
			'service',
			array(
				'public'       => true,
				'label'        => 'Services',
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'services' ),
				'show_in_rest' => true,
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_type'  => 'service',
				'post_title' => 'Emergency plumbing',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$trail = TrailBuilder::from_current_query();
		$this->assertNotNull( $trail );

		$items = $trail->items();
		$this->assertCount( 3, $items );
		$this->assertSame( 'Home', $items[0]['label'] );
		$this->assertSame( 'Services', $items[1]['label'] );
		$this->assertSame( get_post_type_archive_link( 'service' ), $items[1]['url'] );
		$this->assertSame( 'Emergency plumbing', $items[2]['label'] );

		unregister_post_type( 'service' );
	}
}
