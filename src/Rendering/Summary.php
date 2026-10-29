<?php
/**
 * Picks a short description from the first real paragraph of a Markdown page.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

defined( 'ABSPATH' ) || exit;

final class Summary {

	private const MIN_LENGTH = 30;

	public function from_markdown( string $markdown, int $words = 30 ): string {
		foreach ( (array) preg_split( '/\n{2,}/', $markdown ) as $block ) {
			$block = trim( (string) $block );

			if ( '' === $block || 1 === preg_match( '/^(#|[-*+] |\d+[.)] |>|\||`{3}|!\[|---)/', $block ) ) {
				continue;
			}

			$text = $this->plain( $block );

			if ( mb_strlen( $text ) < self::MIN_LENGTH ) {
				continue;
			}

			$parts = explode( ' ', $text );

			return count( $parts ) > $words ? implode( ' ', array_slice( $parts, 0, $words ) ) . '…' : $text;
		}

		return '';
	}

	private function plain( string $block ): string {
		$text = (string) preg_replace( '/!\[[^\]]*\]\([^)]*\)/', '', $block );
		$text = (string) preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', $text );
		$text = (string) preg_replace( '/\\\\([\\\\`*_\[\]<])/', '$1', $text );
		$text = str_replace( array( '**', '~~', '`' ), '', $text );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ), " *_\t" );
	}
}
