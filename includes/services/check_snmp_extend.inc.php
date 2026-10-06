<?php

// The script runs through its own #!/usr/bin/env php line; under PHP-FPM,
// PHP_BINARY would be php-fpm rather than the CLI.

// Reads an SNMP extend on the service's own device (service_param is the
// extend name), so the device checks itself and LibreNMS needs no extra access.
$check_cmd = \App\Facades\LibrenmsConfig::get('install_dir') . '/scripts/check-snmp-extend.php ' . (int) $service['device_id'] . ' ' . trim((string) $service['service_param']);
