<?php

namespace LibreNMS\Tests\Unit;

use LibreNMS\Data\Graphing\GraphParameters;
use LibreNMS\Tests\TestCase;

class GraphClientSizingTest extends TestCase
{
    public function testThumbnailsStillShrinkWithoutAClientFontSize(): void
    {
        $options = (new GraphParameters(['type' => 'device_bits', 'width' => 271, 'height' => 160, 'graph_type' => 'svg']))->toRrdOptions();
        $this->assertContains('-m', $options);
        $this->assertNotContains('--disable-rrdtool-tag', $options);
    }

    public function testAClientFontSizeKeepsFullScaleAndDropsTheTag(): void
    {
        // A phone card asks for a narrow graph with readable text; a 0.75 zoom
        // shrank the whole image, legend included, so the app could not crop it.
        $options = (new GraphParameters(['type' => 'device_bits', 'width' => 271, 'height' => 160, 'graph_type' => 'svg', 'font_size' => 10]))->toRrdOptions();
        $this->assertNotContains('-m', $options);
        $this->assertContains('--disable-rrdtool-tag', $options);
        $this->assertContains('LEGEND:10:' . \App\Facades\LibrenmsConfig::get('mono_font'), $options);
    }

    public function testNarrowClientGraphsUseShortTimeLabels(): void
    {
        $grid = fn (array $vars) => $this->xGrid((new GraphParameters($vars + ['type' => 'device_bits', 'height' => 160, 'graph_type' => 'svg', 'font_size' => 10]))->toRrdOptions());
        $this->assertSame('HOUR:12:DAY:1:DAY:2:0:%m/%d', $grid(['width' => 271, 'from' => '-7d']));
        $this->assertSame('HOUR:12:DAY:1:DAY:1:0:%m/%d', $grid(['width' => 420, 'from' => '-7d']));
        $this->assertSame('HOUR:1:HOUR:6:HOUR:4:0:%H:%M', $grid(['width' => 361, 'from' => '-1d']));
        $this->assertSame('MINUTE:30:HOUR:1:HOUR:2:0:%H:%M', $grid(['width' => 271, 'from' => '-6h']));
        $this->assertNull($grid(['width' => 900, 'from' => '-7d']), 'Wide graphs keep rrdtool labels');
        $this->assertNull($grid(['width' => 271, 'from' => '-30d']), 'Month and year labels are already short');
        $this->assertNull($this->xGrid((new GraphParameters(['type' => 'device_bits', 'width' => 271, 'from' => '-7d', 'graph_type' => 'svg']))->toRrdOptions()),
            'Only graphs whose client sets the font size change');
    }

    private function xGrid(array $options): ?string
    {
        $index = array_search('--x-grid', $options, true);

        return $index === false ? null : $options[$index + 1];
    }
}
