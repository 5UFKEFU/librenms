<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Runs service checks on demand: a saved service right away (recording the
 * result like a poll), or unsaved settings as a dry run so a client can try
 * a configuration and see the plugin's output before saving it.
 */
class ServiceCheckController extends Controller
{
    public function __construct()
    {
        require_once base_path('includes/services.inc.php');
    }

    /** Check a saved service now and store the result, as the poller would. */
    public function run(int $id): JsonResponse
    {
        $row = Device::query()->select('devices.*', 'services.*')
            ->join('services', 'devices.device_id', '=', 'services.device_id')
            ->where('services.service_id', $id)
            ->first();
        if ($row === null) {
            return response()->json(['status' => 'error', 'message' => "Service $id not found"], 404);
        }

        $started = microtime(true);
        poll_service($row);
        $service = Service::query()->find($id);

        return response()->json([
            'status' => 'ok',
            'service' => [
                'service_id' => $service->service_id,
                'service_status' => $service->service_status,
                'service_message' => $service->service_message,
                'service_checked' => $service->service_checked,
                'service_changed' => $service->service_changed,
                'service_slow' => (bool) ($service->service_slow ?? false),
                'service_response_time' => $service->service_response_time ?? null,
            ],
            'duration' => round(microtime(true) - $started, 3),
        ]);
    }

    /** Run a check with unsaved settings and return the result without storing anything. */
    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'type' => 'required|string|max:64',
            'ip' => 'nullable|string|max:255',
            'param' => 'nullable|string|max:2048',
        ]);
        if (! in_array($validated['type'], list_available_services(), true)) {
            return response()->json(['status' => 'error', 'message' => "The service {$validated['type']} does not exist."], 422);
        }
        $device = Device::query()->find($validated['device_id']);
        if ($device === null) {
            return response()->json(['status' => 'error', 'message' => "Device {$validated['device_id']} not found"], 404);
        }

        [$command, $parser] = service_check_command([
            'service_type' => $validated['type'],
            'service_ip' => $validated['ip'] ?? '',
            'service_param' => $validated['param'] ?? '',
            'hostname' => $device->hostname,
            'overwrite_ip' => $device->overwrite_ip,
            'device_id' => $device->device_id,
        ]);
        $started = microtime(true);
        [$status, $message, $metrics] = check_service($command, $parser);

        return response()->json([
            'status' => 'ok',
            'result' => [
                'service_status' => (int) $status,
                'service_message' => trim((string) $message),
                'metrics' => array_map(fn ($metric) => ['value' => $metric['value'] ?? null, 'uom' => $metric['uom'] ?? ''], $metrics),
            ],
            'duration' => round(microtime(true) - $started, 3),
        ]);
    }
}
