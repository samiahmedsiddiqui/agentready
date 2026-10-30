<?php
/**
 * Warnings shown at the top of the plugin's admin pages.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Environment\Environment;
use AgentReady\Http\Urls;

defined( 'ABSPATH' ) || exit;

final class Notices {

	public function __construct( private Environment $environment ) {}

	/**
	 * @return array<string, mixed>
	 */
	public function data(): array {
		$yoast  = get_option( 'wpseo' );
		$reason = $this->environment->reason();

		return array(
			'inactive'   => null === $reason ? null : $this->environment->describe( $reason ),
			'resume_url' => 'site_address_changed' === $reason && current_user_can( 'manage_options' ) ? wp_nonce_url( admin_url( 'admin-post.php?action=agentready_resume' ), 'agentready_resume' ) : '',
			'pretty'     => Urls::has_pretty_permalinks(),
			'yoast_llms' => is_array( $yoast ) && ! empty( $yoast['enable_llms_txt'] ),
			'llms_file'  => file_exists( get_home_path() . 'llms.txt' ),
		);
	}
}
