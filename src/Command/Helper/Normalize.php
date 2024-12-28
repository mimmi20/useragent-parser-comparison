<?php

/**
 * This file is part of the mimmi20/useragent-parser-comparison package.
 *
 * Copyright (c) 2015-2024, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use Symfony\Component\Console\Helper\Helper;

use function array_key_exists;
use function array_keys;
use function array_slice;
use function explode;
use function file_exists;
use function implode;
use function in_array;
use function is_array;
use function mb_strtolower;
use function preg_replace;
use function str_replace;

final class Normalize extends Helper
{
    private const string MAP_FILE = __DIR__ . '/../../../mappings/mappings.php';

    /** @var array<array<string>> */
    private array $mappings = [];

    /** @throws void */
    public function __construct()
    {
        if (!file_exists(self::MAP_FILE)) {
            return;
        }

        $this->mappings = include self::MAP_FILE;
    }

    /** @throws void */
    #[\Override]
    public function getName(): string
    {
        return 'normalize';
    }

    /**
     * @param array<mixed> $parsed
     *
     * @return array<array<float|string|null>|float|string|null>
     *
     * @throws void
     */
    public function normalize(array $parsed): array
    {
        $normalized = [];

        foreach (array_keys($parsed) as $key) {
            if ($key === 'raw') {
                $normalized[$key] = $parsed[$key];

                continue;
            }

            $normKey = mb_strtolower(str_replace('res', '', $key));

            $normalized[$key] = $this->normalizeValue($normKey, $parsed[$key], $parsed);
        }

        return $normalized;
    }

    /** @throws void */
    private function truncateVersion(string $version): string
    {
        $version      = str_replace('_', '.', $version);
        $versionParts = explode('.', $version);
        $versionParts = array_slice($versionParts, 0, 2);

        return implode('.', $versionParts);
    }

    /**
     * @param array<mixed>|bool|float|int|string|null $value
     * @param array<mixed>                            $parsed
     *
     * @return array<float|string|null>|float|string|null
     *
     * @throws void
     */
    private function normalizeValue(
        string $normKey,
        bool | array | string | int | float | null $value,
        array $parsed,
    ): array | float | string | null {
        if ($value === null) {
            return null;
        }

        if ($value === false) {
            return 'false';
        }

        if ($value === true) {
            return 'true';
        }

        if (is_array($value)) {
            $list = [];

            foreach ($value as $key2 => $value2) {
                $list[$key2] = $this->normalizeValue($normKey, $value2, $parsed);
            }

            return $list;
        }

        if (in_array($normKey, ['clientversion', 'osversion', 'engineversion'], true)) {
            $value = $this->truncateVersion(mb_strtolower((string) $value));
        } elseif (in_array($normKey, ['devicedisplaysize'], true)) {
            $value = preg_replace('|[^0-9a-z.]|', '', mb_strtolower((string) $value));
        } elseif (!in_array($normKey, ['parse_time', 'init_time', 'version'], true)) {
            $value = preg_replace('|[^0-9a-z]|', '', mb_strtolower((string) $value));
        }

        // Special Windows normalization for parsers that don't differntiate the version of windows
        // in the name, but use the version.
        if ($normKey === 'osname' && !empty($parsed['resOsVersion'])) {
            if ($value === 'windows') {
                $value .= preg_replace(
                    '|[^0-9a-z.]|',
                    '',
                    mb_strtolower((string) $parsed['resOsVersion']),
                );
            }

            if ($value === 'windowsphone') {
                $value .= preg_replace(
                    '|[^0-9a-z.]|',
                    '',
                    mb_strtolower((string) $parsed['resOsVersion']),
                );
            }
        }

        if (!array_key_exists($normKey, $this->mappings) || !is_array($this->mappings[$normKey])) {
            return $value;
        }

        $v = array_key_exists($normKey, $this->mappings) && is_array($this->mappings[$normKey])
            ? $this->mappings[$normKey]
            : [];

        if (is_array($v) && array_key_exists($value, $v)) {
            $value = $v[$value];
        }

        return $value;
    }
}
