
require 'date'
require 'device_detector'

$benchmarkPos = array_search('--benchmark', $argv);
$benchmark    = false;

if ($benchmarkPos !== false) {
    $benchmark = true;
    unset($argv[$benchmarkPos]);
    $argv = array_values($argv);
}

$agentListFile = $argv[1];

$results   = [];
$parseTime = 0;

dt = DateTime.now

client = DeviceDetector.new('Test String')

dt = DateTime.now - dt

file = File.new(agentListFile, 'r');

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (empty($agentString)) {
        continue;
    }

    $dd->setUserAgent($agentString);

    $start = microtime(true);
    client = DeviceDetector.new($agentString)
    $end = microtime(true) - $start;

    $parseTime += $end;

    if ($benchmark) {
        continue;
    }

    $clientInfo = $dd->getClient();
    $osInfo     = $dd->getOs();
    $model      = $dd->getModel();
    $brand      = $dd->getBrandName();
    $device     = $dd->getDeviceName();
    $isMobile   = $dd->isMobile();

    $results[] = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $clientInfo['name'] ?? null,
                'version' => $clientInfo['version'] ?? null,
            ],
            'platform' => [
                'name'    => $osInfo['name'] ?? null,
                'version' => $osInfo['version'] ?? null,
            ],
            'device' => [
                'name'     => $model ? $model : null,
                'brand'    => $brand ? $brand : null,
                'type'     => $device ? $device : null,
                'ismobile' => $isMobile ? true : false,
            ],
        ],
        'time' => $end,
    ];
}

$file = null;

// Get version from composer
$package = new \PackageInfo\Package('piwik/device-detector');

echo (new \JsonClass\Json())->encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => memory_get_peak_usage(),
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
