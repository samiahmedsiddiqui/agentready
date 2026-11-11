<?php
/**
 * Rendering tab: which part of the page becomes Markdown, and site access.
 *
 * @package AgentWelcome
 */

use AgentWelcome\Admin\View;
use AgentWelcome\Rendering\Extractor;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$name     = $vars['name'];
$options  = $vars['options'];
$locked   = $options->http_auth_from_constants();
$has_pass = '' !== (string) $options->get( 'http_auth_password', '' );
?>
<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'Page content', 'agentwelcome' ); ?></h2>
	<p class="agentwelcome-intro"><?php esc_html_e( 'Choose which part of each page becomes the Markdown. Use Verify & Preview to try changes before saving. Saving a change here refreshes the stored Markdown in the background.', 'agentwelcome' ); ?></p>
	<div class="agentwelcome-field">
		<label for="agentwelcome-selector"><?php esc_html_e( 'Content selector', 'agentwelcome' ); ?></label>
		<input type="text" id="agentwelcome-selector" class="large-text code" name="<?php echo esc_attr( $name ); ?>[content_selector]" value="<?php echo esc_attr( $options->content_selector() ); ?>" placeholder="<?php esc_attr_e( 'Auto-detect', 'agentwelcome' ); ?>" />
		<p class="description"><?php esc_html_e( 'Leave empty to detect the main content automatically. Or enter one or more CSS selectors, tried in order.', 'agentwelcome' ); ?></p>
		<?php
		View::render(
			'partials/chips',
			array(
				'items'  => $vars['common_content'],
				'target' => 'agentwelcome-selector',
				'mode'   => 'comma',
			)
		);
		?>
	</div>
	<div class="agentwelcome-field">
		<label for="agentwelcome-strip"><?php esc_html_e( 'Elements to exclude', 'agentwelcome' ); ?></label>
		<textarea id="agentwelcome-strip" name="<?php echo esc_attr( $name ); ?>[strip_selectors]" rows="3" class="large-text code" placeholder=".cookie-notice&#10;article footer"><?php echo esc_textarea( (string) $options->get( 'strip_selectors', '' ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'One CSS selector per line. These are removed in addition to the built-in list. Unsupported selectors are ignored.', 'agentwelcome' ); ?></p>
		<?php
		View::render(
			'partials/chips',
			array(
				'items'  => $vars['common_excludes'],
				'target' => 'agentwelcome-strip',
				'mode'   => 'line',
			)
		);
		?>
		<details class="agentwelcome-details" style="margin-top:10px">
			<summary><?php esc_html_e( 'Built-in exclusions', 'agentwelcome' ); ?></summary>
			<p><code><?php echo esc_html( implode( ', ', Extractor::DEFAULT_STRIP ) ); ?></code></p>
			<p class="description"><?php esc_html_e( 'Also removed: elements with role="banner" or role="contentinfo", and any header or footer that is not inside an article, main or section element. Headers and footers inside an article are kept.', 'agentwelcome' ); ?></p>
		</details>
	</div>
</section>

<section class="agentwelcome-card">
	<h2><?php esc_html_e( 'Site access', 'agentwelcome' ); ?></h2>
	<p class="agentwelcome-intro"><?php esc_html_e( 'AgentWelcome reads each page with a request to your own site.', 'agentwelcome' ); ?></p>
	<div class="agentwelcome-field">
		<label>
			<input type="checkbox" id="agentwelcome-http-auth" name="<?php echo esc_attr( $name ); ?>[http_auth_enabled]" value="1" <?php checked( (bool) $options->get( 'http_auth_enabled' ) ); ?> />
			<?php esc_html_e( 'This site is protected with HTTP Basic authentication', 'agentwelcome' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Enable this if the site asks for a username and password, so that request can get through. The credentials are stored encrypted and only sent to this site.', 'agentwelcome' ); ?></p>
		<div id="agentwelcome-http-auth-fields" style="margin-top:12px">
			<?php if ( $locked ) : ?>
				<p><em><?php esc_html_e( 'The credentials are set with AGENTWELCOME_HTTP_AUTH_USER and AGENTWELCOME_HTTP_AUTH_PASSWORD in wp-config.php.', 'agentwelcome' ); ?></em></p>
			<?php endif; ?>
			<p>
				<label for="agentwelcome-http-user"><?php esc_html_e( 'Username', 'agentwelcome' ); ?></label><br />
				<input type="text" id="agentwelcome-http-user" class="regular-text" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[http_auth_user]" value="<?php echo esc_attr( (string) $options->get( 'http_auth_user', '' ) ); ?>" <?php disabled( $locked ); ?> />
			</p>
			<p>
				<label for="agentwelcome-http-pass"><?php esc_html_e( 'Password', 'agentwelcome' ); ?></label><br />
				<input type="password" id="agentwelcome-http-pass" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $name ); ?>[http_auth_password]" value="" placeholder="<?php echo esc_attr( $has_pass ? '••••••••' : '' ); ?>" <?php disabled( $locked ); ?> />
				<span class="description"><?php esc_html_e( 'Leave blank to keep the saved password. Clear the username to remove the credentials.', 'agentwelcome' ); ?></span>
			</p>
		</div>
	</div>
</section>
