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

if ($hasUa) {
    $start = microtime(true);
    $parser->setUserAgent($agentString);
    $isbot = $parser->isCrawler();
    $end = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => null,
                'version' => null,
                'isBot'   => $isbot ? true : null,
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
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => memory_get_peak_usage(),
    'version'     => \Composer\InstalledVersions::getPrettyVersion('jaybizzle/crawler-detect'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
