<?php

declare(strict_types = 1);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

$uaPos       = array_search('--ua', $argv);
$hasUa       = false;
$agentString = '';

if ($uaPos !== false) {
    $hasUa = true;

    $agentString = $argv[2];
}

$result    = null;
$parseTime = 0;

$start = microtime(true);
require_once __DIR__ . '/../vendor/autoload.php';

use EndorphinStudio\Detector\Detector;

$detector = new Detector();

$detector->analyse('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = $detector->analyse($agentString);
    $end   = microtime(true) - $start;

    $r = json_decode(json_encode($r));

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => $r->isRobot ? (isset($r->robot) ? $r->robot->name : null) : ((isset($r->browser->name) && $r->browser->name !== 'not available') ? $r->browser->name : null),
                'version' => (isset($r->browser->version) && $r->browser->version !== 'not available') ? $r->browser->version : null,
                'isBot'   => $r->isRobot ? true : null,
                'type'    => null,
            ],
            'platform' => [
                'name'    => (isset($r->os->name) && $r->os->name !== 'not available') ? $r->os->name : null,
                'version' => (isset($r->os->version) && $r->os->version !== 'not available') ? $r->os->version : null,
            ],
            'device' => [
                'name'     => isset($r->device->model) ? $r->device->model : null,
                'brand'    => isset($r->device->name) ? $r->device->name : null,
                'type'     => isset($r->device->type) ? $r->device->type : null,
                'ismobile' => $r->isMobile ? true : null,
                'istouch'  => $r->isTouch ? true : null,
            ],
            'engine' => [
                'name'    => null,
                'version' => null,
            ],
            'raw' => $r,
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$file   = null;
$memory = memory_get_peak_usage();

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => \Composer\InstalledVersions::getPrettyVersion('endorphin-studio/browser-detector'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
