<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

return [
    'Mozilla/5.0 (iPhone; CPU iPhone OS 10_2 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) CriOS/54.0.2840.91 Mobile/14C92 Safari/602.1' => [
        'client' => ['name' => 'chrome', 'version' => '54.0.2840.91'],
        'platform' => ['name' => 'ios', 'version' => '10.2'],
        'device' => ['name' => 'iphone', 'brand' => 'apple', 'type' => 'mobile phone', 'ismobile' => true],
        'engine' => ['name' => 'webkit', 'version' => '602.1.50'],
    ],
];
