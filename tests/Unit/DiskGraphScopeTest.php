<?php

namespace LibreNMS\Tests\Unit;

use LibreNMS\Data\Graphing\DiskGraphScope;
use PHPUnit\Framework\TestCase;

class DiskGraphScopeTest extends TestCase
{
    public function testWholeDevicesExcludePartitionsAndStackedStorage(): void
    {
        foreach (['sda', 'sdab', '/dev/sdb', 'nvme0n1', 'nvme12n3', 'vda', 'xvdb', 'mmcblk0', 'ada0', 'da1', 'disk2'] as $name) {
            $this->assertTrue(DiskGraphScope::isWholeDisk($name), $name);
        }
        foreach (['sda1', 'nvme0n1p1', 'nvme12n3p20', 'mmcblk0p1', 'mmcblk0boot0', 'md0', 'md127', 'dm-0', 'bcache0', 'loop0', 'zram0', 'ram0', 'sr0', 'ada0p1', 'disk2s1', '', '../sda'] as $name) {
            $this->assertFalse(DiskGraphScope::isWholeDisk($name), $name);
        }
    }
}
