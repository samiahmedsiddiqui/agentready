<?php
/**
 * Sanitizes settings before they are saved.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Settings;

defined( 'ABSPATH' ) || exit;

final class Sanitizer {

	/**
	 * @param mixed $input Raw submitted value.
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = Options::defaults();

		$modes = array( Options::MODE_AUTO, Options::MODE_CUSTOM, Options::MODE_HYBRID );
		$mode  = isset( $input['llms_mode'] ) ? sanitize_key( $input['llms_mode'] ) : $defaults['llms_mode'];

		$excluded = isset( $input['excluded_post_types'] ) && is_array( $input['excluded_post_types'] )
			? array_values( array_map( 'sanitize_key', $input['excluded_post_types'] ) )
			: array();

		$terms = isset( $input['excluded_terms'] ) && is_array( $input['excluded_terms'] )
			? array_values( array_filter( array_map( 'absint', $input['excluded_terms'] ) ) )
			: array();

		return array(
			'llms_mode'          => in_array( $mode, $modes, true ) ? $mode : $defaults['llms_mode'],
			'llms_intro'          => isset( $input['llms_intro'] ) ? sanitize_textarea_field( $input['llms_intro'] ) : '',
			'llms_footer'         => isset( $input['llms_footer'] ) ? sanitize_textarea_field( $input['llms_footer'] ) : '',
			'llms_custom'         => isset( $input['llms_custom'] ) ? sanitize_textarea_field( $input['llms_custom'] ) : '',
			'excluded_post_types' => $excluded,
			'excluded_posts'      => isset( $input['excluded_posts'] ) ? sanitize_textarea_field( $input['excluded_posts'] ) : '',
			'excluded_terms'      => $terms,
			'content_selector'    => isset( $input['content_selector'] ) ? sanitize_text_field( $input['content_selector'] ) : $defaults['content_selector'],
			'robots_ai_access'    => ! empty( $input['robots_ai_access'] ),
		);
	}
}
