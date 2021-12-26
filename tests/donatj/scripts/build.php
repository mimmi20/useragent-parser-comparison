<?php

declare(strict_types = 1);
$tests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$content = file_get_contents(__DIR__ . '/../vendor/donatj/phpuseragentparser/tests/user_agents.dist.json');

if ($content === '' || $content === PHP_EOL) {
    exit;
}

$provider = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

foreach ($provider as $ua => $data) {
    if (empty($ua)) {
        continue;
    }

    $tests[] = [
        'headers' => [
            'user-agent' => $ua,
        ],
        'client' => [
            'name'    => $data['browser'],
            'version' => $data['version'],
            'isBot'   => null,
            'type'    => null,
        ],
        'engine' => [
            'name'    => null,
            'version' => null,
        ],
        'platform' => [
            'name'    => $data['platform'],
            'version' => null,
        ],
        'device' => [
            'name'     => null,
            'brand'    => null,
            'type'     => null,
            'ismobile' => null,
            'istouch'  => null,
        ],
        'raw' => $data,
        'file' => null,
    ];
}

echo json_encode([
    'tests'   => $tests,
    'version' => \Composer\InstalledVersions::getPrettyVersion('donatj/phpuseragentparser'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
