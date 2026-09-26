<?php

namespace LibreNMS\Tests\Feature\Http;

use App\Models\Device;
use App\Models\Mempool;
use App\Models\Processor;
use App\Services\CurrentRrdMetricService;
use App\Services\PolledDeviceMetricService;
use Illuminate\Database\Eloquent\Collection;
use LibreNMS\Tests\TestCase;
use Mockery;

class PolledMetricSummaryTest extends TestCase
{
    public function test_summary_requires_api_authentication(): void
    {
        $this->getJson('/api/v0/devices/example/metrics/summary')->assertUnauthorized();
    }

    public function test_database_values_survive_missing_rrds_without_live_sampling(): void
    {
        $rrd = Mockery::mock(CurrentRrdMetricService::class);
        $rrd->shouldReceive('filename')->andReturn('missing.rrd');
        $rrd->shouldReceive('latestAverage')->times(6)->andReturnNull();
        $rrd->shouldReceive('latestAverages')->once()->andReturn([]);
        $device = new Device;
        $device->hostname = 'example';
        $device->last_polled = '2026-09-26 12:00:00';
        $processor = new Processor;
        $processor->processor_usage = 23;
        $pool = new Mempool;
        $pool->mempool_descr = 'Physical memory';
        $pool->mempool_perc = '50';
        $pool->mempool_used = '1024';
        $pool->mempool_total = '2048';
        $device->setRelation('processors', new Collection([$processor]));
        $device->setRelation('mempools', new Collection([$pool]));
        $device->setRelation('diskIo', new Collection);
        $port = new \App\Models\Port;
        $port->ifOperStatus = 'up';
        $port->ifAdminStatus = 'up';
        $port->ifType = 'ethernetCsmacd';
        $port->ifInOctets_rate = 0;
        $port->ifOutOctets_rate = 125;
        $device->setRelation('ports', new Collection([$port]));
        $metrics = (new PolledDeviceMetricService($rrd))->read($device);
        $this->assertSame(23.0, $metrics['cpu']['value']);
        $this->assertSame(50.0, $metrics['memory']['value']);
        $this->assertSame(1024.0, $metrics['memory']['used_bytes']);
        $this->assertFalse($metrics['disk']['available']);
        $this->assertFalse($metrics['load']['available']);
        $this->assertSame('poller_database_rrd', $metrics['source']);
        $this->assertNotNull($metrics['sampled_at']);
        $this->assertTrue($metrics['network']['available']);
        $this->assertEquals(0, $metrics['network']['in_bps']);
        $this->assertEquals(1000, $metrics['network']['out_bps']);
    }
}
