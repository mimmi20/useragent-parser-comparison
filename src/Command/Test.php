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

use JsonException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Throwable;

use function addcslashes;
use function array_key_exists;
use function array_keys;
use function array_multisort;
use function assert;
use function date;
use function file_exists;
use function file_put_contents;
use function implode;
use function is_string;
use function json_encode;
use function mb_str_pad;
use function mb_strlen;
use function mb_substr;
use function mkdir;
use function number_format;
use function sprintf;
use function time;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;
use const SORT_ASC;
use const SORT_DESC;
use const SORT_FLAG_CASE;
use const SORT_NATURAL;
use const SORT_NUMERIC;
use const STR_PAD_LEFT;

final class Test extends Command
{
    /** @var array<mixed> */
    private array $tests   = [];
    private string $runDir = __DIR__ . '/../../data/test-runs';

    /** @throws void */
    protected function configure(): void
    {
        $this->setName('test')
            ->setDescription('Runs test against the parsers')
            ->addOption('use-db', null, InputOption::VALUE_NONE, 'Whether to use a database')
            ->addArgument(
                'run',
                InputArgument::OPTIONAL,
                'The name of the test run, if omitted will be generated from date',
            )
            ->setHelp(
                'Runs various test suites against the parsers to help determine which is the most "correct".',
            );
    }

    /** @throws JsonException */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $thisRunDirName = $input->getArgument('run');
        assert(is_string($thisRunDirName) || $thisRunDirName === null);

        if (empty($thisRunDirName)) {
            $thisRunDirName = date('YmdHis');
        }

        $useDb = $input->getOption('use-db');

        if ($useDb) {
            $thisRunDir = null;
        } else {
            $thisRunDir  = $this->runDir . '/' . $thisRunDirName;
            $resultsDir  = $thisRunDir . '/results';
            $expectedDir = $thisRunDir . '/expected';

            mkdir($thisRunDir);
            mkdir($resultsDir);
            mkdir($expectedDir);
        }

        $testHelper = $this->getHelper('tests');
        assert($testHelper instanceof Helper\Tests);

        foreach ($testHelper->collectTests($output, $thisRunDir) as $testPath => $testConfig) {
            $this->tests[$testPath] = $testConfig;
        }

        $rows   = [];
        $paths  = [];
        $counts = [];

        $output->writeln('These are all available test suites, choose which you would like to run');

        foreach ($this->tests as $testPath => $testConfig) {
            $paths[$testPath]  = $testPath;
            $counts[$testPath] = $testConfig['test-count'];
        }

        array_multisort(
            $paths,
            SORT_ASC,
            SORT_FLAG_CASE | SORT_NATURAL,
            $counts,
            SORT_DESC,
            SORT_NUMERIC,
            $this->tests,
        );

        $questions = [];

        foreach ($this->tests as $testPath => $testConfig) {
            $questions[] = $testPath;
            $rows[]      = [
                $testPath,
                mb_str_pad(
                    number_format($testConfig['test-count'], 0, '.', ','),
                    15,
                    ' ',
                    STR_PAD_LEFT,
),
            ];
        }

        $table = new Table($output);
        $table->setHeaders(['Test Suite', 'Number of Tests']);
        $table->setRows($rows);
        $table->render();

        $questions[] = 'All Suites';

        $questionHelper = $this->getHelper('question');
        $question       = new ChoiceQuestion(
            'Choose which test suites to run, separate multiple with commas (press enter to use all)',
            $questions,
            implode(',', array_keys($questions)),
        );
        $question->setMultiselect(true);
        $question->setAutocompleterValues($questions);

        $answers       = $questionHelper->ask($input, $output, $question);
        $selectedTests = [];

        foreach ($answers as $name) {
            if ($name === 'All Suites') {
                $selectedTests = $this->tests;

                break;
            }

            $selectedTests[$name] = $this->tests[$name];
        }

        $output->writeln('Choose which parsers you would like to run this test suite against');

        $parserHelper = $this->getHelper('parsers');
        assert($parserHelper instanceof Helper\Parsers);
        $parsers = $parserHelper->getParsers($input, $output);

        $usedTests  = [];
        $textLength = 0;

