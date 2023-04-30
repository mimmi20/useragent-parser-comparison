<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UserAgentParserComparison\Html\Index;
use UserAgentParserComparison\Html\OverviewGeneral;
use UserAgentParserComparison\Html\OverviewProvider;

use function assert;
use function file_exists;
use function file_put_contents;
use function is_string;
use function max;
use function mb_strlen;
use function mkdir;
use function sprintf;
use function str_pad;

/** @SuppressWarnings(PHPMD.ExcessiveClassLength) */
final class GenerateReports extends Command
{
    /** @throws void */
    public function __construct(private readonly PDO $pdo, private readonly string $version)
    {
        parent::__construct();
    }

    /** @throws void */
    protected function configure(): void
    {
        $this->setName('generate-reports')
            ->addArgument(
                'run',
                InputArgument::OPTIONAL,
                'The name of the test run directory that you want to normalize',
            )
            ->setHelp('');
    }

    /** @throws void */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $thisRunName = $input->getArgument('run');
        assert(is_string($thisRunName) || $thisRunName === null);
        $basePath = '../gh-pages/';

        if (empty($thisRunName)) {
            $version = 'v' . $this->version;

            $output->writeln(
                sprintf('<comment>Generate reports for version %s</comment>', $this->version),
            );
        } else {
            $version = 'r' . $thisRunName;

            $output->writeln(
                sprintf('<comment>Generate reports for test run: %s</comment>', $thisRunName),
            );
        }

        $basePath .= $version;

        if (!file_exists($basePath)) {
            mkdir($basePath, 0777, true);
        }

        if (empty($thisRunName)) {
            $output->write('generate index page ...');
            $generate = new Index($this->pdo, 'UserAgentParserComparison comparison');
            file_put_contents($basePath . '/../index.html', $generate->getHtml());
            $output->writeln("\r" . 'generate index page <info>done</info>');
        }

        $output->write('generate general overview page ...');
        $generate = new OverviewGeneral($this->pdo, 'UserAgentParserComparison comparison overview');
        file_put_contents($basePath . '/index.html', $generate->getHtml($version, $thisRunName));
        $output->writeln("\r" . 'generate general overview page <info>done</info>');

        $baseMessage      = 'generate overview page and found pages for';
        $messageLength    = 0;
        $messageLengthMax = 0;

        $output->write($baseMessage . ' each provider ...');
        $statementSelectProvider = $this->pdo->prepare(
            'SELECT * FROM `real-provider` WHERE `proId` IN (SELECT DISTINCT `provider_id` FROM `result` WHERE `run` = :runId)',
        );
        $statementSelectProvider->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
        $statementSelectProvider->execute();

