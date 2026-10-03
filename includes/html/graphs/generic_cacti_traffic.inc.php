<?php

/*
 * Cacti-style traffic graph: the total of every listed interface, with
 * inbound as a green area and outbound as a dark blue line on one axis.
 * Legend rows keep LibreNMS's Now/Ave/Max/percentile layout.
 *
 * Expects $rrd_list entries with 'filename', 'ds_in' and 'ds_out' (octets).
 */

require 'includes/html/graphs/common.inc.php';

$percentile = \App\Facades\LibrenmsConfig::get('percentile_value');
$in_parts = [];
$out_parts = [];
foreach (array_values($rrd_list ?? []) as $i => $rrd) {
    $rrd_options[] = 'DEF:inoctets' . $i . '=' . $rrd['filename'] . ':' . $rrd['ds_in'] . ':AVERAGE';
    $rrd_options[] = 'DEF:outoctets' . $i . '=' . $rrd['filename'] . ':' . $rrd['ds_out'] . ':AVERAGE';
    $in_parts[] = 'inoctets' . $i . ',UN,0,inoctets' . $i . ',IF';
    $out_parts[] = 'outoctets' . $i . ',UN,0,outoctets' . $i . ',IF';
}

if (empty($in_parts)) {
    throw new \LibreNMS\Exceptions\RrdGraphException('No Ports');
}

$sum = fn (array $parts) => implode(',', $parts) . str_repeat(',+', count($parts) - 1);
$rrd_options[] = 'CDEF:inbits=' . $sum($in_parts) . ',8,*';
$rrd_options[] = 'CDEF:outbits=' . $sum($out_parts) . ',8,*';
$rrd_options[] = 'VDEF:percentile_in=inbits,' . $percentile . ',PERCENT';
$rrd_options[] = 'VDEF:percentile_out=outbits,' . $percentile . ',PERCENT';

$legend_rows = $graph_params->visible('legend');
$direction = $graph_params->trafficDirection;
if ($legend_rows) {
    $rrd_options[] = 'COMMENT:bps      Now       Ave      Max      ' . $percentile . 'th %\\n';
}

$series = [];
if ($direction !== 'out') {
    $series[] = ['inbits', 'percentile_in', 'AREA:inbits#00CF00', 'In\ '];
}
if ($direction !== 'in') {
    $series[] = ['outbits', 'percentile_out', 'LINE1.5:outbits#002A97', 'Out'];
}

foreach ($series as [$name, $percentile_name, $draw, $label]) {
    $rrd_options[] = $draw . ':' . ($legend_rows ? $label : '');
    if ($legend_rows) {
        $rrd_options[] = 'GPRINT:' . $name . ':LAST:%6.' . $float_precision . 'lf%s';
        $rrd_options[] = 'GPRINT:' . $name . ':AVERAGE:%6.' . $float_precision . 'lf%s';
        $rrd_options[] = 'GPRINT:' . $name . ':MAX:%6.' . $float_precision . 'lf%s';
        $rrd_options[] = 'GPRINT:' . $percentile_name . ':%6.' . $float_precision . 'lf%s\\\\n';
    }
}
