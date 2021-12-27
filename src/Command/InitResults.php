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
use Ramsey\Uuid\Uuid;

class InitResults extends Command
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
        $this->setName('init-results');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $statementSelectProvider = $this->pdo->prepare('SELECT `proId` FROM `real-provider` WHERE `proName` = :proName');

        $statementCreateTempUas  = $this->pdo->prepare('CREATE TEMPORARY TABLE IF NOT EXISTS `temp_userAgent` AS (SELECT * FROM `userAgent` LIMIT :start, :count)');

        $statementSelectResult   = $this->pdo->prepare('SELECT * FROM `result` WHERE `provider_id` = :proId AND `userAgent_id` = :uaId');
        $statementInsertResult   = $this->pdo->prepare('INSERT INTO `result` (`provider_id`, `userAgent_id`, `resId`, `resProviderVersion`, `resFilename`, `resParseTime`, `resInitTime`, `resMemoryUsed`, `resLastChangeDate`, `resResultFound`, `resClientName`, `resClientVersion`, `resEngineName`, `resEngineVersion`, `resOsName`, `resOsVersion`, `resDeviceModel`, `resDeviceBrand`, `resDeviceType`, `resDeviceIsMobile`, `resDeviceIsTouch`, `resClientIsBot`, `resClientType`, `resRawResult`) VALUES (:proId, :uaId, :resId, :resProviderVersion, :resFilename, :resParseTime, :resInitTime, :resMemoryUsed, :resLastChangeDate, :resResultFound, :resClientName, :resClientVersion, :resEngineName, :resEngineVersion, :resOsName, :resOsVersion, :resDeviceModel, :resDeviceBrand, :resDeviceType, :resDeviceIsMobile, :resDeviceIsTouch, :resClientIsBot, :resClientType, :resRawResult)');
        $statementUpdateResult   = $this->pdo->prepare('UPDATE `result` SET `provider_id` = :proId, `userAgent_id` = :uaId, `resProviderVersion` = :resProviderVersion, `resFilename` = :resFilename, `resParseTime` = :resParseTime, `resInitTime` = :resInitTime, `resMemoryUsed` = :resMemoryUsed, `resLastChangeDate` = :resLastChangeDate, `resResultFound` = :resResultFound, `resClientName` = :resClientName, `resClientVersion` = :resClientVersion, `resEngineName` = :resEngineName, `resEngineVersion` = :resEngineVersion, `resOsName` = :resOsName, `resOsVersion` = :resOsVersion, `resDeviceModel` = :resDeviceModel, `resDeviceBrand` = :resDeviceBrand, `resDeviceType` = :resDeviceType, `resDeviceIsMobile` = :resDeviceIsMobile, `resDeviceIsTouch` = :resDeviceIsTouch, `resClientIsBot` = :resClientIsBot, `resClientType` = :resClientType, `resRawResult` = :resRawResult WHERE `resId` = :resId');

        $output->writeln('~~~ Detect all UAs ~~~');

        /** @var \UserAgentParserComparison\Command\Helper\Parsers $parserHelper */
        $parserHelper = $this->getHelper('parsers');

        $providers  = [];
        $nameLength = 0;

        foreach ($parserHelper->getAllParsers($output) as $parserPath => $parserConfig) {
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

        $currenUserAgent = 1;
        $count           = 100;
        $start           = 0;
        $providerCount   = count($providers);
        $baseMessage     = "\r";

        do {
            $this->pdo->prepare('DROP TEMPORARY TABLE IF EXISTS `temp_userAgent`')->execute();

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
                $message = $baseMessage;

                foreach ($providers as $proName => $provider) {
                    [$parserPath, $parserConfig, $proId] = $provider;

                    $statementSelectResult->bindValue(':proId', $proId, \PDO::PARAM_STR);
                    $statementSelectResult->bindValue(':uaId', $row['uaId'], \PDO::PARAM_STR);

                    $statementSelectResult->execute();

                    $dbResultResult = $statementSelectResult->fetch(\PDO::FETCH_ASSOC);

                    if (false !== $dbResultResult) {
                        $row2 = $dbResultResult;

                        // skip
                        if ($row['uaAdditionalHeaders'] === null && ($dbResultResult['resProviderVersion'] === $parserConfig['metadata']['version'] /* || $parserConfig['metadata']['version'] === null/**/)) {
                            $message .= 'S';

                            $output->write(str_pad($message, $providerCount + 3) . ' - Count: ' . str_pad((string) $currenUserAgent, 8, ' ', STR_PAD_LEFT) . ' - ' . str_pad($proName, $nameLength));
                            continue;
                        }
                    } else {
                        $row2 = [
                            'provider_id' => $proId,
                            'userAgent_id' => $row['uaId']
                        ];
                    }

//                    $additionalHeaders = [];
//                    if ($row['uaAdditionalHeaders'] !== null) {
//                        $additionalHeaders = json_decode($row['uaAdditionalHeaders'], true);
//                    }

                    $singleResult = $parserConfig['parse-ua']($row['uaString']);

                    if (null === $singleResult) {
                        $message .= 'E';

                        $output->write(str_pad($message, $providerCount + 3) . ' - Count: ' . str_pad((string) $currenUserAgent, 8, ' ', STR_PAD_LEFT) . ' - ' . str_pad($proName, $nameLength));

                        continue;
                    }

                    $row2['resProviderVersion'] = $singleResult['version'];
                    $row2['resParseTime'] = $singleResult['parse_time'];
                    $row2['resInitTime'] = $singleResult['init_time'];
                    $row2['resMemoryUsed'] = $singleResult['memory_used'];
                    $date = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                    $row2['resLastChangeDate'] = $date->format('Y-m-d H:i:s');

                    /*
                     * Hydrate the result
                     */
                    if (!isset($singleResult['result']['parsed'])) {
                        $row2['resResultFound'] = 0;
                    } else {
                        $row2['resResultFound'] = 1;

                        $row2 = $this->hydrateResult($row2, $singleResult['result']['parsed']);
                    }

                    /*
                     * Persist
                     */
                    if (! isset($row2['resId'])) {
                        $row2['resId'] = Uuid::uuid4()->toString();

                        $statementInsertResult->bindValue(':resId', Uuid::uuid4()->toString(), \PDO::PARAM_STR);
                        $statementInsertResult->bindValue(':proId', $proId, \PDO::PARAM_STR);
                        $statementInsertResult->bindValue(':uaId', $row['uaId'], \PDO::PARAM_STR);
                        $statementInsertResult->bindValue(':resProviderVersion', $row2['resProviderVersion'], \PDO::PARAM_STR);

                        if (array_key_exists('resFilename', $row)) {
                            $statementInsertResult->bindValue(':resFilename', str_replace('\\', '/', $row['resFilename']));
                        } else {
                            $statementInsertResult->bindValue(':resFilename', null);
                        }

                        $statementInsertResult->bindValue(':resParseTime', $row2['resParseTime']);
                        $statementInsertResult->bindValue(':resInitTime', $row2['resInitTime']);
                        $statementInsertResult->bindValue(':resMemoryUsed', $row2['resMemoryUsed']);
                        $statementInsertResult->bindValue(':resLastChangeDate', $row2['resLastChangeDate'], \PDO::PARAM_STR);
                        $statementInsertResult->bindValue(':resResultFound', $row2['resResultFound'], \PDO::PARAM_INT);

                        if (array_key_exists('resClientName', $row2) && !in_array($row2['resClientName'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementInsertResult->bindValue(':resClientName', $row2['resClientName']);
                        } else {
                            $statementInsertResult->bindValue(':resClientName', null);
                        }

                        if (array_key_exists('resClientVersion', $row2) && !in_array($row2['resClientVersion'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                            $statementInsertResult->bindValue(':resClientVersion', $row2['resClientVersion']);
                        } else {
                            $statementInsertResult->bindValue(':resClientVersion', null);
                        }

                        $statementInsertResult->bindValue(':resClientIsBot', $row2['resClientIsBot'] ?? null);

                        if (array_key_exists('resClientType', $row2) && !in_array($row2['resClientType'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementInsertResult->bindValue(':resClientType', $row2['resClientType']);
                        } else {
                            $statementInsertResult->bindValue(':resClientType', null);
                        }

                        if (array_key_exists('resEngineName', $row2) && !in_array($row2['resEngineName'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementInsertResult->bindValue(':resEngineName', $row2['resEngineName']);
                        } else {
                            $statementInsertResult->bindValue(':resEngineName', null);
                        }

                        if (array_key_exists('resEngineVersion', $row2) && !in_array($row2['resEngineVersion'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                            $statementInsertResult->bindValue(':resEngineVersion', $row2['resEngineVersion']);
                        } else {
                            $statementInsertResult->bindValue(':resEngineVersion', null);
                        }

                        if (array_key_exists('resOsName', $row2) && !in_array($row2['resOsName'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementInsertResult->bindValue(':resOsName', $row2['resOsName']);
                        } else {
                            $statementInsertResult->bindValue(':resOsName', null);
                        }

                        if (array_key_exists('resOsVersion', $row2) && !in_array($row2['resOsVersion'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                            $statementInsertResult->bindValue(':resOsVersion', $row2['resOsVersion']);
                        } else {
                            $statementInsertResult->bindValue(':resOsVersion', null);
                        }

                        if (array_key_exists('resDeviceModel', $row2) && !in_array($row2['resDeviceModel'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementInsertResult->bindValue(':resDeviceModel', $row2['resDeviceModel']);
                        } else {
                            $statementInsertResult->bindValue(':resDeviceModel', null);
                        }

                        if (array_key_exists('resDeviceBrand', $row2) && !in_array($row2['resDeviceBrand'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementInsertResult->bindValue(':resDeviceBrand', $row2['resDeviceBrand']);
                        } else {
                            $statementInsertResult->bindValue(':resDeviceBrand', null);
                        }

                        if (array_key_exists('resDeviceType', $row2) && !in_array($row2['resDeviceType'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementInsertResult->bindValue(':resDeviceType', $row2['resDeviceType']);
                        } else {
                            $statementInsertResult->bindValue(':resDeviceType', null);
                        }

                        $statementInsertResult->bindValue(':resDeviceIsMobile', $row2['resDeviceIsMobile'] ?? null);
                        $statementInsertResult->bindValue(':resDeviceIsTouch', $row2['resDeviceIsTouch'] ?? null);

                        if (array_key_exists('resRawResult', $row2) && !in_array($row2['resRawResult'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementInsertResult->bindValue(':resRawResult', $row2['resRawResult']);
                        } else {
                            $statementInsertResult->bindValue(':resRawResult', null);
                        }

                        $statementInsertResult->execute();
                    } else {
                        $statementUpdateResult->bindValue(':resId', $dbResultResult['resId'], \PDO::PARAM_STR);
                        $statementUpdateResult->bindValue(':proId', $proId, \PDO::PARAM_STR);
                        $statementUpdateResult->bindValue(':uaId', $row['uaId'], \PDO::PARAM_STR);
                        $statementUpdateResult->bindValue(':resProviderVersion', $row2['resProviderVersion'], \PDO::PARAM_STR);

                        if (array_key_exists('resFilename', $row)) {
                            $statementUpdateResult->bindValue(':resFilename', str_replace('\\', '/', $row['resFilename']));
                        } else {
                            $statementUpdateResult->bindValue(':resFilename', null);
                        }

                        $statementUpdateResult->bindValue(':resParseTime', $row2['resParseTime']);
                        $statementUpdateResult->bindValue(':resInitTime', $row2['resInitTime']);
                        $statementUpdateResult->bindValue(':resMemoryUsed', $row2['resMemoryUsed']);
                        $statementUpdateResult->bindValue(':resLastChangeDate', $row2['resLastChangeDate'], \PDO::PARAM_STR);
                        $statementUpdateResult->bindValue(':resResultFound', $row2['resResultFound'], \PDO::PARAM_INT);

                        if (array_key_exists('resClientName', $row2) && !in_array($row2['resClientName'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementUpdateResult->bindValue(':resClientName', $row2['resClientName']);
                        } else {
                            $statementUpdateResult->bindValue(':resClientName', null);
                        }

                        if (array_key_exists('resClientVersion', $row2) && !in_array($row2['resClientVersion'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                            $statementUpdateResult->bindValue(':resClientVersion', $row2['resClientVersion']);
                        } else {
                            $statementUpdateResult->bindValue(':resClientVersion', null);
                        }

                        $statementUpdateResult->bindValue(':resClientIsBot', $row2['resClientIsBot'] ?? null);

                        if (array_key_exists('resClientType', $row2) && !in_array($row2['resClientType'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementUpdateResult->bindValue(':resClientType', $row2['resClientType']);
                        } else {
                            $statementUpdateResult->bindValue(':resClientType', null);
                        }

                        if (array_key_exists('resEngineName', $row2) && !in_array($row2['resEngineName'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementUpdateResult->bindValue(':resEngineName', $row2['resEngineName']);
                        } else {
                            $statementUpdateResult->bindValue(':resEngineName', null);
                        }

                        if (array_key_exists('resEngineVersion', $row2) && !in_array($row2['resEngineVersion'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                            $statementUpdateResult->bindValue(':resEngineVersion', $row2['resEngineVersion']);
                        } else {
                            $statementUpdateResult->bindValue(':resEngineVersion', null);
                        }

                        if (array_key_exists('resOsName', $row2) && !in_array($row2['resOsName'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementUpdateResult->bindValue(':resOsName', $row2['resOsName']);
                        } else {
                            $statementUpdateResult->bindValue(':resOsName', null);
                        }

                        if (array_key_exists('resOsVersion', $row2) && !in_array($row2['resOsVersion'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                            $statementUpdateResult->bindValue(':resOsVersion', $row2['resOsVersion']);
                        } else {
                            $statementUpdateResult->bindValue(':resOsVersion', null);
                        }

                        if (array_key_exists('resDeviceModel', $row2) && !in_array($row2['resDeviceModel'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementUpdateResult->bindValue(':resDeviceModel', $row2['resDeviceModel']);
                        } else {
                            $statementUpdateResult->bindValue(':resDeviceModel', null);
                        }

                        if (array_key_exists('resDeviceBrand', $row2) && !in_array($row2['resDeviceBrand'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementUpdateResult->bindValue(':resDeviceBrand', $row2['resDeviceBrand']);
                        } else {
                            $statementUpdateResult->bindValue(':resDeviceBrand', null);
                        }

                        if (array_key_exists('resDeviceType', $row2) && !in_array($row2['resDeviceType'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementUpdateResult->bindValue(':resDeviceType', $row2['resDeviceType']);
                        } else {
                            $statementUpdateResult->bindValue(':resDeviceType', null);
                        }

                        $statementUpdateResult->bindValue(':resDeviceIsMobile', $row2['resDeviceIsMobile'] ?? null);
                        $statementUpdateResult->bindValue(':resDeviceIsTouch', $row2['resDeviceIsTouch'] ?? null);

                        if (array_key_exists('resRawResult', $row2) && !in_array($row2['resRawResult'], ['UNKNOWN', 'unknown', ''], true)) {
                            $statementUpdateResult->bindValue(':resRawResult', $row2['resRawResult']);
                        } else {
                            $statementUpdateResult->bindValue(':resRawResult', null);
                        }

                        $statementUpdateResult->execute();
                    }

                    $message .= '.';

                    echo str_pad($message, $providerCount + 3) . ' - Count: ' . str_pad((string) $currenUserAgent, 8, ' ', STR_PAD_LEFT) . ' - ' . str_pad($proName, $nameLength);
                }

                // display "progress"
                echo str_pad($message, $providerCount + 3) . ' - Count: ' . str_pad((string) $currenUserAgent, 8, ' ', STR_PAD_LEFT) . '   ' . str_pad(' ', $nameLength), PHP_EOL;

                $currenUserAgent++;
            }

            $this->pdo->commit();

            $statementCountAllResults = $this->pdo->prepare('SELECT COUNT(*) AS `count` FROM `temp_userAgent`');
            $statementCountAllResults->execute();

            $colCount = $statementCountAllResults->fetch(\PDO::FETCH_COLUMN);

            $this->pdo->prepare('DROP TEMPORARY TABLE IF EXISTS `temp_userAgent`')->execute();

            $start += $count;
        } while ($colCount > 0);

        return self::SUCCESS;
    }

    private function hydrateResult(array $row2, array $result): array
    {
        $toHydrate = [
            'resClientName' => $result['client']['name'] ?? null,
            'resClientVersion' => $result['client']['version'] ?? null,
            'resClientIsBot' => $result['client']['isBot'] ?? null,
            'resClientType' => $result['client']['type'] ?? null,

            'resEngineName' => $result['engine']['name'],
            'resEngineVersion' => $result['engine']['version'],

            'resOsName' => $result['platform']['name'],
            'resOsVersion' => $result['platform']['version'],

            'resDeviceModel' => $result['device']['name'],
            'resDeviceBrand' => $result['device']['brand'],
            'resDeviceType' => $result['device']['type'],
            'resDeviceIsMobile' => $result['device']['ismobile'],
            'resDeviceIsTouch' => $result['device']['istouch'],

            'resRawResult' => json_encode($result)
        ];

        return array_merge($row2, $toHydrate);
    }
}
