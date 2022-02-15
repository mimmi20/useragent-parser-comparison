<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

error_reporting(E_ERROR | E_WARNING | E_PARSE);
chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../files'));
$files = new class($iterator, 'php') extends \FilterIterator {
    private string $extension;

    public function __construct(Iterator $iterator, string $extension)
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

foreach ($files as $file) {
    /** @var \SplFileInfo $file */
    $pathName = $file->getPathname();
    $pathName = str_replace('\\', '/', $pathName);

    $provider = include $pathName;

    foreach ($provider as $ua => $properties) {
        $uid = \Ramsey\Uuid\Uuid::uuid4()->toString();

        $tests[$uid] = array_merge(
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
