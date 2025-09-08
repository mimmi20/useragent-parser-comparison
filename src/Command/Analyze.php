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

namespace UserAgentParserComparison\Command;

use FilesystemIterator;
use JsonException;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Throwable;
use UserAgentParserComparison\Command\Helper\Tests;
use UserAgentParserComparison\Compare\Comparison;

use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_pop;
use function array_search;
use function array_shift;
use function array_splice;
use function array_values;
use function assert;
use function count;
use function current;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_string;
use function json_decode;
use function ksort;
use function max;
use function number_format;
use function reset;
use function sort;
use function sprintf;
use function uasort;
use function ucfirst;

use const JSON_THROW_ON_ERROR;

final class Analyze extends Command
{
    private string $runDir = __DIR__ . '/../../data/test-runs';

    /** @var array<string, mixed> */
    private array $options = [];

    /** @var array<string, mixed> */
    private array $comparison = [];

    /** @var array<string, mixed> */
    private array $agents                  = [];
    private Table | null $summaryTable     = null;
    private InputInterface | null $input   = null;
    private OutputInterface | null $output = null;

    /** @var array<string, mixed> */
    private array $failures = [];

    /** @throws void */
    protected function configure(): void
    {
        $this->setName('analyze')
            ->setDescription('Analyzes the data from test runs')
            ->addArgument(
                'run',
                InputArgument::OPTIONAL,
                'The name of the test run directory that you want to analyze',
            )
            ->setHelp('');
    }

    /** @throws void */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input  = $input;
        $this->output = $output;

        $run = $input->getArgument('run');
        assert(is_string($run) || $run === null);

        if (empty($run)) {
            $testHelper = $this->getHelper('tests');
            assert($testHelper instanceof Tests);
            $run = $testHelper->getTest($input, $output);

            if ($run === null) {
                $output->writeln('<error>No valid test run found</error>');

                return self::FAILURE;
            }
        }

        if (!file_exists($this->runDir . '/' . $run)) {
            $output->writeln(sprintf('<error>No run directory found with that id (%s)</error>', $run));

            return self::FAILURE;
        }

        $metaDataFile = $this->runDir . '/' . $run . '/metadata.json';

        if (!file_exists($metaDataFile)) {
            $output->writeln(sprintf('<error>No options file found for run (%s)</error>', $run));

            return self::INVALID;
        }

        try {
            $contents = file_get_contents($metaDataFile);
        } catch (Throwable) {
            $output->writeln(sprintf('<error>Could not read file (%s)</error>', $metaDataFile));

            return self::INVALID;
        }

        try {
            $this->options = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $output->writeln(
                '<error>An error occured while parsing metadata for run ' . $run . '</error>',
            );

            return self::INVALID;
        }

        $output->writeln(sprintf('<info>Analyzing data from test run: %s</info>', $run));

        if (empty($this->options['tests']) && empty($this->options['file'])) {
            $output->writeln(sprintf('<error>Error in options file for run (%s)</error>', $run));

            return self::FAILURE;
        }

        $useTests = true;

        if (empty($this->options['tests'])) {
            $useTests = false;

            $this->options['tests'] = [
                $this->options['file'] => [
                    'metadata' => [
                        'name' => $this->options['file'],
                    ],
                ],
            ];
        }

        $this->summaryTable = new Table($output);

        $rows   = [];
        $totals = [];

        $headerStyle = new TableCellStyle([
            'align' => 'center',
            'fg' => 'green',
        ]);

        $dataStyle = new TableCellStyle([
            'align' => 'right',
            'fg' => 'green',
        ]);

        $rows[] = [
            new TableCell(
                'Parser',
                ['style' => $headerStyle],
            ),
            new TableCell(
                'Version',
                ['style' => $headerStyle],
            ),
            new TableCell(
                'Client Results',
                ['style' => $headerStyle],
            ),
            new TableCell(
                'Engine Results',
                ['style' => $headerStyle],
            ),
            new TableCell(
                'Platform Results',
                ['style' => $headerStyle],
            ),
            new TableCell(
                'Device Results',
                ['style' => $headerStyle],
            ),
            new TableCell(
                'Init Time',
                ['style' => $headerStyle],
            ),
            new TableCell(
                'Parsing Time',
                ['style' => $headerStyle],
            ),
            new TableCell(
                'Memory',
                ['style' => $headerStyle],
            ),
            new TableCell(
                'Score',
                ['style' => $headerStyle],
            ),
        ];

