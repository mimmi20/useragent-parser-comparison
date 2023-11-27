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

require __DIR__ . '/../vendor/autoload.php';

$cache   = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache(
    new \MatthiasMullie\Scrapbook\Adapters\MemoryStore()
);

$start = microtime(true);
$logger    = new \Psr\Log\NullLogger();
$factory   = new \BrowserDetector\DetectorFactory($cache, $logger);
$detector  = $factory();
$detector->getBrowser('Test String');
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
    'version' => \Composer\InstalledVersions::getPrettyVersion('mimmi20/browser-detector'),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = $detector->getBrowser($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'architecture' => $r['device']['architecture'],
            'deviceName' => $r['device']['deviceName'],
            'marketingName' => $r['device']['marketingName'],
            'manufacturer' => $r['device']['manufacturer'],
            'brand' => $r['device']['brand'],
            'display-width' => $r['device']['display']['width'],
            'display-height' => $r['device']['display']['height'],
            'istouch' => $r['device']['display']['touch'],
            'display-size' => $r['device']['display']['size'],
            'dualOrientation' => $r['device']['dualOrientation'],
            'type' => $r['device']['type'],
            'simCount' => $r['device']['simCount'],
            'ismobile' => $r['device']['ismobile'],
            'istv' => $r['device']['istv'],
            'bits' => $r['device']['bits'],
        ],
        'client' => [
            'name' => $r['client']['name'],
            'version' => (new \BrowserDetector\Version\VersionBuilder($logger))->set($r['client']['version'] ?? '')->getVersion(\BrowserDetector\Version\VersionInterface::IGNORE_MICRO),
            'manufacturer' => $r['client']['manufacturer'],
            'type' => $r['client']['type'],
            'isbot' => $r['client']['isbot'],
        ],
        'platform' => [
            'name' => $r['os']['name'],
            'marketingName' => $r['os']['marketingName'],
            'version' => (new \BrowserDetector\Version\VersionBuilder($logger))->set($r['os']['version'] ?? '')->getVersion(\BrowserDetector\Version\VersionInterface::IGNORE_MICRO),
            'manufacturer' => $r['os']['manufacturer'],
        ],
        'engine' => [
            'name' => $r['engine']['name'],
            'version' => (new \BrowserDetector\Version\VersionBuilder($logger))->set($r['engine']['version'] ?? '')->getVersion(\BrowserDetector\Version\VersionInterface::IGNORE_MICRO),
            'manufacturer' => $r['engine']['manufacturer'],
        ],
        'raw' => $r,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
