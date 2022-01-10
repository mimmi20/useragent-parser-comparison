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
            ->setHelp('Runs various test suites against the parsers to help determine which is the most "correct".');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Prepare our test directory to store the data from this run
        /** @var string|null $thisRunDirName */
        $thisRunDirName = $input->getArgument('run');

        if (empty($thisRunDirName)) {
            $thisRunDirName = date('YmdHis');
        }
        $thisRunDir   = $this->runDir . '/' . $thisRunDirName;
        $resultsDir   = $thisRunDir . '/results';
        $expectedDir  = $thisRunDir . '/expected';

        mkdir($thisRunDir);
        mkdir($resultsDir);
        mkdir($expectedDir);

        /** @var \UserAgentParserComparison\Command\Helper\Tests $testHelper */
        $testHelper = $this->getHelper('tests');

        foreach ($testHelper->collectTests($output, $thisRunDir) as $testPath => $testData) {
            $this->tests[$testPath] = $testData;
        }

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

        $usedTests = [];

        foreach ($selectedTests as $testName => $testData) {
            $result     = [];
            $actualTest = 0;

            foreach ($testData['build']() as $singleTestName => $singleTestData) {
                ++$actualTest;

                $agent = $singleTestData['headers']['user-agent'] ?? null;

                if (null === $agent) {
                    var_dump($singleTestData);
                    $output->writeln("\r" . ' <error>There was no useragent header for the testsuite ' . $singleTestName . '.</error>');
                    continue;
                }

                $agent = addcslashes($agent, PHP_EOL);
                $agentToShow = $agent;

                if (mb_strlen($agentToShow) > 100) {
                    $agentToShow = mb_substr($agentToShow, 0, 96) . ' ...';
                }

                $basicTestMessage = sprintf(
                    '  [%s] UA: <fg=yellow>%s</>',
                    $actualTest,
                    $agentToShow
                );

                $output->write("\r" . $basicTestMessage);
                $textLength = mb_strlen($basicTestMessage);

                foreach ($parsers as $parserName => $parser) {
                    if (!array_key_exists($parserName, $result)) {
                        $result[$parserName] = [
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

                    if (!file_exists($resultsDir . '/' . $parserName . '/' . $testName)) {
                        mkdir($resultsDir . '/' . $parserName . '/' . $testName);
                    }

                    file_put_contents(
                        $resultsDir . '/' . $parserName . '/' . $testName . '/' . $singleTestName . '.json',
                        json_encode(
                            [
                                'headers' => $singleResult['headers'],
                                'parsed'  => $singleResult['result']['parsed'],
                                'err'     => $singleResult['result']['err'],
                                'version' => $singleResult['version'],
                                'init'    => $singleResult['init_time'],
                                'time'    => $singleResult['parse_time'],
                                'memory'  => $singleResult['memory_used'],
                            ],
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                        )
                    );

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

                $output->write("\r" . str_pad($testMessage, $textLength));
            }

            $output->writeln('');

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
                    $resultsDir . '/' . $parserName . '/' . $testName . '/metadata.json',
                    json_encode($result[$parserName], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                );

                $usedTests[$testName] = $testData;
            }
        }

        try {
            $encoded = json_encode(
                ['tests' => $usedTests, 'parsers' => $parsers, 'date' => time()],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (Exception $e) {
            $output->writeln('<error>Encoding result metadata failed for the ' . $thisRunDirName . ' directory</error>');

            return self::FAILURE;
        }

        // write some test data to file
        file_put_contents(
            $thisRunDir . '/metadata.json',
            $encoded
        );

        $output->writeln('<comment>Parsing complete, data stored in ' . $thisRunDirName . ' directory</comment>');

        return self::SUCCESS;
    }
}
