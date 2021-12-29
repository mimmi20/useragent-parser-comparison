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
require __DIR__ . '/../vendor/autoload.php';
$parser   = new \Wolfcast\BrowserDetection('Test String');
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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('wolfcast/browser-detection'),
];

if ($hasUa) {
    $start  = microtime(true);
    $result = new \Wolfcast\BrowserDetection($agentString);
    $end    = microtime(true) - $start;

    $output['result']['parsed'] = [
        'client' => [
            'name'    => ($result->getName() !== 'unknown') ? $result->getName() : null,
            'version' => ($result->getVersion() !== 'unknown') ? $result->getVersion() : null,
            'isBot'   => null,
            'type'    => null,
        ],
        'platform' => [
            'name'    => ($result->getPlatform() !== 'unknown') ? $result->getPlatform() : null,
            'version' => ($result->getPlatformVersion(true) !== 'unknown') ? $result->getPlatformVersion(true) : null,
        ],
        'device' => [
            'name'     => null,
            'brand'    => null,
            'type'     => null,
            'ismobile' => $result->isMobile() ? true : null,
            'istouch'  => null,
        ],
        'engine' => [
            'name'    => null,
            'version' => null,
        ],
        'raw' => null,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
