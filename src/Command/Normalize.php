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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_keys;
use function assert;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class Normalize extends Command
{
    private string $runDir = __DIR__ . '/../../data/test-runs';

    /** @var array<string, array<int|string, mixed>> */
    private array $options = [];

    /** @throws void */
    protected function configure(): void
    {
        $this->setName('normalize')
            ->setDescription('Normalizes data from a test run for better analysis')
            ->addArgument(
                'run',
                InputArgument::OPTIONAL,
                'The name of the test run directory that you want to normalize',
            )
            ->setHelp('');
    }

    /** @throws JsonException */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $run = $input->getArgument('run');
        assert(is_string($run) || $run === null);

        if (empty($run)) {
            // @todo Show user the available runs, perhaps limited to 10 or something, for now, throw an error
            $output->writeln('<error>run argument is required</error>');

            return self::FAILURE;
        }

        if (!file_exists($this->runDir . '/' . $run)) {
            $output->writeln('<error>No run directory found with that id</error>');

            return self::FAILURE;
        }

        $normalizeHelper = $this->getHelper('normalize');
        assert($normalizeHelper instanceof Helper\Normalize);

        $output->writeln('<comment>Normalizing data from test run: ' . $run . '</comment>');
        $this->options = ['tests' => [], 'parsers' => []];

        if (file_exists($this->runDir . '/' . $run . '/metadata.json')) {
            try {
                $contents = file_get_contents($this->runDir . '/' . $run . '/metadata.json');

                try {
                    $this->options = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    $output->writeln(
                        '<error>An error occured while parsing metadata for run ' . $run . '</error>',
                    );

                    return self::INVALID;
                }
            } catch (Throwable) {
                $output->writeln('<error>Could not read metadata file for run ' . $run . '</error>');

                return self::INVALID;
            }
        }

        if (!empty($this->options['tests'])) {
            if (!file_exists($this->runDir . '/' . $run . '/expected/normalized')) {
                mkdir($this->runDir . '/' . $run . '/expected/normalized');
            }

            $output->writeln('<comment>Normalizing output from the test suites</comment>');

            foreach (array_keys($this->options['tests']) as $testSuite) {
                $message = sprintf(
                    '  Normalizing output from the <fg=yellow>%s</> test suite... ',
                    $testSuite,
                );

                $output->write("\r" . $message . '<info> parsing result</info>');

                if (!file_exists($this->runDir . '/' . $run . '/expected/normalized/' . $testSuite)) {
                    mkdir($this->runDir . '/' . $run . '/expected/normalized/' . $testSuite);
                }

                foreach (
                    new FilesystemIterator(
                        $this->runDir . '/' . $run . '/expected/' . $testSuite,
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

                    $data['test'] = $normalizeHelper->normalize($data['test']);

                    // Write normalized to file
                    file_put_contents(
                        $this->runDir . '/' . $run . '/expected/normalized/' . $testSuite . '/' . $testFile->getFilename(),
                        json_encode(
                            $data,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                        ),
                    );
                }

                $output->writeln("\r" . $message . '<info> done!</info>           ');
            }
        }

        if (!empty($this->options['parsers'])) {
            // Process the parser runs
            foreach (array_keys($this->options['parsers']) as $resultDir) {
                $output->writeln(
                    '<comment>Normalizing results from the ' . $resultDir . ' parser</comment>',
                );

                if (
                    !file_exists($this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized')
                ) {
                    mkdir($this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized');
                }

                if (!empty($this->options['tests'])) {
                    foreach (array_keys($this->options['tests']) as $testSuite) {
                        $message = sprintf(
                            '  Normalizing output from the <fg=yellow>%s</> test suite... ',
                            $testSuite,
                        );

                        $output->write("\r" . $message . '<info> parsing result</info>');

                        if (
                            !file_exists(
                                $this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized/' . $testSuite,
                            )
                        ) {
                            mkdir(
                                $this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized/' . $testSuite,
                            );
                        }

                        foreach (
                            new FilesystemIterator(
                                $this->runDir . '/' . $run . '/results/' . $resultDir . '/' . $testSuite,
                            ) as $resultFile
                        ) {
                            assert($resultFile instanceof SplFileInfo);

                            if (
                                $resultFile->isDir()
                                || $resultFile->getFilename() === 'metadata.json'
                            ) {
                                continue;
                            }

                            try {
                                $contents = file_get_contents($resultFile->getPathname());
                            } catch (Throwable) {
                                continue;
                            }

                            try {
                                $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                            } catch (JsonException) {
                                $output->writeln(
                                    "\r" . $message . '<error>An error occured while parsing results for the ' . $testSuite . ' test suite</error>',
                                );

                                continue;
                            }

                            if (!is_array($data['parsed'])) {
                                continue;
                            }

                            $data['parsed'] = $normalizeHelper->normalize($data['parsed']);

                            // Write normalized to file
                            file_put_contents(
                                $this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized/' . $testSuite . '/' . $resultFile->getFilename(),
                                json_encode(
                                    $data,
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                                ),
                            );
                        }

                        $output->writeln("\r" . $message . '<info> done!</info>           ');
                    }
                }

                if (empty($this->options['file'])) {
                    continue;
                }

                $testSuite = $this->options['file'];

                $message = sprintf(
                    '  Normalizing output from the <fg=yellow>%s</> test file... ',
                    $testSuite,
                );

                $output->write("\r" . $message . '<info> preparing folders</info>');

                if (
                    !file_exists($this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized/')
                ) {
                    mkdir($this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized/');
                }

                $output->write("\r" . $message . '<info> reading result   </info>');

                $contents = file_get_contents(
                    $this->runDir . '/' . $run . '/results/' . $resultDir . '/' . $testSuite . '.json',
                );

                if ($contents === false) {
                    $output->writeln(
                        "\r" . $message . '<error>An error occured while the result file for the ' . $testSuite . ' test file</error>',
                    );

                    continue;
                }

                $output->write("\r" . $message . '<info> parsing result   </info>');

                try {
                    $multiData = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $output->writeln(
                        "\r" . $message . '<error>An error occured while parsing results for the ' . $testSuite . ' test file</error>',
                    );

                    continue;
                }

                $output->write("\r" . $message . '<info> normalizing result</info>');

                foreach (array_keys($multiData['results']) as $key) {
                    if (!is_array($multiData['results'][$key]['parsed'])) {
                        continue;
                    }

                    $multiData['results'][$key]['parsed'] = $normalizeHelper->normalize(
                        $multiData['results'][$key]['parsed'],
                    );
                }

                $output->write("\r" . $message . '<info> writing normalized result</info>');

                // Write normalized to file
                file_put_contents(
                    $this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized/' . $testSuite . '.json',
                    json_encode(
                        $multiData,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                    ),
                );

                $output->writeln("\r" . $message . '<info> done!                    </info>');
            }
        }

        $output->writeln('<comment>Normalized files written to the test run\'s directory</comment>');

        return self::SUCCESS;
    }
}
