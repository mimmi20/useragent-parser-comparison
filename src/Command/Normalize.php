<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use FilesystemIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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
use function str_replace;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class Normalize extends Command
{
    private string $runDir = __DIR__ . '/../../data/test-runs';

    protected function configure(): void
    {
        $this->setName('normalize')
            ->setDescription('Normalizes data from a test run for better analysis')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run directory that you want to normalize')
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $thisRunName = $input->getArgument('run');
        assert(is_string($thisRunName) || null === $thisRunName);

        if (empty($thisRunName)) {
            // @todo Show user the available runs, perhaps limited to 10 or something, for now, throw an error
            $output->writeln('<error>run argument is required</error>');

            return self::FAILURE;
        }

        if (!file_exists($this->runDir . '/' . $thisRunName)) {
            $output->writeln('<error>No run directory found with that id</error>');

            return self::FAILURE;
        }

        $output->writeln('<comment>Normalizing data from test run: ' . $thisRunName . '</comment>');
        $options = ['tests' => [], 'parsers' => []];

        if (file_exists($this->runDir . '/' . $thisRunName . '/metadata.json')) {
            try {
                $contents = file_get_contents($this->runDir . '/' . $thisRunName . '/metadata.json');

                try {
                    $options = json_decode($contents, true, JSON_THROW_ON_ERROR);
                } catch (Throwable $e) {
                    $output->writeln('<error>An error occured while parsing metadata for run ' . $thisRunName . '</error>');
                }
            } catch (Throwable $e) {
                $output->writeln('<error>Could not read metadata file for run ' . $thisRunName . '</error>');
            }
        }

        if (!empty($options['tests'])) {
            if (!file_exists($this->runDir . '/' . $thisRunName . '/expected/normalized')) {
                mkdir($this->runDir . '/' . $thisRunName . '/expected/normalized');
            }

            $output->writeln('<comment>Processing output from the test suites</comment>');

            foreach (new FilesystemIterator($this->runDir . '/' . $thisRunName . '/expected') as $testFile) {
                assert($testFile instanceof SplFileInfo);
                if ($testFile->isDir()) {
                    continue;
                }

                $message = sprintf('%sProcessing output from the <fg=yellow>%s</> test suite... ', '  ', $testFile->getBasename('.' . $testFile->getExtension()));

                $output->write($message . '<info> parsing result</info>');

                try {
                    $contents = file_get_contents($testFile->getPathname());
                } catch (Throwable $e) {
                    continue;
                }

                try {
                    $data = json_decode($contents, true, JSON_THROW_ON_ERROR);
                } catch (Throwable $e) {
                    $output->writeln("\r" . $message . '<error>An error occured while normalizing test suite ' . $testFile->getFilename() . '</error>');

                    continue;
                }

                $normalized = $data;

                if (!is_array($data['tests'])) {
                    $output->writeln("\r" . $message . '<info> done!</info>');

                    continue;
                }

                $output->write("\r" . $message . '<info> normalizing result</info>');

                foreach ($data['tests'] as $ua => $parsed) {
                    $normalized['tests'][$ua] = $this->normalizeResult($parsed);
                }

                $output->write("\r" . $message . '<info> writing result</info>    ');

                // Write normalized to file
                file_put_contents(
                    $this->runDir . '/' . $thisRunName . '/expected/normalized/' . $testFile->getFilename(),
                    json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );

                $output->writeln("\r" . $message . '<info> done!</info>           ');
            }
        }

        if (!empty($options['parsers'])) {
            foreach (new FilesystemIterator($this->runDir . '/' . $thisRunName . '/results') as $resultDir) {
                assert($resultDir instanceof SplFileInfo);
                $parserName = $resultDir->getFilename();

                $output->writeln('<comment>Processing results from the ' . $parserName . ' parser</comment>');

                if (!file_exists($this->runDir . '/' . $thisRunName . '/results/' . $parserName . '/normalized')) {
                    mkdir($this->runDir . '/' . $thisRunName . '/results/' . $parserName . '/normalized');
                }

                foreach (new FilesystemIterator($resultDir->getPathname()) as $resultFile) {
                    assert($resultFile instanceof SplFileInfo);
                    if ($resultFile->isDir()) {
                        continue;
                    }

                    $testName = str_replace('.json', '', $resultFile->getFilename());
                    $message  = sprintf('%sProcessing results from the <fg=yellow>%s</> test suite... ', '  ', $testName);

                    $output->write($message . '<info> parsing result</info>');

                    try {
                        $contents = file_get_contents($resultFile->getPathname());
                    } catch (Throwable $e) {
                        continue;
                    }

                    try {
                        $data = json_decode($contents, true, JSON_THROW_ON_ERROR);
                    } catch (Throwable $e) {
                        $output->writeln("\r" . $message . '<error>An error occured while parsing results for the ' . $testName . ' test suite</error>');
                        $data['results'] = [];
                    }

                    $normalized = [];

                    if (!is_array($data['results'])) {
                        continue;
                    }

                    $output->write("\r" . $message . '<info> normalizing result</info>');

                    foreach ($data['results'] as $result) {
                        if (!isset($result['parsed'])) {
                            $output->writeLn('<error>There was no "parsed" property for the ' . $testName . ' test suite </error>');
                        } else {
                            $result['parsed'] = $this->normalizeResult($result['parsed']);
                            $normalized[]     = $result;
                        }
                    }

                    $output->write("\r" . $message . '<info> writing result</info>    ');

                    $data['results'] = $normalized;

                    // Write normalized to file
                    file_put_contents(
                        $this->runDir . '/' . $thisRunName . '/results/' . $parserName . '/normalized/' . $resultFile->getFilename(),
                        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    );

                    $output->writeln("\r" . $message . '<info> done!</info>           ');
                }
            }
        }

        unset($normalized);

        $output->writeln('<comment>Normalized files written to the test run\'s directory</comment>');

        return self::SUCCESS;
    }

    /**
     * @param mixed[][] $parsed
     *
     * @return mixed[]
     */
    private function normalizeResult(array $parsed): array
    {
        $normalizeHelper = $this->getHelper('normalize');
        assert($normalizeHelper instanceof \UserAgentParserComparison\Command\Helper\Normalize);

        return $normalizeHelper->normalizeParsed($parsed);
    }
}
