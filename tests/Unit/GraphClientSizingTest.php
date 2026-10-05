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
}
