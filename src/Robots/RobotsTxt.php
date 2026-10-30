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

final class RobotsTxt implements Hookable {

	public function __construct(
		private LlmsOptions $options,
		private Environment $environment
	) {}

	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'append' ), 20, 2 );
	}

	/**
	 * @param mixed $output The robots.txt content.
	 * @param mixed $indexable Whether the site asks search engines to index it.
	 */
	public function append( mixed $output, mixed $indexable ): mixed {
		if ( ! is_string( $output ) || ! (bool) (int) $indexable || ! $this->options->robots_line() || ! Urls::has_pretty_permalinks() || ! $this->environment->is_active() ) {
			return $output;
		}

		return $output . self::lines( home_url( '/llms.txt' ), $this->options->full_enabled() ? home_url( '/llms-full.txt' ) : null );
	}

	/**
	 * @param string|null $full The llms-full.txt URL, or null when that file is off.
	 */
	public static function lines( string $llms, ?string $full ): string {
		$lines = "\n# AgentReady\n# llms.txt: " . $llms . "\n";

		if ( null !== $full ) {
			$lines .= '# llms-full.txt: ' . $full . "\n";
		}

		return $lines;
	}
}
