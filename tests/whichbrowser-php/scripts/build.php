<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$tests = [];

function isMobile($data)
{
    if (!isset($data['device']['type'])) {
        return null;
    }

    $mobileTypes = ['mobile', 'tablet', 'ereader', 'media', 'watch', 'camera'];

    if (in_array($data['device']['type'], $mobileTypes)) {
        return true;
    }

    if ($data['device']['type'] === 'gaming') {
        if (isset($data['device']['subtype']) && $data['device']['subtype'] === 'portable') {
            return true;
        }
    }

    return false;
}

$uas = [];

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vendor/whichbrowser/parser/tests/data'));
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

foreach ($files as $fixture) {
    /** @var \SplFileInfo $fixture */
    $pathName = $fixture->getPathname();
    $pathName = str_replace('\\', '/', $pathName);

    $content = file_get_contents($pathName);

    if (false === $content || $content === '' || $content === PHP_EOL) {
        continue;
    }

    $provider = Yaml::parse($content);

    foreach ($provider as $data) {
        if (isset($data['useragent'])) {
            $ua = $data['useragent'];

            $data['headers'] = [
                'User-Agent' => $ua,
            ];
            $data['file'] = $pathName;

            unset($data['useragent']);

            $uas[$ua] = $data;
            continue;
        }

        if (is_array($data['headers']) && !empty($data['headers']['User-Agent'])) {
            $ua = $data['headers']['User-Agent'];
            $data['file'] = $pathName;

            $uas[$ua] = $data;
            continue;
        }

        if (!is_string($data['headers'])) {
            continue;
        }

        if (mb_strpos($data['headers'], 'User-Agent: ') !== 0) {
            // There are a few tests that don't have a "User-Agent:" header
            // discarding those since other parsers don't parse different headers in this comparison
            continue;
        }

        $ua = str_replace('User-Agent: ', '', $data['headers']);

        $data['headers'] = [
            'User-Agent' => $ua,
        ];
        $data['file'] = $pathName;

        $uas[$ua] = $data;
    }
}

foreach ($uas as $ua => $data) {
    if (empty($ua)) {
        continue;
    }

    $browserName    = null;
    $browserVersion = null;

    if (isset($data['result']['browser']['name'])) {
        $browserName = $data['result']['browser']['name'];
    }

    if (isset($data['result']['browser']['version'])) {
        if (is_array($data['result']['browser']['version'])) {
            $browserVersion = $data['result']['browser']['version']['value'] ?? null;
        } else {
            $browserVersion = $data['result']['browser']['version'];
        }
    }

    $engineName    = null;
    $engineVersion = null;

    if (isset($data['result']['engine']['name'])) {
        $engineName = $data['result']['engine']['name'];
    }

    if (isset($data['result']['engine']['version'])) {
        if (is_array($data['result']['engine']['version'])) {
            $engineVersion = $data['result']['engine']['version']['value'] ?? null;
        } else {
            $engineVersion = $data['result']['engine']['version'];
        }
    }

    $osName    = null;
    $osVersion = null;

    if (isset($data['result']['os']['name'])) {
        $osName = $data['result']['os']['name'];
    }

    if (isset($data['result']['os']['version'])) {
        if (is_array($data['result']['os']['version'])) {
            $osVersion = $data['result']['os']['version']['value'] ?? null;
        } else {
            $osVersion = $data['result']['os']['version'];
        }
    }

    $tests[] = [
        'headers' => array_change_key_case($data['headers'], CASE_LOWER),
        'client' => [
            'name'    => $browserName,
            'version' => $browserVersion,
            'isBot'   => (isset($data['result']['device']['type']) && 'bot' === $data['result']['device']['type']) ? true : null,
            'type'    => $data['result']['browser']['type'] ?? null,
        ],
        'engine' => [
            'name'    => $engineName,
            'version' => $engineVersion,
        ],
        'platform' => [
            'name'    => $osName,
            'version' => $osVersion,
        ],
        'device' => [
            'name'     => $data['result']['device']['model'] ?? null,
            'brand'    => $data['result']['device']['manufacturer'] ?? null,
            'type'     => $data['result']['device']['type'] ?? null,
            'ismobile' => isMobile($data['result']) ? true : null,
            'istouch'  => null,
        ],
        'raw' => $data,
        'file' => $data['file'],
    ];
}

echo json_encode([
    'tests'   => $tests,
    'version' => \Composer\InstalledVersions::getPrettyVersion('whichbrowser/parser'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
