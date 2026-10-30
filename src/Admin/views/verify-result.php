<?php
/**
 * Verify result: verdict, update button, and the preview, raw, changes and coverage panes.
 *
 * @package AgentReady
 */

use AgentReady\Rendering\RenderResult;
use AgentReady\Verify\Comparison;
use AgentReady\Verify\VerifyResult;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$result   = $vars['result'];
$built    = $result->page;
$coverage = $result->coverage;
$entry    = $result->entry;
$state    = $result->state;
$verdicts = array(
	VerifyResult::IN_SYNC    => __( 'In sync', 'agentready' ),
	VerifyResult::DIFFERS    => __( 'Out of date', 'agentready' ),
	VerifyResult::NOT_STORED => __( 'Not stored yet', 'agentready' ),
	VerifyResult::NOT_SERVED => __( 'Not served', 'agentready' ),
);
$messages = array(
	VerifyResult::IN_SYNC    => __( 'The stored copy matches what the page produces now.', 'agentready' ),
	VerifyResult::DIFFERS    => sprintf(
		/* translators: %s: number of lines. */
		_n( '%s line differs from what the page produces now.', '%s lines differ from what the page produces now.', $result->changed, 'agentready' ),
		number_format_i18n( $result->changed )
	),
	VerifyResult::NOT_STORED => __( 'There is no stored copy yet. It is created on the first request, or you can create it now.', 'agentready' ),
	VerifyResult::NOT_SERVED => $result->reason,
);
?>
<section class="agentready-card">
	<h2><?php echo esc_html( $vars['title'] ); ?></h2>
	<p class="agentready-verdict" data-agentready-verdict="<?php echo esc_attr( $state ); ?>">
		<span class="agentready-dot" aria-hidden="true"></span>
		<strong><?php echo esc_html( $verdicts[ $state ] ); ?>.</strong>
		<?php echo esc_html( $messages[ $state ] ); ?>
	</p>
	<dl class="agentready-result-grid">
		<dt><?php esc_html_e( 'Post', 'agentready' ); ?></dt>
		<dd>#<?php echo esc_html( (string) $vars['post']->ID ); ?> (<?php echo esc_html( $vars['post']->post_type ); ?>)</dd>
		<?php if ( VerifyResult::NOT_SERVED !== $state && null !== $vars['md_url'] ) : ?>
			<dt><?php esc_html_e( 'Markdown URL', 'agentready' ); ?></dt>
			<dd><a href="<?php echo esc_url( $vars['md_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $vars['md_url'] ); ?></a></dd>
		<?php endif; ?>
		<?php if ( null !== $entry ) : ?>
			<dt><?php esc_html_e( 'Stored copy', 'agentready' ); ?></dt>
			<dd>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: date and time, 2: how the page was read, 3: plugin version. */
						__( 'Generated %1$s from the %2$s by AgentReady %3$s.', 'agentready' ),
						$vars['stored_at'],
						RenderResult::LOOPBACK === $entry->source ? __( 'rendered page', 'agentready' ) : __( 'post content', 'agentready' ),
						$entry->generator_version
					)
				);
				?>
			</dd>
		<?php endif; ?>
	</dl>
	<?php if ( '' !== $vars['update_url'] && ( VerifyResult::DIFFERS === $state || VerifyResult::NOT_STORED === $state ) ) : ?>
		<p class="agentready-actions">
			<a class="button button-primary" href="<?php echo esc_url( $vars['update_url'] ); ?>"><?php echo esc_html( VerifyResult::DIFFERS === $state ? __( 'Update stored copy', 'agentready' ) : __( 'Create stored copy', 'agentready' ) ); ?></a>
		</p>
		<?php if ( $vars['applied'] ) : ?>
			<p class="description"><?php esc_html_e( 'The button saves a copy built with your saved settings, not the selectors tried above.', 'agentready' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( VerifyResult::NOT_SERVED !== $state ) : ?>
		<p class="description"><?php esc_html_e( 'Servers and CDNs may keep .md and llms.txt for a few minutes (AgentReady allows 5). If a change does not show yet, add a query string to the URL, such as ?t=123, to skip that cache.', 'agentready' ); ?></p>
	<?php endif; ?>
</section>
<?php if ( null !== $built ) : ?>
	<section class="agentready-card" data-agentready-panes>
		<div class="agentready-pane-tabs" role="tablist">
			<button type="button" class="button" role="tab" data-agentready-pane-tab="preview"><?php esc_html_e( 'Preview', 'agentready' ); ?></button>
			<button type="button" class="button" role="tab" data-agentready-pane-tab="raw"><?php esc_html_e( 'Raw Markdown', 'agentready' ); ?></button>
			<button type="button" class="button" role="tab" data-agentready-pane-tab="changes">
				<?php
				echo esc_html(
					VerifyResult::DIFFERS === $state
						/* translators: %s: number of changed lines. */
						? sprintf( __( 'Changes (%s)', 'agentready' ), number_format_i18n( $result->changed ) )
						: __( 'Changes', 'agentready' )
				);
				?>
			</button>
			<button type="button" class="button" role="tab" data-agentready-pane-tab="coverage"><?php esc_html_e( 'Page text', 'agentready' ); ?></button>
		</div>

		<div class="agentready-pane" data-agentready-pane="preview">
			<p class="description"><?php esc_html_e( 'An approximate preview of the freshly built Markdown. It needs JavaScript.', 'agentready' ); ?></p>
			<div id="agentready-rendered" class="agentready-rendered"></div>
		</div>

		<div class="agentready-pane" data-agentready-pane="raw">
			<div class="agentready-toolbar">
				<span><strong><?php esc_html_e( 'Source:', 'agentready' ); ?></strong> <?php echo esc_html( RenderResult::LOOPBACK === $built->source ? __( 'Rendered page (loopback request)', 'agentready' ) : __( 'Post content (fallback)', 'agentready' ) ); ?></span>
				<button type="button" class="button" data-agentready-copy="agentready-markdown" data-copied="<?php esc_attr_e( 'Copied', 'agentready' ); ?>"><?php esc_html_e( 'Copy Markdown', 'agentready' ); ?></button>
			</div>
			<textarea id="agentready-markdown" class="agentready-markdown" rows="24" readonly><?php echo esc_textarea( $built->markdown ); ?></textarea>
		</div>

		<div class="agentready-pane" data-agentready-pane="changes">
			<?php if ( VerifyResult::NOT_STORED === $state ) : ?>
				<p><?php esc_html_e( 'There is no stored copy to compare with.', 'agentready' ); ?></p>
			<?php elseif ( VerifyResult::IN_SYNC === $state ) : ?>
				<p><?php esc_html_e( 'No differences.', 'agentready' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Lines in the stored copy are red, lines in the fresh Markdown are green.', 'agentready' ); ?></p>
				<div class="agentready-diff">
					<?php foreach ( array_slice( Comparison::condense( $result->diff ), 0, 400 ) as $row ) : ?>
						<?php if ( Comparison::GAP === $row['type'] ) : ?>
							<div class="agentready-diff-gap">
								<?php
								/* translators: %s: number of unchanged lines. */
								echo esc_html( sprintf( _n( '%s unchanged line', '%s unchanged lines', (int) $row['text'], 'agentready' ), number_format_i18n( (int) $row['text'] ) ) );
								?>
							</div>
						<?php else : ?>
							<div class="agentready-diff-<?php echo esc_attr( $row['type'] ); ?>"><span aria-hidden="true"><?php echo esc_html( Comparison::ADD === $row['type'] ? '+' : ( Comparison::DEL === $row['type'] ? '−' : ' ' ) ); ?></span><code><?php echo esc_html( $row['text'] ); ?></code></div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="agentready-pane" data-agentready-pane="coverage">
			<?php if ( null === $coverage ) : ?>
				<p><?php esc_html_e( 'The page text was not checked.', 'agentready' ); ?></p>
			<?php else : ?>
				<?php $percent = $coverage['total'] > 0 ? (int) round( $coverage['found'] / $coverage['total'] * 100 ) : 100; ?>
				<p>
					<strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: found text blocks, 2: all text blocks, 3: percent. */
								__( '%1$s of %2$s text blocks from the page are in the Markdown (%3$s%%).', 'agentready' ),
								number_format_i18n( $coverage['found'] ),
								number_format_i18n( $coverage['total'] ),
								number_format_i18n( $percent )
							)
						);
						?>
					</strong>
				</p>
				<?php if ( ! empty( $coverage['missing'] ) ) : ?>
					<h3><?php esc_html_e( 'On the page, but not in the Markdown', 'agentready' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Text that was lost in the conversion. Images, embeds and some widgets cannot become text.', 'agentready' ); ?></p>
					<ul class="agentready-blocks">
						<?php foreach ( array_slice( $coverage['missing'], 0, 50 ) as $block ) : ?>
							<li><?php echo esc_html( $block ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( ! empty( $result->excluded ) ) : ?>
					<h3>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of text blocks. */
								_n( 'Removed by the exclusions (%s block)', 'Removed by the exclusions (%s blocks)', count( $result->excluded ), 'agentready' ),
								number_format_i18n( count( $result->excluded ) )
							)
						);
						?>
					</h3>
					<p class="description"><?php esc_html_e( 'Text inside the content area that the built-in or your own exclusions take out on purpose. Check that nothing important is here.', 'agentready' ); ?></p>
					<ul class="agentready-blocks">
						<?php foreach ( array_slice( $result->excluded, 0, 20 ) as $block ) : ?>
							<li><?php echo esc_html( $block ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( empty( $coverage['missing'] ) && empty( $result->excluded ) ) : ?>
					<p><?php esc_html_e( 'Everything on the page is in the Markdown.', 'agentready' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>
