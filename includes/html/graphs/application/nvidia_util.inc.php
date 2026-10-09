<?php

// GPU and memory bandwidth utilisation together; both are percentages.
$unit_text = 'Util %';
$unitlen = 10;
$bigdescrlen = 13;
$smalldescrlen = 13;

$rrdVars = ['sm' => 'GPU', 'mem' => 'Mem bandwidth'];

require 'nvidia-common.inc.php';
