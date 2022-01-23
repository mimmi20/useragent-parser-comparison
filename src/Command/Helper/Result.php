<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use Exception;
use FilesystemIterator;
use Ramsey\Uuid\Uuid;
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

class Result extends Helper
{
    private \PDO $pdo;

    /**
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getName(): string
    {
        return 'result';
    }

    public function storeResult(
        string $name,
        string $proId,
        string $uaId,
        array $singleResult,
        ?string $version = null,
        ?string $resFilename = null
    ): void
    {
        $statementSelectResult   = $this->pdo->prepare('SELECT * FROM `result` WHERE `provider_id` = :proId AND `userAgent_id` = :uaId AND `run` = :run');
        $statementInsertResult   = $this->pdo->prepare('INSERT INTO `result` (`run`, `provider_id`, `userAgent_id`, `resId`, `resProviderVersion`, `resFilename`, `resParseTime`, `resInitTime`, `resMemoryUsed`, `resLastChangeDate`, `resResultFound`, `resResultError`, `resClientName`, `resClientModus`, `resClientVersion`, `resClientManufacturer`, `resClientBits`, `resEngineName`, `resEngineVersion`, `resEngineManufacturer`, `resOsName`, `resOsMarketingName`, `resOsVersion`, `resOsManufacturer`, `resOsBits`, `resDeviceName`, `resDeviceMarketingName`, `resDeviceManufacturer`, `resDeviceBrand`, `resDeviceDualOrientation`, `resDeviceType`, `resDeviceIsMobile`, `resDeviceSimCount`, `resDeviceDisplayWidth`, `resDeviceDisplayHeight`, `resDeviceDisplayIsTouch`, `resDeviceDisplayType`, `resDeviceDisplaySize`, `resClientIsBot`, `resClientType`, `resRawResult`) VALUES (:run, :proId, :uaId, :resId, :resProviderVersion, :resFilename, :resParseTime, :resInitTime, :resMemoryUsed, :resLastChangeDate, :resResultFound, :resResultError, :resClientName, :resClientModus, :resClientVersion, :resClientManufacturer, :resClientBits, :resEngineName, :resEngineVersion, :resEngineManufacturer, :resOsName, :resOsMarketingName, :resOsVersion, :resOsManufacturer, :resOsBits, :resDeviceName, :resDeviceMarketingName, :resDeviceManufacturer, :resDeviceBrand, :resDeviceDualOrientation, :resDeviceType, :resDeviceIsMobile, :resDeviceSimCount, :resDeviceDisplayWidth, :resDeviceDisplayHeight, :resDeviceDisplayIsTouch, :resDeviceDisplayType, :resDeviceDisplaySize, :resClientIsBot, :resClientType, :resRawResult)');
        $statementUpdateResult   = $this->pdo->prepare('UPDATE `result` SET `resProviderVersion` = :resProviderVersion, `resFilename` = :resFilename, `resParseTime` = :resParseTime, `resInitTime` = :resInitTime, `resMemoryUsed` = :resMemoryUsed, `resLastChangeDate` = :resLastChangeDate, `resResultFound` = :resResultFound, `resResultError` = :resResultError, `resClientName` = :resClientName, `resClientModus` = :resClientModus, `resClientVersion` = :resClientVersion, `resClientManufacturer` = :resClientManufacturer, `resClientBits` = :resClientBits, `resEngineName` = :resEngineName, `resEngineVersion` = :resEngineVersion, `resEngineManufacturer` = :resEngineManufacturer, `resOsName` = :resOsName, `resOsMarketingName` = :resOsMarketingName, `resOsVersion` = :resOsVersion, `resOsManufacturer` = :resOsManufacturer, `resOsBits` = :resOsBits, `resDeviceName` = :resDeviceName, `resDeviceMarketingName` = :resDeviceMarketingName, `resDeviceManufacturer` = :resDeviceManufacturer, `resDeviceBrand` = :resDeviceBrand, `resDeviceDualOrientation` = :resDeviceDualOrientation, `resDeviceType` = :resDeviceType, `resDeviceIsMobile` = :resDeviceIsMobile, `resDeviceSimCount` = :resDeviceSimCount, `resDeviceDisplayWidth` = :resDeviceDisplayWidth, `resDeviceDisplayHeight` = :resDeviceDisplayHeight, `resDeviceDisplayIsTouch` = :resDeviceDisplayIsTouch, `resDeviceDisplayType` = :resDeviceDisplayType, `resDeviceDisplaySize` = :resDeviceDisplaySize, `resClientIsBot` = :resClientIsBot, `resClientType` = :resClientType, `resRawResult` = :resRawResult WHERE `resId` = :resId');

        $statementSelectResult->bindValue(':proId', $proId, \PDO::PARAM_STR);
        $statementSelectResult->bindValue(':uaId', $uaId, \PDO::PARAM_STR);
        $statementSelectResult->bindValue(':run', $name, \PDO::PARAM_STR);

        $statementSelectResult->execute();

        $dbResultResult = $statementSelectResult->fetch(\PDO::FETCH_ASSOC);

        if (false !== $dbResultResult) {
            $row2 = $dbResultResult;
        } else {
            $row2 = [
                'provider_id'  => $proId,
                'userAgent_id' => $uaId
            ];
        }

        $row2['resProviderVersion'] = $singleResult['version'] ?? $version;
        $row2['resParseTime'] = $singleResult['parse_time'] ?? null;
        $row2['resInitTime'] = $singleResult['init_time'] ?? null;
        $row2['resMemoryUsed'] = $singleResult['memory_used'] ?? null;
        $date = new \DateTimeImmutable('now');
        $row2['resLastChangeDate'] = $date->format('Y-m-d H:i:s');

        /*
         * Hydrate the result
         */
        $row2['resResultFound'] = (int) isset($singleResult['result']['parsed']);
        $row2['resResultError'] = (int) isset($singleResult['result']['err']);

