<?php

namespace LibreNMS\Data\Graphing;

final class DiskGraphScope
{
    // SNMP exposes OS block-device names, not hardware topology. Keep whole
    // devices, including disks presented to VMs; exclude partitions and stacked
    // software devices (md, dm, bcache, loop, zram) to avoid counting I/O twice.
    public static function isWholeDisk(string $name): bool
    {
        $name = preg_replace('~^/dev/~', '', trim($name));

        return (bool) preg_match('/^(?:(?:sd|hd|vd|xvd)[a-z]+|nvme[0-9]+n[0-9]+|mmcblk[0-9]+|(?:ada|da|ad|disk)[0-9]+)$/D', $name);
    }
}
