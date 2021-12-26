<?php

declare(strict_types = 1);

use Symfony\Component\Yaml\Yaml;

$uas = [];

require_once __DIR__ . '/../vendor/autoload.php';

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vendor/endorphin-studio/browser-detector-tests-data/data'));
$files = new class($iterator, 'yaml') extends \FilterIterator {
    private string $extension;

    public function __construct(\Iterator $iterator , string $extension)
    {
        parent::__construct($iterator);
        $this->extension = $extension;
    }

    public function accept(): bool
    {
        $file = $this->getInnerIterator()->current();

        assert($file instanceof \SplFileInfo);

        return $file->isFile() && $file->getExtension() === $this->extension;
    }
};

$defaultExpected = [
    'headers' => [
        'user-agent' => null,
    ],
    'client' => [
        'name'    => null,
        'version' => null,
        'isBot'   => null,
        'type'    => null,
    ],
    'engine' => [
        'name'    => null,
        'version' => null,
    ],
    'platform' => [
        'name'    => null,
        'version' => null,
    ],
    'device' => [
        'name'     => null,
        'type'     => null,
        'brand'    => null,
        'ismobile' => null,
        'istouch'  => null,
    ],
    'bot' => [
        'isbot' => null,
    ],
    'raw' => null,
    'file' => null,
];

foreach ($files as $fixture) {
    /** @var \SplFileInfo $fixture */
    $pathName = $fixture->getPathname();
    $pathName = str_replace('\\', '/', $pathName);

    $provider = Spyc::YAMLLoad($pathName);

    if (isset($provider['checkList']['name']) && mb_strpos($pathName, '/browser/') !== false) {
        $expected = [
            'client' => [
                'name' => $provider['checkList']['name'],
            ],
            'raw' => $provider['checkList'],
        ];

        if (isset($provider['checkList']['type'])) {
            $expected['device'] = [
                'type' => $provider['checkList']['type'],
            ];
        }
    } elseif (isset($provider['checkList']['name']) && mb_strpos($pathName, '/device/') !== false) {
        $expected = [
            'device' => [
                'name' => $provider['checkList']['name'],
            ],
            'raw' => $provider['checkList'],
        ];

        if (isset($provider['checkList']['type'])) {
            $expected['device'] = [
                'type' => $provider['checkList']['type'],
            ];
        }
    } elseif (isset($provider['checkList']['name']) && mb_strpos($pathName, '/os/') !== false) {
        if ($provider['checkList']['name'] === 'Windows' && isset($provider['checkList']['version'])) {
            $name = $provider['checkList']['name'] . $provider['checkList']['version'];
        } else {
            $name = $provider['checkList']['name'];
        }
        $expected = [
            'platform' => [
                'name' => $name,
            ],
            'raw' => $provider['checkList'],
        ];
    } elseif (isset($provider['checkList']['name']) && mb_strpos($pathName, '/robot/') !== false) {
        $expected = [
            'client' => [
                'name' => $provider['checkList']['name'],
                'isbot' => true,
            ],
            'raw' => $provider['checkList'],
        ];
    } else {
        $expected = [];
    }

    if (empty($expected)) {
        continue;
    }

    foreach ($provider['uaList'] as $ua) {
        $agent = (string) $ua;

        if (isset($uas[$agent])) {
            $uas[$agent] = array_merge(
                $uas[$agent],
                [
                    'headers' => [
                        'user-agent' => $agent,
                    ],
                ],
                $expected
            );
        } else {
            $uas[$agent] = array_merge(
                $defaultExpected,
                [
                    'headers' => [
                        'user-agent' => $agent,
                    ],
                ],
                $expected
            );
        }
    }
}

echo json_encode([
    'tests'   => $uas,
    'version' => \Composer\InstalledVersions::getPrettyVersion('endorphin-studio/browser-detector'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
