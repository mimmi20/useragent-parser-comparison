<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Html;

use PDO;

use function number_format;
use function round;

final class OverviewProvider extends AbstractHtml
{
    /** @param string[] $provider */
    public function __construct(PDO $pdo, private array $provider, string | null $title = null)
    {
        $this->pdo   = $pdo;
        $this->title = $title;
    }

    public function getHtml(string | null $run = null): string
    {
        $body = '
<div class="section">
    <h1 class="header center orange-text">' . $this->provider['proName'] . ' overview</h1>

    <div class="row center">
        <h5 class="header light">
            We took <strong>' . $this->getUserAgentCount($run) . '</strong> different user agents and analyzed them with this provider<br />
        </h5>
    </div>
</div>

<div class="section">
    ';
        if ($this->provider['proIsLocal']) {
            $body .= '<div><span class="material-icons">public_off</span>';

            switch ($this->provider['proLanguage']) {
                case 'PHP':
                    $body .= '<span class="material-icons">php</span>';

                    break;
                case 'JavaScript':
                    $body .= '<span class="material-icons">javascript</span>';

                    break;
            }

            $body .= '</div>';

            $body .= '<div>';
            if ($this->provider['proPackageName']) {
                switch ($this->provider['proLanguage']) {
                    case 'PHP':
                        $body .= '<a href="https://packagist.org/packages/' . $this->provider['proPackageName'] . '">' . $this->provider['proName'] . '</a>';

                        break;
                    case 'JavaScript':
                        $body .= '<a href="https://www.npmjs.com/package/' . $this->provider['proPackageName'] . '">' . $this->provider['proName'] . '</a>';

                        break;
                    default:
                        $body .= $this->provider['proName'];
                }
            } else {
                $body .= $this->provider['proName'];
            }

            $body .= '<br /><small>' . $this->provider['proVersion'] . '</small>';
            if (null !== $this->provider['proLastReleaseDate']) {
                $body .= '<br /><small>' . $this->provider['proLastReleaseDate'] . '</small>';
            }

            $body .= '</div>';
        } elseif ($this->provider['proIsApi']) {
            $body .= '<div><span class="material-icons">public</span></div>';

            $body .= '<div>';
            if ($this->provider['proHomepage']) {
                $body .= '<a href="' . $this->provider['proHomepage'] . '">' . $this->provider['proName'] . '</a>';
            } else {
                $body .= $this->provider['proName'];
            }

            $body .= '</div>';
        }

        $body .= '
</div>

<div class="section">
    ' . $this->getTable() . '
</div>
';

        return parent::getHtmlCombined($body);
    }

    /** @return false|mixed[] */
    private function getResult(): array | false
    {
        $sql = '
            SELECT
                SUM(`resResultFound`) AS `resultFound`,
                SUM(`resResultError`) AS `resultError`,

                COUNT(`resClientName`) AS `clientNameFound`,
                COUNT(`resClientVersion`) AS `clientVersionFound`,
                COUNT(`resClientIsBot`) AS `asBotDetected`,
                COUNT(`resClientType`) AS `clientTypeFound`,

                COUNT(`resEngineName`) AS `engineNameFound`,
                COUNT(`resEngineVersion`) AS `engineVersionFound`,

                COUNT(`resOsName`) AS `osNameFound`,
                COUNT(`resOsVersion`) AS `osVersionFound`,

                COUNT(`resDeviceBrand`) AS `deviceBrandFound`,

                COUNT(`resDeviceName`) AS `deviceModelFound`,

                COUNT(`resDeviceType`) AS `deviceTypeFound`,

                COUNT(`resDeviceIsMobile`) AS `asMobileDetected`,
                COUNT(`resDeviceDisplayIsTouch`) AS `asTouchDeviceDetected`,

                AVG(`resInitTime`) AS `avgInitTime`,
                AVG(`resParseTime`) AS `avgParseTime`,
                AVG(`resMemoryUsed`) AS `avgMemoryUsed`
            FROM `result`
            INNER JOIN `real-provider` ON `proId` = `provider_id`
            WHERE
                `provider_id` = :proId
            GROUP BY
                `proId`
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':proId', $this->provider['proId'], PDO::PARAM_STR);
        $statement->execute();

