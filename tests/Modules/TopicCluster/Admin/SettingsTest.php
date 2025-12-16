<?php
/**
 * Tests for Topic Cluster settings storage.
 *
 * @package AirygenTest\Modules\TopicCluster\Admin
 */

declare(strict_types=1);

namespace AirygenTest\Modules\TopicCluster\Admin;

use Airygen\Constants;
use Airygen\Modules\TopicCluster\Admin\Settings;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\TopicCluster\Admin\Settings
 */
final class SettingsTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( Constants::OPTION_TOPIC_CLUSTER );
	}

	public function tear_down(): void {
		delete_option( Constants::OPTION_TOPIC_CLUSTER );
		parent::tear_down();
	}

	public function test_defaults_seed_correctly(): void {
		Settings::ensure_exists();

		$config = Settings::get();

		$this->assertFalse( $config['manual_output_enabled'] );
		$this->assertFalse( $config['auto_injection_enabled'] );
		$this->assertFalse( $config['override_breadcrumbs'] );
		$this->assertFalse( $config['override_wp_adjacent'] );
		$this->assertSame( 'after-content', $config['insert_position'] );
		$this->assertSame( array( 'post' ), $config['post_types'] );
	}

	public function test_update_sanitizes_payload(): void {
		Settings::update(
			array(
				'manual_output_enabled'  => true,
				'auto_injection_enabled' => true,
				'override_breadcrumbs'   => true,
				'override_wp_adjacent'   => true,
				'insert_position'        => 'before-content',
				'post_types'             => array( 'post', 'page', 'invalid-type' ),
			)
		);

		$config = Settings::get();

		$this->assertTrue( $config['manual_output_enabled'] );
		$this->assertTrue( $config['auto_injection_enabled'] );
		$this->assertTrue( $config['override_breadcrumbs'] );
		$this->assertTrue( $config['override_wp_adjacent'] );
		$this->assertSame( 'before-content', $config['insert_position'] );
		$this->assertSame( array( 'post', 'page' ), $config['post_types'] );
	}
}
