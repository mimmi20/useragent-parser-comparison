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
$dd = new DeviceDetector();
$dd->setUserAgent('Test String');
$dd->parse();
$initTime = microtime(true) - $start;

$output = [
    'hasUa' => $hasUa,
    'headers' => [
        'user-agent' => $agentString,
    ],
    'result' => [
        'parsed' => null,
        'err' => null,
    ],
    'parse_time' => 0,
    'init_time' => $initTime,
    'memory_used' => 0,
    'version' => \Composer\InstalledVersions::getPrettyVersion('matomo/device-detector'),
];

if ($hasUa) {
    $dd->skipBotDetection();
    $dd->setUserAgent($agentString);

    $start1 = microtime(true);
    $dd->parse();

    $clientInfo = $dd->getClient();
    $osInfo     = $dd->getOs();
    $model      = $dd->getModel();
    $brand      = $dd->getBrandName();
    $device     = $dd->getDeviceName();
    $isMobile   = $dd->isMobile();

    $end1 = microtime(true) - $start1;

    $dd->skipBotDetection(false);
    $dd->setUserAgent($agentString . ' - ');

    $start2 = microtime(true);

    $dd->parse();

    $isBot   = $dd->isBot();
    $botInfo = $dd->getBot();

    $end2 = microtime(true) - $start2;

    $output['result']['parsed'] = [
        'device' => [
            //'architecture' => null,
            //'deviceName' => null,
            'marketingName' => $model ?? null,
            'manufacturer' => null,
            'brand' => $brand ?? null,
            //'display' => [
            //    'width' => null,
            //    'height' => null,
            //    'touch' => null,
            //    'type' => null,
            //    'size' => null,
            //],
            //'dualOrientation' => null,
            'type' => $device ?? null,
            'simCount' => null,
            'ismobile' => $isMobile,
            'istv' => false,
            'bits' => null,
        ],
        'client' => [
            'name' => $isBot ? ($botInfo['name'] ?? null) : ($clientInfo['name'] ?? null),
            //'modus' => null,
            'version' => $isBot ? null : ($clientInfo['version'] ?? null),
            //'manufacturer' => null,
            //'bits' => null,
            'type' => $isBot ? ($botInfo['category'] ?? null) : ($clientInfo['type'] ?? null),
            'isbot' => $isBot,
        ],
        'platform' => [
            'name' => $osInfo['name'] ?? null,
            'marketingName' => $osInfo['name'] ?? null,
            'version' => $osInfo['version'] ?? null,
            //'manufacturer' => null,
            //'bits' => null,
        ],
        'engine' => [
            'name' => $isBot ? null : ($clientInfo['engine'] ?? null),
            'version' => $isBot ? null : ($clientInfo['engine_version'] ?? null),
            //'manufacturer' => null,
        ],
        'raw' => null,
    ];

    $output['parse_time'] = $isBot ? $end2 : $end1;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
