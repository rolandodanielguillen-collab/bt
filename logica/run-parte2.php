<?php
// ponytail: CLI wrapper for cargar.auxiliar.v2-parte2.php
if (!isset($argv[1]) || !is_numeric($argv[1])) { echo 'Usage: php run-parte2.php <evento_id>'; exit(1); }
$_GET['evento'] = abs((int)$argv[1]);
chdir(__DIR__);
include __DIR__ . '/cargar.auxiliar.v2-parte2.php';
