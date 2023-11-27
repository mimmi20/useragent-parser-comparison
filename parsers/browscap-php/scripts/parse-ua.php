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
$cacheDir  = __DIR__ . '/../data';
$browscapAdapter = new \League\Flysystem\Local\LocalFilesystemAdapter($cacheDir);
$cache   = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache(
    new \MatthiasMullie\Scrapbook\Adapters\Flysystem(
        new \League\Flysystem\Filesystem($browscapAdapter)
    )
);
$logger    = new \Psr\Log\NullLogger();
$bc        = new \BrowscapPHP\Browscap($cache, $logger);
$start = microtime(true);
$bc->getBrowser('Test String');
$initTime = microtime(true) - $start;

$bcCache = new \BrowscapPHP\Cache\BrowscapCache($cache, $logger);

$output = [
    'hasUa' => $hasUa,
    'headers' => [
        'user-agent' => $agentString,
    ],
    'result'      => [
        'parsed' => null,
        'err'    => null,
    ],
    'parse_time'  => 0,
    'init_time'   => $initTime,
    'memory_used' => 0,
    'version'     => \Composer\InstalledVersions::getPrettyVersion('browscap/browscap-php') . '-' . $bcCache->getVersion(),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = $bc->getBrowser($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'architecture' => null,
            'deviceName'     => $r->device_name,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => $r->device_maker,
            'display-width' => null,
            'display-height' => null,
            'istouch' => (property_exists($r, 'device_pointing_method') && $r->device_pointing_method === 'touchscreen'),
            'display-size' => null,
            'dualOrientation' => null,
            'type'     => $r->device_type,
            'simCount' => null,
            'ismobile' => property_exists($r, 'ismobiledevice') ? $r->ismobiledevice : null,
            'istv' => property_exists($r, 'istvdevice') ? $r->istvdevice : null,
            'bits' => null,
        ],
        'client' => [
            'name'    => $r->browser,
            'version' => (new \BrowserDetector\Version\VersionBuilder($logger))->set($r->version ?? '')->getVersion(\BrowserDetector\Version\VersionInterface::IGNORE_MICRO),
            'manufacturer' => $r->browser_maker,
            'type'    => $r->browser_type ?? null,
            'isbot'   => property_exists($r, 'crawler') ? $r->crawler : null,
        ],
        'platform' => [
            'name'    => $r->platform,
            'marketingName' => null,
            'version' => (new \BrowserDetector\Version\VersionBuilder($logger))->set($r->platform_version ?? '')->getVersion(\BrowserDetector\Version\VersionInterface::IGNORE_MICRO),
            'manufacturer' => $r->platform_maker,
        ],
        'engine' => [
            'name'    => $r->renderingengine_name,
            'version' => (new \BrowserDetector\Version\VersionBuilder($logger))->set($r->renderingengine_version ?? '')->getVersion(\BrowserDetector\Version\VersionInterface::IGNORE_MICRO),
            'manufacturer' => $r->renderingengine_maker,
        ],
        'raw' => $r,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
