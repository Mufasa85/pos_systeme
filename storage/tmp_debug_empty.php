<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/config.php';

$path = tempnam(sys_get_temp_dir(), 'empty_') . '.csv';
file_put_contents($path, '');

$service = new App\Services\ProductImportService();
$result = $service->importFile(null, $path);

var_dump($result['success']);
var_dump($result['error'] ?? null);
var_dump($result['summary']);
var_dump($result['processed'] ?? null);
var_dump($result['rows'] ?? null);

@unlink($path);
