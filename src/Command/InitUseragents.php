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

        $output->writeln('~~~ Load all UAs ~~~');

        /** @var Helper\Result $resultHelper */
        $resultHelper = $this->getHelper('result');

        /** @var \UserAgentParserComparison\Command\Helper\Tests $testHelper */
        $testHelper = $this->getHelper('tests');

        foreach ($testHelper->collectTests($output, null) as $testPath => $testConfig) {
            $proName                    = $testConfig['metadata']['name'] ?? $testPath;
            $proVersion                 = $testConfig['metadata']['version'] ?? null;

            $statementSelectProvider->bindValue(':proName', $proName, \PDO::PARAM_STR);

            $statementSelectProvider->execute();

            $proId = $statementSelectProvider->fetch(\PDO::FETCH_COLUMN);

            $message  = sprintf('test suite <fg=yellow>%s</>', $testPath);
            $messageLength = mb_strlen($message);
            $output->write($message);

            $tests  = 0;

            foreach ($testConfig['build']() as $singleTestData) {
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
                        $statementUpdateUa->bindValue(':uaAdditionalHeaders', json_encode($additionalHeaders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                        $statementUpdateUa->execute();
                    }
                } else {
                    $uaId = Uuid::uuid4()->toString();

                    $statementInsertUa->bindValue(':uaId', $uaId, \PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaHash', $uaHash, \PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaString', $agent, \PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaAdditionalHeaders', json_encode($additionalHeaders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                    $statementInsertUa->execute();
                }

                /*
                 * Result
                 */
                $resultHelper->storeResult('0', $proId, $uaId, $singleTestData, $proVersion);

                ++$tests;

                $updateMessage = $message . sprintf(' <info>importing</info> [tests imported: %d]', $tests);
                $messageLength = mb_strlen($updateMessage);
                $output->write("\r" . $updateMessage);
            }

            $output->writeln("\r" . $message . str_pad(' <info>done</info>', $messageLength));
        }

        return self::SUCCESS;
    }
}
