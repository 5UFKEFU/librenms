<?php

/**
 * GraphExclusion.php
 *
 * Leaves chosen time windows out of a graph: every data source reads as
 * unknown inside them, so a one-off spike neither draws nor stretches the
 * scale, and the legend statistics skip it too. Nothing in the RRD changes.
 *
 * @link       https://www.librenms.org
 */

namespace LibreNMS\Data\Graphing;

class GraphExclusion
{
    /** At most this many windows per graph. */
    private const MAX_WINDOWS = 10;

    /**
     * Parse "start-end,start-end" (unix seconds) into ordered windows.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public static function parse(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $windows = [];
        foreach (explode(',', $value) as $part) {
            if (! preg_match('/^(\d{1,12})-(\d{1,12})$/', trim($part), $m)) {
                continue;
            }
            [$start, $end] = [(int) $m[1], (int) $m[2]];
            if ($end > $start) {
                $windows[] = [$start, $end];
            }
        }

        return array_slice($windows, 0, self::MAX_WINDOWS);
    }

    /**
     * Rewrite each DEF so its values inside the windows become UNKN.
     *
     * @param  array<int, string>  $options  rrdtool graph arguments
     * @param  array<int, array{0: int, 1: int}>  $windows
     * @return array<int, string>
     */
    public static function apply(array $options, array $windows): array
    {
        if ($windows === []) {
            return $options;
        }

        // Non-zero when TIME is outside every window.
        $outside = implode(',', array_map(
            fn ($window) => "TIME,{$window[0]},LT,TIME,{$window[1]},GT,+",
            $windows
        )) . str_repeat(',*', count($windows) - 1);

        $result = [];
        foreach ($options as $option) {
            if (is_string($option) && preg_match('/^DEF:([A-Za-z0-9_-]{1,200})=(.+)$/s', $option, $m)) {
                $raw = $m[1] . '__raw';
                $result[] = "DEF:$raw={$m[2]}";
                $result[] = "CDEF:{$m[1]}=$outside,$raw,UNKN,IF";
                continue;
            }
            $result[] = $option;
        }

        return $result;
    }
}
