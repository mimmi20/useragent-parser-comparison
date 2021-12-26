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
UserAgentFactory::analyze('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = UserAgentFactory::analyze($agentString);
    $end   = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => !empty($r->browser['name']) ? $r->browser['name'] : null,
                'version' => !empty($r->browser['version']) ? $r->browser['version'] : null,
                'isBot'   => null,
                'type'    => null,
            ],
            'platform' => [
                'name'    => !empty($r->os['name']) ? $r->os['name'] : null,
                'version' => !empty($r->os['version']) ? $r->os['version'] : null,
            ],
            'device' => [
                'name'     => !empty($r->device['model']) ? $r->device['model'] : null,
                'brand'    => !empty($r->device['brand']) ? $r->device['brand'] : null,
                'type'     => null,
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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('zsxsoft/php-useragent'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
