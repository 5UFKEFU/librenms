<?php

$ds_in = 'INOCTETS';
$ds_out = 'OUTOCTETS';

if ($graph_params->trafficStyle === 'cacti') {
    $rrd_list = [['filename' => $rrd_filename, 'ds_in' => $ds_in, 'ds_out' => $ds_out]];
    require 'includes/html/graphs/generic_cacti_traffic.inc.php';
} else {
    require 'includes/html/graphs/generic_data.inc.php';
}
