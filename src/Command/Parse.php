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

use Exception;
use JsonException;
use SplFileObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use UserAgentParserComparison\Command\Helper\Parsers;

use function addcslashes;
use function array_key_exists;
use function array_pop;
use function assert;
use function basename;
use function fclose;
use function file_exists;
use function file_put_contents;
use function fopen;
use function fputcsv;
use function is_string;
use function json_encode;
use function mb_str_pad;
use function mb_strlen;
use function mb_substr;
use function mkdir;
use function rewind;
use function round;
use function rtrim;
use function sprintf;
use function stream_get_contents;
use function time;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;

final class Parse extends Command
{
    private string $runDir = __DIR__ . '/../../data/test-runs';

    /** @throws void */
    protected function configure(): void
    {
        $this->setName('parse')
            ->setDescription('Parses useragents in a file using the selected parser(s)')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file to parse')
            ->addArgument('run', InputArgument::OPTIONAL, 'Name of the run, for storing results')
            ->addOption('normalize', null, InputOption::VALUE_NONE, 'Whether to normalize the output')
            ->addOption('csv', null, InputOption::VALUE_NONE, 'Outputs CSV without showing CLI table')
            ->addOption(
                'no-output',
                null,
                InputOption::VALUE_NONE,
                'Disables output after parsing, useful when chaining commands',
            )
            ->addOption(
                'csv-file',
                null,
                InputOption::VALUE_OPTIONAL,
                'File name to output CSV data to, implies the options "csv" and "no-output"',
            )
            ->setHelp(
                'Parses the useragent strings (one per line) from the passed in file and outputs the parsed properties.',
            );
    }

    /** @throws JsonException */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $input->getArgument('file');
        assert(is_string($filename));
        $normalize = $input->getOption('normalize');
        $csv       = $input->getOption('csv');

        $name = $input->getArgument('run');
        assert(is_string($name) || $name === null);
        $noOutput = $input->getOption('no-output');

        $csvFile = $input->getOption('csv-file');
        assert(is_string($csvFile) || $csvFile === null);

        if ($csvFile) {
            $noOutput = true;
            $csv      = true;
        } elseif ($csv) {
            $output->writeln(
                '<error>csvFile parameter is required if csv parameter is specified</error>',
            );

            return self::FAILURE;
        }

        $normalizeHelper = $this->getHelper('normalize');
        assert($normalizeHelper instanceof Helper\Normalize);
        $questionHelper = $this->getHelper('question');

        $table = new Table($output);
        $table->setHeaders([
            [new TableCell('UserAgent', ['colspan' => '7']), 'Parse Time'],
            ['browser_name', 'browser_version', 'platform_name', 'platform_version', 'device_name', 'device_brand', 'device_type', 'is_mobile'],
        ]);

        if ($name) {
            mkdir($this->runDir . '/' . $name);
            mkdir($this->runDir . '/' . $name . '/results');
        }

        $parserHelper = $this->getHelper('parsers');
        assert($parserHelper instanceof Parsers);
        $parsers    = $parserHelper->getParsers($input, $output);
        $actualTest = 0;

