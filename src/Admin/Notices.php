<?php
/**
 * Warnings shown at the top of the plugin's admin pages.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Admin;

use AgentWelcome\Cache\Changes;
use AgentWelcome\Environment\Environment;
use AgentWelcome\Http\Urls;

defined( 'ABSPATH' ) || exit;

/** Collects what the admin pages show as notices: why the plugin is off, setup problems, and theme and plugin changes. */
final class Notices {

	/**
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 * @param Changes     $changes     Theme and plugin changes that have not been dismissed.
	 */
	public function __construct(
		private Environment $environment,
		private Changes $changes
	) {}

	/**
	 * Values for the notices template.
	 *
	 * @return array<string, mixed> Values by name.
	 */
	public function data(): array {
		$yoast  = get_option( 'wpseo' );
		$reason = $this->environment->reason();

		return array(
			'inactive'   => null === $reason ? null : $this->environment->describe( $reason ),
			'resume_url' => 'site_address_changed' === $reason && current_user_can( 'manage_options' ) ? wp_nonce_url( admin_url( 'admin-post.php?action=agentwelcome_resume' ), 'agentwelcome_resume' ) : '',
			'pretty'     => Urls::has_pretty_permalinks(),
			'yoast_llms' => is_array( $yoast ) && ! empty( $yoast['enable_llms_txt'] ),
			'llms_file'  => file_exists( get_home_path() . 'llms.txt' ),
			'full_file'  => file_exists( get_home_path() . 'llms-full.txt' ),
			'changes'    => $this->changes(),
		);
	}

	/**
	 * Notices for theme and plugin changes, with their links.
	 *
	 * @return array<int, array{message: string, check_url: string, dismiss_url: string}> One entry per notice.
	 */
	private function changes(): array {
		$notices  = array();
		$cron_off = $this->environment->cron_disabled();

		foreach ( $this->changes->pending() as $key => $change ) {
			$message = self::change_message( (string) $key, is_array( $change ) ? $change : array(), $cron_off );

			if ( '' === $message ) {
				continue;
			}

			$notices[] = array(
				'message'     => $message,
				'check_url'   => Changes::PLUGINS === $key || $cron_off ? VerifyPage::url( array( 'tab' => 'all' ) ) : '',
				'dismiss_url' => wp_nonce_url( admin_url( 'admin-post.php?action=agentwelcome_dismiss&key=' . rawurlencode( (string) $key ) ), 'agentwelcome_dismiss' ),
			);
		}

		return $notices;
	}

	/**
	 * Builds the text of a change notice.
	 *
	 * @param string               $key           Notice key, REFRESH or PLUGINS from the Changes class.
	 * @param array<string, mixed> $change        Saved details of the change.
	 * @param bool                 $cron_disabled Whether WP-Cron is disabled, which changes what is promised.
	 * @return string The sentence, or an empty string for an unknown key or reason.
	 */
	public static function change_message( string $key, array $change, bool $cron_disabled = false ): string {
		if ( Changes::PLUGINS === $key ) {
			$count = max( 1, (int) ( $change['count'] ?? 1 ) );

			return sprintf(
				/* translators: %s: number of plugin changes. */
				_n( '%s plugin was activated, deactivated or updated. If it changes how your pages look, check whether any stored page is out of date.', '%s plugins were activated, deactivated or updated. If they change how your pages look, check whether any stored page is out of date.', $count, 'agentwelcome' ),
				number_format_i18n( $count )
			);
		}

		if ( Changes::REFRESH !== $key ) {
			return '';
		}

		$reasons = array(
			'theme'        => __( 'The active theme changed', 'agentwelcome' ),
			'theme_update' => __( 'The active theme was updated', 'agentwelcome' ),
			'customizer'   => __( 'The Customizer was saved', 'agentwelcome' ),
		);
		$reason  = $reasons[ (string) ( $change['reason'] ?? '' ) ] ?? '';

		if ( '' === $reason ) {
			return '';
		}

		if ( $cron_disabled ) {
			return $reason . '. ' . __( 'WP-Cron is disabled, so stored pages do not refresh by themselves. Run Check all pages, then update the ones that are out of date.', 'agentwelcome' );
		}

		return $reason . '. ' . __( 'AgentWelcome is refreshing the stored Markdown of every page in the background.', 'agentwelcome' );
	}
}
