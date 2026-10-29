<?php
/**
 * Reads plugin settings with defaults applied.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Settings;

defined( 'ABSPATH' ) || exit;

final class Options {

	public const KEY = 'agentready_settings';

	public const MODE_AUTO   = 'auto';
	public const MODE_CUSTOM = 'custom';
	public const MODE_HYBRID = 'hybrid';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'llms_mode'           => self::MODE_AUTO,
			'llms_intro'          => '',
			'llms_footer'         => '',
			'llms_custom'         => '',
			'excluded_post_types' => array(),
			'excluded_posts'      => '',
			'excluded_terms'      => array(),
			'content_selector'    => 'main',
			'robots_ai_access'    => false,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$saved = get_option( self::KEY, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public function get( string $key, mixed $fallback = null ): mixed {
		$all = $this->all();

		return $all[ $key ] ?? $fallback;
	}
}
