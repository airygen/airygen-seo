<?php
/**
 * Provides metadata for supported IndexNow engines.
 *
 * @package Airygen\Modules\InstantIndexing\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides metadata for built-in IndexNow engines.
 */
final class EngineRegistry {

	/**
	 * Static map of supported engines.
	 *
	 * @var array<string, array{label: string, endpoint: string}>
	 */
	private const ENGINES = array(
		'bing'   => array(
			'label'    => 'Microsoft Bing',
			'endpoint' => 'https://www.bing.com/indexnow',
		),
		'yandex' => array(
			'label'    => 'Yandex',
			'endpoint' => 'https://yandex.com/indexnow',
		),
		'seznam' => array(
			'label'    => 'Seznam.cz',
			'endpoint' => 'https://search.seznam.cz/indexnow',
		),
		'naver'  => array(
			'label'    => 'Naver',
			'endpoint' => 'https://www.naver.com/indexnow',
		),
		'yep'    => array(
			'label'    => 'Yep (Ahrefs)',
			'endpoint' => 'https://yep.com/indexnow',
		),
	);

	/**
	 * Retrieve engine descriptors as value objects.
	 *
	 * @return array<string, Engine>
	 */
	public static function all(): array {
		$engines = array();

		foreach ( self::ENGINES as $slug => $data ) {
			$engines[ $slug ] = new Engine( $slug, $data['label'], $data['endpoint'] );
		}

		return $engines;
	}

	/**
	 * Fetch engine descriptor by slug.
	 *
	 * @param string $slug Engine identifier.
	 * @return Engine|null
	 */
	public static function get( string $slug ): ?Engine {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}

	/**
	 * Default endpoint map keyed by slug.
	 *
	 * @return array<string, string>
	 */
	public static function default_endpoints(): array {
		$defaults = array();

		foreach ( self::ENGINES as $slug => $data ) {
			$defaults[ $slug ] = $data['endpoint'];
		}

		return $defaults;
	}
}
