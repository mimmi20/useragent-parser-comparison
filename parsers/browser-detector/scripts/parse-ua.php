<?php

/**
 * This file is part of the mimmi20/useragent-parser-comparison package.
 *
 * Copyright (c) 2015-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

use BrowserDetector\DetectorFactory;
use Composer\InstalledVersions;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

$uaPos       = array_search('--ua', $argv, true);
$hasUa       = false;
$agentString = '';

if ($uaPos !== false) {
    $hasUa = true;

    $agentString = $argv[2];
}

require __DIR__ . '/../vendor/autoload.php';

$cache = new class () implements CacheInterface {
    /**
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return null;
    }

    /**
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function set(string $key, mixed $value, DateInterval | int | null $ttl = null): bool
    {
        return false;
    }

    /**
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function delete(string $key): bool
    {
        return false;
    }

    /** @throws void */
    public function clear(): bool
    {
        return false;
    }

    /**
     * @param iterable<string> $keys a list of keys that can be obtained in a single operation
     *
     * @return iterable<mixed>
     *
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return [];
    }

    /**
     * @param iterable<string, mixed> $values a list of key => value pairs for a multiple-set operation
     *
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function setMultiple(iterable $values, DateInterval | int | null $ttl = null): bool
    {
        return false;
    }

    /**
     * @param iterable<string> $keys a list of string-based keys to be deleted
     *
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return false;
    }

    /**
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function has(string $key): bool
    {
        return false;
    }
};

$start    = microtime(true);
$logger   = new NullLogger();
$factory  = new DetectorFactory($cache, $logger);
$detector = $factory();
$detector->getBrowser('Test String');
$initTime = microtime(true) - $start;

$output = [
    'hasUa' => $hasUa,
    'headers' => ['user-agent' => $agentString],
    'result' => [
        'parsed' => null,
        'err' => null,
    ],
    'parse_time' => 0,
    'init_time' => $initTime,
    'memory_used' => 0,
    'version' => InstalledVersions::getPrettyVersion('mimmi20/browser-detector'),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = $detector->getBrowser($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => $r['device'],
        'client' => $r['client'],
        'platform' => $r['os'],
        'engine' => $r['engine'],
        'raw' => $r,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
