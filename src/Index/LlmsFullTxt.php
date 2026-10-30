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
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class LlmsFullTxt {

	public const MAX_BYTES = 5000000;

	private const BATCH = 100;

	public function __construct(
		private Store $store,
		private Eligibility $eligibility,
		private Urls $urls
	) {}

	public function build(): string {
		$max    = max( 100000, (int) apply_filters( 'agentready_llms_full_max_bytes', self::MAX_BYTES ) );
		$text   = '# ' . self::line( get_bloginfo( 'name' ) ) . "\n";
		$left   = 0;
		$full   = false;
		$cursor = 0;

		do {
			$ids   = $this->store->ids( $cursor, self::BATCH );
			$count = count( $ids );

			foreach ( $ids as $id ) {
				$cursor = $id;
				$post   = get_post( $id );

				if ( ! $post instanceof WP_Post || ! $this->eligibility->is_eligible( $post ) ) {
					continue;
				}

				$entry = $this->store->get( $id );
				$body  = null === $entry ? '' : self::strip_front_matter( $entry->markdown );
				$url   = $this->urls->permalink( $post );

				if ( '' === $body || null === $url ) {
					continue;
				}

				$block = self::block( self::line( get_the_title( $post ) ), $url, $body );

				if ( $full || strlen( $text ) + strlen( $block ) > $max ) {
					$full = true;
					++$left;
					continue;
				}

				$text .= $block;
			}
		} while ( self::BATCH === $count );

		if ( $left > 0 ) {
			/* translators: %d: number of pages left out. */
			$text .= "\n" . sprintf( _n( 'Note: %d page is not included because this file reached its size limit. The full list is in llms.txt.', 'Note: %d pages are not included because this file reached its size limit. The full list is in llms.txt.', $left, 'agentready' ), $left ) . "\n";
		}

		return (string) apply_filters( 'agentready_llms_full_txt', $text );
	}

	/**
	 * Removes the metadata block stored at the top of a page.
	 */
	public static function strip_front_matter( string $markdown ): string {
		$stripped = preg_replace( '/\A---\n.*?\n---\n/s', '', $markdown, 1 );

		return trim( is_string( $stripped ) ? $stripped : $markdown );
	}

	public static function block( string $title, string $url, string $body ): string {
		return "\n---\n\n# " . ( '' === $title ? $url : $title ) . "\n\nSource: " . $url . "\n\n" . $body . "\n";
	}

	private static function line( string $text ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
	}
}
