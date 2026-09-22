<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use LibreNMS\Util\Number;
use SnmpQuery;

class LiveSnmpMetricService
{
    private const CPU_OIDS = [
        'user' => '.1.3.6.1.4.1.2021.11.50.0',
        'nice' => '.1.3.6.1.4.1.2021.11.51.0',
        'system' => '.1.3.6.1.4.1.2021.11.52.0',
        'idle' => '.1.3.6.1.4.1.2021.11.53.0',
        'wait' => '.1.3.6.1.4.1.2021.11.54.0',
    ];

    private const MEMORY_OIDS = [
        'total' => '.1.3.6.1.4.1.2021.4.5.0',
        'free' => '.1.3.6.1.4.1.2021.4.6.0',
        'buffers' => '.1.3.6.1.4.1.2021.4.14.0',
        'cached' => '.1.3.6.1.4.1.2021.4.15.0',
        'available' => '.1.3.6.1.4.1.2021.4.27.0',
    ];

    private const IF_HC_IN_OID = '.1.3.6.1.2.1.31.1.1.1.6';
    private const IF_HC_OUT_OID = '.1.3.6.1.2.1.31.1.1.1.10';
    private const HR_STORAGE_ALLOCATION_UNITS_OID = '.1.3.6.1.2.1.25.2.3.1.4';
    private const HR_STORAGE_SIZE_OID = '.1.3.6.1.2.1.25.2.3.1.5';
    private const HR_STORAGE_USED_OID = '.1.3.6.1.2.1.25.2.3.1.6';
    private const MAX_INTERFACES = 24;
    private const CPU_MIN_WINDOW_SECONDS = 3.0;
    private const NETWORK_MIN_WINDOW_SECONDS = 10.0;
    private const MAX_WINDOW_SECONDS = 30.0;
    private const CACHE_TTL_SECONDS = 600;

    /** Return the last complete cache entry without contacting the device. */
    public function cached(Device $device): array
    {
        return Cache::get($this->latestKey($device), $this->emptyMetrics());
    }

    /**
     * Take one SNMP snapshot and update independently available values.
     * Counter rates use a distinct, correctly timed prior snapshot so an
     * agent's internal cache cannot inflate short rates.
     */
    public function refreshCache(Device $device): bool
    {
        $lock = Cache::lock("live-snmp-refresh:{$device->device_id}", 15);
        if (! $lock->get()) {
            return false;
        }

        try {
            $startedAt = microtime(true);
            $ports = $this->activePorts($device);
            $hostResources = $device->os === 'routeros' ? $this->hostResourcesPlan($device) : null;
            $loadOids = $hostResources === null
                ? array_merge(array_values(self::CPU_OIDS), array_values(self::MEMORY_OIDS))
                : array_merge($hostResources['processor_oids'], array_values($hostResources['memory_oids']));
            $oids = array_values(array_unique(array_merge($loadOids, $this->networkOids($ports))));
            $values = SnmpQuery::device($device)->numeric()->get($oids)->values();
            $sampledAt = microtime(true);
            $sample = [
                'timestamp' => $sampledAt,
                'sampled_at' => Carbon::createFromTimestampUTC($sampledAt)->toIso8601String(),
                'cpu' => $hostResources === null ? $this->counterSnapshot($values, self::CPU_OIDS) : null,
                'network' => $this->networkCounterSnapshot($values, $ports),
            ];

            $historyKey = $this->historyKey($device);
            $history = Cache::get($historyKey, []);
            $history = is_array($history) ? array_values(array_filter($history, fn ($entry) => is_array($entry) && $sampledAt - (float) ($entry['timestamp'] ?? 0) <= self::MAX_WINDOW_SECONDS
            )) : [];

            $latest = $this->cached($device);
            $latest['source'] = 'live_snmp_cache';
            $latest['sampled_at'] = $sample['sampled_at'];
            $latest['duration_ms'] = (int) round(($sampledAt - $startedAt) * 1000);

            $memory = $hostResources === null
                ? $this->memoryMetrics($values)
                : $this->hostResourcesMemoryMetrics($values, $hostResources['memory_oids']);
            if ($memory['available']) {
                $latest['memory'] = $this->stamp($memory, $sample['sampled_at'], 0.0);
            }

            if ($hostResources !== null) {
                $cpu = $this->hostResourcesCpuMetrics($values, $hostResources['processor_oids']);
                if ($cpu['available']) {
                    $latest['cpu'] = $this->stamp($cpu, $sample['sampled_at'], 0.0);
                }
            } else {
                $cpuBaseline = $this->selectBaseline($history, $sample, self::CPU_MIN_WINDOW_SECONDS, 'cpu');
                if ($cpuBaseline !== null) {
                    $window = $sampledAt - (float) $cpuBaseline['timestamp'];
                    [$cpu, $ioWait] = $this->cpuMetrics($cpuBaseline['cpu'], $sample['cpu']);
                    if ($cpu['available']) {
                        $latest['cpu'] = $this->stamp($cpu, $sample['sampled_at'], $window);
                    }
                    if ($ioWait['available']) {
                        $latest['io_wait'] = $this->stamp($ioWait, $sample['sampled_at'], $window);
                    }
                }
            }

            $networkBaseline = $this->selectBaseline($history, $sample, self::NETWORK_MIN_WINDOW_SECONDS, 'network');
            if ($networkBaseline !== null) {
                $window = $sampledAt - (float) $networkBaseline['timestamp'];
                $network = $this->networkMetrics($networkBaseline['network'], $sample['network'], $ports, $window);
                if ($network['available']) {
                    $latest['network'] = array_merge($network, [
                        'sampled_at' => $sample['sampled_at'],
                        'sample_window_seconds' => round($window, 3),
                    ]);
                }
            }

            $history[] = $sample;
            Cache::put($historyKey, array_slice($history, -12), self::CACHE_TTL_SECONDS);
            Cache::put($this->latestKey($device), $latest, self::CACHE_TTL_SECONDS);

            return true;
        } finally {
            $lock->release();
        }
    }

