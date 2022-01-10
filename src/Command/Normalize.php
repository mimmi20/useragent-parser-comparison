<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use FilesystemIterator;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function mkdir;
use function sprintf;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Normalize extends Command
{
    /**
     * @var string
     */
    private string $runDir = __DIR__ . '/../../data/test-runs';

    /**
     * @var array
     */
    private array $options = [];

    protected function configure(): void
    {
        $this->setName('normalize')
            ->setDescription('Normalizes data from a test run for better analysis')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run directory that you want to normalize')
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $run */
        $run = $input->getArgument('run');

        if (empty($run)) {
            // @todo Show user the available runs, perhaps limited to 10 or something, for now, throw an error
            $output->writeln('<error>run argument is required</error>');

            return self::FAILURE;
        }

        if (!file_exists($this->runDir . '/' . $run)) {
            $output->writeln('<error>No run directory found with that id</error>');

            return self::FAILURE;
        }

        /** @var \UserAgentParserComparison\Command\Helper\Normalize $normalizeHelper */
        $normalizeHelper = $this->getHelper('normalize');

        $output->writeln('<comment>Normalizing data from test run: ' . $run . '</comment>');
        $this->options = ['tests' => [], 'parsers' => []];

        if (file_exists($this->runDir . '/' . $run . '/metadata.json')) {
            try {
                $contents = file_get_contents($this->runDir . '/' . $run . '/metadata.json');

                try {
                    $this->options = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (Exception $e) {
                    $output->writeln('<error>An error occured while parsing metadata for run ' . $run . '</error>');

                    return self::INVALID;
                }
            } catch (Exception $e) {
                $output->writeln('<error>Could not read metadata file for run ' . $run . '</error>');

                return self::INVALID;
            }
        }

        if (!empty($this->options['tests'])) {
            if (!file_exists($this->runDir . '/' . $run . '/expected/normalized')) {
                mkdir($this->runDir . '/' . $run . '/expected/normalized');
            }

            $output->writeln('<comment>Processing output from the test suites</comment>');

            foreach (array_keys($this->options['tests']) as $testSuite) {
                $message = sprintf('  Processing output from the <fg=yellow>%s</> test suite... ', $testSuite);

                $output->write($message . '<info> parsing result</info>');

                if (!file_exists($this->runDir . '/' . $run . '/expected/normalized/' . $testSuite)) {
                    mkdir($this->runDir . '/' . $run . '/expected/normalized/' . $testSuite);
                }

                // Process the test files (expected data)
                /** @var SplFileInfo $testFile */
                foreach (new FilesystemIterator($this->runDir . '/' . $run . '/expected/' . $testSuite) as $testFile) {
                    if ($testFile->isDir() || 'metadata.json' === $testFile->getFilename()) {
                        continue;
                    }

                    try {
                        $contents = file_get_contents($testFile->getPathname());
                    } catch (Exception $e) {
                        continue;
                    }

                    try {
                        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    } catch (Exception $e) {
                        $output->writeln("\r" . $message . '<error>An error occured while normalizing test suite ' . $testFile->getFilename() . '</error>');
                        continue;
                    }

                    $data['test'] = $normalizeHelper->normalize($data['test']);

                    // Write normalized to file
                    file_put_contents(
                        $this->runDir . '/' . $run . '/expected/normalized/' . $testSuite . '/' . $testFile->getFilename(),
                        json_encode(
                            $data,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                        )
                    );
                }

                $output->writeln("\r" . $message . '<info> done!</info>           ');
            }
        }

        if (!empty($this->options['parsers'])) {
            // Process the parser runs
            foreach (array_keys($this->options['parsers']) as $resultDir) {

                $output->writeln('<comment>Processing results from the ' . $resultDir . ' parser</comment>');

                if (!file_exists($this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized')) {
                    mkdir($this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized');
                }

                foreach (array_keys($this->options['tests']) as $testSuite) {
                    $message = sprintf('  Processing output from the <fg=yellow>%s</> test suite... ', $testSuite);

                    $output->write($message . '<info> parsing result</info>');

                    if (!file_exists($this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized/' . $testSuite)) {
                        mkdir($this->runDir . '/' . $run . '/results/' . $resultDir . '/normalized/' . $testSuite);
                    }

                    /** @var SplFileInfo $resultFile */
                    foreach (new FilesystemIterator($this->runDir . '/' . $run . '/results/' . $resultDir . '/' . $testSuite) as $resultFile) {
                        if ($resultFile->isDir() || 'metadata.json' === $resultFile->getFilename()) {
                            continue;
                        }

                        try {
                            $contents = file_get_contents($resultFile->getPathname());
                        } catch (Exception $e) {
                            continue;
                        }

                        try {
                            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                        } catch (\JsonException $e) {
                            $output->writeln("\r" . $message . '<error>An error occured while parsing results for the ' . $testName . ' test suite</error>');
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
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                            )
                        );
                    }

                    $output->writeln("\r" . $message . '<info> done!</info>           ');
                }
            }
        }

        $output->writeln('<comment>Normalized files written to the test run\'s directory</comment>');

        return self::SUCCESS;
    }
}
