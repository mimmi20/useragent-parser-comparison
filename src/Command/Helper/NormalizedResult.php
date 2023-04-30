<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use PDO;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Helper\Helper;

final class NormalizedResult extends Helper
{
    /** @throws void */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @throws void */
    public function getName(): string
    {
        return 'normalized-result';
    }

    /**
     * @param array<mixed> $singleResult
     *
     * @throws void
     */
    public function storeResult(string $resId, array $singleResult): void
    {
        $statementSelectResult = $this->pdo->prepare(
            'SELECT * FROM `result-normalized` WHERE `result_id` = :resId',
        );
        $statementInsertResult = $this->pdo->prepare(
            'INSERT INTO `result-normalized` (`resNormaId`, `result_id`, `resNormaClientName`, `resNormaClientModus`, `resNormaClientVersion`, `resNormaClientManufacturer`, `resNormaClientBits`, `resNormaEngineName`, `resNormaEngineVersion`, `resNormaEngineManufacturer`, `resNormaOsName`, `resNormaOsMarketingName`, `resNormaOsVersion`, `resNormaOsManufacturer`, `resNormaOsBits`, `resNormaDeviceName`, `resNormaDeviceMarketingName`, `resNormaDeviceManufacturer`, `resNormaDeviceBrand`, `resNormaDeviceDualOrientation`, `resNormaDeviceType`, `resNormaDeviceIsMobile`, `resNormaDeviceSimCount`, `resNormaDeviceDisplayWidth`, `resNormaDeviceDisplayHeight`, `resNormaDeviceDisplayIsTouch`, `resNormaDeviceDisplayType`, `resNormaDeviceDisplaySize`, `resNormaClientIsBot`, `resNormaClientType`) VALUES (:resNormaId, :resId, :resNormaClientName, :resNormaClientModus, :resNormaClientVersion, :resNormaClientManufacturer, :resNormaClientBits, :resNormaEngineName, :resNormaEngineVersion, :resNormaEngineManufacturer, :resNormaOsName, :resNormaOsMarketingName, :resNormaOsVersion, :resNormaOsManufacturer, :resNormaOsBits, :resNormaDeviceName, :resNormaDeviceMarketingName, :resNormaDeviceManufacturer, :resNormaDeviceBrand, :resNormaDeviceDualOrientation, :resNormaDeviceType, :resNormaDeviceIsMobile, :resNormaDeviceSimCount, :resNormaDeviceDisplayWidth, :resNormaDeviceDisplayHeight, :resNormaDeviceDisplayIsTouch, :resNormaDeviceDisplayType, :resNormaDeviceDisplaySize, :resNormaClientIsBot, :resNormaClientType)',
        );
        $statementUpdateResult = $this->pdo->prepare(
            'UPDATE `result-normalized` SET `resNormaClientName` = :resNormaClientName, `resNormaClientModus` = :resNormaClientModus, `resNormaClientVersion` = :resNormaClientVersion, `resNormaClientManufacturer` = :resNormaClientManufacturer, `resNormaClientBits` = :resNormaClientBits, `resNormaEngineName` = :resNormaEngineName, `resNormaEngineVersion` = :resNormaEngineVersion, `resNormaEngineManufacturer` = :resNormaEngineManufacturer, `resNormaOsName` = :resNormaOsName, `resNormaOsMarketingName` = :resNormaOsMarketingName, `resNormaOsVersion` = :resNormaOsVersion, `resNormaOsManufacturer` = :resNormaOsManufacturer, `resNormaOsBits` = :resNormaOsBits, `resNormaDeviceName` = :resNormaDeviceName, `resNormaDeviceMarketingName` = :resNormaDeviceMarketingName, `resNormaDeviceManufacturer` = :resNormaDeviceManufacturer, `resNormaDeviceBrand` = :resNormaDeviceBrand, `resNormaDeviceDualOrientation` = :resNormaDeviceDualOrientation, `resNormaDeviceType` = :resNormaDeviceType, `resNormaDeviceIsMobile` = :resNormaDeviceIsMobile, `resNormaDeviceSimCount` = :resNormaDeviceSimCount, `resNormaDeviceDisplayWidth` = :resNormaDeviceDisplayWidth, `resNormaDeviceDisplayHeight` = :resNormaDeviceDisplayHeight, `resNormaDeviceDisplayIsTouch` = :resNormaDeviceDisplayIsTouch, `resNormaDeviceDisplayType` = :resNormaDeviceDisplayType, `resNormaDeviceDisplaySize` = :resNormaDeviceDisplaySize, `resNormaClientIsBot` = :resNormaClientIsBot, `resNormaClientType` = :resNormaClientType WHERE `resNormaId` = :resNormaId',
        );

        $statementSelectResult->bindValue(':resId', $resId, PDO::PARAM_STR);

        $statementSelectResult->execute();

        $dbResultResult = $statementSelectResult->fetch(PDO::FETCH_ASSOC);

        /*
         * Persist
         */
        if ($dbResultResult === false) {
            $singleResult['resNormaId'] = Uuid::uuid4()->toString();

            $statementInsertResult->bindValue(
                ':resNormaId',
                $singleResult['resNormaId'],
                PDO::PARAM_STR,
            );
            $statementInsertResult->bindValue(':resId', $resId, PDO::PARAM_STR);

            $statementInsertResult->bindValue(
                ':resNormaClientName',
                $singleResult['resClientName'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaClientModus',
                $singleResult['resClientModus'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaClientVersion',
                $singleResult['resClientVersion'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaClientManufacturer',
                $singleResult['resClientManufacturer'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaClientBits',
                $singleResult['resClientBits'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaClientIsBot',
                $singleResult['resClientIsBot'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaClientType',
                $singleResult['resClientType'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaEngineName',
                $singleResult['resEngineName'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaEngineVersion',
                $singleResult['resEngineVersion'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaEngineManufacturer',
                $singleResult['resEngineManufacturer'] ?? null,
            );
            $statementInsertResult->bindValue(':resNormaOsName', $singleResult['resOsName'] ?? null);
            $statementInsertResult->bindValue(
                ':resNormaOsMarketingName',
                $singleResult['resOsMarketingName'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaOsVersion',
                $singleResult['resOsVersion'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaOsManufacturer',
                $singleResult['resOsManufacturer'] ?? null,
            );
            $statementInsertResult->bindValue(':resNormaOsBits', $singleResult['resOsBits'] ?? null);
            $statementInsertResult->bindValue(
                ':resNormaDeviceName',
                $singleResult['resDeviceName'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceMarketingName',
                $singleResult['resDeviceMarketingName'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceManufacturer',
                $singleResult['resDeviceManufacturer'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceBrand',
                $singleResult['resDeviceBrand'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceDualOrientation',
                $singleResult['resDeviceDualOrientation'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceType',
                $singleResult['resDeviceType'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceIsMobile',
                $singleResult['resDeviceIsMobile'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceSimCount',
                $singleResult['resDeviceSimCount'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceDisplayWidth',
                $singleResult['resDeviceDisplayWidth'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceDisplayHeight',
                $singleResult['resDeviceDisplayHeight'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceDisplayIsTouch',
                $singleResult['resDeviceDisplayIsTouch'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceDisplayType',
                $singleResult['resDeviceDisplayType'] ?? null,
            );
            $statementInsertResult->bindValue(
                ':resNormaDeviceDisplaySize',
                $singleResult['resDeviceDisplaySize'] ?? null,
            );

            $statementInsertResult->execute();
        } else {
            $statementUpdateResult->bindValue(
                ':resNormaId',
                $dbResultResult['resNormaId'],
                PDO::PARAM_STR,
            );
            // $statementUpdateResult->bindValue(':resId', $dbResultResult['result_id'], \PDO::PARAM_STR);

            $statementUpdateResult->bindValue(
                ':resNormaClientName',
                $singleResult['resClientName'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaClientModus',
                $singleResult['resClientModus'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaClientVersion',
                $singleResult['resClientVersion'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaClientManufacturer',
                $singleResult['resClientManufacturer'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaClientBits',
                $singleResult['resClientBits'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaClientIsBot',
                $singleResult['resClientIsBot'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaClientType',
                $singleResult['resClientType'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaEngineName',
                $singleResult['resEngineName'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaEngineVersion',
                $singleResult['resEngineVersion'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaEngineManufacturer',
                $singleResult['resEngineManufacturer'] ?? null,
            );
            $statementUpdateResult->bindValue(':resNormaOsName', $singleResult['resOsName'] ?? null);
            $statementUpdateResult->bindValue(
                ':resNormaOsMarketingName',
                $singleResult['resOsMarketingName'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaOsVersion',
                $singleResult['resOsVersion'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaOsManufacturer',
                $singleResult['resOsManufacturer'] ?? null,
            );
            $statementUpdateResult->bindValue(':resNormaOsBits', $singleResult['resOsBits'] ?? null);
            $statementUpdateResult->bindValue(
                ':resNormaDeviceName',
                $singleResult['resDeviceName'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceMarketingName',
                $singleResult['resDeviceMarketingName'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceManufacturer',
                $singleResult['resDeviceManufacturer'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceBrand',
                $singleResult['resDeviceBrand'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceDualOrientation',
                $singleResult['resDeviceDualOrientation'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceType',
                $singleResult['resDeviceType'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceIsMobile',
                $singleResult['resDeviceIsMobile'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceSimCount',
                $singleResult['resDeviceSimCount'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceDisplayWidth',
                $singleResult['resDeviceDisplayWidth'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceDisplayHeight',
                $singleResult['resDeviceDisplayHeight'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceDisplayIsTouch',
                $singleResult['resDeviceDisplayIsTouch'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceDisplayType',
                $singleResult['resDeviceDisplayType'] ?? null,
            );
            $statementUpdateResult->bindValue(
                ':resNormaDeviceDisplaySize',
                $singleResult['resDeviceDisplaySize'] ?? null,
            );

            $statementUpdateResult->execute();
        }
    }
}
