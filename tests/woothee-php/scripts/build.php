<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once __DIR__ . '/../vendor/autoload.php';

$tests = [];

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vendor/woothee/woothee-testset/testsets'));
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

    $provider = Spyc::YAMLLoad($pathName);

    foreach ($provider as $data) {
        if (empty($data['target'])) {
            continue;
        }

        $ua = $data['target'];

        $tests[] = [
            'headers' => [
                'user-agent' => $ua,
            ],
            'client' => [
                'name'    => $data['name'] ?? null,
                'version' => $data['version'] ?? null,
                'isBot'   => (isset($data['category']) && 'crawler' === $data['category']) ? true : null,
                'type'    => $data['category'] ?? null,
            ],
            'engine' => [
                'name'    => null,
                'version' => null,
            ],
            'platform' => [
                'name'    => (isset($data['os']) && $data['os'] !== 'UNKNOWN') ? $data['os'] : null,
                'version' => $data['os_version'] ?? null,
            ],
            'device' => [
                'name'     => null,
                'brand'    => null,
                'type'     => $data['category'] ?? null,
                'ismobile' => null,
                'istouch'  => null,
            ],
            'raw' => $data,
            'file' => $pathName,
        ];
    }
}

echo json_encode([
    'tests'   => $tests,
    'version' => \Composer\InstalledVersions::getPrettyVersion('woothee/woothee-testset'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
