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

use BrowscapHelper\Source\BrowscapSource;
use Composer\InstalledVersions;

error_reporting(E_ERROR | E_WARNING | E_PARSE);
chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

$source        = new BrowscapSource();
$baseMessage   = sprintf('reading from source %s ', $source->getName());
$messageLength = 0;
$tests         = [];

if ($source->isReady($baseMessage)) {
    foreach ($source->getProperties($baseMessage, $messageLength) as $uid => $test) {
        $tests[$uid] = $test;
    }
}

echo json_encode([
    'tests' => $tests,
    'version' => InstalledVersions::getPrettyVersion('browscap/browscap'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
