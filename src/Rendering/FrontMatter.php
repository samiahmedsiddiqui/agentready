<?php
/**
 * Builds the metadata block placed at the top of each Markdown page.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

defined( 'ABSPATH' ) || exit;

/** Builds the metadata block at the top of a Markdown page. */
final class FrontMatter {

	/**
	 * Builds the block.
	 *
	 * @param array<string, string> $fields Key and value pairs. Empty values are skipped.
	 * @return string The block, between two lines of dashes.
	 */
	public function render( array $fields ): string {
		$lines = array( '---' );

		foreach ( $fields as $key => $value ) {
			$value = trim( $value );

			if ( '' !== $value ) {
				$lines[] = $key . ': ' . $this->scalar( $value );
			}
		}

		$lines[] = '---';

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Quotes a value so it is valid in the block.
	 *
	 * @param string $value Plain text.
	 * @return string The value as a JSON string.
	 */
	private function scalar( string $value ): string {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		return false === $encoded ? '""' : $encoded;
	}
}
