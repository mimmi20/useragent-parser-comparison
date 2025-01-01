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

namespace UserAgentParserComparison\Command\Helper;

use Closure;
use DateTimeImmutable;
use FilesystemIterator;
use JsonException;
use SplFileInfo;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_pop;
use function assert;
use function count;
use function date;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function max;
use function mkdir;
use function reset;
use function shell_exec;
use function sprintf;
use function str_replace;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const SORT_FLAG_CASE;
use const SORT_NATURAL;

final class Tests extends Helper
{
    private string $testResultDir = __DIR__ . '/../../../data/test-runs';
    private string $testDir       = __DIR__ . '/../../../tests';

    /** @throws void */
    public function getName(): string
    {
        return 'tests';
    }

    /** @throws void */
    public function getTest(InputInterface $input, OutputInterface $output): string | null
    {
        $rows  = [];
        $names = [];
        $tests = [];

        foreach (new FilesystemIterator($this->testResultDir) as $testDir) {
            assert($testDir instanceof SplFileInfo);

            if (!is_dir($testDir->getPathname())) {
                continue;
            }

            $pathName = $testDir->getPathname();
            $pathName = str_replace('\\', '/', $pathName);

            if (!file_exists($pathName . '/metadata.json')) {
                $output->writeln(
                    '<error>metadata file for test in ' . $pathName . ' does not exist</error>',
                );

                continue;
            }

            $tests[$testDir->getFilename()] = $testDir;
        }

        ksort($tests, SORT_FLAG_CASE | SORT_NATURAL);

        foreach ($tests as $testDir) {
            assert($testDir instanceof SplFileInfo);
            $pathName = $testDir->getPathname();
            $pathName = str_replace('\\', '/', $pathName);

            try {
                $contents = file_get_contents($pathName . '/metadata.json');

                try {
                    $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    $output->writeln(
                        '<error>An error occured while parsing metadata for test ' . $pathName . '</error>',
                    );

                    continue;
                }
            } catch (Throwable) {
                $output->writeln(
                    '<error>Could not read metadata file for test in ' . $pathName . '</error>',
                );

                continue;
            }

            $countRows   = max(count($metadata['tests']), count($metadata['parsers']));
            $testNames   = array_keys($metadata['tests']);
            $parserNames = array_keys($metadata['parsers']);
            $valid       = true;

            if ($countRows === 0) {
                $valid = false;
            }

            if (empty($testNames)) {
                $valid = false;
            }

            if (empty($parserNames)) {
                $valid = false;
            }

            $runName = empty($metadata['date']) ? 'n/a' : date('Y-m-d H:i:s', $metadata['date']);

            $rows[] = [
                new TableCell(
                    ($valid ? '<fg=green;bg=black>' : '<fg=red;bg=black>') . $testDir->getFilename() . '</>',
                    ['rowspan' => $countRows],
                ),
                new TableCell(
                    ($valid ? '<fg=green;bg=black>' : '<fg=red;bg=black>') . $runName . '</>',
                    ['rowspan' => $countRows],
                ),
                new TableCell(
                    empty($metadata['tests']) ? '' : $metadata['tests'][$testNames[0]]['metadata']['name'],
                ),
                new TableCell(
                    empty($metadata['tests']) ? '' : ($metadata['tests'][$testNames[0]]['metadata']['version'] ?? 'n/a'),
                ),
                new TableCell(
                    empty($metadata['parsers']) ? '' : $metadata['parsers'][$parserNames[0]]['metadata']['name'],
                ),
                new TableCell(
                    empty($metadata['parsers']) ? '' : ($metadata['parsers'][$parserNames[0]]['metadata']['version'] ?? 'n/a'),
                ),
            ];

            if ($countRows > 1) {
                for ($i = 1, $max = $countRows; $i < $max; ++$i) {
                    $rows[] = [
                        new TableCell(
                            empty($metadata['tests']) || !array_key_exists(
                                $i,
                                $testNames,
                            ) ? '' : $metadata['tests'][$testNames[$i]]['metadata']['name'],
                        ),
                        new TableCell(
                            empty($metadata['tests']) || !array_key_exists(
                                $i,
                                $testNames,
                            ) ? '' : ($metadata['tests'][$testNames[$i]]['metadata']['version'] ?? 'n/a'),
                        ),
                        new TableCell(
                            empty($metadata['parsers']) || !array_key_exists(
                                $i,
                                $parserNames,
                            ) ? '' : $metadata['parsers'][$parserNames[$i]]['metadata']['name'],
                        ),
                        new TableCell(
                            empty($metadata['parsers']) || !array_key_exists(
                                $i,
                                $parserNames,
                            ) ? '' : ($metadata['parsers'][$parserNames[$i]]['metadata']['version'] ?? 'n/a'),
                        ),
                    ];
                }
            }

            $rows[] = new TableSeparator();

            if ($valid) {
                $names[$testDir->getFilename()] = $testDir->getFilename();
            }
        }

        if (count($rows) < 1) {
            return null;
        }

        $table = new Table($output);
        $table->setHeaders(
            [
                [
                    new TableCell('Name / Date', ['rowspan' => 2, 'colspan' => 2]),
                    new TableCell(
                        'Test Suites',
                        ['colspan' => 2],
                    ),
                    new TableCell(
                        'Parsers',
                        ['colspan' => 2],
                    ),
                ],
                [
                    new TableCell('Name'),
                    new TableCell('Version'),
                    new TableCell('Name'),
                    new TableCell(
                        'Version',
                    ),
                ],
            ],
        );

        array_pop($rows);

        $table->setRows($rows);
        $table->render();

        $questions    = array_keys($names);
        $questionText = 'Select the test run to use';

        $question = new ChoiceQuestion($questionText, $questions);

        $helper = $this->helperSet->get('question');
        assert($helper instanceof QuestionHelper);
        $answer = $helper->ask($input, $output, $question);

        return $names[$answer];
    }

