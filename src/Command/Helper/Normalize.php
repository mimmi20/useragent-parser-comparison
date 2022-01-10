<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use function preg_replace;
use Symfony\Component\Console\Helper\Helper;

class Normalize extends Helper
{
    /**
     * @var string
     */
    private const MAP_FILE = __DIR__ . '/../../../mappings/mappings.php';

    private array $mappings = [];

    public function __construct()
    {
        if (file_exists(self::MAP_FILE)) {
            $this->mappings = include self::MAP_FILE;
        }
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

    private function normalizeValue(string $section, string $key, mixed $value): array|string|null
    {
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
                $list[$key2] = $this->normalizeValue($section, $key2, $value2);
            }

            return $list;
        }

        if ($key === 'version') {
            $value = $this->truncateVersion(mb_strtolower((string) $value));
        } else {
            $value = preg_replace('|[^0-9a-z]|', '', mb_strtolower((string) $value));
        }

        // Special Windows normalization for parsers that don't differntiate the version of windows
        // in the name, but use the version.
        if ($section === 'platform' && $key === 'name' && $value === 'windows') {
            if (!empty($parsed['platform']['version'])) {
                $value .= preg_replace('|[^0-9a-z.]|', '', mb_strtolower($parsed['platform']['version']));
            }
        }

        if ($section === 'platform' && $key === 'name' && $value === 'windowsphone') {
            if (!empty($parsed['platform']['version'])) {
                $value .= preg_replace('|[^0-9a-z.]|', '', mb_strtolower($parsed['platform']['version']));
            }
        }

        if (isset($this->mappings[$section][$key])
            && is_array($this->mappings[$section][$key])
        ) {
            $v = $this->mappings[$section][$key];
        } else {
            $v = [];
        }

        if (is_array($v) && array_key_exists($value, $v)) {
            $value = $v[$value];
        }

        return $value;
    }
}
