<?php

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
use function sprintf;
use function str_replace;

class Normalize extends Helper
{
    private const MAP_FILE = __DIR__ . '/../../../mappings/mappings.php';

    private array $mappings = [];

    public function __construct()
    {
        if (!file_exists(self::MAP_FILE)) {
            return;
        }

        $this->mappings = include self::MAP_FILE;
    }

    public function getName(): string
    {
        return 'normalize';
    }

    public function normalize(array $parsed): array
    {
        $normalized = [];
        $sections   = ['client', 'platform', 'device', 'engine'];

        foreach ($sections as $section) {
            if (!array_key_exists($section, $parsed)) {
                continue;
            }

            $normalized[$section] = [];
            $properties           = $parsed[$section];

            foreach ($properties as $key => $value) {
                $normalized[$section][$key] = $this->normalizeValue($section, $key, $value);
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

    /**
     * @return array|string|null
     *
     * @throws void
     */
    private function normalizeValue(string $section, string $key, mixed $value): array | string | null
    {
        if (null === $value) {
            return null;
        }

        if (is_array($value)) {
            $list = [];
            foreach ($value as $key2 => $value2) {
                $list[$key2] = $this->normalizeValue($section, $key2, $value2);
            }

            return $list;
        }

        if (false === $value) {
            $value = 'false';
        }

        if (true === $value) {
            $value = 'true';
        }

        if ('version' === $key) {
            $value = $this->truncateVersion(mb_strtolower((string) $value));
        } else {
            $value = preg_replace('|[^0-9a-z+]|', '', mb_strtolower((string) $value));
        }

        if (!isset($this->mappings[$section][$key])) {
            return $value;
        }

        $v = $this->mappings[$section][$key];

        if (!is_array($v)) {
            return $value;
        }

        $oldValue = $value;

        while (array_key_exists($value, $v)) {
            $value = $v[$value];

            if (null === $value) {
                break;
            }

            if ($value === $oldValue) {
                echo sprintf('normalizing circle detected for value "%s"', $oldValue);
                exit;
            }

            if (array_key_exists($value, $v)) {
                echo sprintf('"%s" was normalized to "%s" which will be normalized again. Please update the normalizing array.' . "\n", $oldValue, $value);
            }
        }

        return $value;
    }
}
