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

if ($hasUa) {
    $start = microtime(true);
    $r     = $bc->getBrowser($agentString);
    $end   = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => $r->browser,
                'version' => $r->version,
                'isBot'   => (isset($r->crawler) && $r->crawler) ? true : null,
                'type'    => $r->browser_type ?? null,
            ],
            'platform' => [
                'name'    => $r->platform,
                'version' => $r->platform_version,
            ],
            'device' => [
                'name'     => $r->device_name,
                'brand'    => $r->device_maker,
                'type'     => $r->device_type,
                'ismobile' => $r->ismobiledevice ? true : null,
                'istouch'  => (isset($r->device_pointing_method) && $r->device_pointing_method === 'touchscreen') ? true : null,
            ],
            'engine' => [
                'name'    => $r->renderingengine_name ?? null,
                'version' => $r->renderingengine_version ?? null,
            ],
            'raw' => $r,
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$memory = memory_get_peak_usage();

$bcCache = new \BrowscapPHP\Cache\BrowscapCache($cache, $logger);

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => \Composer\InstalledVersions::getPrettyVersion('browscap/browscap-php') . '-' . $bcCache->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
