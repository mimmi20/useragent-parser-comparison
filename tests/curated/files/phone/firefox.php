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

return [
    'Mozilla/5.0 (iPhone; CPU iPhone OS 10_2 like Mac OS X) AppleWebKit/602.3.12 (KHTML, like Gecko) FxiOS/5.3 Mobile/14C92 Safari/602.3.12' => [
        'client' => ['name' => 'firefox', 'version' => '5.3'],
        'platform' => ['name' => 'ios', 'version' => '10.2'],
        'device' => ['name' => 'iphone', 'brand' => 'apple', 'type' => 'mobile phone', 'ismobile' => true],
        'engine' => ['engine' => 'webkit', 'version' => '602.3.12'],
    ],
];
