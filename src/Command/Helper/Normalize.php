<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
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
use function sprintf;
use function str_replace;
use function var_dump;

final class Normalize extends Helper
{
    private const MAP_FILE = __DIR__ . '/../../../mappings/mappings.php';

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
            if ('raw' === $key) {
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
        if (null === $value) {
            return null;
        }

        if (false === $value) {
            return 'false';
        }

        if (true === $value) {
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
        if ('osname' === $normKey && !empty($parsed['resOsVersion'])) {
            if ('windows' === $value) {
                $value .= preg_replace('|[^0-9a-z.]|', '', mb_strtolower($parsed['resOsVersion']));
            }

            if ('windowsphone' === $value) {
                $value .= preg_replace('|[^0-9a-z.]|', '', mb_strtolower($parsed['resOsVersion']));
            }
        }

        if (!array_key_exists($normKey, $this->mappings) || !is_array($this->mappings[$normKey])) {
            return $value;
        }

        if (
            array_key_exists($normKey, $this->mappings)
            && is_array($this->mappings[$normKey])
        ) {
            $v = $this->mappings[$normKey];
        } else {
            $v = [];
        }

        if (!is_array($v)) {
            var_dump(sprintf('\'%s\' found in mapping table, but izs not an array - 2.', $normKey));
        }

        if (is_array($v) && array_key_exists($value, $v)) {
            $value = $v[$value];
        }

        return $value;
    }
}
