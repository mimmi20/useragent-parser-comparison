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
$detector('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = $detector($agentString);
    $end   = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => $r->getBrowser()->getName(),
                'version' => $r->getBrowser()->getVersion()->getVersion(),
                'isBot'   => $r->getBrowser()->getType()->isBot() ? true : null,
                'type'    => $r->getBrowser()->getType()->getType(),
            ],
            'platform' => [
                'name'    => $r->getOs()->getName(),
                'version' => $r->getOs()->getVersion()->getVersion(),
            ],
            'device' => [
                'name'     => $r->getDevice()->getDeviceName(),
                'brand'    => $r->getDevice()->getBrand()->getBrandName(),
                'type'     => $r->getDevice()->getType()->getName(),
                'ismobile' => $r->getDevice()->getType()->isMobile() ? true : null,
                'istouch'  => $r->getDevice()->getDisplay()->hasTouch() ? true : null,
            ],
            'engine' => [
                'name'    => $r->getEngine()->getName(),
                'version' => $r->getEngine()->getVersion()->getVersion(),
            ],
            'raw' => $r->toArray(),
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$file = null;

$memory = memory_get_peak_usage();

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => \Composer\InstalledVersions::getPrettyVersion('mimmi20/browser-detector'),
], JSON_UNESCAPED_SLASHES);
