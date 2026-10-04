<?php

namespace LibreNMS\Tests\Unit;

use LibreNMS\Data\Graphing\GraphParameters;
use LibreNMS\RRD\RrdProcess;
use LibreNMS\Tests\TestCase;

class CactiTrafficGraphTest extends TestCase
{
    private function cactiOptions(array $vars): array
    {
        $graph_params = new GraphParameters($vars);
        $rrd_list = [
            ['filename' => '/tmp/a.rrd', 'ds_in' => 'INOCTETS', 'ds_out' => 'OUTOCTETS'],
            ['filename' => '/tmp/b.rrd', 'ds_in' => 'INOCTETS', 'ds_out' => 'OUTOCTETS'],
        ];
        $rrd_options = [];
        $float_precision = 2;
        require base_path('includes/html/graphs/generic_cacti_traffic.inc.php');

        return $rrd_options;
    }

    public function testCactiStyleTotalsInterfacesAsGreenInboundAreaAndBlueOutboundLine(): void
    {
        $options = $this->cactiOptions(['type' => 'device_bits', 'traffic_style' => 'cacti']);
        $this->assertContains('CDEF:inbits=inoctets0,inoctets1,ADDNAN,8,*', $options);
        $this->assertContains('CDEF:outbits=outoctets0,outoctets1,ADDNAN,8,*', $options);
        $this->assertContains('AREA:inbits#00CF00:In ', $options);
        $this->assertContains('LINE1.5:outbits#002A97:Out', $options);
        $this->assertContains('GPRINT:outbits:MAX:%6.2lf%s', $options);
        $this->assertContains('GPRINT:percentile_out:%6.2lf%s\\n', $options, 'One escape level, so no backslash is printed');
        $this->assertEmpty(array_filter($options, fn ($option) => str_contains($option, 'doutbits')),
            'Outbound shares the inbound axis instead of being mirrored below it');
    }

    public function testSharedTrafficRestylingSkipsTheCactiStyle(): void
    {
        // The app sends traffic_same_axis=1 with the Cacti style; the shared
        // rewriting recoloured the green area and blue line into the default style.
        $cacti = new GraphParameters(['type' => 'device_bits', 'traffic_style' => 'cacti', 'traffic_same_axis' => '1', 'traffic_direction' => 'both']);
        $this->assertFalse($cacti->usesTrafficRestyling());
        $this->assertTrue((new GraphParameters(['type' => 'device_bits', 'traffic_same_axis' => '1']))->usesTrafficRestyling());
        $this->assertTrue((new GraphParameters(['type' => 'device_processor', 'traffic_style' => 'cacti']))->usesTrafficRestyling(),
            'Only traffic graphs have a Cacti style');
    }

    public function testCactiStyleHonoursASingleDirection(): void
    {
        $inbound = implode("\n", $this->cactiOptions(['type' => 'port_bits', 'traffic_style' => 'cacti', 'traffic_direction' => 'in']));
        $this->assertStringContainsString('AREA:inbits#00CF00', $inbound);
        $this->assertStringNotContainsString('LINE1.5:outbits', $inbound);
        $outbound = implode("\n", $this->cactiOptions(['type' => 'port_bits', 'traffic_style' => 'cacti', 'traffic_direction' => 'out']));
        $this->assertStringNotContainsString('AREA:inbits', $outbound);
        $this->assertStringContainsString('LINE1.5:outbits#002A97', $outbound);
    }

    public function testStyleAndFontSizeOnlyApplyWhereSupported(): void
    {
        $this->assertSame('cacti', (new GraphParameters(['type' => 'device_bits', 'traffic_style' => 'cacti']))->trafficStyle);
        $this->assertSame('default', (new GraphParameters(['type' => 'device_mempool', 'traffic_style' => 'cacti']))->trafficStyle);
        $this->assertSame('default', (new GraphParameters(['type' => 'port_bits', 'traffic_style' => 'other']))->trafficStyle);
        $this->assertSame(11, (new GraphParameters(['type' => 'device_bits', 'width' => 1000, 'font_size' => '11']))->font_size);
        $this->assertSame(8, (new GraphParameters(['type' => 'device_bits', 'width' => 1000, 'font_size' => '40']))->font_size);
        $this->assertSame(7, (new GraphParameters(['type' => 'device_bits', 'width' => 200]))->font_size);
    }

    public function testRequestedTimezoneTakesPrecedenceOverTheSessionPreference(): void
    {
        session(['preferences.timezone' => 'Europe/London']);
        $this->assertSame(['TZ' => 'Europe/London'], RrdProcess::timezoneEnvironment());
        config(['librenms.graph_timezone' => 'Asia/Bangkok']);
        $this->assertSame(['TZ' => 'Asia/Bangkok'], RrdProcess::timezoneEnvironment());
        config(['librenms.graph_timezone' => null]);
        session()->forget('preferences.timezone');
        $this->assertSame([], RrdProcess::timezoneEnvironment());
    }
}