        foreach ($this->options['tests'] as $testSuite => $testData) {
            $this->comparison[$testSuite] = [];

            $expectedFilename = $this->runDir . '/' . $run . '/expected/normalized/' . $testSuite;
            $expectedResults  = ['tests' => []];

            if (file_exists($expectedFilename)) {
                foreach (
                    new FilesystemIterator(
                        $this->runDir . '/' . $run . '/expected/normalized/' . $testSuite,
                    ) as $testFile
                ) {
                    assert($testFile instanceof SplFileInfo);

                    if ($testFile->isDir() || $testFile->getFilename() === 'metadata.json') {
                        continue;
                    }

                    try {
                        $contents = file_get_contents($testFile->getPathname());
                    } catch (Throwable) {
                        continue;
                    }

                    try {
                        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    } catch (Throwable) {
                        $output->writeln(
                            "\r" . $message . '<error>An error occured while normalizing test suite ' . $testFile->getFilename() . '</error>',
                        );

                        continue;
                    }

                    $singleTestName = $testFile->getBasename('.' . $testFile->getExtension());

                    $expectedResults['tests'][$singleTestName] = $data['test'];
                }

                $headerMessage = sprintf(
                    'Parser comparison for <fg=yellow>%s%s</>',
                    $testData['metadata']['name'],
                    isset($testData['metadata']['version']) ? ' (' . $testData['metadata']['version'] . ')' : '',
                );
            } else {
                // When we aren't comparing to a test suite, the first parser's results become the expected results

                $fileName = $this->runDir . '/' . $run . '/results/' . array_keys(
                    $this->options['parsers'],
                )[0] . '/normalized/' . $testSuite . '.json';

                try {
                    $contents = file_get_contents($fileName);
                } catch (Throwable) {
                    $this->output->writeln('<error>Could not read file (' . $fileName . ')</error>');

                    continue;
                }

                try {
                    $testResult    = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    $headerMessage = sprintf(
                        '<fg=yellow>Parser comparison for %s file, using %s results as expected</>',
                        $testSuite,
                        array_keys($this->options['parsers'])[0],
                    );
                } catch (Throwable) {
                    $this->output->writeln(
                        sprintf(
                            '<error>An error occured while parsing metadata for run %s, skipping</error>',
                            $run,
                        ),
                    );

                    continue;
                }

                foreach ($testResult['results'] as $key => $data) {
                    $expectedResults['tests'][$key] = $data['parsed'];
                }
            }

            if (
                !isset($expectedResults['tests'])
                || !is_array($expectedResults['tests'])
                || empty($expectedResults['tests'])
            ) {
                continue;
            }

            $rows[] = new TableSeparator();
            $rows[] = [new TableCell($headerMessage, ['colspan' => 9])];
            $rows[] = new TableSeparator();

            $this->agents = array_flip(array_keys($expectedResults['tests']));

            $scores = [];

            foreach ($this->options['parsers'] as $parserName => $parserData) {
                $passFail = [
                    'client' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                    'platform' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                    'device' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                    'engine' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                ];

                $scores[$parserName][$testSuite] = [
                    'count' => 0,
                    'pass' => 0,
                    'fail' => 0,
                ];

                $parseTime = 0.0;
                $initTime  = 0.0;
                $memoryUse = 0;

                if ($parserData['metadata']['name'] === 'BrowserDetector' || $parserData['metadata']['name'] === 'Matomo Device Detector') {
                    $rows[] = new TableSeparator();
                }

                if ($useTests) {
                    foreach (
                        new FilesystemIterator(
                            $this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $testSuite,
                        ) as $resultFile
                    ) {
                        try {
                            $contents = file_get_contents($resultFile->getPathname());
                        } catch (Throwable) {
                            $this->output->writeln(
                                sprintf(
                                    '<error>Could not read file (%s), skipping</error>',
                                    $resultFile->getPathname(),
                                ),
                            );

                            continue;
                        }

                        try {
                            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                        } catch (Throwable) {
                            $this->output->writeln(
                                sprintf(
                                    '<error>An error occured while parsing file (%s), skipping</error>',
                                    $resultFile->getPathname(),
                                ),
                            );

                            continue;
                        }

                        if (!array_key_exists('parsed', $data)) {
                            continue;
                        }

                        $singleTestName = $resultFile->getBasename('.' . $resultFile->getExtension());

                        $expected   = $expectedResults['tests'][$singleTestName] ?? [];
                        $comparison = new Comparison($expected, $data['parsed'] ?? []);
                        $comparison->setTestname($singleTestName);
                        $comparison->setTest($data);

                        $parseTime += $data['time'];
                        $initTime  += $data['init'];
                        $memoryUse  = max($memoryUse, $data['memory']);

                        foreach (['client', 'platform', 'device', 'engine'] as $compareKey) {
                            if (
                                !array_key_exists($compareKey, $expected)
                                || !array_key_exists($compareKey, $data['parsed'])
                            ) {
                                continue;
                            }

                            $score         = $this->calculateScore(
                                $expected[$compareKey],
                                $data['parsed'][$compareKey],
                            );
                            $possibleScore = $this->calculateScore(
                                $expected[$compareKey],
                                $data['parsed'][$compareKey],
                                true,
                            );

                            $passFail[$compareKey]['count'] += count($expected[$compareKey]);
                            $passFail[$compareKey]['pass']  += $score;
                            $passFail[$compareKey]['fail']  += $possibleScore - $score;

                            $filtered = array_filter(
                                $expected[$compareKey],
                                static fn (mixed $value): bool => $value !== null,
                            );

                            $scores[$parserName][$testSuite]['count'] += count($filtered);
                            $scores[$parserName][$testSuite]['pass']  += $score;
                            $scores[$parserName][$testSuite]['fail']  += $possibleScore - $score;
                        }

                        $this->comparison[$testSuite] = $comparison->getComparison(
                            $parserName,
                            $this->agents[$singleTestName] ?? 0,
                        );
                        $failures                     = $comparison->getFailures();

                        if (empty($failures)) {
                            continue;
                        }

                        $failuresWithDiff = array_filter(
                            $failures,
                            static function (array $var): bool {
                                $diff = false;

                                foreach ($var as $value) {
                                    if ($diff) {
                                        return true;
                                    }

                                    if (!array_key_exists('diff', $value)) {
                                        return true;
                                    }

                                    $diff = $value['diff'];
                                }

                                return $diff;
                            },
                        );

                        if (empty($failuresWithDiff)) {
                            continue;
                        }

                        $this->failures[$testSuite][$parserName][$singleTestName] = [
                            'headers' => $data['headers'],
                            'fail' => $failures,
                        ];
                    }
                } else {
                    $contents = file_get_contents(
                        $this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $testSuite . '.json',
                    );

                    if ($contents === false) {
                        $this->output->writeln(
                            sprintf(
                                '<error>Could not read file (%s), skipping</error>',
                                $this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $testSuite . '.json',
                            ),
                        );

                        continue;
                    }

                    try {
                        $multiData = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        $output->writeln(
                            "\r" . $message . '<error>An error occured while parsing results for the ' . $this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $testSuite . '.json test file</error>',
                        );

                        continue;
                    }

                    foreach (array_keys($multiData['results']) as $singleTestName) {
                        if (!is_array($multiData['results'][$singleTestName]['parsed'])) {
                            continue;
                        }

                        $expected = $expectedResults['tests'][$singleTestName] ?? [];
                        $data     = $multiData['results'][$singleTestName];

                        $comparison = new Comparison($expected, $data['parsed'] ?? []);
                        $comparison->setTestname((string) $singleTestName);
                        $comparison->setTest($data);

                        $parseTime += $data['time'];
                        $initTime  += $data['init'] ?? 0;
                        $memoryUse  = max($memoryUse, $data['memory']);

                        foreach (['client', 'platform', 'device', 'engine'] as $compareKey) {
                            if (
                                !array_key_exists($compareKey, $expected)
                                || !array_key_exists($compareKey, $data['parsed'])
                            ) {
                                continue;
                            }

                            $score         = $this->calculateScore(
                                expected: $expected[$compareKey],
                                actual: $data['parsed'][$compareKey],
                            );
                            $possibleScore = $this->calculateScore(
                                expected: $expected[$compareKey],
                                actual: $data['parsed'][$compareKey],
                                possible: true,
                            );

                            $passFail[$compareKey]['count'] += count($expected[$compareKey]);
                            $passFail[$compareKey]['pass']  += $score;
                            $passFail[$compareKey]['fail']  += $possibleScore - $score;

                            $filtered = array_filter(
                                $expected[$compareKey],
                                static fn (mixed $value): bool => $value !== null,
                            );

                            $scores[$parserName][$testSuite]['count'] += count($filtered);
                            $scores[$parserName][$testSuite]['pass']  += $score;
                            $scores[$parserName][$testSuite]['fail']  += $possibleScore - $score;
                        }

                        $this->comparison[$testSuite] = $comparison->getComparison(
                            $parserName,
                            $this->agents[$singleTestName] ?? 0,
                        );
                        $failures                     = $comparison->getFailures();

                        if (empty($failures)) {
                            continue;
                        }

                        $failuresWithDiff = array_filter(
                            $failures,
                            static function (array $var): bool {
                                $diff = false;

                                foreach ($var as $value) {
                                    if ($diff) {
                                        return true;
                                    }

                                    if (!array_key_exists('diff', $value)) {
                                        return true;
                                    }

                                    $diff = $value['diff'];
                                }

                                return $diff;
                            },
                        );

                        if (empty($failuresWithDiff)) {
                            continue;
                        }

                        $this->failures[$testSuite][$parserName][$singleTestName] = [
                            'headers' => $data['headers'],
                            'fail' => $failures,
                        ];
                    }
                }

                if ($passFail['client']['pass'] + $passFail['client']['fail'] === 0) {
                    $clientAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $clientAPercentage = $passFail['client']['pass'] / ($passFail['client']['pass'] + $passFail['client']['fail']) * 100;
                    $clientAContent    = $this->colorByPercent(
                        $clientAPercentage,
                    ) . $passFail['client']['pass'] . '/' . ($passFail['client']['pass'] + $passFail['client']['fail']) . ' ' . number_format(
                        $clientAPercentage,
                        2,
                    ) . '%</>';
                }

                if ($passFail['engine']['pass'] + $passFail['engine']['fail'] === 0) {
                    $engineAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $engineAPercentage = $passFail['engine']['pass'] / ($passFail['engine']['pass'] + $passFail['engine']['fail']) * 100;
                    $engineAContent    = $this->colorByPercent(
                        $engineAPercentage,
                    ) . $passFail['engine']['pass'] . '/' . ($passFail['engine']['pass'] + $passFail['engine']['fail']) . ' ' . number_format(
                        $engineAPercentage,
                        2,
                    ) . '%</>';
                }

                if ($passFail['platform']['pass'] + $passFail['platform']['fail'] === 0) {
                    $platformAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $platformAPercentage = $passFail['platform']['pass'] / ($passFail['platform']['pass'] + $passFail['platform']['fail']) * 100;
                    $platformAContent    = $this->colorByPercent(
                        $platformAPercentage,
                    ) . $passFail['platform']['pass'] . '/' . ($passFail['platform']['pass'] + $passFail['platform']['fail']) . ' ' . number_format(
                        $platformAPercentage,
                        2,
                    ) . '%</>';
                }

                if ($passFail['device']['pass'] + $passFail['device']['fail'] === 0) {
                    $deviceAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $deviceAPercentage = $passFail['device']['pass'] / ($passFail['device']['pass'] + $passFail['device']['fail']) * 100;
                    $deviceAContent    = $this->colorByPercent(
                        $deviceAPercentage,
                    ) . $passFail['device']['pass'] . '/' . ($passFail['device']['pass'] + $passFail['device']['fail']) . ' ' . number_format(
                        $deviceAPercentage,
                        2,
                    ) . '%</>';
                }

                if ($scores[$parserName][$testSuite]['pass'] + $scores[$parserName][$testSuite]['fail'] === 0) {
                    $summaryAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $summaryAPercentage = $scores[$parserName][$testSuite]['pass'] / ($scores[$parserName][$testSuite]['pass'] + $scores[$parserName][$testSuite]['fail']) * 100;
                    $summaryAContent    = $this->colorByPercent(
                        $summaryAPercentage,
                    ) . $scores[$parserName][$testSuite]['pass'] . '/' . ($scores[$parserName][$testSuite]['pass'] + $scores[$parserName][$testSuite]['fail']) . ' ' . number_format(
                        $summaryAPercentage,
                        2,
                    ) . '%</>';
                }

                $rows[] = [
                    new TableCell(
                        $parserData['metadata']['name'],
                        [
                            'style' => new TableCellStyle(
                                [
                                    'align' => 'left',
                                ]
                            )
                        ],
                    ),
                    new TableCell(
                        $parserData['metadata']['version'] ?? 'n/a',
                        [
                            'style' => new TableCellStyle(
                                [
                                    'align' => 'right',
                                ]
                            )
                        ],
                    ),
                    new TableCell(
                        $clientAContent,
                        [
                            'style' => new TableCellStyle(
                                [
                                    'align' => 'right',
                                ]
                            )
                        ],
                    ),
                    new TableCell(
                        $engineAContent,
                        [
                            'style' => new TableCellStyle(
                                [
                                    'align' => 'right',
                                ]
                            )
                        ],
                    ),
                    new TableCell(
                        $platformAContent,
                        [
                            'style' => new TableCellStyle(
                                [
                                    'align' => 'right',
                                ]
                            )
                        ],
                    ),
                    new TableCell(
                        $deviceAContent,
                        [
                            'style' => new TableCellStyle(
                                [
                                    'align' => 'right',
                                ]
                            )
                        ],
                    ),
                    new TableCell(
                        number_format($initTime, 3, ',', '.') . 's',
                        [
                            'style' => new TableCellStyle(
                                [
                                    'align' => 'right',
                                ]
                            )
                        ],
                    ),
                    new TableCell(
                        number_format($parseTime, 3, ',', '.') . 's',
                        [
                            'style' => new TableCellStyle(
                                [
                                    'align' => 'right',
                                ]
                            )
                        ],
                    ),
                    new TableCell(
                        number_format($memoryUse, 3, ',', '.') . 'B',
                        [
                            'style' => new TableCellStyle(
                                [
                                    'align' => 'right',
                                ]
                            )
                        ],
                    ),
                    new TableCell(
                        $summaryAContent,
                        [
                            'style' => new TableCellStyle(
                                [
                                    'align' => 'right',
                                ]
                            )
                        ],
                    ),
                ];

                if ($parserData['metadata']['name'] === 'BrowserDetector' || $parserData['metadata']['name'] === 'Matomo Device Detector') {
                    $rows[] = new TableSeparator();
                }

                if (!isset($totals[$parserName])) {
                    $totals[$parserName] = [
                        'client' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                        'engine' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                        'platform' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                        'device' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                        'time' => 0,
                        'init' => 0,
                        'memory' => 0,
                        'score' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                    ];
                }

                $totals[$parserName]['client']['count']   += $passFail['client']['count'];
                $totals[$parserName]['client']['pass']    += $passFail['client']['pass'];
                $totals[$parserName]['client']['fail']    += $passFail['client']['fail'];
                $totals[$parserName]['engine']['count']   += $passFail['engine']['count'];
                $totals[$parserName]['engine']['pass']    += $passFail['engine']['pass'];
                $totals[$parserName]['engine']['fail']    += $passFail['engine']['fail'];
                $totals[$parserName]['platform']['count'] += $passFail['platform']['count'];
                $totals[$parserName]['platform']['pass']  += $passFail['platform']['pass'];
                $totals[$parserName]['platform']['fail']  += $passFail['platform']['fail'];
                $totals[$parserName]['device']['count']   += $passFail['device']['count'];
                $totals[$parserName]['device']['pass']    += $passFail['device']['pass'];
                $totals[$parserName]['device']['fail']    += $passFail['device']['fail'];
                $totals[$parserName]['time']              += $parseTime;
                $totals[$parserName]['init']              += $initTime;
                $totals[$parserName]['memory']            += max(
                    $totals[$parserName]['memory'],
                    $memoryUse,
                );
                $totals[$parserName]['score']['count']    += $scores[$parserName][$testSuite]['count'];
                $totals[$parserName]['score']['pass']     += $scores[$parserName][$testSuite]['pass'];
                $totals[$parserName]['score']['fail']     += $scores[$parserName][$testSuite]['fail'];
            }

            $rows[] = new TableSeparator();
        }

