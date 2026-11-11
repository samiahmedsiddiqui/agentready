<?php
/**
 * The llms.txt page: choose the file mode, add your own text, and turn on llms-full.txt and the robots.txt line.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Admin;

use AgentWelcome\Cache\Coverage;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;
use AgentWelcome\Http\Urls;
use AgentWelcome\Index\LlmsTxt;
use AgentWelcome\Settings\LlmsOptions;

defined( 'ABSPATH' ) || exit;

/** The llms.txt page: file mode and custom text, llms-full.txt and the robots.txt line. */
final class LlmsPage implements Hookable {

	/** Page slug. */
	public const SLUG = 'agentwelcome-llms';

	/** Settings group of the form. */
	private const GROUP = 'agentwelcome_llms_group';

	/** Longest preview of the file shown on the page, in characters. */
	private const PREVIEW_CHARS = 20000;

	/**
	 * @param LlmsOptions $options     Settings of the llms files.
	 * @param LlmsTxt     $llms        Builds llms.txt for the preview.
	 * @param Coverage    $coverage    Counts the stored pages.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 * @param Notices     $notices     Notices to show.
	 */
	public function __construct(
		private LlmsOptions $options,
		private LlmsTxt $llms,
		private Coverage $coverage,
		private Environment $environment,
		private Notices $notices
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( Capabilities::class, 'files' ) );
	}

	/**
	 * Registers the setting with its cleaning function.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			LlmsOptions::KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->options, 'sanitize' ),
				'default'           => LlmsOptions::defaults(),
			)
		);
	}

	/**
	 * Loads the style and script on this page.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'agentwelcome_page_' . self::SLUG, 'toplevel_page_' . self::SLUG ), true ) ) {
			return;
		}

		Assets::style();
		wp_enqueue_script( 'agentwelcome-admin', AGENTWELCOME_URL . 'assets/js/admin.js', array(), Assets::version( 'assets/js/admin.js' ), true );
	}

	/**
	 * Prints the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::files() ) ) {
			return;
		}

		$preview = $this->llms->build();
		$custom  = $this->options->custom();
		$cache   = $this->coverage->summary();

		View::render(
			'llms',
			array(
				'group'          => self::GROUP,
				'name'           => LlmsOptions::KEY,
				'notices'        => $this->notices->data(),
				'mode'           => $this->options->mode(),
				'custom'         => $custom,
				'full'           => $this->options->full_enabled(),
				'robots_line'    => $this->options->robots_line(),
				'max_bytes'      => LlmsOptions::MAX_BYTES,
				'notes'          => LlmsOptions::check( $custom, (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
				'preview'        => strlen( $preview ) > self::PREVIEW_CHARS ? substr( $preview, 0, self::PREVIEW_CHARS ) . "\n…" : $preview,
				'pretty'         => Urls::has_pretty_permalinks(),
				'active'         => $this->environment->is_active(),
				'llms_url'       => home_url( '/llms.txt' ),
				'full_url'       => home_url( '/llms-full.txt' ),
				'stored'         => $cache['fresh'] + $cache['stale'],
				'missing'        => $cache['missing'],
				'robots_file'    => file_exists( get_home_path() . 'robots.txt' ),
				'verify_all_url' => current_user_can( 'manage_options' ) ? VerifyPage::url( array( 'tab' => 'all' ) ) : '',
			)
		);
	}
}
