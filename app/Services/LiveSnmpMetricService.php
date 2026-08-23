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
    private const CPU_IDLE_PERCENT_OID = '.1.3.6.1.4.1.2021.11.11.0';

    private const MEMORY_OIDS = [
        'total' => '.1.3.6.1.4.1.2021.4.5.0',
        'free' => '.1.3.6.1.4.1.2021.4.6.0',
        'buffers' => '.1.3.6.1.4.1.2021.4.14.0',
        'cached' => '.1.3.6.1.4.1.2021.4.15.0',
        'available' => '.1.3.6.1.4.1.2021.4.27.0',
    ];

    private const IF_HC_IN_OID = '.1.3.6.1.2.1.31.1.1.1.6';
    private const IF_HC_OUT_OID = '.1.3.6.1.2.1.31.1.1.1.10';
    private const MAX_INTERFACES = 24;
    private const SAMPLE_INTERVAL_MICROSECONDS = 1_000_000;

    public function collect(Device $device, bool $includeNetwork = true): array
    {
        $startedAt = microtime(true);
        $ports = $includeNetwork ? $this->activePorts($device) : [];
        $cpuCacheKey = "live-snmp-metrics:cpu-state:{$device->device_id}";
        $cachedCpuState = Cache::get($cpuCacheKey, []);
        $firstOids = array_merge(
            array_values(self::CPU_OIDS),
            [self::CPU_IDLE_PERCENT_OID],
            array_values(self::MEMORY_OIDS),
            $this->networkOids($ports),
        );
        $secondOids = array_merge(array_values(self::CPU_OIDS), $this->networkOids($ports));

        $first = SnmpQuery::device($device)->numeric()->get($firstOids)->values();
        $firstSampleAt = microtime(true);
        usleep(self::SAMPLE_INTERVAL_MICROSECONDS);
        $second = SnmpQuery::device($device)->numeric()->get($secondOids)->values();
        $finishedAt = microtime(true);
        $sampleInterval = max(0.001, $finishedAt - $firstSampleAt);
        $duration = max(0.001, $finishedAt - $startedAt);

        $cpu = $this->cpuMetrics($first, $second);
        $ioWait = $this->ioWaitMetric($first, $second);
        $cachedCounters = is_array($cachedCpuState['counters'] ?? null) ? $cachedCpuState['counters'] : null;
        if (! $cpu['available'] && $cachedCounters !== null) {
            $cpu = $this->cpuMetrics($cachedCounters, $second);
        }
        if (! $ioWait['available'] && $cachedCounters !== null) {
            $ioWait = $this->ioWaitMetric($cachedCounters, $second);
        }
        if (! $cpu['available']) {
            $cpu = $this->cpuFromIdlePercent($first);
        }
        if (! $ioWait['available'] && ($cachedCpuState['io_wait']['available'] ?? false)) {
            $ioWait = array_merge($cachedCpuState['io_wait'], ['fresh' => false]);
        }

        $counters = $this->cpuCounterSnapshot($second);
        if ($counters !== null) {
            Cache::put($cpuCacheKey, [
                'counters' => $counters,
                'cpu' => $cpu,
                'io_wait' => $ioWait,
            ], 300);
        }

        return [
            'source' => 'live_snmp',
            'sampled_at' => Carbon::now('UTC')->toIso8601String(),
            'sample_interval_seconds' => round($sampleInterval, 3),
            'duration_ms' => (int) round($duration * 1000),
            'cpu' => $cpu,
            'memory' => $this->memoryMetrics($first),
            'io_wait' => $ioWait,
            'network' => $includeNetwork
                ? $this->networkMetrics($first, $second, $ports, $sampleInterval)
                : $this->unavailableNetworkMetric(),
        ];
    }

    public function collectNetwork(Device $device): array
    {
        $startedAt = microtime(true);
        $ports = $this->activePorts($device);
        $oids = $this->networkOids($ports);
        if ($oids === []) {
            return [
                'source' => 'live_snmp',
                'sampled_at' => Carbon::now('UTC')->toIso8601String(),
                'sample_interval_seconds' => 0,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'network' => $this->unavailableNetworkMetric(),
            ];
        }

        $first = SnmpQuery::device($device)->numeric()->get($oids)->values();
        $firstSampleAt = microtime(true);
        usleep(self::SAMPLE_INTERVAL_MICROSECONDS);
        $second = SnmpQuery::device($device)->numeric()->get($oids)->values();
        $finishedAt = microtime(true);
        $sampleInterval = max(0.001, $finishedAt - $firstSampleAt);

        return [
            'source' => 'live_snmp',
            'sampled_at' => Carbon::now('UTC')->toIso8601String(),
            'sample_interval_seconds' => round($sampleInterval, 3),
            'duration_ms' => (int) round(($finishedAt - $startedAt) * 1000),
            'network' => $this->networkMetrics($first, $second, $ports, $sampleInterval),
        ];
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

    private function cpuMetrics(array $first, array $second): array
    {
        $deltas = $this->cpuDeltas($first, $second);
        if ($deltas === null) {
            return $this->unavailableMetric('percent');
        }

        $total = $deltas['user'] + $deltas['nice'] + $deltas['system'] + $deltas['idle'];
        if ($total <= 0) {
            return $this->unavailableMetric('percent');
        }

        return $this->availableMetric(($total - $deltas['idle']) / $total * 100, 'percent');
    }

    private function cpuFromIdlePercent(array $values): array
    {
        $idle = $this->numericValue($values, self::CPU_IDLE_PERCENT_OID);
        if ($idle === null) {
            return $this->unavailableMetric('percent');
        }

        return array_merge($this->availableMetric(100 - $idle, 'percent'), [
            'fresh' => false,
            'sample_window' => 'one_minute',
        ]);
    }

    private function ioWaitMetric(array $first, array $second): array
    {
        $deltas = $this->cpuDeltas($first, $second);
        if ($deltas === null) {
            return $this->unavailableMetric('percent');
        }

        $total = $deltas['user'] + $deltas['nice'] + $deltas['system'] + $deltas['idle'];
        if ($total <= 0) {
            return $this->unavailableMetric('percent');
        }

        return $this->availableMetric($deltas['wait'] / $total * 100, 'percent');
    }

    private function cpuDeltas(array $first, array $second): ?array
    {
        $deltas = [];
        foreach (self::CPU_OIDS as $name => $oid) {
            $before = $this->counterValue($first, $oid);
            $after = $this->counterValue($second, $oid);
            if ($before === null || $after === null) {
                return null;
            }

            $deltas[$name] = Number::calculateRate($before, $after, 0, 1, 32);
        }

        return $deltas;
    }

    private function cpuCounterSnapshot(array $values): ?array
    {
        $snapshot = [];
        foreach (self::CPU_OIDS as $oid) {
            $value = $this->counterValue($values, $oid);
            if ($value === null) {
                return null;
            }
            $snapshot[$oid] = $value;
        }

        return $snapshot;
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

    private function networkMetrics(array $first, array $second, array $ports, float $elapsed): array
    {
        $inOctets = 0.0;
        $outOctets = 0.0;
        $sampledInterfaces = 0;

        foreach ($ports as $port) {
            $inOid = self::IF_HC_IN_OID . '.' . $port['if_index'];
            $outOid = self::IF_HC_OUT_OID . '.' . $port['if_index'];
            $inBefore = $this->counterValue($first, $inOid);
            $inAfter = $this->counterValue($second, $inOid);
            $outBefore = $this->counterValue($first, $outOid);
            $outAfter = $this->counterValue($second, $outOid);
            if ($inBefore === null || $inAfter === null || $outBefore === null || $outAfter === null) {
                continue;
            }

            $inOctets += Number::calculateRate($inBefore, $inAfter, 0, $elapsed, 64);
            $outOctets += Number::calculateRate($outBefore, $outAfter, 0, $elapsed, 64);
            $sampledInterfaces++;
        }

        if ($sampledInterfaces === 0) {
            return $this->unavailableNetworkMetric(count($ports), 0);
        }

        $inBps = round($inOctets * 8, 2);
        $outBps = round($outOctets * 8, 2);
        if ($inBps <= 0 && $outBps <= 0) {
            return $this->unavailableNetworkMetric(count($ports), $sampledInterfaces);
        }

        return [
            'available' => true,
            'in_bps' => $inBps,
            'out_bps' => $outBps,
            'interface_count' => count($ports),
            'sampled_interface_count' => $sampledInterfaces,
        ];
    }

    private function unavailableNetworkMetric(int $interfaceCount = 0, int $sampledInterfaceCount = 0): array
    {
        return [
            'available' => false,
            'in_bps' => null,
            'out_bps' => null,
            'interface_count' => $interfaceCount,
            'sampled_interface_count' => $sampledInterfaceCount,
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
}
