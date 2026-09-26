<?php

namespace LibreNMS\Tests\Feature\Http;

use App\Facades\LibrenmsConfig;
use App\Facades\Rrd;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LibreNMS\Tests\TestCase;
use LibreNMS\Util\Graph;
use Spatie\Permission\Models\Role;

class DiskGraphScopeApiTest extends TestCase
{
    use RefreshDatabase;

    public function testPhysicalScopeKeepsReadAndWriteWithoutPartitions(): void
    {
        Role::findOrCreate('admin');
        $this->actingAs(User::factory()->create(['enabled' => 1])->assignRole('admin'));
        LibrenmsConfig::set('auth_mechanism', 'mysql');
        $device = Device::factory()->create();
        foreach (['nvme0n1', 'nvme0n1p1', 'sda', 'sda1', 'md0', 'dm-0', 'bcache0'] as $index => $name) {
            DB::table('ucd_diskio')->insert(['device_id' => $device->device_id, 'diskio_index' => $index, 'diskio_descr' => $name]);
        }
        Rrd::partialMock()->shouldReceive('checkRrdExists')->andReturn(true);
        Rrd::partialMock()->shouldReceive('graph')->andReturn('<svg/>');
        require_once base_path('includes/html/api_functions.inc.php');
        foreach (['device_diskio_bits', 'device_diskio_ops'] as $type) {
            $vars = ['type' => $type, 'id' => $device->device_id, 'width' => 1100, 'height' => 420];
            $legacy = implode("\n", Graph::getRrdOptions($vars));
            $this->assertStringContainsString('nvme0n1p1', $legacy);
            $this->assertStringContainsString('md0', $legacy);
            $filtered = implode("\n", Graph::getRrdOptions($vars + ['disk_scope' => 'physical']));
            foreach (['nvme0n1p1', 'sda1', 'md0', 'dm-0', 'bcache0'] as $name) {
                $this->assertStringNotContainsString($name, $filtered);
            }
            $this->assertMatchesRegularExpression('/nvme0n1\s+Read/', $filtered);
            $this->assertMatchesRegularExpression('/nvme0n1\s+Write/', $filtered);
            $this->assertMatchesRegularExpression('/sda\s+Read/', $filtered);
            $this->assertMatchesRegularExpression('/sda\s+Write/', $filtered);
            $response = api_get_graph(Request::create('/graph', 'GET', ['disk_scope' => 'physical']), $vars);
            $this->assertSame('physical', $response->headers->get('X-Disk-Scope'));
        }
    }
}
