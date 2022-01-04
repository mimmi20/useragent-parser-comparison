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

class InitDb extends Command
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
        $this->setName('init-db');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
  `proLocal` TINYINT(1) NOT NULL,
  `proApi` TINYINT(1) NOT NULL,
  `proIsActive` TINYINT(1) NOT NULL,
  `proCanDetectClientName` TINYINT(1) NOT NULL,
  `proCanDetectClientVersion` TINYINT(1) NOT NULL,
  `proCanDetectClientIsBot` TINYINT(1) NOT NULL,
  `proCanDetectClientType` TINYINT(1) NOT NULL,
  `proCanDetectEngineName` TINYINT(1) NOT NULL,
  `proCanDetectEngineVersion` TINYINT(1) NOT NULL,
  `proCanDetectOsName` TINYINT(1) NOT NULL,
  `proCanDetectOsVersion` TINYINT(1) NOT NULL,
  `proCanDetectDeviceModel` TINYINT(1) NOT NULL,
  `proCanDetectDeviceBrand` TINYINT(1) NOT NULL,
  `proCanDetectDeviceType` TINYINT(1) NOT NULL,
  `proCanDetectDeviceIsMobile` TINYINT(1) NOT NULL,
  `proCanDetectDeviceIsTouch` TINYINT(1) NOT NULL,
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
  UNIQUE KEY `userAgent_hash` (`uaHash`)
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
  `resClientVersion` VARCHAR(255) DEFAULT NULL,
  `resClientIsBot` TINYINT(1) DEFAULT NULL,
  `resClientType` VARCHAR(255) DEFAULT NULL,
  `resEngineName` VARCHAR(255) DEFAULT NULL,
  `resEngineVersion` VARCHAR(255) DEFAULT NULL,
  `resOsName` VARCHAR(255) DEFAULT NULL,
  `resOsVersion` VARCHAR(255) DEFAULT NULL,
  `resDeviceModel` VARCHAR(255) DEFAULT NULL,
  `resDeviceBrand` VARCHAR(255) DEFAULT NULL,
  `resDeviceType` VARCHAR(255) DEFAULT NULL,
  `resDeviceIsMobile` TINYINT(1) DEFAULT NULL,
  `resDeviceIsTouch` TINYINT(1) DEFAULT NULL,
  `resRawResult` JSON NULL DEFAULT NULL COLLATE \'utf8mb4_bin\',
  PRIMARY KEY (`resId`),
  UNIQUE KEY `unique_run_userAgent_provider` (`run`, `userAgent_id`,`provider_id`),
  KEY `IDX_136AC113E127EC2A` (`userAgent_id`),
  KEY `IDX_136AC113A53A8AA` (`provider_id`),
  KEY `result_resClientName` (`resClientName`),
  KEY `result_resEngineName` (`resEngineName`),
  KEY `result_resOsName` (`resOsName`),
  KEY `result_resDeviceModel` (`resDeviceModel`),
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
  `resNormaClientVersion` VARCHAR(255) DEFAULT NULL,
  `resNormaClientIsBot` TINYINT(1) DEFAULT NULL,
  `resNormaClientType` VARCHAR(255) DEFAULT NULL,
  `resNormaEngineName` VARCHAR(255) DEFAULT NULL,
  `resNormaEngineVersion` VARCHAR(255) DEFAULT NULL,
  `resNormaOsName` VARCHAR(255) DEFAULT NULL,
  `resNormaOsVersion` VARCHAR(255) DEFAULT NULL,
  `resNormaDeviceModel` VARCHAR(255) DEFAULT NULL,
  `resNormaDeviceBrand` VARCHAR(255) DEFAULT NULL,
  `resNormaDeviceType` VARCHAR(255) DEFAULT NULL,
  `resNormaDeviceIsMobile` TINYINT(1) DEFAULT NULL,
  `resNormaDeviceIsTouch` TINYINT(1) DEFAULT NULL,
  PRIMARY KEY (`resNormaId`),
  UNIQUE KEY `unique_result` (`result_id`),
  KEY `resNormaClientName` (`resNormaClientName`),
  KEY `resNormaEngineName` (`resNormaEngineName`),
  KEY `resNormaOsName` (`resNormaOsName`),
  KEY `resNormaDeviceModel` (`resNormaDeviceModel`),
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
  `browserName` int(11) NOT NULL,
  `browserNameHarmonized` int(11) NOT NULL,
  `browserVersion` int(11) NOT NULL,
  `browserVersionHarmonized` int(11) NOT NULL,
  `engineName` int(11) NOT NULL,
  `engineNameHarmonized` int(11) NOT NULL,
  `engineVersion` int(11) NOT NULL,
  `engineVersionHarmonized` int(11) NOT NULL,
  `osName` int(11) NOT NULL,
  `osNameHarmonized` int(11) NOT NULL,
  `osVersion` int(11) NOT NULL,
  `osVersionHarmonized` int(11) NOT NULL,
  `deviceModel` int(11) NOT NULL,
  `deviceModelHarmonized` int(11) NOT NULL,
  `deviceBrand` int(11) NOT NULL,
  `deviceBrandHarmonized` int(11) NOT NULL,
  `deviceType` int(11) NOT NULL,
  `deviceTypeHarmonized` int(11) NOT NULL,
  `asMobileDetectedByOthers` int(11) NOT NULL,
  `asTouchDetectedByOthers` int(11) NOT NULL,
  `asBotDetectedByOthers` int(11) NOT NULL,
  `botName` int(11) NOT NULL,
  `botNameHarmonized` int(11) NOT NULL,
  `botType` int(11) NOT NULL,
  `botTypeHarmonized` int(11) NOT NULL,
  PRIMARY KEY (`revId`),
  UNIQUE KEY `UNIQ_2846B1657A7B643` (`result_id`),
  CONSTRAINT `FK_2846B1657A7B643` FOREIGN KEY (`result_id`) REFERENCES `result` (`resId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC CHECKSUM=1')->execute();

        $this->pdo->prepare('DROP TABLE IF EXISTS `useragentevaluation`')->execute();
        $this->pdo->prepare('CREATE TABLE IF NOT EXISTS `useragentevaluation` (
  `uevId` CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
  `lastChangeDate` DATETIME NOT NULL,
  `resultCount` int(11) NOT NULL,
  `resultFound` int(11) NOT NULL,
  `browserNames` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `browserNamesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `browserNameFound` int(11) NOT NULL,
  `browserNameFoundUnique` int(11) NOT NULL,
  `browserNameMaxCount` int(11) NOT NULL,
  `browserNameHarmonizedFoundUnique` int(11) NOT NULL,
  `browserNameHarmonizedMaxCount` int(11) NOT NULL,
  `browserVersions` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `browserVersionsHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `browserVersionFound` int(11) NOT NULL,
  `browserVersionFoundUnique` int(11) NOT NULL,
  `browserVersionMaxCount` int(11) NOT NULL,
  `browserVersionHarmonizedFoundUnique` int(11) NOT NULL,
  `browserVersionHarmonizedMaxCount` int(11) NOT NULL,
  `engineNames` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `engineNamesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `engineNameFound` int(11) NOT NULL,
  `engineNameFoundUnique` int(11) NOT NULL,
  `engineNameMaxCount` int(11) NOT NULL,
  `engineNameHarmonizedFoundUnique` int(11) NOT NULL,
  `engineNameHarmonizedMaxCount` int(11) NOT NULL,
  `engineVersions` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `engineVersionsHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `engineVersionFound` int(11) NOT NULL,
  `engineVersionFoundUnique` int(11) NOT NULL,
  `engineVersionMaxCount` int(11) NOT NULL,
  `engineVersionHarmonizedFoundUnique` int(11) NOT NULL,
  `engineVersionHarmonizedMaxCount` int(11) NOT NULL,
  `osNames` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `osNamesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `osNameFound` int(11) NOT NULL,
  `osNameFoundUnique` int(11) NOT NULL,
  `osNameMaxCount` int(11) NOT NULL,
  `osNameHarmonizedFoundUnique` int(11) NOT NULL,
  `osNameHarmonizedMaxCount` int(11) NOT NULL,
  `osVersions` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `osVersionsHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `osVersionFound` int(11) NOT NULL,
  `osVersionFoundUnique` int(11) NOT NULL,
  `osVersionMaxCount` int(11) NOT NULL,
  `osVersionHarmonizedFoundUnique` int(11) NOT NULL,
  `osVersionHarmonizedMaxCount` int(11) NOT NULL,
  `deviceModels` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `deviceModelsHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `deviceModelFound` int(11) NOT NULL,
  `deviceModelFoundUnique` int(11) NOT NULL,
  `deviceModelMaxCount` int(11) NOT NULL,
  `deviceModelHarmonizedFoundUnique` int(11) NOT NULL,
  `deviceModelHarmonizedMaxCount` int(11) NOT NULL,
  `deviceBrands` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `deviceBrandsHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `deviceBrandFound` int(11) NOT NULL,
  `deviceBrandFoundUnique` int(11) NOT NULL,
  `deviceBrandMaxCount` int(11) NOT NULL,
  `deviceBrandHarmonizedFoundUnique` int(11) NOT NULL,
  `deviceBrandHarmonizedMaxCount` int(11) NOT NULL,
  `deviceTypes` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `deviceTypesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `deviceTypeFound` int(11) NOT NULL,
  `deviceTypeFoundUnique` int(11) NOT NULL,
  `deviceTypeMaxCount` int(11) NOT NULL,
  `deviceTypeHarmonizedFoundUnique` int(11) NOT NULL,
  `deviceTypeHarmonizedMaxCount` int(11) NOT NULL,
  `asMobileDetectedCount` int(11) NOT NULL,
  `asTouchDetectedCount` int(11) NOT NULL,
  `asBotDetectedCount` int(11) NOT NULL,
  `botNames` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `botNamesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `botNameFound` int(11) NOT NULL,
  `botNameFoundUnique` int(11) NOT NULL,
  `botNameMaxCount` int(11) NOT NULL,
  `botNameHarmonizedFoundUnique` int(11) NOT NULL,
  `botNameHarmonizedMaxCount` int(11) NOT NULL,
  `botTypes` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `botTypesHarmonized` LONGTEXT NOT NULL COMMENT \'(DC2Type:object)\',
  `clientTypeFound` int(11) NOT NULL,
  `botTypeFoundUnique` int(11) NOT NULL,
  `botTypeMaxCount` int(11) NOT NULL,
  `botTypeHarmonizedFoundUnique` int(11) NOT NULL,
  `botTypeHarmonizedMaxCount` int(11) NOT NULL,
  `userAgent_id` CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
  PRIMARY KEY (`uevId`),
  UNIQUE KEY `UNIQ_D98F3DB4E127EC2A` (`userAgent_id`),
  CONSTRAINT `FK_D98F3DB4E127EC2A` FOREIGN KEY (`userAgent_id`) REFERENCES `useragent` (`uaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC CHECKSUM=1')->execute();

        $this->pdo->prepare('CREATE OR REPLACE VIEW `real-provider` AS SELECT * FROM `provider` WHERE `proType` = \'real\' AND `proIsActive` = 1')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `test-provider` AS SELECT * FROM `provider` WHERE `proType` = \'testSuite\' AND `proIsActive` = 1')->execute();

        $this->pdo->prepare('CREATE OR REPLACE VIEW `list-found-general-client-names` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resClientName` IS NOT NULL')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-client-names` AS SELECT 
        `resClientName` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resClientName`) AS `detectionCount`
    FROM `list-found-general-client-names`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resClientName`')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `list-found-general-engine-names` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resEngineName` IS NOT NULL')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-engine-names` AS SELECT
        `resEngineName` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resEngineName`) AS `detectionCount`
    FROM `list-found-general-engine-names`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resEngineName`')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `list-found-general-os-names` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resOsName` IS NOT NULL')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-os-names` AS SELECT
        `resOsName` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resOsName`) AS `detectionCount`
    FROM `list-found-general-os-names`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resOsName`')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `list-found-general-device-models` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resDeviceModel` IS NOT NULL')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-device-models` AS SELECT
        `resDeviceModel` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resDeviceModel`) AS `detectionCount`
    FROM `list-found-general-device-models`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resDeviceModel`')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `list-found-general-device-brands` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resDeviceBrand` IS NOT NULL')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-device-brands` AS SELECT
        `resDeviceBrand` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resDeviceBrand`) AS `detectionCount`
    FROM `list-found-general-device-brands`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resDeviceBrand`')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `list-found-general-device-types` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resDeviceType` IS NOT NULL')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-device-types` AS SELECT
        `resDeviceType` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resDeviceType`) AS `detectionCount`
    FROM `list-found-general-device-types`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resDeviceType`')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `list-found-general-device-ismobile` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resDeviceIsMobile` IS NOT NULL')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `list-found-general-client-isbot` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resClientIsBot` IS NOT NULL')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `list-found-general-client-types` AS SELECT * FROM `result` WHERE `provider_id` IN (SELECT `proId` FROM `real-provider`) AND `resClientType` IS NOT NULL')->execute();
        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-general-client-types` AS SELECT
        `resClientType` AS `name`,
        `uaId`,
        `uaString`,
        COUNT(`resClientType`) AS `detectionCount`
    FROM `list-found-general-client-types`
    INNER JOIN `userAgent`
        ON `uaId` = `userAgent_id`
    GROUP BY `resClientType`')->execute();

        $this->pdo->prepare('CREATE OR REPLACE VIEW `found-results` AS SELECT * FROM `result` WHERE `resResultFound` = 1 AND `provider_id` IN (SELECT `proId` FROM `real-provider`)')->execute();

        return self::SUCCESS;
    }
}
