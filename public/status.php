<?php
declare(strict_types=1);

use BuildStatus\App;
use BuildStatus\ConfigLoader;

require dirname(__DIR__) . '/src/bootstrap.php';

$config = ConfigLoader::load(dirname(__DIR__) . '/config/builds.json');
$snapshot = (new App($config))->snapshot();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
