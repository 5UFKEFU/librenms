<?php

namespace LibreNMS\Tests\Unit\Services;

use App\Services\LiveSnmpMetricService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class LiveSnmpMetricServiceTest extends TestCase
{
    #[Test]
    public function cpu_busy_and_wait_share_the_complete_tick_denominator(): void
    {
        [$cpu, $wait] = $this->invoke('cpuMetrics', [
            ['user' => '1000', 'nice' => '1000', 'system' => '1000', 'idle' => '1000', 'wait' => '1000'],
            ['user' => '1020', 'nice' => '1000', 'system' => '1005', 'idle' => '1050', 'wait' => '1025'],
        ]);

        $this->assertSame(25.0, $cpu['value']);
        $this->assertSame(25.0, $wait['value']);
    }

    #[Test]
    public function network_uses_the_actual_counter_window(): void
    {
        $network = $this->invoke('networkMetrics', [
            ['7' => ['in' => '1000', 'out' => '2000']],
            ['7' => ['in' => '10001000', 'out' => '20002000']],
            [['if_index' => 7, 'name' => 'eth0']],
            10.0,
        ]);

        $this->assertSame(8_000_000.0, $network['in_bps']);
        $this->assertSame(16_000_000.0, $network['out_bps']);
    }

    #[Test]
    public function unchanged_agent_counters_are_not_a_valid_baseline(): void
    {
        $current = ['timestamp' => 20.0, 'network' => ['7' => ['in' => '1000', 'out' => '2000']]];
        $unchanged = ['timestamp' => 10.0, 'network' => ['7' => ['in' => '1000', 'out' => '2000']]];
        $changed = ['timestamp' => 8.0, 'network' => ['7' => ['in' => '900', 'out' => '1900']]];

        $this->assertNull($this->invoke('selectBaseline', [[$unchanged], $current, 10.0, 'network']));
        $this->assertSame($changed, $this->invoke('selectBaseline', [[$changed, $unchanged], $current, 10.0, 'network']));
    }

    #[Test]
    public function host_resources_cpu_averages_the_discovered_processors(): void
    {
        $metric = $this->invoke('hostResourcesCpuMetrics', [
            [
                '.1.3.6.1.2.1.25.3.3.1.2.1' => '59',
                '.1.3.6.1.2.1.25.3.3.1.2.2' => '13',
                '.1.3.6.1.2.1.25.3.3.1.2.3' => '14',
                '.1.3.6.1.2.1.25.3.3.1.2.4' => '15',
            ],
            [
                '.1.3.6.1.2.1.25.3.3.1.2.1',
                '.1.3.6.1.2.1.25.3.3.1.2.2',
                '.1.3.6.1.2.1.25.3.3.1.2.3',
                '.1.3.6.1.2.1.25.3.3.1.2.4',
            ],
        ]);

        $this->assertSame(25.25, $metric['value']);
        $this->assertSame(4, $metric['sampled_processor_count']);
    }

    #[Test]
    public function host_resources_memory_uses_allocation_units_size_and_used(): void
    {
        $metric = $this->invoke('hostResourcesMemoryMetrics', [
            [
                '.1.3.6.1.2.1.25.2.3.1.4.65536' => '1024',
                '.1.3.6.1.2.1.25.2.3.1.5.65536' => '3997696',
                '.1.3.6.1.2.1.25.2.3.1.6.65536' => '237588',
            ],
            [
                'allocation_units' => '.1.3.6.1.2.1.25.2.3.1.4.65536',
                'size' => '.1.3.6.1.2.1.25.2.3.1.5.65536',
                'used' => '.1.3.6.1.2.1.25.2.3.1.6.65536',
            ],
        ]);

        $this->assertSame(5.9431, $metric['value']);
        $this->assertSame(243290112, $metric['used_bytes']);
        $this->assertSame(4093640704, $metric['total_bytes']);
    }

    private function invoke(string $method, array $arguments): mixed
    {
        return (new ReflectionMethod(LiveSnmpMetricService::class, $method))
            ->invokeArgs(new LiveSnmpMetricService, $arguments);
    }
}