        foreach ($selectedTests as $testName => $testConfig) {
            $result     = [];
            $actualTest = 0;

            foreach ($testConfig['build']() as $singleTestName => $singleTestData) {
                ++$actualTest;

                $agent = $singleTestData['headers']['user-agent'] ?? null;

                if ($agent === null) {
                    //                    var_dump($singleTestData);
                    //                    $output->writeln("\r" . ' <error>There was no useragent header for the testsuite ' . $singleTestName . '.</error>');
                    continue;
                }

                $agent       = addcslashes($agent, PHP_EOL);
                $agentToShow = $agent;

                if (mb_strlen($agentToShow) > 100) {
                    $agentToShow = mb_substr($agentToShow, 0, 96) . ' ...';
                }

                $basicTestMessage = sprintf(
                    'test suite <fg=yellow>%s</> <info>parsing</info> [%s] UA: <fg=yellow>%s</>',
                    $testName,
                    $actualTest,
                    $agentToShow,
                );

                $output->write("\r" . $basicTestMessage);

                if (mb_strlen($basicTestMessage) > $textLength) {
                    $textLength = mb_strlen($basicTestMessage);
                }

                foreach ($parsers as $parserName => $parser) {
                    if (!array_key_exists($parserName, $result)) {
                        $result[$parserName] = [
                            'parse_time' => 0,
                            'init_time' => 0,
                            'memory_used' => 0,
                            'version' => null,
                        ];
                    }

                    $testMessage = $basicTestMessage . ' against the <fg=green;options=bold,underscore>' . $parserName . '</> parser...';

                    if (mb_strlen($testMessage) > $textLength) {
                        $textLength = mb_strlen($testMessage);
                    }

                    $output->write("\r" . mb_str_pad($testMessage, $textLength));

                    $singleResult = $parser['parse-ua']($agent);

                    if (empty($singleResult)) {
                        $testMessage = $basicTestMessage . ' <error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>';

                        if (mb_strlen($testMessage) > $textLength) {
                            $textLength = mb_strlen($testMessage);
                        }

                        $output->writeln("\r" . mb_str_pad($testMessage, $textLength));

                        continue;
                    }

                    if (!empty($singleResult['version'])) {
                        $parsers[$parserName]['metadata']['version'] = $singleResult['version'];
                    }

                    if (!file_exists($resultsDir . '/' . $parserName)) {
                        mkdir($resultsDir . '/' . $parserName);
                    }

                    if (!file_exists($resultsDir . '/' . $parserName . '/' . $testName)) {
                        mkdir($resultsDir . '/' . $parserName . '/' . $testName);
                    }

                    file_put_contents(
                        $resultsDir . '/' . $parserName . '/' . $testName . '/' . $singleTestName . '.json',
                        json_encode(
                            [
                                'headers' => $singleResult['headers'],
                                'parsed' => $singleResult['result']['parsed'],
                                'err' => $singleResult['result']['err'],
                                'version' => $singleResult['version'],
                                'init' => $singleResult['init_time'],
                                'time' => $singleResult['parse_time'],
                                'memory' => $singleResult['memory_used'],
                            ],
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                        ),
                    );

                    if ($singleResult['init_time'] > $result[$parserName]['init_time']) {
                        $result[$parserName]['init_time'] = $singleResult['init_time'];
                    }

                    if ($singleResult['memory_used'] > $result[$parserName]['memory_used']) {
                        $result[$parserName]['memory_used'] = $singleResult['memory_used'];
                    }

                    $result[$parserName]['parse_time'] += $singleResult['parse_time'];
                    $result[$parserName]['version']     = $singleResult['version'];
                }

                $testMessage = $basicTestMessage . ' <info>done!</info>';

                if (mb_strlen($testMessage) > $textLength) {
                    $textLength = mb_strlen($testMessage);
                }

                $output->write("\r" . mb_str_pad($testMessage, $textLength));
            }

            $output->writeln('');

            foreach (array_keys($parsers) as $parserName) {
                if (!array_key_exists($parserName, $result)) {
                    $output->writeln(
                        '<error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>',
                    );

                    continue;
                }

                if (!file_exists($resultsDir . '/' . $parserName)) {
                    mkdir($resultsDir . '/' . $parserName);
                }

                file_put_contents(
                    $resultsDir . '/' . $parserName . '/' . $testName . '/metadata.json',
                    json_encode(
                        $result[$parserName],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                    ),
                );

                $usedTests[$testName] = $testConfig;
            }
        }

        try {
            $encoded = json_encode(
                ['tests' => $usedTests, 'parsers' => $parsers, 'date' => time()],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (Throwable) {
            $output->writeln(
                '<error>Encoding result metadata failed for the ' . $thisRunDirName . ' directory</error>',
            );

            return self::FAILURE;
        }

        // write some test data to file
        file_put_contents($thisRunDir . '/metadata.json', $encoded);

        $output->writeln(
            '<comment>Parsing complete, data stored in ' . $thisRunDirName . ' directory</comment>',
        );

        return self::SUCCESS;
    }
}
