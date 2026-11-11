<?php
/**
 * Describes the stored copy of one page for the editor box and the REST API.
 *
 * @package AgentReady\Cache
 */

declare(strict_types=1);

namespace AgentReady\Cache;

use AgentReady\Content\Eligibility;
use AgentReady\Environment\Environment;
use AgentReady\Rendering\RenderResult;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Describes the state of one page for the editor box, the list tables and the REST API. */
final class PageStatus {

	/** State of a page that is not served, for example because it is excluded. */
	public const UNAVAILABLE = 'unavailable';

	/**
	 * @param Store       $store       Stored copies.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 */
	public function __construct(
		private Store $store,
		private Eligibility $eligibility,
		private Environment $environment
	) {}

	/**
	 * Builds the status of a page.
	 *
	 * @param WP_Post $post The page.
	 * @return array{state: string, label: string, detail: string, can_regenerate: bool, can_clear: bool} State, a sentence for people, extra detail, and which actions apply.
	 */
	public function for_post( WP_Post $post ): array {
		$entry = $this->store->get( $post->ID, false );
		$base  = array(
			'detail'         => '',
			'can_regenerate' => false,
			'can_clear'      => null !== $entry,
		);

		$env_reason = $this->environment->reason();

		if ( null !== $env_reason ) {
			return array_merge( $base, $this->unavailable( $this->environment->describe( $env_reason ) ) );
		}

		$reason = $this->eligibility->reason( $post );

		if ( null !== $reason ) {
			return array_merge( $base, $this->unavailable( $this->eligibility->describe( $reason ) ) );
		}

		$base['can_regenerate'] = true;
		$state                  = Freshness::classify( $entry, AGENTREADY_VERSION );

		if ( null === $entry || Freshness::MISSING === $state ) {
			return array_merge(
				$base,
				array(
					'state' => Freshness::MISSING,
					'label' => __( 'Not generated yet. It is created on the first request.', 'agentready' ),
				)
			);
		}

		$labels = array(
			Freshness::FRESH    => __( 'Up to date.', 'agentready' ),
			Freshness::STALE    => RenderResult::FALLBACK === $entry->source
				? __( 'Built from the post content only. A new attempt is made later.', 'agentready' )
				: __( 'Out of date. The old copy is served while a new one is built.', 'agentready' ),
			Freshness::OUTDATED => __( 'Built by an older plugin version. It is rebuilt on the next request.', 'agentready' ),
		);

		return array_merge(
			$base,
			array(
				'state'  => $state,
				'label'  => $labels[ $state ],
				'detail' => sprintf(
					/* translators: 1: date and time, 2: how the page was read. */
					__( 'Generated %1$s. Source: %2$s.', 'agentready' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry->timestamp() ),
					RenderResult::LOOPBACK === $entry->source ? __( 'rendered page', 'agentready' ) : __( 'post content', 'agentready' )
				),
			)
		);
	}

	/**
	 * Status of a page that is not served.
	 *
	 * @param string $label Reason, as a sentence.
	 * @return array{state: string, label: string} The unavailable state with the reason.
	 */
	private function unavailable( string $label ): array {
		return array(
			'state' => self::UNAVAILABLE,
			'label' => sprintf(
				/* translators: %s: reason. */
				__( 'Not served. %s', 'agentready' ),
				$label
			),
		);
	}
}
