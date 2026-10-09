<?php

$name = 'nvidia';
$colours = 'greens';
$dostack = 0;
$printtotal = 0;
$addarea = 1;
$transparency = 15;

$int = 0;
$rrd_list = [];
$rrd_filename = Rrd::name($device['hostname'], ['app', $app->app_type, $app->app_id, $int]);

// One colour per metric (filled under the line) so the GPU graphs are easy
// to tell apart; a second GPU gets a darker shade of the same colour.
$nvidia_colours = [
    'sm' => ['2563EB', '1E3A8A'],     // GPU utilisation: blue
    'fb' => ['7C3AED', '4C1D95'],     // video memory used: violet
    'mem' => ['0891B2', '155E75'],    // memory bandwidth: cyan
    'temp' => ['EA580C', '9A3412'],   // temperature: orange
    'pwr' => ['DC2626', '7F1D1D'],    // power: red
    'pclk' => ['CA8A04', '854D0E'],   // GPU clock: amber
    'mclk' => ['4F46E5', '312E81'],   // memory clock: indigo
    'enc' => ['DB2777', '831843'],    // encoder: pink
    'dec' => ['059669', '064E3B'],    // decoder: emerald
];

// A graph may combine metrics that share a unit: $rrdVars maps each
// rrd variable to its legend label. Otherwise $rrdVar is drawn per GPU.
$nvidia_vars = $rrdVars ?? [$rrdVar => null];

while (Rrd::checkRrdExists($rrd_filename)) {
    foreach ($nvidia_vars as $nvidia_var => $nvidia_label) {
        $shades = $nvidia_colours[$nvidia_var] ?? null;
        $rrd_list[] = [
            'filename' => $rrd_filename,
            'descr' => $nvidia_label === null ? 'GPU ' . $int : ($int ? "GPU $int " : '') . $nvidia_label,
            'ds' => $nvidia_var,
            'colour' => $shades[$int % 2] ?? null,
            'area' => $shades !== null,
        ];
    }

    $int++;
    $rrd_filename = Rrd::name($device['hostname'], ['app', $app->app_type, $app->app_id, $int]);
}

require 'includes/html/graphs/generic_multi_line_exact_numbers.inc.php';
