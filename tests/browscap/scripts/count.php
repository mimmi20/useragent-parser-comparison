<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

error_reporting(E_ERROR | E_WARNING | E_PARSE);
chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

$source = new \BrowscapHelper\Source\BrowscapSource();
$baseMessage = sprintf('reading from source %s ', $source->getName());
$messageLength = 0;
$count = 0;

if ($source->isReady($baseMessage)) {
    foreach ($source->getProperties($baseMessage, $messageLength) as $uid => $test) {
        ++$count;
    }
}

echo json_encode([
    'tests' => $count,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
