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
require __DIR__ . '/../vendor/autoload.php';
$parser = new \Woothee\Classifier();
$parser->parse('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = $parser->parse($agentString);
    $end   = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => (isset($r['name']) && $r['name'] !== 'UNKNOWN') ? $r['name'] : null,
                'version' => (isset($r['version']) && $r['version'] !== 'UNKNOWN') ? $r['version'] : null,
                'isBot'   => null,
                'type'    => null,
            ],
            'platform' => [
                'name'    => (isset($r['os']) && $r['os'] !== 'UNKNOWN') ? $r['os'] : null,
                'version' => (isset($r['os_version']) && $r['os_version'] !== 'UNKNOWN') ? $r['os_version'] : null,
            ],
            'device' => [
                'name'     => null,
                'brand'    => (isset($r['vendor']) && $r['vendor'] !== 'UNKNOWN') ? $r['vendor'] : null,
                'type'     => (isset($r['category']) && $r['category'] !== 'UNKNOWN') ? $r['category'] : null,
                'ismobile' => null,
                'istouch'  => null,
            ],
            'engine' => [
                'name'    => null,
                'version' => null,
            ],
            'raw' => $r,
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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('woothee/woothee'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
