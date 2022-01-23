<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

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
use Throwable;

use function array_pop;
use function assert;
use function basename;
use function file_exists;
use function is_string;
use function round;
use function rtrim;
use function fclose;
use function file_put_contents;
use function fopen;
use function fputcsv;
use function json_encode;
use function mkdir;
use function rewind;
use function stream_get_contents;
use function time;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class Parse extends Command
{
    private string $runDir = __DIR__ . '/../../data/test-runs';

    protected function configure(): void
    {
        $this->setName('parse')
            ->setDescription('Parses useragents in a file using the selected parser(s)')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file to parse')
            ->addArgument('run', InputArgument::OPTIONAL, 'Name of the run, for storing results')
            ->addOption('normalize', null, InputOption::VALUE_NONE, 'Whether to normalize the output')
            ->addOption('csv', null, InputOption::VALUE_NONE, 'Outputs CSV without showing CLI table')
            ->addOption('no-output', null, InputOption::VALUE_NONE, 'Disables output after parsing, useful when chaining commands')
            ->addOption('csv-file', null, InputOption::VALUE_OPTIONAL, 'File name to output CSV data to, implies the options "csv" and "no-output"')
            ->setHelp('Parses the useragent strings (one per line) from the passed in file and outputs the parsed properties.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $input->getArgument('file');
        assert(is_string($filename));
        $normalize = $input->getOption('normalize');
        $csv       = $input->getOption('csv');

        $thisRunName = $input->getArgument('run');
        assert(is_string($thisRunName) || null === $thisRunName);
        $noOutput = $input->getOption('no-output');

        $csvFile = $input->getOption('csv-file');
        assert(is_string($csvFile) || null === $csvFile);

        if ($csvFile) {
            $noOutput = true;
            $csv      = true;
            $csvFile  = (string) $csvFile;
        } elseif ($csv) {
            $output->writeln(
                '<error>csvFile parameter is required if csv parameter is specified</error>'
            );

            return self::FAILURE;
        }

        $parserHelper = $this->getHelper('parsers');

        $normalizeHelper = $this->getHelper('normalize');
        assert($normalizeHelper instanceof Helper\Normalize);
        $questionHelper = $this->getHelper('question');

        $table = new Table($output);
        $table->setHeaders([
            [new TableCell('UserAgent', ['colspan' => '7']), 'Parse Time'],
            ['browser_name', 'browser_version', 'platform_name', 'platform_version', 'device_name', 'device_brand', 'device_type', 'is_mobile'],
        ]);

        if ($thisRunName) {
            mkdir($this->runDir . '/' . $thisRunName);
            mkdir($this->runDir . '/' . $thisRunName . '/results');
        }

        $parsers = $parserHelper->getParsers($input, $output);

        $output->writeln('<comment>Preparing to parse ' . $filename . '</comment>');

        foreach ($parsers as $parserName => $parser) {
            $output->write('  <info> Testing against the <fg=green;options=bold,underscore>' . $parserName . '</> parser... </info>');
            $result = $parser['parse']($filename);

            if (empty($result)) {
                $output->writeln(
                    '<error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>'
                );

                continue;
            }

            if (isset($result['version'])) {
                $parsers[$parserName]['metadata']['version'] = $result['version'];
            }

            if ($thisRunName) {
                if (!file_exists($this->runDir . '/' . $thisRunName . '/results/' . $parserName)) {
                    mkdir($this->runDir . '/' . $thisRunName . '/results/' . $parserName);
                }

                file_put_contents(
                    $this->runDir . '/' . $thisRunName . '/results/' . $parserName . '/' . basename($filename) . '.json',
                    json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
            }

            $rows = [];
            foreach ($result['results'] as $parsed) {
                if (!isset($parsed['parsed'])) {
                    $output->writeLn('<error>There was no "parsed" property in the result</error>');

                    continue;
                }

                if ($normalize) {
                    $parsed['parsed'] = $normalizeHelper->normalize($parsed['parsed']);
                }

                $rows[] = [
                    new TableCell('<fg=yellow>' . $parsed['useragent'] . '</>', ['colspan' => '7']),
                    round($parsed['time'], 5) . 's',
                ];
                $rows[] = [
                    $parsed['parsed']['browser']['name'],
                    $parsed['parsed']['browser']['version'],
                    $parsed['parsed']['platform']['name'],
                    $parsed['parsed']['platform']['version'],
                    $parsed['parsed']['device']['name'],
                    $parsed['parsed']['device']['brand'],
                    $parsed['parsed']['device']['type'],
                    $parsed['parsed']['device']['ismobile'],
                ];
                $rows[] = new TableSeparator();
            }

            $output->writeln('<info>done!</info>');

            array_pop($rows);

            $table->setRows($rows);

            $answer = '';

            if (!$csv && !$noOutput) {
                $table->render();

                $question = new ChoiceQuestion('What would you like to do?', ['Dump as CSV', 'Continue'], 1);

                $answer = $questionHelper->ask($input, $output, $question);
            }

            if ((!$csv && 'Dump as CSV' !== $answer) || !$csvFile) {
                continue;
            }

            $csvOutput = '';

            try {
                $title = [
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
                ];

                $csvOutput .= $this->putcsv($title, $csvFile) . "\n";
            } catch (Throwable $e) {
                $output->writeln('<error> error</error>');
            }

            foreach ($result['results'] as $parsed) {
                $out = [
                    $parsed['useragent'],
                    $parsed['parsed']['browser']['name'],
                    $parsed['parsed']['browser']['version'],
                    $parsed['parsed']['platform']['name'],
                    $parsed['parsed']['platform']['version'],
                    $parsed['parsed']['device']['name'],
                    $parsed['parsed']['device']['brand'],
                    $parsed['parsed']['device']['type'],
                    $parsed['parsed']['device']['ismobile'],
                    $parsed['time'],
                ];

                try {
                    $csvOutput .= $this->putcsv($out, $csvFile) . "\n";
                } catch (Throwable $e) {
                    $output->writeln('<error> error</error>');
                }
            }

            if ($csvFile) {
                $output->writeln('Wrote CSV data to ' . $csvFile);
            } else {
                $output->writeln($csvOutput);
                $question = new Question('Press enter to continue', 'yes');
                $questionHelper->ask($input, $output, $question);
            }
        }

        if ($thisRunName) {
            file_put_contents(
                $this->runDir . '/' . $thisRunName . '/metadata.json',
                json_encode(['parsers' => $parsers, 'date' => time(), 'file' => basename($filename)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }

        return self::SUCCESS;
    }

    /**
     * @throws Exception if cannot open file stream
     */
    private function putcsv(array $input, string $csvFile): string
    {
        $delimiter = ',';
        $enclosure = '"';

        if ($csvFile) {
            $fp = fopen($csvFile, 'a+');
        } else {
            $fp = fopen('php://temp', 'r+');
        }

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
