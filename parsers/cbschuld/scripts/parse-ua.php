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
require_once __DIR__ . '/../vendor/autoload.php';
$browser = new Browser();
$browser->setUserAgent('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $browser->setUserAgent($agentString);
    $end   = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => $browser->getBrowser() === 'unknown' ? null : $browser->getBrowser(),
                'version' => $browser->getVersion() === 'unknown' ? null : $browser->getVersion(),
                'isBot'   => null,
                'type'    => null,
            ],
            'platform' => [
                'name'    => $browser->getPlatform() === 'unknown' ? null : $browser->getPlatform(),
                'version' => null,
            ],
            'device' => [
                'name'     => null,
                'brand'    => null,
                'type'     => null,
                'ismobile' => null,
                'istouch'  => null,
            ],
            'engine' => [
                'name'    => null,
                'version' => null,
            ],
            'raw' => $browser,
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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('cbschuld/browser.php'),
], JSON_UNESCAPED_SLASHES);
