#!/usr/bin/env php
<?php

/*
 * Nagios-style check that asks a device over SNMP whether it is listening on
 * a TCP or UDP port (TCP-MIB tcpListenerTable, UDP-MIB udpEndpointTable, with
 * the older udpTable as a fallback). The device checks itself: the port does
 * not need to be reachable from the poller, and UDP works without a reply.
 *
 * Usage: check-port-listen.php <device_id> <tcp|udp> <port>
 */

use App\Facades\DeviceCache;

$init_modules = [];
require __DIR__ . '/../includes/init.php';

$deviceId = (int) ($argv[1] ?? 0);
$protocol = strtolower((string) ($argv[2] ?? ''));
$port = (int) ($argv[3] ?? 0);
if ($deviceId <= 0 || ! in_array($protocol, ['tcp', 'udp'], true) || $port < 1 || $port > 65535) {
    echo "UNKNOWN - usage: check-port-listen.php <device_id> <tcp|udp> <port>\n";
    exit(3);
}

$device = DeviceCache::get($deviceId);
if (! $device->exists) {
    echo "UNKNOWN - device $deviceId not found\n";
    exit(3);
}

/** Split "type.len.addr….rest" off the front of an index; returns [address, rest]. */
$takeAddress = function (array $parts): array {
    $length = (int) ($parts[1] ?? 0);
    $bytes = array_slice($parts, 2, $length);
    $address = $length === 4 ? implode('.', $bytes) : ($length === 0 ? '*' : 'ipv6');

    return [$address, array_slice($parts, 2 + $length)];
};

$listening = [];
$queried = false;
if ($protocol === 'tcp') {
    // tcpListenerProcess: index = addressType.length.address….port
    $base = '.1.3.6.1.2.1.6.20.1.4';
    $rows = SnmpQuery::device($device)->numeric()->walk($base)->values();
    $queried = ! empty($rows);
    foreach (array_keys($rows) as $oid) {
        $parts = explode('.', substr('.' . ltrim($oid, '.'), strlen($base) + 1));
        [$address, $rest] = $takeAddress($parts);
        if ((int) ($rest[0] ?? -1) === $port) {
            $listening[] = $address;
        }
    }
} else {
    // udpEndpointProcess: index = local type.len.addr….port.remote type.len.addr….port.instance
    $base = '.1.3.6.1.2.1.7.7.1.8';
    $rows = SnmpQuery::device($device)->numeric()->walk($base)->values();
    $queried = ! empty($rows);
    foreach (array_keys($rows) as $oid) {
        $parts = explode('.', substr('.' . ltrim($oid, '.'), strlen($base) + 1));
        [$address, $rest] = $takeAddress($parts);
        if ((int) ($rest[0] ?? -1) === $port) {
            $listening[] = $address;
        }
    }
    if (! $queried) {
        // Older agents: udpLocalPort, index = a.b.c.d.port
        $base = '.1.3.6.1.2.1.7.5.1.2';
        $rows = SnmpQuery::device($device)->numeric()->walk($base)->values();
        $queried = ! empty($rows);
        foreach ($rows as $oid => $value) {
            if ((int) $value === $port) {
                $parts = explode('.', substr('.' . ltrim($oid, '.'), strlen($base) + 1));
                $listening[] = implode('.', array_slice($parts, 0, 4));
            }
        }
    }
}

$label = strtoupper($protocol) . " $port";
if (! $queried) {
    echo "UNKNOWN - {$device->displayName()} did not return its $protocol listener table over SNMP\n";
    exit(3);
}
if (empty($listening)) {
    echo "CRITICAL - nothing is listening on $label\n";
    exit(2);
}
$addresses = implode(', ', array_unique(array_map(fn ($a) => $a === '0.0.0.0' ? 'all addresses' : $a, $listening)));
echo "OK - listening on $label ($addresses)\n";
exit(0);
