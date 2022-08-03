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
use function array_slice;
use function explode;
use function file_exists;
use function implode;
use function is_array;
use function mb_strtolower;
use function preg_replace;
use function str_replace;

final class Normalize extends Helper
{
    private const MAP_FILE = __DIR__ . '/../../../mappings/mappings.php';

    public function getName(): string
    {
        return 'normalize';
    }

    /**
     * @param mixed[][] $parsed
     *
     * @return mixed[]
     */
    public function normalizeParsed(array $parsed): array
    {
        $normalized = [];
        $mappings   = [];

        if (file_exists(self::MAP_FILE)) {
            $mappings = include self::MAP_FILE;
        }

        $sections = ['browser', 'platform', 'device'];

        foreach ($sections as $section) {
            if (!array_key_exists($section, $parsed)) {
                continue;
            }

            $normalized[$section] = [];
            $properties           = $parsed[$section];

            foreach ($properties as $key => $value) {
                if (null === $value) {
                    $normalized[$section][$key] = $value;

                    continue;
                }

                if ('version' === $key) {
                    $value = $this->truncateVersion(mb_strtolower((string) $value));
                } elseif (false === $value) {
                    $value = 'false';
                } elseif (true === $value) {
                    $value = 'true';
                } else {
                    $value = preg_replace('|[^0-9a-z]|', '', mb_strtolower((string) $value));
                }

                // Special Windows normalization for parsers that don't differntiate the version of windows
                // in the name, but use the version.
                if ('platform' === $section && 'name' === $key && 'windows' === $value) {
                    if (!empty($parsed['platform']['version'])) {
                        $value .= preg_replace('|[^0-9a-z.]|', '', mb_strtolower($parsed['platform']['version']));
                    }
                }

                if ('platform' === $section && 'name' === $key && 'windowsphone' === $value) {
                    if (!empty($parsed['platform']['version'])) {
                        $value .= preg_replace('|[^0-9a-z.]|', '', mb_strtolower($parsed['platform']['version']));
                    }
                }

                if (
                    isset($mappings[$section][$key])
                    && is_array($mappings[$section][$key])
                ) {
                    $v = $mappings[$section][$key];
                } else {
                    $v = [];
                }

                if (is_array($v) && array_key_exists($value, $v)) {
                    $value = $v[$value];
                }

                $normalized[$section][$key] = $value;
            }
        }

        return $normalized;
    }

    private function truncateVersion(string $version): string
    {
        $version      = str_replace('_', '.', $version);
        $versionParts = explode('.', $version);
        $versionParts = array_slice($versionParts, 0, 2);

        return implode('.', $versionParts);
    }
}
