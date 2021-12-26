<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/../vendor/autoload.php';

$tests = [];

function processFixture(\SplFileInfo $fixture, &$tests): void
{
    $provider = Spyc::YAMLLoad($fixture->getPathname());

    foreach ($provider['test_cases'] as $data) {
        if (empty($data['user_agent_string'])) {
            continue;
        }

        $ua = $data['user_agent_string'];

        if (isset($tests[addcslashes($ua, "\n")])) {
            $browser  = $tests[$ua]['client'];
            $platform = $tests[$ua]['platform'];
            $device   = $tests[$ua]['device'];
        } else {
            $browser = [
                'name'    => null,
                'version' => null,
                'isBot'   => null,
                'type'    => null,
            ];

            $platform = [
                'name'    => null,
                'version' => null,
            ];

            $device = [
                'name'     => null,
                'brand'    => null,
                'type'     => null,
                'ismobile' => null,
                'istouch'  => null,
            ];
        }

        switch ($fixture->getFilename()) {
            case 'test_device.yaml':
                $device = [
                    'name'     => $data['model'],
                    'brand'    => $data['brand'],
                    'type'     => null,
                    'ismobile' => null,
                    'istouch'  => null,
                ];

                break;
            case 'test_os.yaml':
            case 'additional_os_tests.yaml':
                $platform = [
                    'name'    => $data['family'],
                    'version' => $data['major'] . (!empty($data['minor']) ? '.' . $data['minor'] : ''),
                ];

                break;
            case 'test_ua.yaml':
            case 'firefox_user_agent_strings.yaml':
            case 'opera_mini_user_agent_strings.yaml':
            case 'pgts_browser_list.yaml':
                $browserVersion = (isset($data['major']) && $data['major'] !== '') ? $data['major'] . ($data['minor'] !== null ? '.' . $data['minor'] : '') : '';

                if ($browserVersion === '0') {
                    $browserVersion = '';
                }

                $browser = [
                    'name'    => $data['family'],
                    'version' => $browserVersion,
                ];

                break;
        }

        $expected = [
            'headers' => [
                'user-agent' => addcslashes($ua, "\n"),
            ],
            'client'  => $browser,
            'engine' => [
                'name'    => null,
                'version' => null,
            ],
            'platform' => $platform,
            'device'   => $device,
            'raw' => $data,
            'file' => null,
        ];

        $tests[addcslashes($ua, "\n")] = $expected;
    }
}

$appendIter = new AppendIterator();
$appendIter->append(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vendor/ua-parser/uap-core/tests')));
$appendIter->append(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vendor/ua-parser/uap-core/test_resources')));
$files = new class($appendIter, 'yaml') extends \FilterIterator {
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

    if ($fixture->getFilename() === 'pgts_browser_list-orig.yaml') {
        continue;
    }

    processFixture($fixture, $tests);
}

echo json_encode([
    'tests'   => array_values($tests),
    'version' => file_get_contents(__DIR__ . '/../version.txt'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
