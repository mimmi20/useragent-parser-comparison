<?php

declare(strict_types = 1);
$tests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$lines = file(__DIR__ . '/../vendor/jaybizzle/crawler-detect/tests/crawlers.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if ($lines !== false) {
    foreach ($lines as $ua) {
        if (empty($ua)) {
            continue;
        }

        $tests[] = [
            'headers' => [
                'user-agent' => $ua,
            ],
            'client' => [
                'name' => null,
                'version' => null,
                'isBot'   => true,
                'type'    => null,
            ],
            'engine' => [
                'name' => null,
                'version' => null,
            ],
            'platform' => [
                'name' => null,
                'version' => null,
            ],
            'device' => [
                'name' => null,
                'brand' => null,
                'type' => null,
                'ismobile' => null,
                'istouch' => null,
            ],
            'raw' => null,
            'file' => null,
        ];
    }
}

$lines = file(__DIR__ . '/../vendor/jaybizzle/crawler-detect/tests/devices.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if ($lines !== false) {
    foreach ($lines as $ua) {
        if (empty($ua)) {
            continue;
        }

        $tests[] = [
            'headers' => [
                'user-agent' => $ua,
            ],
            'client' => [
                'name' => null,
                'version' => null,
                'isBot'   => null,
                'type'    => null,
            ],
            'engine' => [
                'name' => null,
                'version' => null,
            ],
            'platform' => [
                'name' => null,
                'version' => null,
            ],
            'device' => [
                'name' => null,
                'brand' => null,
                'type' => null,
                'ismobile' => null,
                'istouch' => null,
            ],
            'raw' => null,
            'file' => null,
        ];
    }
}

echo json_encode([
    'tests'   => $tests,
    'version' => \Composer\InstalledVersions::getPrettyVersion('jaybizzle/crawler-detect'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
