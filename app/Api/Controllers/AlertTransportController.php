<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AlertOperationTransportMap;
use App\Models\AlertTransport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LibreNMS\Alert\Transport;

/**
 * Alert transports over the API, so an integration can register its own
 * endpoint (for example an API/webhook transport) without the web UI.
 */
class AlertTransportController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', AlertTransport::class);

        $transports = AlertTransport::query()->orderBy('transport_id')->get()->map(fn (AlertTransport $transport) => $this->serialize($transport));

        return $this->response($transports->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AlertTransport::class);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'type' => 'required|string|max:64|regex:/^[a-z0-9]+$/i',
            'is_default' => 'sometimes|boolean',
            'config' => 'required|array',
        ]);

        $type = Str::lower($validated['type']);
        $class = Transport::getClass($type);
        if (! class_exists($class) || ! method_exists($class, 'configTemplate')) {
            return response()->json(['status' => 'error', 'message' => "Unknown transport type '$type'."], 422);
        }

        $template = $class::configTemplate();
        $validator = Validator::make($validated['config'], $template['validation'] ?? []);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => implode(' ', $validator->errors()->all())], 422);
        }

        // Keep only the fields the transport defines, like the web form does.
        $config = [];
        foreach ($template['config'] ?? [] as $field) {
            if (isset($field['name']) && ($field['type'] ?? '') !== 'hidden') {
                $config[$field['name']] = $validated['config'][$field['name']] ?? ($field['default'] ?? null);
            }
        }

        $transport = new AlertTransport;
        $transport->transport_name = $validated['name'];
        $transport->transport_type = $type;
        $transport->is_default = (bool) ($validated['is_default'] ?? false);
        $transport->transport_config = $config;
        $transport->save();

        return $this->response([$this->serialize($transport)], 201);
    }

    public function destroy(AlertTransport $transport): JsonResponse
    {
        $this->authorize('delete', AlertTransport::class);

        DB::transaction(function () use ($transport): void {
            AlertOperationTransportMap::query()
                ->where('target_type', 'single')
                ->where('transport_or_group_id', $transport->transport_id)
                ->delete();
            $transport->delete();
        });

        return response()->json(['status' => 'ok', 'message' => 'Transport deleted.']);
    }

    /** Secrets stay out of API listings. */
    private function serialize(AlertTransport $transport): array
    {
        $config = [];
        foreach ((array) $transport->transport_config as $key => $value) {
            $config[$key] = Str::contains(Str::lower((string) $key), ['password', 'secret', 'token']) ? ($value === null || $value === '' ? null : '********') : $value;
        }

        return [
            'transport_id' => (int) $transport->transport_id,
            'transport_name' => $transport->transport_name,
            'transport_type' => $transport->transport_type,
            'is_default' => (bool) $transport->is_default,
            'transport_config' => $config,
        ];
    }

    private function response(array $transports, int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'count' => count($transports),
            'transports' => $transports,
        ], $status);
    }
}
