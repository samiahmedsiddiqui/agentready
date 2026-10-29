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
use AgentReady\Rendering\Extractor;
use AgentReady\Settings\Options;
use AgentReady\Settings\Sanitizer;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class SettingsPage implements Hookable {

	public const SLUG = 'agentready';

	private const GROUP = 'agentready_group';

	private const TERM_LIMIT = 200;

	private const AUTHOR_URL = 'https://www.linkedin.com/in/sami-ahmed-siddiqui/';

	private const COMMON_CONTENT = array( 'main', '[role="main"]', 'article', '#content', '#main', '#primary', '.site-content', '.entry-content', '.post-content', '.page-content' );

	private const COMMON_EXCLUDES = array( '.breadcrumb', '.breadcrumbs', '.cookie-notice', '.related-posts', '.post-navigation', '.author-box', '.share-buttons', '.newsletter-signup' );

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
		add_filter( 'admin_footer_text', array( $this, 'footer_text' ), 11 );
		add_filter( 'update_footer', array( $this, 'footer_version' ), 11 );
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

	public function footer_text( mixed $text ): mixed {
		if ( ! $this->is_settings_screen() ) {
			return $text;
		}

		return wp_kses(
			sprintf(
				/* translators: %s: author name, linked to their profile. */
				__( 'Thank you for using AgentReady. Built by %s.', 'agentready' ),
				'<a href="' . esc_url( self::AUTHOR_URL ) . '" target="_blank" rel="noopener noreferrer">Sami Ahmed Siddiqui</a>'
			),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		);
	}

	public function footer_version( mixed $text ): mixed {
		if ( ! $this->is_settings_screen() ) {
			return $text;
		}

		/* translators: %s: plugin version. */
		return esc_html( sprintf( __( 'AgentReady %s', 'agentready' ), AGENTREADY_VERSION ) );
	}

	private function is_settings_screen(): bool {
		$screen = get_current_screen();

		return $screen instanceof \WP_Screen && 'settings_page_' . self::SLUG === $screen->id;
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

		wp_enqueue_style( 'agentready-admin', AGENTREADY_URL . 'assets/css/admin.css', array(), $this->asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'agentready-admin', AGENTREADY_URL . 'assets/js/admin.js', array(), $this->asset_version( 'assets/js/admin.js' ), true );
	}

	/**
	 * The plugin version plus the file's modification time, so a changed file is never served from a stale browser cache.
	 */
	private function asset_version( string $file ): string {
		$path = AGENTREADY_PATH . $file;

		return AGENTREADY_VERSION . '.' . ( file_exists( $path ) ? (int) filemtime( $path ) : 0 );
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
		<div class="wrap agentready">
			<h1 class="agentready-title">
				<?php esc_html_e( 'AgentReady', 'agentready' ); ?>
				<span class="agentready-version">v<?php echo esc_html( AGENTREADY_VERSION ); ?></span>
			</h1>
			<p class="agentready-tagline"><?php esc_html_e( 'Serve an llms.txt index and a Markdown version of every page, so AI agents can read your content.', 'agentready' ); ?></p>
			<?php $this->notices(); ?>
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

		$file_exists = file_exists( get_home_path() . 'llms.txt' );
		$yoast       = get_option( 'wpseo' );

		if ( is_array( $yoast ) && ! empty( $yoast['enable_llms_txt'] ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html(
					$file_exists
						? __( 'Yoast SEO\'s llms.txt feature is on. Yoast saves a physical llms.txt file, which the web server serves before WordPress runs, so AgentReady\'s llms.txt is not used while that file exists. Turn the feature off in the Yoast SEO settings to use AgentReady\'s.', 'agentready' )
						: __( 'Yoast SEO\'s llms.txt feature is on, but its file does not exist yet, so AgentReady answers /llms.txt for now. When Yoast creates the file, it takes over. Turn the feature off in the Yoast SEO settings to keep using AgentReady\'s.', 'agentready' )
				)
			);
		} elseif ( $file_exists ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'A physical llms.txt file exists in the site root. The web server serves it before WordPress runs, so AgentReady cannot replace it. Delete or rename the file to use the generated one.', 'agentready' )
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
			<div class="agentready-grid">
				<div class="agentready-main">

					<section class="agentready-card">
						<h2><?php esc_html_e( 'Post types', 'agentready' ); ?></h2>
						<p class="agentready-intro"><?php esc_html_e( 'Every public post type is served by default. Tick the ones to leave out.', 'agentready' ); ?></p>
						<div class="agentready-types">
							<?php foreach ( $this->post_types->available() as $type ) : ?>
								<?php $object = get_post_type_object( $type ); ?>
								<label class="agentready-type">
									<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[excluded_post_types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $this->post_types->excluded(), true ) ); ?> />
									<span class="agentready-type-name"><?php echo esc_html( $object ? $object->labels->name : $type ); ?></span>
									<span class="agentready-type-meta"><?php echo esc_html( $this->published_label( $type ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</section>

					<section class="agentready-card">
						<h2><?php esc_html_e( 'Exclusions', 'agentready' ); ?></h2>
						<p class="agentready-intro"><?php esc_html_e( 'Leave out specific pages. You can also hide one page from its own editor screen.', 'agentready' ); ?></p>
						<div class="agentready-field">
							<label for="agentready-excluded-posts"><?php esc_html_e( 'IDs and URL patterns', 'agentready' ); ?></label>
							<textarea id="agentready-excluded-posts" name="<?php echo esc_attr( $name ); ?>[excluded_posts]" rows="4" class="large-text code" placeholder="42&#10;checkout/*&#10;privacy"><?php echo esc_textarea( (string) $this->options->get( 'excluded_posts', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One per line. A number is a post ID. Anything else is a path where * matches anything.', 'agentready' ); ?></p>
						</div>
						<?php $this->render_terms( $excluded ); ?>
					</section>

					<section class="agentready-card">
						<h2><?php esc_html_e( 'Editor', 'agentready' ); ?></h2>
						<p class="agentready-intro"><?php esc_html_e( 'Choose whether editors see AgentReady on the edit screen.', 'agentready' ); ?></p>
						<div class="agentready-field">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_meta_box]" value="1" <?php checked( (bool) $this->options->get( 'show_meta_box', true ) ); ?> />
								<?php esc_html_e( 'Show the AgentReady box on the edit screen', 'agentready' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'The box lets editors hide a page and write its llms.txt description. Hiding the box does not remove anything already saved on a page.', 'agentready' ); ?></p>
						</div>
					</section>

					<section class="agentready-card">
						<h2><?php esc_html_e( 'Page content', 'agentready' ); ?></h2>
						<p class="agentready-intro"><?php esc_html_e( 'Choose which part of each page becomes the Markdown. Use the Preview tab to try changes before saving.', 'agentready' ); ?></p>
						<div class="agentready-field">
							<label for="agentready-selector"><?php esc_html_e( 'Content selector', 'agentready' ); ?></label>
							<input type="text" id="agentready-selector" class="large-text code" name="<?php echo esc_attr( $name ); ?>[content_selector]" value="<?php echo esc_attr( $this->options->content_selector() ); ?>" placeholder="<?php esc_attr_e( 'Auto-detect', 'agentready' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave empty to detect the main content automatically. Or enter one or more CSS selectors, tried in order.', 'agentready' ); ?></p>
							<?php $this->chips( self::COMMON_CONTENT, 'agentready-selector', 'comma' ); ?>
						</div>
						<div class="agentready-field">
							<label for="agentready-strip"><?php esc_html_e( 'Elements to exclude', 'agentready' ); ?></label>
							<textarea id="agentready-strip" name="<?php echo esc_attr( $name ); ?>[strip_selectors]" rows="3" class="large-text code" placeholder=".cookie-notice&#10;article footer"><?php echo esc_textarea( (string) $this->options->get( 'strip_selectors', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One CSS selector per line. These are removed in addition to the built-in list. Unsupported selectors are ignored.', 'agentready' ); ?></p>
							<?php $this->chips( self::COMMON_EXCLUDES, 'agentready-strip', 'line' ); ?>
							<details class="agentready-details" style="margin-top:10px">
								<summary><?php esc_html_e( 'Built-in exclusions', 'agentready' ); ?></summary>
								<p><code><?php echo esc_html( implode( ', ', Extractor::DEFAULT_STRIP ) ); ?></code></p>
								<p class="description"><?php esc_html_e( 'Also removed: elements with role="banner" or role="contentinfo", and any header or footer that is not inside an article, main or section element. Headers and footers inside an article are kept.', 'agentready' ); ?></p>
							</details>
						</div>
					</section>

					<section class="agentready-card">
						<h2><?php esc_html_e( 'Site access', 'agentready' ); ?></h2>
						<p class="agentready-intro"><?php esc_html_e( 'AgentReady reads each page with a request to your own site.', 'agentready' ); ?></p>
						<div class="agentready-field">
							<label>
								<input type="checkbox" id="agentready-http-auth" name="<?php echo esc_attr( $name ); ?>[http_auth_enabled]" value="1" <?php checked( (bool) $this->options->get( 'http_auth_enabled' ) ); ?> />
								<?php esc_html_e( 'This site is protected with HTTP Basic authentication', 'agentready' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Enable this if the site asks for a username and password, so that request can get through. The credentials are stored encrypted and only sent to this site.', 'agentready' ); ?></p>
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
						</div>
					</section>

					<?php submit_button(); ?>
				</div>

				<aside class="agentready-side">
					<?php $this->render_status(); ?>
				</aside>
			</div>
		</form>
		<?php
	}

	private function render_status(): void {
		$pretty = Urls::has_pretty_permalinks();
		?>
		<section class="agentready-card">
			<h2><?php esc_html_e( 'Status', 'agentready' ); ?></h2>
			<ul class="agentready-status">
				<li>
					<?php if ( $pretty ) : ?>
						<span class="dashicons dashicons-yes-alt agentready-ok"></span>
						<span><?php esc_html_e( 'Pretty permalinks are on.', 'agentready' ); ?></span>
					<?php else : ?>
						<span class="dashicons dashicons-warning agentready-warn"></span>
						<span><?php esc_html_e( 'Plain permalinks are on. Nothing is served.', 'agentready' ); ?></span>
					<?php endif; ?>
				</li>
				<?php if ( $pretty ) : ?>
					<li>
						<span class="dashicons dashicons-media-text"></span>
						<span><a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View llms.txt', 'agentready' ); ?></a></span>
					</li>
				<?php endif; ?>
				<?php foreach ( $this->post_types->enabled() as $type ) : ?>
					<?php $object = get_post_type_object( $type ); ?>
					<li>
						<span class="dashicons dashicons-admin-page"></span>
						<span><?php echo esc_html( ( $object ? $object->labels->name : $type ) . ': ' . $this->published_label( $type ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="agentready-card">
			<h2><?php esc_html_e( 'How it works', 'agentready' ); ?></h2>
			<ol class="agentready-steps">
				<li><?php esc_html_e( 'Add .md to a page URL, for example /about.md. The homepage is /index.html.md.', 'agentready' ); ?></li>
				<li><?php esc_html_e( 'The page is read like a visitor sees it, so theme, ACF and builder output is included.', 'agentready' ); ?></li>
				<li><?php esc_html_e( 'Navigation, scripts and forms are removed, and the rest becomes Markdown.', 'agentready' ); ?></li>
			</ol>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::SLUG . '&tab=preview' ) ); ?>"><?php esc_html_e( 'Try the preview', 'agentready' ); ?></a></p>
		</section>
		<?php
	}

	/**
	 * @param int[] $excluded Term IDs that are currently excluded.
	 */
	private function render_terms( array $excluded ): void {
		$groups = array();

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy->name,
					'hide_empty' => false,
					'number'     => self::TERM_LIMIT,
					'orderby'    => 'name',
				)
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$total = wp_count_terms(
				array(
					'taxonomy'   => $taxonomy->name,
					'hide_empty' => false,
				)
			);

			$groups[] = array(
				'taxonomy' => $taxonomy,
				'terms'    => $this->ordered_terms( array_filter( $terms, static fn( $term ): bool => $term instanceof WP_Term ), (bool) $taxonomy->hierarchical ),
				'total'    => is_wp_error( $total ) ? 0 : (int) $total,
			);
		}
		?>
		<div class="agentready-field">
			<span class="agentready-label"><?php esc_html_e( 'Taxonomy terms', 'agentready' ); ?></span>
			<?php if ( empty( $groups ) ) : ?>
				<p class="description"><?php esc_html_e( 'There are no categories, tags or other terms yet.', 'agentready' ); ?></p>
			<?php else : ?>
				<div class="agentready-terms" data-agentready-terms>
					<div class="agentready-chosen" data-agentready-chosen data-label="<?php esc_attr_e( 'Excluded:', 'agentready' ); ?>" aria-live="polite" hidden></div>
					<input type="search" class="agentready-term-search" data-agentready-term-search placeholder="<?php esc_attr_e( 'Search terms', 'agentready' ); ?>" aria-label="<?php esc_attr_e( 'Search terms', 'agentready' ); ?>" />
					<?php foreach ( $groups as $index => $group ) : ?>
						<?php
						$ids      = array_map(
							static fn( array $item ): int => (int) $item['term']->term_id,
							$group['terms']
						);
						$selected = array_intersect( $ids, $excluded );
						?>
						<details class="agentready-term-group" <?php echo 0 === $index || ! empty( $selected ) ? 'open' : ''; ?>>
							<summary><?php echo esc_html( $group['taxonomy']->labels->name ); ?></summary>
							<ul class="agentready-term-list">
								<?php foreach ( $group['terms'] as $item ) : ?>
									<li data-name="<?php echo esc_attr( strtolower( $item['term']->name ) ); ?>" style="--depth:<?php echo (int) $item['depth']; ?>">
										<label>
											<input type="checkbox" name="<?php echo esc_attr( Options::KEY ); ?>[excluded_terms][]" value="<?php echo esc_attr( (string) $item['term']->term_id ); ?>" data-name="<?php echo esc_attr( $item['term']->name ); ?>" data-group="<?php echo esc_attr( $group['taxonomy']->labels->singular_name ); ?>" <?php checked( in_array( (int) $item['term']->term_id, $excluded, true ) ); ?> />
											<?php echo esc_html( $item['term']->name ); ?>
											<span class="agentready-term-count"><?php echo esc_html( number_format_i18n( (int) $item['term']->count ) ); ?></span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
							<?php if ( $group['total'] > self::TERM_LIMIT ) : ?>
								<p class="description" style="padding:0 10px 8px">
									<?php
									/* translators: %s: number of terms listed. */
									echo esc_html( sprintf( __( 'Only the first %s terms are listed.', 'agentready' ), number_format_i18n( self::TERM_LIMIT ) ) );
									?>
								</p>
							<?php endif; ?>
						</details>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Posts in any ticked term are left out. The number is how many posts use the term.', 'agentready' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param WP_Term[] $terms        Terms to order.
	 * @param bool      $hierarchical Whether children are shown under their parents.
	 * @return array<int, array{term: WP_Term, depth: int}>
	 */
	private function ordered_terms( array $terms, bool $hierarchical ): array {
		if ( ! $hierarchical ) {
			return array_map(
				static fn( WP_Term $term ): array => array(
					'term'  => $term,
					'depth' => 0,
				),
				array_values( $terms )
			);
		}

		$listed    = array();
		$by_parent = array();

		foreach ( $terms as $term ) {
			$listed[ $term->term_id ] = true;
		}

		foreach ( $terms as $term ) {
			$by_parent[ isset( $listed[ $term->parent ] ) ? (int) $term->parent : 0 ][] = $term;
		}

		$ordered = array();
		$walk    = static function ( int $parent, int $depth ) use ( &$walk, &$ordered, $by_parent ): void {
			foreach ( $by_parent[ $parent ] ?? array() as $term ) {
				$ordered[] = array(
					'term'  => $term,
					'depth' => $depth,
				);
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};

		$walk( 0, 0 );

		return $ordered;
	}

	/**
	 * @param string[] $items  Selectors to offer.
	 * @param string   $target ID of the field the chips fill.
	 * @param string   $mode   Either "comma" or "line".
	 */
	private function chips( array $items, string $target, string $mode ): void {
		?>
		<div class="agentready-chips">
			<span class="agentready-chips-label"><?php esc_html_e( 'Common:', 'agentready' ); ?></span>
			<?php foreach ( $items as $item ) : ?>
				<button type="button" class="button button-small" data-agentready-add="<?php echo esc_attr( $item ); ?>" data-target="<?php echo esc_attr( $target ); ?>" data-separator="<?php echo esc_attr( $mode ); ?>"><?php echo esc_html( $item ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function published_label( string $type ): string {
		$counts = wp_count_posts( $type );
		$total  = is_object( $counts ) && isset( $counts->publish ) ? (int) $counts->publish : 0;

		/* translators: %s: number of published posts. */
		return sprintf( _n( '%s published', '%s published', $total, 'agentready' ), number_format_i18n( $total ) );
	}
}
