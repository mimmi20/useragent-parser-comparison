<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
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
    public function getHtml(string $version = '', string | null $run = null): string
    {
        $body = '
<div class="section">
    <h1 class="header center orange-text">Useragent parser comparison ' . $version . '</h1>

    <div class="row center">
        <h5 class="header light">
            We took <strong>' . number_format($this->getUserAgentCount($run)) . '</strong> different user agents and analyzed them with all providers below.<br />
            That way, it\'s possible to get a good overview of each provider
        </h5>
    </div>
</div>

<div class="section">
    <h3 class="header center orange-text">
        Detected by all providers
    </h3>

    ' . $this->getTableSummary($run) . '

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

    /** @return Generator|mixed[] */
    private function getProviders(string | null $run = null): iterable
    {
        $sql = 'SELECT
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

                COUNT(`result`.`resDeviceName`) AS `deviceModelFound`,
                COUNT(DISTINCT `result`.`resDeviceName`) AS `deviceModelFoundUnique`,

                COUNT(`result`.`resDeviceType`) AS `deviceTypeFound`,
                COUNT(DISTINCT `result`.`resDeviceType`) AS `deviceTypeFoundUnique`,

                COUNT(`result`.`resDeviceIsMobile`) AS `asMobileDetected`,
                COUNT(`result`.`resDeviceDisplayIsTouch`) AS `asTouchDeviceDetected`,

                AVG(`result`.`resInitTime`) AS `avgInitTime`,
                AVG(`result`.`resParseTime`) AS `avgParseTime`,
                AVG(`result`.`resMemoryUsed`) AS `avgMemoryUsed`
            FROM `result`
            INNER JOIN `real-provider`
                ON `real-provider`.`proId` = `result`.`provider_id` AND (`real-provider`.`proVersion` = `result`.`resProviderVersion` OR ISNULL(`real-provider`.`proVersion`)) ';
        if (null !== $run) {
            $sql .= ' WHERE `result`.`run` = :run';
        }

        $sql .= '
            GROUP BY
                `real-provider`.`proId`,`real-provider`.`proVersion`
            ORDER BY
                `real-provider`.`proName`';

        $statement = $this->pdo->prepare($sql);

        if (null !== $run) {
            $statement->bindValue(':run', $run, PDO::PARAM_STR);
        }

        $statement->execute();

        yield from $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return Generator|mixed[] */
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
            $html .= '<tr>';

            $html .= '<th>';

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
                    switch ($row['proLanguage']) {
                        case 'PHP':
                            $html .= '<a href="https://packagist.org/packages/' . $row['proPackageName'] . '">' . $row['proName'] . '</a>';

                            break;
                        case 'JavaScript':
                            $html .= '<a href="https://www.npmjs.com/package/' . $row['proPackageName'] . '">' . $row['proName'] . '</a>';

                            break;
                        default:
                            $html .= $row['proName'];
                    }
                } else {
                    $html .= $row['proName'];
                }

                $html .= '<br /><small>' . $row['proVersion'] . '</small>';
                if (null !== $row['proLastReleaseDate']) {
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
            $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['resultFound']);
            $html .= '<br />Tot.' . $row['resultFound'];
            $html .= '<br />&nbsp;';
            $html .= '</td>';

            $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['resultError']);
            $html .= '<br />Tot.' . $row['resultError'];
            $html .= '<br />&nbsp;';
            $html .= '</td>';

            /*
             * Client
             */
            if ($row['proCanDetectClientName']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['clientNameFound'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['clientNameFound'];
                $html .= '<br />Unq.' . $row['clientNameFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectClientVersion']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['clientVersionFound'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['clientVersionFound'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectClientType']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['clientTypeFound'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['clientTypeFound'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectClientIsBot']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['asBotDetected'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['asBotDetected'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            /*
             * Engine
             */
            if ($row['proCanDetectEngineName']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['engineNameFound'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['engineNameFound'];
                $html .= '<br />Unq.' . $row['engineNameFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectEngineVersion']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['engineVersionFound'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['engineVersionFound'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            /*
             * OS
             */
            if ($row['proCanDetectOsName']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['osNameFound'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['osNameFound'];
                $html .= '<br />Unq.' . $row['osNameFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectOsVersion']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['osVersionFound'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['osVersionFound'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            /*
             * device
             */
            if ($row['proCanDetectDeviceBrand']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['deviceBrandFound'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['deviceBrandFound'];
                $html .= '<br />Unq.' . $row['deviceBrandFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectDeviceName']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['deviceModelFound'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['deviceModelFound'];
                $html .= '<br />Unq.' . $row['deviceModelFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectDeviceType']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['deviceTypeFound'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['deviceTypeFound'];
                $html .= '<br />Unq.' . $row['deviceTypeFoundUnique'];
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectDeviceIsMobile']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['asMobileDetected'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['asMobileDetected'];
                $html .= '<br />&nbsp;';
                $html .= '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($row['proCanDetectDeviceDisplayIsTouch']) {
                $html .= '<td>' . $this->getPercentCircle($countOfUseragents, $row['asTouchDeviceDetected'], $row['resultFound']);
                $html .= '<br />Tot.' . $row['asTouchDeviceDetected'];
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
                    <a class="tooltipped" data-position="top" data-delay="50" data-tooltip="' . htmlspecialchars($info) . '">
                        ' . number_format(round($row['avgParseTime'] * 1000, 3), 3) . '
                    </a>
                </td>
            ';

            $html .= '
                <td>
                    <a class="tooltipped" data-position="top" data-delay="50" data-tooltip="' . htmlspecialchars($info) . '">
                        ' . number_format(round($row['avgMemoryUsed'], 2), 2) . '
                    </a>
                </td>
            ';

            $html .= '<td><a href="' . $row['proName'] . '.html" class="btn waves-effect waves-light">Details</a></td>';

            $html .= '</tr>';
        }

        $html .= '</tbody>';

        $html .= '</table>';

        return $html;
    }

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

        $html .= '</table>';

        return $html;
    }
}
