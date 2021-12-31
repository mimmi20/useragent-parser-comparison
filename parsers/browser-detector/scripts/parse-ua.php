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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('mimmi20/browser-detector'),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = $detector($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'client' => [
            'name'    => $r->getBrowser()->getName(),
            'version' => $r->getBrowser()->getVersion()->getVersion(),
            'isBot'   => $r->getBrowser()->getType()->isBot(),
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
            'ismobile' => $r->getDevice()->getType()->isMobile(),
            'istouch'  => $r->getDevice()->getDisplay()->hasTouch(),
        ],
        'engine' => [
            'name'    => $r->getEngine()->getName(),
            'version' => $r->getEngine()->getVersion()->getVersion(),
        ],
        'raw' => $r->toArray(),
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