        $result = [];
        $file   = new SplFileObject($filename);
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        while (!$file->eof()) {
            $agentString = $file->fgets();
            ++$actualTest;

            if (empty($agentString)) {
                continue;
            }

            $agentString = addcslashes($agentString, PHP_EOL);
            $agentToShow = $agentString;

            if (mb_strlen($agentToShow) > 100) {
                $agentToShow = mb_substr($agentToShow, 0, 96) . ' ...';
            }

            $basicTestMessage = sprintf(
                '%s[%s] Parsing UA <fg=yellow>%s</>',
                '  ',
                (string) $actualTest,
                $agentToShow . ' ',
            );

            $output->write($basicTestMessage);
            $textLength = mb_strlen($basicTestMessage);

            foreach ($parsers as $parserName => $parser) {
                if (!array_key_exists($parserName, $result)) {
                    $result[$parserName] = [
                        'results' => [],
                        'parse_time' => 0,
                        'init_time' => 0,
                        'memory_used' => 0,
                        'version' => null,
                    ];
                }

                $testMessage = $basicTestMessage . ' <info>against the <fg=green;options=bold,underscore>' . $parserName . '</> parser... </info>';

                if (mb_strlen($testMessage) > $textLength) {
                    $textLength = mb_strlen($testMessage);
                }

                $output->write("\r" . mb_str_pad($testMessage, $textLength));
                $singleResult = $parser['parse-ua']($agentString);

                if (empty($singleResult)) {
                    $testMessage = $basicTestMessage . ' <error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>';

                    $output->writeln("\r" . $testMessage);

                    continue;
                }

                if (isset($singleResult['version'])) {
                    $parsers[$parserName]['metadata']['version'] = $singleResult['version'];
                }

                $result[$parserName]['results'][] = [
                    'headers' => $singleResult['headers'],
                    'parsed' => $singleResult['result']['parsed'],
                    'err' => $singleResult['result']['err'],
                    'version' => $singleResult['version'],
                    'init' => $singleResult['init_time'],
                    'time' => $singleResult['parse_time'],
                    'memory' => $singleResult['memory_used'],
                ];

                if ($singleResult['init_time'] > $result[$parserName]['init_time']) {
                    $result[$parserName]['init_time'] = $singleResult['init_time'];
                }

                if ($singleResult['memory_used'] > $result[$parserName]['memory_used']) {
                    $result[$parserName]['memory_used'] = $singleResult['memory_used'];
                }

                $result[$parserName]['parse_time'] += $singleResult['parse_time'];
                $result[$parserName]['version']     = $singleResult['version'];

                unset($singleResult);
            }

            $testMessage = $basicTestMessage . ' <info>done!</info>';

            if (mb_strlen($testMessage) > $textLength) {
                $textLength = mb_strlen($testMessage);
            }

            $output->writeln("\r" . mb_str_pad($testMessage, $textLength));

            foreach ($parsers as $parserName => $parser) {
                if ($name) {
                    if (!file_exists($this->runDir . '/' . $name . '/results/' . $parserName)) {
                        mkdir($this->runDir . '/' . $name . '/results/' . $parserName);
                    }

                    file_put_contents(
                        $this->runDir . '/' . $name . '/results/' . $parserName . '/' . basename(
                            $filename,
                        ) . '.json',
                        json_encode(
                            $result[$parserName],
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                        ),
                    );
                }

                $rows = [];

                foreach ($result[$parserName]['results'] as $singleResult) {
                    if ($normalize) {
                        $singleResult['parsed'] = $normalizeHelper->normalize($singleResult['parsed']);
                    }

                    $rows[] = [
                        new TableCell(
                            '<fg=yellow>' . (isset($singleResult['useragent']) ? ' ' . rtrim(
                                $singleResult['useragent'],
                                '\\',
                            ) . ' ' : '--') . '</fg>',
                            ['colspan' => '7'],
                        ),
                        round($singleResult['time'], 5) . 's',
                    ];
                    $rows[] = [
                        $singleResult['parsed']['client']['name'],
                        $singleResult['parsed']['client']['version'],
                        $singleResult['parsed']['platform']['name'],
                        $singleResult['parsed']['platform']['version'],
                        $singleResult['parsed']['device']['name'] ?? null,
                        $singleResult['parsed']['device']['brand'],
                        $singleResult['parsed']['device']['type'],
                        $singleResult['parsed']['device']['ismobile'],
                    ];
                    $rows[] = new TableSeparator();
                }

                array_pop($rows);

                $table->setRows($rows);

                $answer = '';

                if (!$csv && !$noOutput) {
                    $table->render();

                    $question = new ChoiceQuestion(
                        'What would you like to do?',
                        ['Dump as CSV', 'Continue'],
                        1,
                    );

                    $answer = $questionHelper->ask($input, $output, $question);
                }

                if (!$csv && $answer !== 'Dump as CSV') {
                    continue;
                }

                $csvOutput = $this->putcsv(
                    [
                        'useragent',
                        'browser_name',
                        'browser_version',
                        'platform_name',
                        'platform_version',
                        'device_name',
                        'device_brand',
                        'device_type',
                        'ismobile',
                        'time',
                    ],
                    $csvFile,
                );

                $csvOutput .= "\n";

                foreach ($result[$parserName]['results'] as $singleResult) {
                    $out = [
                        $singleResult['useragent'],
                        $singleResult['parsed']['client']['name'],
                        $singleResult['parsed']['client']['version'],
                        $singleResult['parsed']['platform']['name'],
                        $singleResult['parsed']['platform']['version'],
                        $singleResult['parsed']['device']['name'],
                        $singleResult['parsed']['device']['brand'],
                        $singleResult['parsed']['device']['type'],
                        $singleResult['parsed']['device']['ismobile'],
                        $singleResult['time'],
                    ];

                    $csvOutput .= $this->putcsv($out, $csvFile) . "\n";
                }

                if ($csvFile) {
                    $output->writeln('Wrote CSV data to ' . $csvFile);
                } else {
                    $output->writeln($csvOutput);
                    $question = new Question('Press enter to continue', 'yes');
                    $questionHelper->ask($input, $output, $question);
                }
            }
        }

        if ($name) {
            file_put_contents(
                $this->runDir . '/' . $name . '/metadata.json',
                json_encode(
                    ['parsers' => $parsers, 'date' => time(), 'file' => basename($filename)],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
            );
        }

        $output->writeln('<info>done!</info>');

        return self::SUCCESS;
    }

    /**
     * @param array<int|string, string> $input
     *
     * @throws Exception if cannot open file stream
     */
    private function putcsv(array $input, string $csvFile): string
    {
        $delimiter = ',';
        $enclosure = '"';

        $fp = $csvFile ? fopen($csvFile, 'a+') : fopen('php://temp', 'r+');

        fputcsv($fp, $input, $delimiter, $enclosure);
        rewind($fp);
        $data = rtrim((string) stream_get_contents($fp), "\n");
        fclose($fp);

        if ($csvFile) {
            return '';
        }

        return $data;
    }
}
