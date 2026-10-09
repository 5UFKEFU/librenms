<?php

$unit_text = 'FB GB';
$unitlen = 6;
$bigdescrlen = 6;
$smalldescrlen = 6;

$rrdVar = 'fb';
// nvidia-smi reports MiB; show GiB with one decimal.
$divider = 1024;
$gprint_format = '%8.1lf';

require 'nvidia-common.inc.php';
