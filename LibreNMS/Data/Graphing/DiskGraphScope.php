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

    /** The whole disk a partition belongs to (sda for sda1, nvme0n1 for nvme0n1p2), or null. */
    public static function partitionParent(string $name): ?string
    {
        $name = preg_replace('~^/dev/~', '', trim($name));
        if (preg_match('/^((?:sd|hd|vd|xvd)[a-z]+)[0-9]+$/D', $name, $match)
            || preg_match('/^((?:nvme[0-9]+n[0-9]+|mmcblk[0-9]+))p[0-9]+$/D', $name, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * Whether a disk graph limited to $scope (all, physical, partitions) and,
     * optionally, to one disk and its partitions includes $name.
     */
    public static function includes(string $name, string $scope, ?string $disk = null): bool
    {
        $parent = self::partitionParent($name);
        if ($disk !== null && $name !== $disk && $parent !== $disk) {
            return false;
        }

        return match ($scope) {
            'physical' => self::isWholeDisk($name),
            'partitions' => $parent !== null,
            default => true,
        };
    }
}
