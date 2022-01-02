<?php

declare(strict_types = 1);
$tests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../files'));
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

foreach ($files as $file) {
    /** @var \SplFileInfo $file */
    $pathName = $file->getPathname();
    $pathName = str_replace('\\', '/', $pathName);

    $provider = include $pathName;

    foreach ($provider as $ua => $properties) {
        $tests[] = array_merge(
            [
                'headers' => [
                    'user-agent' => $ua,
                ],
            ],
            $properties,
            [
                'raw' => null,
                'file' => $pathName,
            ]
        );
    }
}

echo json_encode([
    'tests' => $tests,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
