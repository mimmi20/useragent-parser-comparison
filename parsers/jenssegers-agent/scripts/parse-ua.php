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

use Jenssegers\Agent\Agent;

$agent = new Agent();
$agent->setUserAgent('Test String');
$agent->isDesktop();
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $agent->setUserAgent($agentString);
    $device          = $agent->device();
    $platform        = $agent->platform();
    $browser         = $agent->browser();
    $isMobile        = $agent->isMobile();
    $browserVersion  = $agent->version($browser);
    $platformVersion = $agent->version($platform);
    $type            = null;
    $isBot           = false;

    if ($agent->isDesktop()) {
        $type = 'desktop';
    } elseif ($agent->isPhone()) {
        $type = 'mobile phone';
    } elseif ($agent->isTablet()) {
        $type = 'tablet';
    } elseif ($agent->isBot()) {
        $type           = null;
        $isBot          = true;
        $browser        = $agent->robot();
        $browserVersion = null;
    }
    $end = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'client' => [
                'name'    => (isset($browser) && false !== $browser) ? $browser : null,
                'version' => (isset($browserVersion) && false !== $browserVersion) ? $browserVersion : null,
                'isBot'   => $isBot ? true : null,
                'type'    => $isBot ? 'crawler' : null,
            ],
            'platform' => [
                'name'    => (isset($platform) && false !== $platform) ? $platform : null,
                'version' => (isset($platformVersion) && false !== $platformVersion) ? $platformVersion : null,
            ],
            'device' => [
                'name'     => (isset($device) && false !== $device) ? $device : null,
                'brand'    => null,
                'type'     => $type,
                'ismobile' => $isMobile ? true : null,
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

$file   = null;
$memory = memory_get_peak_usage();

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => \Composer\InstalledVersions::getPrettyVersion('jenssegers/agent'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
