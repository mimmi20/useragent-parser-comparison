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

use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UserAgentParserComparison\Command\Helper\Parsers;

use function assert;
use function count;
use function date;
use function max;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;

use const STR_PAD_LEFT;

final class InitResults extends Command
{
    /** @throws void */
    public function __construct(private readonly PDO $pdo)
    {
        parent::__construct();
    }

    /** @throws void */
    protected function configure(): void
    {
        $this->setName('init-results')
            ->addOption(
                'run',
                'r',
                InputOption::VALUE_OPTIONAL,
                'The name of the test run, if omitted will be generated from date',
            );
    }

    /** @throws void */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getOption('run');

        if (empty($name)) {
            $name = date('YmdHis');
        }

        $resultHelper = $this->getHelper('result');
        assert($resultHelper instanceof Helper\Result);

        $statementSelectProvider = $this->pdo->prepare(
            'SELECT `proId` FROM `real-provider` WHERE `proName` = :proName',
        );

        $statementCreateTempUas = $this->pdo->prepare(
            'CREATE TEMPORARY TABLE IF NOT EXISTS `temp_userAgent` AS (SELECT * FROM `userAgent` LIMIT :start, :count)',
        );

        $output->writeln('~~~ Detect all UAs ~~~');

        $parserHelper = $this->getHelper('parsers');
        assert($parserHelper instanceof Parsers);

        $providers  = [];
        $nameLength = 0;

        foreach ($parserHelper->getAllParsers($output) as $parserPath => $parserConfig) {
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

        $currenUserAgent = 1;
        $count           = 100;
        $start           = 0;
        $providerCount   = count($providers);
        $baseMessage     = "\r";

        do {
            $this->pdo->prepare('DROP TEMPORARY TABLE IF EXISTS `temp_userAgent`')->execute();

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
                $message = $baseMessage;

                foreach ($providers as $proName => $provider) {
                    $output->write(
                        mb_str_pad($message, $providerCount + 3) . ' - Count: ' . mb_str_pad(
                            (string) $currenUserAgent,
                            8,
                            ' ',
                            STR_PAD_LEFT,
                        ) . ' - ' . mb_str_pad(
                            $proName,
                            $nameLength,
                        ),
                    );

                    [, $parserConfig, $proId] = $provider;

                    $singleResult = $parserConfig['parse-ua']($row['uaString']);

                    if ($singleResult === null) {
                        $message .= 'E';

                        $output->write(
                            mb_str_pad($message, $providerCount + 3) . ' - Count: ' . mb_str_pad(
                                (string) $currenUserAgent,
                                8,
                                ' ',
                                STR_PAD_LEFT,
                            ) . ' - ' . mb_str_pad(
                                $proName,
                                $nameLength,
                            ),
                        );

                        continue;
                    }

                    $resultHelper->storeResult($name, $proId, $row['uaId'], $singleResult);

                    $message .= '.';

                    $output->write(
                        mb_str_pad($message, $providerCount + 3) . ' - Count: ' . mb_str_pad(
                            (string) $currenUserAgent,
                            8,
                            ' ',
                            STR_PAD_LEFT,
                        ) . ' - ' . mb_str_pad(
                            $proName,
                            $nameLength,
                        ),
                    );
                }

                // display "progress"
                $output->writeln(
                    mb_str_pad($message, $providerCount + 3) . ' - Count: ' . mb_str_pad(
                        (string) $currenUserAgent,
                        8,
                        ' ',
                        STR_PAD_LEFT,
                    ) . '   ' . mb_str_pad(
                        ' ',
                        $nameLength,
                    ),
                );

                ++$currenUserAgent;
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

        return self::SUCCESS;
    }
}
