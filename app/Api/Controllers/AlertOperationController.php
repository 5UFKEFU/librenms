<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AlertOperation;
use App\Models\AlertOperationSegment;
use App\Models\AlertOperationTransportMap;
use App\Models\AlertTransport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Global alert operations over the API: list them, and add or remove one
 * transport on their segments so an integration can receive every alert the
 * operations deliver without rewriting the rules that use them.
 */
class AlertOperationController extends Controller
{
    private const PHASES = ['problem', 'recovery', 'update'];

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', AlertOperation::class);

        $operations = AlertOperation::query()->with('segments')->orderBy('id')->get()
            ->map(fn (AlertOperation $operation) => $operation->toApiArray());

        return $this->response($operations->all());
    }

    public function attachTransport(AlertOperation $operation, Request $request): JsonResponse
    {
        $this->authorize('update', AlertOperation::class);

        $validated = $request->validate([
            'transport_id' => 'required|integer|min:1',
            'phases' => 'sometimes|array|min:1',
            'phases.*' => 'in:problem,recovery,update',
        ]);
        $transport = AlertTransport::query()->find($validated['transport_id']);
        if ($transport === null) {
            return response()->json(['status' => 'error', 'message' => 'Transport not found.'], 404);
        }
        $phases = $validated['phases'] ?? self::PHASES;

        $added = 0;
        DB::transaction(function () use ($operation, $transport, $phases, &$added): void {
            foreach ($operation->segments()->whereIn('operation_phase', $phases)->get() as $segment) {
                /** @var AlertOperationSegment $segment */
                $exists = AlertOperationTransportMap::query()
                    ->where('segment_id', $segment->id)
                    ->where('target_type', 'single')
                    ->where('transport_or_group_id', $transport->transport_id)
                    ->exists();
                if (! $exists) {
                    AlertOperationTransportMap::create([
                        'segment_id' => $segment->id,
                        'transport_or_group_id' => $transport->transport_id,
                        'target_type' => 'single',
                    ]);
                    $added++;
                }
            }
        });

        return $this->response([$operation->fresh()->toApiArray()], $added);
    }

    public function detachTransport(AlertOperation $operation, AlertTransport $transport): JsonResponse
    {
        $this->authorize('update', AlertOperation::class);

        $removed = AlertOperationTransportMap::query()
            ->whereIn('segment_id', $operation->segments()->select('id'))
            ->where('target_type', 'single')
            ->where('transport_or_group_id', $transport->transport_id)
            ->delete();

        return $this->response([$operation->fresh()->toApiArray()], $removed);
    }

    private function response(array $operations, ?int $changed = null): JsonResponse
    {
        $body = ['status' => 'ok', 'count' => count($operations), 'operations' => $operations];
        if ($changed !== null) {
            $body['changed_segments'] = $changed;
        }

        return response()->json($body);
    }
}
