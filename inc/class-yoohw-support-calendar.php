<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared clock for automatic topic lifecycle actions. */
class YoOhw_Support_Calendar {

	public static function is_working_day( int $timestamp, array $days ): bool {
		return in_array( (int) wp_date( 'w', $timestamp, wp_timezone() ), $days, true );
	}

	public static function is_due( int $start, int $hours, ?int $now = null ): bool {
		$now  = $now ?? time();
		$days = YoOhw_Support_Settings::working_days();
		return $hours > 0 && self::is_working_day( $now, $days )
			&& self::elapsed( $start, $now, $days ) >= $hours * HOUR_IN_SECONDS;
	}

	/** Actual seconds spent on selected local dates, including 23/25-hour DST days. */
	public static function elapsed( int $start, int $end, array $days ): int {
		if ( $end <= $start ) {
			return 0;
		}
		if ( 7 === count( $days ) ) {
			return $end - $start;
		}

		$timezone = wp_timezone();
		$offset   = $timezone->getOffset( new DateTimeImmutable( '@' . $start ) );
		$elapsed  = 0;
		// Within each constant-offset interval, whole weeks can be counted in bulk.
		// Splitting at transitions counts repeated hours and omits skipped hours.
		$transitions = $timezone->getTransitions( $start, $end );
		foreach ( $transitions ?: [] as $transition ) {
			$boundary = (int) $transition['ts'];
			$elapsed += self::elapsed_at_offset( $start, $boundary, $offset, $days );
			$start  = $boundary;
			$offset = (int) $transition['offset'];
		}
		return $elapsed + self::elapsed_at_offset( $start, $end, $offset, $days );
	}

	private static function elapsed_at_offset( int $start, int $end, int $offset, array $days ): int {
		$weeks   = intdiv( $end - $start, WEEK_IN_SECONDS );
		$elapsed = $weeks * count( $days ) * DAY_IN_SECONDS;
		$cursor  = $start + $offset + $weeks * WEEK_IN_SECONDS;
		$end    += $offset;
		// At most seven days remain, regardless of the age of the topic.
		while ( $cursor < $end ) {
			$next = min( $end, ( (int) floor( $cursor / DAY_IN_SECONDS ) + 1 ) * DAY_IN_SECONDS );
			if ( in_array( (int) gmdate( 'w', $cursor ), $days, true ) ) {
				$elapsed += $next - $cursor;
			}
			$cursor = $next;
		}
		return $elapsed;
	}
}
