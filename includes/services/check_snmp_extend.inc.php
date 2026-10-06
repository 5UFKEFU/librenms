<?php

// Reads an SNMP extend on the service's own device (service_param is the
// extend name), so the device checks itself and LibreNMS needs no extra access.
$check_cmd = PHP_BINARY . ' ' . \App\Facades\LibrenmsConfig::get('install_dir') . '/scripts/check-snmp-extend.php ' . (int) $service['device_id'] . ' ' . trim((string) $service['service_param']);
