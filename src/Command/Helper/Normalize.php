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

namespace UserAgentParserComparison\Command\Helper;

use Symfony\Component\Console\Helper\Helper;
use UaDataMapper\InputMapper;

use function array_key_exists;
use function array_slice;
use function explode;
use function implode;
use function is_array;
use function str_replace;

final class Normalize extends Helper
{
    private InputMapper $inputMapper;

    /** @throws void */
    public function __construct()
    {
        $this->inputMapper = new InputMapper();
    }

    /** @throws void */
    public function getName(): string
    {
        return 'normalize';
    }

    /**
     * @param array<string, mixed> $parsed
     *
     * @return array<string, mixed>
     *
     * @throws void
     */
    public function normalize(array $parsed): array
    {
        if (isset($parsed['device']['deviceName']) && is_array($parsed['device']['deviceName'])) {
            $parsed['device']['deviceName'] = array_key_exists('model', $parsed['device']['deviceName'])
                ? $parsed['device']['deviceName']['model']
                : null;
        }

        return [
            'client' => [
                'name' => $this->inputMapper->mapBrowserName($parsed['client']['name']),
                'modus' => $parsed['client']['modus'] ?? null,
                'version' => $this->inputMapper->mapBrowserVersion(
                    (string) ($parsed['client']['version'] ?? ''),
                    $parsed['client']['name'],
                ),
                'manufacturer' => $this->inputMapper->mapBrowserMaker(
                    $parsed['client']['manufacturer'] ?? '',
                    $parsed['client']['name'],
                ),
                'bits' => $parsed['client']['bits'] ?? null,
                'type' => $this->inputMapper->mapBrowserType($parsed['client']['type'] ?? null),
                'isbot' => $parsed['client']['isbot'] ?? null,
            ],
            'platform' => [
                'name' => $this->inputMapper->mapOsName($parsed['platform']['name']),
                'marketingName' => $this->inputMapper->mapOsMaker(
                    $parsed['platform']['marketingName'] ?? '',
                    $parsed['platform']['name'],
                ),
                'version' => $this->inputMapper->mapOsVersion(
                    (string) ($parsed['platform']['version'] ?? ''),
                    $parsed['platform']['name'],
                ),
                'manufacturer' => $parsed['platform']['manufacturer'] ?? null,
                'bits' => $parsed['platform']['bits'] ?? null,
            ],
            'device' => [
                'deviceName' => $this->inputMapper->mapDeviceName(
                    $parsed['device']['deviceName'] ?? null,
                ),
                'marketingName' => $this->inputMapper->mapDeviceMarketingName(
                    $parsed['device']['marketingName'] ?? null,
                    $parsed['device']['deviceName'] ?? null,
                ),
                'manufacturer' => $this->inputMapper->mapDeviceMaker(
                    $parsed['device']['manufacturer'] ?? '',
                    $parsed['device']['deviceName'] ?? null,
                ),
                'brand' => $this->inputMapper->mapDeviceBrandName(
                    $parsed['device']['brand'] ?? null,
                    $parsed['device']['deviceName'] ?? null,
                ),
                'display' => [
                    'width' => $parsed['device']['display']['width'] ?? null,
                    'height' => $parsed['device']['display']['height'] ?? null,
                    'touch' => $parsed['device']['display']['touch'] ?? null,
                    'type' => $parsed['device']['display']['type'] ?? null,
                    'size' => $parsed['device']['display']['size'] ?? null,
                ],
                'dualOrientation' => $parsed['device']['dualOrientation'] ?? null,
                'type' => $this->inputMapper->mapDeviceType($parsed['device']['type']),
                'simCount' => $parsed['device']['simCount'] ?? null,
                'ismobile' => $parsed['device']['ismobile'] ?? null,
            ],
            'engine' => [
                'name' => $this->inputMapper->mapEngineName($parsed['engine']['name'] ?? null),
                'version' => $this->inputMapper->mapEngineVersion(
                    (string) $parsed['engine']['version'],
                ),
                'manufacturer' => $parsed['engine']['manufacturer'] ?? null,
            ],
        ];
    }

    /** @throws void */
    private function truncateVersion(string $version): string
    {
        $version      = str_replace('_', '.', $version);
        $versionParts = explode('.', $version);
        $versionParts = array_slice($versionParts, 0, 2);

        return implode('.', $versionParts);
    }
}
