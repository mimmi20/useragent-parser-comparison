<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

return [
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:51.0) Gecko/20100101 Firefox/51.0' => [
        'client' => ['name' => 'firefox', 'version' => '51.0'],
        'platform' => ['name' => 'macos', 'version' => '10.12'],
        'device' => ['name' => 'macintosh', 'brand' => 'apple', 'type' => 'desktop', 'ismobile' => false],
        'engine' => ['name' => 'gecko', 'version' => '51.0'],
    ],
    'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:43.0) Gecko/20100101 Firefox/43.0' => [
        'client' => ['name' => 'firefox', 'version' => '43.0'],
        'platform' => ['name' => 'windows', 'version' => '10.0'],
        'device' => ['name' => null, 'brand' => null, 'type' => 'desktop', 'ismobile' => false],
        'engine' => ['name' => 'gecko', 'version' => '43.0'],
    ],
];
