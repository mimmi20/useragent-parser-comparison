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

use PDO;

use function number_format;
use function round;

final class OverviewProvider extends AbstractHtml
{
    /**
     * @param array<string> $provider
     *
     * @throws void
     */
    public function __construct(PDO $pdo, private readonly array $provider, string | null $title = null)
    {
        $this->pdo   = $pdo;
        $this->title = $title;
    }

    /** @throws void */
    public function getHtml(string | null $run = null): string
    {
        $body = '
<div class="section">
    <h1 class="header center orange-text">' . $this->provider['proName'] . ' overview</h1>

    <div class="row center">
        <h5 class="header light">
            We took <strong>' . $this->getUserAgentCount(
        $run,
) . '</strong> different user agents and analyzed them with this provider<br />
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
                match ($this->provider['proLanguage']) {
                    'PHP' => $body        .= '<a href="https://packagist.org/packages/' . $this->provider['proPackageName'] . '">' . $this->provider['proName'] . '</a>',
                    'JavaScript' => $body .= '<a href="https://www.npmjs.com/package/' . $this->provider['proPackageName'] . '">' . $this->provider['proName'] . '</a>',
                    default => $body      .= $this->provider['proName'],
                };
            } else {
                $body .= $this->provider['proName'];
            }

            $body .= '<br /><small>' . $this->provider['proVersion'] . '</small>';

            $body .= '<br /><small>' . $this->provider['proLastReleaseDate'] . '</small>';

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
    ' . $this->getTable($run) . '
</div>
';

        return parent::getHtmlCombined($body);
    }

    /** @throws void */
    private function getResult(string | null $run = null): array | false
    {
        $sql = '
            SELECT
                SUM(`result`.`resResultFound`) AS `resultFound`,
                SUM(`result`.`resResultError`) AS `resultError`,

                COUNT(`result-normalized`.`resNormaClientName`) AS `clientNameFound`,
                COUNT(`result-normalized`.`resNormaClientVersion`) AS `clientVersionFound`,
                COUNT(`result-normalized`.`resNormaClientIsBot`) AS `asBotDetected`,
                COUNT(`result-normalized`.`resNormaClientType`) AS `clientTypeFound`,

                COUNT(`result-normalized`.`resNormaEngineName`) AS `engineNameFound`,
                COUNT(`result-normalized`.`resNormaEngineVersion`) AS `engineVersionFound`,

                COUNT(`result-normalized`.`resNormaOsName`) AS `osNameFound`,
                COUNT(`result-normalized`.`resNormaOsVersion`) AS `osVersionFound`,

                COUNT(`result-normalized`.`resNormaDeviceBrand`) AS `deviceBrandFound`,

                COUNT(`result-normalized`.`resNormaDeviceName`) AS `deviceModelFound`,

                COUNT(`result-normalized`.`resNormaDeviceType`) AS `deviceTypeFound`,

                COUNT(`result-normalized`.`resNormaDeviceIsMobile`) AS `asMobileDetected`,
                COUNT(`result-normalized`.`resNormaDeviceDisplayIsTouch`) AS `asTouchDeviceDetected`,

                AVG(`result`.`resInitTime`) AS `avgInitTime`,
                AVG(`result`.`resParseTime`) AS `avgParseTime`,
                AVG(`result`.`resMemoryUsed`) AS `avgMemoryUsed`
            FROM `result-normalized`
            INNER JOIN `result`
                ON `result`.`resId` = `result-normalized`.`result_id`
            INNER JOIN `real-provider`
                ON `real-provider`.`proId` = `result`.`provider_id`
            WHERE
                `result`.`provider_id` = :proId AND
                `result`.`run` = :run
            GROUP BY
                `real-provider`.`proId`
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':proId', $this->provider['proId'], PDO::PARAM_STR);
        $statement->bindValue(':run', $run ?? 0, PDO::PARAM_STR);
        $statement->execute();

        return $statement->fetch();
    }

    /** @throws void */
    private function getTable(string | null $run = null): string
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
        $countOfUseragents = $this->getUserAgentCount($run);

        $row = $this->getResult($run);

        $html .= '<tbody>';

        if ($row === false) {
            $html .= '
            <tr>
                <td colspan="5" class="center-align red lighten-1">
                    <strong>Not available with this provider</strong>
                </td>
            </tr>

            ';

            $html .= '</tbody>';

            return $html . '</table>';
        }

        /*
         * Results found
         */
        $html .= '
            <tr>
            <th rowspan="2"></th>
            <th>Results</th>
            <td>' . $row['resultFound'] . '</td>
            <td>' . $this->getPercentCircle($countOfUseragents, (int) $row['resultFound']) . '</td>
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
            <td>' . $this->getPercentCircle($countOfUseragents, (int) $row['resultError']) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['clientNameFound'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['clientVersionFound'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['clientTypeFound'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['asBotDetected'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['engineNameFound'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['engineVersionFound'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['osNameFound'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['osVersionFound'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['deviceBrandFound'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['deviceModelFound'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['deviceTypeFound'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['asMobileDetected'],
                    (int) $row['resultFound'],
) . '</td>
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
                <td>' . $this->getPercentCircle(
                    $countOfUseragents,
                    (int) $row['asTouchDeviceDetected'],
                    (int) $row['resultFound'],
) . '</td>
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
            <td>' . number_format(round((float) $row['avgParseTime'] * 1000, 3), 3) . '</td>
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
            <td>' . number_format(round((float) $row['avgMemoryUsed'], 2), 2) . '</td>
            <td></td>
            <td></td>
            </tr>
        ';

        $html .= '</tbody>';

        return $html . '</table>';
    }
}
