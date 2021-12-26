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

class InitUseragents extends Command
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
        $this->setName('init-useragents');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $statementSelectProvider = $this->pdo->prepare('SELECT `proId` FROM `test-provider` WHERE `proName` = :proName');

        $statementSelectUa       = $this->pdo->prepare('SELECT * FROM `userAgent` WHERE `uaHash` = :uaHash');
        $statementInsertUa       = $this->pdo->prepare('INSERT INTO `useragent` (`uaId`, `uaHash`, `uaString`, `uaAdditionalHeaders`) VALUES (:uaId, :uaHash, :uaString, :uaAdditionalHeaders)');
        $statementUpdateUa       = $this->pdo->prepare('UPDATE `useragent` SET `uaHash` = :uaHash, `uaString` = :uaString, `uaAdditionalHeaders` = :uaAdditionalHeaders WHERE `uaId` = :uaId');

        $statementSelectResult   = $this->pdo->prepare('SELECT * FROM `result` WHERE `provider_id` = :proId AND `userAgent_id` = :uaId');
        $statementInsertResult   = $this->pdo->prepare('INSERT INTO `result` (`provider_id`, `userAgent_id`, `resId`, `resProviderVersion`, `resFilename`, `resParseTime`, `resLastChangeDate`, `resResultFound`, `resClientName`, `resClientVersion`, `resEngineName`, `resEngineVersion`, `resOsName`, `resOsVersion`, `resDeviceModel`, `resDeviceBrand`, `resDeviceType`, `resDeviceIsMobile`, `resDeviceIsTouch`, `resClientIsBot`, `resClientType`, `resRawResult`) VALUES (:proId, :uaId, :resId, :resProviderVersion, :resFilename, :resParseTime, :resLastChangeDate, :resResultFound, :resClientName, :resClientVersion, :resEngineName, :resEngineVersion, :resOsName, :resOsVersion, :resDeviceModel, :resDeviceBrand, :resDeviceType, :resDeviceIsMobile, :resDeviceIsTouch, :resClientIsBot, :resClientType, :resRawResult)');
        $statementUpdateResult   = $this->pdo->prepare('UPDATE `result` SET `provider_id` = :proId, `userAgent_id` = :uaId, `resProviderVersion` = :resProviderVersion, `resFilename` = :resFilename, `resParseTime` = :resParseTime, `resLastChangeDate` = :resLastChangeDate, `resResultFound` = :resResultFound, `resClientName` = :resClientName, `resClientVersion` = :resClientVersion, `resEngineName` = :resEngineName, `resEngineVersion` = :resEngineVersion, `resOsName` = :resOsName, `resOsVersion` = :resOsVersion, `resDeviceModel` = :resDeviceModel, `resDeviceBrand` = :resDeviceBrand, `resDeviceType` = :resDeviceType, `resDeviceIsMobile` = :resDeviceIsMobile, `resDeviceIsTouch` = :resDeviceIsTouch, `resClientIsBot` = :resClientIsBot, `resClientType` = :resClientType, `resRawResult` = :resRawResult WHERE `resId` = :resId');

        $output->writeln('~~~ Load all UAs ~~~');

        /** @var \UserAgentParserComparison\Command\Helper\Tests $testHelper */
        $testHelper = $this->getHelper('tests');

        foreach ($testHelper->collectTests($output) as $testPath => $testData) {
            $proName                    = $testData['metadata']['name'] ?? $testPath;
            $proVersion                 = $testData['metadata']['version'] ?? null;

            $statementSelectProvider->bindValue(':proName', $proName, \PDO::PARAM_STR);

            $statementSelectProvider->execute();

            $proId = $statementSelectProvider->fetch(\PDO::FETCH_COLUMN);

            $message  = sprintf('test suite <fg=yellow>%s</>', $testPath);
            $messageLength = mb_strlen($message);
            $output->write($message);

            $updated  = 0;
            $inserted = 0;

            foreach ($testData['build']() as $singleTestData) {
                $agent = $singleTestData['headers']['user-agent'] ?? null;

                if (null === $agent) {
                    var_dump($singleTestData);exit;
                    $output->writeln("\r" . $message . ' <error>There was no useragent header for the testsuite ' . $testName . '.</error>');
                    continue;
                }

                $uaHash = bin2hex(sha1($agent, true));

                /*
                 * insert UA itself
                 */
                $statementSelectUa->bindValue(':uaHash', $uaHash, \PDO::PARAM_STR);

                $statementSelectUa->execute();

                $dbResultUa = $statementSelectUa->fetch(\PDO::FETCH_ASSOC);

                $additionalHeaders = $singleTestData['headers'];
                unset($additionalHeaders['user-agent']);

                if (empty($additionalHeaders)) {
                    $additionalHeaders = null;
                }


                if (false !== $dbResultUa) {
                    // update!
                    $uaId = $dbResultUa['uaId'];

                    if (null !== $additionalHeaders) {
                        $statementUpdateUa->bindValue(':uaId', $uaId, \PDO::PARAM_STR);
                        $statementUpdateUa->bindValue(':uaHash', $uaHash, \PDO::PARAM_STR);
                        $statementUpdateUa->bindValue(':uaString', $agent, \PDO::PARAM_STR);
                        $statementUpdateUa->bindValue(':uaAdditionalHeaders', json_encode($additionalHeaders));

                        $statementUpdateUa->execute();
                    }
                } else {
                    $uaId = Uuid::uuid4()->toString();

                    $statementInsertUa->bindValue(':uaId', $uaId, \PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaHash', $uaHash, \PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaString', $agent, \PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaAdditionalHeaders', json_encode($additionalHeaders));

                    $statementInsertUa->execute();
                }

                /*
                 * Result
                 */
                $date = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

                $statementSelectResult->bindValue(':proId', $proId, \PDO::PARAM_STR);
                $statementSelectResult->bindValue(':uaId', $uaId, \PDO::PARAM_STR);

                $statementSelectResult->execute();

                $dbResultResult = $statementSelectResult->fetch(\PDO::FETCH_ASSOC);

                if (false !== $dbResultResult) {
                    // update!
                    $statementUpdateResult->bindValue(':resId', $dbResultResult['resId'], \PDO::PARAM_STR);
                    $statementUpdateResult->bindValue(':proId', $proId, \PDO::PARAM_STR);
                    $statementUpdateResult->bindValue(':uaId', $uaId, \PDO::PARAM_STR);
                    $statementUpdateResult->bindValue(':resProviderVersion', $proVersion, \PDO::PARAM_STR);
                    $statementUpdateResult->bindValue(':resFilename', null);
                    $statementUpdateResult->bindValue(':resParseTime', null);
                    $statementUpdateResult->bindValue(':resLastChangeDate', $date->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                    $statementUpdateResult->bindValue(':resResultFound', 1, \PDO::PARAM_INT);

                    if (isset($singleTestData['client']['name']) && !in_array($singleTestData['client']['name'], ['UNKNOWN', 'unknown', ''], true)) {
                        $statementUpdateResult->bindValue(':resClientName', $singleTestData['client']['name']);
                    } else {
                        $statementUpdateResult->bindValue(':resClientName', null);
                    }

                    if (isset($singleTestData['client']['version']) && !in_array($singleTestData['client']['version'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                        $statementUpdateResult->bindValue(':resClientVersion', $singleTestData['client']['version']);
                    } else {
                        $statementUpdateResult->bindValue(':resClientVersion', null);
                    }

                    if (isset($singleTestData['client']['isBot'])) {
                        $statementUpdateResult->bindValue(':resClientIsBot', $singleTestData['client']['isBot']);
                    } else {
                        $statementUpdateResult->bindValue(':resClientIsBot', null);
                    }

                    if (isset($singleTestData['client']['type'])) {
                        $statementUpdateResult->bindValue(':resClientType', $singleTestData['client']['type']);
                    } else {
                        $statementUpdateResult->bindValue(':resClientType', null);
                    }

                    if (isset($singleTestData['engine']['name']) && !in_array($singleTestData['engine']['name'], ['UNKNOWN', 'unknown', ''], true)) {
                        $statementUpdateResult->bindValue(':resEngineName', $singleTestData['engine']['name']);
                    } else {
                        $statementUpdateResult->bindValue(':resEngineName', null);
                    }

                    if (isset($singleTestData['engine']['version']) && !in_array($singleTestData['engine']['version'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                        $statementUpdateResult->bindValue(':resEngineVersion', $singleTestData['engine']['version']);
                    } else {
                        $statementUpdateResult->bindValue(':resEngineVersion', null);
                    }

                    if (isset($singleTestData['platform']['name']) && !in_array(isset($singleTestData['platform']['name']), ['UNKNOWN', 'unknown', ''], true)) {
                        $statementUpdateResult->bindValue(':resOsName', $singleTestData['platform']['name']);
                    } else {
                        $statementUpdateResult->bindValue(':resOsName', null);
                    }

                    if (isset($singleTestData['platform']['version']) && !in_array($singleTestData['platform']['version'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                        $statementUpdateResult->bindValue(':resOsVersion', $singleTestData['platform']['version']);
                    } else {
                        $statementUpdateResult->bindValue(':resOsVersion', null);
                    }

                    if (isset($singleTestData['device']['name']) && !in_array(isset($singleTestData['device']['name']), ['UNKNOWN', 'unknown', ''], true)) {
                        $statementUpdateResult->bindValue(':resDeviceModel', $singleTestData['device']['name']);
                    } else {
                        $statementUpdateResult->bindValue(':resDeviceModel', null);
                    }

                    if (isset($singleTestData['device']['brand']) && !in_array($singleTestData['device']['brand'], ['UNKNOWN', 'unknown', ''], true)) {
                        $statementUpdateResult->bindValue(':resDeviceBrand', $singleTestData['device']['brand']);
                    } else {
                        $statementUpdateResult->bindValue(':resDeviceBrand', null);
                    }

                    if (isset($singleTestData['device']['type']) && !in_array($singleTestData['device']['type'], ['UNKNOWN', 'unknown', ''], true)) {
                        $statementUpdateResult->bindValue(':resDeviceType', $singleTestData['device']['type']);
                    } else {
                        $statementUpdateResult->bindValue(':resDeviceType', null);
                    }

                    $statementUpdateResult->bindValue(':resDeviceIsMobile', $singleTestData['device']['ismobile'] ?? null);
                    $statementUpdateResult->bindValue(':resDeviceIsTouch', $singleTestData['device']['istouch'] ?? null);

                    if (array_key_exists('raw', $singleTestData)) {
                        $statementUpdateResult->bindValue(':resRawResult', json_encode($singleTestData['raw'], JSON_THROW_ON_ERROR));
                    } else {
                        $statementUpdateResult->bindValue(':resRawResult', null);
                    }

                    $statementUpdateResult->execute();

                    ++$updated;
                } else {
                    $statementInsertResult->bindValue(':resId', Uuid::uuid4()->toString(), \PDO::PARAM_STR);
                    $statementInsertResult->bindValue(':proId', $proId, \PDO::PARAM_STR);
                    $statementInsertResult->bindValue(':uaId', $uaId, \PDO::PARAM_STR);
                    $statementInsertResult->bindValue(':resProviderVersion', $proVersion, \PDO::PARAM_STR);
                    $statementInsertResult->bindValue(':resFilename', null);
                    $statementInsertResult->bindValue(':resParseTime', null);
                    $statementInsertResult->bindValue(':resLastChangeDate', $date->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                    $statementInsertResult->bindValue(':resResultFound', 1, \PDO::PARAM_INT);

                    if (isset($singleTestData['client']['name']) && !in_array($singleTestData['client']['name'], ['UNKNOWN', 'unknown', ''], true)) {
                        $statementInsertResult->bindValue(':resClientName', $singleTestData['client']['name']);
                    } else {
                        $statementInsertResult->bindValue(':resClientName', null);
                    }

                    if (isset($singleTestData['client']['version']) && !in_array($singleTestData['client']['version'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                        $statementInsertResult->bindValue(':resClientVersion', $singleTestData['client']['version']);
                    } else {
                        $statementInsertResult->bindValue(':resClientVersion', null);
                    }

                    if (isset($singleTestData['client']['isBot'])) {
                        $statementInsertResult->bindValue(':resClientIsBot', $singleTestData['client']['isBot']);
                    } else {
                        $statementInsertResult->bindValue(':resClientIsBot', null);
                    }

                    if (isset($singleTestData['client']['type'])) {
                        $statementInsertResult->bindValue(':resClientType', $singleTestData['client']['type']);
                    } else {
                        $statementInsertResult->bindValue(':resClientType', null);
                    }

                    if (isset($singleTestData['engine']['name']) && !in_array($singleTestData['engine']['name'], ['UNKNOWN', 'unknown', ''], true)) {
                        $statementInsertResult->bindValue(':resEngineName', $singleTestData['engine']['name']);
                    } else {
                        $statementInsertResult->bindValue(':resEngineName', null);
                    }

                    if (isset($singleTestData['engine']['version']) && !in_array($singleTestData['engine']['version'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                        $statementInsertResult->bindValue(':resEngineVersion', $singleTestData['engine']['version']);
                    } else {
                        $statementInsertResult->bindValue(':resEngineVersion', null);
                    }

                    if (isset($singleTestData['platform']['name']) && !in_array(isset($singleTestData['platform']['name']), ['UNKNOWN', 'unknown', ''], true)) {
                        $statementInsertResult->bindValue(':resOsName', $singleTestData['platform']['name']);
                    } else {
                        $statementInsertResult->bindValue(':resOsName', null);
                    }

                    if (isset($singleTestData['platform']['version']) && !in_array($singleTestData['platform']['version'], ['UNKNOWN', 'unknown', '0.0', ''], true)) {
                        $statementInsertResult->bindValue(':resOsVersion', $singleTestData['platform']['version']);
                    } else {
                        $statementInsertResult->bindValue(':resOsVersion', null);
                    }

                    if (isset($singleTestData['device']['name']) && !in_array(isset($singleTestData['device']['name']), ['UNKNOWN', 'unknown', ''], true)) {
                        $statementInsertResult->bindValue(':resDeviceModel', $singleTestData['device']['name']);
                    } else {
                        $statementInsertResult->bindValue(':resDeviceModel', null);
                    }

                    if (isset($singleTestData['device']['brand']) && !in_array($singleTestData['device']['brand'], ['UNKNOWN', 'unknown', ''], true)) {
                        $statementInsertResult->bindValue(':resDeviceBrand', $singleTestData['device']['brand']);
                    } else {
                        $statementInsertResult->bindValue(':resDeviceBrand', null);
                    }

                    if (isset($singleTestData['device']['type']) && !in_array($singleTestData['device']['type'], ['UNKNOWN', 'unknown', ''], true)) {
                        $statementInsertResult->bindValue(':resDeviceType', $singleTestData['device']['type']);
                    } else {
                        $statementInsertResult->bindValue(':resDeviceType', null);
                    }

                    $statementInsertResult->bindValue(':resDeviceIsMobile', $singleTestData['device']['ismobile'] ?? null);
                    $statementInsertResult->bindValue(':resDeviceIsTouch', $singleTestData['device']['istouch'] ?? null);

                    if (array_key_exists('raw', $singleTestData)) {
                        $statementInsertResult->bindValue(':resRawResult', json_encode($singleTestData['raw'], JSON_THROW_ON_ERROR));
                    } else {
                        $statementInsertResult->bindValue(':resRawResult', null);
                    }

                    $statementInsertResult->execute();

                    ++$inserted;
                }

                $updateMessage = $message . sprintf(' <info>importing</info> [inserted: %d, updated: %d]', $inserted, $updated);
                $messageLength = mb_strlen($updateMessage);
                $output->write("\r" . $updateMessage);
            }

            $output->writeln("\r" . $message . str_pad(' <info>done</info>', $messageLength));
        }

        return self::SUCCESS;
    }
}
