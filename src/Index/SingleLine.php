<?php
/**
 * Turns text from the editor into one plain line, for titles and descriptions in the llms files.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Index;

defined( 'ABSPATH' ) || exit;

/**
 * Removes markup from text and collapses it to a single line.
 */
final class SingleLine {

	/**
	 * Strips tags and entities and replaces every run of whitespace with one space.
	 *
	 * @param string $text Text that may contain HTML.
	 * @return string Plain text on one line, with no space at the ends.
	 */
	public static function from( string $text ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
	}
}
