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

use Override;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

use function addcslashes;
use function array_flip;
use function assert;
use function count;
use function date;
use function is_string;
use function max;
use function mb_str_pad;
use function mb_strlen;
use function mb_substr;
use function sprintf;
use function strip_tags;

use const PHP_EOL;
use const STR_PAD_LEFT;

final class Test extends Command
{
    /** @throws void */
    public function __construct(private readonly PDO $pdo)
    {
        parent::__construct();
    }

    /** @throws void */
    #[Override]
    protected function configure(): void
    {
        $this->setName('test')
            ->setDescription('Runs test against the parsers')
            ->addArgument(
                'run',
                InputArgument::OPTIONAL,
                'The name of the test run, if omitted will be generated from date',
            )
            ->setHelp(
                'Runs various test suites against the parsers to help determine which is the most "correct".',
            );
    }

    /** @throws void */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('~~~ Testing all UAs ~~~');

        $thisRunName = $input->getArgument('run');
        assert(is_string($thisRunName) || $thisRunName === null);

        if (empty($thisRunName)) {
            $thisRunName = date('YmdHis');
        }

        $output->writeln(sprintf('<comment>Testing data for test run: %s</comment>', $thisRunName));

        $statementCreateTempUas           = $this->pdo->prepare(
            'CREATE TEMPORARY TABLE IF NOT EXISTS `temp_userAgent` AS (SELECT `userAgent`.* FROM `userAgent` INNER JOIN `result` ON `userAgent`.`uaId` = `result`.`userAgent_id` WHERE `result`.`provider_id` = :proId LIMIT :start, :count)',
        );
        $statementSelectProvider          = $this->pdo->prepare(
            'SELECT `proId` FROM `real-provider` WHERE `proName` = :proName',
        );
        $statementSelectTestCountProvider = $this->pdo->prepare(
            'SELECT `countNumber` FROM `useragents-general-overview` WHERE `proName` = :proName',
        );

        $statementSelectTestProvider = $this->pdo->prepare(
            'SELECT * FROM `useragents-general-overview`',
        );
        $statementSelectTestProvider->execute();

        $tests     = [];
        $rows      = [];
        $questions = [];