        while (
            $dbResultProvider = $statementSelectProvider->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)
        ) {
            $message       = $baseMessage . sprintf(
                ' provider <fg=yellow>%s</> ',
                $dbResultProvider['proName'],
            );
            $messageLength = max($messageLength, mb_strlen($message));

            $message = str_pad($message, $messageLength);

            $output->write("\r" . $message);

            $generate = new OverviewProvider(
                $this->pdo,
                $dbResultProvider,
                'Overview - ' . $dbResultProvider['proName'],
            );

            file_put_contents(
                $basePath . '/' . $dbResultProvider['proName'] . '.html',
                $generate->getHtml($thisRunName),
            );

            $message .= '.';

            $messageLengthMax = max($messageLengthMax, mb_strlen($message));

            $output->write("\r" . str_pad($message, $messageLengthMax));

            //            $folder = $basePath . '/detected/' . $dbResultProvider['proName'];
            //            if (!file_exists($folder)) {
            //                mkdir($folder, 0777, true);
            //            }
            //
            //            /*
            //             * detected - browserNames
            //             */
            //            if ($dbResultProvider['proCanDetectClientName']) {
            //                $sql       = '
            //            SELECT
            //                `resClientName` AS `name`,
            //                `uaId`,
            //                `uaString`,
            //                COUNT(`resClientName`) AS `detectionCount`
            //            FROM `list-found-general-client-names`
            //            INNER JOIN `userAgent`
            //                ON `uaId` = `userAgent_id`
            //            WHERE
            //                `provider_id` = :proId AND
            //                `run` = :runId
            //            GROUP BY `resClientName`
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'Detected browser names - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/client-names.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }
            //
            //            /*
            //             * detected - renderingEngines
            //             */
            //            if ($dbResultProvider['proCanDetectEngineName']) {
            //                $sql       = '
            //            SELECT
            //                `resEngineName` AS `name`,
            //                `uaId`,
            //                `uaString`,
            //                COUNT(`resEngineName`) AS `detectionCount`
            //            FROM `list-found-general-engine-names`
            //            INNER JOIN `userAgent`
            //                ON `uaId` = `userAgent_id`
            //            WHERE
            //                `provider_id` = :proId AND
            //                `run` = :runId
            //            GROUP BY `resEngineName`
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'Detected rendering engines - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/rendering-engines.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }
            //
            //            /*
            //             * detected - OSnames
            //             */
            //            if ($dbResultProvider['proCanDetectOsName']) {
            //                $sql       = '
            //            SELECT
            //                `resOsName` AS `name`,
            //                `uaId`,
            //                `uaString`,
            //                COUNT(`resOsName`) AS `detectionCount`
            //            FROM `list-found-general-os-names`
            //            INNER JOIN `userAgent`
            //                ON `uaId` = `userAgent_id`
            //            WHERE
            //                `provider_id` = :proId AND
            //                `run` = :runId
            //            GROUP BY `resOsName`
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'Detected operating systems - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/operating-systems.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }
            //
            //            /*
            //             * detected - deviceBrand
            //             */
            //            if ($dbResultProvider['proCanDetectDeviceBrand']) {
            //                $sql       = '
            //            SELECT
            //                `resDeviceBrand` AS `name`,
            //                `uaId`,
            //                `uaString`,
            //                COUNT(`resDeviceBrand`) AS `detectionCount`
            //            FROM `list-found-general-device-brands`
            //            INNER JOIN `userAgent`
            //                ON `uaId` = `userAgent_id`
            //            WHERE
            //                `provider_id` = :proId AND
            //                `run` = :runId
            //            GROUP BY `resDeviceBrand`
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'Detected device brands - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/device-brands.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }
            //
            //            /*
            //             * detected - deviceModel
            //             */
            //            if ($dbResultProvider['proCanDetectDeviceName']) {
            //                $sql       = '
            //            SELECT
            //                `resDeviceName` AS `name`,
            //                `uaId`,
            //                `uaString`,
            //                COUNT(`resDeviceName`) AS `detectionCount`
            //            FROM `list-found-general-device-models`
            //            INNER JOIN `userAgent`
            //                ON `uaId` = `userAgent_id`
            //            WHERE
            //                `provider_id` = :proId AND
            //                `run` = :runId
            //            GROUP BY `resDeviceName`
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'Detected device models - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/device-models.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }
            //
            //            /*
            //             * detected - deviceTypes
            //             */
            //            if ($dbResultProvider['proCanDetectDeviceType']) {
            //                $sql       = '
            //            SELECT
            //                `resDeviceType` AS `name`,
            //                `uaId`,
            //                `uaString`,
            //                COUNT(`resDeviceType`) AS `detectionCount`
            //            FROM `list-found-general-device-types`
            //            INNER JOIN `userAgent`
            //                ON `uaId` = `userAgent_id`
            //            WHERE
            //                `provider_id` = :proId AND
            //                `run` = :runId
            //            GROUP BY `resDeviceType`
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'Detected device types - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/device-types.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }
            //
            //            /*
            //             * detected - bots
            //             */
            //            if ($dbResultProvider['proCanDetectClientIsBot']) {
            //                $sql       = '
            //            SELECT
            //                `resClientName` AS `name`,
            //                `uaId`,
            //                `uaString`,
            //                COUNT(`resClientName`) AS `detectionCount`
            //            FROM `list-found-general-client-isbot`
            //            INNER JOIN `userAgent`
            //                ON `uaId` = `userAgent_id`
            //            WHERE
            //                `provider_id` = :proId AND
            //                `run` = :runId
            //            GROUP BY `resClientName`
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'Detected as bot - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/bot-is-bot.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }
            //
            //            /*
            //             * detected - botTypes
            //             */
            //            if ($dbResultProvider['proCanDetectClientType']) {
            //                $sql       = '
            //            SELECT
            //                `resClientType` AS `name`,
            //                `uaId`,
            //                `uaString`,
            //                COUNT(`resClientType`) AS `detectionCount`
            //            FROM `list-found-general-client-types`
            //            INNER JOIN `userAgent`
            //                ON `uaId` = `userAgent_id`
            //            WHERE
            //                `provider_id` = :proId AND
            //                `run` = :runId
            //            GROUP BY `resClientType`
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'Detected client types - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/bot-types.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }
            //
            //            $folder = $basePath . '/not-detected/' . $dbResultProvider['proName'];
            //            if (!file_exists($folder)) {
            //                mkdir($folder, 0777, true);
            //            }
            //
            //            /*
            //             * no result found
            //             */
            //            $sql       = '
            //        SELECT
            //            `result`.`resClientName` AS `name`,
            //            `userAgent`.`uaId`,
            //            `userAgent`.`uaString`,
            //            (
            //                SELECT
            //                    COUNT(`found-results`.`resClientName`)
            //                FROM `found-results`
            //                WHERE
            //                    `found-results`.`userAgent_id` = `userAgent`.`uaId`
            //                AND `found-results`.`provider_id` != `result`.`provider_id`
            //            ) AS `detectionCount`
            //        FROM `result`
            //        INNER JOIN `userAgent`
            //            ON `userAgent`.`uaId` = `result`.`userAgent_id`
            //        WHERE
            //            `result`.`provider_id` = :proId
            //            AND `result`.`run` = :runId
            //            AND `result`.`resResultFound` = 0
            //    ';
            //            $statement = $this->pdo->prepare($sql);
            //            $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //            $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //            $statement->execute();
            //
            //            $generate = new SimpleList($this->pdo, 'Not detected - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //            $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //            file_put_contents($folder . '/no-result-found.html', $generate->getHtml());
            //
            //            $message .= '.';
            //
            //            $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //            $output->write("\r" . str_pad($message, $messageLengthMax));

            /*
             * browserName
             */
            //            if ($dbResultProvider['proCanDetectClientName']) {
            //                $sql       = '
            //            SELECT
            //                `found-results`.`resClientName` AS `name`,
            //                `userAgent`.`uaId`,
            //                `userAgent`.`uaString`,
            //                (
            //                    SELECT
            //                        COUNT(`list-found-general-client-names`.`resClientName`)
            //                    FROM `list-found-general-client-names`
            //                    WHERE
            //                        `list-found-general-client-names`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-client-names`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCount`,
            //                (
            //                    SELECT
            //                        COUNT(DISTINCT `list-found-general-client-names`.`resClientName`)
            //                    FROM `list-found-general-client-names`
            //                    WHERE
            //                        `list-found-general-client-names`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-client-names`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCountUnique`,
            //                (
            //                    SELECT
            //                        GROUP_CONCAT(DISTINCT `list-found-general-client-names`.`resClientName`)
            //                    FROM `list-found-general-client-names`
            //                    WHERE
            //                        `list-found-general-client-names`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-client-names`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionValuesDistinct`
            //            FROM `found-results`
            //            INNER JOIN `userAgent`
            //                ON `userAgent`.`uaId` = `found-results`.`userAgent_id`
            //            WHERE
            //                `found-results`.`provider_id` = :proId
            //                AND `found-results`.`run` = :runId
            //                AND `found-results`.`resClientIsBot` IS NULL
            //                AND `found-results`.`resClientName` IS NULL
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'No browser name found - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/client-names.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }

            /*
             * renderingEngine
             */
            //            if ($dbResultProvider['proCanDetectEngineName']) {
            //                $sql       = '
            //            SELECT
            //                `found-results`.`resEngineName` AS `name`,
            //                `userAgent`.`uaId`,
            //                `userAgent`.`uaString`,
            //                (
            //                    SELECT
            //                        COUNT(`list-found-general-engine-names`.`resEngineName`)
            //                    FROM `list-found-general-engine-names`
            //                    WHERE
            //                        `list-found-general-engine-names`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-engine-names`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCount`,
            //                (
            //                    SELECT
            //                        COUNT(DISTINCT `list-found-general-engine-names`.`resEngineName`)
            //                    FROM `list-found-general-engine-names`
            //                    WHERE
            //                        `list-found-general-engine-names`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-engine-names`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCountUnique`,
            //                (
            //                    SELECT
            //                        GROUP_CONCAT(DISTINCT `list-found-general-engine-names`.`resEngineName`)
            //                    FROM `list-found-general-engine-names`
            //                    WHERE
            //                        `list-found-general-engine-names`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-engine-names`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionValuesDistinct`
            //            FROM `found-results`
            //            INNER JOIN `userAgent`
            //                ON `userAgent`.`uaId` = `found-results`.`userAgent_id`
            //            WHERE
            //                `found-results`.`provider_id` = :proId
            //                AND `found-results`.`run` = :runId
            //                AND `found-results`.`resClientIsBot` IS NULL
            //                AND `found-results`.`resEngineName` IS NULL
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'No rendering engine found - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/rendering-engines.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }

            /*
             * OSname
             */
            //            if ($dbResultProvider['proCanDetectOsName']) {
            //                $sql       = '
            //            SELECT
            //                `found-results`.`resOsName` AS `name`,
            //                `userAgent`.`uaId`,
            //                `userAgent`.`uaString`,
            //                (
            //                    SELECT
            //                        COUNT(`list-found-general-os-names`.`resOsName`)
            //                    FROM `list-found-general-os-names`
            //                    WHERE
            //                        `list-found-general-os-names`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-os-names`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCount`,
            //                (
            //                    SELECT
            //                        COUNT(DISTINCT `list-found-general-os-names`.`resOsName`)
            //                    FROM `list-found-general-os-names`
            //                    WHERE
            //                        `list-found-general-os-names`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-os-names`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCountUnique`,
            //                (
            //                    SELECT
            //                        GROUP_CONCAT(DISTINCT `list-found-general-os-names`.`resOsName`)
            //                    FROM `list-found-general-os-names`
            //                    WHERE
            //                        `list-found-general-os-names`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-os-names`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionValuesDistinct`
            //            FROM `found-results`
            //            INNER JOIN `userAgent`
            //                ON `userAgent`.`uaId` = `found-results`.`userAgent_id`
            //            WHERE
            //                `found-results`.`provider_id` = :proId
            //                AND `found-results`.`run` = :runId
            //                AND `resClientIsBot` IS NULL
            //                AND `resOsName` IS NULL
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'No operating system found - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/operating-systems.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }

            /*
             * deviceBrand
             */
            //            if ($dbResultProvider['proCanDetectDeviceBrand']) {
            //                $sql       = '
            //            SELECT
            //                `found-results`.`resDeviceBrand` AS `name`,
            //                `userAgent`.`uaId`,
            //                `userAgent`.`uaString`,
            //                (
            //                    SELECT
            //                        COUNT(`list-found-general-device-brands`.`resDeviceBrand`)
            //                    FROM `list-found-general-device-brands`
            //                    WHERE
            //                        `list-found-general-device-brands`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-device-brands`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCount`,
            //                (
            //                    SELECT
            //                        COUNT(DISTINCT `list-found-general-device-brands`.`resDeviceBrand`)
            //                    FROM `list-found-general-device-brands`
            //                    WHERE
            //                        `list-found-general-device-brands`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-device-brands`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCountUnique`,
            //                (
            //                    SELECT
            //                        GROUP_CONCAT(DISTINCT `list-found-general-device-brands`.`resDeviceBrand`)
            //                    FROM `list-found-general-device-brands`
            //                    WHERE
            //                        `list-found-general-device-brands`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-device-brands`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionValuesDistinct`
            //            FROM `found-results`
            //            INNER JOIN `userAgent`
            //                ON `userAgent`.`uaId` = `found-results`.`userAgent_id`
            //            WHERE
            //                `found-results`.`provider_id` = :proId
            //                AND `found-results`.`run` = :runId
            //                AND `found-results`.`resClientIsBot` IS NULL
            //                AND `found-results`.`resDeviceBrand` IS NULL
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'No device brands found - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/device-brands.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }

            /*
             * deviceModel
             */
            //            if ($dbResultProvider['proCanDetectDeviceName']) {
            //                $sql       = '
            //            SELECT
            //                `found-results`.`resDeviceName` AS `name`,
            //                `userAgent`.`uaId`,
            //                `userAgent`.`uaString`,
            //                (
            //                    SELECT
            //                        COUNT(`list-found-general-device-models`.`resDeviceName`)
            //                    FROM `list-found-general-device-models`
            //                    WHERE
            //                        `list-found-general-device-models`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-device-models`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCount`,
            //                (
            //                    SELECT
            //                        COUNT(DISTINCT `list-found-general-device-models`.`resDeviceName`)
            //                    FROM `list-found-general-device-models`
            //                    WHERE
            //                        `list-found-general-device-models`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-device-models`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCountUnique`,
            //                (
            //                    SELECT
            //                        GROUP_CONCAT(DISTINCT `list-found-general-device-models`.`resDeviceName`)
            //                    FROM `list-found-general-device-models`
            //                    WHERE
            //                        `list-found-general-device-models`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-device-models`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionValuesDistinct`
            //            FROM `found-results`
            //            INNER JOIN `userAgent`
            //                ON `userAgent`.`uaId` = `found-results`.`userAgent_id`
            //            WHERE
            //                `found-results`.`provider_id` = :proId
            //                AND `found-results`.`run` = :runId
            //                AND `found-results`.`resClientIsBot` IS NULL
            //                AND `found-results`.`resDeviceName` IS NULL
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'No device model found - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/device-models.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }

            /*
             * deviceTypes
             */
            //            if ($dbResultProvider['proCanDetectDeviceType']) {
            //                $sql       = '
            //            SELECT
            //                `found-results`.`resDeviceType` AS `name`,
            //                `userAgent`.`uaId`,
            //                `userAgent`.`uaString`,
            //                (
            //                    SELECT
            //                        COUNT(`list-found-general-device-types`.`resDeviceType`)
            //                    FROM `list-found-general-device-types`
            //                    WHERE
            //                        `list-found-general-device-types`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-device-types`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCount`,
            //                (
            //                    SELECT
            //                        COUNT(DISTINCT `list-found-general-device-types`.`resDeviceType`)
            //                    FROM `list-found-general-device-types`
            //                    WHERE
            //                        `list-found-general-device-types`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-device-types`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCountUnique`,
            //                (
            //                    SELECT
            //                        GROUP_CONCAT(DISTINCT `list-found-general-device-types`.`resDeviceType`)
            //                    FROM `list-found-general-device-types`
            //                    WHERE
            //                        `list-found-general-device-types`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-device-types`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionValuesDistinct`
            //            FROM `found-results`
            //            INNER JOIN `userAgent`
            //                ON `userAgent`.`uaId` = `found-results`.`userAgent_id`
            //            WHERE
            //                `found-results`.`provider_id` = :proId
            //                AND `found-results`.`run` = :runId
            //                AND `found-results`.`resClientIsBot` IS NULL
            //                AND `found-results`.`resDeviceType` IS NULL
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'No device type found - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/device-types.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }

            /*
             * not detected as mobile
             */
            //            if ($dbResultProvider['proCanDetectDeviceIsMobile']) {
            //                $sql       = '
            //            SELECT
            //                `found-results`.`resClientName` AS `name`,
            //                `userAgent`.`uaId`,
            //                `userAgent`.`uaString`,
            //                (
            //                    SELECT
            //                        COUNT(`list-found-general-device-ismobile`.`resClientName`)
            //                    FROM `list-found-general-device-ismobile`
            //                    WHERE
            //                        `list-found-general-device-ismobile`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-device-ismobile`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCount`
            //            FROM `found-results`
            //            INNER JOIN `userAgent`
            //                ON `userAgent`.`uaId` = `found-results`.`userAgent_id`
            //            WHERE
            //                `found-results`.`provider_id` = :proId
            //                AND `found-results`.`run` = :runId
            //                AND `found-results`.`resDeviceIsMobile` IS NULL
            //                AND `userAgent`.`uaId` IN(
            //                    SELECT
            //                        `result`.`userAgent_id`
            //                    FROM `test-provider`
            //                    INNER JOIN `result`
            //                        ON `result`.`provider_id` = `test-provider`.`proId`
            //                        AND `result`.`resDeviceIsMobile` = 1
            //                )
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'Not detected as mobile - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/device-is-mobile.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }

            /*
             * not detected as bot
             */
            //            if ($dbResultProvider['proCanDetectClientIsBot']) {
            //                $sql       = '
            //            SELECT
            //              `found-results`.`resClientName` AS `name`,
            //              `userAgent`.`uaId`,
            //              `userAgent`.`uaString`,
            //              (
            //                  SELECT
            //                      COUNT(`list-found-general-client-isbot`.`resClientName`)
            //                  FROM `list-found-general-client-isbot`
            //                    WHERE
            //                      `list-found-general-client-isbot`.`userAgent_id` = `userAgent`.`uaId`
            //                      AND `list-found-general-client-isbot`.`provider_id` != `found-results`.`provider_id`
            //                ) as `detectionCount`
            //            FROM `found-results`
            //            INNER JOIN `userAgent`
            //                ON `userAgent`.`uaId` = `found-results`.`userAgent_id`
            //            WHERE
            //              `found-results`.`provider_id` = :proId
            //                AND `found-results`.`run` = :runId
            //                AND `found-results`.`resClientIsBot` IS NULL
            //              AND `userAgent`.`uaId` IN(
            //                  SELECT
            //                        `result`.`userAgent_id`
            //                    FROM `test-provider`
            //                    INNER JOIN `result`
            //                        ON `result`.`provider_id` = `test-provider`.`proId`
            //                      AND `result`.`resClientIsBot` = 1
            //                )
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'Not detected as bot - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/bot-is-bot.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }

            /*
             * botTypes
             */
            //            if ($dbResultProvider['proCanDetectClientType']) {
            //                $sql       = '
            //            SELECT
            //                `found-results`.`resClientType` AS `name`,
            //                `userAgent`.`uaId`,
            //                `userAgent`.`uaString`,
            //                (
            //                    SELECT
            //                        COUNT(`list-found-general-client-types`.`resClientType`)
            //                    FROM `list-found-general-client-types`
            //                    WHERE
            //                        `list-found-general-client-types`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-client-types`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCount`,
            //                (
            //                    SELECT
            //                        COUNT(DISTINCT `list-found-general-client-types`.`resClientType`)
            //                    FROM `list-found-general-client-types`
            //                    WHERE
            //                        `list-found-general-client-types`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-client-types`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionCountUnique`,
            //                (
            //                    SELECT
            //                        GROUP_CONCAT(DISTINCT `list-found-general-client-types`.`resClientType`)
            //                    FROM `list-found-general-client-types`
            //                    WHERE
            //                         `list-found-general-client-types`.`userAgent_id` = `userAgent`.`uaId`
            //                        AND `list-found-general-client-types`.`provider_id` != `found-results`.`provider_id`
            //                ) AS `detectionValuesDistinct`
            //            FROM `found-results`
            //            INNER JOIN `userAgent`
            //                ON `userAgent`.`uaId` = `found-results`.`userAgent_id`
            //            WHERE
            //                `found-results`.`provider_id` = :proId
            //                AND `found-results`.`run` = :runId
            //                AND `found-results`.`resClientType` IS NULL
            //                AND `userAgent`.`uaId` IN(
            //                    SELECT
            //                        `result`.`userAgent_id`
            //                    FROM `test-provider`
            //                    INNER JOIN `result`
            //                        ON `result`.`provider_id` = `test-provider`.`proId`
            //                        AND `result`.`resClientType` IS NOT NULL
            //                )
            //        ';
            //                $statement = $this->pdo->prepare($sql);
            //                $statement->bindValue(':proId', $dbResultProvider['proId'], PDO::PARAM_STR);
            //                $statement->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
            //
            //                $statement->execute();
            //
            //                $generate = new SimpleList($this->pdo, 'No bot type found - ' . $dbResultProvider['proName'] . ' <small>' . $dbResultProvider['proVersion'] . '</small>');
            //                $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
            //
            //                file_put_contents($folder . '/bot-types.html', $generate->getHtml());
            //
            //                $message .= '.';
            //
            //                $messageLengthMax = max($messageLengthMax, mb_strlen($message));
            //
            //                $output->write("\r" . str_pad($message, $messageLengthMax));
            //            }

            $message = $baseMessage . sprintf(
                ' provider <fg=yellow>%s</> <info>done</info>',
                $dbResultProvider['proName'],
            );

            $messageLengthMax2 = max($messageLengthMax + 13, mb_strlen($message));
            $output->writeln("\r" . str_pad($message, $messageLengthMax2));
        }

        $output->writeln(
            "\r" . str_pad($baseMessage . ' each provider <info>done</info>', $messageLength + 30),
        );

        //        $folder = $basePath . '/detected/general';
        //        if (!file_exists($folder)) {
        //            mkdir($folder, 0777, true);
        //        }

        //        $output->write('generate overview pages for found elements ');
        //        /*
        //         * detected - clientNames
        //         */
        //        $statement = $this->pdo->prepare('SELECT * FROM `found-general-client-names`');
        //        $statement->execute();
        //
        //        $generate = new SimpleList($this->pdo, 'Detected client names');
        //        $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
        //
        //        file_put_contents($folder . '/client-names.html', $generate->getHtml());
        //        $output->write('.');
        //
        //        /*
        //         * detected - renderingEngines
        //         */
        //        $statement = $this->pdo->prepare('SELECT * FROM `found-general-engine-names`');
        //        $statement->execute();
        //
        //        $generate = new SimpleList($this->pdo, 'Detected rendering engines');
        //        $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
        //
        //        file_put_contents($folder . '/rendering-engines.html', $generate->getHtml());
        //        $output->write('.');
        //
        //        /*
        //         * detected - OSnames
        //         */
        //        $statement = $this->pdo->prepare('SELECT * FROM `found-general-os-names`');
        //        $statement->execute();
        //
        //        $generate = new SimpleList($this->pdo, 'Detected operating systems');
        //        $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
        //
        //        file_put_contents($folder . '/operating-systems.html', $generate->getHtml());
        //        $output->write('.');
        //
        //        /*
        //         * detected - deviceModel
        //         */
        //        $statement = $this->pdo->prepare('SELECT * FROM `found-general-device-models`');
        //        $statement->execute();
        //
        //        $generate = new SimpleList($this->pdo, 'Detected device models');
        //        $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
        //
        //        file_put_contents($folder . '/device-models.html', $generate->getHtml());
        //        $output->write('.');
        //
        //        /*
        //         * detected - deviceBrand
        //         */
        //        $statement = $this->pdo->prepare('SELECT * FROM `found-general-device-brands`');
        //        $statement->execute();
        //
        //        $generate = new SimpleList($this->pdo, 'Detected device brands');
        //        $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
        //
        //        file_put_contents($folder . '/device-brands.html', $generate->getHtml());
        //        $output->write('.');
        //
        //        /*
        //         * detected - deviceTypes
        //         */
        //        $statement = $this->pdo->prepare('SELECT * FROM `found-general-device-types`');
        //        $statement->execute();
        //
        //        $generate = new SimpleList($this->pdo, 'Detected device types');
        //        $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
        //
        //        file_put_contents($folder . '/device-types.html', $generate->getHtml());
        //        $output->write('.');
        //
        //        /*
        //         * detected - botNames
        //         */
        //        $statement = $this->pdo->prepare('SELECT * FROM `found-general-client-names`');
        //        $statement->execute();
        //
        //        $generate = new SimpleList($this->pdo, 'Detected bot names');
        //        $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
        //
        //        file_put_contents($folder . '/bot-names.html', $generate->getHtml());
        //        $output->write('.');
        //
        //        /*
        //         * detected - botTypes
        //         */
        //        $statement = $this->pdo->prepare('SELECT * FROM `found-general-client-types`');
        //        $statement->execute();
        //
        //        $generate = new SimpleList($this->pdo, 'Detected bot types');
        //        $generate->setElements($statement->fetchAll(PDO::FETCH_ASSOC));
        //
        //        file_put_contents($folder . '/bot-types.html', $generate->getHtml());
        //
        //        $output->writeln("\r" . 'generate overview pages for found elements <info>done</info>  ');

        //        $output->write('generate useragent detail pages');
        //
        //        $statementSelectUa = $this->pdo->prepare('SELECT * FROM `useragent` WHERE `uaId` IN (SELECT DISTINCT `userAgent_id` FROM `result` WHERE `run` = :runId)');
        //        $statementSelectUa->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
        //        $statementSelectUa->execute();
        //
        //        $statementSelectResults = $this->pdo->prepare('SELECT `result`.*, `provider`.* FROM `result` INNER JOIN `provider` ON `result`.`provider_id` = `provider`.`proId` WHERE `result`.`userAgent_id` = :uaId AND `result`.`run` = :runId AND `provider`.`proIsActive` = 1 ORDER BY `provider`.`proName`');
        //        $uaCount                = 0;
        //
        //        while ($dbResultUa = $statementSelectUa->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
        //            $statementSelectResults->bindValue(':uaId', $dbResultUa['uaId'], PDO::PARAM_STR);
        //            $statementSelectResults->bindValue(':runId', $thisRunName ?? 0, PDO::PARAM_STR);
        //            $statementSelectResults->execute();
        //            $results = $statementSelectResults->fetchAll(PDO::FETCH_ASSOC);
        //
        //            if (0 === count($results)) {
        //                continue;
        //            }
        //
        //            $generate = new UserAgentDetail($this->pdo, 'User agent detail - ' . $dbResultUa['uaString']);
        //            $generate->setUserAgent($dbResultUa);
        //            $generate->setResults($results);
        //
        //            /*
        //             * create the folder
        //             */
        //            $folder = $basePath . '/user-agent-detail/' . mb_substr($dbResultUa['uaId'], 0, 2) . '/' . mb_substr($dbResultUa['uaId'], 2, 2);
        //            if (!file_exists($folder)) {
        //                mkdir($folder, 0777, true);
        //            }
        //
        //            /*
        //             * persist!
        //             */
        //            file_put_contents($folder . '/' . $dbResultUa['uaId'] . '.html', $generate->getHtml());
        //            ++$uaCount;
        //
        //            $output->write("\r" . 'generate useragent detail pages ' . $uaCount . ' [' . $dbResultUa['uaId'] . ']');
        //        }
        //
        //        $output->writeln("\r" . 'generate useragent detail pages <info>done</info>                                                  ');

        $output->writeln('<info>done</info>');

        return self::SUCCESS;
    }
}
