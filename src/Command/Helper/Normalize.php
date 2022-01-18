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

        foreach (array_keys($parsed) as $key) {
            $normKey = strtolower(str_replace('res', '', $key));

            $normalized[$key] = $this->normalizeValue($key, $normKey, $parsed[$key]);
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

    private function normalizeValue(string $key, string $normKey, mixed $value): array|string|null
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
                $list[$key2] = $this->normalizeValue($key2, $normKey, $value2);
            }

            return $list;
        }

        if (in_array($normKey, ['clientversion', 'osversion', 'engineversion'])) {
            $value = $this->truncateVersion(mb_strtolower((string) $value));
        } elseif (in_array($normKey, ['devicedisplaysize'])) {
            $value = preg_replace('|[^0-9a-z.]|', '', mb_strtolower((string) $value));
        } else {
            $value = preg_replace('|[^0-9a-z]|', '', mb_strtolower((string) $value));
        }

        // Special Windows normalization for parsers that don't differntiate the version of windows
        // in the name, but use the version.
        if ($normKey === 'osname' && !empty($parsed['resOsVersion'])) {
            if ($value === 'windows') {
                $value .= preg_replace('|[^0-9a-z.]|', '', mb_strtolower($parsed['resOsVersion']));
            }

            if ($value === 'windowsphone') {
                $value .= preg_replace('|[^0-9a-z.]|', '', mb_strtolower($parsed['resOsVersion']));
            }
        }

        if (isset($this->mappings[$normKey])
            && is_array($this->mappings[$normKey])
        ) {
            $v = $this->mappings[$normKey];
        } else {
            $v = [];
        }

        if (is_array($v) && array_key_exists($value, $v)) {
            $value = $v[$value];
        }

        return $value;
    }
}
