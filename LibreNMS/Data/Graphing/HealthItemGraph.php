<?php

namespace LibreNMS\Data\Graphing;

/**
 * Graph types for one item of a device's health list (one processor, memory
 * pool, storage volume, disk or sensor), as requested through
 * devices/{device}/graphs/health/{type}/{id}.
 */
class HealthItemGraph
{
    private const TYPES = [
        'device_processor' => 'processor_usage',
        'device_mempool' => 'mempool_usage',
        'device_storage' => 'storage_usage',
        'device_diskio' => 'diskio_bits',
        'device_diskio_bits' => 'diskio_bits',
        'device_diskio_ops' => 'diskio_ops',
    ];

    /** The graph type for one item; sensor classes keep LibreNMS's sensor_{class} graphs. */
    public static function type(string $type): string
    {
        return self::TYPES[$type] ?? str_replace('device_', 'sensor_', $type);
    }

    /** The device-wide graph for a health type that has no graph of its own name. */
    public static function aggregateType(string $type): string
    {
        return $type === 'device_diskio' ? 'device_diskio_bits' : $type;
    }
}
