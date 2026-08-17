<?php
echo "padre: ", date('Y-m-d H:i:s'), " (", time(), ")\n";
$tub = [];
$p = proc_open('php -r \'echo "hijo:  " . date("Y-m-d H:i:s") . " (" . time() . ")\n";\'',
               [1 => ['pipe', 'w']], $tub, '/app');
echo stream_get_contents($tub[1]);
fclose($tub[1]);
proc_close($p);
