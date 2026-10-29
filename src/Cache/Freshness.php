<?php
/**
 * Classifies a stored copy against the running plugin version.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Cache;

defined( 'ABSPATH' ) || exit;

final class Freshness {

	public const FRESH    = 'fresh';
	public const STALE    = 'stale';
	public const MISSING  = 'missing';
	public const OUTDATED = 'outdated';

	public static function classify( ?Entry $entry, string $version ): string {
		if ( null === $entry ) {
			return self::MISSING;
		}

		if ( $entry->generator_version !== $version ) {
			return self::OUTDATED;
		}

		return Entry::STALE === $entry->state ? self::STALE : self::FRESH;
	}
}
