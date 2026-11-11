<?php
/**
 * Opt-in comment in robots.txt that points to llms.txt. It adds no allow or disallow rules.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Robots;

use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Http\Urls;
use AgentReady\Settings\LlmsOptions;

defined( 'ABSPATH' ) || exit;

/** Adds comment lines to robots.txt that point to the llms files. It adds no allow or disallow rules. */
final class RobotsTxt implements Hookable {

	/**
	 * @param LlmsOptions $options     Settings of the llms files.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 */
	public function __construct(
		private LlmsOptions $options,
		private Environment $environment
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'append' ), 20, 2 );
	}

	/**
	 * Adds the lines to the generated robots.txt.
	 *
	 * @param mixed $output    The robots.txt content.
	 * @param mixed $indexable Whether the site asks search engines to index it.
	 * @return mixed The content, with the lines added when the setting is on.
	 */
	public function append( mixed $output, mixed $indexable ): mixed {
		if ( ! is_string( $output ) || ! $this->should_add( (bool) (int) $indexable ) ) {
			return $output;
		}

		return $output . self::lines( home_url( '/llms.txt' ), $this->options->full_enabled() ? home_url( '/llms-full.txt' ) : null );
	}

	/**
	 * Whether the lines should be added.
	 *
	 * @param bool $indexable Whether the site asks search engines to index it.
	 * @return bool True when they should.
	 */
	private function should_add( bool $indexable ): bool {
		return $indexable && $this->options->robots_line() && Urls::has_pretty_permalinks() && $this->environment->is_active();
	}

	/**
	 * Builds the comment lines.
	 *
	 * @param string      $llms Address of llms.txt.
	 * @param string|null $full Address of llms-full.txt, or null when that file is off.
	 * @return string The lines, starting with a line break.
	 */
	public static function lines( string $llms, ?string $full ): string {
		$lines = "\n# AgentReady\n# llms.txt: " . $llms . "\n";

		if ( null !== $full ) {
			$lines .= '# llms-full.txt: ' . $full . "\n";
		}

		return $lines;
	}
}
