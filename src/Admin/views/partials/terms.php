<?php
/**
 * Grouped taxonomy term checkboxes with search.
 *
 * @package AgentReady
 */

use AgentReady\Settings\Options;

defined( 'ABSPATH' ) || exit;

$groups   = $vars['term_groups'];
$excluded = $vars['excluded_terms'];
$limit    = $vars['term_limit'];
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
					<?php if ( $group['total'] > $limit ) : ?>
						<p class="description" style="padding:0 10px 8px">
							<?php
							/* translators: %s: number of terms listed. */
							echo esc_html( sprintf( __( 'Only the first %s terms are listed.', 'agentready' ), number_format_i18n( $limit ) ) );
							?>
						</p>
					<?php endif; ?>
				</details>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php esc_html_e( 'Posts in any ticked term are left out. The number is how many posts use the term.', 'agentready' ); ?></p>
	<?php endif; ?>
</div>
