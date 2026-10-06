<?php

// Run the CLI php from PHP's own bin dir: under PHP-FPM, PHP_BINARY is
// php-fpm and PATH is cleared, so neither it nor `env php` would work.

// Reads an SNMP extend on the service's own device (service_param is the
// extend name), so the device checks itself and LibreNMS needs no extra access.
$check_cmd = PHP_BINDIR . '/php ' . \App\Facades\LibrenmsConfig::get('install_dir') . '/scripts/check-snmp-extend.php ' . (int) $service['device_id'] . ' ' . trim((string) $service['service_param']);
