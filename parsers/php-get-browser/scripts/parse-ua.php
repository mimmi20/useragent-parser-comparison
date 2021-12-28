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
get_browser('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = get_browser($agentString);
    $end   = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => ($r->browser && $r->browser !== 'unknown') ? $r->browser : null,
                'version' => ($r->version && $r->version !== 'unknown') ? $r->version : null,
                'isBot'   => (isset($r->crawler) && $r->crawler) ? true : null,
                'type'    => $r->browser_type ?? null,
            ],
            'platform' => [
                'name'    => ($r->platform && $r->platform !== 'unknown') ? $r->platform : null,
                'version' => ($r->platform_version && $r->platform_version !== 'unknown') ? $r->platform_version : null,
            ],
            'device' => [
                'name'     => ($r->device_name && $r->device_name !== 'unknown') ? $r->device_name : null,
                'brand'    => ($r->device_maker && $r->device_maker !== 'unknown') ? $r->device_maker : null,
                'type'     => ($r->device_type && $r->device_type !== 'unknown') ? $r->device_type : null,
                'ismobile' => $r->ismobiledevice ? true : null,
                'istouch'  => (isset($r->device_pointing_method) && $r->device_pointing_method === 'touchscreen') ? true : null,
            ],
            'engine' => [
                'name'    => ($r->renderingengine_name && $r->renderingengine_name !== 'unknown') ? $r->renderingengine_name : null,
                'version' => ($r->renderingengine_version && $r->renderingengine_version !== 'unknown') ? $r->renderingengine_version : null,
            ],
            'raw' => $r,
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$memory = memory_get_peak_usage();

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => PHP_VERSION . '-' . file_get_contents(__DIR__ . '/../data/version.txt'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
