<?php
/**
 * Verify result: verdict, update button, and the preview, raw, changes and coverage panes.
 *
 * @package AgentWelcome
 */

use AgentWelcome\Rendering\RenderResult;
use AgentWelcome\Verify\Comparison;
use AgentWelcome\Verify\VerifyResult;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.

$result   = $vars['result'];
$built    = $result->page;
$coverage = $result->coverage;
$entry    = $result->entry;
$state    = $result->state;
$verdicts = array(
	VerifyResult::IN_SYNC    => __( 'In sync', 'agentwelcome' ),
	VerifyResult::DIFFERS    => __( 'Out of date', 'agentwelcome' ),
	VerifyResult::NOT_STORED => __( 'Not stored yet', 'agentwelcome' ),
	VerifyResult::NOT_SERVED => __( 'Not served', 'agentwelcome' ),
);
$messages = array(
	VerifyResult::IN_SYNC    => __( 'The stored copy matches what the page produces now.', 'agentwelcome' ),
	VerifyResult::DIFFERS    => sprintf(
		/* translators: %s: number of lines. */
		_n( '%s line differs from what the page produces now.', '%s lines differ from what the page produces now.', $result->changed, 'agentwelcome' ),
		number_format_i18n( $result->changed )
	),
	VerifyResult::NOT_STORED => __( 'There is no stored copy yet. It is created on the first request, or you can create it now.', 'agentwelcome' ),
	VerifyResult::NOT_SERVED => $result->reason,
);
?>
<section class="agentwelcome-card">
	<h2><?php echo esc_html( $vars['title'] ); ?></h2>
	<p class="agentwelcome-verdict" data-agentwelcome-verdict="<?php echo esc_attr( $state ); ?>">
		<span class="agentwelcome-dot" aria-hidden="true"></span>
		<strong><?php echo esc_html( $verdicts[ $state ] ); ?>.</strong>
		<?php echo esc_html( $messages[ $state ] ); ?>
	</p>
	<dl class="agentwelcome-result-grid">
		<dt><?php esc_html_e( 'Post', 'agentwelcome' ); ?></dt>
		<dd>#<?php echo esc_html( (string) $vars['post']->ID ); ?> (<?php echo esc_html( $vars['post']->post_type ); ?>)</dd>
		<?php if ( VerifyResult::NOT_SERVED !== $state && null !== $vars['md_url'] ) : ?>
			<dt><?php esc_html_e( 'Markdown URL', 'agentwelcome' ); ?></dt>
			<dd><a href="<?php echo esc_url( $vars['md_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $vars['md_url'] ); ?></a></dd>
		<?php endif; ?>
		<?php if ( null !== $entry ) : ?>
			<dt><?php esc_html_e( 'Stored copy', 'agentwelcome' ); ?></dt>
			<dd>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: date and time, 2: how the page was read, 3: plugin version. */
						__( 'Generated %1$s from the %2$s by AgentWelcome %3$s.', 'agentwelcome' ),
						$vars['stored_at'],
						RenderResult::LOOPBACK === $entry->source ? __( 'rendered page', 'agentwelcome' ) : __( 'post content', 'agentwelcome' ),
						$entry->generator_version
					)
				);
				?>
			</dd>
		<?php endif; ?>
	</dl>
	<?php if ( '' !== $vars['update_url'] && ( VerifyResult::DIFFERS === $state || VerifyResult::NOT_STORED === $state ) ) : ?>
		<p class="agentwelcome-actions">
			<a class="button button-primary" href="<?php echo esc_url( $vars['update_url'] ); ?>"><?php echo esc_html( VerifyResult::DIFFERS === $state ? __( 'Update stored copy', 'agentwelcome' ) : __( 'Create stored copy', 'agentwelcome' ) ); ?></a>
		</p>
		<?php if ( $vars['applied'] ) : ?>
			<p class="description"><?php esc_html_e( 'The button saves a copy built with your saved settings, not the selectors tried above.', 'agentwelcome' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( VerifyResult::NOT_SERVED !== $state ) : ?>
		<p class="description"><?php esc_html_e( 'Servers and CDNs may keep .md and llms.txt for a few minutes (AgentWelcome allows 5). If a change does not show yet, add a query string to the URL, such as ?t=123, to skip that cache.', 'agentwelcome' ); ?></p>
	<?php endif; ?>
</section>
<?php if ( null !== $built ) : ?>
	<section class="agentwelcome-card" data-agentwelcome-panes>
		<div class="agentwelcome-pane-tabs" role="tablist">
			<button type="button" class="button" role="tab" data-agentwelcome-pane-tab="preview"><?php esc_html_e( 'Preview', 'agentwelcome' ); ?></button>
			<button type="button" class="button" role="tab" data-agentwelcome-pane-tab="raw"><?php esc_html_e( 'Raw Markdown', 'agentwelcome' ); ?></button>
			<button type="button" class="button" role="tab" data-agentwelcome-pane-tab="changes">
				<?php
				echo esc_html(
					VerifyResult::DIFFERS === $state
						/* translators: %s: number of changed lines. */
						? sprintf( __( 'Changes (%s)', 'agentwelcome' ), number_format_i18n( $result->changed ) )
						: __( 'Changes', 'agentwelcome' )
				);
				?>
			</button>
			<button type="button" class="button" role="tab" data-agentwelcome-pane-tab="coverage"><?php esc_html_e( 'Page text', 'agentwelcome' ); ?></button>
		</div>

		<div class="agentwelcome-pane" data-agentwelcome-pane="preview">
			<p class="description"><?php esc_html_e( 'An approximate preview of the freshly built Markdown. It needs JavaScript.', 'agentwelcome' ); ?></p>
			<div id="agentwelcome-rendered" class="agentwelcome-rendered"></div>
		</div>

		<div class="agentwelcome-pane" data-agentwelcome-pane="raw">
			<div class="agentwelcome-toolbar">
				<span><strong><?php esc_html_e( 'Source:', 'agentwelcome' ); ?></strong> <?php echo esc_html( RenderResult::LOOPBACK === $built->source ? __( 'Rendered page (loopback request)', 'agentwelcome' ) : __( 'Post content (fallback)', 'agentwelcome' ) ); ?></span>
				<button type="button" class="button" data-agentwelcome-copy="agentwelcome-markdown" data-copied="<?php esc_attr_e( 'Copied', 'agentwelcome' ); ?>"><?php esc_html_e( 'Copy Markdown', 'agentwelcome' ); ?></button>
			</div>
			<textarea id="agentwelcome-markdown" class="agentwelcome-markdown" rows="24" readonly><?php echo esc_textarea( $built->markdown ); ?></textarea>
		</div>

		<div class="agentwelcome-pane" data-agentwelcome-pane="changes">
			<?php if ( VerifyResult::NOT_STORED === $state ) : ?>
				<p><?php esc_html_e( 'There is no stored copy to compare with.', 'agentwelcome' ); ?></p>
			<?php elseif ( VerifyResult::IN_SYNC === $state ) : ?>
				<p><?php esc_html_e( 'No differences.', 'agentwelcome' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Lines in the stored copy are red, lines in the fresh Markdown are green.', 'agentwelcome' ); ?></p>
				<div class="agentwelcome-diff">
					<?php foreach ( array_slice( Comparison::condense( $result->diff ), 0, 400 ) as $row ) : ?>
						<?php if ( Comparison::GAP === $row['type'] ) : ?>
							<div class="agentwelcome-diff-gap">
								<?php
								/* translators: %s: number of unchanged lines. */
								echo esc_html( sprintf( _n( '%s unchanged line', '%s unchanged lines', (int) $row['text'], 'agentwelcome' ), number_format_i18n( (int) $row['text'] ) ) );
								?>
							</div>
						<?php else : ?>
							<div class="agentwelcome-diff-<?php echo esc_attr( $row['type'] ); ?>"><span aria-hidden="true"><?php echo esc_html( Comparison::ADD === $row['type'] ? '+' : ( Comparison::DEL === $row['type'] ? '−' : ' ' ) ); ?></span><code><?php echo esc_html( $row['text'] ); ?></code></div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="agentwelcome-pane" data-agentwelcome-pane="coverage">
			<?php if ( null === $coverage ) : ?>
				<p><?php esc_html_e( 'The page text was not checked.', 'agentwelcome' ); ?></p>
			<?php else : ?>
				<?php $percent = $coverage['total'] > 0 ? (int) round( $coverage['found'] / $coverage['total'] * 100 ) : 100; ?>
				<p>
					<strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: found text blocks, 2: all text blocks, 3: percent. */
								__( '%1$s of %2$s text blocks from the page are in the Markdown (%3$s%%).', 'agentwelcome' ),
								number_format_i18n( $coverage['found'] ),
								number_format_i18n( $coverage['total'] ),
								number_format_i18n( $percent )
							)
						);
						?>
					</strong>
				</p>
				<?php if ( ! empty( $coverage['missing'] ) ) : ?>
					<h3><?php esc_html_e( 'On the page, but not in the Markdown', 'agentwelcome' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Text that was lost in the conversion. Images, embeds and some widgets cannot become text.', 'agentwelcome' ); ?></p>
					<ul class="agentwelcome-blocks">
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
								_n( 'Removed by the exclusions (%s block)', 'Removed by the exclusions (%s blocks)', count( $result->excluded ), 'agentwelcome' ),
								number_format_i18n( count( $result->excluded ) )
							)
						);
						?>
					</h3>
					<p class="description"><?php esc_html_e( 'Text inside the content area that the built-in or your own exclusions take out on purpose. Check that nothing important is here.', 'agentwelcome' ); ?></p>
					<ul class="agentwelcome-blocks">
						<?php foreach ( array_slice( $result->excluded, 0, 20 ) as $block ) : ?>
							<li><?php echo esc_html( $block ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( empty( $coverage['missing'] ) && empty( $result->excluded ) ) : ?>
					<p><?php esc_html_e( 'Everything on the page is in the Markdown.', 'agentwelcome' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>
