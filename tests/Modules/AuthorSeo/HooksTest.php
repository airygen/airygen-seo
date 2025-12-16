<?php
/**
 * Tests for Author SEO public hooks.
 *
 * @package AirygenTest\Modules\AuthorSeo
 */

declare(strict_types=1);

namespace AirygenTest\Modules\AuthorSeo;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\AuthorSeo\Public\Hooks;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\AuthorSeo\Public\Hooks
 */
class HooksTest extends BaseTestCase {

	/**
	 * Author-specific user meta should override global social profiles in schema output.
	 *
	 * @return void
	 */
	public function test_emit_head_uses_author_social_profiles_when_present(): void {
		ModuleSettings::update(
			array(
				'schema' => false,
			)
		);

		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'Author One',
				'user_nicename' => 'author-one',
				'description'   => 'Author bio',
			)
		);

		update_option(
			'airygen_author_seo',
			array(
				'enabled'              => true,
				'title_template'       => '%author_name% | %site_name%',
				'description_template' => '%author_bio%',
				'social_profiles'      => array( 'https://x.com/global_profile' ),
			)
		);

		update_user_meta(
			$user_id,
			'_airygen_social_profiles',
			array(
				'https://x.com/author_profile',
				'https://linkedin.com/in/author-profile',
			)
		);

		$this->go_to( get_author_posts_url( $user_id ) );

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'https:\/\/x.com\/author_profile', $output );
		$this->assertStringContainsString( 'https:\/\/linkedin.com\/in\/author-profile', $output );
		$this->assertStringNotContainsString( 'https:\/\/x.com\/global_profile', $output );
	}

	/**
	 * Global social profiles should be used when author meta is empty.
	 *
	 * @return void
	 */
	public function test_emit_head_falls_back_to_global_social_profiles(): void {
		ModuleSettings::update(
			array(
				'schema' => false,
			)
		);

		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'Author Two',
				'user_nicename' => 'author-two',
				'description'   => 'Author two bio',
			)
		);

		update_option(
			'airygen_author_seo',
			array(
				'enabled'              => true,
				'title_template'       => '%author_name% | %site_name%',
				'description_template' => '%author_bio%',
				'social_profiles'      => array(
					'https://x.com/global_profile',
					'https://facebook.com/global.profile',
				),
			)
		);

		delete_user_meta( $user_id, '_airygen_social_profiles' );

		$this->go_to( get_author_posts_url( $user_id ) );

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'https:\/\/x.com\/global_profile', $output );
		$this->assertStringContainsString( 'https:\/\/facebook.com\/global.profile', $output );
	}
}
