<?php
/**
 * Builds /llms-full.txt, the stored Markdown of all eligible pages in one file.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Index;

use AgentReady\Cache\Store;
use AgentReady\Content\Eligibility;
use AgentReady\Http\Urls;
use Generator;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Builds llms-full.txt, the stored Markdown of all served pages in one file. */
final class LlmsFullTxt {

	/** Default size limit of the file, in bytes. */
	public const MAX_BYTES = 5000000;

	/** Stored pages read from the database at a time. */
	private const BATCH = 100;

	/** The metadata block at the top of a stored page. */
	private const FRONT_MATTER = '/\A---\n.*?\n---\n/s';
	/** A first line that is a Markdown heading, followed by the rest of the text. */
	private const TOP_HEADING = '/\A(# [^\n]+)\n*(.*)\z/s';

	/**
	 * @param Store       $store       Stored copies.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 * @param Urls        $urls        Builds the addresses.
	 */
	public function __construct(
		private Store $store,
		private Eligibility $eligibility,
		private Urls $urls
	) {}

	/**
	 * Builds the file. Whole pages are added until the size limit, and a note says how many were left out.
	 *
	 * @return string The text of llms-full.txt.
	 */
	public function build(): string {
		$max  = max( 100000, (int) apply_filters( 'agentready_llms_full_max_bytes', self::MAX_BYTES ) );
		$text = '# ' . SingleLine::from( get_bloginfo( 'name' ) ) . "\n";
		$left = 0;

		foreach ( $this->blocks() as $block ) {
			if ( $left > 0 || strlen( $text ) + strlen( $block ) > $max ) {
				++$left;
				continue;
			}

			$text .= $block;
		}

		if ( $left > 0 ) {
			/* translators: %d: number of pages left out. */
			$text .= "\n" . sprintf( _n( 'Note: %d page is not included because this file reached its size limit. The full list is in llms.txt.', 'Note: %d pages are not included because this file reached its size limit. The full list is in llms.txt.', $left, 'agentready' ), $left ) . "\n";
		}

		return (string) apply_filters( 'agentready_llms_full_txt', $text );
	}

	/**
	 * The text of each served stored page, in page ID order.
	 *
	 * @return Generator<int, string> Text of one page at a time.
	 */
	private function blocks(): Generator {
		$cursor = 0;

		do {
			$ids   = $this->store->ids( $cursor, self::BATCH );
			$count = count( $ids );

			foreach ( $ids as $id ) {
				$cursor = $id;
				$block  = $this->block_for( $id );

				if ( null !== $block ) {
					yield $block;
				}
			}
		} while ( self::BATCH === $count );
	}

	/**
	 * Text of one stored page.
	 *
	 * @param int $id Page ID.
	 * @return string|null The text, or null when the page is not served or has no copy.
	 */
	private function block_for( int $id ): ?string {
		$post = get_post( $id );

		if ( ! $post instanceof WP_Post || ! $this->eligibility->is_eligible( $post ) ) {
			return null;
		}

		$entry = $this->store->get( $id );
		$body  = null === $entry ? '' : self::strip_front_matter( $entry->markdown );
		$url   = $this->urls->permalink( $post );

		return '' === $body || null === $url ? null : self::block( SingleLine::from( get_the_title( $post ) ), $url, $body );
	}

	/**
	 * Removes the metadata block at the top of a stored page.
	 *
	 * @param string $markdown Stored Markdown.
	 * @return string The Markdown without the block.
	 */
	public static function strip_front_matter( string $markdown ): string {
		$stripped = preg_replace( self::FRONT_MATTER, '', $markdown, 1 );

		return trim( is_string( $stripped ) ? $stripped : $markdown );
	}

	/**
	 * Puts the source line under the page's own top heading, or adds a heading when the body has none.
	 *
	 * @param string $title Page title, used when the body has no heading.
	 * @param string $url   Address of the page.
	 * @param string $body  Markdown of the page.
	 * @return string The block, starting with a separator.
	 */
	public static function block( string $title, string $url, string $body ): string {
		$source = 'Source: ' . $url;

		if ( 1 === preg_match( self::TOP_HEADING, $body, $match ) ) {
			return "\n---\n\n" . $match[1] . "\n\n" . $source . "\n" . ( '' === $match[2] ? '' : "\n" . $match[2] . "\n" );
		}

		return "\n---\n\n# " . ( '' === $title ? $url : $title ) . "\n\n" . $source . "\n\n" . $body . "\n";
	}
}
