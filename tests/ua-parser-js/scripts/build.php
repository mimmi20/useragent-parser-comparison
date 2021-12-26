<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

$uas = [];

$base = [
    'browser' => [
        'name'    => null,
        'version' => null,
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
        'brand'    => null,
        'type'     => null,
        'ismobile' => null,
        'istouch'  => null,
    ],
    'bot' => [
        'isbot' => null,
    ],
];

require_once __DIR__ . '/../vendor/autoload.php';

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../node_modules/ua-parser-js/test'));
$files = new class($iterator, 'json') extends \FilterIterator {
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

foreach ($files as $fixture) {
    /** @var \SplFileInfo $fixture */
    $pathName = $fixture->getPathname();
    $pathName = str_replace('\\', '/', $pathName);

    $content = file_get_contents($pathName);

    if ($content === '' || $content === PHP_EOL) {
        continue;
    }

    $provider     = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    $providerName = $fixture->getFilename();

    foreach ($provider as $data) {
        $ua = $data['ua'];

        if (!isset($uas[$ua])) {
            $uas[$ua] = array_merge(
                [
                    'headers' => [
                        'user-agent' => $ua,
                    ],
                ],
                $base,
                [
                    'raw' => $data,
                    'file' => null,
                ]
            );
        }

        switch ($providerName) {
            case 'browser-test.json':
                $uas[$ua]['client']['name']    = (!isset($data['expect']['name']) || $data['expect']['name'] === 'undefined') ? null : $data['expect']['name'];
                $uas[$ua]['client']['version'] = (!isset($data['expect']['version']) || $data['expect']['version'] === 'undefined') ? null : $data['expect']['version'];

                break;
            case 'device-test.json':
                $uas[$ua]['device']['name']  = (!isset($data['expect']['model']) || $data['expect']['model']  === 'undefined') ? null : $data['expect']['model'];
                $uas[$ua]['device']['brand'] = (!isset($data['expect']['vendor']) || $data['expect']['vendor'] === 'undefined') ? null : $data['expect']['vendor'];
                $uas[$ua]['device']['type']  = (!isset($data['expect']['type']) || $data['expect']['type']   === 'undefined') ? null : $data['expect']['type'];

                break;
            case 'os-test.json':
                $uas[$ua]['platform']['name']    = (!isset($data['expect']['name']) || $data['expect']['name'] === 'undefined') ? null : $data['expect']['name'];
                $uas[$ua]['platform']['version'] = (!isset($data['expect']['version']) || $data['expect']['version'] === 'undefined') ? null : $data['expect']['version'];

                break;
            case 'engine-test.json':
                $uas[$ua]['engine']['name']    = (!isset($data['expect']['name']) || $data['expect']['name'] === 'undefined') ? null : $data['expect']['name'];
                $uas[$ua]['engine']['version'] = (!isset($data['expect']['version']) || $data['expect']['version'] === 'undefined') ? null : $data['expect']['version'];

                break;
            // Skipping cpu-test.json because we don't look at CPU data, which is all that file tests against
            // Skipping mediaplayer-test.json because it seems that this file isn't used in this project's actual tests (see test.js)
        }
    }
}

// Get version from installed module's package.json
$package = json_decode(file_get_contents(__DIR__ . '/../node_modules/ua-parser-js/package.json'));
$version = $package->version;

echo json_encode([
    'tests'   => array_values($uas),
    'version' => $version,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
