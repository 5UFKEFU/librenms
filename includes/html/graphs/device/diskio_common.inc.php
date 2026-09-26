<?php

$i = 1;

foreach (dbFetchRows('SELECT * FROM `ucd_diskio` AS U, `devices` AS D WHERE D.device_id = ? AND U.device_id = D.device_id', [$device['device_id']]) as $disk) {
    if ($graph_params->physicalDisksOnly && ! \LibreNMS\Data\Graphing\DiskGraphScope::isWholeDisk($disk['diskio_descr'])) {
        continue;
    }
    $rrd_filename = Rrd::name($disk['hostname'], ['ucd_diskio', $disk['diskio_descr']]);
    if (Rrd::checkRrdExists($rrd_filename)) {
        $rrd_list[$i]['filename'] = $rrd_filename;
        $rrd_list[$i]['descr'] = $disk['diskio_descr'];
        if ($graph_params->physicalDisksOnly) {
            $rrd_list[$i]['descr_out'] = $disk['diskio_descr'];
            $rrd_list[$i]['in_label'] = ' Read';
            $rrd_list[$i]['out_label'] = ' Write';
        }
        $rrd_list[$i]['ds_in'] = $ds_in;
        $rrd_list[$i]['ds_out'] = $ds_out;
        $i++;
    }
}
