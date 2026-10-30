<?php
/**
 * The llms.txt page: choose the file mode, add your own text, and turn on llms-full.txt and the robots.txt line.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Cache\Coverage;
use AgentReady\Contracts\Hookable;
use AgentReady\Environment\Environment;
use AgentReady\Http\Urls;
use AgentReady\Index\LlmsTxt;
use AgentReady\Settings\LlmsOptions;

defined( 'ABSPATH' ) || exit;

final class LlmsPage implements Hookable {

	public const SLUG = 'agentready-llms';

	private const GROUP = 'agentready_llms_group';

	private const PREVIEW_CHARS = 20000;

	public function __construct(
		private LlmsOptions $options,
		private LlmsTxt $llms,
		private Coverage $coverage,
		private Environment $environment,
		private Notices $notices
	) {}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

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

	public function enqueue( string $hook ): void {
		if ( 'agentready_page_' . self::SLUG !== $hook ) {
			return;
		}

		Assets::style();
		wp_enqueue_script( 'agentready-admin', AGENTREADY_URL . 'assets/js/admin.js', array(), Assets::version( 'assets/js/admin.js' ), true );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
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
				'verify_all_url' => VerifyPage::url( array( 'tab' => 'all' ) ),
			)
		);
	}
}
