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
    'ua' => $agentString,
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
        'client' => [
            'name'    => null,
            'version' => null,
            'isBot'   => $isbot,
            'type'    => null,
        ],
        'platform' => [
            'name'    => null,
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
        'raw' => null,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
