<?php

namespace LibreNMS\Tests\Feature\Http;

use App\Api\Controllers\MobileDashboardController;
use App\Models\Dashboard;
use App\Models\User;
use App\Models\UserWidget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LibreNMS\Tests\TestCase;

class MobileDashboardApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mobile_test', 'database.connections.mobile_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('mobile_test');
        Schema::create('users', function (Blueprint $table): void { $table->integer('user_id')->primary(); });
        Schema::create('dashboards', function (Blueprint $table): void {
            $table->increments('dashboard_id');
            $table->integer('user_id');
            $table->string('dashboard_name');
            $table->integer('access')->default(0);
        });
        Schema::create('users_widgets', function (Blueprint $table): void {
            $table->increments('user_widget_id');
            foreach (['user_id', 'dashboard_id', 'col', 'row', 'size_x', 'size_y', 'refresh'] as $field) { $table->integer($field); }
            $table->string('widget');
            $table->string('title');
            $table->text('settings');
        });
        DB::table('users')->insert([['user_id' => 1], ['user_id' => 2]]);
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v0/me/mobile-dashboard')->assertUnauthorized();
        $this->putJson('/api/v0/me/mobile-dashboard', ['layout' => $this->layout()])->assertUnauthorized();
    }

    private function request(int $userID, ?array $layout = null): Request
    {
        $request = Request::create('/api/v0/me/mobile-dashboard', $layout === null ? 'GET' : 'PUT', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $layout === null ? '' : json_encode(['layout' => $layout]));
        $user = new User;
        $user->user_id = $userID;
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function layout(): array
    {
        return ['version' => 4, 'modules' => [['id' => '00000000-0000-4000-8000-000000000001', 'kind' => 'overview', 'title' => 'My dashboard', 'metrics' => ['cpu'], 'deviceIDs' => [4, 5]]]];
    }

    public function test_save_load_is_idempotent_and_isolated_by_authenticated_user(): void
    {
        $controller = new MobileDashboardController;
        $layout = $this->layout();
        $controller->update($this->request(1, $layout));
        $controller->update($this->request(1, $layout));
        $this->assertSame($layout, $controller->show($this->request(1))->getData(true)['layout']);
        $this->assertNull($controller->show($this->request(2))->getData(true)['layout']);
        $this->assertSame(1, Dashboard::count());
        $this->assertSame(1, UserWidget::count());
        $controller->update($this->request(2, ['version' => 4, 'modules' => []]));
        $this->assertSame($layout, $controller->show($this->request(1))->getData(true)['layout']);
    }

    public function test_reads_legacy_notes_without_web_cookies(): void
    {
        $controller = new MobileDashboardController;
        $layout = $this->layout();
        $controller->update($this->request(1, $layout));
        $widget = UserWidget::first();
        $settings = $widget->settings;
        unset($settings['mobile_layout']);
        $widget->settings = $settings;
        $widget->save();
        $this->assertSame($layout, $controller->show($this->request(1))->getData(true)['layout']);
    }

    private function multiDashboardLayout(): array
    {
        return [
            'version' => 5,
            'modules' => $this->layout()['modules'],
            'primaryName' => 'Main',
            'defaultDashboard' => '00000000-0000-4000-8000-0000000000a2',
            'dashboards' => [
                ['id' => '00000000-0000-4000-8000-0000000000a1', 'name' => 'Edge', 'kind' => 'manual',
                    'modules' => [['id' => '00000000-0000-4000-8000-000000000011', 'kind' => 'graph', 'deviceID' => 7]]],
                ['id' => '00000000-0000-4000-8000-0000000000a2', 'name' => 'HK traffic', 'kind' => 'automatic',
                    'policy' => ['hostnameMatch' => 'prefix', 'hostname' => 'hk', 'graphs' => ['device_bits']], 'order' => ['7|device_bits']],
            ],
        ];
    }

    public function test_multiple_dashboards_round_trip(): void
    {
        $controller = new MobileDashboardController;
        $layout = $this->multiDashboardLayout();
        $controller->update($this->request(1, $layout));
        $this->assertSame($layout, $controller->show($this->request(1))->getData(true)['layout']);
    }

    public function test_older_app_saving_keeps_dashboards_it_cannot_see(): void
    {
        $controller = new MobileDashboardController;
        $controller->update($this->request(1, $this->multiDashboardLayout()));
        $legacy = ['version' => 4, 'modules' => [['id' => '00000000-0000-4000-8000-000000000002', 'kind' => 'alerts']]];
        $controller->update($this->request(1, $legacy));
        $saved = $controller->show($this->request(1))->getData(true)['layout'];
        $this->assertSame($legacy['modules'], $saved['modules']);
        $this->assertSame(5, $saved['version']);
        $this->assertSame($this->multiDashboardLayout()['dashboards'], $saved['dashboards']);
        $this->assertSame('00000000-0000-4000-8000-0000000000a2', $saved['defaultDashboard']);
        // The legacy Notes copy stays readable by older parsers.
        $widget = UserWidget::first();
        preg_match('/FEBLIBRENMS_MOBILE_V1:([A-Za-z0-9+\/=]+)/', $widget->settings['notes'], $matches);
        $this->assertSame(['version' => 4, 'modules' => $legacy['modules']], json_decode(base64_decode($matches[1]), true));
    }

    public function test_module_ids_must_be_unique_across_dashboards(): void
    {
        $controller = new MobileDashboardController;
        $layout = $this->multiDashboardLayout();
        $layout['dashboards'][0]['modules'][0]['id'] = $layout['modules'][0]['id'];
        $this->expectException(ValidationException::class);
        $controller->update($this->request(1, $layout));
    }

    public function test_rejects_unknown_dashboard_kind(): void
    {
        $controller = new MobileDashboardController;
        $layout = $this->multiDashboardLayout();
        $layout['dashboards'][1]['kind'] = 'smart';
        try {
            $controller->update($this->request(1, $layout));
            $this->fail('Unknown dashboard kind accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('layout.dashboards.1.kind', $e->errors());
        }
    }

    public function test_invalid_layout_does_not_overwrite_saved_configuration(): void
    {
        $controller = new MobileDashboardController;
        $layout = $this->layout();
        $controller->update($this->request(1, $layout));
        try {
            $controller->update($this->request(1, ['version' => 4, 'modules' => [['id' => 'invalid', 'kind' => 'overview']]]));
            $this->fail('Invalid module id accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('layout.modules.0.id', $e->errors());
        }
        $this->assertSame($layout, $controller->show($this->request(1))->getData(true)['layout']);
    }
}
