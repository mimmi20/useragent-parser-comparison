<?php

declare(strict_types = 1);
$uas = [];

require_once __DIR__ . '/../vendor/autoload.php';

$provider = simplexml_load_file(
    __DIR__ . '/../vendor/sinergi/browser-detector/tests/BrowserDetector/Tests/_files/UserAgentStrings.xml'
);

foreach ($provider->strings as $string) {
    foreach ($string as $field) {
        $ua = explode("\n", (string) $field->field[6]);
        $ua = array_map('trim', $ua);
        $ua = trim(implode(' ', $ua));

        $browser        = (string) $field->field[0];
        $browserVersion = (string) $field->field[1];

        $platform        = (string) $field->field[2];
        $platformVersion = (string) $field->field[3];

        $device = (string) $field->field[4];

        $uas[$ua] = [
            'browser' => [
                'name'    => $browser,
                'version' => $browserVersion,
            ],
            'platform' => [
                'name'    => $platform,
                'version' => $platformVersion,
            ],
            'device' => [
                'name'     => $device,
                'brand'    => null,
                'type'     => null,
                'ismobile' => null,
            ],
        ];
    }
}

echo json_encode([
    'tests'   => $uas,
    'version' => \Composer\InstalledVersions::getPrettyVersion('sinergi/browser-detector'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
