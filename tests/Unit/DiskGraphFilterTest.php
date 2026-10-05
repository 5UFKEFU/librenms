<?php

namespace LibreNMS\Tests\Unit;

use LibreNMS\Data\Graphing\DiskGraphScope;
use LibreNMS\Data\Graphing\GraphParameters;
use LibreNMS\Tests\TestCase;

class DiskGraphFilterTest extends TestCase
{
    public function testScopesAndDisks(): void
    {
        $disks = ['nvme3n1', 'nvme3n1p1', 'nvme3n1p2', 'sda', 'sda1', 'md1', 'bcache0', 'loop0'];
        $pick = fn (string $scope, ?string $disk = null) => array_values(array_filter($disks, fn ($name) => DiskGraphScope::includes($name, $scope, $disk)));
        $this->assertSame($disks, $pick('all'));
        $this->assertSame(['nvme3n1', 'sda'], $pick('physical'));
        $this->assertSame(['nvme3n1p1', 'nvme3n1p2', 'sda1'], $pick('partitions'));
        $this->assertSame(['nvme3n1', 'nvme3n1p1', 'nvme3n1p2'], $pick('all', 'nvme3n1'));
        $this->assertSame(['nvme3n1p1', 'nvme3n1p2'], $pick('partitions', 'nvme3n1'));
        $this->assertSame(['md1'], $pick('all', 'md1'));
        $this->assertSame('nvme3n1', DiskGraphScope::partitionParent('nvme3n1p2'));
        $this->assertNull(DiskGraphScope::partitionParent('md10'));
    }

    public function testParametersOnlyApplyToDiskGraphs(): void
    {
        $disk = new GraphParameters(['type' => 'device_diskio_bits', 'disk_scope' => 'partitions', 'disk' => 'nvme3n1', 'disk_direction' => 'write']);
        $this->assertSame('partitions', $disk->diskScope);
        $this->assertSame('nvme3n1', $disk->diskName);
        $this->assertSame('write', $disk->diskDirection);
        $this->assertFalse($disk->physicalDisksOnly);
        $this->assertTrue((new GraphParameters(['type' => 'device_diskio_ops', 'disk_scope' => 'physical']))->physicalDisksOnly);

        $other = new GraphParameters(['type' => 'device_bits', 'disk_scope' => 'partitions', 'disk' => 'sda', 'disk_direction' => 'read']);
        $this->assertSame('all', $other->diskScope);
        $this->assertNull($other->diskName);
        $this->assertSame('both', $other->diskDirection);
        $this->assertNull((new GraphParameters(['type' => 'device_diskio_bits', 'disk' => '../etc']))->diskName);
    }
}
