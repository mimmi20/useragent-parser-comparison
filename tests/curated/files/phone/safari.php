<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

return [
    'Mozilla/5.0 (iPhone; CPU iPhone OS 10_2 like Mac OS X) AppleWebKit/602.3.12 (KHTML, like Gecko) Version/10.0 Mobile/14C92 Safari/602.1' => [
        'client' => ['name' => 'safari', 'version' => '10.0'],
        'platform' => ['name' => 'ios', 'version' => '10.2'],
        'device' => ['name' => 'iphone', 'brand' => 'apple', 'type' => 'mobile phone', 'ismobile' => true],
        'engine' => ['name' => 'webkit', 'version' => '602.3.12'],
    ],
];
