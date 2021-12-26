<?php

declare(strict_types = 1);
$tests = [];

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../vendor/cbschuld/browser.php/tests/TabDelimitedFileIterator.php';

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vendor/cbschuld/browser.php/tests/lists'));
$files = new class($iterator, 'txt') extends \FilterIterator {
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

$tests = [];

foreach ($files as $fixture) {
    /** @var \SplFileInfo $fixture */

    $pathName = $fixture->getPathname();
    $pathName = str_replace('\\', '/', $pathName);

    $tabIterator = new TabDelimitedFileIterator($pathName);

    foreach ($tabIterator as $testData) {
        if (empty($testData[0])) {
            continue;
        }

        $tests[] = [
            'headers' => [
                'user-agent' => $testData[0],
            ],
            'client' => [
                'name'    => $testData[2],
                'version' => $testData[3],
                'isBot'   => null,
                'type'    => null,
            ],
            'engine' => [
                'name'    => null,
                'version' => null,
            ],
            'platform' => [
                'name'    => $testData[5] ?? null,
                'version' => null,
            ],
            'device' => [
                'name'     => null,
                'brand'    => null,
                'type'     => null,
                'ismobile' => null,
                'istouch'  => null,
            ],
            'raw' => $testData,
            'file' => $pathName,
        ];
    }
}

echo json_encode([
    'tests'   => $tests,
    'version' => \Composer\InstalledVersions::getPrettyVersion('cbschuld/browser.php'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
