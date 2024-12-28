<?php

/**
 * This file is part of the mimmi20/useragent-parser-comparison package.
 *
 * Copyright (c) 2015-2024, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UserAgentParserComparison\Command\Helper\Parsers;

use function assert;
use function floor;
use function is_bool;
use function is_string;
use function log;
use function microtime;
use function round;

final class Benchmark extends Command
{
    /** @throws void */
    #[\Override]
    protected function configure(): void
    {
        $this->setName('benchmark')
            ->setDescription('Benchmarks selected parsers against a passed in file')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file to parse')
            ->addOption(
                'iterations',
                'i',
                InputOption::VALUE_REQUIRED,
                'Number of parser runs to perform per parser',
                1,
            )
            ->setHelp(
                'Runs the selected parsers against a list of useragents (provided in the passed in "file" argument). By default performs just one iteration per parser but this can be configured with the "--iterations" option.  Reports the time taken and memory use of each parser.',
            );
    }

    /** @throws void */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file       = $input->getArgument('file');
        $iterations = $input->getOption('iterations');
        assert(is_bool($iterations) || is_string($iterations) || $iterations === null);
        $iterations = (int) $iterations;

        $parserHelper = $this->getHelper('parsers');
        assert($parserHelper instanceof Parsers);

        $parsers = $parserHelper->getParsers($input, $output);

        $table = new Table($output);
        $table->setHeaders(
            ['Parser', 'Average Init Time', 'Average Parse Time', 'Average Extra Time', 'Average Memory Used'],
        );
        $rows = [];

        foreach ($parsers as $parserName => $parser) {
            $initTime  = 0;
            $parseTime = 0;
            $totalTime = 0;
            $memory    = 0;

            $output->writeln('Running against the ' . $parserName . ' parser... ');

            $progress = new ProgressBar($output, $iterations);
            $progress->start();

            for ($i = 0; $i < $iterations; ++$i) {
                $start  = microtime(true);
                $result = $parser['parse']($file, true);
                $end    = microtime(true) - $start;

                $initTime  += $result['init_time'];
                $parseTime += $result['parse_time'];
                $totalTime += $end;
                $memory    += $result['memory_used'];

                $progress->advance();
            }

            $progress->finish();
            $output->writeln('');

            $rows[] = [
                $parserName,
                round($initTime / $iterations, 3) . 's',
                round($parseTime / $iterations, 3) . 's',
                round(($totalTime - $initTime - $parseTime) / $iterations, 3) . 's',
                $this->formatBytes($memory / $iterations),
            ];
        }

        $table->setRows($rows);
        $table->render();

        return 0;
    }

    /** @throws void */
    private function formatBytes(float $bytes, int $precision = 2): string
    {
        $base     = log($bytes, 1024);
        $suffixes = ['', 'K', 'M', 'G', 'T'];

        return round(1024 ** ($base - floor($base)), $precision) . ' ' . $suffixes[(int) floor($base)];
    }
}
