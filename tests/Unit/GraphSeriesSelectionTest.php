<?php

namespace LibreNMS\Tests\Unit;

use LibreNMS\Data\Graphing\GraphSeriesSelection;
use LibreNMS\Exceptions\RrdGraphException;
use PHPUnit\Framework\TestCase;

class GraphSeriesSelectionTest extends TestCase
{
    public function testCpuUsesOriginalCoreValuesAndReleasesFixedScale(): void
    {
        $options = ['-u', 100, '-l', 0, '--rigid', 'DEF:usage0=cpu.rrd:usage:AVERAGE', 'CDEF:usage_cdef0=usage0,16,/', 'AREA:usage_cdef0#ff8800:CPU 0:STACK', 'GPRINT:usage0:LAST:%5lf', 'AREA:usage_cdef1#ff9900:CPU 1:STACK', 'GPRINT:usage1:LAST:%5lf'];
        $selected = GraphSeriesSelection::select($options, 'device_processor', 'usage_cdef0');
        $this->assertContains('AREA:usage0#ff8800:CPU 0', $selected);
        $this->assertNotContains('-u', $selected);
        $this->assertNotContains('--rigid', $selected);
        $this->assertContains('--alt-autoscale', $selected);
        $this->assertStringNotContainsString('usage_cdef1', implode(' ', $selected));
    }

    public function testWriteMarkerResolvesActualAreaAndRemovesStackAndReferenceRules(): void
    {
        $options = ['DEF:outB0=disk.rrd:write:AVERAGE', 'CDEF:outbits0=outB0,8,*', 'CDEF:outbits0_neg=outbits0,-1,*', 'AREA:inbits0#008800:Disk Read', 'GPRINT:inbits0:LAST:%5lf', 'HRULE:999999999999999#000088:Disk Write', 'GPRINT:outbits0:LAST:%5lf', 'AREA:outbits0_neg#000088::STACK', 'HRULE:0#999999'];
        $entries = GraphSeriesSelection::entries($options, 'device_diskio_ops');
        $this->assertSame(['inbits0', 'outbits0_neg'], array_column($entries, 'key'));
        $selected = GraphSeriesSelection::select($options, 'device_diskio_ops', 'outbits0_neg');
        $this->assertContains('AREA:outbits0_neg#000088:', $selected);
        $this->assertStringNotContainsString('HRULE:', implode(' ', $selected));
        $this->assertStringNotContainsString('STACK', implode(' ', $selected));
        $this->assertStringNotContainsString('AREA:inbits', implode(' ', $selected));
    }

    public function testUnknownSeriesCannotInjectDrawingInstructions(): void
    {
        $this->expectException(RrdGraphException::class);
        GraphSeriesSelection::select([], 'device_processor', 'not_a_series');
    }
}
