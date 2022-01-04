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
        $statementInsertProvider = $this->pdo->prepare('INSERT INTO `provider` (`proId`, `proType`, `proName`, `proHomepage`, `proVersion`, `proLastReleaseDate`, `proPackageName`, `proLanguage`, `proLocal`, `proApi`, `proIsActive`, `proCanDetectClientName`, `proCanDetectClientVersion`, `proCanDetectEngineName`, `proCanDetectEngineVersion`, `proCanDetectOsName`, `proCanDetectOsVersion`, `proCanDetectDeviceModel`, `proCanDetectDeviceBrand`, `proCanDetectDeviceType`, `proCanDetectDeviceIsMobile`, `proCanDetectDeviceIsTouch`, `proCanDetectClientIsBot`, `proCanDetectClientType`) VALUES (:proId, :proType, :proName, :proHomepage, :proVersion, :proLastReleaseDate, :proPackageName, :proLanguage, :proLocal, :proApi, :proIsActive, :proCanDetectClientName, :proCanDetectClientVersion, :proCanDetectEngineName, :proCanDetectEngineVersion, :proCanDetectOsName, :proCanDetectOsVersion, :proCanDetectDeviceModel, :proCanDetectDeviceBrand, :proCanDetectDeviceType, :proCanDetectDeviceIsMobile, :proCanDetectDeviceIsTouch, :proCanDetectClientIsBot, :proCanDetectClientType)');
        $statementUpdateProvider = $this->pdo->prepare('UPDATE `provider` SET `proType` = :proType, `proName` = :proName, `proHomepage` = :proHomepage, `proVersion` = :proVersion, `proLastReleaseDate` = :proLastReleaseDate, `proPackageName` = :proPackageName, `proLanguage` = :proLanguage, `proLocal` = :proLocal, `proApi` = :proApi, `proIsActive` = :proIsActive, `proCanDetectClientName` = :proCanDetectClientName, `proCanDetectClientVersion` = :proCanDetectClientVersion, `proCanDetectEngineName` = :proCanDetectEngineName, `proCanDetectEngineVersion` = :proCanDetectEngineVersion, `proCanDetectOsName` = :proCanDetectOsName, `proCanDetectOsVersion` = :proCanDetectOsVersion, `proCanDetectDeviceModel` = :proCanDetectDeviceModel, `proCanDetectDeviceBrand` = :proCanDetectDeviceBrand, `proCanDetectDeviceType` = :proCanDetectDeviceType, `proCanDetectDeviceIsMobile` = :proCanDetectDeviceIsMobile, `proCanDetectDeviceIsTouch` = :proCanDetectDeviceIsTouch, `proCanDetectClientIsBot` = :proCanDetectClientIsBot, `proCanDetectClientType` = :proCanDetectClientType WHERE `proId` = :proId');

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
        $proLocal                   = $providerConfig['metadata']['local'];
        $proApi                     = $providerConfig['metadata']['api'];
        $proIsActive                = $providerConfig['metadata']['isActive'] ?? 1;
        $proCanDetectBrowserName    = $providerConfig['metadata']['detectionCapabilities']['client']['name'];
        $proCanDetectBrowserVersion = $providerConfig['metadata']['detectionCapabilities']['client']['version'];
        $proCanDetectBotIsBot       = $providerConfig['metadata']['detectionCapabilities']['client']['isBot'];
        $proCanDetectBotType        = $providerConfig['metadata']['detectionCapabilities']['client']['type'];
        $proCanDetectEngineName     = $providerConfig['metadata']['detectionCapabilities']['renderingEngine']['name'];
        $proCanDetectEngineVersion  = $providerConfig['metadata']['detectionCapabilities']['renderingEngine']['version'];
        $proCanDetectOsName         = $providerConfig['metadata']['detectionCapabilities']['operatingSystem']['name'];
        $proCanDetectOsVersion      = $providerConfig['metadata']['detectionCapabilities']['operatingSystem']['version'];
        $proCanDetectDeviceModel    = $providerConfig['metadata']['detectionCapabilities']['device']['model'];
        $proCanDetectDeviceBrand    = $providerConfig['metadata']['detectionCapabilities']['device']['brand'];
        $proCanDetectDeviceType     = $providerConfig['metadata']['detectionCapabilities']['device']['type'];
        $proCanDetectDeviceIsMobile = $providerConfig['metadata']['detectionCapabilities']['device']['isMobile'];
        $proCanDetectDeviceIsTouch  = $providerConfig['metadata']['detectionCapabilities']['device']['isTouch'];

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
            $statementUpdateProvider->bindValue(':proLocal', $proLocal, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proApi', $proApi, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proIsActive', $proIsActive, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientName', $proCanDetectBrowserName, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientVersion', $proCanDetectBrowserVersion, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientIsBot', $proCanDetectBotIsBot, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectClientType', $proCanDetectBotType, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectEngineName', $proCanDetectEngineName, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectEngineVersion', $proCanDetectEngineVersion, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectOsName', $proCanDetectOsName, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectOsVersion', $proCanDetectOsVersion, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceModel', $proCanDetectDeviceModel, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceBrand', $proCanDetectDeviceBrand, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceType', $proCanDetectDeviceType, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceIsMobile', $proCanDetectDeviceIsMobile, \PDO::PARAM_INT);
            $statementUpdateProvider->bindValue(':proCanDetectDeviceIsTouch', $proCanDetectDeviceIsTouch, \PDO::PARAM_INT);

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
        $statementInsertProvider->bindValue(':proLocal', $proLocal, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proApi', $proApi, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proIsActive', $proIsActive, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientName', $proCanDetectBrowserName, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientVersion', $proCanDetectBrowserVersion, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientIsBot', $proCanDetectBotIsBot, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectClientType', $proCanDetectBotType, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectEngineName', $proCanDetectEngineName, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectEngineVersion', $proCanDetectEngineVersion, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectOsName', $proCanDetectOsName, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectOsVersion', $proCanDetectOsVersion, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceModel', $proCanDetectDeviceModel, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceBrand', $proCanDetectDeviceBrand, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceType', $proCanDetectDeviceType, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceIsMobile', $proCanDetectDeviceIsMobile, \PDO::PARAM_INT);
        $statementInsertProvider->bindValue(':proCanDetectDeviceIsTouch', $proCanDetectDeviceIsTouch, \PDO::PARAM_INT);

        $statementInsertProvider->execute();

        $output->writeln(' - <info>new provider inserted</info>');
    }
}
