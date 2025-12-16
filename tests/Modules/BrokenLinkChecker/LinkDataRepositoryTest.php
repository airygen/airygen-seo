<?php
/**
 * Tests for Broken Link Checker link candidate selection.
 *
 * @package AirygenTest\Modules\BrokenLinkChecker
 */

declare(strict_types=1);

namespace AirygenTest\Modules\BrokenLinkChecker;

use Airygen\Constants;
use Airygen\Modules\BrokenLinkChecker\Domain\LinkDataRepository;
use Airygen\Support\Database\WpDbAdapter;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\BrokenLinkChecker\Domain\LinkDataRepository
 */
class LinkDataRepositoryTest extends BaseTestCase {

	/**
	 * Database adapter.
	 *
	 * @var WpDbAdapter
	 */
	private $db;

	/**
	 * Link counter data table.
	 *
	 * @var string
	 */
	private $table;

	protected function setUp(): void {
		parent::setUp();

		$this->db    = new WpDbAdapter();
		$this->table = $this->db->table( Constants::TABLE_LINK_COUNTER_DATA );
		$this->db->query( sprintf( 'TRUNCATE TABLE %s', $this->table ) );
	}

	/**
	 * @covers ::get_candidates
	 */
	public function test_get_candidates_respects_external_type_filter_for_pending_rows(): void {
		$this->insert_candidate(
			array(
				'url'                    => 'http://example.test/internal-a',
				'type'                   => 'internal',
				'status_check'           => 0,
				'last_status_checked_at' => null,
			)
		);

		$this->insert_candidate(
			array(
				'url'                    => 'http://example.test/external-a',
				'type'                   => 'external',
				'status_check'           => 0,
				'last_status_checked_at' => null,
			)
		);

		$this->insert_candidate(
			array(
				'url'                    => 'http://example.test/internal-b',
				'type'                   => 'internal',
				'status_check'           => 2,
				'last_status_checked_at' => gmdate( 'Y-m-d H:i:s', time() - ( 2 * HOUR_IN_SECONDS ) ),
			)
		);

		$repository = new LinkDataRepository( $this->db );
		$results    = $repository->get_candidates( 20, 60, array( 'external' ) );

		$this->assertCount( 1, $results );
		$this->assertSame( 'http://example.test/external-a', $results[0]['url'] );
	}

	/**
	 * Insert a candidate row into link counter data table.
	 *
	 * @param array<string,mixed> $row Candidate values.
	 * @return void
	 */
	private function insert_candidate( array $row ): void {
		$this->db->insert(
			$this->table,
			array(
				'url'                    => $row['url'],
				'post_id'                => 1,
				'target_post_id'         => 0,
				'type'                   => $row['type'],
				'status_check'           => $row['status_check'],
				'last_status_checked_at' => $row['last_status_checked_at'],
			),
			array( '%s', '%d', '%d', '%s', '%d', '%s' )
		);
	}
}
