<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\Device\AbstractDeviceParser;

require_once __DIR__ . '/../vendor/autoload.php';

$tests = [];

// These functions are adapted from DeviceDetector's source
// Didn't want to use the actual classes here due to performance and consideration of what we're actually testing
// (i.e. how can the parser ever fail on this field if the parser is generating it)
function isMobile(array $data): bool
{
    $device     = $data['device']['type'];
    $os         = $data['os']['short_name'] ?? null;
    $deviceType = AbstractDeviceParser::getAvailableDeviceTypes()[$device] ?? null;

    // Mobile device types
    if (!empty($deviceType) && in_array($deviceType, [
            AbstractDeviceParser::DEVICE_TYPE_FEATURE_PHONE,
            AbstractDeviceParser::DEVICE_TYPE_SMARTPHONE,
            AbstractDeviceParser::DEVICE_TYPE_TABLET,
            AbstractDeviceParser::DEVICE_TYPE_PHABLET,
            AbstractDeviceParser::DEVICE_TYPE_CAMERA,
            AbstractDeviceParser::DEVICE_TYPE_PORTABLE_MEDIA_PAYER,
        ])
    ) {
        return true;
    }

    // non mobile device types
    if (!empty($deviceType) && in_array($deviceType, [
            AbstractDeviceParser::DEVICE_TYPE_TV,
            AbstractDeviceParser::DEVICE_TYPE_SMART_DISPLAY,
            AbstractDeviceParser::DEVICE_TYPE_CONSOLE,
        ])
    ) {
        return false;
    }

    // Check for browsers available for mobile devices only
    if (isset($data['client']['type'])
        && $data['client']['type'] === 'browser'
        && Browser::isMobileOnlyBrowser($data['client']['short_name'] ?? 'UNK')
    ) {
        return true;
    }

    if (empty($os) || $os === 'UNK') {
        return false;
    }

    return !isDesktop($data);
}

function isDesktop(array $data): bool
{
    $osShort = $data['os']['short_name'] ?? null;
    if (empty($osShort) || $osShort === 'UNK') {
        return false;
    }
    // Check for browsers available for mobile devices only
    if ($data['client']['type'] === 'browser' && Browser::isMobileOnlyBrowser($data['client']['short_name'] ? $data['client']['short_name'] : 'UNK')) {
        return false;
    }

    return in_array($data['os_family'], ['AmigaOS', 'IBM', 'GNU/Linux', 'Mac', 'Unix', 'Windows', 'BeOS', 'Chrome OS']);
}

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vendor/matomo/device-detector/Tests/fixtures'));
$files = new class($iterator, 'yml') extends \FilterIterator {
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
        // If no client property, may be in bot file, which we're not parsing just yet
        if (empty($data['user_agent'])) {
            continue;
        }

        $ua = $data['user_agent'];

        if (!empty($data['client'])) {
            $expected = [
                'headers' => [
                    'user-agent' => $ua,
                ],
                'client' => [
                    'name' => $data['client']['name'] ?? null,
                    'version' => $data['client']['version'] ?? null,
                    'isBot'   => false,
                    'type'    => $data['client']['type'] ?? null,
                ],
                'engine' => [
                    'name' => $data['client']['engine'] ?? null,
                    'version' => $data['client']['engine_version'] ?? null,
                ],
                'platform' => [
                    'name' => $data['os']['name'] ?? null,
                    'version' => $data['os']['version'] ?? null,
                ],
                'device' => [
                    'name' => (string)$data['device']['model'],
                    'brand' => AbstractDeviceParser::getFullName($data['device']['brand']),
                    'type' => $data['device']['type'],
                    'ismobile' => isMobile($data),
                    'istouch' => null,
                ],
                'raw' => $data,
                'file' => $pathName,
            ];
        } elseif (!empty($data['bot'])) {
            $expected = [
                'headers' => [
                    'user-agent' => $ua,
                ],
                'client' => [
                    'name' => $data['bot']['name'] ?? null,
                    'version' => null,
                    'isBot'   => true,
                    'type'    => $data['bot']['category'] ?? null,
                ],
                'engine' => [
                    'name' => null,
                    'version' => null,
                ],
                'platform' => [
                    'name' => null,
                    'version' => null,
                ],
                'device' => [
                    'name' => null,
                    'brand' => null,
                    'type' => null,
                    'ismobile' => null,
                    'istouch' => null,
                ],
                'raw' => $data,
                'file' => $pathName,
            ];
        } else {
            continue;
        }

        $tests[] = $expected;
    }
}

echo json_encode([
    'tests'   => $tests,
    'version' => \Composer\InstalledVersions::getPrettyVersion('matomo/device-detector'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