        return $statement->fetch();
    }

    private function getTable(): string
    {
        $provider = $this->provider;

        $html = '<table class="striped">';

        /*
         * Header
         */
        $html .= '
            <thead>
                <tr>
                    <th colspan="2"></th>
                    <th>Total</th>
                    <th>Percent</th>
                    <th>Actions</th>
                </tr>
            </thead>
        ';

        /*
         * body
         */
        $countOfUseragents = $this->getUserAgentCount();

        $row = $this->getResult();

        $html .= '<tbody>';

        if (false === $row) {
            $html .= '
            <tr>
                <td colspan="5" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            </tr>

            ';

            $html .= '</tbody>';
            $html .= '</table>';

            return $html;
        }

        /*
         * Results found
         */
        $html .= '
            <tr>
            <th rowspan="2"></th>
            <th>Results</th>
            <td>' . $row['resultFound'] . '</td>
            <td>' . $this->getPercentCircle($countOfUseragents, $row['resultFound']) . '</td>
            <td>
                <a href="not-detected/' . $provider['proName'] . '/no-result-found.html" class="btn waves-effect waves-light">
                    Not detected
                </a>
            </td>
            </tr>
        ';
        $html .= '
            <tr>
            <th>Errors</th>
            <td>' . $row['resultError'] . '</td>
            <td>' . $this->getPercentCircle($countOfUseragents, $row['resultError']) . '</td>
            <td></td>
            </tr>
        ';

        /*
         * Client
         */
        $html .= '
                <tr>
                <th rowspan="4">Client</th>
                <th>Name</th>
            ';
        if ($provider['proCanDetectClientName']) {
            $html .= '
                <td>' . $row['clientNameFound'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['clientNameFound'], $row['resultFound']) . '</td>
                <td>
                    <a href="detected/' . $provider['proName'] . '/client-names.html" class="btn waves-effect waves-light">
                        Detected
                    </a>
                    <a href="not-detected/' . $provider['proName'] . '/client-names.html" class="btn waves-effect waves-light">
                        Not detected
                    </a>
                </td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        $html .= '
                <tr>
                <th>Version</th>
            ';
        if ($provider['proCanDetectClientVersion']) {
            $html .= '
                <td>' . $row['clientVersionFound'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['clientVersionFound'], $row['resultFound']) . '</td>
                <td></td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        $html .= '
                <tr>
                <th>Type</th>
            ';
        if ($provider['proCanDetectClientType']) {
            $html .= '
                <td>' . $row['clientTypeFound'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['clientTypeFound'], $row['resultFound']) . '</td>
                <td></td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        $html .= '
                <tr>
                <th>Is bot</th>
            ';
        if ($provider['proCanDetectClientIsBot']) {
            $html .= '
                <td>' . $row['asBotDetected'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['asBotDetected'], $row['resultFound']) . '</td>
                <td></td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        /*
         * engine
         */
        $html .= '
                <tr>
                <th rowspan="2">Rendering Engine</th>
                <th>Name</th>
            ';
        if ($provider['proCanDetectEngineName']) {
            $html .= '
                <td>' . $row['engineNameFound'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['engineNameFound'], $row['resultFound']) . '</td>
                <td>
                    <a href="detected/' . $provider['proName'] . '/rendering-engines.html" class="btn waves-effect waves-light">
                        Detected
                    </a>
                    <a href="not-detected/' . $provider['proName'] . '/rendering-engines.html" class="btn waves-effect waves-light">
                        Not detected
                    </a>
                </td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        $html .= '
                <tr>
                <th>Version</th>
            ';
        if ($provider['proCanDetectEngineVersion']) {
            $html .= '
                <td>' . $row['engineVersionFound'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['engineVersionFound'], $row['resultFound']) . '</td>
                <td></td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        /*
         * os
         */
        $html .= '
                <tr>
                <th rowspan="2">Operating System</th>
                <th>Name</th>
            ';
        if ($provider['proCanDetectOsName']) {
            $html .= '
                <td>' . $row['osNameFound'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['osNameFound'], $row['resultFound']) . '</td>
                <td>
                    <a href="detected/' . $provider['proName'] . '/operating-systems.html" class="btn waves-effect waves-light">
                        Detected
                    </a>
                    <a href="not-detected/' . $provider['proName'] . '/operating-systems.html" class="btn waves-effect waves-light">
                        Not detected
                    </a>
                </td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        $html .= '
                <tr>
                <th>Version</th>
            ';
        if ($provider['proCanDetectOsVersion']) {
            $html .= '
                <td>' . $row['osVersionFound'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['osVersionFound'], $row['resultFound']) . '</td>
                <td></td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        /*
         * device
         */
        $html .= '
                <tr>
                <th rowspan="5">Device</th>
                <th>Brand</th>
            ';
        if ($provider['proCanDetectDeviceBrand']) {
            $html .= '
                <td>' . $row['deviceBrandFound'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['deviceBrandFound'], $row['resultFound']) . '</td>
                <td>
                    <a href="detected/' . $provider['proName'] . '/device-brands.html" class="btn waves-effect waves-light">
                        Detected
                    </a>
                    <a href="not-detected/' . $provider['proName'] . '/device-brands.html" class="btn waves-effect waves-light">
                        Not detected
                    </a>
                </td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        $html .= '
                <tr>
                <th>Model</th>
            ';
        if ($provider['proCanDetectDeviceName']) {
            $html .= '
                <td>' . $row['deviceModelFound'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['deviceModelFound'], $row['resultFound']) . '</td>
                <td>
                    <a href="detected/' . $provider['proName'] . '/device-models.html" class="btn waves-effect waves-light">
                        Detected
                    </a>
                    <a href="not-detected/' . $provider['proName'] . '/device-models.html" class="btn waves-effect waves-light">
                        Not detected
                    </a>
                </td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        $html .= '
                <tr>
                <th>Type</th>
            ';
        if ($provider['proCanDetectDeviceType']) {
            $html .= '
                <td>' . $row['deviceTypeFound'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['deviceTypeFound'], $row['resultFound']) . '</td>
                <td>
                    <a href="detected/' . $provider['proName'] . '/device-types.html" class="btn waves-effect waves-light">
                        Detected
                    </a>
                    <a href="not-detected/' . $provider['proName'] . '/device-types.html" class="btn waves-effect waves-light">
                        Not detected
                    </a>
                </td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        $html .= '
                <tr>
                <th>Is mobile</th>
            ';
        if ($provider['proCanDetectDeviceIsMobile']) {
            $html .= '
                <td>' . $row['asMobileDetected'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['asMobileDetected'], $row['resultFound']) . '</td>
                <td>
                    <a href="not-detected/' . $provider['proName'] . '/device-is-mobile.html" class="btn waves-effect waves-light">
                        Not detected
                    </a>
                </td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        $html .= '
                <tr>
                <th>Is touch</th>
            ';
        if ($provider['proCanDetectDeviceDisplayIsTouch']) {
            $html .= '
                <td>' . $row['asTouchDeviceDetected'] . '</td>
                <td>' . $this->getPercentCircle($countOfUseragents, $row['asTouchDeviceDetected'], $row['resultFound']) . '</td>
                <td></td>
                </tr>
            ';
        } else {
            $html .= '
                <td colspan="3" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            ';
        }

        $html .= '
                </tr>
            ';

        /*
         * Parse time
         */
        $html .= '
            <tr>
            <th colspan="2">Parse time [ms]</th>
            <td>' . number_format(round($row['avgParseTime'] * 1000, 3), 3) . '</td>
            <td></td>
            <td></td>
            </tr>
        ';

        /*
         * Required memory
         */
        $html .= '
            <tr>
            <th colspan="2">Required memory</th>
            <td>' . number_format(round($row['avgMemoryUsed'], 2), 2) . '</td>
            <td></td>
            <td></td>
            </tr>
        ';

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }
}
