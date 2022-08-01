<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace UserAgentParserComparison\Command;

use JsonException;
use PDO;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function bin2hex;
use function is_array;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function sha1;
use function shell_exec;
use function sprintf;
use function str_pad;
use function trim;
use function var_dump;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class InitUseragents extends Command
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('init-useragents');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $statementSelectProvider = $this->pdo->prepare('SELECT * FROM `test-provider`');

        $statementSelectUa = $this->pdo->prepare('SELECT * FROM `userAgent` WHERE `uaHash` = :uaHash');
        $statementInsertUa = $this->pdo->prepare('INSERT INTO `useragent` (`uaId`, `uaHash`, `uaString`, `uaAdditionalHeaders`) VALUES (:uaId, :uaHash, :uaString, :uaAdditionalHeaders)');
        $statementUpdateUa = $this->pdo->prepare('UPDATE `useragent` SET `uaHash` = :uaHash, `uaString` = :uaString, `uaAdditionalHeaders` = :uaAdditionalHeaders WHERE `uaId` = :uaId');

        $output->writeln('~~~ Load all UAs ~~~');

        $resultHelper = $this->getHelper('result');
        assert($resultHelper instanceof Helper\Result);

        $statementSelectProvider->execute();

        while ($row = $statementSelectProvider->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
            $proName = $row['proName'];
            $proVersion = $row['proVersion'];
            $proId = $row['proId'];

            $message = sprintf('test suite <fg=yellow>%s</>', $proName);
            $messageLength = mb_strlen($message);

            $output->write("\r" . $message . ' <info>building test suite</info>');

            if (!$row['proIsActive']) {
                $output->writeln("\r" . $message . ' <fg=gray>testsuite ' . $proName . ' is not active</>');

                continue;
            }

            if (!$row['proCommand']) {
                $output->writeln("\r" . $message . ' <fg=gray>testsuite ' . $proName . ' has no command</>');

                continue;
            }

            $testOutput = shell_exec($row['proCommand']);

            if (null === $testOutput || false === $testOutput) {
                $output->writeln("\r" . $message . ' <error>There was an error with the output from the testsuite ' . $proName . '! No content was sent.</error>');

                continue;
            }

            $testOutput = trim($testOutput);

            try {
                $tests = json_decode($testOutput, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                // var_dump($testOutput);
                var_dump($e->getMessage());
                $output->writeln("\r" . $message . ' <error>There was an error with the output from the testsuite ' . $proName . '! json_decode failed.</error>');

                continue;
            }

            if (null === $tests['tests'] || !is_array($tests['tests']) || [] === $tests['tests']) {
                var_dump($testOutput);
                $output->writeln("\r" . $message . ' <error>There was an error with the output from the testsuite ' . $proName . '! No tests were found.</error>');

                continue;
            }

            $inserted = 0;
            $updated = 0;

            foreach ($tests['tests'] as $singleTestData) {
                $agent = $singleTestData['headers']['user-agent'] ?? null;

                if (null === $agent) {
                    var_dump($singleTestData);
                    exit;
                    $output->writeln("\r" . $message . ' <error>There was no useragent header for the testsuite ' . $proName . '.</error>');
                    continue;
                }

                $uaHash = bin2hex(sha1($agent, true));

                /*
                 * insert UA itself
                 */
                $statementSelectUa->bindValue(':uaHash', $uaHash, PDO::PARAM_STR);

                $statementSelectUa->execute();

                $dbResultUa = $statementSelectUa->fetch(PDO::FETCH_ASSOC);

                $additionalHeaders = $singleTestData['headers'];
                unset($additionalHeaders['user-agent']);

                if (empty($additionalHeaders)) {
                    $additionalHeaders = null;
                }

                if (false !== $dbResultUa) {
                    // update!
                    $uaId = $dbResultUa['uaId'];

                    if (null !== $additionalHeaders) {
                        $statementUpdateUa->bindValue(':uaId', $uaId, PDO::PARAM_STR);
                        $statementUpdateUa->bindValue(':uaHash', $uaHash, PDO::PARAM_STR);
                        $statementUpdateUa->bindValue(':uaString', $agent, PDO::PARAM_STR);
                        $statementUpdateUa->bindValue(':uaAdditionalHeaders', json_encode($additionalHeaders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                        $statementUpdateUa->execute();
                    }

                    ++$updated;
                } else {
                    $uaId = Uuid::uuid4()->toString();

                    $statementInsertUa->bindValue(':uaId', $uaId, PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaHash', $uaHash, PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaString', $agent, PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaAdditionalHeaders', json_encode($additionalHeaders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                    $statementInsertUa->execute();

                    ++$inserted;
                }

                /*
                 * Result
                 */
                $resultHelper->storeResult('0', $proId, $uaId, $singleTestData, $proVersion);

                $updateMessage = $message . sprintf(' <info>importing</info> [tests inserted: %d, updated: %d]', $inserted, $updated);
                $messageLength = mb_strlen($updateMessage);
                $output->write("\r" . $updateMessage);
            }

            $output->writeln("\r" . $message . str_pad(' <info>importing done</info>', $messageLength));
        }

        return self::SUCCESS;
    }
}
