<?php

// Run the CLI php from PHP's own bin dir: under PHP-FPM, PHP_BINARY is
// php-fpm and PATH is cleared, so neither it nor `env php` would work.

// Asks the service's own device over SNMP whether it listens on a port.
// service_param: "tcp <port>" or "udp <port>" (protocol defaults to tcp).
$listen = preg_split('/\s+/', trim((string) $service['service_param'])) ?: [];
$listen_protocol = in_array(strtolower($listen[0] ?? ''), ['tcp', 'udp'], true) ? strtolower($listen[0]) : 'tcp';
$listen_port = (int) (end($listen) ?: 0);
$check_cmd = PHP_BINDIR . '/php ' . \App\Facades\LibrenmsConfig::get('install_dir') . '/scripts/check-port-listen.php ' . (int) $service['device_id'] . ' ' . $listen_protocol . ' ' . $listen_port;
