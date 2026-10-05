<?php

namespace LibreNMS\Tests\Unit;

use LibreNMS\Data\Graphing\HealthItemGraph;
use LibreNMS\Tests\TestCase;

class HealthItemGraphTest extends TestCase
{
    public function testItemsUseTheirOwnGraphs(): void
    {
        $this->assertSame('processor_usage', HealthItemGraph::type('device_processor'));
        $this->assertSame('mempool_usage', HealthItemGraph::type('device_mempool'));
        $this->assertSame('storage_usage', HealthItemGraph::type('device_storage'));
        $this->assertSame('diskio_bits', HealthItemGraph::type('device_diskio'));
        $this->assertSame('diskio_ops', HealthItemGraph::type('device_diskio_ops'));
        $this->assertSame('sensor_temperature', HealthItemGraph::type('device_temperature'), 'Sensor classes are unchanged');
    }

    public function testEveryItemGraphHasATemplate(): void
    {
        foreach (['device_processor', 'device_mempool', 'device_storage', 'device_diskio', 'device_diskio_ops'] as $type) {
            [$group, $graph] = explode('_', HealthItemGraph::type($type), 2);
            $this->assertFileExists(base_path("includes/html/graphs/$group/$graph.inc.php"));
        }
        $this->assertFileExists(base_path('includes/html/graphs/device/' . substr(HealthItemGraph::aggregateType('device_diskio'), 7) . '.inc.php'));
    }
}
