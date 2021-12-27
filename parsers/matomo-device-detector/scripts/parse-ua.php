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
$dd->skipBotDetection();
$dd->parse();
$initTime = microtime(true) - $start;

if ($hasUa) {
    $dd->setUserAgent($agentString);

    $start = microtime(true);
    $dd->parse();
    $end = microtime(true) - $start;

    $clientInfo = $dd->getClient();
    $osInfo     = $dd->getOs();
    $model      = $dd->getModel();
    $brand      = $dd->getBrandName();
    $device     = $dd->getDeviceName();
    $isMobile   = $dd->isMobile();

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => $clientInfo['name'] ?? null,
                'version' => $clientInfo['version'] ?? null,
                'isBot' => null,
                'type' => $clientInfo['type'] ?? null,
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
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$file = null;

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => memory_get_peak_usage(),
    'version'     => \Composer\InstalledVersions::getPrettyVersion('matomo/device-detector'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
