<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use FilesystemIterator;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function ksort;
use function mkdir;
use function sort;
use function sprintf;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Test extends Command
{
    /**
     * @var array
     */
    private $tests = [];

    /**
     * @var string
     */
    private $testsDir = __DIR__ . '/../../tests';

    /**
     * @var string
     */
    private $runDir = __DIR__ . '/../../data/test-runs';

    /**
     * @var array
     */
    private $results = [];

    protected function configure(): void
    {
        $this->setName('test')
            ->setDescription('Runs test against the parsers')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run, if omitted will be generated from date')
            ->addOption('single-ua', null, InputOption::VALUE_NONE, 'parses one useragent after another')
            ->setHelp('Runs various test suites against the parsers to help determine which is the most "correct".');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exceptions\ArrayException
     * @throws \Exceptions\FilesystemException
     * @throws \Exceptions\JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->collectTests($output);

        $rows = [];

        $output->writeln('These are all available test suites, choose which you would like to run');

        $questions = array_keys($this->tests);
        sort($questions, SORT_FLAG_CASE | SORT_NATURAL);

        $i = 1;
        foreach ($questions as $name) {
            $rows[] = [$name];
            ++$i;
        }

        $table = new Table($output);
        $table->setHeaders(['Test Suite']);
        $table->setRows($rows);
        $table->render();

        $questions[] = 'All Suites';

        $questionHelper = $this->getHelper('question');
        $question       = new ChoiceQuestion(
            'Choose which test suites to run, separate multiple with commas (press enter to use all)',
            $questions,
            count($questions) - 1
        );
        $question->setMultiselect(true);

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

        /** @var \UserAgentParserComparison\Command\Helper\Parsers $parserHelper */
        $parserHelper = $this->getHelper('parsers');
        $parsers      = $parserHelper->getParsers($input, $output);

        // Prepare our test directory to store the data from this run
        /** @var string|null $thisRunDirName */
        $thisRunDirName = $input->getArgument('run');

        if (empty($thisRunDirName)) {
            $thisRunDirName = date('YmdHis');
        }
        $thisRunDir   = $this->runDir . '/' . $thisRunDirName;
        $testFilesDir = $thisRunDir . '/test-files';
        $resultsDir   = $thisRunDir . '/results';
        $expectedDir  = $thisRunDir . '/expected';

        mkdir($thisRunDir);
        mkdir($testFilesDir);
        mkdir($resultsDir);
        mkdir($expectedDir);

        $usedTests = [];

        foreach ($selectedTests as $testName => $testData) {
            $message = sprintf('Test suite <fg=yellow>%s</>', $testName);

            $output->write($message . ' <info>building test suite</info>');
            $this->results[$testName] = [];

            $testOutput = trim((string) shell_exec($testData['path'] . '/build.sh'));

            $output->write("\r" . $message . ' <info>writing test suite</info>');

            file_put_contents($expectedDir . '/' . $testName . '.json', $testOutput);

            try {
                $testOutput = json_decode($testOutput, true, 512, JSON_THROW_ON_ERROR);
            } catch (Exception $e) {
                $output->writeln("\r" . $message . ' <error>There was an error with the output from the ' . $testName . ' test suite.</error>');

                continue;
            }

            if ($testOutput['tests'] === null || $testOutput['tests'] === []) {
                $output->writeln("\r" . $message . ' <error>There was an error with the output from the ' . $testName . ' test suite, no tests were found.</error>');
                continue;
            }

            if (!empty($testOutput['version'])) {
                $testData['metadata']['version'] = $testOutput['version'];
            }

            if ($input->getOption('single-ua')) {
                if (is_array($testOutput['tests'])) {
                    $agents = array_keys($testOutput['tests']);
                } else {
                    $agents = [];
                }

                $output->writeln("\r" . $message . ' <info>build done! [' . count($agents) . ' tests found]</info>');
                $result     = [];
                $countTests = (string) count($agents);
                $actualTest = 0;

                foreach ($agents as $agent) {
                    ++$actualTest;

                    if (is_int($agent)) {
                        continue;
                    }

                    $agent = addcslashes($agent, PHP_EOL);

                    $output->writeln(
                        sprintf(
                            '%s[%s/%s] UA: <fg=yellow>%s</>',
                            '  ',
                            str_pad((string) $actualTest, mb_strlen($countTests), ' ', STR_PAD_LEFT),
                            $countTests,
                            $agent
                        )
                    );

                    $basicTestMessage = sprintf(
                        '%s[%s/%s] Testing',
                        '  ',
                        str_pad((string) $actualTest, mb_strlen($countTests), ' ', STR_PAD_LEFT),
                        $countTests
                    );

                    $output->write($basicTestMessage);
                    $textLength = mb_strlen($basicTestMessage);

                    foreach ($parsers as $parserName => $parser) {
                        if (!array_key_exists($parserName, $result)) {
                            $result[$parserName] = [
                                'results'     => [],
                                'parse_time'  => 0,
                                'init_time'   => 0,
                                'memory_used' => 0,
                                'version'     => null,
                            ];
                        }

                        $testMessage = $basicTestMessage . ' against the <fg=green;options=bold,underscore>' . $parserName . '</> parser...';

                        if (mb_strlen($testMessage) > $textLength) {
                            $textLength = mb_strlen($testMessage);
                        }

                        $output->write("\r" . str_pad($testMessage, $textLength));

                        $singleResult = $parser['parse-ua']($agent);

                        if (empty($singleResult)) {
                            $testMessage = $basicTestMessage . ' <error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>';

                            if (mb_strlen($testMessage) > $textLength) {
                                $textLength = mb_strlen($testMessage);
                            }

                            $output->writeln("\r" . str_pad($testMessage, $textLength));

                            continue;
                        }

                        if (!empty($singleResult['version'])) {
                            $parsers[$parserName]['metadata']['version'] = $singleResult['version'];
                        }

                        if (!file_exists($resultsDir . '/' . $parserName)) {
                            mkdir($resultsDir . '/' . $parserName);
                        }

                        $result[$parserName]['results'][] = $singleResult['result'];

                        if ($singleResult['init_time'] > $result[$parserName]['init_time']) {
                            $result[$parserName]['init_time'] = $singleResult['init_time'];
                        }

                        if ($singleResult['memory_used'] > $result[$parserName]['memory_used']) {
                            $result[$parserName]['memory_used'] = $singleResult['memory_used'];
                        }

                        $result[$parserName]['parse_time'] += $singleResult['parse_time'];
                        $result[$parserName]['version'] = $singleResult['version'];
                    }

                    $testMessage = $basicTestMessage . ' <info>done!</info>';

                    if (mb_strlen($testMessage) > $textLength) {
                        $textLength = mb_strlen($testMessage);
                    }

                    $output->writeln("\r" . str_pad($testMessage, $textLength));
                }

                foreach (array_keys($parsers) as $parserName) {
                    if (!array_key_exists($parserName, $result)) {
                        $output->writeln(
                            '<error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>'
                        );

                        continue;
                    }

                    if (!file_exists($resultsDir . '/' . $parserName)) {
                        mkdir($resultsDir . '/' . $parserName);
                    }

                    file_put_contents(
                        $resultsDir . '/' . $parserName . '/' . $testName . '.json',
                        json_encode($result[$parserName], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                    );

                    $usedTests[$testName] = $testData;
                }
            } else {
                $output->write("\r" . $message . ' <info>write test data into file...</info>');

                // write our test's file that we'll pass to the parsers
                $filename = $testFilesDir . '/' . $testName . '.txt';

                if (is_array($testOutput['tests'])) {
                    $agents = array_keys($testOutput['tests']);
                } else {
                    $agents = [];
                }

                array_walk($agents, static function (string &$item): void {
                    $item = addcslashes($item, PHP_EOL);
                });

                file_put_contents($filename, implode(PHP_EOL, $agents));
                $output->writeln("\r" . $message . ' <info>build done! [' . count($agents) . ' tests found]</info>');

                foreach ($parsers as $parserName => $parser) {
                    $basicTestMessage = sprintf(' Testing against the <fg=green;options=bold,underscore>%s</> parser...', $parserName);
                    $output->write($basicTestMessage . ' <info> parsing</info>');

                    $result = $parser['parse']($filename);

                    if (empty($result)) {
                        $output->writeln(
                        "\r" . $basicTestMessage . ' <error>The parser did not return any data, there may have been an error</error>'
                        );

                        continue;
                    }

                    if (!empty($result['version'])) {
                        $parsers[$parserName]['metadata']['version'] = $result['version'];
                    }

                    if (!file_exists($resultsDir . '/' . $parserName)) {
                        mkdir($resultsDir . '/' . $parserName);
                    }

                    try {
                        $encoded = json_encode(
                            $result,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                        );
                    } catch (Exception $e) {
                    $output->writeln("\r" . $basicTestMessage . ' <error>encoding the result failed!</error>');
                        continue;
                    }

                    file_put_contents(
                        $resultsDir . '/' . $parserName . '/' . $testName . '.json',
                        $encoded
                    );
                    $output->writeln("\r" . $basicTestMessage . ' <info>done!</info>                                                                                 ');

                    $usedTests[$testName] = $testData;
                }
            }
        }

        try {
            $encoded = json_encode(
                ['tests' => $usedTests, 'parsers' => $parsers, 'date' => time()],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (Exception $e) {
            $output->writeln('<error>Encoding result metadata failed for the ' . $thisRunDirName . ' directory</error>');

            return 1;
        }

        // write some test data to file
        file_put_contents(
            $thisRunDir . '/metadata.json',
            $encoded
        );

        $output->writeln('<comment>Parsing complete, data stored in ' . $thisRunDirName . ' directory</comment>');

        return 0;
    }

    private function collectTests(OutputInterface $output): void
    {
        /** @var SplFileInfo $testDir */
        foreach (new FilesystemIterator($this->testsDir) as $testDir) {
            $metadata = [];
            if (file_exists($testDir->getPathname() . '/metadata.json')) {
                try {
                    $contents = file_get_contents($testDir->getPathname() . '/metadata.json');
                } catch (Exception $e) {
                    $output->writeln('<error>An error occured while reading the metadata file</error>');

                    continue;
                }

                try {
                    $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (Exception $e) {
                    $output->writeln('<error>An error occured while parsing results for the ' . $testDir->getPathname() . ' test suite</error>');

                    continue;
                }
            }

            $this->tests[$testDir->getFilename()] = [
                'path'     => $testDir->getPathname(),
                'metadata' => $metadata,
            ];
        }

        ksort($this->tests);
    }
}
