<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use FilesystemIterator;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function ksort;
use function mkdir;
use function sort;
use function sprintf;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Test extends Command
{
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
        $this->setName('test')
            ->setDescription('Runs test against the parsers')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run, if omitted will be generated from date')
            ->setHelp('Runs various test suites against the parsers to help determine which is the most "correct".');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Prepare our test directory to store the data from this run
        /** @var string|null $thisRunName */
        $thisRunName = $input->getArgument('run');

        if (empty($thisRunName)) {
            $thisRunName = date('YmdHis');
        }

        $output->writeln(sprintf('<comment>Testing data for test run: %s</comment>', $thisRunName));

        $statementCreateTempUas  = $this->pdo->prepare('CREATE TEMPORARY TABLE IF NOT EXISTS `temp_userAgent` AS (SELECT `userAgent`.* FROM `userAgent` INNER JOIN `result` ON `userAgent`.`uaId` = `result`.`userAgent_id` WHERE `result`.`provider_id` = :proId LIMIT :start, :count)');
        $statementSelectProvider = $this->pdo->prepare('SELECT `proId` FROM `real-provider` WHERE `proName` = :proName');

        $statementSelectTestProvider = $this->pdo->prepare('SELECT * FROM `useragents-general-overview`');
        $statementSelectTestProvider->execute();

        $tests = [];
        $rows  = [];
        $questions = [];

        while ($row = $statementSelectTestProvider->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT)) {
            $tests[$row['proId']] = $row['proName'];
            $questions[] = $row['proName'];
            $rows[] = [$row['proName'], $row['countNumber']];
        }

        $output->writeln('These are all available test suites, choose which you would like to run');

        $testnames = array_flip($tests);

        $table = new Table($output);
        $table->setHeaders(['Test Suite', 'Count of tests']);
        $table->setRows($rows);
        $table->render();

        $questions[] = 'All Suites';

        $questionHelper = $this->getHelper('question');
        $question       = new ChoiceQuestion(
            'Choose which test suites to run, separate multiple with commas (press enter to use all)',
            $questions,
            count($questions) - 1
        );
        $question->setMultiselect(true);

        $answers       = $questionHelper->ask($input, $output, $question);
        $selectedTests = [];

        foreach ($answers as $name) {
            if ($name === 'All Suites') {
                $selectedTests = $tests;

                break;
            }

            $selectedTests[$testnames[$name]] = $name;
        }

        $output->writeln('Choose which parsers you would like to run this test suite against');

        /** @var Helper\Parsers $parserHelper */
        $parserHelper = $this->getHelper('parsers');
        $parsers      = $parserHelper->getParsers($input, $output);

        $providers  = [];
        $nameLength = 0;

        foreach ($parsers as $parserPath => $parserConfig) {
            $proName = $parserConfig['metadata']['name'] ?? $parserPath;

            $statementSelectProvider->bindValue(':proName', $proName, \PDO::PARAM_STR);
            $statementSelectProvider->execute();

            $proId = $statementSelectProvider->fetch(\PDO::FETCH_COLUMN);

            if (!$proId) {
                $output->writeln(sprintf('<error>no provider found with name %s</error>', $proName));
                continue;
            }

            $nameLength = max($nameLength, mb_strlen($proName));

            $providers[$proName] = [$parserPath, $parserConfig, $proId];
        }

        /** @var Helper\Result $resultHelper */
        $resultHelper = $this->getHelper('result');

        foreach ($selectedTests as $id => $testName) {
            $actualTest = 0;
            $currenUserAgent = 1;
            $count           = 100;
            $start           = 0;
            $textLength   = 0;

            $basicMessage = sprintf(
                'test suite <fg=yellow>%s</>',
                $testName
            );

            if (mb_strlen($basicMessage) > $textLength) {
                $textLength = mb_strlen($basicMessage);
            }

            $output->write("\r" . str_pad($basicMessage, $textLength));

            do {
                $this->pdo->prepare('DROP TEMPORARY TABLE IF EXISTS `temp_userAgent`')->execute();

                $statementCreateTempUas->bindValue(':proId', $id, \PDO::PARAM_STR);
                $statementCreateTempUas->bindValue(':start', $start, \PDO::PARAM_INT);
                $statementCreateTempUas->bindValue(':count', $count, \PDO::PARAM_INT);

                $statementCreateTempUas->execute();

                /*
                 * load userAgents...
                 */
                $statementSelectAllUa = $this->pdo->prepare('SELECT * FROM `temp_userAgent`');
                $statementSelectAllUa->execute();

                $this->pdo->beginTransaction();

                while ($row = $statementSelectAllUa->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT)) {
                    $agent = addcslashes($row['uaString'], PHP_EOL);
                    $agentToShow = $agent;

                    ++$actualTest;

                    if (mb_strlen($agentToShow) > 100) {
                        $agentToShow = mb_substr($agentToShow, 0, 96) . ' ...';
                    }

                    $basicTestMessage = sprintf(
                        $basicMessage . ' <info>parsing</info> [%s] UA: <fg=yellow>%s</>',
                        $actualTest,
                        $agentToShow
                    );

                    if (mb_strlen($basicTestMessage) > $textLength) {
                        $textLength = mb_strlen($basicTestMessage);
                    }

                    $output->write("\r" . str_pad($basicTestMessage, $textLength));

                    foreach ($providers as $parserName => $provider) {

                        [, $parserConfig, $proId] = $provider;

                        $testMessage = $basicTestMessage . ' against the <fg=green;options=bold,underscore>' . $parserName . '</> parser ...';

                        if (mb_strlen($testMessage) > $textLength) {
                            $textLength = mb_strlen($testMessage);
                        }

                        $output->write("\r" . str_pad($testMessage, $textLength));

                        $singleResult = $parserConfig['parse-ua']($row['uaString']);

                        if (empty($singleResult)) {
                            $testMessage = $basicTestMessage . ' <error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>';

                            if (mb_strlen($testMessage) > $textLength) {
                                $textLength = mb_strlen($testMessage);
                            }

                            $output->writeln("\r" . str_pad($testMessage, $textLength));

                            continue;
                        }

                        $resultHelper->storeResult($thisRunName, $proId, $row['uaId'], $singleResult);
                    }

                    $currenUserAgent++;
                }

                $this->pdo->commit();

                $statementCountAllResults = $this->pdo->prepare('SELECT COUNT(*) AS `count` FROM `temp_userAgent`');
                $statementCountAllResults->execute();

                $colCount = $statementCountAllResults->fetch(\PDO::FETCH_COLUMN);

                $this->pdo->prepare('DROP TEMPORARY TABLE IF EXISTS `temp_userAgent`')->execute();

                $start += $count;
            } while ($colCount > 0);

            $output->writeln("\r" . str_pad($basicMessage . ' <info>done!</info>', $textLength));
        }

        $output->writeln('<info>done!</info>');

        return self::SUCCESS;
    }
}
