<?php
/**
 * Tests for breadcrumb settings storage.
 *
 * @package AirygenTest\Modules\Breadcrumbs\Admin
 */

declare(strict_types=1);

namespace AirygenTest\Modules\Breadcrumbs\Admin;

use Airygen\Modules\Breadcrumbs\Admin\Settings;
use AirygenTest\BaseTestCase;
use function home_url;
use function trailingslashit;

/**
 * @covers \Airygen\Modules\Breadcrumbs\Admin\Settings
 */
final class SettingsTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_breadcrumbs' );
	}

	public function tear_down(): void {
		delete_option( 'airygen_breadcrumbs' );
		parent::tear_down();
	}

	public function test_defaults_seed_correctly(): void {
		Settings::ensure_exists();

		$config = Settings::get();

		$this->assertTrue( $config['manual_output_enabled'] );
		$this->assertTrue( $config['auto_injection_enabled'] );
		$this->assertSame( 'before_content', $config['injection_position'] );
		$this->assertSame( '›', $config['separator'] );
		$this->assertSame( '', $config['prefix'] );
		$this->assertTrue( $config['home']['display'] );
		$this->assertNotEmpty( $config['home']['url'] );
		$this->assertSame( 'Archives for %s', $config['labels']['archive'] );
		$this->assertTrue( $config['display']['showCurrent'] );
		$this->assertFalse( $config['display']['showAncestors'] );
	}

	public function test_update_sanitizes_payload(): void {
		Settings::update(
			array(
				'manual_output_enabled'  => false,
				'auto_injection_enabled' => true,
				'injection_position'     => 'after_content',
				'separator'              => '',
				'prefix'                 => '  You are here: ',
				'home'                   => array(
					'display' => false,
					'label'   => ' Start ',
					'url'     => 'https://example.com/start',
				),
				'labels'                 => array(
					'archive' => 'Archive: %s',
					'search'  => 'Search – %s',
					'error'   => 'Not found',
				),
				'display'                => array(
					'showCurrent'    => false,
					'showAncestors'  => true,
					'showBlog'       => true,
					'showPagination' => false,
					'hideTaxonomy'   => true,
				),
			)
		);

		$config = Settings::get();

		$this->assertFalse( $config['manual_output_enabled'] );
		$this->assertTrue( $config['auto_injection_enabled'] );
		$this->assertSame( 'after_content', $config['injection_position'] );
		$this->assertSame( '›', $config['separator'] );
		$this->assertSame( 'You are here:', $config['prefix'] );
		$this->assertFalse( $config['home']['display'] );
		$this->assertSame( 'Start', $config['home']['label'] );
		$this->assertSame( trailingslashit( home_url() ), $config['home']['url'] );
		$this->assertSame( 'Archive: %s', $config['labels']['archive'] );
		$this->assertFalse( $config['display']['showCurrent'] );
		$this->assertTrue( $config['display']['showAncestors'] );
		$this->assertTrue( $config['display']['showBlog'] );
		$this->assertFalse( $config['display']['showPagination'] );
		$this->assertTrue( $config['display']['hideTaxonomy'] );
	}
}