    /**
     * @return iterable<string, array{name: string, path: string, metadata: array<string, mixed>, command: string, build: Closure}>
     *
     * @throws void
     */
    public function collectTests(OutputInterface $output, string | null $thisRunDir): iterable
    {
        $expectedDir = $thisRunDir === null ? null : $thisRunDir . '/expected';

        foreach (new FilesystemIterator($this->testDir) as $testDir) {
            assert($testDir instanceof SplFileInfo);
            $metadata = [];
            $pathName = $testDir->getPathname();
            $pathName = str_replace('\\', '/', $pathName);
            assert(is_string($pathName));

            if (file_exists($pathName . '/metadata.json')) {
                $contents = @file_get_contents($pathName . '/metadata.json');

                if ($contents === false) {
                    $output->writeln(
                        '<error>Could not read metadata file for testsuite in ' . $testDir->getFilename() . '</error>',
                    );
                } else {
                    try {
                        $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        $output->writeln(
                            '<error>An error occured while parsing metadata for testsuite ' . $testDir->getFilename() . '</error>',
                        );
                    }
                }
            }

            $language = $metadata['language'] ?? '';
//            $local    = $metadata['local'] ?? false;
//            $api      = $metadata['api'] ?? false;

            if (is_string($metadata['packageName'])) {
                switch ($language) {
                    case 'PHP':
                        $metadata['version']      = $this->getVersionPHP(
                            $pathName,
                            $metadata['packageName'],
                        );
                        $metadata['release-date'] = $this->getUpdateDatePHP(
                            $pathName,
                            $metadata['packageName'],
                        );

                        break;
                    case 'JavaScript':
                        $metadata['version']      = $this->getVersionJS(
                            $pathName,
                            $metadata['packageName'],
                        );
                        $metadata['release-date'] = $this->getUpdateDateJS(
                            $pathName,
                            $metadata['packageName'],
                        );

                        break;
                    default:
                        $output->writeln(
                            '<error>could not detect version and release date for testsuite ' . $testDir->getFilename() . '</error>',
                        );
                }
            }

            $testName = $testDir->getFilename();

            switch ($language) {
                case 'PHP':
                    $command = match ($testName) {
                        'browser-detector', 'crawler-detect' => 'php -d memory_limit=3048M ' . $pathName . '/scripts/build.php',
                        default => 'php -d memory_limit=1024M ' . $pathName . '/scripts/build.php',
                    };

                    break;
                case 'JavaScript':
                    $command = 'php ' . $pathName . '/scripts/build.php';

                    break;
                default:
                    continue 2;
            }

            $testPath = $testDir->getFilename();

            yield $testPath => [
                'name' => $pathName,
                'path' => $testPath,
                'metadata' => $metadata,
                'command' => $command,
                'build' => static function () use ($testPath, $output, $expectedDir, $command): iterable {
                    $message = sprintf('test suite <fg=yellow>%s</>', $testPath);

                    $output->write("\r" . $message . ' <info>building test suite</info>');

                    $testOutput = shell_exec($command);

                    if ($testOutput === null || $testOutput === false) {
                        $output->writeln(
                            "\r" . $message . ' <error>There was an error with the output from the testsuite ' . $testPath . '! No content was sent.</error>',
                        );

                        return null;
                    }

                    $testOutput = trim($testOutput);

                    if ($expectedDir !== null) {
                        if (!file_exists($expectedDir . '/' . $testPath)) {
                            mkdir($expectedDir . '/' . $testPath);
                        }
                    }

                    try {
                        $tests = json_decode($testOutput, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        $output->writeln(
                            "\r" . $message . ' <error>There was an error with the output from the testsuite ' . $testPath . '! json_decode failed.</error>',
                        );

                        return null;
                    }

                    if (
                        $tests['tests'] === null
                        || !is_array($tests['tests'])
                        || $tests['tests'] === []
                    ) {
                        $output->writeln(
                            "\r" . $message . ' <error>There was an error with the output from the testsuite ' . $testPath . '! No tests were found.</error>',
                        );

                        return null;
                    }

                    foreach ($tests['tests'] as $singleTestName => $singleTestData) {
                        if ($expectedDir !== null) {
                            file_put_contents(
                                $expectedDir . '/' . $testPath . '/' . $singleTestName . '.json',
                                json_encode(
                                    ['test' => $singleTestData],
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                                ),
                            );
                        }

                        yield $singleTestName => $singleTestData;
                    }

                    if ($expectedDir !== null) {
                        file_put_contents(
                            $expectedDir . '/' . $testPath . '/metadata.json',
                            json_encode(
                                ['version' => $tests['version'] ?? null],
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                            ),
                        );
                    }
                },
            ];
        }
    }

    /**
     * Return the version of the provider
     *
     * @throws void
     */
    private function getVersionPHP(string $path, string $packageName): string | null
    {
        $installed = json_decode(
            file_get_contents($path . '/vendor/composer/installed.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $filtered = array_filter(
            $installed['packages'],
            static fn (array $value): bool => array_key_exists(
                'name',
                $value,
            ) && $packageName === $value['name'],
        );

        if ($filtered === []) {
            return null;
        }

        $filtered = reset($filtered);

        if ($filtered === [] || !array_key_exists('time', $filtered)) {
            return null;
        }

        return $filtered['version'];
    }

    /**
     * Get the last change date of the provider
     *
     * @throws void
     */
    private function getUpdateDatePHP(string $path, string $packageName): DateTimeImmutable | null
    {
        $installed = json_decode(
            file_get_contents($path . '/vendor/composer/installed.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $filtered = array_filter(
            $installed['packages'],
            static fn (array $value): bool => array_key_exists(
                'name',
                $value,
            ) && $packageName === $value['name'],
        );

        if ($filtered === []) {
            return null;
        }

        $filtered = reset($filtered);

        if ($filtered === [] || !array_key_exists('time', $filtered)) {
            return null;
        }

        return new DateTimeImmutable($filtered['time']);
    }

    /**
     * Return the version of the provider
     *
     * @throws void
     */
    private function getVersionJS(string $path, string $packageName): string | null
    {
        $installed = json_decode(
            file_get_contents($path . '/npm-shrinkwrap.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $installed['packages']['node_modules/' . $packageName]['version'] ?? $installed['dependencies'][$packageName]['version'] ?? null;
    }

    /**
     * Get the last change date of the provider
     *
     * @throws void
     */
    private function getUpdateDateJS(string $path, string $packageName): DateTimeImmutable | null
    {
        $installed = json_decode(
            file_get_contents($path . '/npm-shrinkwrap.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (isset($installed['packages']['node_modules/' . $packageName]['time'])) {
            return new DateTimeImmutable(
                $installed['packages']['node_modules/' . $packageName]['time'],
            );
        }

        if (isset($installed['dependencies'][$packageName]['time'])) {
            return new DateTimeImmutable($installed['dependencies'][$packageName]['time']);
        }

        return null;
    }
}
