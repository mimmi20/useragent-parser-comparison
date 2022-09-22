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

class InitProvider extends Command
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
        $this->setName('init-provider');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $statementSelectProvider = $this->pdo->prepare('SELECT * FROM `provider` WHERE `proName` = :proName AND `proType` = :proType');
        $statementInsertProvider = $this->pdo->prepare('INSERT INTO `provider` (`proId`, `proType`, `proName`, `proHomepage`, `proVersion`, `proLastReleaseDate`, `proPackageName`, `proLanguage`, `proIsLocal`, `proIsApi`, `proIsActive`, `proCanDetectClientName`, `proCanDetectClientModus`, `proCanDetectClientVersion`, `proCanDetectClientManufacturer`, `proCanDetectClientBits`, `proCanDetectEngineName`, `proCanDetectEngineVersion`, `proCanDetectEngineManufacturer`, `proCanDetectOsName`, `proCanDetectOsMarketingName`, `proCanDetectOsVersion`, `proCanDetectOsManufacturer`, `proCanDetectOsBits`, `proCanDetectDeviceName`, `proCanDetectDeviceMarketingName`, `proCanDetectDeviceManufacturer`, `proCanDetectDeviceBrand`, `proCanDetectDeviceDualOrientation`, `proCanDetectDeviceType`, `proCanDetectDeviceIsMobile`, `proCanDetectDeviceSimCount`, `proCanDetectDeviceDisplayWidth`, `proCanDetectDeviceDisplayHeight`, `proCanDetectDeviceDisplayIsTouch`, `proCanDetectDeviceDisplayType`, `proCanDetectDeviceDisplaySize`, `proCanDetectClientIsBot`, `proCanDetectClientType`, `proCommand`) VALUES (:proId, :proType, :proName, :proHomepage, :proVersion, :proLastReleaseDate, :proPackageName, :proLanguage, :proIsLocal, :proIsApi, :proIsActive, :proCanDetectClientName, :proCanDetectClientModus, :proCanDetectClientVersion, :proCanDetectClientManufacturer, :proCanDetectClientBits, :proCanDetectEngineName, :proCanDetectEngineVersion, :proCanDetectEngineManufacturer, :proCanDetectOsName, :proCanDetectOsMarketingName, :proCanDetectOsVersion, :proCanDetectOsManufacturer, :proCanDetectOsBits, :proCanDetectDeviceName, :proCanDetectDeviceMarketingName, :proCanDetectDeviceManufacturer, :proCanDetectDeviceBrand, :proCanDetectDeviceDualOrientation, :proCanDetectDeviceType, :proCanDetectDeviceIsMobile, :proCanDetectDeviceSimCount, :proCanDetectDeviceDisplayWidth, :proCanDetectDeviceDisplayHeight, :proCanDetectDeviceDisplayIsTouch, :proCanDetectDeviceDisplayType, :proCanDetectDeviceDisplaySize, :proCanDetectClientIsBot, :proCanDetectClientType, :proCommand)');
        $statementUpdateProvider = $this->pdo->prepare('UPDATE `provider` SET `proType` = :proType, `proName` = :proName, `proHomepage` = :proHomepage, `proVersion` = :proVersion, `proLastReleaseDate` = :proLastReleaseDate, `proPackageName` = :proPackageName, `proLanguage` = :proLanguage, `proIsLocal` = :proIsLocal, `proIsApi` = :proIsApi, `proIsActive` = :proIsActive, `proCanDetectClientName` = :proCanDetectClientName, `proCanDetectClientModus` = :proCanDetectClientModus, `proCanDetectClientVersion` = :proCanDetectClientVersion, `proCanDetectClientManufacturer` = :proCanDetectClientManufacturer, `proCanDetectClientBits` = :proCanDetectClientBits, `proCanDetectEngineName` = :proCanDetectEngineName, `proCanDetectEngineVersion` = :proCanDetectEngineVersion, `proCanDetectEngineManufacturer` = :proCanDetectEngineManufacturer, `proCanDetectOsName` = :proCanDetectOsName, `proCanDetectOsMarketingName` = :proCanDetectOsMarketingName, `proCanDetectOsVersion` = :proCanDetectOsVersion, `proCanDetectOsManufacturer` = :proCanDetectOsManufacturer, `proCanDetectOsBits` = :proCanDetectOsBits, `proCanDetectDeviceName` = :proCanDetectDeviceName, `proCanDetectDeviceMarketingName` = :proCanDetectDeviceMarketingName, `proCanDetectDeviceManufacturer` = :proCanDetectDeviceManufacturer, `proCanDetectDeviceBrand` = :proCanDetectDeviceBrand, `proCanDetectDeviceDualOrientation` = :proCanDetectDeviceDualOrientation, `proCanDetectDeviceType` = :proCanDetectDeviceType, `proCanDetectDeviceIsMobile` = :proCanDetectDeviceIsMobile, `proCanDetectDeviceSimCount` = :proCanDetectDeviceSimCount, `proCanDetectDeviceDisplayWidth` = :proCanDetectDeviceDisplayWidth, `proCanDetectDeviceDisplayHeight` = :proCanDetectDeviceDisplayHeight, `proCanDetectDeviceDisplayIsTouch` = :proCanDetectDeviceDisplayIsTouch, `proCanDetectDeviceDisplayType` = :proCanDetectDeviceDisplayType, `proCanDetectDeviceDisplaySize` = :proCanDetectDeviceDisplaySize, `proCanDetectClientIsBot` = :proCanDetectClientIsBot, `proCanDetectClientType` = :proCanDetectClientType, `proCommand` = :proCommand WHERE `proId` = :proId');

        $output->writeln('~~~ Load all Providers ~~~');

        /** @var \UserAgentParserComparison\Command\Helper\Parsers $parserHelper */
        $parserHelper = $this->getHelper('parsers');

        foreach ($parserHelper->getAllParsers($output) as $parserConfig) {
            $this->insertProvider(
                $output,
                $statementSelectProvider,
                $statementInsertProvider,
                $statementUpdateProvider,
                'real',
                $parserConfig
            );
        }

        /** @var \UserAgentParserComparison\Command\Helper\Tests $testHelper */
        $testHelper = $this->getHelper('tests');

        foreach ($testHelper->collectTests($output, null) as $testConfig) {
            $this->insertProvider(
                $output,
                $statementSelectProvider,
                $statementInsertProvider,
                $statementUpdateProvider,
                'testSuite',
                $testConfig
            );
        }

        $output->writeln('<info>done!</info>');

        return self::SUCCESS;
    }
    
    private function insertProvider(
        OutputInterface $output,
        \PDOStatement   $statementSelectProvider,
        \PDOStatement   $statementInsertProvider,
        \PDOStatement   $statementUpdateProvider,
        string          $type,
        array           $providerConfig
    ): void
    {
        $proName                    = $providerConfig['metadata']['name'];
        $proLanguage                = $providerConfig['metadata']['language'];

        $output->write('writing data for provider <fg=green;options=bold,underscore>' . $proName . '</> [' . $proLanguage . '/' . $type . '] into DB');

        $proHomepage                = $providerConfig['metadata']['homepage'];
        $proVersion                 = $providerConfig['metadata']['version'] ?? null;
        $proReleaseDate             = $providerConfig['metadata']['release-date'] ?? null;
        $proPackageName             = $providerConfig['metadata']['packageName'];
        $proIsLocal                   = $providerConfig['metadata']['local'];
        $proIsApi                     = $providerConfig['metadata']['api'];
        $proIsActive                = $providerConfig['metadata']['isActive'] ?? 1;
        $proCanDetectClientName     = $providerConfig['metadata']['detectionCapabilities']['client']['name'];
        $proCanDetectClientModus    = $providerConfig['metadata']['detectionCapabilities']['client']['modus'];
        $proCanDetectClientVersion  = $providerConfig['metadata']['detectionCapabilities']['client']['version'];
        $proCanDetectClientManufacturer = $providerConfig['metadata']['detectionCapabilities']['client']['manufacturer'];
        $proCanDetectClientBits     = $providerConfig['metadata']['detectionCapabilities']['client']['bits'];
        $proCanDetectClientIsBot    = $providerConfig['metadata']['detectionCapabilities']['client']['isBot'];
        $proCanDetectClientType     = $providerConfig['metadata']['detectionCapabilities']['client']['type'];
        $proCanDetectEngineName     = $providerConfig['metadata']['detectionCapabilities']['renderingEngine']['name'];
        $proCanDetectEngineVersion  = $providerConfig['metadata']['detectionCapabilities']['renderingEngine']['version'];
        $proCanDetectEngineManufacturer = $providerConfig['metadata']['detectionCapabilities']['renderingEngine']['manufacturer'];
        $proCanDetectOsName         = $providerConfig['metadata']['detectionCapabilities']['operatingSystem']['name'];
        $proCanDetectOsMarketingName = $providerConfig['metadata']['detectionCapabilities']['operatingSystem']['marketing-name'];
        $proCanDetectOsVersion      = $providerConfig['metadata']['detectionCapabilities']['operatingSystem']['version'];
        $proCanDetectOsManufacturer = $providerConfig['metadata']['detectionCapabilities']['operatingSystem']['manufacturer'];
        $proCanDetectOsBits         = $providerConfig['metadata']['detectionCapabilities']['operatingSystem']['bits'];
        $proCanDetectDeviceName     = $providerConfig['metadata']['detectionCapabilities']['device']['name'];
        $proCanDetectDeviceMarketingName = $providerConfig['metadata']['detectionCapabilities']['device']['marketing-name'];
        $proCanDetectDeviceManufacturer = $providerConfig['metadata']['detectionCapabilities']['device']['manufacturer'];
        $proCanDetectDeviceBrand    = $providerConfig['metadata']['detectionCapabilities']['device']['brand'];
        $proCanDetectDeviceDualOrientation = $providerConfig['metadata']['detectionCapabilities']['device']['dual-orientation'];
        $proCanDetectDeviceType     = $providerConfig['metadata']['detectionCapabilities']['device']['type'];
        $proCanDetectDeviceIsMobile = $providerConfig['metadata']['detectionCapabilities']['device']['isMobile'];
        $proCanDetectDeviceSimCount = $providerConfig['metadata']['detectionCapabilities']['device']['sim-count'];
        $proCanDetectDeviceDisplayWidth = $providerConfig['metadata']['detectionCapabilities']['device']['display-width'];
        $proCanDetectDeviceDisplayHeight = $providerConfig['metadata']['detectionCapabilities']['device']['display-height'];
        $proCanDetectDeviceDisplayIsTouch  = $providerConfig['metadata']['detectionCapabilities']['device']['isTouch'];
        $proCanDetectDeviceDisplayType = $providerConfig['metadata']['detectionCapabilities']['device']['display-type'];
        $proCanDetectDeviceDisplaySize = $providerConfig['metadata']['detectionCapabilities']['device']['display-size'];
        $proCommand                    = $providerConfig['command'] ?? null;

        $statementSelectProvider->bindValue(':proName', $proName, \PDO::PARAM_STR);
        $statementSelectProvider->bindValue(':proType', $type, \PDO::PARAM_STR);

        $statementSelectProvider->execute();

        $dbResultProvider = $statementSelectProvider->fetch(\PDO::FETCH_ASSOC);

        if (is_array($dbResultProvider)) {
            // update!
            $statementUpdateProvider->bindValue(':proId', $dbResultProvider['proId'], \PDO::PARAM_STR);
            $statementUpdateProvider->bindValue(':proType', $type, \PDO::PARAM_STR);
            $statementUpdateProvider->bindValue(':proName', $proName, \PDO::PARAM_STR);
            $statementUpdateProvider->bindValue(':proHomepage', $proHomepage, \PDO::PARAM_STR);
            $statementUpdateProvider->bindValue(':proVersion', $proVersion, \PDO::PARAM_STR);
            if (null !== $proReleaseDate) {
                $statementUpdateProvider->bindValue(':proLastReleaseDate', $proReleaseDate->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            } else {
                $statementUpdateProvider->bindValue(':proLastReleaseDate', null);
            }
            $statementUpdateProvider->bindValue(':proPackageName', $proPackageName, \PDO::PARAM_STR);
            $statementUpdateProvider->bindValue(':proLanguage', $proLanguage, \PDO::PARAM_STR);
            $statementUpdateProvider->bindValue(':proIsLocal', $proIsLocal, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proIsApi', $proIsApi, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proIsActive', $proIsActive, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientName', $proCanDetectClientName, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientModus', $proCanDetectClientModus, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientVersion', $proCanDetectClientVersion, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientManufacturer', $proCanDetectClientManufacturer, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientBits', $proCanDetectClientBits, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientIsBot', $proCanDetectClientIsBot, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientType', $proCanDetectClientType, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectEngineName', $proCanDetectEngineName, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectEngineVersion', $proCanDetectEngineVersion, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectEngineManufacturer', $proCanDetectEngineManufacturer, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectOsName', $proCanDetectOsName, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectOsMarketingName', $proCanDetectOsMarketingName, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectOsVersion', $proCanDetectOsVersion, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectOsManufacturer', $proCanDetectOsManufacturer, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectOsBits', $proCanDetectOsBits, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceName', $proCanDetectDeviceName, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceMarketingName', $proCanDetectDeviceMarketingName, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceManufacturer', $proCanDetectDeviceManufacturer, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceBrand', $proCanDetectDeviceBrand, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceDualOrientation', $proCanDetectDeviceDualOrientation, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceType', $proCanDetectDeviceType, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceIsMobile', $proCanDetectDeviceIsMobile, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceSimCount', $proCanDetectDeviceSimCount, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceDisplayWidth', $proCanDetectDeviceDisplayWidth, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceDisplayHeight', $proCanDetectDeviceDisplayHeight, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceDisplayIsTouch', $proCanDetectDeviceDisplayIsTouch, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceDisplayType', $proCanDetectDeviceDisplayType, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceDisplaySize', $proCanDetectDeviceDisplaySize, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCommand', $proCommand);

            $statementUpdateProvider->execute();

            $output->writeln(' - <info>existing provider updated</info>');
            return;
        }

        $statementInsertProvider->bindValue(':proId', Uuid::uuid4()->toString(), \PDO::PARAM_STR);
        $statementInsertProvider->bindValue(':proType', $type, \PDO::PARAM_STR);
        $statementInsertProvider->bindValue(':proName', $proName, \PDO::PARAM_STR);
        $statementInsertProvider->bindValue(':proHomepage', $proHomepage, \PDO::PARAM_STR);
        $statementInsertProvider->bindValue(':proVersion', $proVersion, \PDO::PARAM_STR);
        if (null !== $proReleaseDate) {
            $statementInsertProvider->bindValue(':proLastReleaseDate', $proReleaseDate->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        } else {
            $statementInsertProvider->bindValue(':proLastReleaseDate', null);
        }
        $statementInsertProvider->bindValue(':proPackageName', $proPackageName, \PDO::PARAM_STR);
        $statementInsertProvider->bindValue(':proLanguage', $proLanguage, \PDO::PARAM_STR);
        $statementInsertProvider->bindValue(':proIsLocal', $proIsLocal, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proIsApi', $proIsApi, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proIsActive', $proIsActive, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientName', $proCanDetectClientName, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientModus', $proCanDetectClientModus, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientVersion', $proCanDetectClientVersion, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientManufacturer', $proCanDetectClientManufacturer, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientBits', $proCanDetectClientBits, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientIsBot', $proCanDetectClientIsBot, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientType', $proCanDetectClientType, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectEngineName', $proCanDetectEngineName, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectEngineVersion', $proCanDetectEngineVersion, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectEngineManufacturer', $proCanDetectEngineManufacturer, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectOsName', $proCanDetectOsName, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectOsMarketingName', $proCanDetectOsMarketingName, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectOsVersion', $proCanDetectOsVersion, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectOsManufacturer', $proCanDetectOsManufacturer, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectOsBits', $proCanDetectOsBits, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceName', $proCanDetectDeviceName, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceMarketingName', $proCanDetectDeviceMarketingName, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceManufacturer', $proCanDetectDeviceManufacturer, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceBrand', $proCanDetectDeviceBrand, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceDualOrientation', $proCanDetectDeviceDualOrientation, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceType', $proCanDetectDeviceType, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceIsMobile', $proCanDetectDeviceIsMobile, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceSimCount', $proCanDetectDeviceSimCount, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceDisplayWidth', $proCanDetectDeviceDisplayWidth, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceDisplayHeight', $proCanDetectDeviceDisplayHeight, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceDisplayIsTouch', $proCanDetectDeviceDisplayIsTouch, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceDisplayType', $proCanDetectDeviceDisplayType, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceDisplaySize', $proCanDetectDeviceDisplaySize, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCommand', $proCommand);

        $statementInsertProvider->execute();

        $output->writeln(' - <info>new provider inserted</info>');
    }
}
