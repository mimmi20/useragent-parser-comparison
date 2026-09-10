<?php

/**
 * This file is part of the mimmi20/useragent-parser-comparison package.
 *
 * Copyright (c) 2015-2026, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Html;

use Override;
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
    #[Override]
    public function getHtml(): string
    {
        $body = '
<div class="section">
    <h1 class="header center orange-text">Useragent parser comparison v' . COMPARISON_VERSION . '</h1>

    <div class="row center">
        <h5 class="header light">
            We took <strong>' . number_format(
        $this->getUserAgentCount(),
    ) . '</strong> different user agents and analyzed them with all providers below.<br />
            That way, it\'s possible to get a good overview of each provider
        </h5>
    </div>
</div>

<div class="section">
    <h3 class="header center orange-text">
        Detected by all providers
    </h3>

    ' . $this->getTableSummary() . '

</div>

<div class="section center">

    <h3 class="header center orange-text">
        Detected by all providers
    </h3>

    <a href="detected/general/client-names.html" class="btn waves-effect waves-light">
        Browser names
    </a><br /><br />

    <a href="detected/general/rendering-engines.html" class="btn waves-effect waves-light">
        Rendering engines
    </a><br /><br />

    <a href="detected/general/operating-systems.html" class="btn waves-effect waves-light">
        Operating systems
    </a><br /><br />

    <a href="detected/general/device-brands.html" class="btn waves-effect waves-light">
        Device brands
    </a><br /><br />

    <a href="detected/general/device-models.html" class="btn waves-effect waves-light">
        Device models
    </a><br /><br />

    <a href="detected/general/device-types.html" class="btn waves-effect waves-light">
        Device types
    </a><br /><br />

    <a href="detected/general/bot-names.html" class="btn waves-effect waves-light">
        Bot names
    </a><br /><br />

    <a href="detected/general/bot-types.html" class="btn waves-effect waves-light">
        Bot types
    </a><br /><br />

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
     * @return iterable<array<mixed>>
     *
     * @throws void
     */
    private function getProviders(): iterable
    {
        $statement = $this->pdo->prepare('SELECT
                `real-provider`.*,

                SUM(`result`.`resResultFound`) AS `resultFound`,
                SUM(`result`.`resResultError`) AS `resultError`,

                COUNT(`result`.`resClientName`) AS `clientNameFound`,
                COUNT(DISTINCT `result`.`resClientName`) AS `clientNameFoundUnique`,
                COUNT(`result`.`resClientVersion`) AS `clientVersionFound`,
                COUNT(`result`.`resClientIsBot`) AS `asBotDetected`,
                COUNT(`result`.`resClientType`) AS `clientTypeFound`,
                COUNT(DISTINCT `result`.`resClientType`) AS `clientTypeFoundUnique`,

                COUNT(`result`.`resEngineName`) AS `engineNameFound`,
                COUNT(DISTINCT `result`.`resEngineName`) AS `engineNameFoundUnique`,
                COUNT(`result`.`resEngineVersion`) AS `engineVersionFound`,

                COUNT(`result`.`resOsName`) AS `osNameFound`,
                COUNT(DISTINCT `result`.`resOsName`) AS `osNameFoundUnique`,
                COUNT(`result`.`resOsVersion`) AS `osVersionFound`,

                COUNT(`result`.`resDeviceBrand`) AS `deviceBrandFound`,
                COUNT(DISTINCT `result`.`resDeviceBrand`) AS `deviceBrandFoundUnique`,

                COUNT(`result`.`resDeviceModel`) AS `deviceModelFound`,
                COUNT(DISTINCT `result`.`resDeviceModel`) AS `deviceModelFoundUnique`,

                COUNT(`result`.`resDeviceType`) AS `deviceTypeFound`,
                COUNT(DISTINCT `result`.`resDeviceType`) AS `deviceTypeFoundUnique`,

                COUNT(`result`.`resDeviceIsMobile`) AS `asMobileDetected`,
                COUNT(`result`.`resDeviceIsTouch`) AS `asTouchDeviceDetected`,

                AVG(`result`.`resInitTime`) AS `avgInitTime`,
                AVG(`result`.`resParseTime`) AS `avgParseTime`,
                AVG(`result`.`resMemoryUsed`) AS `avgMemoryUsed`
            FROM `result`
            INNER JOIN `real-provider`
                ON `real-provider`.`proId` = `result`.`provider_id` AND `real-provider`.`proVersion` = `result`.`resProviderVersion`
            GROUP BY
                `real-provider`.`proId`,`real-provider`.`proVersion`
            ORDER BY
                `real-provider`.`proName`');
        $statement->execute();

        yield from $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return iterable<array{proName: string, countNumber: int}>
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
    private function getTableSummary(): string
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

        foreach ($this->getProviders() as $provider) {
            $html .= '<tr>';

            $html .= '<th>';

            if ($provider['proLocal']) {
                $html .= '<div><span class="material-icons">public_off</span>';

                switch ($provider['proLanguage']) {
                    case 'PHP':
                        $html .= '<span class="material-icons">php</span>';

                        break;
                    case 'JavaScript':
                        $html .= '<span class="material-icons">javascript</span>';

                        break;
                }

                $html .= '</div>';

                $html .= '<div>';

                if ($provider['proPackageName']) {
                    match ($provider['proLanguage']) {
                        'PHP' => $html        .= '<a href="https://packagist.org/packages/' . $provider['proPackageName'] . '">' . $provider['proName'] . '</a>',
                        'JavaScript' => $html .= '<a href="https://www.npmjs.com/package/' . $provider['proPackageName'] . '">' . $provider['proName'] . '</a>',
                        default => $html      .= $provider['proName'],
                    };
                } else {
                    $html .= $provider['proName'];
                }

                $html .= '<br /><small>' . $provider['proVersion'] . '</small>';

                if ($provider['proLastReleaseDate'] !== null) {
                    $html .= '<br /><small>' . $provider['proLastReleaseDate'] . '</small>';
                }

                $html .= '</div>';
            } elseif ($provider['proApi']) {
                $html .= '<div><span class="material-icons">public</span></div>';

                $html .= '<div>';

                if ($provider['proHomepage']) {
                    $html .= '<a href="' . $provider['proHomepage'] . '">' . $provider['proName'] . '</a>';
                } else {
                    $html .= $provider['proName'];
                }

                $html .= '</div>';
            }

            $html .= '</th>';

            $countOfUseragents = $this->getUserAgentCount();

            /*
             * Result found?
             */
            $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $provider['resultFound']);
            $html .= '<br />Tot.' . $provider['resultFound'];
            $html .= '<br />&nbsp;';
            $html .= '</td>';

            $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $provider['resultError']);
            $html .= '<br />Tot.' . $provider['resultError'];
            $html .= '<br />&nbsp;';
            $html .= '</td>';

            /*
             * Client
             */
            if ($provider['proCanDetectClientName']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['clientNameFound'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['clientNameFound'];
                $html .= '<br />Unq.' . $provider['clientNameFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($provider['proCanDetectClientVersion']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['clientVersionFound'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['clientVersionFound'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($provider['proCanDetectClientType']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['clientTypeFound'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['clientTypeFound'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($provider['proCanDetectClientIsBot']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['asBotDetected'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['asBotDetected'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            /*
             * Engine
             */
            if ($provider['proCanDetectEngineName']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['engineNameFound'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['engineNameFound'];
                $html .= '<br />Unq.' . $provider['engineNameFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($provider['proCanDetectEngineVersion']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['engineVersionFound'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['engineVersionFound'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            /*
             * OS
             */
            if ($provider['proCanDetectOsName']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['osNameFound'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['osNameFound'];
                $html .= '<br />Unq.' . $provider['osNameFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($provider['proCanDetectOsVersion']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['osVersionFound'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['osVersionFound'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            /*
             * device
             */
            if ($provider['proCanDetectDeviceBrand']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['deviceBrandFound'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['deviceBrandFound'];
                $html .= '<br />Unq.' . $provider['deviceBrandFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($provider['proCanDetectDeviceModel']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['deviceModelFound'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['deviceModelFound'];
                $html .= '<br />Unq.' . $provider['deviceModelFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($provider['proCanDetectDeviceType']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['deviceTypeFound'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['deviceTypeFound'];
                $html .= '<br />Unq.' . $provider['deviceTypeFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($provider['proCanDetectDeviceIsMobile']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['asMobileDetected'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['asMobileDetected'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($provider['proCanDetectDeviceIsTouch']) {
                $html .= '<td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    $provider['asTouchDeviceDetected'],
                    $provider['resultFound'],
                );
                $html .= '<br />Tot.' . $provider['asTouchDeviceDetected'];
                $html .= '<br />&nbsp;';
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
                        ' . number_format(round($provider['avgParseTime'] * 1000, 3), 3) . '
                    </a>
                </td>
            ';

            $html .= '
                <td>
                    <a class="tooltipped" data-position="top" data-delay="50" data-tooltip="' . htmlspecialchars(
                $info,
            ) . '">
                        ' . number_format(round($provider['avgMemoryUsed'], 2), 2) . '
                    </a>
                </td>
            ';

            $html .= '<td><a href="' . $provider['proName'] . '.html" class="btn waves-effect waves-light">Details</a></td>';

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
