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

require_once __DIR__ . '/../vendor/autoload.php';
use DeviceDetector\DeviceDetector;

$cache   = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache(
    new \MatthiasMullie\Scrapbook\Adapters\MemoryStore()
);

$start = microtime(true);
$dd = new DeviceDetector('Test String');
$dd->parse();
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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('matomo/device-detector'),
];

if ($hasUa) {
    $dd->setUserAgent($agentString);

    $start = microtime(true);
    $dd->parse();

    $clientInfo = $dd->getClient();
    $osInfo     = $dd->getOs();
    $model      = $dd->getModel();
    $brand      = $dd->getBrandName();
    $device     = $dd->getDeviceName();
    $isMobile   = $dd->isMobile();
    $isBot      = $dd->isBot();
    $botInfo    = $dd->getBot();

    $end = microtime(true) - $start;

    $output['result']['parsed'] = [
        'client' => [
            'name'    => $isBot ? ($botInfo['name'] ?? null) : ($clientInfo['name'] ?? null),
            'version' => $isBot ? null : ($clientInfo['version'] ?? null),
            'isBot' => $isBot ? true : null,
            'type' => $isBot ? ($botInfo['category'] ?? null) : ($clientInfo['type'] ?? null),
        ],
        'platform' => [
            'name'    => $osInfo['name'] ?? null,
            'version' => $osInfo['version'] ?? null,
        ],
        'device' => [
            'name'     => $model ?? null,
            'brand'    => $brand ?? null,
            'type'     => $device ?? null,
            'ismobile' => $isMobile ? true : null,
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
