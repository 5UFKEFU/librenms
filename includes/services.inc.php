<?php

use App\Facades\LibrenmsConfig;
use App\Models\Eventlog;
use App\Models\Service;
use LibreNMS\Alert\AlertRules;
use LibreNMS\Enum\Severity;
use LibreNMS\RRD\RrdDefinition;
use LibreNMS\Util\Clean;
use LibreNMS\Util\IP;

function add_service($device, $type, $desc, $ip = '', $param = '', $ignore = 0, $disabled = 0, $template_id = '', $name = '')
{
    // keep legacy signature, delegate to modern implementation
    return \LibreNMS\Services::addService($device, $type, $desc, $ip, $param, $ignore, $disabled, $template_id, $name);
}

function service_get($device = null, $service = null)
{
    if (! is_null($service)) {
        // Add a service filter to the SQL query.
        $services = Service::query()->where('service_id', $service)->get();
    } elseif (! is_null($device)) {
        $services = Service::query()->where('device_id', $device)->get();
    } else {
        $services = Service::query()->get();
    }

    d_echo('Service Array: ' . print_r($services, true) . "\n");

    return $services->toArray();
}

function edit_service($update = [], $service = null)
{
    if (! is_numeric($service)) {
        return false;
    }

    return Service::query()->where('service_id', $service)->update($update);
}

function delete_service($service = null)
{
    if (! is_numeric($service)) {
        return false;
    }

    return Service::query()->where('service_id', $service)->delete();
}

/**
 * Build the plugin command for a service row (service_type, service_ip,
 * service_param, hostname, overwrite_ip), the same way the poller does.
 *
 * @return array{0: string, 1: callable|null} the command and an optional output parser
 */
function service_check_command($service): array
{
    $service['service_type'] = Clean::fileName($service['service_type']);
    $service['service_ip'] = IP::isValid($service['service_ip']) ? $service['service_ip'] : Clean::fileName($service['service_ip']);
    $service['hostname'] = IP::isValid($service['hostname']) ? $service['hostname'] : Clean::fileName($service['hostname']);
    $service['overwrite_ip'] = IP::isValid($service['overwrite_ip']) ? $service['overwrite_ip'] : Clean::fileName($service['overwrite_ip']);
    $check_cmd = '';
    $check_parser = null;

    // if we have a script for this check, use it.
    $check_script = \LibreNMS\Services::customCheckPath($service['service_type']);
    if (is_file($check_script)) {
        include $check_script;
    }

    // If we do not have a cmd from the check script, build one.
    if ($check_cmd == '') {
        $check_cmd = LibrenmsConfig::get('nagios_plugins') . '/check_' . $service['service_type'] . ' -H ' . ($service['service_ip'] ?: $service['hostname']);
        $check_cmd .= ' ' . $service['service_param'];
    }

    return [$check_cmd, $check_parser];
}

