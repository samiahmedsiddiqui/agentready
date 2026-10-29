<?php
/**
 * Public taxonomy terms grouped for the exclusion checkboxes.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use WP_Taxonomy;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class TermList {

	public const LIMIT = 200;

	/**
	 * @return array<int, array{taxonomy: WP_Taxonomy, terms: array<int, array{term: WP_Term, depth: int}>, total: int}>
	 */
	public function groups(): array {
		$groups = array();

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy->name,
					'hide_empty' => false,
					'number'     => self::LIMIT,
					'orderby'    => 'name',
				)
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$total = wp_count_terms(
				array(
					'taxonomy'   => $taxonomy->name,
					'hide_empty' => false,
				)
			);

			$groups[] = array(
				'taxonomy' => $taxonomy,
				'terms'    => $this->ordered( array_filter( $terms, static fn( $term ): bool => $term instanceof WP_Term ), (bool) $taxonomy->hierarchical ),
				'total'    => is_wp_error( $total ) ? 0 : (int) $total,
			);
		}

		return $groups;
	}

	/**
	 * @param WP_Term[] $terms        Terms to order.
	 * @param bool      $hierarchical Whether children are shown under their parents.
	 * @return array<int, array{term: WP_Term, depth: int}>
	 */
	private function ordered( array $terms, bool $hierarchical ): array {
		if ( ! $hierarchical ) {
			return array_map(
				static fn( WP_Term $term ): array => array(
					'term'  => $term,
					'depth' => 0,
				),
				array_values( $terms )
			);
		}

		$listed    = array();
		$by_parent = array();

		foreach ( $terms as $term ) {
			$listed[ $term->term_id ] = true;
		}

		foreach ( $terms as $term ) {
			$by_parent[ isset( $listed[ $term->parent ] ) ? (int) $term->parent : 0 ][] = $term;
		}

		$ordered = array();
		$walk    = static function ( int $parent_id, int $depth ) use ( &$walk, &$ordered, $by_parent ): void {
			foreach ( $by_parent[ $parent_id ] ?? array() as $term ) {
				$ordered[] = array(
					'term'  => $term,
					'depth' => $depth,
				);
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};

		$walk( 0, 0 );

		return $ordered;
	}
}
