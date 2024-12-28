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

namespace UserAgentParserComparison\Html;

use Generator;
use PDO;

use function extension_loaded;
use function htmlspecialchars;
use function number_format;
use function round;
use function zend_version;

use const PHP_OS;
use const PHP_VERSION;

final class OverviewGeneral extends AbstractHtml
{
    /** @throws void */
    public function getHtml(string $version = '', string | null $run = null): string
    {
        $body = '
<div class="section">
    <h1 class="header center orange-text">Useragent parser comparison ' . $version . '</h1>

    <div class="row center">
        <h5 class="header light">
            We took <strong>' . number_format(
        $this->getUserAgentCount($run),
) . '</strong> different user agents and analyzed them with all providers below.<br />
            That way, it\'s possible to get a good overview of each provider
        </h5>
    </div>
</div>

<div class="section">
    <h3 class="header center orange-text">
        Detection Results
    </h3>

    ' . $this->getTableSummary($run) . '

</div>

<div class="section">
    <h3 class="header center orange-text">
        Sources of the user agents
    </h3>
    <div class="row center">
        <h5 class="header light">
            The user agents were extracted from different test suites when possible<br />
            <strong>Note</strong> The actual number of tested user agents can be higher in the test suite itself.
        </h5>
    </div>

    ' . $this->getTableTests() . '

</div>
';

