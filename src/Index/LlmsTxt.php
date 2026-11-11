<?php
/**
 * Builds /llms.txt: a Markdown index of every eligible page.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Index;

use AgentWelcome\Content\Description;
use AgentWelcome\Content\Eligibility;
use AgentWelcome\Content\PostTypes;
use AgentWelcome\Http\Urls;
use AgentWelcome\Settings\LlmsOptions;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Builds llms.txt, from the generated list of pages, a custom text, or both. */
final class LlmsTxt {

	/**
	 * @param PostTypes   $post_types  Post types that are served.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 * @param Urls        $urls        Builds the addresses.
	 * @param Description $description Finds the description of a page.
	 * @param LlmsOptions $options     Settings of the llms files.
	 */
	public function __construct(
		private PostTypes $post_types,
		private Eligibility $eligibility,
		private Urls $urls,
		private Description $description,
		private LlmsOptions $options
	) {}

	/**
	 * Builds the file for the current mode.
	 *
	 * @return string The text of llms.txt.
	 */
	public function build(): string {
		$mode   = $this->options->mode();
		$custom = $this->options->custom();

		$text = LlmsOptions::CUSTOM === $mode && '' !== $custom
			? self::compose( $mode, $custom, array(), array() )
			: self::compose( $mode, $custom, $this->heading(), $this->sections() );

		return (string) apply_filters( 'agentwelcome_llms_txt', $text );
	}

	/**
	 * Joins the parts of the file for a mode. A custom text is used as written, and an empty one falls back to the generated file.
	 *
	 * @param string   $mode     One of the LlmsOptions modes.
	 * @param string   $custom   Custom text.
	 * @param string[] $heading  Generated title and tagline lines.
	 * @param string[] $sections Generated page list lines.
	 * @return string The file.
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
	 * Title and tagline lines from the site name and description.
	 *
	 * @return string[] Lines of text.
	 */
	private function heading(): array {
		$lines   = array( '# ' . SingleLine::from( get_bloginfo( 'name' ) ) );
		$tagline = SingleLine::from( get_bloginfo( 'description' ) );

		if ( '' !== $tagline ) {
			$lines[] = '';
			$lines[] = '> ' . $tagline;
		}

		return $lines;
	}

	/**
	 * One section per served post type, with a line for each page.
	 *
	 * @return string[] Lines of text.
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
	 * Lines for the pages of one post type.
	 *
	 * @param string $type Post type.
	 * @return string[] One line per served page.
	 */
	private function entries( string $type ): array {
		$hierarchical = is_post_type_hierarchical( $type );
		$posts        = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'has_password'     => false,
				'posts_per_page'   => (int) apply_filters( 'agentwelcome_llms_max_items', 500, $type ),
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

	/**
	 * Line for one page: a link to the Markdown version, with its description.
	 *
	 * @param WP_Post $post The page.
	 * @return string|null The line, or null when the page is not served.
	 */
	private function entry( WP_Post $post ): ?string {
		$url = $this->urls->markdown_url( $post );

		if ( null === $url || ! $this->eligibility->is_eligible( $post ) ) {
			return null;
		}

		$title = str_replace( array( '[', ']' ), array( '\\[', '\\]' ), SingleLine::from( get_the_title( $post ) ) );
		$title = '' === $title ? $post->post_name : $title;
		$text  = SingleLine::from( wp_trim_words( $this->description->get( $post ), 30 ) );

		return '- [' . $title . '](' . $url . ')' . ( '' === $text ? '' : ': ' . $text );
	}
}
