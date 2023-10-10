<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use Exception;
use FilesystemIterator;
use function file_get_contents;
use function json_decode;
use function ksort;
use SplFileInfo;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Tests extends Helper
{
    /**
     * @var string
     */
    private $testResultDir = __DIR__ . '/../../../data/test-runs';
    private $testDir = __DIR__ . '/../../../tests';

    public function getName(): string
    {
        return 'tests';
    }

    public function getTest(InputInterface $input, OutputInterface $output): ?string
    {
        $rows  = [];
        $names = [];
        $tests = [];

        /** @var SplFileInfo $testDir */
        foreach (new FilesystemIterator($this->testResultDir) as $testDir) {
            if (!is_dir($testDir->getPathname())) {
                continue;
            }

            $pathName = $testDir->getPathname();
            $pathName = str_replace('\\', '/', $pathName);

            if (!file_exists($pathName . '/metadata.json')) {
                $output->writeln('<error>metadata file for test in ' . $pathName . ' does not exist</error>');
                continue;
            }

            $tests[$testDir->getFilename()] = $testDir;
        }

        ksort($tests, SORT_FLAG_CASE | SORT_NATURAL);

        /** @var SplFileInfo $testDir */
        foreach ($tests as $testDir) {
            $pathName = $testDir->getPathname();
            $pathName = str_replace('\\', '/', $pathName);

            try {
                $contents = file_get_contents($pathName . '/metadata.json');

                try {
                    $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (Exception $e) {
                    $output->writeln('<error>An error occured while parsing metadata for test ' . $pathName . '</error>');
                    continue;
                }
            } catch (Exception $e) {
                $output->writeln('<error>Could not read metadata file for test in ' . $pathName . '</error>');
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
                new TableCell(($valid ? '<fg=green;bg=black>' : '<fg=red;bg=black>') . $testDir->getFilename() . '</>', ['rowspan' => $countRows]),
                new TableCell(($valid ? '<fg=green;bg=black>' : '<fg=red;bg=black>') . $runName . '</>', ['rowspan' => $countRows]),
                new TableCell(empty($metadata['tests']) ? '' : $metadata['tests'][$testNames[0]]['metadata']['name']),
                new TableCell(empty($metadata['tests']) ? '' : ($metadata['tests'][$testNames[0]]['metadata']['version'] ?? 'n/a')),
                new TableCell(empty($metadata['parsers']) ? '' : $metadata['parsers'][$parserNames[0]]['metadata']['name']),
                new TableCell(empty($metadata['parsers']) ? '' : ($metadata['parsers'][$parserNames[0]]['metadata']['version'] ?? 'n/a')),
            ];

            if ($countRows > 1) {
                for ($i = 1, $max = $countRows; $i < $max; ++$i) {
                    $rows[] = [
                        new TableCell((empty($metadata['tests']) || !array_key_exists($i, $testNames)) ? '' : $metadata['tests'][$testNames[$i]]['metadata']['name']),
                        new TableCell((empty($metadata['tests']) || !array_key_exists($i, $testNames)) ? '' : ($metadata['tests'][$testNames[$i]]['metadata']['version'] ?? 'n/a')),
                        new TableCell((empty($metadata['parsers']) || !array_key_exists($i, $parserNames)) ? '' : $metadata['parsers'][$parserNames[$i]]['metadata']['name']),
                        new TableCell((empty($metadata['parsers']) || !array_key_exists($i, $parserNames)) ? '' : ($metadata['parsers'][$parserNames[$i]]['metadata']['version'] ?? 'n/a')),
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
                [new TableCell('Name / Date', ['rowspan' => 2, 'colspan' => 2]), new TableCell('Test Suites', ['colspan' => 2]), new TableCell('Parsers', ['colspan' => 2])],
                [new TableCell('Name'), new TableCell('Version'), new TableCell('Name'), new TableCell('Version')],
            ]
        );

        array_pop($rows);

        $table->setRows($rows);
        $table->render();

        $questions    = array_keys($names);
        $questionText = 'Select the test run to use';

        $question = new ChoiceQuestion(
            $questionText,
            $questions
        );

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->helperSet->get('question');
        $answer = $helper->ask($input, $output, $question);

        return $names[$answer];
    }

    public function collectTests(OutputInterface $output, ?string $thisRunDir): iterable
    {
        $expectedDir  = $thisRunDir === null ?  null : $thisRunDir . '/expected';

        /** @var SplFileInfo $testDir */
        foreach (new FilesystemIterator($this->testDir) as $testDir) {
            $metadata = [];
            $pathName = $testDir->getPathname();
            $pathName = str_replace('\\', '/', $pathName);

            if (file_exists($pathName . '/metadata.json')) {
                $contents = @file_get_contents($pathName . '/metadata.json');

                if (false === $contents) {
                    $output->writeln('<error>Could not read metadata file for testsuite in ' . $testDir->getFilename() . '</error>');
                } else {
                    try {
                        $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException $e) {
                        $output->writeln('<error>An error occured while parsing metadata for testsuite ' . $testDir->getFilename() . '</error>');
                    }
                }
            }

            $language = $metadata['language'] ?? '';
            $local    = $metadata['local'] ?? false;
            $api      = $metadata['api'] ?? false;

            if (is_string($metadata['packageName'])) {
                switch ($language) {
                    case 'PHP':
                        $metadata['version'] = $this->getVersionPHP($pathName, $metadata['packageName']);
                        $metadata['release-date'] = $this->getUpdateDatePHP($pathName, $metadata['packageName']);
                        break;
                    case 'JavaScript':
                        $metadata['version'] = $this->getVersionJS($pathName, $metadata['packageName']);
                        $metadata['release-date'] = $this->getUpdateDateJS($pathName, $metadata['packageName']);
                        break;
                    default:
                        $output->writeln('<error>could not detect version and release date for testsuite ' . $testDir->getFilename() . '</error>');
                }
            }

            $testName = $testDir->getFilename();

            switch ($language) {
                case 'PHP':
                    switch ($testName) {
                        case 'browser-detector':
                        case 'crawler-detect':
                            $command = 'php -d memory_limit=3048M ' . $pathName . '/scripts/build.php';
                            break;
                        default:
                            $command = 'php ' . $pathName . '/scripts/build.php';
                            break;
                    }

                    break;
                case 'JavaScript':
                    $command = 'php ' . $pathName . '/scripts/build.php';
                    break;
                default:
                    continue 2;
            }

            $testPath = $testDir->getFilename();

            yield $testPath => [
                'name'     => $pathName,
                'path'     => $testPath,
                'metadata' => $metadata,
                'command'  => $command,
                'build'    => static function () use ($testPath, $output, $language, $pathName, $expectedDir, $command): iterable {
                    $message = sprintf('test suite <fg=yellow>%s</>', $testPath);

                    $output->write("\r" . $message . ' <info>building test suite</info>');

                    $testOutput = shell_exec($command);

                    if (null === $testOutput || false === $testOutput) {
                        $output->writeln("\r" . $message . ' <error>There was an error with the output from the testsuite ' . $testPath . '! No content was sent.</error>');

                        return null;
                    }

                    $testOutput = trim($testOutput);

                    if (null !== $expectedDir) {
                        if (!file_exists($expectedDir . '/' . $testPath)) {
                            mkdir($expectedDir . '/' . $testPath);
                        }
                    }

                    try {
                        $tests = json_decode($testOutput, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        $output->writeln("\r" . $message . ' <error>There was an error with the output from the testsuite ' . $testPath . '! json_decode failed.</error>');

                        return null;
                    }

                    if ($tests['tests'] === null || !is_array($tests['tests']) || $tests['tests'] === []) {
                        $output->writeln("\r" . $message . ' <error>There was an error with the output from the testsuite ' . $testPath . '! No tests were found.</error>');

                        return null;
                    }

                    foreach ($tests['tests'] as $singleTestName => $singleTestData) {
                        if (null !== $expectedDir) {
                            file_put_contents(
                                $expectedDir . '/' . $testPath . '/' . $singleTestName . '.json',
                                json_encode(['test' => $singleTestData], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                            );
                        }

                        yield $singleTestName => $singleTestData;
                    }

                    if (null !== $expectedDir) {
                        file_put_contents(
                            $expectedDir . '/' . $testPath . '/metadata.json',
                            json_encode(['version' => $tests['version'] ?? null], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                        );
                    }
                },
            ];
        }
    }

    /**
     * Return the version of the provider
     *
     * @return string|null
     */
    private function getVersionPHP(string $path, string $packageName): ?string
    {
        $installed = json_decode(file_get_contents($path . '/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);

        $filtered = array_filter(
            $installed['packages'],
            function (array $value) use ($packageName): bool {
                return array_key_exists('name', $value) && $packageName === $value['name'];
            }
        );

        if ([] === $filtered) {
            return null;
        }

        $filtered = reset($filtered);

        if ([] === $filtered || !array_key_exists('time', $filtered)) {
            return null;
        }

        return $filtered['version'];
    }

    /**
     * Get the last change date of the provider
     *
     * @return \DateTimeImmutable|null
     */
    private function getUpdateDatePHP(string $path, string $packageName): ?\DateTimeImmutable
    {
        $installed = json_decode(file_get_contents($path . '/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);

        $filtered = array_filter(
            $installed['packages'],
            function (array $value) use ($packageName): bool {
                return array_key_exists('name', $value) && $packageName === $value['name'];
            }
        );

        if ([] === $filtered) {
            return null;
        }

        $filtered = reset($filtered);

        if ([] === $filtered || !array_key_exists('time', $filtered)) {
            return null;
        }

        return new \DateTimeImmutable($filtered['time']);
    }

    /**
     * Return the version of the provider
     *
     * @return string|null
     */
    private function getVersionJS(string $path, string $packageName): ?string
    {
        $installed = json_decode(file_get_contents($path . '/npm-shrinkwrap.json'), true, 512, JSON_THROW_ON_ERROR);

        if (isset($installed['packages']['node_modules/' . $packageName]['version'])) {
            return $installed['packages']['node_modules/' . $packageName]['version'];
        }

        if (isset($installed['dependencies'][$packageName]['version'])) {
            return $installed['dependencies'][$packageName]['version'];
        }

        return null;
    }

    /**
     * Get the last change date of the provider
     *
     * @return \DateTimeImmutable|null
     */
    private function getUpdateDateJS(string $path, string $packageName): ?\DateTimeImmutable
    {
        $installed = json_decode(file_get_contents($path . '/npm-shrinkwrap.json'), true, 512, JSON_THROW_ON_ERROR);

        if (isset($installed['packages']['node_modules/' . $packageName]['time'])) {
            return new \DateTimeImmutable($installed['packages']['node_modules/' . $packageName]['time']);
        }

        if (isset($installed['dependencies'][$packageName]['time'])) {
            return new \DateTimeImmutable($installed['dependencies'][$packageName]['time']);
        }

        return null;
    }
}
