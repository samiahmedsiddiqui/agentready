<?php
/**
 * Builds the metadata block placed at the top of each Markdown page.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Rendering;

defined( 'ABSPATH' ) || exit;

final class FrontMatter {

	/**
	 * @param array<string, string> $fields Key/value pairs; empty values are skipped.
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

	private function scalar( string $value ): string {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		return false === $encoded ? '""' : $encoded;
	}
}
