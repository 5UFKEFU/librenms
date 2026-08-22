<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\CurrentRrdMetricService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use LibreNMS\Data\Store\Rrd;

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

    public function __construct(private readonly CurrentRrdMetricService $metrics)
    {
    }

    public function ioWait(string $hostname): JsonResponse
    {
        $device = ctype_digit($hostname)
            ? Device::find((int) $hostname)
            : Device::findByHostname($hostname);
        abort_if($device === null, 404, "Device $hostname does not exist");
        $this->authorize('view', $device);

        $now = time();
        $samples = [];
        foreach (self::CPU_STATES as $state => $rrdName) {
            $samples[$state] = $this->metrics->latestAverage(
                Rrd::name($device->hostname, 'ucd_' . $rrdName),
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
