<?php
/**
 * Tests for llms.txt public output behavior.
 *
 * @package AirygenTest\Modules\LlmsTxt\Public
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LlmsTxt\Public;

use Airygen\Modules\LlmsTxt\Public\Hooks;
use AirygenTest\BaseTestCase;
use ReflectionMethod;

/**
 * @covers \Airygen\Modules\LlmsTxt\Public\Hooks
 */
final class HooksTest extends BaseTestCase {

	private string $original_home         = '';
	private ?string $original_request_uri = null;

	public function set_up(): void {
		parent::set_up();
		$this->original_home        = (string) get_option( 'home', '' );
		$this->original_request_uri = isset( $_SERVER['REQUEST_URI'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
		: null;
	}

	public function tear_down(): void {
		update_option( 'home', $this->original_home, 'no' );
		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}
		parent::tear_down();
	}

	/**
	 * Extension custom declaration should override the root agent_note.
	 *
	 * @return void
	 */
	public function test_build_extension_content_uses_extension_custom_declaration_as_agent_note(): void {
		$settings = array(
			'enabled'                    => true,
			'custom_declaration'         => 'Root note',
			'post_types'                 => array( 'post' ),
			'exclude_noindex'            => false,
			'exclude_password_protected' => false,
			'min_word_count'             => 0,
			'sections'                   => array(),
			'extensions'                 => array(
				array(
					'id'                 => 'ext_1',
					'title'              => 'Extension Title',
					'description'        => 'Extension description',
					'custom_declaration' => 'Extension note',
					'path'               => 'placeholder1',
					'filename'           => 'llms-small.txt',
					'enabled'            => true,
					'sections'           => array(),
				),
			),
		);

		$content = Hooks::build_extension_content( $settings, 'ext_1' );

		$this->assertStringContainsString( 'agent_note: Extension note', $content );
		$this->assertStringNotContainsString( 'agent_note: Root note', $content );
	}

	/**
	 * Empty extension custom declaration should not fall back to the root agent_note.
	 *
	 * @return void
	 */
	public function test_build_extension_content_omits_agent_note_when_extension_custom_declaration_is_empty(): void {
		$settings = array(
			'enabled'                    => true,
			'custom_declaration'         => 'Root note',
			'post_types'                 => array( 'post' ),
			'exclude_noindex'            => false,
			'exclude_password_protected' => false,
			'min_word_count'             => 0,
			'sections'                   => array(),
			'extensions'                 => array(
				array(
					'id'                 => 'ext_1',
					'title'              => 'Extension Title',
					'description'        => 'Extension description',
					'custom_declaration' => '',
					'path'               => 'placeholder1',
					'filename'           => 'llms-small.txt',
					'enabled'            => true,
					'sections'           => array(),
				),
			),
		);

		$content = Hooks::build_extension_content( $settings, 'ext_1' );

		$this->assertStringNotContainsString( 'agent_note:', $content );
	}

	/**
	 * Multisite-style subdirectory paths should normalize to site-relative paths.
	 *
	 * @return void
	 */
	public function test_normalize_site_relative_path_strips_site_prefix(): void {
		update_option( 'home', 'http://example.org/en', 'no' );

		$method = new ReflectionMethod( Hooks::class, 'normalize_site_relative_path' );
		$method->setAccessible( true );

		$normalized = $method->invoke( null, '/en/placeholder2/llms.txt' );

		$this->assertSame( '/placeholder2/llms.txt', $normalized );
	}

	/**
	 * Current request path should normalize a multisite-style subdirectory prefix.
	 *
	 * @return void
	 */
	public function test_current_request_path_strips_site_prefix_from_request_uri(): void {
		update_option( 'home', 'http://example.org/en', 'no' );
		$_SERVER['REQUEST_URI'] = '/en/placeholder2/llms.txt';

		$method = new ReflectionMethod( Hooks::class, 'current_request_path' );
		$method->setAccessible( true );

		$this->assertSame( '/placeholder2/llms.txt', $method->invoke( null ) );
	}
}