function poll_service($service)
{
    $update = [];
    $old_status = $service['service_status'];
    [$check_cmd, $check_parser] = service_check_command($service);

    $service_id = $service['service_id'];
    // Some debugging
    d_echo("\nNagios Service - $service_id\n");
    // the check_service function runs $check_cmd through escapeshellcmd, so
    [$new_status, $msg, $perf] = check_service($check_cmd, $check_parser ?? null);
    $update['service_checked'] = time();

    // A service can ask for several failed checks in a row before it is
    // marked failed (and alerts fire). Until then it stays OK, and the
    // message carries a "[soft n/N]" marker so the failure is still visible.
    $retries = max(1, (int) ($service['service_retries'] ?? 1));
    $fail_count = (int) ($service['service_fail_count'] ?? 0);
    if ($new_status == 0) {
        if ($fail_count > 0) {
            $update['service_fail_count'] = 0;
        }
    } else {
        $fail_count = min($fail_count + 1, 255);
        $update['service_fail_count'] = $fail_count;
        if ($old_status == 0 && $fail_count < $retries) {
            $msg = "[soft $fail_count/$retries] " . $msg;
            $new_status = 0;
        }
    }
    d_echo("Response: $msg\n");

    // If we have performance data we will store it.
    if (count($perf) > 0) {
        // Yes, We have perf data.
        $rrd_name = ['services', $service_id];

        // Set the DS in the DB if it is blank.
        $DS = [];
        foreach ($perf as $k => $v) {
            $DS[$k] = ['uom' => $v['uom'], 'full_name' => $v['full_name']];
        }
        d_echo('Service DS: ' . json_encode($DS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        if (($service['service_ds'] == '{}') || ($service['service_ds'] == '')) {
            $update['service_ds'] = json_encode($DS);
        }

        // rrd definition
        $rrd_def = new RrdDefinition();
        foreach ($perf as $k => $v) {
            if (($v['uom'] == 'c') && ! preg_match('/[Uu]ptime/', (string) $k)) {
                // This is a counter, create the DS as such
                $rrd_def->addDataset($k, 'COUNTER', 0);
            } else {
                // Not a counter, must be a gauge
                $rrd_def->addDataset($k, 'GAUGE', 0);
            }
        }

        // Update data
        $fields = [];
        foreach ($perf as $k => $v) {
            $fields[$k] = $v['value'];
        }

        $tags = ['service_id' => $service_id, 'rrd_name' => $rrd_name, 'rrd_def' => $rrd_def];
        //TODO not sure if we have $device at this point, if we do replace faked $device
        app('Datastore')->put($service, 'services', $tags, $fields);
    }

    // Response time, tracked apart from up/down: a check slower than
    // service_slow_after (seconds) counts as slow; after service_retries slow
    // checks in a row service_slow is set, which alert rules can match on
    // without the service being reported as failed.
    $response_time = service_response_seconds($perf);
    if ($response_time !== null) {
        $update['service_response_time'] = $response_time;
    }
    $slow_after = (float) ($service['service_slow_after'] ?? 0);
    $was_slow = (bool) ($service['service_slow'] ?? false);
    $is_slow = false;
    if ($slow_after > 0 && $response_time !== null && $new_status == 0) {
        $slow_count = $response_time > $slow_after ? min((int) ($service['service_slow_count'] ?? 0) + 1, 255) : 0;
        $update['service_slow_count'] = $slow_count;
        $is_slow = $slow_count >= $retries;
    } elseif ((int) ($service['service_slow_count'] ?? 0) > 0) {
        $update['service_slow_count'] = 0;
    }
    if ($is_slow !== $was_slow) {
        $update['service_slow'] = $is_slow;
        Eventlog::log(
            "Service {$service['service_name']} ({$service['service_type']}) " . ($is_slow ? "is slow: {$response_time}s > {$slow_after}s" : 'is responding normally again'),
            $service['device_id'],
            'service',
            $is_slow ? Severity::Warning : Severity::Ok,
            $service['service_id']
        );
    }

    if ($old_status != $new_status) {
        // Status has changed, update.
        $update['service_changed'] = time();
        $update['service_status'] = $new_status;
        $update['service_message'] = $msg;

        // TODO: Put the 3 lines below in a function getStatus(int) ?
        $status_text = [0 => 'OK', 1 => 'Warning', 3 => 'Unknown'];
        $old_status_text = $status_text[$old_status] ?? 'Critical';
        $new_status_text = $status_text[$new_status] ?? 'Critical';

        Eventlog::log(
            "Service {$service['service_name']} ({$service['service_type']})' changed status from $old_status_text to $new_status_text - {$service['service_desc']} - $msg",
            $service['device_id'],
            'service',
            Severity::Warning,
            $service['service_id']
        );

        // Run alert rules due to status changed
        $rules = new AlertRules($service['device_id']);
        $rules->run();
    }

    if ($service['service_message'] != $msg) {
        // Message has changed, update.
        $update['service_message'] = $msg;
    }

    if (count($update) > 0) {
        edit_service($update, $service['service_id']);
    }

    // A slow/normal change only (the status change above already ran them).
    if ($is_slow !== $was_slow && $old_status == $new_status) {
        $rules = new AlertRules($service['device_id']);
        $rules->run();
    }

    return true;
}

/**
 * The check's response time in seconds from its perfdata ("time", or a
 * ping's "rta"), or null when the plugin reported none.
 */
function service_response_seconds(array $perf): ?float
{
    $metric = $perf['time'] ?? $perf['rta'] ?? null;
    if (! is_array($metric) || ! is_numeric($metric['value'] ?? null)) {
        return null;
    }
    $value = (float) $metric['value'];

    return match (strtolower((string) ($metric['uom'] ?? 's'))) {
        'ms' => $value / 1000,
        'us' => $value / 1000000,
        default => $value,
    };
}

function check_service($command, ?callable $parser = null)
{
    // Make our command safe.
    $parts = preg_split('~(?:\'[^\']*\'|"[^"]*")(*SKIP)(*F)|\h+~', trim((string) $command));
    $safe_command = implode(' ', array_map(function ($part) {
        $trimmed = preg_replace('/^(\'(.*)\'|"(.*)")$/', '$2$3', $part);

        return escapeshellarg($trimmed);
    }, $parts));

    d_echo("Request:  $safe_command\n");

    // Run the command and return its response.
    exec('LC_NUMERIC="C" ' . $safe_command, $response_array, $status);

    // exec returns an array, lets implode it back to a string.
    $response_string = implode("\n", $response_array);

    // Split out the response and the performance data.
    [$response, $perf] = explode('|', $response_string, 2) + ['', ''];

    $metrics = \LibreNMS\Services::parsePerfdata($perf);

    if ($parser) {
        $metrics = $parser($response_string, $metrics);
    } elseif (empty($metrics)) {
        $metrics = \LibreNMS\Services::parseStats($response_string);
    }

    return [$status, $response, $metrics];
}

/**
 * List all available services from nagios plugins directory
 *
 * @return array
 */
function list_available_services()
{
    return \LibreNMS\Services::list();
}

/**
 * What a check actually reaches, for a client to confirm it tested the right
 * machine: the command (secrets masked), DNS answers, and for HTTP the
 * connection and response headers as `curl -v` shows them; for TCP services
 * the address that answered.
 *
 * @return string[] lines of text
 */
function service_diagnostics(string $type, string $command): array
{
    $parts = preg_split('~(?:\'[^\']*\'|"[^"]*")(*SKIP)(*F)|\h+~', trim($command));
    $parts = array_map(fn ($part) => preg_replace('/^(\'(.*)\'|"(.*)")$/', '$2$3', $part), $parts);
    $plugin = basename((string) array_shift($parts));

    // Options and their values (a following word that is not itself an option).
    $options = [];
    $secret = ['-a', '--authorization', '--password', '-p' => ['mysql', 'pgsql', 'mysql_query']];
    $shown = [$plugin];
    for ($i = 0; $i < count($parts); $i++) {
        $part = $parts[$i];
        if (! preg_match('/^(--?[A-Za-z][\w-]*)(?:=(.*))?$/', $part, $m)) {
            $shown[] = $part;
            continue;
        }
        $value = $m[2] ?? null;
        if ($value === null && isset($parts[$i + 1]) && ! str_starts_with($parts[$i + 1], '-')) {
            $value = $parts[++$i];
        }
        $options[$m[1]][] = $value ?? true;
        $masked = in_array($m[1], ['-a', '--authorization', '--password'], true)
            || ($m[1] === '-p' && in_array($type, $secret['-p'], true));
        $shown[] = $value === null ? $m[1] : $m[1] . ' ' . ($masked ? '******' : escapeshellarg((string) $value));
    }

    $lines = ['Command: ' . implode(' ', $shown)];
    if (in_array($type, ['snmp_extend', 'port_listen'], true)) {
        $lines[] = 'Runs on the server itself, read over SNMP.';
    }

    $first = fn (string $key) => isset($options[$key]) && is_string($options[$key][0]) ? $options[$key][0] : null;
    $last = fn (string $key) => isset($options[$key]) && is_string(end($options[$key])) ? end($options[$key]) : null;
    $host = $first('-I') ?? $first('-H') ?? $first('--hostname');
    $vhost = $last('-H') ?? $host;
    if ($host === null) {
        return $lines;
    }

    $address = $host;
    if (! filter_var($host, FILTER_VALIDATE_IP)) {
        $records = @gethostbynamel($host) ?: [];
        $lines[] = "DNS: $host → " . ($records ? implode(', ', $records) : 'no address');
        $address = $records[0] ?? null;
    }
    if ($vhost !== $host && ! filter_var($vhost, FILTER_VALIDATE_IP)) {
        $records = @gethostbynamel($vhost) ?: [];
        $lines[] = "DNS: $vhost → " . ($records ? implode(', ', $records) : 'no address');
    }

    if ($plugin === 'check_http' || $plugin === 'check_curl') {
        $ssl = isset($options['-S']) || isset($options['--ssl']) || isset($options['--sni']);
        $port = (int) ($first('-p') ?? $first('--port') ?? ($ssl ? 443 : 80));
        $uri = $first('-u') ?? $first('--url') ?? '/';
        $scheme = $ssl ? 'https' : 'http';
        $url = "$scheme://$vhost:$port" . (str_starts_with($uri, '/') ? $uri : "/$uri");
        $curl = ['curl', '-sv', '-o', '/dev/null', '-k', '--max-time', '10', '--max-redirs', '0'];
        if ($first('-I') !== null && $vhost !== $first('-I')) {
            // Like check_http -I: connect to that address, ask for the name.
            $curl[] = '--connect-to';
            $curl[] = "$vhost:$port:" . $first('-I') . ":$port";
        }
        $curl[] = $url;
        $lines[] = '$ ' . implode(' ', array_map('escapeshellarg', $curl));
        $process = proc_open($curl, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($process)) {
            stream_get_contents($pipes[1]);
            $output = stream_get_contents($pipes[2]);
            proc_close($process);
            $keep = '/^\*\s+(IPv4|IPv6|Trying|Connected to|Established connection|SSL connection|ALPN: server|Server certificate|subject|start date|expire date|issuer|SSL certificate verif)|^> (GET|HEAD|Host:)|^< /i';
            $count = 0;
            foreach (preg_split('/\r?\n/', (string) $output) as $line) {
                $line = rtrim($line);
                if ($line !== '' && $line !== '<' && preg_match($keep, $line) && $count++ < 40) {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    $defaults = ['ssh' => 22, 'ftp' => 21, 'smtp' => 25, 'pop' => 110, 'imap' => 143, 'mysql' => 3306, 'pgsql' => 5432, 'tcp' => null];
    // check_mysql and check_pgsql take the port as -P (-p is the password).
    $port = in_array($type, ['mysql', 'pgsql'], true)
        ? ($first('-P') ?? $defaults[$type])
        : ($first('-p') ?? $first('--port') ?? ($defaults[$type] ?? null));
    if ($address !== null && is_numeric($port) && $type !== 'udp') {
        $started = microtime(true);
        $target = (str_contains($address, ':') ? "[$address]" : $address) . ':' . (int) $port;
        $socket = @stream_socket_client("tcp://$target", $errno, $error, 5);
        if ($socket) {
            $lines[] = sprintf('Connected to %s from %s in %.0f ms', stream_socket_get_name($socket, true),
                stream_socket_get_name($socket, false), (microtime(true) - $started) * 1000);
            fclose($socket);
        } else {
            $lines[] = "Could not connect to $target: $error";
        }
    }

    return $lines;
}
