<?php

declare(strict_types = 1);
$tests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vendor/browscap/browscap/tests/issues'));
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

foreach ($files as $fixture) {
    /** @var \SplFileInfo $fixture */
    if (in_array($fixture->getFilename(), ['issue-000-invalids.php', 'issue-000-invalid-versions.php'])) {
        continue;
    }

    $pathName = $fixture->getPathname();
    $pathName = str_replace('\\', '/', $pathName);

    $provider = include $pathName;

    foreach ($provider as $testName => $data) {
        if ($data['full'] === false || empty($data['ua'])) {
            continue;
        }

        $ua = $data['ua'];

        $isMobile = false;

        if (isset($data['properties']['Device_Type'])) {
            switch ($data['properties']['Device_Type']) {
                case 'Mobile Phone':
                case 'Tablet':
                case 'Console':
                case 'Digital Camera':
                case 'Ebook Reader':
                case 'Mobile Device':
                    $isMobile = true;

                    break;
            }
        }

        $tests[] = [
            'headers' => [
                'user-agent' => $ua,
            ],
            'client' => [
                'name'    => $data['properties']['Browser'] ?? null,
                'version' => (isset($data['properties']['Version']) && $data['properties']['Version'] !== '0.0' ? $data['properties']['Version'] : null),
                'isBot'   => array_key_exists('Crawler', $data['properties']) ? $data['properties']['Crawler'] : null,
                'type'    => $data['properties']['Browser_Type'] ?? null,
            ],
            'engine' => [
                'name'    => $data['properties']['RenderingEngine_Name'] ?? null,
                'version' => (isset($data['properties']['RenderingEngine_Version']) && $data['properties']['RenderingEngine_Version'] !== '0.0' ? $data['properties']['RenderingEngine_Version'] : null),
            ],
            'platform' => [
                'name'    => $data['properties']['Platform'] ?? null,
                'version' => (isset($data['properties']['Platform_Version']) && $data['properties']['Platform_Version'] !== '0.0' ? $data['properties']['Platform_Version'] : null),
            ],
            'device' => [
                'name'     => $data['properties']['Device_Name'] ?? null,
                'brand'    => $data['properties']['Device_Brand_Name'] ?? null,
                'type'     => $data['properties']['Device_Type'] ?? null,
                'ismobile' => $isMobile,
                'istouch'  => (isset($data['properties']['Device_Pointing_Method']) && $data['properties']['Device_Pointing_Method'] === 'touchscreen'),
            ],
            'raw' => $data,
            'file' => $pathName,
        ];
    }
}

echo json_encode([
    'tests'   => $tests,
    'version' => \Composer\InstalledVersions::getPrettyVersion('browscap/browscap'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
