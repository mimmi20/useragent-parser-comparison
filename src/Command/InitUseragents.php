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

use JsonException;
use Override;
use PDO;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_change_key_case;
use function assert;
use function date;
use function is_array;
use function json_decode;
use function json_encode;
use function mb_str_pad;
use function mb_strlen;
use function shell_exec;
use function sprintf;
use function trim;

use const CASE_LOWER;
use const JSON_PRETTY_PRINT;
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
    #[Override]
    protected function configure(): void
    {
        $this->setName('init-useragents');
    }

    /**
     * @throws JsonException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $statementSelectProvider = $this->pdo->prepare('SELECT * FROM `test-provider`');

        // $statementSelectUa = $this->pdo->prepare('SELECT * FROM `userAgent` WHERE `uaHash` = :uaHash');
        // $statementInsertUa = $this->pdo->prepare(
        //     'INSERT INTO `useragent` (`uaId`, `uaHash`, `uaString`, `uaAdditionalHeaders`) VALUES (:uaId, :uaHash, :uaString, :uaAdditionalHeaders)',
        // );
        // $statementUpdateUa = $this->pdo->prepare(
        //     'UPDATE `useragent` SET `uaHash` = :uaHash, `uaString` = :uaString, `uaAdditionalHeaders` = :uaAdditionalHeaders WHERE `uaId` = :uaId',
        // );

        $statementInsertUaHeaders = $this->pdo->prepare(
            'INSERT IGNORE INTO `useragent-headers` (`uaId`, `date`, `provider`, `providerVersion`, `headers`, `user-agent`, `device-stock-ua`, `x-device-user-agent`, `x-skyfire-version`, `x-skyfire-phone`, `x-bluecoat-via`, `x-operamini-phone-ua`, `x-operamini-phone`, `x-ucbrowser-ua`, `x-ucbrowser-device-ua`, `x-ucbrowser-device`, `x-ucbrowser-phone-ua`, `x-ucbrowser-phone`, `x-original-user-agent`, `x-bolt-phone-ua`, `x-mobile-ua`, `x-requested-with`, `ua-os`, `baidu-flyflow`, `x-wap-profile`, `x-puffin-ua`, `x-mobile-gateway`, `x-nb-content`, `sec-ch-ua`, `sec-ch-ua-arch`, `sec-ch-ua-bitness`, `sec-ch-ua-full-version`, `sec-ch-ua-full-version-list`, `sec-ch-ua-mobile`, `sec-ch-ua-model`, `sec-ch-ua-platform`, `sec-ch-ua-platform-version`)
                                            VALUES (:uaId,  :date,  :provider,  :providerVersion,  :headers,  :userAgent,  :deviceStockUa,     :xDeviceUserAgent,     :xSkyfireVersion,    :xSkyfirePhone,    :xBluecoatVia,    :xOperaminiPhoneUa,     :xOperaminiPhone,    :xUcbrowserUa,    :xUcbrowserDeviceUa,     :xUcbrowserDevice,    :xUcbrowserPhoneUa,     :xUcbrowserPhone,    :xOriginalUserAgent,     :xBoltPhoneUa,     :xMobileUa,    :xRequestedWith,    :uaOs,   :baiduFlyflow,   :xWapProfile,    :xPuffinUa,    :xMobileGateway,    :xNbContent,    :secChUa,    :secChUaArch,     :secChUaBitness,     :secChUaFullVersion,      :secChUaFullVersionList,       :secChUaMobile,     :secChUaModel,     :secChUaPlatform,     :secChUaPlatformVersion)',
        );

        $output->writeln('~~~ Load all UAs ~~~');

        $resultHelper = $this->getHelper('result');
        assert($resultHelper instanceof Helper\Result);

        $statementSelectProvider->execute();

        while ($row = $statementSelectProvider->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
            $proName    = $row['proName'];
            $proVersion = $row['proVersion'];
            // $proId      = $row['proId'];

            $message       = sprintf('test suite <fg=yellow>%s</>', $proName);
            $messageLength = mb_strlen($message);

            $output->write("\r" . $message . ' <info>building test suite</info>');

            if (!$row['proIsActive']) {
                $output->writeln(
                    "\r" . $message . ' <fg=gray>testsuite ' . $proName . ' is not active</>',
                );

                continue;
            }

            if (!$row['proCommand']) {
                $output->writeln(
                    "\r" . $message . ' <fg=gray>testsuite ' . $proName . ' has no command</>',
                );

                continue;
            }

            $testOutput = shell_exec($row['proCommand']);

            if ($testOutput === null || $testOutput === false) {
                $output->writeln(
                    "\r" . $message . ' <error>There was an error with the output from the testsuite ' . $proName . '! No content was sent.</error>',
                );

                continue;
            }

            $testOutput = trim($testOutput);

            try {
                $tests = json_decode($testOutput, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $output->writeln(
                    "\r" . $message . ' <error>There was an error with the output from the testsuite ' . $proName . '! json_decode failed.</error>',
                );

                continue;
            }

            if ($tests['tests'] === null || !is_array($tests['tests']) || $tests['tests'] === []) {
//                $output->writeln(
//                    "\r" . $message . ' <error>There was an error with the output from the testsuite ' . $proName . '! No tests were found.</error>',
//                );

                continue;
            }

            $inserted = 0;
            $updated  = 0;

            foreach ($tests['tests'] as $singleTestData) {
                $agent = $singleTestData['headers']['user-agent'] ?? null;

                if ($agent === null) {
//                    $output->writeln(
//                        "\r" . $message . ' <error>There was no useragent header for the testsuite ' . $proName . '.</error>',
//                    );

                    continue;
                }

                $uaId = Uuid::uuid4()->toString();

//                $uaHash = bin2hex(sha1((string) $agent, true));
//
//                /*
//                 * insert UA itself
//                 */
//                $statementSelectUa->bindValue(':uaHash', $uaHash, PDO::PARAM_STR);
//
//                $statementSelectUa->execute();
//
//                $dbResultUa = $statementSelectUa->fetch(PDO::FETCH_ASSOC);
//
//                $additionalHeaders = $singleTestData['headers'];
//                unset($additionalHeaders['user-agent']);
//
//                if (empty($additionalHeaders)) {
//                    $additionalHeaders = null;
//                }
//
//                if ($dbResultUa !== false) {
//                    // update!
//                    $uaId = $dbResultUa['uaId'];
//
//                    if ($additionalHeaders !== null) {
//                        $statementUpdateUa->bindValue(':uaId', $uaId, PDO::PARAM_STR);
//                        $statementUpdateUa->bindValue(':uaHash', $uaHash, PDO::PARAM_STR);
//                        $statementUpdateUa->bindValue(':uaString', $agent, PDO::PARAM_STR);
//                        $statementUpdateUa->bindValue(
//                            ':uaAdditionalHeaders',
//                            json_encode(
//                                $additionalHeaders,
//                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
//                            ),
//                        );
//
//                        $statementUpdateUa->execute();
//                    }
//
//                    ++$updated;
//                } else {
//                    $statementInsertUa->bindValue(':uaId', $uaId, PDO::PARAM_STR);
//                    $statementInsertUa->bindValue(':uaHash', $uaHash, PDO::PARAM_STR);
//                    $statementInsertUa->bindValue(':uaString', $agent, PDO::PARAM_STR);
//                    $statementInsertUa->bindValue(
//                        ':uaAdditionalHeaders',
//                        json_encode(
//                            $additionalHeaders,
//                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
//                        ),
//                    );
//
//                    $statementInsertUa->execute();
//
//                    ++$inserted;
//                }
//
//                /*
//                 * Result
//                 */
//                $resultHelper->storeResult('0', $proId, $uaId, $singleTestData, $proVersion);

                $headers = array_change_key_case($singleTestData['headers'], CASE_LOWER);

                $statementInsertUaHeaders->bindValue(':uaId', $uaId);
                $statementInsertUaHeaders->bindValue(':date', date('Y-m-d H:i:s'));
                $statementInsertUaHeaders->bindValue(':provider', $proName);
                $statementInsertUaHeaders->bindValue(':providerVersion', $proVersion);
                $statementInsertUaHeaders->bindValue(
                    ':headers',
                    json_encode(
                        $headers,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
                    ),
                );
                $statementInsertUaHeaders->bindValue(':userAgent', $headers['user-agent'] ?? null);
                $statementInsertUaHeaders->bindValue(
                    ':deviceStockUa',
                    $headers['device-stock-ua'] ?? $headers['http-device-stock-ua'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xDeviceUserAgent',
                    $headers['x-device-user-agent'] ?? $headers['http-x-device-user-agent'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xSkyfireVersion',
                    $headers['x-skyfire-version'] ?? $headers['http-x-skyfire-version'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xSkyfirePhone',
                    $headers['x-skyfire-phone'] ?? $headers['http-x-skyfire-phone'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xBluecoatVia',
                    $headers['x-bluecoat-via'] ?? $headers['http-x-bluecoat-via'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xOperaminiPhoneUa',
                    $headers['x-operamini-phone-ua'] ?? $headers['http-x-operamini-phone-ua'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xOperaminiPhone',
                    $headers['x-operamini-phone'] ?? $headers['http-x-operamini-phone'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xUcbrowserUa',
                    $headers['x-ucbrowser-ua'] ?? $headers['http-x-ucbrowser-ua'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xUcbrowserDeviceUa',
                    $headers['x-ucbrowser-device-ua'] ?? $headers['http-x-ucbrowser-device-ua'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xUcbrowserDevice',
                    $headers['x-ucbrowser-device'] ?? $headers['http-x-ucbrowser-device'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xUcbrowserPhoneUa',
                    $headers['x-ucbrowser-phone-ua'] ?? $headers['http-x-ucbrowser-phone-ua'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xUcbrowserPhone',
                    $headers['x-ucbrowser-phone'] ?? $headers['http-x-ucbrowser-phone'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xOriginalUserAgent',
                    $headers['x-original-user-agent'] ?? $headers['http-x-original-user-agent'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xBoltPhoneUa',
                    $headers['x-bolt-phone-ua'] ?? $headers['http-x-bolt-phone-ua'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xMobileUa',
                    $headers['x-mobile-ua'] ?? $headers['http-x-mobile-ua'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xRequestedWith',
                    $headers['x-requested-with'] ?? $headers['http-x-requested-with'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':uaOs',
                    $headers['ua-os'] ?? $headers['http-ua-os'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':baiduFlyflow',
                    $headers['baidu-flyflow'] ?? $headers['http-baidu-flyflow'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xWapProfile',
                    $headers['x-wap-profile'] ?? $headers['http-x-wap-profile'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xPuffinUa',
                    $headers['x-puffin-ua'] ?? $headers['http-x-puffin-ua'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xMobileGateway',
                    $headers['x-mobile-gateway'] ?? $headers['http-x-mobile-gateway'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':xNbContent',
                    $headers['x-nb-content'] ?? $headers['http-x-nb-content'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':secChUa',
                    $headers['sec-ch-ua'] ?? $headers['http-sec-ch-ua'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':secChUaArch',
                    $headers['sec-ch-ua-arch'] ?? $headers['http-sec-ch-ua-arch'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':secChUaBitness',
                    $headers['sec-ch-ua-bitness'] ?? $headers['http-sec-ch-ua-bitness'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':secChUaFullVersion',
                    $headers['sec-ch-ua-full-version'] ?? $headers['http-sec-ch-ua-full-version'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':secChUaFullVersionList',
                    $headers['sec-ch-ua-full-version-list'] ?? $headers['http-sec-ch-ua-full-version-list'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':secChUaMobile',
                    $headers['sec-ch-ua-mobile'] ?? $headers['http-sec-ch-ua-mobile'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':secChUaModel',
                    $headers['sec-ch-ua-model'] ?? $headers['http-sec-ch-ua-model'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':secChUaPlatform',
                    $headers['sec-ch-ua-platform'] ?? $headers['http-sec-ch-ua-platform'] ?? null,
                );
                $statementInsertUaHeaders->bindValue(
                    ':secChUaPlatformVersion',
                    $headers['sec-ch-ua-platform-version'] ?? $headers['http-sec-ch-ua-platform-version'] ?? null,
                );

                $statementInsertUaHeaders->execute();

                ++$inserted;

                $updateMessage = $message . sprintf(
                    ' <info>importing</info> [tests inserted: %d, updated: %d]',
                    $inserted,
                    $updated,
                );
                $messageLength = mb_strlen($updateMessage);

                $output->write("\r" . $updateMessage);
            }

            $output->writeln(
                "\r" . $message . mb_str_pad(' <info>importing done</info>', $messageLength),
            );
        }

        $output->writeln('<info>done!</info>');

        return self::SUCCESS;
    }
}