        if (count($this->options['tests']) > 1) {
            $rows[] = [new TableCell('<fg=yellow>Total for all Test suites</>', ['colspan' => 13])];
            $rows[] = new TableSeparator();

            foreach ($totals as $parser => $total) {
                if ($total['client']['pass'] + $total['client']['fail'] === 0) {
                    $clientAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $clientAPercentage = $total['client']['pass'] / ($total['client']['pass'] + $total['client']['fail']) * 100;
                    $clientAContent    = $this->colorByPercent(
                        $clientAPercentage,
                    ) . $total['client']['pass'] . '/' . ($total['client']['pass'] + $total['client']['fail']) . ' ' . number_format(
                        $clientAPercentage,
                        2,
                    ) . '%</>';
                }

                if ($total['engine']['pass'] + $total['engine']['fail'] === 0) {
                    $engineAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $engineAPercentage = $total['engine']['pass'] / ($total['engine']['pass'] + $total['engine']['fail']) * 100;
                    $engineAContent    = $this->colorByPercent(
                        $engineAPercentage,
                    ) . $total['engine']['pass'] . '/' . ($total['engine']['pass'] + $total['engine']['fail']) . ' ' . number_format(
                        $engineAPercentage,
                        2,
                    ) . '%</>';
                }

                if ($total['platform']['pass'] + $total['platform']['fail'] === 0) {
                    $platformAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $platformAPercentage = $total['platform']['pass'] / ($total['platform']['pass'] + $total['platform']['fail']) * 100;
                    $platformAContent    = $this->colorByPercent(
                        $platformAPercentage,
                    ) . $total['platform']['pass'] . '/' . ($total['platform']['pass'] + $total['platform']['fail']) . ' ' . number_format(
                        $platformAPercentage,
                        2,
                    ) . '%</>';
                }

                if ($total['device']['pass'] + $total['device']['fail'] === 0) {
                    $deviceAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $deviceAPercentage = $total['device']['pass'] / ($total['device']['pass'] + $total['device']['fail']) * 100;
                    $deviceAContent    = $this->colorByPercent(
                        $deviceAPercentage,
                    ) . $total['device']['pass'] . '/' . ($total['device']['pass'] + $total['device']['fail']) . ' ' . number_format(
                        $deviceAPercentage,
                        2,
                    ) . '%</>';
                }

                if ($total['score']['pass'] + $total['score']['fail'] === 0) {
                    $summaryAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $summaryAPercentage = $total['score']['pass'] / ($total['score']['pass'] + $total['score']['fail']) * 100;
                    $summaryAContent    = $this->colorByPercent(
                        $summaryAPercentage,
                    ) . $total['score']['pass'] . '/' . ($total['score']['pass'] + $total['score']['fail']) . ' ' . number_format(
                        $summaryAPercentage,
                        2,
                    ) . '%</>';
                }

                $rows[] = [
                    $parser,
                    $this->options['parsers'][$parser]['metadata']['version'] ?? 'n/a',
                    $clientAContent,
                    $engineAContent,
                    $platformAContent,
                    $deviceAContent,
                    number_format($total['init'], 3) . 's',
                    number_format($total['time'], 3) . 's',
                    number_format($total['memory']) . 'B',
                    $summaryAContent,
                ];
            }

            $rows[] = new TableSeparator();
        }

