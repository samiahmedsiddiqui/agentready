<?php
/**
 * Settings screen under Settings > AgentReady.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Admin;

use AgentReady\Content\PostTypes;
use AgentReady\Contracts\Hookable;
use AgentReady\Http\Urls;
use AgentReady\Settings\Options;
use AgentReady\Settings\Sanitizer;

defined( 'ABSPATH' ) || exit;

final class SettingsPage implements Hookable {

	public const SLUG = 'agentready';

	private const GROUP = 'agentready_group';

	public function __construct(
		private Options $options,
		private PostTypes $post_types,
		private Sanitizer $sanitizer,
		private PreviewTool $preview
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AGENTREADY_FILE ), array( $this, 'action_links' ) );
	}

	public function add_page(): void {
		add_options_page(
			__( 'AgentReady', 'agentready' ),
			__( 'AgentReady', 'agentready' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Options::KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->sanitizer, 'sanitize' ),
				'default'           => Options::defaults(),
			)
		);
	}

	public function enqueue( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_register_script( 'agentready-settings', false, array(), AGENTREADY_VERSION, true );
		wp_enqueue_script( 'agentready-settings' );
		wp_add_inline_script(
			'agentready-settings',
			"document.addEventListener('DOMContentLoaded',function(){var t=document.getElementById('agentready-http-auth'),f=document.getElementById('agentready-http-auth-fields');if(!t||!f){return;}var s=function(){f.style.display=t.checked?'':'none';};t.addEventListener('change',s);s();});"
		);
	}

	/**
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'agentready' ) . '</a>' );

		return $links;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch.
		$tab = 'preview' === $tab ? 'preview' : 'general';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AgentReady', 'agentready' ); ?></h1>
			<?php
			settings_errors();
			$this->notices();
			?>
			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ); ?>"><?php esc_html_e( 'Settings', 'agentready' ); ?></a>
				<a class="nav-tab <?php echo 'preview' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::SLUG . '&tab=preview' ) ); ?>"><?php esc_html_e( 'Preview', 'agentready' ); ?></a>
			</nav>
			<?php
			if ( 'preview' === $tab ) {
				$this->preview->render();
			} else {
				$this->render_form();
			}
			?>
		</div>
		<?php
	}

	private function notices(): void {
		if ( ! Urls::has_pretty_permalinks() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				wp_kses(
					sprintf(
						/* translators: %s: link to the Permalinks settings screen. */
						__( 'AgentReady needs pretty permalinks. Plain permalinks (such as <code>?p=123</code>) are not supported, so no Markdown pages or llms.txt are served. Choose another structure in %s.', 'agentready' ),
						'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Settings → Permalinks', 'agentready' ) . '</a>'
					),
					array(
						'code' => array(),
						'a'    => array( 'href' => array() ),
					)
				)
			);
		}

		if ( file_exists( get_home_path() . 'llms.txt' ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'A physical llms.txt file exists in the site root. The web server serves it before WordPress runs, so AgentReady cannot replace it. Delete or rename the file to use the generated one.', 'agentready' )
			);
		}

		$yoast = get_option( 'wpseo' );

		if ( is_array( $yoast ) && ! empty( $yoast['enable_llms_txt'] ) ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				esc_html__( 'Yoast SEO also generates an llms.txt. While AgentReady is active, it answers /llms.txt first. Turn off one of the two to avoid confusion.', 'agentready' )
			);
		}
	}

	private function render_form(): void {
		$name     = Options::KEY;
		$excluded = array_map( 'absint', (array) $this->options->get( 'excluded_terms', array() ) );
		$locked   = $this->options->http_auth_from_constants();
		$has_pass = '' !== (string) $this->options->get( 'http_auth_password', '' );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( self::GROUP ); ?>

			<h2><?php esc_html_e( 'Post types', 'agentready' ); ?></h2>
			<p><?php esc_html_e( 'Every public post type is served as Markdown by default. Untick the ones to leave out.', 'agentready' ); ?></p>
			<?php foreach ( $this->post_types->available() as $type ) : ?>
				<?php $object = get_post_type_object( $type ); ?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled_post_types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( $this->post_types->is_enabled( $type ) ); ?> />
					<?php echo esc_html( $object ? $object->labels->name : $type ); ?> <code><?php echo esc_html( $type ); ?></code>
				</label><br />
			<?php endforeach; ?>

			<h2><?php esc_html_e( 'Exclusions', 'agentready' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="agentready-excluded-posts"><?php esc_html_e( 'IDs and URL patterns', 'agentready' ); ?></label></th>
					<td>
						<textarea id="agentready-excluded-posts" name="<?php echo esc_attr( $name ); ?>[excluded_posts]" rows="5" class="large-text code"><?php echo esc_textarea( (string) $this->options->get( 'excluded_posts', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One per line. A number is a post ID. Anything else is a path pattern where * matches anything, for example checkout/* or privacy.', 'agentready' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="agentready-excluded-terms"><?php esc_html_e( 'Taxonomy terms', 'agentready' ); ?></label></th>
					<td>
						<select id="agentready-excluded-terms" name="<?php echo esc_attr( $name ); ?>[excluded_terms][]" multiple size="8" style="min-width:320px">
							<?php foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) : ?>
								<?php
									$terms = get_terms(
										array(
											'taxonomy'   => $taxonomy->name,
											'hide_empty' => false,
											'number'     => 200,
										)
									);
								?>
								<?php if ( is_wp_error( $terms ) || empty( $terms ) ) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<optgroup label="<?php echo esc_attr( $taxonomy->labels->name ); ?>">
									<?php foreach ( $terms as $term ) : ?>
										<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $excluded, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Posts in any selected term are left out. You can also hide a single page from its editor screen.', 'agentready' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Rendering', 'agentready' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="agentready-selector"><?php esc_html_e( 'Content selector', 'agentready' ); ?></label></th>
					<td>
						<input type="text" id="agentready-selector" class="large-text code" name="<?php echo esc_attr( $name ); ?>[content_selector]" value="<?php echo esc_attr( (string) $this->options->get( 'content_selector', Options::DEFAULT_SELECTOR ) ); ?>" />
						<p class="description"><?php esc_html_e( 'Comma-separated CSS selectors, tried in order, that mark the main content of a page. Supports tag, #id, .class and [attribute]. If none match, the whole body is used.', 'agentready' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'HTTP Basic authentication', 'agentready' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="agentready-http-auth" name="<?php echo esc_attr( $name ); ?>[http_auth_enabled]" value="1" <?php checked( (bool) $this->options->get( 'http_auth_enabled' ) ); ?> />
							<?php esc_html_e( 'This site is protected with HTTP Basic authentication', 'agentready' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'AgentReady reads each page with a request to your own site. If the site asks for a username and password, enable this so that request can get through. The credentials are only sent to this site.', 'agentready' ); ?></p>
						<div id="agentready-http-auth-fields" style="margin-top:12px">
							<?php if ( $locked ) : ?>
								<p><em><?php esc_html_e( 'The credentials are set with AGENTREADY_HTTP_AUTH_USER and AGENTREADY_HTTP_AUTH_PASSWORD in wp-config.php.', 'agentready' ); ?></em></p>
							<?php endif; ?>
							<p>
								<label for="agentready-http-user"><?php esc_html_e( 'Username', 'agentready' ); ?></label><br />
								<input type="text" id="agentready-http-user" class="regular-text" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[http_auth_user]" value="<?php echo esc_attr( (string) $this->options->get( 'http_auth_user', '' ) ); ?>" <?php disabled( $locked ); ?> />
							</p>
							<p>
								<label for="agentready-http-pass"><?php esc_html_e( 'Password', 'agentready' ); ?></label><br />
								<input type="password" id="agentready-http-pass" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $name ); ?>[http_auth_password]" value="" placeholder="<?php echo esc_attr( $has_pass ? '••••••••' : '' ); ?>" <?php disabled( $locked ); ?> />
								<span class="description"><?php esc_html_e( 'Leave blank to keep the saved password. Clear the username to remove the credentials.', 'agentready' ); ?></span>
							</p>
						</div>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}
}
