<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\CurrentRrdMetricService;
use App\Services\LiveSnmpMetricService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DeviceCurrentMetricController extends Controller
{
    private const CPU_STATES = [
        'user' => 'ssCpuRawUser',
        'nice' => 'ssCpuRawNice',
        'system' => 'ssCpuRawSystem',
        'idle' => 'ssCpuRawIdle',
        'wait' => 'ssCpuRawWait',
        'kernel' => 'ssCpuRawKernel',
        'interrupt' => 'ssCpuRawInterrupt',
        'soft_irq' => 'ssCpuRawSoftIRQ',
        'steal' => 'ssCpuRawSteal',
    ];

    public function __construct(
        private readonly CurrentRrdMetricService $metrics,
        private readonly LiveSnmpMetricService $liveMetrics,
    ) {
    }

    public function live(string $hostname): JsonResponse
    {
        $device = $this->findDevice($hostname);
        $this->authorize('view', $device);

        $lock = Cache::lock("live-snmp-metrics:{$device->device_id}", 15);
        if (! $lock->get()) {
            return response()->json([
                'status' => 'busy',
                'message' => 'A live sample for this device is already running.',
            ], 429);
        }

        try {
            return response()->json([
                'status' => 'ok',
                'device_id' => (int) $device->device_id,
                'hostname' => $device->hostname,
                'metrics' => $this->liveMetrics->collect($device),
            ]);
        } finally {
            $lock->release();
        }
    }

    public function liveLoad(string $hostname): JsonResponse
    {
        $device = $this->findDevice($hostname);
        $this->authorize('view', $device);

        $lock = Cache::lock("live-snmp-load:{$device->device_id}", 15);
        if (! $lock->get()) {
            return response()->json([
                'status' => 'busy',
                'message' => 'A live load sample for this device is already running.',
            ], 429);
        }

        try {
            return response()->json([
                'status' => 'ok',
                'device_id' => (int) $device->device_id,
                'hostname' => $device->hostname,
                'metrics' => $this->liveMetrics->collect($device, false),
            ]);
        } finally {
            $lock->release();
        }
    }

    public function liveNetwork(string $hostname): JsonResponse
    {
        $device = $this->findDevice($hostname);
        $this->authorize('view', $device);

        $lock = Cache::lock("live-snmp-network:{$device->device_id}", 15);
        if (! $lock->get()) {
            return response()->json([
                'status' => 'busy',
                'message' => 'A live network sample for this device is already running.',
            ], 429);
        }

        try {
            $sample = $this->liveMetrics->collectNetwork($device);
            $databaseNetwork = $this->databaseNetwork($device);

            return response()->json([
                'status' => 'ok',
                'device_id' => (int) $device->device_id,
                'hostname' => $device->hostname,
                'sampled_at' => $sample['sampled_at'],
                'duration_ms' => $sample['duration_ms'],
                'network' => $sample['network'],
                'database_network' => $databaseNetwork,
            ]);
        } finally {
            $lock->release();
        }
    }

    private function databaseNetwork(Device $device): array
    {
        $ports = $device->ports()
            ->select(['ifInOctets_rate', 'ifOutOctets_rate'])
            ->where('deleted', 0)
            ->where('disabled', 0)
            ->where('ignore', 0)
            ->where('ifOperStatus', 'up')
            ->where('ifAdminStatus', 'up')
            ->where('ifType', '!=', 'softwareLoopback')
            ->get();
        $inBps = round($ports->sum(fn ($port) => max(0, (float) $port->ifInOctets_rate)) * 8, 2);
        $outBps = round($ports->sum(fn ($port) => max(0, (float) $port->ifOutOctets_rate)) * 8, 2);

        return [
            'available' => $inBps > 0 || $outBps > 0,
            'in_bps' => $inBps > 0 ? $inBps : null,
            'out_bps' => $outBps > 0 ? $outBps : null,
            'interface_count' => $ports->count(),
            'sampled_at' => $device->last_polled?->toIso8601String(),
            'source' => 'ports_database',
        ];
    }

    public function ioWait(string $hostname): JsonResponse
    {
        $device = $this->findDevice($hostname);
        $this->authorize('view', $device);

        $now = time();
        $samples = [];
        foreach (self::CPU_STATES as $state => $rrdName) {
            $samples[$state] = $this->metrics->latestAverage(
                $this->metrics->filename($device->hostname, 'ucd_' . $rrdName),
                'value',
                $now,
            );
        }

        $wait = $samples['wait'];
        if ($wait === null) {
            return $this->unavailableResponse($device, 'No recent UCD I/O wait sample is available.');
        }

        $total = 0.0;
        foreach ($samples as $sample) {
            if ($sample !== null && $sample->timestamp === $wait->timestamp) {
                $total += max(0.0, (float) $sample->get('value'));
            }
        }

        if ($total <= 0.0) {
            return $this->unavailableResponse($device, 'No matching CPU state samples are available.');
        }

        $rawValue = max(0.0, (float) $wait->get('value'));
        $percentage = min(100.0, $rawValue / $total * 100.0);

        return response()->json([
            'status' => 'ok',
            'metric' => [
                'name' => 'io_wait',
                'available' => true,
                'value' => round($percentage, 4),
                'raw_value' => round($rawValue, 6),
                'unit' => 'percent',
                'timestamp' => CarbonImmutable::createFromTimestampUTC($wait->timestamp)->toIso8601String(),
                'device_id' => (int) $device->device_id,
                'hostname' => $device->hostname,
                'graph' => 'device_ucd_io_wait',
                'source' => 'ucd_cpu_rrd',
            ],
        ]);
    }

    private function findDevice(string $hostname): Device
    {
        $device = ctype_digit($hostname)
            ? Device::find((int) $hostname)
            : Device::findByHostname($hostname);
        abort_if($device === null, 404, "Device $hostname does not exist");

        return $device;
    }

    private function unavailableResponse(Device $device, string $reason): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'metric' => [
                'name' => 'io_wait',
                'available' => false,
                'value' => null,
                'unit' => 'percent',
                'timestamp' => null,
                'device_id' => (int) $device->device_id,
                'hostname' => $device->hostname,
                'graph' => 'device_ucd_io_wait',
                'source' => 'ucd_cpu_rrd',
                'reason' => $reason,
            ],
        ]);
    }
}
