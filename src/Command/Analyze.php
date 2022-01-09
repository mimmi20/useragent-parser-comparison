<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use UserAgentParserComparison\Compare\Comparison;
use function array_flip;
use function file_get_contents;
use function json_decode;
use function ksort;
use function sort;
use function sprintf;
use function uasort;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Analyze extends Command
{
    /**
     * @var string
     */
    private $runDir = __DIR__ . '/../../data/test-runs';

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var array
     */
    private $comparison = [];

    /**
     * @var array
     */
    private $agents = [];

    /**
     * @var Table
     */
    private $summaryTable;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var array
     */
    private $failures = [];

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

        /** @var string|null $run */
        $run = $input->getArgument('run');

        if (empty($run)) {
            /** @var \UserAgentParserComparison\Command\Helper\Tests $testHelper */
            $testHelper = $this->getHelper('tests');
            $run        = $testHelper->getTest($input, $output);

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
        } catch (Exception $e) {
            $output->writeln(sprintf('<error>Could not read file (%s)</error>', $metaDataFile));

            return self::INVALID;
        }

        try {
            $this->options = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            $output->writeln('<error>An error occured while parsing metadata for run ' . $run . '</error>');

            return self::INVALID;
        }

        $output->writeln(sprintf('<info>Analyzing data from test run: %s</info>', $run));

        if (!empty($this->options['tests'])) {
            $tests = $this->options['tests'];
        } elseif (!empty($this->options['file'])) {
            $tests = [
                $this->options['file'] => [],
            ];
            $this->options['tests'] = $tests;
        } else {
            $output->writeln(sprintf('<error>Error in options file for run (%s)</error>', $run));

            return self::FAILURE;
        }

        $this->summaryTable = new Table($output);
        $this->summaryTable->setHeaders(['Parser', 'Version', 'Client Results', 'Platform Results', 'Device Results', 'Time Taken', 'Accuracy Score']);
        $rows   = [];
        $totals = [];

        foreach ($tests as $testName => $testData) {
            $this->comparison[$testName] = [];

            $expectedFilename = $this->runDir . '/' . $run . '/expected/normalized/' . $testName . '.json';

            if (file_exists($expectedFilename)) {
                try {
                    $contents = file_get_contents($expectedFilename);
                } catch (Exception $e) {
                    $this->output->writeln('<error>Could not read file (' . $expectedFilename . ')</error>');
                    continue;
                }

                try {
                    $expectedResults = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    $headerMessage   = sprintf('Parser comparison for <fg=yellow>%s%s</>', $testData['metadata']['name'], (isset($testData['metadata']['version']) ? ' (' . $testData['metadata']['version'] . ')' : ''));
                } catch (Exception $e) {
                    $this->output->writeln(sprintf('<error>An error occured while parsing file (%s), skipping</error>', $expectedFilename));
                    continue;
                }
            } else {
                // When we aren't comparing to a test suite, the first parser's results become the expected results
                $expectedResults = ['tests' => []];
                $fileName        = $this->runDir . '/' . $run . '/results/' . array_keys($this->options['parsers'])[0] . '/normalized/' . $testName . '.json';
                try {
                    $contents = file_get_contents($fileName);
                } catch (Exception $e) {
                    $this->output->writeln('<error>Could not read file (' . $fileName . ')</error>');
                    continue;
                }

                try {
                    $testResult    = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    $headerMessage = sprintf('<fg=yellow>Parser comparison for %s file, using %s results as expected</>', $testName, array_keys($this->options['parsers'])[0]);
                } catch (Exception $e) {
                    $this->output->writeln(sprintf('<error>An error occured while parsing metadata for run %s, skipping</error>', $run));
                    continue;
                }

                foreach ($testResult['results'] as $data) {
                    $expectedResults['tests'][$data['useragent']] = $data['parsed'];
                }
            }

            if (!isset($expectedResults['tests']) || !is_array($expectedResults['tests']) || empty($expectedResults['tests'])) {
                continue;
            }

            $rows[] = [new TableCell($headerMessage, ['colspan' => 7])];
            $rows[] = new TableSeparator();

            $this->agents = array_flip(array_keys($expectedResults['tests']));

            $parserScores   = [];
            $possibleScores = [];

            foreach ($this->options['parsers'] as $parserName => $parserData) {
                if (!file_exists($this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $testName . '.json')) {
                    $this->output->writeln(sprintf('<error>No output found for the %s parser, skipping</error>', $parserName));

                    continue;
                }

                $fileName = $this->runDir . '/' . $run . '/results/' . $parserName . '/normalized/' . $testName . '.json';
                try {
                    $contents = file_get_contents($fileName);
                } catch (Exception $e) {
                    $this->output->writeln(sprintf('<error>Could not read file (%s), skipping</error>', $fileName));

                    continue;
                }

                try {
                    $testResult = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (Exception $e) {
                    $this->output->writeln(sprintf('<error>An error occured while parsing file (%s), skipping</error>', $fileName));

                    continue;
                }

                $passFail = [
                    'browser'  => ['pass' => 0, 'fail' => 0],
                    'platform' => ['pass' => 0, 'fail' => 0],
                    'device'   => ['pass' => 0, 'fail' => 0],
                ];

                $parserScores[$parserName][$testName]   = 0;
                $possibleScores[$parserName][$testName] = 0;

                foreach ($testResult['results'] as $data) {
                    if (!array_key_exists('useragent', $data) || !array_key_exists('parsed', $data)) {
                        continue;
                    }

                    $expected   = $expectedResults['tests'][$data['useragent']] ?? [];
                    $comparison = new Comparison($expected, $data['parsed'] ?? []);

                    foreach (['browser', 'platform', 'device'] as $compareKey) {
                        if (!array_key_exists($compareKey, $expected) || !array_key_exists($compareKey, $data['parsed'])) {
                            continue;
                        }

                        $score         = $this->calculateScore($expected[$compareKey], $data['parsed'][$compareKey]);
                        $possibleScore = $this->calculateScore($expected[$compareKey], $data['parsed'][$compareKey], true);

                        $passFail[$compareKey]['pass'] += $score;
                        $passFail[$compareKey]['fail'] += $possibleScore - $score;

                        $parserScores[$parserName][$testName]   += $score;
                        $possibleScores[$parserName][$testName] += $possibleScore;
                    }

                    $this->comparison[$testName] = $comparison->getComparison($parserName, $this->agents[$data['useragent']] ?? 0);
                    $failures = $comparison->getFailures();

                    if (!empty($failures)) {
                        $this->failures[$testName][$parserName][$data['useragent']] = $failures;
                    }
                }

                if (array_sum($passFail['browser']) === 0) {
                    $browserContent = '<fg=white;bg=blue>-</>';
                } else {
                    $browserPercentage = $passFail['browser']['pass'] / array_sum($passFail['browser']) * 100;
                    $browserContent    = $this->colorByPercent($browserPercentage) . $passFail['browser']['pass'] . '/' . array_sum($passFail['browser']) . ' ' . round($browserPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if (array_sum($passFail['platform']) === 0) {
                    $platformContent = '<fg=white;bg=blue>-</>';
                } else {
                    $platformPercentage = $passFail['platform']['pass'] / array_sum($passFail['platform']) * 100;
                    $platformContent    = $this->colorByPercent($platformPercentage) . $passFail['platform']['pass'] . '/' . array_sum($passFail['platform']) . ' ' . round($platformPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if (array_sum($passFail['device']) === 0) {
                    $deviceContent = '<fg=white;bg=blue>-</>';
                } else {
                    $devicePercentage = $passFail['device']['pass'] / array_sum($passFail['device']) * 100;
                    $deviceContent    = $this->colorByPercent($devicePercentage) . $passFail['device']['pass'] . '/' . array_sum($passFail['device']) . ' ' . round($devicePercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if ($possibleScores[$parserName][$testName] === 0) {
                    $summaryContent = '<fg=white;bg=blue>-</>';
                } else {
                    $summaryPercentage = $parserScores[$parserName][$testName] / $possibleScores[$parserName][$testName] * 100;
                    $summaryContent    = $this->colorByPercent($summaryPercentage) . $parserScores[$parserName][$testName] . '/' . $possibleScores[$parserName][$testName] . ' ' . round($summaryPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                $rows[] = [
                    $parserData['metadata']['name'],
                    $parserData['metadata']['version'] ?? 'n/a',
                    $browserContent,
                    $platformContent,
                    $deviceContent,
                    round($testResult['parse_time'] + $testResult['init_time'], 3) . 's',
                    $summaryContent,
                ];

                if (!isset($totals[$parserName])) {
                    $totals[$parserName] = [
                        'browser'  => ['pass' => 0, 'fail' => 0],
                        'platform' => ['pass' => 0, 'fail' => 0],
                        'device'   => ['pass' => 0, 'fail' => 0],
                        'time'     => 0,
                        'score'    => ['earned' => 0, 'possible' => 0],
                    ];
                }

                $totals[$parserName]['browser']['pass']   += $passFail['browser']['pass'];
                $totals[$parserName]['browser']['fail']   += $passFail['browser']['fail'];
                $totals[$parserName]['platform']['pass']  += $passFail['platform']['pass'];
                $totals[$parserName]['platform']['fail']  += $passFail['platform']['fail'];
                $totals[$parserName]['device']['pass']    += $passFail['device']['pass'];
                $totals[$parserName]['device']['fail']    += $passFail['device']['fail'];
                $totals[$parserName]['time']              += ($testResult['parse_time'] + $testResult['init_time']);
                $totals[$parserName]['score']['earned']   += $parserScores[$parserName][$testName];
                $totals[$parserName]['score']['possible'] += $possibleScores[$parserName][$testName];
            }

            $rows[] = new TableSeparator();
        }

        if (count($this->options['tests']) > 1) {
            $rows[] = [new TableCell('<fg=yellow>Total for all Test suites</>', ['colspan' => 7])];
            $rows[] = new TableSeparator();

            foreach ($totals as $parser => $total) {
                if (array_sum($total['browser']) === 0) {
                    $browserContent = '<fg=white;bg=blue>-</>';
                } else {
                    $browserPercentage = $total['browser']['pass'] / array_sum($total['browser']) * 100;
                    $browserContent    = $this->colorByPercent($browserPercentage) . $total['browser']['pass'] . '/' . array_sum($total['browser']) . ' ' . round($browserPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if (array_sum($total['platform']) === 0) {
                    $platformContent = '<fg=white;bg=blue>-</>';
                } else {
                    $platformPercentage = $total['platform']['pass'] / array_sum($total['platform']) * 100;
                    $platformContent    = $this->colorByPercent($platformPercentage) . $total['platform']['pass'] . '/' . array_sum($total['platform']) . ' ' . round($platformPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if (array_sum($total['device']) === 0) {
                    $deviceContent = '<fg=white;bg=blue>-</>';
                } else {
                    $devicePercentage = $total['device']['pass'] / array_sum($total['device']) * 100;
                    $deviceContent    = $this->colorByPercent($devicePercentage) . $total['device']['pass'] . '/' . array_sum($total['device']) . ' ' . round($devicePercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                if ($total['score']['possible'] === 0) {
                    $summaryContent = '<fg=white;bg=blue>-</>';
                } else {
                    $summaryPercentage = $total['score']['earned'] / $total['score']['possible'] * 100;
                    $summaryContent    = $this->colorByPercent($summaryPercentage) . $total['score']['earned'] . '/' . $total['score']['possible'] . ' ' . round($summaryPercentage, 2, \PHP_ROUND_HALF_DOWN) . '%</>';
                }

                $rows[] = [
                    $parser,
                    isset($this->options['parsers'][$parser]['metadata']['version']) ? $this->options['parsers'][$parser]['metadata']['version'] : 'n/a',
                    $browserContent,
                    $platformContent,
                    $deviceContent,
                    round($total['time'], 3) . 's',
                    $summaryContent,
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

        if (count($this->options['tests']) > 1) {
            $question = new ChoiceQuestion(
                'Which Test Suite?',
                array_keys($this->options['tests'])
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
            ['browser', 'platform', 'device']
        );
        $section = $questionHelper->ask($this->input, $this->output, $question);

        return $section;
    }

    private function changePropertyDiffProperty(string $section): string
    {
        $questionHelper = $this->getHelper('question');
        $subs           = [];

        switch ($section) {
            case 'browser':
            case 'platform':
                $subs = ['name'];

                break;
            case 'device':
                $subs = ['name', 'brand', 'type'];

                break;
        }

        if (count($subs) > 1) {
            $question = new ChoiceQuestion(
                'Which Property?',
                $subs
            );
            $property = $questionHelper->ask($this->input, $this->output, $question);
        } elseif (count($subs) === 1) {
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
            3
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
                                array_keys($this->options['tests'])
                            );

                            $selectedTest = $questionHelper->ask($this->input, $this->output, $question);
                        } else {
                            $selectedTest = array_keys($this->options['tests'])[0];
                        }
                    }

                    if (!isset($selectedParser) || $answer === 'Change Parser') {
                        if (count($this->options['parsers']) > 1) {
                            $question = new ChoiceQuestion(
                                'Which parser?',
                                array_keys($this->options['parsers'])
                            );

                            $selectedParser = $questionHelper->ask($this->input, $this->output, $question);
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
                        unset($questions[array_search('Change Test Suite', $questions)]);
                    }

                    if (count($this->options['parsers']) <= 1) {
                        unset($questions[array_search('Change Parser', $questions)]);
                    }

                    // Re-index
                    $questions = array_values($questions);

                    $question = new ChoiceQuestion(
                        'What would you like to do?',
                        $questions,
                        count($questions) - 1
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

                    if (!isset($property) || $answer === 'Change Section' || $answer === 'Change Property') {
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
                        count($questions) - 1
                    );

                    $answer = $questionHelper->ask($this->input, $this->output, $question);

                    if ($answer === 'Export User Agents') {
                        $question     = new Question('Type the expected value to view the agents parsed:');
                        $autoComplete = array_merge(['[no value]'], array_keys($this->comparison[$selectedTest][$section][$property]));
                        sort($autoComplete);
                        $question->setAutocompleterValues($autoComplete);

                        $value = $questionHelper->ask($this->input, $this->output, $question);

                        $this->showComparisonAgents($selectedTest, $section, $property, $value);

                        $question = new Question('Press enter to continue', 'yes');
                        $questionHelper->ask($this->input, $this->output, $question);
                    }
                } while ($answer !== 'Back to Main Menu');

                $this->showMenu();

                break;
            case 'Exit':
                $this->output->writeln('Goodbye!');

                break;
        }
    }

    private function showComparisonAgents(string $test, string $section, string $property, string $value): void
    {
        if ($value === '[no value]') {
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
                '<error>There were no failures for the ' . $parser . ' parser for the ' . $test . ' test suite</error>'
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
        $table->setStyle('box');

        $htmlG = '<html><body><table><colgroup><col span="3" style="width: 33%"></colgroup><thead><tr><th colspan="3">UserAgent</th></tr><tr><th>Browser</th><th>Platform</th><th>Device</th></tr></thead><tbody>';
        $htmlB = '<html><body><table><thead><tr><th>UserAgent</th></tr><tr><th>Browser</th></tr></thead><tbody>';
        $htmlP = '<html><body><table><thead><tr><th>UserAgent</th></tr><tr><th>Platform</th></tr></thead><tbody>';
        $htmlD = '<html><body><table><thead><tr><th>UserAgent</th></tr><tr><th>Device</th></tr></thead><tbody>';

        $table->setHeaders([
            [new TableCell('UserAgent', ['colspan' => 3])],
            [new TableCell('Browser'), new TableCell('Platform'), new TableCell('Device')],
        ]);

        $rows = [];
        foreach ($this->failures[$test][$parser] as $agent => $failData) {
            if (empty($failData['browser']) && empty($failData['platform']) && empty($failData['device'])) {
                continue;
            }

            if ($justAgents === true) {
                $this->output->writeln($agent);
                continue;
            }

            $rows[] = [new TableCell((string) $agent, ['colspan' => 3])];
            $rows[] = [
                new TableCell(isset($failData['browser']) ? $this->outputDiff($failData['browser']) : ''),
                new TableCell(isset($failData['platform']) ? $this->outputDiff($failData['platform']) : ''),
                new TableCell(isset($failData['device']) ? $this->outputDiff($failData['device']) : ''),
            ];
            $rows[] = new TableSeparator();

            $htmlG .= '<tr><td colspan="3">' . (string) $agent . '</td></tr>';
            $htmlG .= '<tr><td>' . (!empty($failData['browser']) ? $this->outputDiffHtml($failData['browser']) : '') . '</td><td>' . (!empty($failData['platform']) ? $this->outputDiffHtml($failData['platform']) : '') . '</td><td>' . (!empty($failData['device']) ? $this->outputDiffHtml($failData['device']) : '') . '</td></tr>';

            if (!empty($failData['browser'])) {
                $htmlB .= '<tr><td>' . (string) $agent . '</td></tr>';
                $htmlB .= '<tr><td>' . $this->outputDiffHtml($failData['browser']) . '</td></tr>';
            }

            if (!empty($failData['platform'])) {
                $htmlP .= '<tr><td>' . (string) $agent . '</td></tr>';
                $htmlP .= '<tr><td>' . $this->outputDiffHtml($failData['platform']) . '</td></tr>';
            }

            if (!empty($failData['device'])) {
                $htmlD .= '<tr><td>' . (string) $agent . '</td></tr>';
                $htmlD .= '<tr><td>' . $this->outputDiffHtml($failData['device']) . '</td></tr>';
            }
        }

        $htmlG .= '</tbody></table></body></html>';
        $htmlB .= '</tbody></table></body></html>';
        $htmlP .= '</tbody></table></body></html>';
        $htmlD .= '</tbody></table></body></html>';

        if ($justAgents === false) {
            array_pop($rows);

            $table->setRows($rows);

            $table->render();
            file_put_contents($this->runDir . '/errors-summary.html', $htmlG);
            file_put_contents($this->runDir . '/errors-browsers.html', $htmlB);
            file_put_contents($this->runDir . '/errors-platforms.html', $htmlP);
            file_put_contents($this->runDir . '/errors-devices.html', $htmlD);
        }
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

            return ($a['expected']['count'] > $b['expected']['count']) ? -1 : 1;
        });

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
                if (count($child) > $max) {
                    $max = count($child);
                }
            }

            foreach (array_keys($this->options['parsers']) as $parser) {
                if (isset($compareRow[$parser])) {
                    uasort($compareRow[$parser], static function (array $a, array $b): int {
                        if ($a['count'] === $b['count']) {
                            return 0;
                        }

                        return ($a['count'] > $b['count']) ? -1 : 1;
                    });
                }
            }

            for ($i = 0; $i < $max; ++$i) {
                $row     = [];
                $parsers = array_merge(['expected'], array_keys($this->options['parsers']));

                foreach ($parsers as $parser) {
                    if ($parser === 'expected') {
                        if ($i === 0) {
                            $row[] = ($expected === '' ? '[no value]' : $expected) . ' <comment>(' . $compareRow['expected']['count'] . ')</comment>';
                        } else {
                            $row[] = ' ';
                        }
                    } else {
                        if (isset($compareRow[$parser]) && count($compareRow[$parser]) > 0) {
                            $key      = current(array_keys($compareRow[$parser]));
                            $quantity = array_shift($compareRow[$parser]);

                            if ($key === $expected) {
                                $row[] = ($key === '' ? '[no value]' : $key) . ' <fg=green>(' . $quantity['count'] . ')</>';
                            } elseif ($expected === '[n/a]' || $key === '[n/a]') {
                                $row[] = ($key === '' ? '[no value]' : $key) . ' <fg=blue>(' . $quantity['count'] . ')</>';
                            } else {
                                $row[] = ($key === '' ? '[no value]' : $key) . ' <fg=red>(' . $quantity['count'] . ')</>';
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
            if ($value === null) {
                continue;
            }

            // this happens if our possible score calculation is called
            if ($possible === true && $actual[$field] !== null) {
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