        return parent::getHtmlCombined($body);
    }

    /**
     * @return array<mixed>|Generator
     *
     * @throws void
     */
    private function getProviders(string | null $run = null): iterable
    {
        $sql = 'SELECT
                `real-provider`.*,

                SUM(`result`.`resResultFound`) AS `resultFound`,
                SUM(`result`.`resResultError`) AS `resultError`,

                COUNT(`result-normalized`.`resNormaClientName`) AS `clientNameFound`,
                COUNT(DISTINCT `result-normalized`.`resNormaClientName`) AS `clientNameFoundUnique`,
                COUNT(`result-normalized`.`resNormaClientVersion`) AS `clientVersionFound`,
                COUNT(`result-normalized`.`resNormaClientIsBot`) AS `asBotDetected`,
                COUNT(`result-normalized`.`resNormaClientType`) AS `clientTypeFound`,
                COUNT(DISTINCT `result-normalized`.`resNormaClientType`) AS `clientTypeFoundUnique`,

                COUNT(`result-normalized`.`resNormaEngineName`) AS `engineNameFound`,
                COUNT(DISTINCT `result-normalized`.`resNormaEngineName`) AS `engineNameFoundUnique`,
                COUNT(`result-normalized`.`resNormaEngineVersion`) AS `engineVersionFound`,

                COUNT(`result-normalized`.`resNormaOsName`) AS `osNameFound`,
                COUNT(DISTINCT `result-normalized`.`resNormaOsName`) AS `osNameFoundUnique`,
                COUNT(`result-normalized`.`resNormaOsVersion`) AS `osVersionFound`,

                COUNT(`result-normalized`.`resNormaDeviceBrand`) AS `deviceBrandFound`,
                COUNT(DISTINCT `result-normalized`.`resNormaDeviceBrand`) AS `deviceBrandFoundUnique`,

                COUNT(`result-normalized`.`resNormaDeviceName`) AS `deviceModelFound`,
                COUNT(DISTINCT `result-normalized`.`resNormaDeviceName`) AS `deviceModelFoundUnique`,

                COUNT(`result-normalized`.`resNormaDeviceType`) AS `deviceTypeFound`,
                COUNT(DISTINCT `result-normalized`.`resNormaDeviceType`) AS `deviceTypeFoundUnique`,

                COUNT(`result-normalized`.`resNormaDeviceIsMobile`) AS `asMobileDetected`,
                COUNT(`result-normalized`.`resNormaDeviceDisplayIsTouch`) AS `asTouchDeviceDetected`,

                AVG(`result`.`resInitTime`) AS `avgInitTime`,
                AVG(`result`.`resParseTime`) AS `avgParseTime`,
                AVG(`result`.`resMemoryUsed`) AS `avgMemoryUsed`
            FROM `result-normalized`
            INNER JOIN `result`
                ON `result`.`resId` = `result-normalized`.`result_id`
            INNER JOIN `real-provider`
                ON `real-provider`.`proId` = `result`.`provider_id` AND (`real-provider`.`proVersion` = `result`.`resProviderVersion` OR ISNULL(`real-provider`.`proVersion`)) ';

        if ($run !== null) {
            $sql .= '
            WHERE `result`.`run` = :run';
        }

        $sql .= '
            GROUP BY
                `real-provider`.`proId`,`real-provider`.`proVersion`
            ORDER BY
                `real-provider`.`proName`';

        $statement = $this->pdo->prepare($sql);

        if ($run !== null) {
            $statement->bindValue(':run', $run, PDO::PARAM_STR);
        }

        $statement->execute();

        yield from $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<mixed>|Generator
     *
     * @throws void
     */
    private function getUserAgentPerProviderCount(): iterable
    {
        $statement = $this->pdo->prepare('SELECT
                `provider`.`proName`,
                COUNT(*) AS `countNumber`
            FROM `provider`
            JOIN `result`
                ON `result`.`provider_id` = `provider`.`proId`
            WHERE `proType` = \'testSuite\'
            GROUP BY `provider`.`proId`
            ORDER BY `provider`.`proName`');

        $statement->execute();

        yield from $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @throws void */
    private function getTableSummary(string | null $run = null): string
    {
        $html = '<table class="striped">';

        /*
         * Header
         */
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th colspan="3"></th>';
        $html .= '<th colspan="4">Client</th>';
        $html .= '<th colspan="2">Rendering Engine</th>';
        $html .= '<th colspan="2">Operating System</th>';
        $html .= '<th colspan="5">Device</th>';
        $html .= '<th colspan="3"></th>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<th>Provider</th>';
        $html .= '<th>Results</th>';
        $html .= '<th>Errors</th>';

        $html .= '<th>Name</th>';
        $html .= '<th>Version</th>';
        $html .= '<th>Type</th>';
        $html .= '<th>Is bot</th>';

        $html .= '<th>Name</th>';
        $html .= '<th>Version</th>';

        $html .= '<th>Name</th>';
        $html .= '<th>Version</th>';

        $html .= '<th>Brand</th>';
        $html .= '<th>Model</th>';
        $html .= '<th>Type</th>';
        $html .= '<th>Is mobile</th>';
        $html .= '<th>Is touch</th>';

        $html .= '<th>Parse time [ms]</th>';
        $html .= '<th>Required memory</th>';
        $html .= '<th>Actions</th>';

        $html .= '</tr>';
        $html .= '</thead>';

        /*
         * body
         */
        $html .= '<tbody>';

        foreach ($this->getProviders($run) as $row) {
            $html .= '<tr><th>';

            if ($row['proIsLocal']) {
                $html .= '<div><span class="material-icons">public_off</span>';

                switch ($row['proLanguage']) {
                    case 'PHP':
                        $html .= '<span class="material-icons">php</span>';

                        break;
                    case 'JavaScript':
                        $html .= '<span class="material-icons">javascript</span>';

                        break;
                }

                $html .= '</div>';

                $html .= '<div>';

                if ($row['proPackageName']) {
                    match ($row['proLanguage']) {
                        'PHP' => $html        .= '<a href="https://packagist.org/packages/' . $row['proPackageName'] . '">' . $row['proName'] . '</a>',
                        'JavaScript' => $html .= '<a href="https://www.npmjs.com/package/' . $row['proPackageName'] . '">' . $row['proName'] . '</a>',
                        default => $html      .= $row['proName'],
                    };
                } else {
                    $html .= $row['proName'];
                }

                $html .= '<br /><small>' . $row['proVersion'] . '</small>';

                if ($row['proLastReleaseDate'] !== null) {
                    $html .= '<br /><small>' . $row['proLastReleaseDate'] . '</small>';
                }

                $html .= '</div>';
            } elseif ($row['proIsApi']) {
                $html .= '<div><span class="material-icons">public</span></div>';

                $html .= '<div>';

                if ($row['proHomepage']) {
                    $html .= '<a href="' . $row['proHomepage'] . '">' . $row['proName'] . '</a>';
                } else {
                    $html .= $row['proName'];
                }

                $html .= '</div>';
            }

            $html .= '</th>';

            $countOfUseragents = $this->getUserAgentCount($run);

            /*
             * Result found?
             */
            $html .= '<td>' . $this->getPercentCircle(
                $countOfUseragents,
                (int) $row['resultFound'],
                $countOfUseragents,
            );
            $html .= '<br />' . $row['resultFound'];
            $html .= '</td>';

            $html .= '<td>' . $this->getPercentCircle($countOfUseragents, (int) $row['resultError']);
            $html .= '<br />' . $row['resultError'];
            $html .= '</td>';

            /*
             * Client
             */
            if ($row['proCanDetectClientName']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['clientNameFound'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['clientNameFound'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectClientVersion']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['clientVersionFound'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['clientVersionFound'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectClientType']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['clientTypeFound'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['clientTypeFound'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectClientIsBot']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['asBotDetected'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['asBotDetected'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            /*
             * Engine
             */
            if ($row['proCanDetectEngineName']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['engineNameFound'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['engineNameFound'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectEngineVersion']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['engineVersionFound'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['engineVersionFound'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            /*
             * OS
             */
            if ($row['proCanDetectOsName']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['osNameFound'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['osNameFound'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectOsVersion']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['osVersionFound'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['osVersionFound'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            /*
             * device
             */
            if ($row['proCanDetectDeviceBrand']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['deviceBrandFound'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['deviceBrandFound'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectDeviceName']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['deviceModelFound'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['deviceModelFound'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectDeviceType']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['deviceTypeFound'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['deviceTypeFound'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectDeviceIsMobile']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['asMobileDetected'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['asMobileDetected'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectDeviceDisplayIsTouch']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['asTouchDeviceDetected'],
                    (int) $row['resultFound'],
                );
                $html .= '<br />' . $row['asTouchDeviceDetected'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            $info = 'PHP v' . PHP_VERSION . ' | Zend v' . zend_version() . ' | On ' . PHP_OS;

            if (extension_loaded('xdebug')) {
                $info .= ' | with xdebug';
            }

            if (extension_loaded('zend opcache')) {
                $info .= ' | with opcache';
            }

            $html .= '
                <td>
                    <a class="tooltipped" data-position="top" data-delay="50" data-tooltip="' . htmlspecialchars(
                $info,
) . '">
                        ' . number_format(round((float) $row['avgParseTime'] * 1000, 3), 3) . '
                    </a>
                </td>
            ';

            $html .= '
                <td>
                    <a class="tooltipped" data-position="top" data-delay="50" data-tooltip="' . htmlspecialchars(
                $info,
) . '">
                        ' . number_format(round((float) $row['avgMemoryUsed'], 2), 2) . '
                    </a>
                </td>
            ';

            $html .= '<td><a href="' . $row['proName'] . '.html" class="btn waves-effect waves-light">Details</a></td>';

            $html .= '</tr>';
        }

        $html .= '</tbody>';

        return $html . '</table>';
    }

    /** @throws void */
    private function getTableTests(): string
    {
        $html = '<table class="striped">';

        /*
         * Header
         */
        $html .= '
            <thead>
                <tr>
                    <th>
                        Provider
                    </th>
                    <th class="right-align">
                        Number of user agents
                    </th>
                </tr>
            </thead>
        ';

        /*
         * Body
         */
        $html .= '<tbody>';

        foreach ($this->getUserAgentPerProviderCount() as $row) {
            $html .= '<tr>';

            $html .= '<td>' . $row['proName'] . '</td>';
            $html .= '<td class="right-align">' . number_format($row['countNumber']) . '</td>';

            $html .= '</tr>';
        }

        $html .= '</tbody>';

        return $html . '</table>';
    }
}
