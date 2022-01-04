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
        $this->setName('init-results')
            ->addOption('run', 'r', InputOption::VALUE_OPTIONAL, 'The name of the test run, if omitted will be generated from date');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getOption('run');

        if (empty($name)) {
            $name = date('YmdHis');
        }

        /** @var Helper\Result $resultHelper */
        $resultHelper = $this->getHelper('result');

        $statementSelectProvider = $this->pdo->prepare('SELECT `proId` FROM `real-provider` WHERE `proName` = :proName');

        $statementCreateTempUas  = $this->pdo->prepare('CREATE TEMPORARY TABLE IF NOT EXISTS `temp_userAgent` AS (SELECT * FROM `userAgent` LIMIT :start, :count)');

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
                    $output->write(str_pad($message, $providerCount + 3) . ' - Count: ' . str_pad((string) $currenUserAgent, 8, ' ', STR_PAD_LEFT) . ' - ' . str_pad($proName, $nameLength));

                    [, $parserConfig, $proId] = $provider;

                    $singleResult = $parserConfig['parse-ua']($row['uaString']);

                    if (null === $singleResult) {
                        $message .= 'E';

                        $output->write(str_pad($message, $providerCount + 3) . ' - Count: ' . str_pad((string) $currenUserAgent, 8, ' ', STR_PAD_LEFT) . ' - ' . str_pad($proName, $nameLength));

                        continue;
                    }

                    $resultHelper->storeResult($name, $proId, $row['uaId'], $singleResult);

                    $message .= '.';

                    $output->write(str_pad($message, $providerCount + 3) . ' - Count: ' . str_pad((string) $currenUserAgent, 8, ' ', STR_PAD_LEFT) . ' - ' . str_pad($proName, $nameLength));
                }

                // display "progress"
                $output->writeln(str_pad($message, $providerCount + 3) . ' - Count: ' . str_pad((string) $currenUserAgent, 8, ' ', STR_PAD_LEFT) . '   ' . str_pad(' ', $nameLength));

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
        ];

        return array_merge($row2, $toHydrate);
    }
}
