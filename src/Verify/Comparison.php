<?php
/**
 * Compares stored Markdown with fresh Markdown and checks page text against Markdown.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Verify;

defined( 'ABSPATH' ) || exit;

final class Comparison {

	public const SAME = 'same';
	public const ADD  = 'add';
	public const DEL  = 'del';
	public const GAP  = 'gap';

	private const MAX_CELLS = 250000;

	/**
	 * @param string[] $ignore Regular expressions for text to leave out of the comparison.
	 */
	public static function normalise( string $markdown, array $ignore = array() ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $markdown );

		foreach ( $ignore as $pattern ) {
			$result = preg_replace( $pattern, '', $text );
			$text   = null === $result ? $text : $result;
		}

		$lines = array_map( 'rtrim', explode( "\n", $text ) );
		$text  = implode( "\n", $lines );

		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	/**
	 * @return array<int, array{type: string, text: string}>
	 */
	public static function diff( string $old, string $new ): array {
		$a = '' === $old ? array() : explode( "\n", $old );
		$b = '' === $new ? array() : explode( "\n", $new );
		$n = count( $a );
		$m = count( $b );

		if ( $n * $m > self::MAX_CELLS ) {
			return self::diff_by_set( $a, $b );
		}

		$table = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );

		for ( $i = $n - 1; $i >= 0; $i-- ) {
			for ( $j = $m - 1; $j >= 0; $j-- ) {
				$table[ $i ][ $j ] = $a[ $i ] === $b[ $j ] ? $table[ $i + 1 ][ $j + 1 ] + 1 : max( $table[ $i + 1 ][ $j ], $table[ $i ][ $j + 1 ] );
			}
		}

		$out = array();
		$i   = 0;
		$j   = 0;

		while ( $i < $n && $j < $m ) {
			if ( $a[ $i ] === $b[ $j ] ) {
				$out[] = array(
					'type' => self::SAME,
					'text' => $a[ $i ],
				);
				++$i;
				++$j;
			} elseif ( $table[ $i + 1 ][ $j ] >= $table[ $i ][ $j + 1 ] ) {
				$out[] = array(
					'type' => self::DEL,
					'text' => $a[ $i++ ],
				);
			} else {
				$out[] = array(
					'type' => self::ADD,
					'text' => $b[ $j++ ],
				);
			}
		}

		for ( ; $i < $n; $i++ ) {
			$out[] = array(
				'type' => self::DEL,
				'text' => $a[ $i ],
			);
		}

		for ( ; $j < $m; $j++ ) {
			$out[] = array(
				'type' => self::ADD,
				'text' => $b[ $j ],
			);
		}

		return $out;
	}

	/**
	 * @param array<int, array{type: string, text: string}> $diff Result of diff().
	 */
	public static function changed( array $diff ): int {
		return count( array_filter( $diff, static fn( array $line ): bool => self::SAME !== $line['type'] ) );
	}

	/**
	 * Keeps changed lines and a few unchanged lines around them.
	 *
	 * @param array<int, array{type: string, text: string}> $diff    Result of diff().
	 * @param int                                           $context Unchanged lines to keep on each side.
	 * @return array<int, array{type: string, text: string}>
	 */
	public static function condense( array $diff, int $context = 2 ): array {
		$keep = array();
		$last = count( $diff ) - 1;

		foreach ( $diff as $index => $line ) {
			if ( self::SAME === $line['type'] ) {
				continue;
			}

			for ( $k = max( 0, $index - $context ); $k <= min( $last, $index + $context ); $k++ ) {
				$keep[ $k ] = true;
			}
		}

		$out     = array();
		$skipped = 0;

		foreach ( $diff as $index => $line ) {
			if ( isset( $keep[ $index ] ) ) {
				if ( $skipped > 0 ) {
					$out[]   = array(
						'type' => self::GAP,
						'text' => (string) $skipped,
					);
					$skipped = 0;
				}

				$out[] = $line;
			} else {
				++$skipped;
			}
		}

		if ( $skipped > 0 && array() !== $out ) {
			$out[] = array(
				'type' => self::GAP,
				'text' => (string) $skipped,
			);
		}

		return $out;
	}

	/**
	 * Letters and digits only, lower case, without the front matter and link addresses.
	 */
	public static function plain_key( string $markdown ): string {
		$text = (string) preg_replace( '/\A---\n.*?\n---\n/s', '', $markdown );
		$text = (string) preg_replace( '/!?\[([^\]]*)\]\([^)]*\)/', '$1', $text );

		return self::key( $text );
	}

	/**
	 * @param string[] $blocks Text blocks taken from the page.
	 * @return array{total: int, found: int, missing: string[]}
	 */
	public static function coverage( array $blocks, string $markdown ): array {
		$plain   = self::plain_key( $markdown );
		$total   = 0;
		$found   = 0;
		$missing = array();

		foreach ( $blocks as $block ) {
			$key = self::key( $block );

			if ( strlen( $key ) < 3 ) {
				continue;
			}

			++$total;

			if ( false !== strpos( $plain, $key ) ) {
				++$found;
			} else {
				$missing[] = $block;
			}
		}

		return array(
			'total'   => $total,
			'found'   => $found,
			'missing' => $missing,
		);
	}

	private static function key( string $text ): string {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );

		return (string) preg_replace( '/[^\p{L}\p{N}]+/u', '', $text );
	}

	/**
	 * Fallback for very long texts: lines only on one side, without alignment.
	 *
	 * @param string[] $a Old lines.
	 * @param string[] $b New lines.
	 * @return array<int, array{type: string, text: string}>
	 */
	private static function diff_by_set( array $a, array $b ): array {
		$in_b = array_flip( $b );
		$in_a = array_flip( $a );
		$out  = array();

		foreach ( $a as $line ) {
			$out[] = array(
				'type' => isset( $in_b[ $line ] ) ? self::SAME : self::DEL,
				'text' => $line,
			);
		}

		foreach ( $b as $line ) {
			if ( ! isset( $in_a[ $line ] ) ) {
				$out[] = array(
					'type' => self::ADD,
					'text' => $line,
				);
			}
		}

		return $out;
	}
}