        while ($row = $statementSelectTestProvider->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
            $tests[$row['proId']] = $row['proName'];
            $questions[]          = $row['proName'];
            $rows[]               = [$row['proName'], $row['countNumber']];
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
            count($questions) - 1,
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

        $parserHelper = $this->getHelper('parsers');
        assert($parserHelper instanceof Helper\Parsers);
        $parsers = $parserHelper->getParsers($input, $output);

        $providers  = [];
        $nameLength = 0;

        foreach ($parsers as $parserPath => $parserConfig) {
            $proName = $parserConfig['metadata']['name'] ?? $parserPath;

            $statementSelectProvider->bindValue(':proName', $proName, PDO::PARAM_STR);
            $statementSelectProvider->execute();

            $proId = $statementSelectProvider->fetch(PDO::FETCH_COLUMN);

            if (!$proId) {
                $output->writeln(sprintf('<error>no provider found with name %s</error>', $proName));

                continue;
            }

            $nameLength = max($nameLength, mb_strlen((string) $proName));

            $providers[$proName] = [$parserPath, $parserConfig, $proId];
        }

        $resultHelper = $this->getHelper('result');
        assert($resultHelper instanceof Helper\Result);

        $normalizeHelper = $this->getHelper('normalize');
        assert($normalizeHelper instanceof Helper\Normalize);

        foreach ($selectedTests as $id => $testName) {
            $actualTest = 0;
            $count      = 100;
            $start      = 0;

            $basicMessage = sprintf('test suite <fg=yellow>%s</>', $testName);

            $output->writeln("\r" . $basicMessage);

            $statementSelectTestCountProvider->bindValue(':proName', $testName, PDO::PARAM_STR);
            $statementSelectTestCountProvider->execute();

            $testCount = $statementSelectTestCountProvider->fetch(PDO::FETCH_COLUMN);

            do {
                $this->pdo->prepare('DROP TEMPORARY TABLE IF EXISTS `temp_userAgent`')->execute();

                $statementCreateTempUas->bindValue(':proId', $id, PDO::PARAM_STR);
                $statementCreateTempUas->bindValue(':start', $start, PDO::PARAM_INT);
                $statementCreateTempUas->bindValue(':count', $count, PDO::PARAM_INT);

                $statementCreateTempUas->execute();

                /*
                 * load userAgents...
                 */
                $statementSelectAllUa = $this->pdo->prepare('SELECT * FROM `temp_userAgent`');
                $statementSelectAllUa->execute();

                $this->pdo->beginTransaction();

                while ($row = $statementSelectAllUa->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $agentToShow = addcslashes((string) $row['uaString'], PHP_EOL);

                    ++$actualTest;

                    if (mb_strlen($agentToShow) > 100 - $nameLength) {
                        $agentToShow = mb_substr($agentToShow, 0, 96 - $nameLength) . ' ...';
                    }

                    $actualTestToShow = mb_str_pad(
                        (string) $actualTest,
                        mb_strlen((string) $testCount),
                        ' ',
                        STR_PAD_LEFT,
                    );

                    $basicTestMessage = sprintf(
                        $basicMessage . ' <info>parsing</info> [%s/%s] UA: <fg=yellow>%s</>',
                        $actualTestToShow,
                        $testCount,
                        $agentToShow,
                    );

                    $textLength = mb_strlen(strip_tags($basicTestMessage));

                    $output->write("\r" . $basicTestMessage);

                    foreach ($providers as $parserName => $provider) {
                        [, $parserConfig, $proId] = $provider;

                        $testMessage = $basicTestMessage . ' against the <fg=green;options=bold,underscore>' . $parserName . '</> parser ...';

                        if (mb_strlen($testMessage) > $textLength) {
                            $textLength = mb_strlen($testMessage);
                        }

                        $output->write("\r" . mb_str_pad($testMessage, $textLength));

                        $singleResult = $parserConfig['parse-ua']($row['uaString']);

                        if (empty($singleResult)) {
                            $testMessage = $basicTestMessage . ' <error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>';

                            if (mb_strlen($testMessage) > $textLength) {
                                $textLength = mb_strlen($testMessage);
                            }

                            $output->writeln("\r" . mb_str_pad($testMessage, $textLength));

                            continue;
                        }

                        //                        $normalizedDevice   = $normalizeHelper->normalize(
                        //                            [
                        //                                'devicename' => $singleResult['result']['parsed']['device']['deviceName'] ?? null,
                        //                                'devicemarketingname' => $singleResult['result']['parsed']['device']['marketingName'] ?? null,
                        //                                'devicemanufacturer' => $singleResult['result']['parsed']['device']['manufacturer'] ?? null,
                        //                                'devicebrand' => $singleResult['result']['parsed']['device']['brand'] ?? null,
                        //                                'devicetype' => $singleResult['result']['parsed']['device']['type'] ?? null,
                        //                            ],
                        //                        );
                        //                        $normalizedClient   = $normalizeHelper->normalize(
                        //                            [
                        //                                'clientname' => $singleResult['result']['parsed']['client']['name'] ?? null,
                        //                                'clientversion' => $singleResult['result']['parsed']['client']['version'] ?? null,
                        //                                'clientmanufacturer' => $singleResult['result']['parsed']['client']['manufacturer'] ?? null,
                        //                                'clienttype' => $singleResult['result']['parsed']['client']['type'] ?? null,
                        //                            ],
                        //                        );
                        //                        $normalizedPlatform = $normalizeHelper->normalize(
                        //                            [
                        //                                'osname' => $singleResult['result']['parsed']['platform']['name'] ?? null,
                        //                                'osversion' => $singleResult['result']['parsed']['platform']['version'] ?? null,
                        //                                'osmarketingname' => $singleResult['result']['parsed']['platform']['marketingName'] ?? null,
                        //                                'osmanufacturer' => $singleResult['result']['parsed']['platform']['manufacturer'] ?? null,
                        //                            ],
                        //                        );
                        //                        $normalizedEngine   = $normalizeHelper->normalize(
                        //                            [
                        //                                'enginename' => $singleResult['result']['parsed']['engine']['name'] ?? null,
                        //                                'engineversion' => $singleResult['result']['parsed']['engine']['version'] ?? null,
                        //                                'enginemanufacturer' => $singleResult['result']['parsed']['engine']['manufacturer'] ?? null,
                        //                            ],
                        //                        );

                        $resultHelper->storeResult($thisRunName, $proId, $row['uaId'], $singleResult);
                    }

                    $testMessage = $basicTestMessage . ' <info>done!</info>';

                    if (mb_strlen($testMessage) > $textLength) {
                        $textLength = mb_strlen($testMessage);
                    }

                    $output->writeln("\r" . mb_str_pad($testMessage, $textLength));
                }

                $this->pdo->commit();

                $statementCountAllResults = $this->pdo->prepare(
                    'SELECT COUNT(*) AS `count` FROM `temp_userAgent`',
                );
                $statementCountAllResults->execute();

                $colCount = $statementCountAllResults->fetch(PDO::FETCH_COLUMN);

                $this->pdo->prepare('DROP TEMPORARY TABLE IF EXISTS `temp_userAgent`')->execute();

                $start += $count;
            } while (0 < $colCount);

            $output->writeln("\r" . $basicMessage . ' <info>done!</info>');
        }

        $output->writeln('<info>done!</info>');

        return self::SUCCESS;
    }
}
