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

    private \PDO $pdo;

    /**
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('normalize')
            ->setDescription('Normalizes data from a test run for better analysis')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run directory that you want to normalize')
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $thisRunName */
        $thisRunName = $input->getArgument('run');

        if (empty($thisRunName)) {
            // @todo Show user the available runs, perhaps limited to 10 or something, for now, throw an error
            $output->writeln('<error>run argument is required</error>');

            return self::FAILURE;
        }

        $statementSelectResultRun  = $this->pdo->prepare('SELECT `result`.* FROM `result` WHERE `result`.`run` = :run');
        $statementSelectResultRun->bindValue(':run', $thisRunName, \PDO::PARAM_STR);
        $statementSelectResultRun->execute();

        $statementSelectResultSource  = $this->pdo->prepare('SELECT `result`.* FROM `result` WHERE `result`.`run` = :run AND `result`.`userAgent_id` = :uaId');

        /** @var Helper\Normalize $normalizeHelper */
        $normalizeHelper = $this->getHelper('normalize');

        /** @var Helper\NormalizedResult $resultHelper */
        $resultHelper = $this->getHelper('normalized-result');

        $output->writeln('<comment>Normalizing data from test run: ' . $thisRunName . '</comment>');

        while ($runRow = $statementSelectResultRun->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT)) {
            $statementSelectResultSource->bindValue(':run', '0', \PDO::PARAM_STR);
            $statementSelectResultSource->bindValue(':uaId', $runRow['userAgent_id'], \PDO::PARAM_STR);

            $statementSelectResultSource->execute();

            $sourceRow = $statementSelectResultSource->fetch(\PDO::FETCH_ASSOC);

            if (false === $sourceRow) {
                $output->writeln(sprintf('<error>Normalizing data from test run: %s - source for UA "%s" not found</error>', $thisRunName, $runRow['userAgent_id']));
                continue;
            }

            $sourceNormRow = $normalizeHelper->normalize($sourceRow);
            $resultHelper->storeResult($sourceRow['resId'], $sourceNormRow);

            $runNormRow = $normalizeHelper->normalize($runRow);
            $resultHelper->storeResult($runRow['resId'], $runNormRow);
        }

//        if (!empty($this->options['tests'])) {
//            if (!file_exists($this->runDir . '/' . $thisRunName . '/expected/normalized')) {
//                mkdir($this->runDir . '/' . $thisRunName . '/expected/normalized');
//            }
//
//            $output->writeln('<comment>Processing output from the test suites</comment>');
//
//            foreach (array_keys($this->options['tests']) as $testSuite) {
//                $message = sprintf('  Processing output from the <fg=yellow>%s</> test suite... ', $testSuite);
//
//                $output->write($message . '<info> parsing result</info>');
//
//                if (!file_exists($this->runDir . '/' . $thisRunName . '/expected/normalized/' . $testSuite)) {
//                    mkdir($this->runDir . '/' . $thisRunName . '/expected/normalized/' . $testSuite);
//                }
//
//                // Process the test files (expected data)
//                /** @var SplFileInfo $testFile */
//                foreach (new FilesystemIterator($this->runDir . '/' . $thisRunName . '/expected/' . $testSuite) as $testFile) {
//                    if ($testFile->isDir() || 'metadata.json' === $testFile->getFilename()) {
//                        continue;
//                    }
//
//                    try {
//                        $contents = file_get_contents($testFile->getPathname());
//                    } catch (Exception $e) {
//                        continue;
//                    }
//
//                    try {
//                        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
//                    } catch (Exception $e) {
//                        $output->writeln("\r" . $message . '<error>An error occured while normalizing test suite ' . $testFile->getFilename() . '</error>');
//                        continue;
//                    }
//
//                    $data['test'] = $normalizeHelper->normalize($data['test']);
//
//                    // Write normalized to file
//                    file_put_contents(
//                        $this->runDir . '/' . $thisRunName . '/expected/normalized/' . $testSuite . '/' . $testFile->getFilename(),
//                        json_encode(
//                            $data,
//                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
//                        )
//                    );
//                }
//
//                $output->writeln("\r" . $message . '<info> done!</info>           ');
//            }
//        }
//
//        if (!empty($this->options['parsers'])) {
//            // Process the parser runs
//            foreach (array_keys($this->options['parsers']) as $resultDir) {
//
//                $output->writeln('<comment>Processing results from the ' . $resultDir . ' parser</comment>');
//
//                if (!file_exists($this->runDir . '/' . $thisRunName . '/results/' . $resultDir . '/normalized')) {
//                    mkdir($this->runDir . '/' . $thisRunName . '/results/' . $resultDir . '/normalized');
//                }
//
//                foreach (array_keys($this->options['tests']) as $testSuite) {
//                    $message = sprintf('  Processing output from the <fg=yellow>%s</> test suite... ', $testSuite);
//
//                    $output->write($message . '<info> parsing result</info>');
//
//                    if (!file_exists($this->runDir . '/' . $thisRunName . '/results/' . $resultDir . '/normalized/' . $testSuite)) {
//                        mkdir($this->runDir . '/' . $thisRunName . '/results/' . $resultDir . '/normalized/' . $testSuite);
//                    }
//
//                    /** @var SplFileInfo $resultFile */
//                    foreach (new FilesystemIterator($this->runDir . '/' . $thisRunName . '/results/' . $resultDir . '/' . $testSuite) as $resultFile) {
//                        if ($resultFile->isDir() || 'metadata.json' === $resultFile->getFilename()) {
//                            continue;
//                        }
//
//                        try {
//                            $contents = file_get_contents($resultFile->getPathname());
//                        } catch (Exception $e) {
//                            continue;
//                        }
//
//                        try {
//                            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
//                        } catch (\JsonException $e) {
//                            $output->writeln("\r" . $message . '<error>An error occured while parsing results for the ' . $testName . ' test suite</error>');
//                            continue;
//                        }
//
//                        if (!is_array($data['parsed'])) {
//                            continue;
//                        }
//
//                        $data['parsed'] = $normalizeHelper->normalize($data['parsed']);
//
//                        // Write normalized to file
//                        file_put_contents(
//                            $this->runDir . '/' . $thisRunName . '/results/' . $resultDir . '/normalized/' . $testSuite . '/' . $resultFile->getFilename(),
//                            json_encode(
//                                $data,
//                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
//                            )
//                        );
//                    }
//
//                    $output->writeln("\r" . $message . '<info> done!</info>           ');
//                }
//            }
//        }

        $output->writeln('<info>done!</info>');

        return self::SUCCESS;
    }
}
