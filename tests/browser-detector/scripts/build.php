<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

$allTests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$logger     = new \Psr\Log\NullLogger();

$companyLoaderFactory = new \BrowserDetector\Loader\CompanyLoaderFactory();

/** @var \BrowserDetector\Loader\CompanyLoader $companyLoader */
$companyLoader = $companyLoaderFactory();
$resultFactory = new \ResultFactory($companyLoader);

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vendor/mimmi20/browser-detector/tests/data/'));
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

foreach ($files as $file) {
    $pathName = $file->getPathname();
    $pathName = str_replace('\\', '/', $pathName);

    $content = file_get_contents($pathName);

    if (false === $content || $content === '' || $content === PHP_EOL) {
        continue;
    }

    try {
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        continue;
    }

    if (!is_array($data)) {
        continue;
    }

    foreach ($data as $test) {
        if (!is_array($test['headers']) || !isset($test['headers']['user-agent'])) {
            continue;
        }

        if ($test['headers']['user-agent'] === 'this is a fake ua to trigger the fallback') {
            continue;
        }

        $expectedResult = $resultFactory->fromArray($logger, $test);
        $browserVersion = $expectedResult->getBrowser()->getVersion()->getVersion();
        $osVersion      = $expectedResult->getOs()->getVersion()->getVersion();

        $allTests[] = [
            'headers' => $test['headers'],
            'client' => [
                'name'    => $expectedResult->getBrowser()->getName(),
                'version' => ($browserVersion === '0.0.0' ? null : $browserVersion),
                'isBot'   => $expectedResult->getBrowser()->getType()->isBot() ? true : null,
                'type'    => $expectedResult->getBrowser()->getType()->getType(),
            ],
            'engine' => [
                'name'    => $expectedResult->getEngine()->getName(),
                'version' => $expectedResult->getEngine()->getVersion()->getVersion(),
            ],
            'platform' => [
                'name'    => $expectedResult->getOs()->getName(),
                'version' => ($osVersion === '0.0.0' ? null : $osVersion),
            ],
            'device' => [
                'name'     => $expectedResult->getDevice()->getMarketingName(),
                'brand'    => $expectedResult->getDevice()->getBrand()->getBrandName(),
                'type'     => $expectedResult->getDevice()->getType()->getName(),
                'ismobile' => $expectedResult->getDevice()->getType()->isMobile() ? true : null,
                'istouch'  => $expectedResult->getDevice()->getDisplay()->hasTouch() ? true : null,
            ],
            'raw' => $test,
            'file' => $pathName,
        ];
    }
}

echo json_encode([
    'tests'   => $allTests,
    'version' => \Composer\InstalledVersions::getPrettyVersion('mimmi20/browser-detector'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
