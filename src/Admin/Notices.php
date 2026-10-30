<?php
/**
 * Warnings shown at the top of the plugin's admin pages.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Cache\Changes;
use AgentReady\Environment\Environment;
use AgentReady\Http\Urls;

defined( 'ABSPATH' ) || exit;

final class Notices {

	public function __construct(
		private Environment $environment,
		private Changes $changes
	) {}

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
			'changes'    => $this->changes(),
		);
	}

	/**
	 * @return array<int, array{message: string, check_url: string, dismiss_url: string}>
	 */
	private function changes(): array {
		$notices = array();

		foreach ( $this->changes->pending() as $key => $change ) {
			$message = self::change_message( (string) $key, is_array( $change ) ? $change : array() );

			if ( '' === $message ) {
				continue;
			}

			$notices[] = array(
				'message'     => $message,
				'check_url'   => Changes::PLUGINS === $key ? VerifyPage::url( array( 'tab' => 'all' ) ) : '',
				'dismiss_url' => wp_nonce_url( admin_url( 'admin-post.php?action=agentready_dismiss&key=' . rawurlencode( (string) $key ) ), 'agentready_dismiss' ),
			);
		}

		return $notices;
	}

	/**
	 * @param array<string, mixed> $change Saved details of the change.
	 */
	public static function change_message( string $key, array $change ): string {
		if ( Changes::PLUGINS === $key ) {
			$count = max( 1, (int) ( $change['count'] ?? 1 ) );

			return sprintf(
				/* translators: %s: number of plugin changes. */
				_n( '%s plugin was activated, deactivated or updated. If it changes how your pages look, check whether any stored page is out of date.', '%s plugins were activated, deactivated or updated. If they change how your pages look, check whether any stored page is out of date.', $count, 'agentready' ),
				number_format_i18n( $count )
			);
		}

		if ( Changes::REFRESH !== $key ) {
			return '';
		}

		$reasons = array(
			'theme'        => __( 'The active theme changed', 'agentready' ),
			'theme_update' => __( 'The active theme was updated', 'agentready' ),
			'customizer'   => __( 'The Customizer was saved', 'agentready' ),
		);
		$reason  = $reasons[ (string) ( $change['reason'] ?? '' ) ] ?? '';

		return '' === $reason ? '' : $reason . '. ' . __( 'AgentReady is refreshing the stored Markdown of every page in the background.', 'agentready' );
	}
}