        if ($row2['resResultFound']) {
            $row2 = $this->hydrateResult($row2, $singleResult['result']['parsed']);
        }

        $row2['resRawResult'] = json_encode($singleResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        /*
         * Persist
         */
        if (! isset($row2['resId'])) {
            $row2['resId'] = Uuid::uuid4()->toString();

            $statementInsertResult->bindValue(':resId', $row2['resId'], \PDO::PARAM_STR);
            $statementInsertResult->bindValue(':proId', $proId, \PDO::PARAM_STR);
            $statementInsertResult->bindValue(':uaId', $uaId, \PDO::PARAM_STR);
            $statementInsertResult->bindValue(':run', $name, \PDO::PARAM_STR);
            $statementInsertResult->bindValue(':resProviderVersion', $row2['resProviderVersion'], \PDO::PARAM_STR);

            if (null !== $resFilename) {
                $statementInsertResult->bindValue(':resFilename', str_replace('\\', '/', $resFilename));
            } else {
                $statementInsertResult->bindValue(':resFilename', null);
            }

            $statementInsertResult->bindValue(':resParseTime', $row2['resParseTime']);
            $statementInsertResult->bindValue(':resInitTime', $row2['resInitTime']);
            $statementInsertResult->bindValue(':resMemoryUsed', $row2['resMemoryUsed']);
            $statementInsertResult->bindValue(':resLastChangeDate', $row2['resLastChangeDate'], \PDO::PARAM_STR);
            $statementInsertResult->bindValue(':resResultFound', $row2['resResultFound'], \PDO::PARAM_INT);
            $statementInsertResult->bindValue(':resResultError', $row2['resResultError'], \PDO::PARAM_INT);
            $statementInsertResult->bindValue(':resClientName', $row2['resClientName'] ?? null);
            $statementInsertResult->bindValue(':resClientModus', $row2['resClientModus'] ?? null);
            $statementInsertResult->bindValue(':resClientVersion', $row2['resClientVersion'] ?? null);
            $statementInsertResult->bindValue(':resClientManufacturer', $row2['resClientManufacturer'] ?? null);
            $statementInsertResult->bindValue(':resClientBits', $row2['resClientBits'] ?? null);
            $statementInsertResult->bindValue(':resClientIsBot', $row2['resClientIsBot'] ?? null);
            $statementInsertResult->bindValue(':resClientType', $row2['resClientType'] ?? null);
            $statementInsertResult->bindValue(':resEngineName', $row2['resEngineName'] ?? null);
            $statementInsertResult->bindValue(':resEngineVersion', $row2['resEngineVersion'] ?? null);
            $statementInsertResult->bindValue(':resEngineManufacturer', $row2['resEngineManufacturer'] ?? null);
            $statementInsertResult->bindValue(':resOsName', $row2['resOsName'] ?? null);
            $statementInsertResult->bindValue(':resOsMarketingName', $row2['resOsMarketingName'] ?? null);
            $statementInsertResult->bindValue(':resOsVersion', $row2['resOsVersion'] ?? null);
            $statementInsertResult->bindValue(':resOsManufacturer', $row2['resOsManufacturer'] ?? null);
            $statementInsertResult->bindValue(':resOsBits', $row2['resOsBits'] ?? null);
            $statementInsertResult->bindValue(':resDeviceName', $row2['resDeviceName'] ?? null);
            $statementInsertResult->bindValue(':resDeviceMarketingName', $row2['resDeviceMarketingName'] ?? null);
            $statementInsertResult->bindValue(':resDeviceManufacturer', $row2['resDeviceManufacturer'] ?? null);
            $statementInsertResult->bindValue(':resDeviceBrand', $row2['resDeviceBrand'] ?? null);
            $statementInsertResult->bindValue(':resDeviceDualOrientation', $row2['resDeviceDualOrientation'] ?? null);
            $statementInsertResult->bindValue(':resDeviceType', $row2['resDeviceType'] ?? null);
            $statementInsertResult->bindValue(':resDeviceIsMobile', $row2['resDeviceIsMobile'] ?? null);
            $statementInsertResult->bindValue(':resDeviceSimCount', $row2['resDeviceSimCount'] ?? null);
            $statementInsertResult->bindValue(':resDeviceDisplayWidth', $row2['resDeviceDisplayWidth'] ?? null);
            $statementInsertResult->bindValue(':resDeviceDisplayHeight', $row2['resDeviceDisplayHeight'] ?? null);
            $statementInsertResult->bindValue(':resDeviceDisplayIsTouch', $row2['resDeviceDisplayIsTouch'] ?? null);
            $statementInsertResult->bindValue(':resDeviceDisplayType', $row2['resDeviceDisplayType'] ?? null);
            $statementInsertResult->bindValue(':resDeviceDisplaySize', $row2['resDeviceDisplaySize'] ?? null);
            $statementInsertResult->bindValue(':resRawResult', $row2['resRawResult'] ?? null);

            $statementInsertResult->execute();
        } else {
            $statementUpdateResult->bindValue(':resId', $dbResultResult['resId'], \PDO::PARAM_STR);
            $statementUpdateResult->bindValue(':resProviderVersion', $row2['resProviderVersion'], \PDO::PARAM_STR);

            if (null !== $resFilename) {
                $statementUpdateResult->bindValue(':resFilename', str_replace('\\', '/', $resFilename));
            } else {
                $statementUpdateResult->bindValue(':resFilename', null);
            }

            $statementUpdateResult->bindValue(':resParseTime', $row2['resParseTime']);
            $statementUpdateResult->bindValue(':resInitTime', $row2['resInitTime']);
            $statementUpdateResult->bindValue(':resMemoryUsed', $row2['resMemoryUsed']);
            $statementUpdateResult->bindValue(':resLastChangeDate', $row2['resLastChangeDate'], \PDO::PARAM_STR);
            $statementUpdateResult->bindValue(':resResultFound', $row2['resResultFound'], \PDO::PARAM_INT);
            $statementUpdateResult->bindValue(':resResultError', $row2['resResultError'], \PDO::PARAM_INT);
            $statementUpdateResult->bindValue(':resClientName', $row2['resClientName'] ?? null);
            $statementUpdateResult->bindValue(':resClientModus', $row2['resClientModus'] ?? null);
            $statementUpdateResult->bindValue(':resClientVersion', $row2['resClientVersion'] ?? null);
            $statementUpdateResult->bindValue(':resClientManufacturer', $row2['resClientManufacturer'] ?? null);
            $statementUpdateResult->bindValue(':resClientBits', $row2['resClientBits'] ?? null);
            $statementUpdateResult->bindValue(':resClientIsBot', $row2['resClientIsBot'] ?? null);
            $statementUpdateResult->bindValue(':resClientType', $row2['resClientType'] ?? null);
            $statementUpdateResult->bindValue(':resEngineName', $row2['resEngineName'] ?? null);
            $statementUpdateResult->bindValue(':resEngineVersion', $row2['resEngineVersion'] ?? null);
            $statementUpdateResult->bindValue(':resEngineManufacturer', $row2['resEngineManufacturer'] ?? null);
            $statementUpdateResult->bindValue(':resOsName', $row2['resOsName'] ?? null);
            $statementUpdateResult->bindValue(':resOsMarketingName', $row2['resOsMarketingName'] ?? null);
            $statementUpdateResult->bindValue(':resOsVersion', $row2['resOsVersion'] ?? null);
            $statementUpdateResult->bindValue(':resOsManufacturer', $row2['resOsManufacturer'] ?? null);
            $statementUpdateResult->bindValue(':resOsBits', $row2['resOsBits'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceName', $row2['resDeviceName'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceMarketingName', $row2['resDeviceMarketingName'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceManufacturer', $row2['resDeviceManufacturer'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceBrand', $row2['resDeviceBrand'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceDualOrientation', $row2['resDeviceDualOrientation'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceType', $row2['resDeviceType'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceIsMobile', $row2['resDeviceIsMobile'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceSimCount', $row2['resDeviceSimCount'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceDisplayWidth', $row2['resDeviceDisplayWidth'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceDisplayHeight', $row2['resDeviceDisplayHeight'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceDisplayIsTouch', $row2['resDeviceDisplayIsTouch'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceDisplayType', $row2['resDeviceDisplayType'] ?? null);
            $statementUpdateResult->bindValue(':resDeviceDisplaySize', $row2['resDeviceDisplaySize'] ?? null);
            $statementUpdateResult->bindValue(':resRawResult', $row2['resRawResult'] ?? null);

            $statementUpdateResult->execute();
        }
    }

    private function hydrateResult(array $row2, array $result): array
    {
        $toHydrate = [
            'resClientName' => $result['client']['name'] ?? null,
            'resClientModus' => $result['client']['modus'] ?? null,
            'resClientVersion' => $result['client']['version'] ?? null,
            'resClientManufacturer' => $result['client']['manufacturer'] ?? null,
            'resClientBits' => $result['client']['bits'] ?? null,
            'resClientIsBot' => $result['client']['isBot'] ?? null,
            'resClientType' => $result['client']['type'] ?? null,

            'resEngineName' => $result['engine']['name'] ?? null,
            'resEngineVersion' => $result['engine']['version'] ?? null,
            'resEngineManufacturer' => $result['engine']['manufacturer'] ?? null,

            'resOsName' => $result['platform']['name'] ?? null,
            'resOsMarketingName' => $result['platform']['marketingName'] ?? null,
            'resOsVersion' => $result['platform']['version'] ?? null,
            'resOsManufacturer' => $result['platform']['manufacturer'] ?? null,
            'resOsBits' => $result['platform']['bits'] ?? null,

            'resDeviceName' => (isset($result['device']['deviceName']) && is_scalar($result['device']['deviceName'])) ?? null,
            'resDeviceMarketingName' => $result['device']['marketingName'] ?? null,
            'resDeviceManufacturer' => $result['device']['manufacturer'] ?? null,
            'resDeviceBrand' => $result['device']['brand'] ?? null,
            'resDeviceDualOrientation' => $result['device']['dualOrientation'] ?? null,
            'resDeviceType' => $result['device']['type'] ?? null,
            'resDeviceIsMobile' => $result['device']['ismobile'] ?? null,
            'resDeviceSimCount' => $result['device']['simCount'] ?? null,
            'resDeviceDisplayWidth' => $result['device']['display']['width'] ?? null,
            'resDeviceDisplayHeight' => $result['device']['display']['height'] ?? null,
            'resDeviceDisplayIsTouch' => $result['device']['display']['touch'] ?? null,
            'resDeviceDisplayType' => $result['device']['display']['type'] ?? null,
            'resDeviceDisplaySize' => $result['device']['display']['size'] ?? null,
        ];

        return array_merge($row2, $toHydrate);
    }
}
