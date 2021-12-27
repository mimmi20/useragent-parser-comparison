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

$parser = new WhichBrowser\Parser();

$cache   = new \MatthiasMullie\Scrapbook\Psr6\Pool(
    new \MatthiasMullie\Scrapbook\Adapters\MemoryStore()
);

$start = microtime(true);
$parser->analyse(['User-Agent' => 'Test String'], ['cache' => $cache]);
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $parser->analyse(['User-Agent' => $agentString], ['cache' => $cache]);
    $end   = microtime(true) - $start;

    $isMobile = $parser->isMobile();

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => !empty($parser->browser->name) ? $parser->browser->name : null,
                'version' => !empty($parser->browser->version) ? $parser->browser->version->value : null,
                'isBot'   => null,
                'type'    => null,
            ],
            'platform' => [
                'name'    => !empty($parser->os->name) ? $parser->os->name : null,
                'version' => !empty($parser->os->version->value) ? $parser->os->version->value : null,
            ],
            'device' => [
                'name'     => !empty($parser->device->model) ? $parser->device->model : null,
                'brand'    => !empty($parser->device->manufacturer) ? $parser->device->manufacturer : null,
                'type'     => !empty($parser->device->type) ? $parser->device->type : null,
                'ismobile' => $isMobile ? true : null,
                'istouch'  => null,
            ],
            'engine' => [
                'name'    => null,
                'version' => null,
            ],
            'raw' => $parser->toArray(),
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$memory = memory_get_peak_usage();

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => \Composer\InstalledVersions::getPrettyVersion('whichbrowser/parser'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
