<?php

declare(strict_types = 1);
$tests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vendor/mobiledetect/mobiledetectlib/tests/providers'));
$files = new class($iterator, 'php') extends \FilterIterator {
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

    $provider = include $pathName;

    foreach ($provider as $vendor => $vendorData) {
        foreach ($vendorData as $ua => $testData) {
            if (is_int($ua)) {
                continue;
            }

            $tests[] = [
                'headers' => [
                    'user-agent' => $ua,
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
                    'name'     => $testData['model'] ?? null,
                    'brand'    => null,
                    'type'     => null,
                    'ismobile' => $testData['isMobile'] ?? false,
                    'istouch'  => null,
                ],
                'raw' => $testData,
                'file' => $pathName,
            ];
        }
    }
}

echo json_encode([
    'tests'   => $tests,
    'version' => \Composer\InstalledVersions::getPrettyVersion('mobiledetect/mobiledetectlib'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