        array_pop($rows);

        $this->summaryTable->setRows($rows);
        $this->showSummary();

        $this->showMenu();

        return self::SUCCESS;
    }

    /** @throws void */
    private function showSummary(): void
    {
        $this->summaryTable->render();
    }

    /** @throws void */
    private function changePropertyDiffTestSuite(): string
    {
        $questionHelper = $this->getHelper('question');

        if (count($this->options['tests']) > 1) {
            $question = new ChoiceQuestion(
                'Which Test Suite?',
                array_keys($this->options['tests']),
            );

            $selectedTest = $questionHelper->ask($this->input, $this->output, $question);
        } else {
            $selectedTest = array_keys($this->options['tests'])[0];
        }

        return $selectedTest;
    }

    /** @throws void */
    private function changePropertyDiffSection(): string
    {
        $questionHelper = $this->getHelper('question');

        $question = new ChoiceQuestion(
            'Which Section?',
            ['client', 'engine', 'platform', 'device'],
        );

        return $questionHelper->ask($this->input, $this->output, $question);
    }

    /** @throws void */
    private function changePropertyDiffProperty(string $section): string
    {
        $questionHelper = $this->getHelper('question');
        $subs           = [];

        switch ($section) {
            case 'client':
            case 'engine':
            case 'platform':
                $subs = ['name'];

                break;
            case 'device':
                $subs = ['name', 'brand', 'type'];

                break;
        }

        if (count($subs) > 1) {
            $question = new ChoiceQuestion('Which Property?', $subs);
            $property = $questionHelper->ask($this->input, $this->output, $question);
        } elseif (count($subs) === 1) {
            $property = reset($subs);
        } else {
            $property = 'name';
        }

        return $property;
    }

    /** @throws void */
    private function showMenu(): void
    {
        $questionHelper = $this->getHelper('question');
        $question       = new ChoiceQuestion(
            'What would you like to view?',
            ['Show Summary', 'View failure diff', 'View property comparison', 'Exit'],
            3,
        );

        $answer = $questionHelper->ask($this->input, $this->output, $question);

        switch ($answer) {
            case 'Show Summary':
                $this->showSummary();
                $this->showMenu();

                break;
            case 'View failure diff':
                $answer = '';

                do {
                    if (!isset($selectedTest) || $answer === 'Change Test Suite') {
                        if (count($this->options['tests']) > 1) {
                            $question = new ChoiceQuestion(
                                'Which test suite?',
                                array_keys($this->options['tests']),
                            );

                            $selectedTest = $questionHelper->ask(
                                $this->input,
                                $this->output,
                                $question,
                            );
                        } else {
                            $selectedTest = array_keys($this->options['tests'])[0];
                        }
                    }

                    if (!isset($selectedParser) || $answer === 'Change Parser') {
                        if (count($this->options['parsers']) > 1) {
                            $question = new ChoiceQuestion(
                                'Which parser?',
                                array_keys($this->options['parsers']),
                            );

                            $selectedParser = $questionHelper->ask(
                                $this->input,
                                $this->output,
                                $question,
                            );
                        } else {
                            $selectedParser = array_keys($this->options['parsers'])[0];
                        }
                    }

                    if (!isset($justAgents) || $answer === 'Show Full Diff') {
                        $justAgents = false;
                    } elseif ($answer === 'Show Just UserAgents') {
                        $justAgents = true;
                    }

                    $this->analyzeFailures($selectedTest, $selectedParser, $justAgents);

                    $justAgentsQuestion = 'Show Just UserAgents';

                    if ($justAgents === true) {
                        $justAgentsQuestion = 'Show Full Diff';
                    }

                    $questions = ['Change Test Suite', 'Change Parser', $justAgentsQuestion, 'Back to Main Menu'];

                    if (count($this->options['tests']) <= 1) {
                        unset($questions[array_search('Change Test Suite', $questions, true)]);
                    }

                    if (count($this->options['parsers']) <= 1) {
                        unset($questions[array_search('Change Parser', $questions, true)]);
                    }

                    // Re-index
                    $questions = array_values($questions);

                    $question = new ChoiceQuestion(
                        'What would you like to do?',
                        $questions,
                        count($questions) - 1,
                    );

                    $answer = $questionHelper->ask($this->input, $this->output, $question);
                } while ($answer !== 'Back to Main Menu');

                $this->showMenu();

                break;
            case 'View property comparison':
                $answer = '';

                do {
                    if (!isset($selectedTest) || $answer === 'Change Test Suite') {
                        $selectedTest = $this->changePropertyDiffTestSuite();
                    }

                    if (!isset($section) || $answer === 'Change Section') {
                        $section = $this->changePropertyDiffSection();
                    }

                    if (
                        !isset($property)
                        || $answer === 'Change Section'
                        || $answer === 'Change Property'
                    ) {
                        $property = $this->changePropertyDiffProperty($section);
                    }

                    if (!isset($justFails) || $answer === 'Show All') {
                        $justFails = false;
                    } elseif ($answer === 'Just Show Failures') {
                        $justFails = true;
                    }

                    $this->showComparison($selectedTest, $section, $property, $justFails);

                    $justFailureQuestion = 'Just Show Failures';

                    if ($justFails === true) {
                        $justFailureQuestion = 'Show All';
                    }

                    $questions = [
                        'Export User Agents',
                        'Change Section',
                        $justFailureQuestion,
                        'Back to Main Menu',
                    ];

                    if (count($this->options['tests']) >= 1) {
                        array_splice($questions, 1, 0, 'Change Test Suite');
                    }

                    if ($section === 'device') {
                        array_splice($questions, 2, 0, 'Change Property');
                    }

                    // Re-index
                    $questions = array_values($questions);

                    $question = new ChoiceQuestion(
                        'What would you like to do?',
                        $questions,
                        count($questions) - 1,
                    );

                    $answer = $questionHelper->ask($this->input, $this->output, $question);

                    if ($answer !== 'Export User Agents') {
                        continue;
                    }

                    $question     = new Question('Type the expected value to view the agents parsed:');
                    $autoComplete = array_merge(
                        ['[no value]'],
                        array_keys($this->comparison[$selectedTest][$section][$property]),
                    );
                    sort($autoComplete);
                    $question->setAutocompleterValues($autoComplete);

                    $value = $questionHelper->ask($this->input, $this->output, $question);

                    $this->showComparisonAgents($selectedTest, $section, $property, $value);

                    $question = new Question('Press enter to continue', 'yes');
                    $questionHelper->ask($this->input, $this->output, $question);
                } while ($answer !== 'Back to Main Menu');

                $this->showMenu();

                break;
            case 'Exit':
                $this->output->writeln('Goodbye!');

                break;
        }
    }

    /** @throws void */
    private function showComparisonAgents(string $test, string $section, string $property, string $value): void
    {
        if ($value === '[no value]') {
            $value = '';
        }

        if (!isset($this->comparison[$test][$section][$property][$value])) {
            $this->output->writeln(
                '<error>There were no agents processed with that property value</error>',
            );

            return;
        }

        $agents = array_flip($this->agents);

        $this->output->writeln(
            '<comment>Showing ' . count(
                $this->comparison[$test][$section][$property][$value]['expected']['agents'],
            ) . ' user agents</comment>',
        );

        $this->output->writeln('');

        foreach ($this->comparison[$test][$section][$property][$value]['expected']['agents'] as $agentId) {
            $this->output->writeln($agents[$agentId]);
        }

        $this->output->writeln('');
    }

    /** @throws void */
    private function analyzeFailures(string $test, string $parser, bool $justAgents = false): void
    {
        if (empty($this->failures[$test][$parser])) {
            $this->output->writeln(
                '<error>There were no failures for the ' . $parser . ' parser for the ' . $test . ' test suite</error>',
            );

            return;
        }

        $table = new Table($this->output);
        $table->setColumnWidth(0, 16);
        $table->setColumnMaxWidth(0, 16);
        $table->setColumnWidth(1, 19);
        $table->setColumnMaxWidth(1, 19);
        $table->setColumnWidth(2, 19);
        $table->setColumnMaxWidth(2, 19);
        $table->setColumnWidth(3, 16);
        $table->setColumnMaxWidth(3, 16);
        $table->setColumnWidth(4, 19);
        $table->setColumnMaxWidth(4, 19);
        $table->setColumnWidth(5, 19);
        $table->setColumnMaxWidth(5, 19);
        $table->setColumnWidth(6, 16);
        $table->setColumnMaxWidth(6, 16);
        $table->setColumnWidth(7, 19);
        $table->setColumnMaxWidth(7, 19);
        $table->setColumnWidth(8, 19);
        $table->setColumnMaxWidth(8, 19);
        $table->setColumnWidth(9, 16);
        $table->setColumnMaxWidth(9, 16);
        $table->setColumnWidth(10, 22);
        $table->setColumnMaxWidth(10, 22);
        $table->setColumnWidth(11, 22);
        $table->setColumnMaxWidth(11, 22);
        $table->setStyle('box');

        $htmlG = '<html><body><table style="border-collapse: collapse; border: 1px solid black;"><thead><tr><th colspan="12">UserAgent</th></tr><tr><th colspan="3">Client</th><th colspan="3">Engine</th><th colspan="3">Platform</th><th colspan="3">Device</th></tr></thead><tbody>';
        $htmlC = '<html><body><table style="border-collapse: collapse; border: 1px solid black;"><thead><tr><th colspan="3">UserAgent</th></tr><tr><th colspan="3">Client</th></tr></thead><tbody>';
        $htmlE = '<html><body><table style="border-collapse: collapse; border: 1px solid black;"><thead><tr><th colspan="3">UserAgent</th></tr><tr><th colspan="3">Engine</th></tr></thead><tbody>';
        $htmlP = '<html><body><table style="border-collapse: collapse; border: 1px solid black;"><thead><tr><th colspan="3">UserAgent</th></tr><tr><th colspan="3">Platform</th></tr></thead><tbody>';
        $htmlD = '<html><body><table style="border-collapse: collapse; border: 1px solid black;"><thead><tr><th colspan="3">UserAgent</th></tr><tr><th colspan="3">Device</th></tr></thead><tbody>';

        $table->setHeaders([
            [new TableCell('UserAgent', ['colspan' => 12])],
            [
                new TableCell('Client', ['colspan' => 3]),
                new TableCell('Engine', ['colspan' => 3]),
                new TableCell(
                    'Platform',
                    ['colspan' => 3],
                ),
                new TableCell(
                    'Device',
                    ['colspan' => 3],
                ),
            ],
        ]);

        $rows = [];

        foreach ($this->failures[$test][$parser] as $failData) {
            if (
                empty($failData['fail']['client'])
                && empty($failData['fail']['platform'])
                && empty($failData['fail']['device'])
                && empty($failData['fail']['engine'])
            ) {
                continue;
            }

            if ($justAgents === true) {
                foreach ($failData['headers'] as $header => $value) {
                    $this->output->writeln($header . ': ' . $value);
                }

                continue;
            }

            foreach ($failData['headers'] as $header => $value) {
                $rows[] = [
                    new TableCell(sprintf('<fg=blue;bg=white>%s</> ', $header)),
                    new TableCell(sprintf('<fg=blue;bg=white>%s</> ', $value), ['colspan' => 11]),
                ];

                $htmlG .= '<tr><td>' . $header . '</td><td colspan="11">' . $value . '</td></tr>';

                if (!empty($failData['fail']['client'])) {
                    $htmlC .= '<tr><td>' . $header . '</td><td colspan="2">' . $value . '</td></tr>';
                }

                if (!empty($failData['fail']['engine'])) {
                    $htmlE .= '<tr><td>' . $header . '</td><td colspan="2">' . $value . '</td></tr>';
                }

                if (!empty($failData['fail']['platform'])) {
                    $htmlP .= '<tr><td>' . $header . '</td><td colspan="2">' . $value . '</td></tr>';
                }

                if (empty($failData['fail']['device'])) {
                    continue;
                }

                $htmlD .= '<tr><td>' . $header . '</td><td colspan="2">' . $value . '</td></tr>';
            }

            $rows[] = new TableSeparator();

            $countDiffRows = 0;

            if (isset($failData['fail']['client'])) {
                $countDiffRows = max($countDiffRows, count($failData['fail']['client']));
            }

            if (isset($failData['fail']['engine'])) {
                $countDiffRows = max($countDiffRows, count($failData['fail']['engine']));
            }

            if (isset($failData['fail']['platform'])) {
                $countDiffRows = max($countDiffRows, count($failData['fail']['platform']));
            }

            if (isset($failData['fail']['device'])) {
                $countDiffRows = max($countDiffRows, count($failData['fail']['device']));
            }

            $clientDiffs   = array_keys($failData['fail']['client']);
            $engineDiffs   = array_keys($failData['fail']['engine']);
            $platformDiffs = array_keys($failData['fail']['platform']);
            $deviceDiffs   = array_keys($failData['fail']['device']);

            for ($diffRow = 0; $diffRow < $countDiffRows; ++$diffRow) {
                $columns = [];

                $htmlG .= '<tr>';

                if (array_key_exists($diffRow, $clientDiffs)) {
                    $field = $clientDiffs[$diffRow];
                    $data  = $failData['fail']['client'][$field];

                    $expectedBgColor = 'green';
                    $actualBgColor   = 'red';

                    $expected = $data['expected'] ?? null;

                    if ($expected === null) {
                        $expected        = '(null)';
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    } elseif ($expected === '') {
                        $expected        = '(empty)';
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    }

                    $actual = $data['actual'] ?? null;

                    if ($actual === null) {
                        $actual = '(null)';
                    } elseif ($actual === '') {
                        $actual = '(empty)';
                    }

                    $columns[] = new TableCell($field);

                    if ($actual === $expected) {
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    }

                    $columns[] = new TableCell(
                        sprintf('<fg=white;bg=%s>%s</> ', $expectedBgColor, $expected),
                    );
                    $columns[] = new TableCell(
                        sprintf('<fg=white;bg=%s>%s</> ', $actualBgColor, $actual),
                    );

                    $htmlC .= $this->outputDiffHtml2($field, $data, true);
                    $htmlG .= $this->outputDiffHtml2($field, $data);
                } else {
                    $columns[] = new TableCell('');
                    $columns[] = new TableCell('');
                    $columns[] = new TableCell('');

                    $htmlG .= '<td colspan="3"></td>';
                }

                if (array_key_exists($diffRow, $engineDiffs)) {
                    $field = $engineDiffs[$diffRow];
                    $data  = $failData['fail']['engine'][$field];

                    $expectedBgColor = 'green';
                    $actualBgColor   = 'red';

                    $expected = $data['expected'] ?? null;

                    if ($expected === null) {
                        $expected        = '(null)';
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    } elseif ($expected === '') {
                        $expected        = '(empty)';
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    }

                    $actual = $data['actual'] ?? null;

                    if ($actual === null) {
                        $actual = '(null)';
                    } elseif ($actual === '') {
                        $actual = '(empty)';
                    }

                    $columns[] = new TableCell($field);

                    if ($actual === $expected) {
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    }

                    $columns[] = new TableCell(
                        sprintf('<fg=white;bg=%s>%s</> ', $expectedBgColor, $expected),
                    );
                    $columns[] = new TableCell(
                        sprintf('<fg=white;bg=%s>%s</> ', $actualBgColor, $actual),
                    );

                    $htmlE .= $this->outputDiffHtml2($field, $data, true);
                    $htmlG .= $this->outputDiffHtml2($field, $data);
                } else {
                    $columns[] = new TableCell('');
                    $columns[] = new TableCell('');
                    $columns[] = new TableCell('');

                    $htmlG .= '<td colspan="3"></td>';
                }

                if (array_key_exists($diffRow, $platformDiffs)) {
                    $field = $platformDiffs[$diffRow];
                    $data  = $failData['fail']['platform'][$field];

                    $expectedBgColor = 'green';
                    $actualBgColor   = 'red';

                    $expected = $data['expected'] ?? null;

                    if ($expected === null) {
                        $expected        = '(null)';
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    } elseif ($expected === '') {
                        $expected        = '(empty)';
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    }

                    $actual = $data['actual'] ?? null;

                    if ($actual === null) {
                        $actual = '(null)';
                    } elseif ($actual === '') {
                        $actual = '(empty)';
                    }

                    $columns[] = new TableCell($field);

                    if ($actual === $expected) {
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    }

                    $columns[] = new TableCell(
                        sprintf('<fg=white;bg=%s>%s</> ', $expectedBgColor, $expected),
                    );
                    $columns[] = new TableCell(
                        sprintf('<fg=white;bg=%s>%s</> ', $actualBgColor, $actual),
                    );

                    $htmlP .= $this->outputDiffHtml2($field, $data, true);
                    $htmlG .= $this->outputDiffHtml2($field, $data);
                } else {
                    $columns[] = new TableCell('');
                    $columns[] = new TableCell('');
                    $columns[] = new TableCell('');

                    $htmlG .= '<td colspan="3"></td>';
                }

                if (array_key_exists($diffRow, $deviceDiffs)) {
                    $field = $deviceDiffs[$diffRow];
                    $data  = $failData['fail']['device'][$field];

                    $expectedBgColor = 'green';
                    $actualBgColor   = 'red';

                    $expected = $data['expected'] ?? null;

                    if ($expected === null) {
                        $expected        = '(null)';
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    } elseif ($expected === '') {
                        $expected        = '(empty)';
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    }

                    $actual = $data['actual'] ?? null;

                    if ($actual === null) {
                        $actual = '(null)';
                    } elseif ($actual === '') {
                        $actual = '(empty)';
                    }

                    $columns[] = new TableCell($field);

                    if ($actual === $expected) {
                        $expectedBgColor = 'black';
                        $actualBgColor   = 'black';
                    }

                    $columns[] = new TableCell(
                        sprintf('<fg=white;bg=%s>%s</> ', $expectedBgColor, $expected),
                    );
                    $columns[] = new TableCell(
                        sprintf('<fg=white;bg=%s>%s</> ', $actualBgColor, $actual),
                    );

                    $htmlD .= $this->outputDiffHtml2($field, $data, true);
                    $htmlG .= $this->outputDiffHtml2($field, $data);
                } else {
                    $columns[] = new TableCell('');
                    $columns[] = new TableCell('');
                    $columns[] = new TableCell('');

                    $htmlG .= '<td colspan="3"></td>';
                }

                $htmlG .= '</tr>';

                $rows[] = $columns;
            }

            $rows[] = new TableSeparator();
        }

        $htmlG .= '</tbody></table></body></html>';
        $htmlC .= '</tbody></table></body></html>';
        $htmlE .= '</tbody></table></body></html>';
        $htmlP .= '</tbody></table></body></html>';
        $htmlD .= '</tbody></table></body></html>';

        if ($justAgents !== false) {
            return;
        }

        array_pop($rows);

        $table->setRows($rows);

        $table->render();
        file_put_contents($this->runDir . '/errors-summary.html', $htmlG);
        file_put_contents($this->runDir . '/errors-browsers.html', $htmlC);
        file_put_contents($this->runDir . '/errors-engines.html', $htmlE);
        file_put_contents($this->runDir . '/errors-platforms.html', $htmlP);
        file_put_contents($this->runDir . '/errors-devices.html', $htmlD);
    }

    /** @throws void */
    private function showComparison(
        string $test,
        string $compareKey,
        string $compareSubKey,
        bool $justFails = false,
    ): void {
        if (empty($this->comparison[$test][$compareKey][$compareSubKey])) {
            return;
        }

        ksort($this->comparison[$test][$compareKey][$compareSubKey]);
        uasort(
            $this->comparison[$test][$compareKey][$compareSubKey],
            static fn (array $a, array $b): int => $b['expected']['count'] <=> $a['expected']['count'],
        );

        $table = new Table($this->output);

        $headers = [' Expected ' . ucfirst($compareKey) . ' ' . ucfirst($compareSubKey)];

        foreach (array_keys($this->options['parsers']) as $parser) {
            $headers[] = $parser;
        }

        $table->setHeaders($headers);

        $rows = [];

        foreach ($this->comparison[$test][$compareKey][$compareSubKey] as $expected => $compareRow) {
            if ($justFails === true && empty($compareRow['expected']['hasFailures'])) {
                continue;
            }

            $max = 0;

            foreach ($compareRow as $child) {
                if (count($child) <= $max) {
                    continue;
                }

                $max = count($child);
            }

            foreach (array_keys($this->options['parsers']) as $parser) {
                if (!isset($compareRow[$parser])) {
                    continue;
                }

                uasort(
                    $compareRow[$parser],
                    static fn (array $a, array $b): int => $b['count'] <=> $a['count'],
                );
            }

            for ($i = 0; $i < $max; ++$i) {
                $row     = [];
                $parsers = array_merge(['expected'], array_keys($this->options['parsers']));

                foreach ($parsers as $parser) {
                    if ($parser === 'expected') {
                        if ($i === 0) {
                            $row[] = ($expected === '' ? '[no value]' : $expected) . ' <comment>(' . $compareRow['expected']['count'] . ')</comment>';

                            continue;
                        }

                        $row[] = ' ';

                        continue;
                    }

                    if (isset($compareRow[$parser]) && count($compareRow[$parser]) > 0) {
                        $key      = current(array_keys($compareRow[$parser]));
                        $quantity = array_shift($compareRow[$parser]);

                        if ($key === $expected) {
                            $row[] = ($key === '' ? '[no value]' : $key) . ' <fg=green>(' . $quantity['count'] . ')</>';

                            continue;
                        }

                        if ($expected === '[n/a]' || $key === '[n/a]') {
                            $row[] = ($key === '' ? '[no value]' : $key) . ' <fg=blue>(' . $quantity['count'] . ')</>';

                            continue;
                        }

                        $row[] = ($key === '' ? '[no value]' : $key) . ' <fg=red>(' . $quantity['count'] . ')</>';

                        continue;
                    }

                    $row[] = ' ';
                }

                $rows[] = $row;
            }

            $rows[] = new TableSeparator();
        }

        array_pop($rows);

        $table->setRows($rows);
        $table->render();
    }

    /**
     * @param array<string, string|null> $expected
     * @param array<string, string|null> $actual
     *
     * @throws void
     */
    private function calculateScore(array $expected, array $actual, bool $possible = false): int
    {
        $score = 0;

        foreach ($expected as $field => $value) {
            if ($possible === true) {
                ++$score;

                continue;
            }

            if (!array_key_exists($field, $actual)) {
                continue;
            }

            if ($value !== null && $value !== $actual[$field]) {
                continue;
            }

            // this happens if our possible score calculation is called
            ++$score;
        }

        return $score;
    }

    /**
     * @param array<bool|int|string|null> $data
     *
     * @throws void
     */
    private function outputDiffHtml2(string $field, array $data, bool $withRow = false): string
    {
        if (empty($data)) {
            return '';
        }

        $expected = $data['expected'];

        $colorExpected = 'green';
        $colorActual   = 'red';

        if ($expected === null) {
            $expected      = '(null)';
            $colorExpected = 'gray';
            $colorActual   = 'gray';
        } elseif ($expected === '') {
            $expected      = '(empty)';
            $colorExpected = 'gray';
            $colorActual   = 'gray';
        }

        $actual = $data['actual'];

        if ($actual === null) {
            $actual = '(null)';
        } elseif ($actual === '') {
            $actual = '(empty)';
        }

        if ($expected === $actual) {
            $colorExpected = 'gray';
            $colorActual   = 'gray';
        }

        $content = '<td>' . $field . '</td><td><span style="background-color: ' . $colorExpected . '; color: white">' . $expected . '</span></td><td><span style="background-color: ' . $colorActual . '; color: white">' . $actual . '</span></td>';

        if ($withRow) {
            $content = '<tr>' . $content . '</tr>';
        }

        return $content;
    }

    /** @throws void */
    private function colorByPercent(float $percent): string
    {
        if ($percent >= 100.0) {
            return '<fg=bright-green;bg=black>';
        }

        if ($percent >= 95.0) {
            return '<fg=green;bg=black>';
        }

        if ($percent >= 90.0) {
            return '<fg=bright-yellow;bg=black>';
        }

        if ($percent >= 85.0) {
            return '<fg=yellow;bg=black>';
        }

        if ($percent < 50.0) {
            return '<fg=red;bg=black>';
        }

        return '<fg=white;bg=black>';
    }
}
