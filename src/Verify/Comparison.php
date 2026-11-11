<?php
/**
 * Compares stored Markdown with fresh Markdown and checks page text against Markdown.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Verify;

defined( 'ABSPATH' ) || exit;

/** Compares two Markdown texts line by line, and checks that the text of a page is in its Markdown. */
final class Comparison {

	/** The metadata block at the top of a stored page. */
	private const FRONT_MATTER = '/\A---\n.*?\n---\n/s';

	/** A Markdown link or image, to keep only its text. */
	private const MARKDOWN_LINK = '/!?\[([^\]]*)\]\([^)]*\)/';

	/** Line type: the line is in both texts. */
	public const SAME = 'same';
	/** Line type: the line is only in the new text. */
	public const ADD = 'add';
	/** Line type: the line is only in the old text. */
	public const DEL = 'del';
	/** Line type: a gap between kept parts of a long diff. */
	public const GAP = 'gap';

	/** Largest table the line-by-line diff builds. Longer texts are compared by whole lines. */
	private const MAX_CELLS = 250000;

	/**
	 * Makes two texts comparable: unifies line endings, removes what should be ignored, and trims the ends and blank runs.
	 *
	 * @param string   $markdown Markdown text.
	 * @param string[] $ignore   Regular expressions for text to leave out of the comparison.
	 * @return string The cleaned text.
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
	 * Compares two texts line by line.
	 *
	 * @param string $before Old text.
	 * @param string $after  New text.
	 * @return array<int, array{type: string, text: string}> Lines with their type.
	 */
	public static function diff( string $before, string $after ): array {
		$old_lines = '' === $before ? array() : explode( "\n", $before );
		$new_lines = '' === $after ? array() : explode( "\n", $after );
		$old_count = count( $old_lines );
		$new_count = count( $new_lines );

		if ( $old_count * $new_count > self::MAX_CELLS ) {
			return self::diff_by_set( $old_lines, $new_lines );
		}

		$common_lengths = array_fill( 0, $old_count + 1, array_fill( 0, $new_count + 1, 0 ) );

		for ( $old_index = $old_count - 1; $old_index >= 0; $old_index-- ) {
			for ( $new_index = $new_count - 1; $new_index >= 0; $new_index-- ) {
				$common_lengths[ $old_index ][ $new_index ] = $old_lines[ $old_index ] === $new_lines[ $new_index ] ? $common_lengths[ $old_index + 1 ][ $new_index + 1 ] + 1 : max( $common_lengths[ $old_index + 1 ][ $new_index ], $common_lengths[ $old_index ][ $new_index + 1 ] );
			}
		}

		$lines     = array();
		$old_index = 0;
		$new_index = 0;

		while ( $old_index < $old_count && $new_index < $new_count ) {
			if ( $old_lines[ $old_index ] === $new_lines[ $new_index ] ) {
				$lines[] = array(
					'type' => self::SAME,
					'text' => $old_lines[ $old_index ],
				);
				++$old_index;
				++$new_index;
			} elseif ( $common_lengths[ $old_index + 1 ][ $new_index ] >= $common_lengths[ $old_index ][ $new_index + 1 ] ) {
				$lines[] = array(
					'type' => self::DEL,
					'text' => $old_lines[ $old_index++ ],
				);
			} else {
				$lines[] = array(
					'type' => self::ADD,
					'text' => $new_lines[ $new_index++ ],
				);
			}
		}

		for ( ; $old_index < $old_count; $old_index++ ) {
			$lines[] = array(
				'type' => self::DEL,
				'text' => $old_lines[ $old_index ],
			);
		}

		for ( ; $new_index < $new_count; $new_index++ ) {
			$lines[] = array(
				'type' => self::ADD,
				'text' => $new_lines[ $new_index ],
			);
		}

		return $lines;
	}

	/**
	 * Counts the lines that were added or removed.
	 *
	 * @param array<int, array{type: string, text: string}> $diff Result of diff().
	 * @return int Number of changed lines.
	 */
	public static function changed( array $diff ): int {
		return count( array_filter( $diff, static fn( array $line ): bool => self::SAME !== $line['type'] ) );
	}

	/**
	 * Keeps changed lines and a few unchanged lines around them.
	 *
	 * @param array<int, array{type: string, text: string}> $diff    Result of diff().
	 * @param int                                           $context Unchanged lines to keep on each side.
	 * @return array<int, array{type: string, text: string}> The kept lines, with a gap line where parts were left out.
	 */
	public static function condense( array $diff, int $context = 2 ): array {
		$keep = array();
		$last = count( $diff ) - 1;

		foreach ( $diff as $index => $line ) {
			if ( self::SAME === $line['type'] ) {
				continue;
			}

			$to = min( $last, $index + $context );

			for ( $k = max( 0, $index - $context ); $k <= $to; $k++ ) {
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
	 *
	 * @param string $markdown Markdown text.
	 * @return string The reduced text.
	 */
	public static function plain_key( string $markdown ): string {
		$text = (string) preg_replace( self::FRONT_MATTER, '', $markdown );
		$text = (string) preg_replace( self::MARKDOWN_LINK, '$1', $text );

		return self::key( $text );
	}

	/**
	 * Checks which text blocks of the page appear in the Markdown.
	 *
	 * @param string[] $blocks   Text blocks taken from the page.
	 * @param string   $markdown The Markdown.
	 * @return array{total: int, found: int, missing: string[]} Blocks counted, blocks found, and the ones missing.
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

	/**
	 * Reduces a text to lower case letters and digits, so layout differences do not matter.
	 *
	 * @param string $text Text.
	 * @return string The reduced text.
	 */
	private static function key( string $text ): string {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );

		return (string) preg_replace( '/[^\p{L}\p{N}]+/u', '', $text );
	}

	/**
	 * Fallback for very long texts: lines that are only on one side, without alignment.
	 *
	 * @param string[] $old_lines Old lines.
	 * @param string[] $new_lines New lines.
	 * @return array<int, array{type: string, text: string}> Lines with their type.
	 */
	private static function diff_by_set( array $old_lines, array $new_lines ): array {
		$in_new = array_flip( $new_lines );
		$in_old = array_flip( $old_lines );
		$lines  = array();

		foreach ( $old_lines as $line ) {
			$lines[] = array(
				'type' => isset( $in_new[ $line ] ) ? self::SAME : self::DEL,
				'text' => $line,
			);
		}

		foreach ( $new_lines as $line ) {
			if ( ! isset( $in_old[ $line ] ) ) {
				$lines[] = array(
					'type' => self::ADD,
					'text' => $line,
				);
			}
		}

		return $lines;
	}
}
