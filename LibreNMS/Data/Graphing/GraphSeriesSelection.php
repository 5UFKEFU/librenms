<?php

namespace LibreNMS\Data\Graphing;

use LibreNMS\Exceptions\RrdGraphException;

/** Re-render one legend series with its own RRD scale, never by stretching SVG pixels. */
class GraphSeriesSelection
{
    public static function supports(string $type): bool
    {
        return in_array($type, ['device_processor', 'device_diskio_bits', 'device_diskio_ops', 'device_ucd_load'], true);
    }

    /** One entry per colored legend marker, in RRD legend order. */
    public static function entries(array $options, string $type): array
    {
        $draws = [];
        foreach ($options as $option) {
            if (is_string($option) && preg_match('/^(?:AREA|LINE[0-9.]*):([^#:\\s]+)#/', $option, $match)) {
                $draws[$match[1]] = true;
            }
        }
        $entries = [];
        foreach ($options as $i => $option) {
            if (! is_string($option) || ! preg_match('/^(AREA|LINE[0-9.]*|HRULE):([^#:\\s]+)#[a-fA-F0-9]+:([^:]*)/', $option, $match) || trim($match[3]) === '') {
                continue;
            }
            $variable = $match[2];
            $statistic = null;
            for ($j = $i + 1; $j < count($options); $j++) {
                $next = (string) $options[$j];
                if (preg_match('/^GPRINT:([^:]+):/', $next, $stat)) {
                    $statistic = $stat[1];
                    break;
                }
                if (preg_match('/^(AREA|LINE[0-9.]*|HRULE):.*#[a-fA-F0-9]+:[^:]+/', $next)) {
                    break;
                }
            }
            if ($match[1] === 'HRULE') {
                // The write legend uses a reference rule; the actual area is drawn later.
                $variable = isset($draws[$statistic . '_neg']) ? $statistic . '_neg' : $statistic;
            }
            $entries[] = isset($draws[$variable ?? '']) ? [
                'key' => $variable,
                // Stacked CPU divides each core by core count. A solo core must
                // use the original utilization, matching its legend statistics.
                'value' => $type === 'device_processor' && $statistic ? $statistic : $variable,
            ] : null;
        }

        return $entries;
    }

    public static function select(array $options, string $type, string $key): array
    {
        $entry = collect(self::entries($options, $type))->first(fn ($entry) => ($entry['key'] ?? null) === $key);
        if (! $entry) {
            throw new RrdGraphException('Unknown graph series', 'Unknown series');
        }
        $result = [];
        for ($i = 0; $i < count($options); $i++) {
            $option = (string) $options[$i];
            if (in_array($option, ['-u', '-l', '--upper-limit', '--lower-limit'], true)) {
                $i++;
                continue;
            }
            if (in_array($option, ['--rigid', '-r', '--alt-autoscale', '--alt-autoscale-max', '--alt-autoscale-min', '-A', '-M', '-J'], true)) {
                continue;
            }
            if (preg_match('/^(AREA|LINE[0-9.]*):([^#:\\s]+)(#.*)$/', $option, $draw)) {
                if ($draw[2] === $key) {
                    $result[] = $draw[1] . ':' . $entry['value'] . preg_replace('/:STACK(?=:|$)/', '', $draw[3]);
                }
                continue;
            }
            if (preg_match('/^(?:HRULE|VRULE|TICK|GPRINT|PRINT|COMMENT|TEXTALIGN):/', $option)) {
                continue;
            }
            $result[] = $options[$i];
        }
        // RRDtool calculates the limits from the only remaining data series.
        $result[] = '--alt-autoscale';

        return $result;
    }

    public static function metadata(string $svg, array $options, string $type, ?string $selected): string
    {
        $json = json_encode(['keys' => array_map(fn ($entry) => $entry['key'] ?? null, self::entries($options, $type)), 'selected' => $selected], JSON_THROW_ON_ERROR);

        return str_replace('</svg>', '<metadata id="librenms-series">' . htmlspecialchars($json, ENT_NOQUOTES | ENT_XML1) . '</metadata></svg>', $svg);
    }
}
