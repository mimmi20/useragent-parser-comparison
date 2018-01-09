<?php
declare(strict_types = 1);
$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->exclude('cache')
    ->exclude('data')
    ->exclude('files')
    ->exclude('node_modules')
    ->files()
    ->name('console')
    ->name('build')
    ->name('parse')
    ->name('*.php')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/bin')
    ->in(__DIR__ . '/mappings')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/tests/curated/files/*')
    ->in(__DIR__ . '/parsers')
    ->exclude('vendor')
    ->append([__FILE__]);

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2'                      => true,
        '@PHP70Migration'            => true,
        '@PHP71Migration'            => true,
        'array_syntax'               => ['syntax' => 'short'],
        'binary_operator_spaces'     => ['align_double_arrow' => true, 'align_equals' => true],
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'concat_space'               => ['spacing' => 'one'],
        'dir_constant'               => true,
        'single_quote'               => false,
        'ternary_to_null_coalescing' => false,
    ])
    ->setUsingCache(true)
    ->setFinder($finder);
