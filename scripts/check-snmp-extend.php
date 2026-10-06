#!/usr/bin/env php
<?php

/*
 * Nagios-style check that reads an SNMP extend on a monitored device, using
 * the device's own SNMP settings from LibreNMS. It lets a device check itself
 * (for example a service that only listens on localhost) and report through
 * SNMP, without exposing the service or storing credentials in the check.
 *
 * Usage: check-snmp-extend.php <device_id> <extend name>
 * Exit status and output are the extend's own (0 OK, 1 warning, 2 critical);
 * 3 (unknown) when the device or the extend can't be read.
 */

use App\Facades\DeviceCache;

$init_modules = [];
require __DIR__ . '/../includes/init.php';

$deviceId = (int) ($argv[1] ?? 0);
$name = (string) ($argv[2] ?? '');
if ($deviceId <= 0 || ! preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $name)) {
    echo "UNKNOWN - usage: check-snmp-extend.php <device_id> <extend name>\n";
    exit(3);
}

$device = DeviceCache::get($deviceId);
if (! $device->exists) {
    echo "UNKNOWN - device $deviceId not found\n";
    exit(3);
}

// NET-SNMP-EXTEND-MIB rows are indexed by the extend name as an OID string.
$index = '.' . strlen($name) . '.' . implode('.', array_map('ord', str_split($name)));
$resultOid = '.1.3.6.1.4.1.8072.1.3.2.3.1.4' . $index;  // nsExtendResult
$outputOid = '.1.3.6.1.4.1.8072.1.3.2.3.1.2' . $index;  // nsExtendOutputFull

$values = SnmpQuery::device($device)->numeric()->get([$resultOid, $outputOid])->values();
$result = $values[$resultOid] ?? $values[ltrim($resultOid, '.')] ?? null;
$output = $values[$outputOid] ?? $values[ltrim($outputOid, '.')] ?? null;

if (! is_numeric($result)) {
    echo "UNKNOWN - extend '$name' not found on {$device->displayName()} (add \"extend $name <command>\" to snmpd.conf)\n";
    exit(3);
}

echo trim((string) $output) . "\n";
$status = (int) $result;
exit($status >= 0 && $status <= 3 ? $status : 2);
