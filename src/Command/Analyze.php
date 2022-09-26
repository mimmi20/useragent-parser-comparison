<?php
/**
 * This file is part of the browser-detector-version package.
 *
 * Copyright (c) 2016-2022, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use FilesystemIterator;
use PDO;
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
    private array $options = [];
    private array $comparison = [];
    private array $agents = [];
    private Table | null $summaryTable = null;
    private InputInterface | null $input = null;
    private OutputInterface | null $output = null;
    private array $failures = [];

    public function __construct(private PDO $pdo)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('analyze')
            ->setDescription('Analyzes the data from test runs')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run directory that you want to analyze')
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input  = $input;
        $this->output = $output;

        $thisRunName = $input->getArgument('run');
        assert(is_string($thisRunName) || null === $thisRunName);

        if (empty($thisRunName)) {
            $testHelper = $this->getHelper('tests');
            assert($testHelper instanceof Tests);
            $thisRunName = $testHelper->getTest($input, $output);

            if (null === $thisRunName) {
                $output->writeln('<error>No valid test run found</error>');

                return self::FAILURE;
            }
        }

        if (!file_exists($this->runDir . '/' . $thisRunName)) {
            $output->writeln(sprintf('<error>No run directory found with that id (%s)</error>', $thisRunName));

            return self::FAILURE;
        }

        $metaDataFile = $this->runDir . '/' . $thisRunName . '/metadata.json';

        if (!file_exists($metaDataFile)) {
            $output->writeln(sprintf('<error>No options file found for run (%s)</error>', $thisRunName));

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
            $output->writeln('<error>An error occured while parsing metadata for run ' . $thisRunName . '</error>');

            return self::INVALID;
        }

        $statementSelectResultRun = $this->pdo->prepare('SELECT `result`.* FROM `result` WHERE `result`.`run` = :run');
        $statementSelectResultRun->bindValue(':run', $thisRunName, PDO::PARAM_STR);
        $statementSelectResultRun->execute();

        $statementSelectResultSource = $this->pdo->prepare('SELECT `result`.* FROM `result` WHERE `result`.`run` = :run AND `result`.`userAgent_id` = :uaId');

        $normalizeHelper = $this->getHelper('normalize');
        assert($normalizeHelper instanceof Helper\Normalize);

        $resultHelper = $this->getHelper('normalized-result');
        assert($resultHelper instanceof Helper\NormalizedResult);

        $output->writeln(sprintf('<info>Analyzing data from test run: %s</info>', $thisRunName));

        if (empty($this->options['tests']) && empty($this->options['file'])) {
            $output->writeln(sprintf('<error>Error in options file for run (%s)</error>', $thisRunName));

            return self::FAILURE;
        }

        if (empty($this->options['tests'])) {
            $this->options['tests'] = [
                $this->options['file'] => [],
            ];
        }

        $this->summaryTable = new Table($output);

        $rows   = [];
        $totals = [];

        $headerStyle = new TableCellStyle([
            'align' => 'center',
            'fg' => 'green',
        ]);

        $rows[] = [
            new TableCell(
                'Parser',
                [
                    'rowspan' => 2,
                    'style' => $headerStyle,
                ],
            ),
            new TableCell(
                'Version',
                [
                    'rowspan' => 2,
                    'style' => $headerStyle,
                ],
            ),
            new TableCell(
                'Client Results',
                [
                    'colspan' => 2,
                    'style' => $headerStyle,
                ],
            ),
            new TableCell(
                'Engine Results',
                [
                    'colspan' => 2,
                    'style' => $headerStyle,
                ],
            ),
            new TableCell(
                'Platform Results',
                [
                    'colspan' => 2,
                    'style' => $headerStyle,
                ],
            ),
            new TableCell(
                'Device Results',
                [
                    'colspan' => 2,
                    'style' => $headerStyle,
                ],
            ),
            new TableCell(
                'Time',
                [
                    'colspan' => 2,
                    'style' => $headerStyle,
                ],
            ),
            new TableCell(
                'Score',
                [
                    'colspan' => 2,
                    'style' => $headerStyle,
                ],
            ),
        ];
        $rows[] = [
            new TableCell('Result', ['style' => $headerStyle]),
            new TableCell('Correct', ['style' => $headerStyle]),
            new TableCell('Result', ['style' => $headerStyle]),
            new TableCell('Correct', ['style' => $headerStyle]),
            new TableCell('Result', ['style' => $headerStyle]),
            new TableCell('Correct', ['style' => $headerStyle]),
            new TableCell('Result', ['style' => $headerStyle]),
            new TableCell('Correct', ['style' => $headerStyle]),
            new TableCell('Init', ['style' => $headerStyle]),
            new TableCell('Parsing', ['style' => $headerStyle]),
            new TableCell('Test', ['style' => $headerStyle]),
            new TableCell('Accuracy', ['style' => $headerStyle]),
        ];

        while ($runRow = $statementSelectResultRun->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
            $statementSelectResultSource->bindValue(':run', '0', PDO::PARAM_STR);
            $statementSelectResultSource->bindValue(':uaId', $runRow['userAgent_id'], PDO::PARAM_STR);

            $statementSelectResultSource->execute();

            $sourceRow = $statementSelectResultSource->fetch(PDO::FETCH_ASSOC);

            if (false === $sourceRow) {
                $output->writeln(sprintf('<error>Analyzing data from test run: %s - source for UA "%s" not found</error>', $thisRunName, $runRow['userAgent_id']));

                continue;
            }

            $headerMessage = sprintf('Parser comparison for <fg=yellow>%s%s</>', $testData['metadata']['name'], isset($testData['metadata']['version']) ? ' (' . $testData['metadata']['version'] . ')' : '');
        }

        foreach ($this->options['tests'] as $testSuite => $testData) {
            $this->comparison[$testSuite] = [];

            $expectedFilename = $this->runDir . '/' . $thisRunName . '/expected/normalized/' . $testSuite;
            $expectedResults  = ['tests' => []];

            if (file_exists($expectedFilename)) {
                foreach (new FilesystemIterator($this->runDir . '/' . $thisRunName . '/expected/normalized/' . $testSuite) as $testFile) {
                    assert($testFile instanceof SplFileInfo);
                    if ($testFile->isDir() || 'metadata.json' === $testFile->getFilename()) {
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
                        $output->writeln("\r" . $message . '<error>An error occured while normalizing test suite ' . $testFile->getFilename() . '</error>');

                        continue;
                    }

                    $singleTestName = $testFile->getBasename('.' . $testFile->getExtension());

                    $expectedResults['tests'][$singleTestName] = $data['test'];
                }

                $headerMessage = sprintf('Parser comparison for <fg=yellow>%s%s</>', $testData['metadata']['name'], isset($testData['metadata']['version']) ? ' (' . $testData['metadata']['version'] . ')' : '');
            } else {
                // When we aren't comparing to a test suite, the first parser's results become the expected results

                $fileName = $this->runDir . '/' . $thisRunName . '/results/' . array_keys($this->options['parsers'])[0] . '/normalized/' . $testSuite . '.json';
                try {
                    $contents = file_get_contents($fileName);
                } catch (Throwable) {
                    $this->output->writeln('<error>Could not read file (' . $fileName . ')</error>');

                    continue;
                }

                try {
                    $testResult    = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    $headerMessage = sprintf('<fg=yellow>Parser comparison for %s file, using %s results as expected</>', $testSuite, array_keys($this->options['parsers'])[0]);
                } catch (Throwable) {
                    $this->output->writeln(sprintf('<error>An error occured while parsing metadata for run %s, skipping</error>', $thisRunName));

                    continue;
                }

                foreach ($testResult['results'] as $data) {
                    $expectedResults['tests'][$data['useragent']] = $data['parsed'];
                }
            }

            if (!isset($expectedResults['tests']) || !is_array($expectedResults['tests']) || empty($expectedResults['tests'])) {
                continue;
            }

            $rows[] = new TableSeparator();
            $rows[] = [new TableCell($headerMessage, ['colspan' => 13])];
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

                foreach (new FilesystemIterator($this->runDir . '/' . $thisRunName . '/results/' . $parserName . '/normalized/' . $testSuite) as $resultFile) {
                    try {
                        $contents = file_get_contents($resultFile->getPathname());
                    } catch (Throwable) {
                        $this->output->writeln(sprintf('<error>Could not read file (%s), skipping</error>', $resultFile->getPathname()));

                        continue;
                    }

                    try {
                        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    } catch (Throwable) {
                        $this->output->writeln(sprintf('<error>An error occured while parsing file (%s), skipping</error>', $resultFile->getPathname()));

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

                    foreach (['client', 'platform', 'device', 'engine'] as $compareKey) {
                        if (!array_key_exists($compareKey, $expected) || !array_key_exists($compareKey, $data['parsed'])) {
                            continue;
                        }

                        $score         = $this->calculateScore($expected[$compareKey], $data['parsed'][$compareKey]);
                        $possibleScore = $this->calculateScore($expected[$compareKey], $data['parsed'][$compareKey], true);

                        $passFail[$compareKey]['count'] += count($expected[$compareKey]);
                        $passFail[$compareKey]['pass']  += $score;
                        $passFail[$compareKey]['fail']  += $possibleScore - $score;

                        $filtered = array_filter(
                            $expected[$compareKey],
                            static fn (mixed $value): bool => null !== $value,
                        );

                        $scores[$parserName][$testSuite]['count'] += count($filtered);
                        $scores[$parserName][$testSuite]['pass']  += $score;
                        $scores[$parserName][$testSuite]['fail']  += $possibleScore - $score;
                    }

                    $this->comparison[$testSuite] = $comparison->getComparison($parserName, $this->agents[$singleTestName] ?? 0);
                    $failures                     = $comparison->getFailures();

                    if (empty($failures)) {
                        continue;
                    }

                    $this->failures[$testSuite][$parserName][$singleTestName] = [
                        'headers' => $data['headers'],
                        'fail' => $failures,
                    ];
                }

                if (0 === $passFail['client']['pass'] + $passFail['client']['fail']) {
                    $clientTContent = '<fg=white;bg=blue>-</>';
                    $clientAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $clientTPercentage = ($passFail['client']['pass'] + $passFail['client']['fail']) / $passFail['client']['count'] * 100;
                    $clientTContent    = $this->colorByPercent($clientTPercentage) . ($passFail['client']['pass'] + $passFail['client']['fail']) . '/' . $passFail['client']['count'] . ' ' . number_format($clientTPercentage, 2) . '%</>';

                    $clientAPercentage = $passFail['client']['pass'] / ($passFail['client']['pass'] + $passFail['client']['fail']) * 100;
                    $clientAContent    = $this->colorByPercent($clientAPercentage) . $passFail['client']['pass'] . '/' . ($passFail['client']['pass'] + $passFail['client']['fail']) . ' ' . number_format($clientAPercentage, 2) . '%</>';
                }

                if (0 === $passFail['engine']['pass'] + $passFail['engine']['fail']) {
                    $engineTContent = '<fg=white;bg=blue>-</>';
                    $engineAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $engineTPercentage = ($passFail['engine']['pass'] + $passFail['engine']['fail']) / $passFail['engine']['count'] * 100;
                    $engineTContent    = $this->colorByPercent($engineTPercentage) . ($passFail['engine']['pass'] + $passFail['engine']['fail']) . '/' . $passFail['engine']['count'] . ' ' . number_format($engineTPercentage, 2) . '%</>';

                    $engineAPercentage = $passFail['engine']['pass'] / ($passFail['engine']['pass'] + $passFail['engine']['fail']) * 100;
                    $engineAContent    = $this->colorByPercent($engineAPercentage) . $passFail['engine']['pass'] . '/' . ($passFail['engine']['pass'] + $passFail['engine']['fail']) . ' ' . number_format($engineAPercentage, 2) . '%</>';
                }

                if (0 === $passFail['platform']['pass'] + $passFail['platform']['fail']) {
                    $platformTContent = '<fg=white;bg=blue>-</>';
                    $platformAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $platformTPercentage = ($passFail['platform']['pass'] + $passFail['platform']['fail']) / $passFail['platform']['count'] * 100;
                    $platformTContent    = $this->colorByPercent($platformTPercentage) . ($passFail['platform']['pass'] + $passFail['platform']['fail']) . '/' . $passFail['platform']['count'] . ' ' . number_format($platformTPercentage, 2) . '%</>';

                    $platformAPercentage = $passFail['platform']['pass'] / ($passFail['platform']['pass'] + $passFail['platform']['fail']) * 100;
                    $platformAContent    = $this->colorByPercent($platformAPercentage) . $passFail['platform']['pass'] . '/' . ($passFail['platform']['pass'] + $passFail['platform']['fail']) . ' ' . number_format($platformAPercentage, 2) . '%</>';
                }

                if (0 === $passFail['device']['pass'] + $passFail['device']['fail']) {
                    $deviceTContent = '<fg=white;bg=blue>-</>';
                    $deviceAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $deviceTPercentage = ($passFail['device']['pass'] + $passFail['device']['fail']) / $passFail['device']['count'] * 100;
                    $deviceTContent    = $this->colorByPercent($deviceTPercentage) . ($passFail['device']['pass'] + $passFail['device']['fail']) . '/' . $passFail['device']['count'] . ' ' . number_format($deviceTPercentage, 2) . '%</>';

                    $deviceAPercentage = $passFail['device']['pass'] / ($passFail['device']['pass'] + $passFail['device']['fail']) * 100;
                    $deviceAContent    = $this->colorByPercent($deviceAPercentage) . $passFail['device']['pass'] . '/' . ($passFail['device']['pass'] + $passFail['device']['fail']) . ' ' . number_format($deviceAPercentage, 2) . '%</>';
                }

                if (0 === $scores[$parserName][$testSuite]['pass'] + $scores[$parserName][$testSuite]['fail']) {
                    $summaryTContent = '<fg=white;bg=blue>-</>';
                    $summaryAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $summaryTPercentage = ($scores[$parserName][$testSuite]['pass'] + $scores[$parserName][$testSuite]['fail']) / $scores[$parserName][$testSuite]['count'] * 100;
                    $summaryTContent    = $this->colorByPercent($summaryTPercentage) . ($scores[$parserName][$testSuite]['pass'] + $scores[$parserName][$testSuite]['fail']) . '/' . $scores[$parserName][$testSuite]['count'] . ' ' . number_format($summaryTPercentage, 2) . '%</>';

                    $summaryAPercentage = $scores[$parserName][$testSuite]['pass'] / ($scores[$parserName][$testSuite]['pass'] + $scores[$parserName][$testSuite]['fail']) * 100;
                    $summaryAContent    = $this->colorByPercent($summaryAPercentage) . $scores[$parserName][$testSuite]['pass'] . '/' . ($scores[$parserName][$testSuite]['pass'] + $scores[$parserName][$testSuite]['fail']) . ' ' . number_format($summaryAPercentage, 2) . '%</>';
                }

                $rows[] = [
                    $parserData['metadata']['name'],
                    $parserData['metadata']['version'] ?? 'n/a',
                    $clientTContent,
                    $clientAContent,
                    $engineTContent,
                    $engineAContent,
                    $platformTContent,
                    $platformAContent,
                    $deviceTContent,
                    $deviceAContent,
                    number_format($initTime, 3) . 's',
                    number_format($parseTime, 3) . 's',
                    $summaryTContent,
                    $summaryAContent,
                ];

                if (!isset($totals[$parserName])) {
                    $totals[$parserName] = [
                        'client' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                        'engine' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                        'platform' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                        'device' => ['count' => 0, 'pass' => 0, 'fail' => 0],
                        'time' => 0,
                        'init' => 0,
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
                $totals[$parserName]['score']['count']    += $scores[$parserName][$testSuite]['count'];
                $totals[$parserName]['score']['pass']     += $scores[$parserName][$testSuite]['pass'];
                $totals[$parserName]['score']['fail']     += $scores[$parserName][$testSuite]['fail'];
            }

            $rows[] = new TableSeparator();
        }

        if (1 < count($this->options['tests'])) {
            $rows[] = [new TableCell('<fg=yellow>Total for all Test suites</>', ['colspan' => 13])];
            $rows[] = new TableSeparator();

            foreach ($totals as $parser => $total) {
                if (0 === $total['client']['pass'] + $total['client']['fail']) {
                    $clientTContent = '<fg=white;bg=blue>-</>';
                    $clientAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $clientTPercentage = ($total['client']['pass'] + $total['client']['fail']) / $total['client']['count'] * 100;
                    $clientTContent    = $this->colorByPercent($clientTPercentage) . ($total['client']['pass'] + $total['client']['fail']) . '/' . $total['client']['count'] . ' ' . number_format($clientTPercentage, 2) . '%</>';

                    $clientAPercentage = $total['client']['pass'] / ($total['client']['pass'] + $total['client']['fail']) * 100;
                    $clientAContent    = $this->colorByPercent($clientAPercentage) . $total['client']['pass'] . '/' . ($total['client']['pass'] + $total['client']['fail']) . ' ' . number_format($clientAPercentage, 2) . '%</>';
                }

                if (0 === $total['engine']['pass'] + $total['engine']['fail']) {
                    $engineTContent = '<fg=white;bg=blue>-</>';
                    $engineAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $engineTPercentage = ($total['engine']['pass'] + $total['engine']['fail']) / $total['engine']['count'] * 100;
                    $engineTContent    = $this->colorByPercent($engineTPercentage) . ($total['engine']['pass'] + $total['engine']['fail']) . '/' . $total['engine']['count'] . ' ' . number_format($engineTPercentage, 2) . '%</>';

                    $engineAPercentage = $total['engine']['pass'] / ($total['engine']['pass'] + $total['engine']['fail']) * 100;
                    $engineAContent    = $this->colorByPercent($engineAPercentage) . $total['engine']['pass'] . '/' . ($total['engine']['pass'] + $total['engine']['fail']) . ' ' . number_format($engineAPercentage, 2) . '%</>';
                }

                if (0 === $total['platform']['pass'] + $total['platform']['fail']) {
                    $platformTContent = '<fg=white;bg=blue>-</>';
                    $platformAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $platformTPercentage = ($total['platform']['pass'] + $total['platform']['fail']) / $total['platform']['count'] * 100;
                    $platformTContent    = $this->colorByPercent($platformTPercentage) . ($total['platform']['pass'] + $total['platform']['fail']) . '/' . $total['platform']['count'] . ' ' . number_format($platformTPercentage, 2) . '%</>';

                    $platformAPercentage = $total['platform']['pass'] / ($total['platform']['pass'] + $total['platform']['fail']) * 100;
                    $platformAContent    = $this->colorByPercent($platformAPercentage) . $total['platform']['pass'] . '/' . ($total['platform']['pass'] + $total['platform']['fail']) . ' ' . number_format($platformAPercentage, 2) . '%</>';
                }

                if (0 === $total['device']['pass'] + $total['device']['fail']) {
                    $deviceTContent = '<fg=white;bg=blue>-</>';
                    $deviceAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $deviceTPercentage = ($total['device']['pass'] + $total['device']['fail']) / $total['device']['count'] * 100;
                    $deviceTContent    = $this->colorByPercent($deviceTPercentage) . ($total['device']['pass'] + $total['device']['fail']) . '/' . $total['device']['count'] . ' ' . number_format($deviceTPercentage, 2) . '%</>';

                    $deviceAPercentage = $total['device']['pass'] / ($total['device']['pass'] + $total['device']['fail']) * 100;
                    $deviceAContent    = $this->colorByPercent($deviceAPercentage) . $total['device']['pass'] . '/' . ($total['device']['pass'] + $total['device']['fail']) . ' ' . number_format($deviceAPercentage, 2) . '%</>';
                }

                if (0 === $total['score']['pass'] + $total['score']['fail']) {
                    $summaryTContent = '<fg=white;bg=blue>-</>';
                    $summaryAContent = '<fg=white;bg=blue>-</>';
                } else {
                    $summaryTPercentage = ($total['score']['pass'] + $total['score']['fail']) / $total['score']['count'] * 100;
                    $summaryTContent    = $this->colorByPercent($summaryTPercentage) . ($total['score']['pass'] + $total['score']['fail']) . '/' . $total['score']['count'] . ' ' . number_format($summaryTPercentage, 2) . '%</>';

                    $summaryAPercentage = $total['score']['pass'] / ($total['score']['pass'] + $total['score']['fail']) * 100;
                    $summaryAContent    = $this->colorByPercent($summaryAPercentage) . $total['score']['pass'] . '/' . ($total['score']['pass'] + $total['score']['fail']) . ' ' . number_format($summaryAPercentage, 2) . '%</>';
                }

                $rows[] = [
                    $parser,
                    $this->options['parsers'][$parser]['metadata']['version'] ?? 'n/a',
                    $clientTContent,
                    $clientAContent,
                    $engineTContent,
                    $engineAContent,
                    $platformTContent,
                    $platformAContent,
                    $deviceTContent,
                    $deviceAContent,
                    number_format($total['init'], 3) . 's',
                    number_format($total['time'], 3) . 's',
                    $summaryTContent,
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

    private function showSummary(): void
    {
        $this->summaryTable->render();
    }

    private function changePropertyDiffTestSuite(): string
    {
        $questionHelper = $this->getHelper('question');

        if (1 < count($this->options['tests'])) {
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

    private function changePropertyDiffSection(): string
    {
        $questionHelper = $this->getHelper('question');

        $question = new ChoiceQuestion(
            'Which Section?',
            ['client', 'engine', 'platform', 'device'],
        );

        return $questionHelper->ask($this->input, $this->output, $question);
    }

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

        if (1 < count($subs)) {
            $question = new ChoiceQuestion(
                'Which Property?',
                $subs,
            );
            $property = $questionHelper->ask($this->input, $this->output, $question);
        } elseif (1 === count($subs)) {
            $property = reset($subs);
        } else {
            $property = 'name';
        }

        return $property;
    }

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
                    if (!isset($selectedTest) || 'Change Test Suite' === $answer) {
                        if (1 < count($this->options['tests'])) {
                            $question = new ChoiceQuestion(
                                'Which test suite?',
                                array_keys($this->options['tests']),
                            );

                            $selectedTest = $questionHelper->ask($this->input, $this->output, $question);
                        } else {
                            $selectedTest = array_keys($this->options['tests'])[0];
                        }
                    }

                    if (!isset($selectedParser) || 'Change Parser' === $answer) {
                        if (1 < count($this->options['parsers'])) {
                            $question = new ChoiceQuestion(
                                'Which parser?',
                                array_keys($this->options['parsers']),
                            );

                            $selectedParser = $questionHelper->ask($this->input, $this->output, $question);
                        } else {
                            $selectedParser = array_keys($this->options['parsers'])[0];
                        }
                    }

                    if (!isset($justAgents) || 'Show Full Diff' === $answer) {
                        $justAgents = false;
                    } elseif ('Show Just UserAgents' === $answer) {
                        $justAgents = true;
                    }

                    $this->analyzeFailures($selectedTest, $selectedParser, $justAgents);

                    $justAgentsQuestion = 'Show Just UserAgents';
                    if (true === $justAgents) {
                        $justAgentsQuestion = 'Show Full Diff';
                    }

                    $questions = ['Change Test Suite', 'Change Parser', $justAgentsQuestion, 'Back to Main Menu'];

                    if (1 >= count($this->options['tests'])) {
                        unset($questions[array_search('Change Test Suite', $questions, true)]);
                    }

                    if (1 >= count($this->options['parsers'])) {
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
                } while ('Back to Main Menu' !== $answer);

                $this->showMenu();

                break;
            case 'View property comparison':
                $answer = '';
                do {
                    if (!isset($selectedTest) || 'Change Test Suite' === $answer) {
                        $selectedTest = $this->changePropertyDiffTestSuite();
                    }

                    if (!isset($section) || 'Change Section' === $answer) {
                        $section = $this->changePropertyDiffSection();
                    }

                    if (!isset($property) || 'Change Section' === $answer || 'Change Property' === $answer) {
                        $property = $this->changePropertyDiffProperty($section);
                    }

                    if (!isset($justFails) || 'Show All' === $answer) {
                        $justFails = false;
                    } elseif ('Just Show Failures' === $answer) {
                        $justFails = true;
                    }

                    $this->showComparison($selectedTest, $section, $property, $justFails);

                    $justFailureQuestion = 'Just Show Failures';
                    if (true === $justFails) {
                        $justFailureQuestion = 'Show All';
                    }

                    $questions = [
                        'Export User Agents',
                        'Change Section',
                        $justFailureQuestion,
                        'Back to Main Menu',
                    ];

                    if (1 <= count($this->options['tests'])) {
                        array_splice($questions, 1, 0, 'Change Test Suite');
                    }

                    if ('device' === $section) {
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

                    if ('Export User Agents' !== $answer) {
                        continue;
                    }

                    $question     = new Question('Type the expected value to view the agents parsed:');
                    $autoComplete = array_merge(['[no value]'], array_keys($this->comparison[$selectedTest][$section][$property]));
                    sort($autoComplete);
                    $question->setAutocompleterValues($autoComplete);

                    $value = $questionHelper->ask($this->input, $this->output, $question);

                    $this->showComparisonAgents($selectedTest, $section, $property, $value);

                    $question = new Question('Press enter to continue', 'yes');
                    $questionHelper->ask($this->input, $this->output, $question);
                } while ('Back to Main Menu' !== $answer);

                $this->showMenu();

                break;
            case 'Exit':
                $this->output->writeln('Goodbye!');

                break;
        }
    }

    private function showComparisonAgents(string $test, string $section, string $property, string $value): void
    {
        if ('[no value]' === $value) {
            $value = '';
        }

        if (!isset($this->comparison[$test][$section][$property][$value])) {
            $this->output->writeln('<error>There were no agents processed with that property value</error>');

            return;
        }

        $agents = array_flip($this->agents);

        $this->output->writeln('<comment>Showing ' . count($this->comparison[$test][$section][$property][$value]['expected']['agents']) . ' user agents</comment>');

        $this->output->writeln('');
        foreach ($this->comparison[$test][$section][$property][$value]['expected']['agents'] as $agentId) {
            $this->output->writeln($agents[$agentId]);
        }

        $this->output->writeln('');
    }

    private function analyzeFailures(string $test, string $parser, bool $justAgents = false): void
    {
        if (empty($this->failures[$test][$parser])) {
            $this->output->writeln(
                '<error>There were no failures for the ' . $parser . ' parser for the ' . $test . ' test suite</error>',
            );

            return;
        }

        $table = new Table($this->output);
        $table->setColumnWidth(0, 50);
        $table->setColumnMaxWidth(0, 50);
        $table->setColumnWidth(1, 50);
        $table->setColumnMaxWidth(1, 50);
        $table->setColumnWidth(2, 50);
        $table->setColumnMaxWidth(2, 50);
        $table->setColumnWidth(3, 50);
        $table->setColumnMaxWidth(3, 50);
        $table->setStyle('box');

        $htmlG = '<html><body><table><colgroup><col span="4" style="width: 25%"></colgroup><thead><tr><th colspan="3">UserAgent</th></tr><tr><th>Client</th><th>Engine</th><th>Platform</th><th>Device</th></tr></thead><tbody>';
        $htmlC = '<html><body><table><thead><tr><th>UserAgent</th></tr><tr><th>Client</th></tr></thead><tbody>';
        $htmlE = '<html><body><table><thead><tr><th>UserAgent</th></tr><tr><th>Engine</th></tr></thead><tbody>';
        $htmlP = '<html><body><table><thead><tr><th>UserAgent</th></tr><tr><th>Platform</th></tr></thead><tbody>';
        $htmlD = '<html><body><table><thead><tr><th>UserAgent</th></tr><tr><th>Device</th></tr></thead><tbody>';

        $table->setHeaders([
            [new TableCell('UserAgent', ['colspan' => 4])],
            [new TableCell('Client'), new TableCell('Engine'), new TableCell('Platform'), new TableCell('Device')],
        ]);

        $rows = [];
        foreach ($this->failures[$test][$parser] as $singleTestName => $failData) {
            if (empty($failData['fail']['client']) && empty($failData['fail']['platform']) && empty($failData['fail']['device']) && empty($failData['fail']['engine'])) {
                continue;
            }

            if (true === $justAgents) {
                foreach ($failData['headers'] as $header => $value) {
                    $this->output->writeln($header . ': ' . $value);
                }

                continue;
            }

            foreach ($failData['headers'] as $header => $value) {
                $rows[] = [new TableCell($header . ': ' . $value, ['colspan' => 4])];
            }

            $rows[] = [
                new TableCell(isset($failData['fail']['client']) ? $this->outputDiff($failData['fail']['client']) : ''),
                new TableCell(isset($failData['fail']['engine']) ? $this->outputDiff($failData['fail']['engine']) : ''),
                new TableCell(isset($failData['fail']['platform']) ? $this->outputDiff($failData['fail']['platform']) : ''),
                new TableCell(isset($failData['fail']['device']) ? $this->outputDiff($failData['fail']['device']) : ''),
            ];
            $rows[] = new TableSeparator();

            $htmlG .= '<tr><td colspan="4">';

            foreach ($failData['headers'] as $header => $value) {
                $htmlG .= $header . ': ' . $value . '<br/>';
            }

            $htmlG .= '</td></tr>';
            $htmlG .= '<tr><td>' . (!empty($failData['fail']['client']) ? $this->outputDiffHtml($failData['fail']['client']) : '') . '</td><td>' . (!empty($failData['fail']['engine']) ? $this->outputDiffHtml($failData['fail']['engine']) : '') . '</td><td>' . (!empty($failData['fail']['platform']) ? $this->outputDiffHtml($failData['fail']['platform']) : '') . '</td><td>' . (!empty($failData['fail']['device']) ? $this->outputDiffHtml($failData['fail']['device']) : '') . '</td></tr>';

            if (!empty($failData['fail']['client'])) {
                $htmlC .= '<tr><td>';

                foreach ($failData['headers'] as $header => $value) {
                    $htmlC .= $header . ': ' . $value . '<br/>';
                }

                $htmlC .= '</td></tr>';
                $htmlC .= '<tr><td>' . $this->outputDiffHtml($failData['fail']['client']) . '</td></tr>';
            }

            if (!empty($failData['fail']['platform'])) {
                $htmlP .= '<tr><td>';

                foreach ($failData['headers'] as $header => $value) {
                    $htmlP .= $header . ': ' . $value . '<br/>';
                }

                $htmlP .= '</td></tr>';
                $htmlP .= '<tr><td>' . $this->outputDiffHtml($failData['fail']['platform']) . '</td></tr>';
            }

            if (!empty($failData['fail']['device'])) {
                $htmlD .= '<tr><td>';

                foreach ($failData['headers'] as $header => $value) {
                    $htmlD .= $header . ': ' . $value . '<br/>';
                }

                $htmlD .= '</td></tr>';
                $htmlD .= '<tr><td>' . $this->outputDiffHtml($failData['fail']['device']) . '</td></tr>';
            }

            if (empty($failData['fail']['engine'])) {
                continue;
            }

            $htmlE .= '<tr><td>';

            foreach ($failData['headers'] as $header => $value) {
                $htmlE .= $header . ': ' . $value . '<br/>';
            }

            $htmlE .= '</td></tr>';
            $htmlE .= '<tr><td>' . $this->outputDiffHtml($failData['fail']['engine']) . '</td></tr>';
        }

        $htmlG .= '</tbody></table></body></html>';
        $htmlC .= '</tbody></table></body></html>';
        $htmlE .= '</tbody></table></body></html>';
        $htmlP .= '</tbody></table></body></html>';
        $htmlD .= '</tbody></table></body></html>';

        if (false !== $justAgents) {
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

    private function showComparison(string $test, string $compareKey, string $compareSubKey, bool $justFails = false): void
    {
        if (empty($this->comparison[$test][$compareKey][$compareSubKey])) {
            return;
        }

        ksort($this->comparison[$test][$compareKey][$compareSubKey]);
        uasort($this->comparison[$test][$compareKey][$compareSubKey], static function (array $a, array $b): int {
            if ($a['expected']['count'] === $b['expected']['count']) {
                return 0;
            }

            return $a['expected']['count'] > $b['expected']['count'] ? -1 : 1;
        });

        $table = new Table($this->output);

        $headers = [' Expected ' . ucfirst($compareKey) . ' ' . ucfirst($compareSubKey)];

        foreach (array_keys($this->options['parsers']) as $parser) {
            $headers[] = $parser;
        }

        $table->setHeaders($headers);

        $rows = [];

        foreach ($this->comparison[$test][$compareKey][$compareSubKey] as $expected => $compareRow) {
            if (true === $justFails && empty($compareRow['expected']['hasFailures'])) {
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

                uasort($compareRow[$parser], static function (array $a, array $b): int {
                    if ($a['count'] === $b['count']) {
                        return 0;
                    }

                    return $a['count'] > $b['count'] ? -1 : 1;
                });
            }

            for ($i = 0; $i < $max; ++$i) {
                $row     = [];
                $parsers = array_merge(['expected'], array_keys($this->options['parsers']));

                foreach ($parsers as $parser) {
                    if ('expected' === $parser) {
                        if (0 === $i) {
                            $row[] = ('' === $expected ? '[no value]' : $expected) . ' <comment>(' . $compareRow['expected']['count'] . ')</comment>';
                        } else {
                            $row[] = ' ';
                        }
                    } else {
                        if (isset($compareRow[$parser]) && 0 < count($compareRow[$parser])) {
                            $key      = current(array_keys($compareRow[$parser]));
                            $quantity = array_shift($compareRow[$parser]);

                            if ($key === $expected) {
                                $row[] = ('' === $key ? '[no value]' : $key) . ' <fg=green>(' . $quantity['count'] . ')</>';
                            } elseif ('[n/a]' === $expected || '[n/a]' === $key) {
                                $row[] = ('' === $key ? '[no value]' : $key) . ' <fg=blue>(' . $quantity['count'] . ')</>';
                            } else {
                                $row[] = ('' === $key ? '[no value]' : $key) . ' <fg=red>(' . $quantity['count'] . ')</>';
                            }
                        } else {
                            $row[] = ' ';
                        }
                    }
                }

                $rows[] = $row;
            }

            $rows[] = new TableSeparator();
        }

        array_pop($rows);

        $table->setRows($rows);
        $table->render();
    }

    private function calculateScore(array $expected, array $actual, bool $possible = false): int
    {
        $score = 0;

        foreach ($expected as $field => $value) {
            if (null === $value || !array_key_exists($field, $actual)) {
                continue;
            }

            // this happens if our possible score calculation is called
            if (true === $possible && null !== $actual[$field]) {
                ++$score;
            } elseif ($value === $actual[$field]) {
                ++$score;
            }
        }

        return $score;
    }

    private function outputDiff(array $diff): string
    {
        if (empty($diff)) {
            return '';
        }

        $output = '';

        foreach ($diff as $field => $data) {
            $output .= $field . ' (expected) : <fg=white;bg=green>' . $data['expected'] . '</> ';
            $output .= $field . ' (actual)   : <fg=white;bg=red>' . $data['actual'] . '</> ';
        }

        return $output;
    }

    private function outputDiffHtml(array $diff): string
    {
        if (empty($diff)) {
            return '';
        }

        $output = '';

        foreach ($diff as $field => $data) {
            $expected = $data['expected'];

            if (null === $expected) {
                $expected = '(null)';
            } elseif ('' === $expected) {
                $expected = '(empty)';
            }

            $actual = $data['actual'];

            if (null === $actual) {
                $actual = '(null)';
            } elseif ('' === $actual) {
                $actual = '(empty)';
            }

            $output .= $field . ': "<span style="background-color: green; color: white">' . $expected . '</span>" "<span style="background-color: red; color: white">' . $actual . '</span>" ';
        }

        return $output;
    }

    private function colorByPercent(float $percent): string
    {
        if (100.0 <= $percent) {
            return '<fg=bright-green;bg=black>';
        }

        if (95.0 <= $percent) {
            return '<fg=green;bg=black>';
        }

        if (90.0 <= $percent) {
            return '<fg=bright-yellow;bg=black>';
        }

        if (85.0 <= $percent) {
            return '<fg=yellow;bg=black>';
        }

        if (50.0 > $percent) {
            return '<fg=red;bg=black>';
        }

        return '<fg=white;bg=black>';
    }
}
