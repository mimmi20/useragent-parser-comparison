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
$parser = new \Jaybizzle\CrawlerDetect\CrawlerDetect();
$parser->setUserAgent('Test String');
$parser->isCrawler();

$initTime = microtime(true) - $start;

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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('jaybizzle/crawler-detect'),
];

if ($hasUa) {
    $start = microtime(true);
    $parser->setUserAgent($agentString);
    $isbot = $parser->isCrawler();
    $end = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'deviceName'     => null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => null,
                'type' => null,
                'size' => null,
            ],
            'dualOrientation' => null,
            'type'     => null,
            'simCount' => null,
            'ismobile' => null,
        ],
        'client' => [
            'name'    => null,
            'modus' => null,
            'version' => null,
            'manufacturer' => null,
            'bits' => null,
            'type' => null,
            'isbot'   => $isbot,
        ],
        'platform' => [
            'name'    => null,
            'marketingName' => null,
            'version' => null,
            'manufacturer' => null,
            'bits' => null,
        ],
        'engine' => [
            'name'    => null,
            'version' => null,
            'manufacturer' => null,
        ],
        'raw' => null,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
