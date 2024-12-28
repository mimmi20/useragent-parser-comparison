<?php

/**
 * This file is part of the browser-detector-version package.
 *
 * Copyright (c) 2016-2024, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use JsonException;
use PDO;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UserAgentParserComparison\Command\Helper\Tests;

use function assert;
use function bin2hex;
use function is_array;
use function json_decode;
use function json_encode;
use function mb_str_pad;
use function mb_strlen;
use function sha1;
use function shell_exec;
use function sprintf;
use function trim;
use function var_dump;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class InitUseragents extends Command
{
    /** @throws void */
    public function __construct(private readonly PDO $pdo)
    {
        parent::__construct();
    }

    /** @throws void */
    protected function configure(): void
    {
        $this->setName('init-useragents');
    }

    /**
     * @throws JsonException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $statementSelectProvider = $this->pdo->prepare(
            'SELECT `proId` FROM `test-provider` WHERE `proName` = :proName',
        );

        $statementSelectUa = $this->pdo->prepare('SELECT * FROM `userAgent` WHERE `uaHash` = :uaHash');
        $statementInsertUa = $this->pdo->prepare(
            'INSERT INTO `useragent` (`uaId`, `uaHash`, `uaString`, `uaAdditionalHeaders`) VALUES (:uaId, :uaHash, :uaString, :uaAdditionalHeaders)',
        );
        $statementUpdateUa = $this->pdo->prepare(
            'UPDATE `useragent` SET `uaHash` = :uaHash, `uaString` = :uaString, `uaAdditionalHeaders` = :uaAdditionalHeaders WHERE `uaId` = :uaId',
        );

        $output->writeln('~~~ Load all UAs ~~~');

        $resultHelper = $this->getHelper('result');
        assert($resultHelper instanceof Helper\Result);

        $testHelper = $this->getHelper('tests');
        assert($testHelper instanceof Tests);

        foreach ($testHelper->collectTests($output, null) as $testPath => $testConfig) {
            if (!$testConfig['metadata']['isActive']) {
                continue;
            }

            $proName    = $testConfig['metadata']['name'] ?? $testPath;
            $proVersion = $testConfig['metadata']['version'] ?? null;

            $statementSelectProvider->bindValue(':proName', $proName, PDO::PARAM_STR);

            $statementSelectProvider->execute();

            $proId = $statementSelectProvider->fetch(PDO::FETCH_COLUMN);

            $message       = sprintf('test suite <fg=yellow>%s</>', $testPath);
            $messageLength = mb_strlen($message);
            $output->write($message);

            $message = sprintf('test suite <fg=yellow>%s</>', $testPath);

            $output->write("\r" . $message . ' <info>building test suite</info>');

            $testOutput = shell_exec($testConfig['command']);

            if ($testOutput === null || $testOutput === false) {
                $output->writeln(
                    "\r" . $message . ' <error>There was an error with the output from the testsuite ' . $testPath . '! No content was sent.</error>',
                );

                continue;
            }

            $testOutput = trim($testOutput);

            try {
                $tests = json_decode($testOutput, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                var_dump($testOutput);
                $output->writeln(
                    "\r" . $message . ' <error>There was an error with the output from the testsuite ' . $testPath . '! json_decode failed.</error>',
                );

                continue;
            }

            if ($tests['tests'] === null || !is_array($tests['tests']) || $tests['tests'] === []) {
                var_dump($testOutput);
                $output->writeln(
                    "\r" . $message . ' <error>There was an error with the output from the testsuite ' . $testPath . '! No tests were found.</error>',
                );

                continue;
            }

            foreach ($tests['tests'] as $singleTestData) {
                $agent = $singleTestData['headers']['user-agent'] ?? null;

                if ($agent === null) {
//                    var_dump($singleTestData);exit;
//                    $output->writeln("\r" . $message . ' <error>There was no useragent header for the testsuite ' . $testName . '.</error>');
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

                if ($dbResultUa !== false) {
                    // update!
                    $uaId = $dbResultUa['uaId'];

                    if ($additionalHeaders !== null) {
                        $statementUpdateUa->bindValue(':uaId', $uaId, PDO::PARAM_STR);
                        $statementUpdateUa->bindValue(':uaHash', $uaHash, PDO::PARAM_STR);
                        $statementUpdateUa->bindValue(':uaString', $agent, PDO::PARAM_STR);
                        $statementUpdateUa->bindValue(
                            ':uaAdditionalHeaders',
                            json_encode(
                                $additionalHeaders,
                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                            ),
                        );

                        $statementUpdateUa->execute();
                    }
                } else {
                    $uaId = Uuid::uuid4()->toString();

                    $statementInsertUa->bindValue(':uaId', $uaId, PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaHash', $uaHash, PDO::PARAM_STR);
                    $statementInsertUa->bindValue(':uaString', $agent, PDO::PARAM_STR);
                    $statementInsertUa->bindValue(
                        ':uaAdditionalHeaders',
                        json_encode(
                            $additionalHeaders,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                        ),
                    );

                    $statementInsertUa->execute();
                }

                /*
                 * Result
                 */
                $resultHelper->storeResult('0', $proId, $uaId, $singleTestData, $proVersion);

                ++$tests;

                $updateMessage = $message . sprintf(
                    ' <info>importing</info> [tests imported: %d]',
                    $tests,
                );
                $messageLength = mb_strlen($updateMessage);
                $output->write("\r" . $updateMessage);
            }

            $output->writeln("\r" . $message . mb_str_pad(' <info>done</info>', $messageLength));
        }

        return self::SUCCESS;
    }
}
