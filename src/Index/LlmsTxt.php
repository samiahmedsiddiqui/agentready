<?php
/**
 * Builds /llms.txt: a Markdown index of every eligible page.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Index;

use AgentReady\Content\Description;
use AgentReady\Content\Eligibility;
use AgentReady\Content\PostTypes;
use AgentReady\Http\Urls;
use AgentReady\Settings\LlmsOptions;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class LlmsTxt {

	public function __construct(
		private PostTypes $post_types,
		private Eligibility $eligibility,
		private Urls $urls,
		private Description $description,
		private LlmsOptions $options
	) {}

	public function build(): string {
		$mode   = $this->options->mode();
		$custom = $this->options->custom();

		$text = LlmsOptions::CUSTOM === $mode && '' !== $custom
			? self::compose( $mode, $custom, array(), array() )
			: self::compose( $mode, $custom, $this->heading(), $this->sections() );

		return (string) apply_filters( 'agentready_llms_txt', $text );
	}

	/**
	 * Joins the parts of the file for a mode. Custom text is used as written, and an empty one falls back to the generated file.
	 *
	 * @param string[] $heading  Generated title and tagline lines.
	 * @param string[] $sections Generated page list lines.
	 */
	public static function compose( string $mode, string $custom, array $heading, array $sections ): string {
		if ( '' !== $custom && LlmsOptions::CUSTOM === $mode ) {
			return $custom . "\n";
		}

		if ( '' !== $custom && LlmsOptions::HYBRID === $mode ) {
			return $custom . "\n" . ( empty( $sections ) ? '' : "\n" . implode( "\n", $sections ) . "\n" );
		}

		return implode( "\n", empty( $sections ) ? $heading : array_merge( $heading, array( '' ), $sections ) ) . "\n";
	}

	/**
	 * @return string[]
	 */
	private function heading(): array {
		$lines   = array( '# ' . $this->clean( get_bloginfo( 'name' ) ) );
		$tagline = $this->clean( get_bloginfo( 'description' ) );

		if ( '' !== $tagline ) {
			$lines[] = '';
			$lines[] = '> ' . $tagline;
		}

		return $lines;
	}

	/**
	 * @return string[]
	 */
	private function sections(): array {
		$lines = array();

		foreach ( $this->post_types->enabled() as $type ) {
			$entries = $this->entries( $type );

			if ( empty( $entries ) ) {
				continue;
			}

			$object = get_post_type_object( $type );

			if ( array() !== $lines ) {
				$lines[] = '';
			}

			$lines[] = '## ' . ( $object ? $object->labels->name : $type );
			$lines[] = '';

			foreach ( $entries as $entry ) {
				$lines[] = $entry;
			}
		}

		return $lines;
	}

	/**
	 * @return string[]
	 */
	private function entries( string $type ): array {
		$hierarchical = is_post_type_hierarchical( $type );
		$posts        = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'has_password'     => false,
				'posts_per_page'   => (int) apply_filters( 'agentready_llms_max_items', 500, $type ),
				'orderby'          => $hierarchical ? 'menu_order title' : 'date',
				'order'            => $hierarchical ? 'ASC' : 'DESC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		$entries = array();

		foreach ( $posts as $post ) {
			$entry = $this->entry( $post );

			if ( null !== $entry ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	private function entry( WP_Post $post ): ?string {
		$url = $this->urls->markdown_url( $post );

		if ( null === $url || ! $this->eligibility->is_eligible( $post ) ) {
			return null;
		}

		$title = str_replace( array( '[', ']' ), array( '\\[', '\\]' ), $this->clean( get_the_title( $post ) ) );
		$title = '' === $title ? $post->post_name : $title;
		$text  = $this->clean( wp_trim_words( $this->description->get( $post ), 30 ) );

		return '- [' . $title . '](' . $url . ')' . ( '' === $text ? '' : ': ' . $text );
	}

	private function clean( string $text ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
	}
}
