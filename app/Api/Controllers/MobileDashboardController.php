<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\UserWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileDashboardController extends Controller
{
    private const NAME = 'LibreNMS Mobile';
    private const TITLE = 'LibreNMS iOS Configuration';
    private const MARKER = 'FEBLIBRENMS_MOBILE_V1:';
    /** Layout keys added with multiple dashboards (version 5). Older apps never send them. */
    private const DASHBOARD_KEYS = ['dashboards', 'defaultDashboard', 'primaryName'];

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user(), 401);
        $dashboard = Dashboard::where('user_id', $request->user()->user_id)->where('dashboard_name', self::NAME)->first();
        $layout = null;
        if ($dashboard) {
            foreach ($dashboard->widgets()->where('user_id', $request->user()->user_id)->where('widget', 'notes')->get() as $widget) {
                $settings = $widget->settings ?? [];
                if (isset($settings['mobile_layout'])) {
                    $layout = $settings['mobile_layout'];
                    break;
                }
                // Read legacy iOS Notes layouts without requiring a web session.
                if (preg_match('/' . self::MARKER . '([A-Za-z0-9+\/=]+)/', $settings['notes'] ?? '', $matches)) {
                    $decoded = json_decode(base64_decode($matches[1], true) ?: '', true);
                    if (is_array($decoded) && isset($decoded['version'], $decoded['modules'])) {
                        $layout = $decoded;
                        break;
                    }
                }
            }
        }

        return response()->json(['status' => 'ok', 'layout' => $layout]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user(), 401);
        abort_if(strlen($request->getContent()) > 65536, 413, 'Dashboard layout exceeds 64 KiB.');
        $request->validate([
            'layout' => 'required|array:version,modules,dashboards,defaultDashboard,primaryName',
            'layout.version' => 'required|integer|min:1|max:5',
            'layout.modules' => 'present|array|max:100',
            'layout.modules.*' => 'required|array',
            'layout.modules.*.id' => 'required|uuid|distinct',
            'layout.modules.*.kind' => 'required|string|max:64',
            'layout.primaryName' => 'nullable|string|max:80',
            'layout.defaultDashboard' => 'nullable|uuid',
            'layout.dashboards' => 'sometimes|array|max:20',
            'layout.dashboards.*' => 'required|array',
            'layout.dashboards.*.id' => 'required|uuid|distinct',
            'layout.dashboards.*.name' => 'required|string|max:80',
            'layout.dashboards.*.kind' => 'required|string|in:manual,automatic',
            'layout.dashboards.*.modules' => 'sometimes|array|max:100',
            'layout.dashboards.*.modules.*' => 'required|array',
            'layout.dashboards.*.modules.*.id' => 'required|uuid',
            'layout.dashboards.*.modules.*.kind' => 'required|string|max:64',
            'layout.dashboards.*.policy' => 'sometimes|array',
            'layout.dashboards.*.order' => 'sometimes|array|max:1000',
            'layout.dashboards.*.order.*' => 'string|max:80',
        ]);
        // Preserve module configuration fields; they are JSON data, never executed.
        $layout = $request->input('layout');
        $this->assertDistinctModuleIDs($layout);
        $saved = null;
        DB::transaction(function () use ($request, $layout, &$saved): void {
            $userID = $request->user()->user_id;
            DB::table('users')->where('user_id', $userID)->lockForUpdate()->first();
            $dashboard = Dashboard::firstOrCreate(['user_id' => $userID, 'dashboard_name' => self::NAME], ['access' => 0]);
            $widget = $dashboard->widgets()->where('user_id', $userID)->where('widget', 'notes')->get()->first(fn ($item) => $item->title === self::TITLE || str_contains($item->settings['notes'] ?? '', self::MARKER));
            $widget ??= new UserWidget(['user_id' => $userID, 'dashboard_id' => $dashboard->dashboard_id, 'widget' => 'notes', 'col' => 1, 'row' => 1, 'size_x' => 4, 'size_y' => 2, 'refresh' => 60]);
            $saved = $this->keepingDashboards($layout, $widget->settings['mobile_layout'] ?? null);
            $widget->title = self::TITLE;
            // Only legacy readers use the Notes copy, so it carries just the
            // primary dashboard and leaves the storage budget to the full layout.
            $legacy = ['version' => min(4, (int) $saved['version']), 'modules' => $saved['modules']];
            $settings = array_merge($widget->settings ?? [], [
                'title' => self::TITLE,
                'mobile_layout' => $saved,
                'notes' => '<pre>Managed by the LibreNMS iOS app.\n' . self::MARKER . base64_encode(json_encode($legacy)) . '</pre>',
            ]);
            abort_if(strlen(json_encode($settings)) > 64000, 413, 'Encoded dashboard layout exceeds storage limit.');
            $widget->settings = $settings;
            $widget->save();
        });

        return response()->json(['status' => 'ok', 'layout' => $saved]);
    }

    /**
     * Apps that predate multiple dashboards send only the primary dashboard.
     * Keep the other dashboards they cannot see instead of deleting them.
     */
    private function keepingDashboards(array $layout, mixed $stored): array
    {
        if (array_key_exists('dashboards', $layout) || ! is_array($stored) || ! isset($stored['dashboards'])) {
            return $layout;
        }
        foreach (self::DASHBOARD_KEYS as $key) {
            if (array_key_exists($key, $stored)) {
                $layout[$key] = $stored[$key];
            }
        }
        $layout['version'] = max((int) $layout['version'], (int) ($stored['version'] ?? 5));

        return $layout;
    }

    /** Module ids identify cards across every dashboard, not only within one list. */
    private function assertDistinctModuleIDs(array $layout): void
    {
        $ids = array_column($layout['modules'], 'id');
        foreach ($layout['dashboards'] ?? [] as $dashboard) {
            array_push($ids, ...array_column($dashboard['modules'] ?? [], 'id'));
        }
        if (count($ids) !== count(array_unique(array_map('strtolower', $ids)))) {
            throw ValidationException::withMessages(['layout.dashboards' => 'Dashboard module ids must be unique across dashboards.']);
        }
    }
}
