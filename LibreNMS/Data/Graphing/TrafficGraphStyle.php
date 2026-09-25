<?php

namespace LibreNMS\Data\Graphing;

/** Keep overlapping traffic directions readable without blending filled areas. */
class TrafficGraphStyle
{
    /**
     * @param  list<string>  $options
     * @return list<string>
     */
    public static function sameAxis(array $options): array
    {
        $colors = [];
        foreach ($options as $option) {
            if (preg_match('/^(?:AREA|LINE[\d.]*):((?:d?out|in)[a-z0-9_]*)#([a-f0-9]{6})/i', $option, $match)) {
                $colors[strtoupper($match[2])] = self::color($match[1]);
            }
        }

        return array_map(function (string $option) use ($colors): string {
            if (preg_match('/^(?:AREA|LINE[\d.]*):((?:d?out|in)[a-z0-9_]*)#[a-f0-9]+(.*)$/i', $option, $match)) {
                $series = $match[1];
                $historical = str_contains($series, 'X');
                $maximum = str_contains($series, '_max');
                $color = self::color($series) . ($historical || $maximum ? '88' : '');
                $suffix = preg_replace('/:dashes(?:=[\d.,]+)?/', '', $match[2]);
                $dash = $historical ? ':dashes=2,4' : (self::outgoing($series) ? ':dashes=6,3' : '');

                return 'LINE' . ($historical || $maximum ? '1' : '2') . ':' . $series . '#' . $color . $suffix . $dash;
            }

            // Some aggregate templates use an invisible HRULE for the outgoing legend swatch.
            if (preg_match('/^(HRULE:[^#]+#)([a-f0-9]{6})(:.*)$/i', $option, $match) && isset($colors[strtoupper($match[2])]) && stripos($match[3], 'out') !== false) {
                return $match[1] . $colors[strtoupper($match[2])] . $match[3];
            }

            return $option;
        }, $options);
    }

    /**
     * Hide only drawing/legend commands, preserving every RRD definition and calculation.
     * Scoped by GraphParameters to device_bits and port_bits templates.
     *
     * @param  list<string>  $options
     * @return list<string>
     */
    public static function direction(array $options, string $direction): array
    {
        if (! in_array($direction, ['in', 'out'], true)) {
            return $options;
        }

        return array_values(array_filter($options, function (string $option) use ($direction): bool {
            if (! preg_match('/^(?:AREA|LINE[\d.]*|HRULE|GPRINT|PRINT):([^:#]+)(.*)$/i', $option, $match)) {
                return true;
            }
            $series = $match[1];
            if (preg_match('/^(?:d?out|totout|aveout|d?percentile_out|olsl)/i', $series)) {
                return $direction === 'out';
            }
            if (preg_match('/^(?:in|totin|avein|percentile_in|ilsl)/i', $series)) {
                return $direction === 'in';
            }
            // Combined totals and the highest-of-both percentile would mislabel a single direction.
            if (preg_match('/^(?:totX?|bitsX?|octetsX?|percentilehigh)$/i', $series)) {
                return false;
            }
            // Aggregate templates use off-scale HRULEs as legend headings; port speed is numeric too.
            if (str_starts_with($option, 'HRULE:') || preg_match('/^LINE[\d.]*:-?[\d.]+#/', $option)) {
                if (preg_match('/(?:^|[ :\\\\])Out(?:[ :\\\\]|$)/i', $match[2])) {
                    return $direction === 'out';
                }
                if (preg_match('/(?:^|[ :\\\\])In(?:[ :\\\\]|$)/i', $match[2])) {
                    return $direction === 'in';
                }
                if (str_contains($match[2], ' Agg')) {
                    return false;
                }
            }

            return true;
        }));
    }

    private static function outgoing(string $series): bool
    {
        return (bool) preg_match('/^d?out/i', $series);
    }

    private static function color(string $series): string
    {
        return self::outgoing($series) ? 'FF8C00' : '00A6ED';
    }
}