    /** Compatibility endpoint for older clients. */
    public function collect(Device $device, bool $includeNetwork = true): array
    {
        $this->refreshCache($device);

        return $this->cached($device);
    }

    /** Compatibility endpoint for older clients. */
    public function collectNetwork(Device $device): array
    {
        $startedAt = microtime(true);
        $this->refreshCache($device);
        $metrics = $this->cached($device);

        return [
            'source' => $metrics['source'],
            'sampled_at' => $metrics['network']['sampled_at'] ?? $metrics['sampled_at'],
            'sample_interval_seconds' => $metrics['network']['sample_window_seconds'] ?? 0,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'network' => $metrics['network'],
        ];
    }

    private function selectBaseline(array $history, array $current, float $minimumWindow, string $counter): ?array
    {
        foreach (array_reverse($history) as $candidate) {
            $elapsed = (float) $current['timestamp'] - (float) ($candidate['timestamp'] ?? 0);
            if ($elapsed < $minimumWindow || $elapsed > self::MAX_WINDOW_SECONDS) {
                continue;
            }
            if (! is_array($candidate[$counter] ?? null) || ! is_array($current[$counter] ?? null)) {
                continue;
            }
            if ($candidate[$counter] === $current[$counter]) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function activePorts(Device $device): array
    {
        return $device->ports()
            ->select(['ifIndex', 'ifName'])
            ->where('deleted', 0)
            ->where('disabled', 0)
            ->where('ignore', 0)
            ->where('ifOperStatus', 'up')
            ->where('ifAdminStatus', 'up')
            ->where('ifType', '!=', 'softwareLoopback')
            ->where('ifIndex', '>', 0)
            ->orderByDesc('ifSpeed')
            ->limit(self::MAX_INTERFACES)
            ->get()
            ->map(fn ($port) => ['if_index' => (int) $port->ifIndex, 'name' => (string) $port->ifName])
            ->all();
    }

    private function networkOids(array $ports): array
    {
        $oids = [];
        foreach ($ports as $port) {
            $oids[] = self::IF_HC_IN_OID . '.' . $port['if_index'];
            $oids[] = self::IF_HC_OUT_OID . '.' . $port['if_index'];
        }

        return $oids;
    }

    /**
     * Use the OIDs LibreNMS already discovered for RouterOS instead of
     * assuming a fixed processor count or hrStorage index.
     */
    private function hostResourcesPlan(Device $device): array
    {
        $processorOids = $device->processors()
            ->where('processor_type', 'hr')
            ->orderBy('processor_index')
            ->pluck('processor_oid')
            ->filter(fn ($oid) => is_string($oid) && $oid !== '')
            ->values()
            ->all();

        $memory = $device->mempools()
            ->where('mempool_type', 'hrstorage')
            ->where('mempool_class', 'system')
            ->where('mempool_deleted', 0)
            ->orderByDesc('mempool_total')
            ->first(['mempool_index']);
        $index = $memory?->mempool_index;
        $memoryOids = $index === null ? [] : [
            'allocation_units' => self::HR_STORAGE_ALLOCATION_UNITS_OID . '.' . $index,
            'size' => self::HR_STORAGE_SIZE_OID . '.' . $index,
            'used' => self::HR_STORAGE_USED_OID . '.' . $index,
        ];

        return [
            'processor_oids' => $processorOids,
            'memory_oids' => $memoryOids,
        ];
    }

    private function counterSnapshot(array $values, array $oids): ?array
    {
        $snapshot = [];
        foreach ($oids as $name => $oid) {
            $value = $this->counterValue($values, $oid);
            if ($value === null) {
                return null;
            }
            $snapshot[$name] = $value;
        }

        return $snapshot;
    }

    private function networkCounterSnapshot(array $values, array $ports): array
    {
        $snapshot = [];
        foreach ($ports as $port) {
            $index = $port['if_index'];
            $in = $this->counterValue($values, self::IF_HC_IN_OID . '.' . $index);
            $out = $this->counterValue($values, self::IF_HC_OUT_OID . '.' . $index);
            if ($in !== null && $out !== null) {
                $snapshot[(string) $index] = ['in' => $in, 'out' => $out];
            }
        }

        return $snapshot;
    }

    private function cpuMetrics(?array $before, ?array $after): array
    {
        if ($before === null || $after === null) {
            return [$this->unavailableMetric('percent'), $this->unavailableMetric('percent')];
        }

        $deltas = [];
        foreach (array_keys(self::CPU_OIDS) as $name) {
            if (! isset($before[$name], $after[$name])) {
                return [$this->unavailableMetric('percent'), $this->unavailableMetric('percent')];
            }
            $deltas[$name] = Number::calculateRate($before[$name], $after[$name], 0, 1, 32);
        }

        // Wait is its own CPU state and must be included in the denominator.
        $total = array_sum($deltas);
        if ($total <= 0) {
            return [$this->unavailableMetric('percent'), $this->unavailableMetric('percent')];
        }

        $busy = ($deltas['user'] + $deltas['nice'] + $deltas['system']) / $total * 100;
        $wait = $deltas['wait'] / $total * 100;

        return [$this->availableMetric($busy, 'percent'), $this->availableMetric($wait, 'percent')];
    }

    private function memoryMetrics(array $values): array
    {
        $totalKb = $this->numericValue($values, self::MEMORY_OIDS['total']);
        if ($totalKb === null || $totalKb <= 0) {
            return $this->unavailableMetric('percent');
        }

        $availableKb = $this->numericValue($values, self::MEMORY_OIDS['available']);
        if ($availableKb === null) {
            $availableKb = ($this->numericValue($values, self::MEMORY_OIDS['free']) ?? 0)
                + ($this->numericValue($values, self::MEMORY_OIDS['buffers']) ?? 0)
                + ($this->numericValue($values, self::MEMORY_OIDS['cached']) ?? 0);
        }
        $availableKb = min($totalKb, max(0, $availableKb));
        $usedKb = $totalKb - $availableKb;

        return array_merge($this->availableMetric($usedKb / $totalKb * 100, 'percent'), [
            'used_bytes' => (int) round($usedKb * 1024),
            'total_bytes' => (int) round($totalKb * 1024),
        ]);
    }

    private function hostResourcesCpuMetrics(array $values, array $oids): array
    {
        $loads = array_values(array_filter(
            array_map(fn ($oid) => $this->numericValue($values, $oid), $oids),
            fn ($value) => $value !== null
        ));
        if ($loads === []) {
            return $this->unavailableMetric('percent');
        }

        return array_merge(
            $this->availableMetric(array_sum($loads) / count($loads), 'percent'),
            ['sampled_processor_count' => count($loads)]
        );
    }

    private function hostResourcesMemoryMetrics(array $values, array $oids): array
    {
        if (! isset($oids['allocation_units'], $oids['size'], $oids['used'])) {
            return $this->unavailableMetric('percent');
        }

        $allocationUnits = $this->numericValue($values, $oids['allocation_units']);
        $size = $this->numericValue($values, $oids['size']);
        $used = $this->numericValue($values, $oids['used']);
        if ($allocationUnits === null || $allocationUnits <= 0 || $size === null || $size <= 0 || $used === null) {
            return $this->unavailableMetric('percent');
        }

        $used = min($size, max(0, $used));

        return array_merge($this->availableMetric($used / $size * 100, 'percent'), [
            'used_bytes' => (int) round($used * $allocationUnits),
            'total_bytes' => (int) round($size * $allocationUnits),
        ]);
    }

    private function networkMetrics(array $before, array $after, array $ports, float $elapsed): array
    {
        $inOctetsPerSecond = 0.0;
        $outOctetsPerSecond = 0.0;
        $sampledInterfaces = 0;
        foreach ($ports as $port) {
            $index = (string) $port['if_index'];
            if (! isset($before[$index], $after[$index])) {
                continue;
            }
            $inOctetsPerSecond += Number::calculateRate($before[$index]['in'], $after[$index]['in'], 0, $elapsed, 64);
            $outOctetsPerSecond += Number::calculateRate($before[$index]['out'], $after[$index]['out'], 0, $elapsed, 64);
            $sampledInterfaces++;
        }

        if ($sampledInterfaces === 0) {
            return $this->unavailableNetworkMetric(count($ports));
        }

        return [
            'available' => true,
            'in_bps' => round(max(0, $inOctetsPerSecond * 8), 2),
            'out_bps' => round(max(0, $outOctetsPerSecond * 8), 2),
            'interface_count' => count($ports),
            'sampled_interface_count' => $sampledInterfaces,
        ];
    }

    private function stamp(array $metric, string $sampledAt, float $window): array
    {
        return array_merge($metric, [
            'sampled_at' => $sampledAt,
            'sample_window_seconds' => round($window, 3),
        ]);
    }

    private function emptyMetrics(): array
    {
        return [
            'source' => 'cache_empty',
            'sampled_at' => null,
            'duration_ms' => 0,
            'cpu' => $this->unavailableMetric('percent'),
            'memory' => $this->unavailableMetric('percent'),
            'io_wait' => $this->unavailableMetric('percent'),
            'network' => $this->unavailableNetworkMetric(),
        ];
    }

    private function unavailableNetworkMetric(int $interfaceCount = 0): array
    {
        return [
            'available' => false,
            'in_bps' => null,
            'out_bps' => null,
            'interface_count' => $interfaceCount,
            'sampled_interface_count' => 0,
        ];
    }

    private function numericValue(array $values, string $oid): ?float
    {
        $value = $values[$oid] ?? $values[ltrim($oid, '.')] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    private function counterValue(array $values, string $oid): ?string
    {
        $value = $values[$oid] ?? $values[ltrim($oid, '.')] ?? null;
        $value = is_scalar($value) ? trim((string) $value) : '';

        return ctype_digit($value) ? $value : null;
    }

    private function availableMetric(float $value, string $unit): array
    {
        return ['available' => true, 'value' => round(min(100, max(0, $value)), 4), 'unit' => $unit];
    }

    private function unavailableMetric(string $unit): array
    {
        return ['available' => false, 'value' => null, 'unit' => $unit];
    }

    private function latestKey(Device $device): string
    {
        return "live-snmp-metrics:latest:{$device->device_id}";
    }

    private function historyKey(Device $device): string
    {
        return "live-snmp-metrics:history:{$device->device_id}";
    }
}
