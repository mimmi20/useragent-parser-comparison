<?php

declare(strict_types = 1);

error_reporting(E_ERROR | E_WARNING | E_PARSE);
chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

$source = new \BrowscapHelper\Source\UapCoreSource();
$baseMessage = sprintf('reading from source %s ', $source->getName());
$messageLength = 0;
$tests = [];

if ($source->isReady($baseMessage)) {
    foreach ($source->getProperties($baseMessage, $messageLength) as $uid => $test) {
        $tests[$uid] = $test;
    }
}

echo json_encode([
    'tests'   => $tests,
    'version' => file_get_contents(__DIR__ . '/../version.txt'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
