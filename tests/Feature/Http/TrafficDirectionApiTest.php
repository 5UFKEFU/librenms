<?php

namespace LibreNMS\Tests\Feature\Http;

use App\Facades\LibrenmsConfig;
use App\Facades\Rrd;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use LibreNMS\Tests\TestCase;
use LibreNMS\Util\Graph;
use Spatie\Permission\Models\Role;

class TrafficDirectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function testOutboundDeviceGraphKeepsInterfaceAndTotalLabels(): void
    {
        Role::findOrCreate('admin');
        $this->actingAs(User::factory()->create(['enabled' => 1])->assignRole('admin'));
        LibrenmsConfig::set('auth_mechanism', 'mysql');
        $device = Device::factory()->create();
        Port::factory()->for($device)->create(['ifName' => 'eth0', 'ifDescr' => 'eth0', 'disabled' => 0, 'deleted' => 0]);
        Port::factory()->for($device)->create(['ifName' => 'eth1', 'ifDescr' => 'eth1', 'disabled' => 0, 'deleted' => 0]);
        Rrd::partialMock()->shouldReceive('checkRrdExists')->andReturn(true);
        $options = Graph::getRrdOptions(['type' => 'device_bits', 'id' => $device->device_id, 'width' => 1100, 'height' => 420, 'traffic_direction' => 'out', 'traffic_same_axis' => 1]);
        $labels = implode("\n", array_filter($options, fn (string $option) => str_starts_with($option, 'HRULE:')));
        $this->assertMatchesRegularExpression('/eth0\s+Out/', $labels);
        $this->assertMatchesRegularExpression('/Total\s+Out/', $labels);
        $this->assertStringNotContainsString('  In', $labels);
    }

    public function testPortFilteringKeepsDefinitionsAndAcknowledgesEachDirection(): void
    {
        Role::findOrCreate('admin');
        $this->actingAs(User::factory()->create(['enabled' => 1])->assignRole('admin'));
        LibrenmsConfig::set('auth_mechanism', 'mysql');
        $device = Device::factory()->create();
        $port = Port::factory()->for($device)->create(['ifSpeed' => 1000000000]);
        require_once base_path('includes/html/api_functions.inc.php');
        Rrd::partialMock()->shouldReceive('graph')->times(3)->andReturn('<svg/>');
        foreach (['in', 'out', 'both'] as $direction) {
            $vars = ['type' => 'port_bits', 'id' => $port->port_id, 'traffic_direction' => $direction, 'traffic_same_axis' => 1, 'previous' => 'yes'];
            $options = Graph::getRrdOptions($vars);
            $this->assertContains('CDEF:doutoctets=outoctets,1,*', $options);
            $this->assertContains('CDEF:doutoctetsX=outoctetsX,1,*', $options);
            $commands = implode("\n", $options);
            $this->assertSame($direction !== 'out', str_contains($commands, 'LINE2:inbits#00A6ED'));
            $this->assertSame($direction !== 'in', str_contains($commands, 'LINE2:doutbits#FF8C00'));
            $response = api_get_graph(Request::create('/graph', 'GET', $vars), ['type' => 'port_bits', 'id' => $port->port_id]);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame($direction, $response->headers->get('X-Traffic-Direction'));
        }
    }
}
