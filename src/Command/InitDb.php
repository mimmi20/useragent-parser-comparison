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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class InitDb extends Command
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
        $this->setName('init-db');
    }

    /**
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('~~~ initialize database ~~~');

        $this->pdo->prepare('DROP TABLE IF EXISTS `provider`')->execute();
        $this->pdo->prepare('CREATE TABLE IF NOT EXISTS `provider` (
    `proId` CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
    `proType` VARCHAR(255) NOT NULL,
    `proName` VARCHAR(255) NOT NULL,
    `proVersion` VARCHAR(255) DEFAULT NULL,
    `proLastReleaseDate` DATETIME NULL DEFAULT NULL,
    `proPackageName` VARCHAR(255) DEFAULT NULL,
    `proHomepage` VARCHAR(255) DEFAULT NULL,
    `proLanguage` VARCHAR(255) DEFAULT NULL,
    `proIsLocal` TINYINT(1) NOT NULL,
    `proIsApi` TINYINT(1) NOT NULL,
    `proIsActive` TINYINT(1) NOT NULL,
    `proCanDetectClientName` TINYINT(1) NOT NULL,
    `proCanDetectClientModus` TINYINT(1) NOT NULL,
    `proCanDetectClientVersion` TINYINT(1) NOT NULL,
    `proCanDetectClientManufacturer` TINYINT(1) NOT NULL,
    `proCanDetectClientBits` TINYINT(1) NOT NULL,
    `proCanDetectClientIsBot` TINYINT(1) NOT NULL,
    `proCanDetectClientType` TINYINT(1) NOT NULL,
    `proCanDetectEngineName` TINYINT(1) NOT NULL,
    `proCanDetectEngineVersion` TINYINT(1) NOT NULL,
    `proCanDetectEngineManufacturer` TINYINT(1) NOT NULL,
    `proCanDetectOsName` TINYINT(1) NOT NULL,
    `proCanDetectOsMarketingName` TINYINT(1) NOT NULL,
    `proCanDetectOsVersion` TINYINT(1) NOT NULL,
    `proCanDetectOsManufacturer` TINYINT(1) NOT NULL,
    `proCanDetectOsBits` TINYINT(1) NOT NULL,
    `proCanDetectDeviceName` TINYINT(1) NOT NULL,
    `proCanDetectDeviceMarketingName` TINYINT(1) NOT NULL,
    `proCanDetectDeviceManufacturer` TINYINT(1) NOT NULL,
    `proCanDetectDeviceBrand` TINYINT(1) NOT NULL,
    `proCanDetectDeviceDualOrientation` TINYINT(1) NOT NULL,
    `proCanDetectDeviceType` TINYINT(1) NOT NULL,
    `proCanDetectDeviceIsMobile` TINYINT(1) NOT NULL,
    `proCanDetectDeviceSimCount` TINYINT(1) NOT NULL,
    `proCanDetectDeviceDisplayWidth` TINYINT(1) NOT NULL,
    `proCanDetectDeviceDisplayHeight` TINYINT(1) NOT NULL,
    `proCanDetectDeviceDisplayIsTouch` TINYINT(1) NOT NULL,
    `proCanDetectDeviceDisplayType` TINYINT(1) NOT NULL,
    `proCanDetectDeviceDisplaySize` TINYINT(1) NOT NULL,
    `proCommand` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`proId`),
    UNIQUE KEY `unique_provider_name` (`proType`,`proName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC CHECKSUM=1')->execute();

        $this->pdo->prepare('DROP TABLE IF EXISTS `useragent`')->execute();
        $this->pdo->prepare('CREATE TABLE IF NOT EXISTS `useragent` (
    `uaId` CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
    `uaHash` VARBINARY(255) NOT NULL,
    `uaString` LONGTEXT NOT NULL,
    `uaAdditionalHeaders` JSON NULL DEFAULT NULL,
    PRIMARY KEY (`uaId`),
    UNIQUE KEY `userAgent_hash` (`uaHash`),
    INDEX `uaString` (`uaString`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC CHECKSUM=1')->execute();

        $this->pdo->prepare('DROP TABLE IF EXISTS `result`')->execute();
        $this->pdo->prepare('CREATE TABLE IF NOT EXISTS `result` (
    `resId` CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
    `run` VARCHAR(255) DEFAULT NULL,
    `provider_id` CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
    `resProviderVersion` VARCHAR(255) DEFAULT NULL,
    `userAgent_id` CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
    `resFilename` VARCHAR(255) DEFAULT NULL,
    `resParseTime` DECIMAL(20,10) DEFAULT NULL,
    `resInitTime` DECIMAL(20,10) DEFAULT NULL,
    `resMemoryUsed` INT DEFAULT NULL,
    `resLastChangeDate` DATETIME NOT NULL,
    `resResultFound` TINYINT(1) NOT NULL,
    `resResultError` TINYINT(1) NOT NULL,
    `resClientName` VARCHAR(255) DEFAULT NULL,
    `resClientModus` VARCHAR(255) DEFAULT NULL,
    `resClientVersion` VARCHAR(255) DEFAULT NULL,
    `resClientManufacturer` VARCHAR(255) DEFAULT NULL,
    `resClientBits` INT DEFAULT NULL,
    `resClientIsBot` TINYINT(1) DEFAULT NULL,
    `resClientType` VARCHAR(255) DEFAULT NULL,
    `resEngineName` VARCHAR(255) DEFAULT NULL,
    `resEngineVersion` VARCHAR(255) DEFAULT NULL,
    `resEngineManufacturer` VARCHAR(255) DEFAULT NULL,
    `resOsName` VARCHAR(255) DEFAULT NULL,
    `resOsMarketingName` VARCHAR(255) DEFAULT NULL,
    `resOsVersion` VARCHAR(255) DEFAULT NULL,
    `resOsManufacturer` VARCHAR(255) DEFAULT NULL,
    `resOsBits` INT DEFAULT NULL,
    `resDeviceName` VARCHAR(255) DEFAULT NULL,
    `resDeviceMarketingName` VARCHAR(255) DEFAULT NULL,
    `resDeviceManufacturer` VARCHAR(255) DEFAULT NULL,
    `resDeviceBrand` VARCHAR(255) DEFAULT NULL,
    `resDeviceDualOrientation` TINYINT(1) DEFAULT NULL,
    `resDeviceType` VARCHAR(255) DEFAULT NULL,
    `resDeviceIsMobile` TINYINT(1) DEFAULT NULL,
    `resDeviceSimCount` INT DEFAULT NULL,
    `resDeviceDisplayWidth` INT DEFAULT NULL,
    `resDeviceDisplayHeight` INT DEFAULT NULL,
    `resDeviceDisplayIsTouch` TINYINT(1) DEFAULT NULL,
    `resDeviceDisplayType` VARCHAR(255) DEFAULT NULL,
    `resDeviceDisplaySize` DECIMAL(20,10) DEFAULT NULL,
    `resRawResult` JSON NULL DEFAULT NULL COLLATE \'utf8mb4_bin\',
    PRIMARY KEY (`resId`),
    UNIQUE KEY `unique_run_userAgent_provider` (`run`, `userAgent_id`,`provider_id`),
    KEY `IDX_136AC113E127EC2A` (`userAgent_id`),
    KEY `IDX_136AC113A53A8AA` (`provider_id`),
    KEY `result_resClientName` (`resClientName`),
    KEY `result_resEngineName` (`resEngineName`),
    KEY `result_resOsName` (`resOsName`),
    KEY `result_resDeviceName` (`resDeviceName`),
    KEY `result_resDeviceBrand` (`resDeviceBrand`),
    KEY `result_resDeviceType` (`resDeviceType`),
    KEY `result_resClientIsBot` (`resClientIsBot`),
    KEY `result_resClientType` (`resClientType`),
    KEY `result_resParseTime` (`resParseTime`),
    KEY `result_resInitTime` (`resInitTime`),
    KEY `result_resMemoryUsed` (`resMemoryUsed`),
    KEY `result_resResultFound` (`resResultFound`),
    KEY `result_resResultError` (`resResultError`),
    CONSTRAINT `FK_136AC113A53A8AA` FOREIGN KEY (`provider_id`) REFERENCES `provider` (`proId`),
    CONSTRAINT `FK_136AC113E127EC2A` FOREIGN KEY (`userAgent_id`) REFERENCES `useragent` (`uaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC CHECKSUM=1')->execute();

        $this->pdo->prepare('DROP TABLE IF EXISTS `result-normalized`')->execute();
        $this->pdo->prepare('CREATE TABLE IF NOT EXISTS `result-normalized` (
    `resNormaId` CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
    `result_id` CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
    `resNormaClientName` VARCHAR(255) DEFAULT NULL,
    `resNormaClientModus` VARCHAR(255) DEFAULT NULL,
    `resNormaClientVersion` VARCHAR(255) DEFAULT NULL,
    `resNormaClientManufacturer` VARCHAR(255) DEFAULT NULL,
    `resNormaClientBits` INT DEFAULT NULL,
    `resNormaClientIsBot` TINYINT(1) DEFAULT NULL,
    `resNormaClientType` VARCHAR(255) DEFAULT NULL,
    `resNormaEngineName` VARCHAR(255) DEFAULT NULL,
    `resNormaEngineVersion` VARCHAR(255) DEFAULT NULL,
    `resNormaEngineManufacturer` VARCHAR(255) DEFAULT NULL,
    `resNormaOsName` VARCHAR(255) DEFAULT NULL,
    `resNormaOsMarketingName` VARCHAR(255) DEFAULT NULL,
    `resNormaOsVersion` VARCHAR(255) DEFAULT NULL,
    `resNormaOsManufacturer` VARCHAR(255) DEFAULT NULL,
    `resNormaOsBits` INT DEFAULT NULL,
    `resNormaDeviceName` VARCHAR(255) DEFAULT NULL,
    `resNormaDeviceMarketingName` VARCHAR(255) DEFAULT NULL,
    `resNormaDeviceManufacturer` VARCHAR(255) DEFAULT NULL,
    `resNormaDeviceBrand` VARCHAR(255) DEFAULT NULL,
    `resNormaDeviceDualOrientation` TINYINT(1) DEFAULT NULL,
    `resNormaDeviceType` VARCHAR(255) DEFAULT NULL,
    `resNormaDeviceIsMobile` TINYINT(1) DEFAULT NULL,
    `resNormaDeviceSimCount` INT DEFAULT NULL,
    `resNormaDeviceDisplayWidth` INT DEFAULT NULL,
    `resNormaDeviceDisplayHeight` INT DEFAULT NULL,
    `resNormaDeviceDisplayIsTouch` TINYINT(1) DEFAULT NULL,
    `resNormaDeviceDisplayType` VARCHAR(255) DEFAULT NULL,
    `resNormaDeviceDisplaySize` DECIMAL(20,10) DEFAULT NULL,
    PRIMARY KEY (`resNormaId`),
    UNIQUE KEY `unique_result` (`result_id`),
    KEY `resNormaClientName` (`resNormaClientName`),
    KEY `resNormaEngineName` (`resNormaEngineName`),
    KEY `resNormaOsName` (`resNormaOsName`),
    KEY `resNormaDeviceName` (`resNormaDeviceName`),
    KEY `resNormaDeviceBrand` (`resNormaDeviceBrand`),
    KEY `resNormaDeviceType` (`resNormaDeviceType`),
    KEY `resNormaClientIsBot` (`resNormaClientIsBot`),
    KEY `resNormaClientType` (`resNormaClientType`),
    CONSTRAINT `FK_result` FOREIGN KEY (`result_id`) REFERENCES `result` (`resId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC CHECKSUM=1')->execute();

        $this->pdo->prepare('DROP TABLE IF EXISTS `resultevaluation`')->execute();
        $this->pdo->prepare('CREATE TABLE IF NOT EXISTS `resultevaluation` (
    `result_id` CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
    `revId` CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
    `lastChangeDate` DATETIME NOT NULL,
    `browserName` INT NOT NULL,
    `browserNameHarmonized` INT NOT NULL,
    `browserVersion` INT NOT NULL,
    `browserVersionHarmonized` INT NOT NULL,
    `engineName` INT NOT NULL,
    `engineNameHarmonized` INT NOT NULL,
    `engineVersion` INT NOT NULL,
    `engineVersionHarmonized` INT NOT NULL,
    `osName` INT NOT NULL,
    `osNameHarmonized` INT NOT NULL,
    `osVersion` INT NOT NULL,
    `osVersionHarmonized` INT NOT NULL,
    `deviceModel` INT NOT NULL,
    `deviceModelHarmonized` INT NOT NULL,
    `deviceBrand` INT NOT NULL,
    `deviceBrandHarmonized` INT NOT NULL,
    `deviceType` INT NOT NULL,
    `deviceTypeHarmonized` INT NOT NULL,
    `asMobileDetectedByOthers` INT NOT NULL,
    `asTouchDetectedByOthers` INT NOT NULL,
    `asBotDetectedByOthers` INT NOT NULL,
    `botName` INT NOT NULL,
    `botNameHarmonized` INT NOT NULL,
    `botType` INT NOT NULL,
    `botTypeHarmonized` INT NOT NULL,
    PRIMARY KEY (`revId`),
    UNIQUE KEY `UNIQ_2846B1657A7B643` (`result_id`),
    CONSTRAINT `FK_2846B1657A7B643` FOREIGN KEY (`result_id`) REFERENCES `result` (`resId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC CHECKSUM=1')->execute();

        $this->pdo->prepare('DROP TABLE IF EXISTS `useragentevaluation`')->execute();
        $this->pdo->prepare('CREATE TABLE IF NOT EXISTS `useragentevaluation` (
    `uevId` CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
    `lastChangeDate` DATETIME NOT NULL,
    `resultCount` INT NOT NULL,
    `resultFound` INT NOT NULL,
    `browserNames` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `browserNamesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `browserNameFound` INT NOT NULL,
    `browserNameFoundUnique` INT NOT NULL,
    `browserNameMaxCount` INT NOT NULL,
    `browserNameHarmonizedFoundUnique` INT NOT NULL,
    `browserNameHarmonizedMaxCount` INT NOT NULL,
    `browserVersions` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `browserVersionsHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `browserVersionFound` INT NOT NULL,
    `browserVersionFoundUnique` INT NOT NULL,
    `browserVersionMaxCount` INT NOT NULL,
    `browserVersionHarmonizedFoundUnique` INT NOT NULL,
    `browserVersionHarmonizedMaxCount` INT NOT NULL,
    `engineNames` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `engineNamesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `engineNameFound` INT NOT NULL,
    `engineNameFoundUnique` INT NOT NULL,
    `engineNameMaxCount` INT NOT NULL,
    `engineNameHarmonizedFoundUnique` INT NOT NULL,
    `engineNameHarmonizedMaxCount` INT NOT NULL,
    `engineVersions` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `engineVersionsHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `engineVersionFound` INT NOT NULL,
    `engineVersionFoundUnique` INT NOT NULL,
    `engineVersionMaxCount` INT NOT NULL,
    `engineVersionHarmonizedFoundUnique` INT NOT NULL,
    `engineVersionHarmonizedMaxCount` INT NOT NULL,
    `osNames` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `osNamesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `osNameFound` INT NOT NULL,
    `osNameFoundUnique` INT NOT NULL,
    `osNameMaxCount` INT NOT NULL,
    `osNameHarmonizedFoundUnique` INT NOT NULL,
    `osNameHarmonizedMaxCount` INT NOT NULL,
    `osVersions` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `osVersionsHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `osVersionFound` INT NOT NULL,
    `osVersionFoundUnique` INT NOT NULL,
    `osVersionMaxCount` INT NOT NULL,
    `osVersionHarmonizedFoundUnique` INT NOT NULL,
    `osVersionHarmonizedMaxCount` INT NOT NULL,
    `deviceModels` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `deviceModelsHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `deviceModelFound` INT NOT NULL,
    `deviceModelFoundUnique` INT NOT NULL,
    `deviceModelMaxCount` INT NOT NULL,
    `deviceModelHarmonizedFoundUnique` INT NOT NULL,
    `deviceModelHarmonizedMaxCount` INT NOT NULL,
    `deviceBrands` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `deviceBrandsHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `deviceBrandFound` INT NOT NULL,
    `deviceBrandFoundUnique` INT NOT NULL,
    `deviceBrandMaxCount` INT NOT NULL,
    `deviceBrandHarmonizedFoundUnique` INT NOT NULL,
    `deviceBrandHarmonizedMaxCount` INT NOT NULL,
    `deviceTypes` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `deviceTypesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `deviceTypeFound` INT NOT NULL,
    `deviceTypeFoundUnique` INT NOT NULL,
    `deviceTypeMaxCount` INT NOT NULL,
    `deviceTypeHarmonizedFoundUnique` INT NOT NULL,
    `deviceTypeHarmonizedMaxCount` INT NOT NULL,
    `asMobileDetectedCount` INT NOT NULL,
    `asTouchDetectedCount` INT NOT NULL,
    `asBotDetectedCount` INT NOT NULL,
    `botNames` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `botNamesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `botNameFound` INT NOT NULL,
    `botNameFoundUnique` INT NOT NULL,
    `botNameMaxCount` INT NOT NULL,
    `botNameHarmonizedFoundUnique` INT NOT NULL,
    `botNameHarmonizedMaxCount` INT NOT NULL,
    `botTypes` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `botTypesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
    `clientTypeFound` INT NOT NULL,
    `botTypeFoundUnique` INT NOT NULL,
    `botTypeMaxCount` INT NOT NULL,
    `botTypeHarmonizedFoundUnique` INT NOT NULL,
    `botTypeHarmonizedMaxCount` INT NOT NULL,
    `userAgent_id` CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
    PRIMARY KEY (`uevId`),
    UNIQUE KEY `UNIQ_D98F3DB4E127EC2A` (`userAgent_id`),
    CONSTRAINT `FK_D98F3DB4E127EC2A` FOREIGN KEY (`userAgent_id`) REFERENCES `useragent` (`uaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC CHECKSUM=1')->execute();

        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `real-provider` AS SELECT * FROM `provider` WHERE `proType` = \'real\' AND `proIsActive` = 1',
        )->execute();
        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `test-provider` AS SELECT * FROM `provider` WHERE `proType` = \'testSuite\' AND `proIsActive` = 1',
        )->execute();

        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `list-found-general-client-names` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resClientName` IS NOT NULL',
        )->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-client-names` AS SELECT
        `list-found-general-client-names`.`resClientName` AS `name`,
        `userAgent`.`uaId`,
        `userAgent`.`uaString`,
        COUNT(`list-found-general-client-names`.`resClientName`) AS `detectionCount`
    FROM `list-found-general-client-names`
    INNER JOIN `userAgent`
        ON `userAgent`.`uaId` = `list-found-general-client-names`.`userAgent_id`
    GROUP BY `list-found-general-client-names`.`resClientName`')->execute();
        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `list-found-general-engine-names` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resEngineName` IS NOT NULL',
        )->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-engine-names` AS SELECT
        `resEngineName` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resEngineName`) AS `detectionCount`
    FROM `list-found-general-engine-names`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resEngineName`')->execute();
        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `list-found-general-os-names` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resOsName` IS NOT NULL',
        )->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-os-names` AS SELECT
        `resOsName` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resOsName`) AS `detectionCount`
    FROM `list-found-general-os-names`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resOsName`')->execute();
        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `list-found-general-device-models` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resDeviceName` IS NOT NULL',
        )->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-device-models` AS SELECT
        `resDeviceName` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resDeviceName`) AS `detectionCount`
    FROM `list-found-general-device-models`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resDeviceName`')->execute();
        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `list-found-general-device-brands` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resDeviceBrand` IS NOT NULL',
        )->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-device-brands` AS SELECT
        `resDeviceBrand` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resDeviceBrand`) AS `detectionCount`
    FROM `list-found-general-device-brands`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resDeviceBrand`')->execute();
        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `list-found-general-device-types` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resDeviceType` IS NOT NULL',
        )->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-device-types` AS SELECT
        `resDeviceType` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resDeviceType`) AS `detectionCount`
    FROM `list-found-general-device-types`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resDeviceType`')->execute();
        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `list-found-general-device-ismobile` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resDeviceIsMobile` IS NOT NULL',
        )->execute();
        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `list-found-general-client-isbot` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resClientIsBot` IS NOT NULL',
        )->execute();
        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `list-found-general-client-types` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resClientType` IS NOT NULL',
        )->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-client-types` AS SELECT
        `resClientType` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resClientType`) AS `detectionCount`
    FROM `list-found-general-client-types`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resClientType`')->execute();

        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `found-results` AS SELECT * FROM `result` WHERE `resResultFound` = 1 AND `provider_id` IN (SELECT `proId` FROM `real-provider`)',
        )->execute();
        $this->pdo->prepare(
            'CREATE OR REPLACE VIEW `useragents-general-overview` AS SELECT `proId`, `proName`, COUNT(*) AS `countNumber` FROM `test-provider` JOIN `result` ON `provider_id` = `proId` GROUP BY `proId` ORDER BY `proName`',
        )->execute();

        $output->writeln('<info>done!</info>');

        return self::SUCCESS;
    }
}
