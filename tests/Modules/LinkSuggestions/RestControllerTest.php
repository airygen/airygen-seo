<?php
/**
 * REST tests for Related Posts controller.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\LinkSuggestions\Admin\Settings;
use Airygen\Modules\LinkSuggestions\Application\RecommendationService;
use Airygen\Modules\LinkSuggestions\Domain\SimilarityScorer;
use Airygen\Modules\LinkSuggestions\Persistence\LinkTermsRepository;
use Airygen\Modules\LinkSuggestions\Runtime\Jobs;
use Airygen\Constants;
use Airygen\Support\Meta\PostData;
use AirygenTest\BaseTestCase;
use AirygenTest\Support\DatabaseHelpers;
use WP_REST_Request;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Admin\RestController
 */
class RestControllerTest extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		DatabaseHelpers::truncate_custom_tables();
		ModuleSettings::ensure_exists();
		$modules                    = ModuleSettings::get();
		$modules['linkSuggestions'] = true;
		ModuleSettings::update( $modules );
		Settings::update(
			array(
				'enabled'            => true,
				'allowed_post_types' => array( 'post' ),
				'max_suggestions'    => 5,
			)
		);

		do_action( 'rest_api_init' );
	}

	/**
	 * @covers ::handle_get_suggestions
	 */
	public function test_handle_get_suggestions_returns_scores(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$current = self::factory()->post->create();
		$target  = self::factory()->post->create();

		$repo = new LinkTermsRepository();
		$repo->save_terms(
			$current,
			'post',
			array(
				'run' => 2.0,
				'seo' => 1.0,
			)
		);

		$repo->save_terms(
			$target,
			'post',
			array(
				'run' => 2.0,
			)
		);

		$request = new WP_REST_Request( 'GET', '/airygen/v1/link-suggestions/suggestions' );
		$request->set_param( 'post', $current );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $data['suggestions'] );
		$this->assertSame( $target, $data['suggestions'][0]['id'] );
	}

	/**
	 * @covers ::handle_get_suggestions
	 */
	public function test_mixed_language_posts_return_suggestions(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$content_base  = str_repeat( 'zebrafish ', 60 ) . '中文 內容';
		$content_base .= '<h2>Performance 效能</h2>';

		$current = self::factory()->post->create(
			array(
				'post_title'   => 'Zebrafish 中文',
				'post_content' => $content_base,
				'post_status'  => 'publish',
			)
		);
		$target  = self::factory()->post->create(
			array(
				'post_title'   => 'Zebrafish Guide 指南',
				'post_content' => $content_base . ' extra',
				'post_status'  => 'publish',
			)
		);
		$decoy   = self::factory()->post->create(
			array(
				'post_title'   => 'Unrelated Post',
				'post_content' => str_repeat( 'quasar ', 60 ),
				'post_status'  => 'publish',
			)
		);

		PostData::save(
			$current,
			array(
				'description'    => 'Zebrafish description 說明',
				'focusKeyphrase' => 'zebrafish',
			)
		);
		PostData::save(
			$target,
			array(
				'description'    => 'Zebrafish details 詳細',
				'focusKeyphrase' => 'zebrafish',
			)
		);
		PostData::save(
			$decoy,
			array(
				'description'    => 'Quasar description',
				'focusKeyphrase' => 'quasar',
			)
		);

		Jobs::handle_recompute( $current );
		Jobs::handle_recompute( $target );
		Jobs::handle_recompute( $decoy );

		$repository    = new LinkTermsRepository();
		$current_terms = $repository->get_terms_for_content( $current, 'post' );
		$target_terms  = $repository->get_terms_for_content( $target, 'post' );
		$this->assertNotEmpty( $current_terms );
		$this->assertNotEmpty( $target_terms );
		$this->assertArrayHasKey( 'zebrafish', $current_terms );
		$this->assertArrayHasKey( 'zebrafish', $target_terms );
		$this->assertNotEmpty( $repository->get_terms_for_content( $decoy, 'post' ) );

		$candidates = $repository->find_candidate_ids_by_stems(
			array_keys( $current_terms ),
			array( 'post' ),
			get_post_stati( array( 'public' => true ) ),
			1000
		);
		$this->assertContains( $target, $candidates );

		$service = new RecommendationService( $repository, new SimilarityScorer() );
		$scores  = $service->recommend(
			$current,
			'post',
			$current_terms,
			$candidates,
			'post',
			array( $current )
		);
		$this->assertNotEmpty( $scores );

		$request = new WP_REST_Request( 'GET', '/airygen/v1/link-suggestions/suggestions' );
		$request->set_param( 'post', $current );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		if ( empty( $data['suggestions'] ) ) {
			$this->fail( 'Reason: ' . ( $data['meta']['reason'] ?? 'unknown' ) );
		}
		$this->assertNotEmpty( $data['suggestions'] );
		$this->assertSame( $target, $data['suggestions'][0]['id'] );
	}
}
