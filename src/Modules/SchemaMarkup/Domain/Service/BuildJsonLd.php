<?php
/**
 * Assembles JSON-LD graph data.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Service
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\SchemaMarkup\Domain\Builder\ArticleBuilder;
use Airygen\Modules\SchemaMarkup\Domain\Builder\BreadcrumbBuilder;
use Airygen\Modules\SchemaMarkup\Domain\Builder\OrganizationBuilder;
use Airygen\Modules\SchemaMarkup\Domain\Builder\WebPageBuilder;
use Airygen\Modules\SchemaMarkup\Domain\Builder\WebsiteBuilder;
use Airygen\Modules\SchemaMarkup\Domain\Policy\EligibilityPolicy;

/**
 * Builds JSON-LD structures for front-end output.
 */
final class BuildJsonLd {

	/**
	 * Build JSON-LD graph from normalized context.
	 *
	 * @param array<string, mixed> $context Normalized context data.
	 *
	 * @return array<string, mixed>
	 */
	public static function from_context( array $context ): array {
		$graph = array();

		$organization    = OrganizationBuilder::build( $context['organization'] ?? array() );
		$organization_id = self::string_or_null( is_array( $organization ) ? ( $organization['@id'] ?? null ) : null );
		if ( null !== $organization ) {
			$graph[] = $organization;
		}

		$website    = WebsiteBuilder::build( $context['website'] ?? array() );
		$website_id = self::string_or_null( is_array( $website ) ? ( $website['@id'] ?? null ) : null );
		if ( null !== $website ) {
			$graph[] = $website;
		}

		$webpage = WebPageBuilder::build( $context['webpage'] ?? array() );
		if ( null !== $webpage ) {
			if ( null !== $website_id ) {
				$webpage['isPartOf'] = array(
					'@id' => $website_id,
				);
			}
		}

		$breadcrumbs = BreadcrumbBuilder::build( $context['breadcrumb'] ?? array() );
		if ( null !== $breadcrumbs ) {
			$breadcrumb_id = self::string_or_null( $breadcrumbs['@id'] ?? null );
			if ( null !== $webpage && null !== $breadcrumb_id ) {
				$webpage['breadcrumb'] = array(
					'@id' => $breadcrumb_id,
				);
			}
			$graph[] = $breadcrumbs;
		}
		if ( null !== $webpage ) {
			$graph[] = $webpage;
		}

		$author = self::author_node( $context['author'] ?? array() );
		if ( null !== $author ) {
			$graph[] = $author;
		}

		$article_data = $context['article'] ?? array();
		if ( EligibilityPolicy::allows_article( $article_data ) ) {
			$article = ArticleBuilder::build( $article_data );
			if ( null !== $article ) {
				if ( null !== $organization_id ) {
					$article['publisher'] = array(
						'@id' => $organization_id,
					);
				}
				$author_node = self::author_graph_node( $article_data );
				if ( null !== $author_node ) {
					$graph[]           = $author_node;
					$article['author'] = array(
						'@id' => $author_node['@id'],
					);
				}
				$graph[] = $article;
			}
		}

		if ( empty( $graph ) ) {
			return array();
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	/**
	 * Build independent author node for graph linking.
	 *
	 * @param array<string, mixed> $article_data Article payload.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function author_graph_node( array $article_data ): ?array {
		$author = $article_data['author'] ?? null;
		if ( ! is_array( $author ) ) {
			return null;
		}

		$id = self::string_or_null( $author['@id'] ?? null );
		if ( null === $id ) {
			return null;
		}

		$name = self::string_or_null( $author['name'] ?? null );
		if ( null === $name ) {
			return null;
		}

		$type = self::string_or_null( $author['type'] ?? 'Person' );
		$url  = self::string_or_null( $author['url'] ?? null );

		$node = array(
			'@id'   => $id,
			'@type' => null === $type ? 'Person' : $type,
			'name'  => $name,
		);

		if ( null !== $url ) {
			$node['url'] = $url;
		}

		$same_as = self::same_as( $author['sameAs'] ?? null );
		if ( ! empty( $same_as ) ) {
			$node['sameAs'] = $same_as;
		}

		return $node;
	}

	/**
	 * Build standalone author node.
	 *
	 * @param mixed $author_context Author context payload.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function author_node( $author_context ): ?array {
		if ( ! is_array( $author_context ) ) {
			return null;
		}

		$id   = self::string_or_null( $author_context['@id'] ?? null );
		$type = self::string_or_null( $author_context['@type'] ?? 'Person' );
		$name = self::string_or_null( $author_context['name'] ?? null );
		$url  = self::string_or_null( $author_context['url'] ?? null );

		if ( null === $id || null === $name ) {
			return null;
		}

		$node = array(
			'@id'   => $id,
			'@type' => null === $type ? 'Person' : $type,
			'name'  => $name,
		);

		if ( null !== $url ) {
			$node['url'] = $url;
		}

		$description = self::string_or_null( $author_context['description'] ?? null );
		if ( null !== $description ) {
			$node['description'] = $description;
		}

		$same_as = self::same_as( $author_context['sameAs'] ?? null );
		if ( ! empty( $same_as ) ) {
			$node['sameAs'] = $same_as;
		}

		return $node;
	}

	/**
	 * Normalize optional scalar string.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return string|null
	 */
	private static function string_or_null( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_scalar( $value ) ) {
			$string = trim( (string) $value );
			if ( '' !== $string ) {
				return $string;
			}
		}

		return null;
	}

	/**
	 * Normalize sameAs URL list.
	 *
	 * @param mixed $same_as Raw sameAs payload.
	 *
	 * @return array<int, string>
	 */
	private static function same_as( $same_as ): array {
		if ( ! is_array( $same_as ) ) {
			return array();
		}

		$urls = array();
		foreach ( $same_as as $value ) {
			$url = self::string_or_null( $value );
			if ( null === $url ) {
				continue;
			}
			if ( in_array( $url, $urls, true ) ) {
				continue;
			}
			$urls[] = $url;
		}

		return $urls;
	}
}
