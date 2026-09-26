<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\UserWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileDashboardController extends Controller
{
    private const NAME = 'LibreNMS Mobile';
    private const TITLE = 'LibreNMS iOS Configuration';
    private const MARKER = 'FEBLIBRENMS_MOBILE_V1:';

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
            'layout' => 'required|array:version,modules',
            'layout.version' => 'required|integer|min:1|max:4',
            'layout.modules' => 'present|array|max:100',
            'layout.modules.*' => 'required|array',
            'layout.modules.*.id' => 'required|uuid|distinct',
            'layout.modules.*.kind' => 'required|string|max:64',
        ]);
        // Preserve module configuration fields; they are JSON data, never executed.
        $layout = $request->input('layout');
        DB::transaction(function () use ($request, $layout): void {
            $userID = $request->user()->user_id;
            DB::table('users')->where('user_id', $userID)->lockForUpdate()->first();
            $dashboard = Dashboard::firstOrCreate(['user_id' => $userID, 'dashboard_name' => self::NAME], ['access' => 0]);
            $widget = $dashboard->widgets()->where('user_id', $userID)->where('widget', 'notes')->get()->first(fn ($item) => $item->title === self::TITLE || str_contains($item->settings['notes'] ?? '', self::MARKER));
            $widget ??= new UserWidget(['user_id' => $userID, 'dashboard_id' => $dashboard->dashboard_id, 'widget' => 'notes', 'col' => 1, 'row' => 1, 'size_x' => 4, 'size_y' => 2, 'refresh' => 60]);
            $widget->title = self::TITLE;
            $settings = array_merge($widget->settings ?? [], [
                'title' => self::TITLE,
                'mobile_layout' => $layout,
                'notes' => '<pre>Managed by the LibreNMS iOS app.\n' . self::MARKER . base64_encode(json_encode($layout)) . '</pre>',
            ]);
            abort_if(strlen(json_encode($settings)) > 64000, 413, 'Encoded dashboard layout exceeds storage limit.');
            $widget->settings = $settings;
            $widget->save();
        });

        return response()->json(['status' => 'ok', 'layout' => $layout]);
    }
}
