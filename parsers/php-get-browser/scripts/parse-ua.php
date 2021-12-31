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

$output = [
    'hasUa' => $hasUa,
    'ua' => $agentString,
    'result'      => [
        'parsed' => null,
        'err'    => null,
    ],
    'parse_time'  => 0,
    'init_time'   => $initTime,
    'memory_used' => 0,
    'version'     => PHP_VERSION . '-' . file_get_contents(__DIR__ . '/../data/version.txt'),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = get_browser($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'client' => [
            'name'    => ($r->browser && $r->browser !== 'unknown') ? $r->browser : null,
            'version' => ($r->version && $r->version !== 'unknown') ? $r->version : null,
            'isBot'   => property_exists($r, 'crawler') ? $r->crawler : null,
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
            'ismobile' => property_exists($r, 'ismobiledevice') ? $r->ismobiledevice : null,
            'istouch'  => (isset($r->device_pointing_method) && $r->device_pointing_method === 'touchscreen'),
        ],
        'engine' => [
            'name'    => ($r->renderingengine_name && $r->renderingengine_name !== 'unknown') ? $r->renderingengine_name : null,
            'version' => ($r->renderingengine_version && $r->renderingengine_version !== 'unknown') ? $r->renderingengine_version : null,
        ],
        'raw' => $r,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
