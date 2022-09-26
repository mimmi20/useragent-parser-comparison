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
$logger    = new \Psr\Log\NullLogger('null');
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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('browscap/browscap-php'),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = $bc->getBrowser($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'deviceName'     => $r->device_name ?? null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => $r->device_maker ?? null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => (isset($r->device_pointing_method) && $r->device_pointing_method === 'touchscreen'),
                'type' => null,
                'size' => null,
            ],
            'dualOrientation' => null,
            'type'     => $r->device_type ?? null,
            'simCount' => null,
            'ismobile' => $r->ismobiledevice ?? null,
        ],
        'client' => [
            'name'    => $r->browser ?? null,
            'modus' => $r->browser_modus ?? null,
            'version' => $r->version ?? null,
            'manufacturer' => $r->browser_maker ?? null,
            'bits' => $r->browser_bits ?? null,
            'isbot'   => $r->crawler ?? null,
            'type'    => $r->browser_type ?? null,
        ],
        'platform' => [
            'name'    => $r->platform ?? null,
            'marketingName' => null,
            'version' => $r->platform_version ?? null,
            'manufacturer' => $r->platform_maker ?? null,
            'bits' => $r->platform_bits ?? null,
        ],
        'engine' => [
            'name'    => $r->renderingengine_name ?? null,
            'version' => $r->renderingengine_version ?? null,
            'manufacturer' => $r->renderingengine_maker ?? null,
        ],
        'raw' => $r,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
