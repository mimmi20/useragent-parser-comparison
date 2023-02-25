<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Html;

use JsonException;

use function array_key_exists;
use function count;
use function htmlspecialchars;
use function is_array;
use function json_decode;
use function number_format;
use function print_r;
use function round;

use const JSON_THROW_ON_ERROR;

final class UserAgentDetail extends AbstractHtml
{
    /** @var array<string> */
    private array $userAgent = [];

    /** @var array<array<mixed>> */
    private array $results = [];

    /**
     * @param array<string> $userAgent
     *
     * @throws void
     */
    public function setUserAgent(array $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    /**
     * @param array<array<mixed>> $results
     *
     * @throws void
     */
    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    /** @throws JsonException */
    public function getHtml(): string
    {
        $addStr = '';

        if (null !== $this->userAgent['uaAdditionalHeaders']) {
            $addHeaders = json_decode($this->userAgent['uaAdditionalHeaders'], true, 512, JSON_THROW_ON_ERROR);

            if (is_array($addHeaders) && 0 < count($addHeaders)) {
                $addStr = '<br /><strong>Additional headers</strong><br />';

                foreach ($addHeaders as $key => $value) {
                    $addStr .= '<strong>' . htmlspecialchars($key) . '</strong> ' . htmlspecialchars($value) . '<br />';
                }
            }
        }

        $body = '
<div class="section">
    <h1 class="header center orange-text">User agent detail</h1>
    <div class="row center">
        <h5 class="header light">
            ' . htmlspecialchars($this->userAgent['uaString']) . '
            ' . $addStr . '
        </h5>
    </div>
</div>

<div class="section">
    ' . $this->getProvidersTable() . '
</div>
';

        $script = '
(() => {
    const allModalTriggers = document.querySelectorAll(\'.modal-trigger\');
    allModalTriggers.forEach(function (modalTrigger) {
        const dialog = document.getElementById(modalTrigger.getAttribute(\'data-modal\'));
        const cancelButton = dialog.querySelectorAll(".modal-close")[0];

        modalTrigger.addEventListener("click", () => {
            dialog.showModal();
        });

        cancelButton.addEventListener("click", () => {
            dialog.close();
        });
    });
})();
        ';

        return parent::getHtmlCombined($body, $script);
    }

    /** @throws void */
    private function getProvidersTable(): string
    {
        $html = '<table class="striped">';

        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th></th>';
        $html .= '<th colspan="4">Client</th>';
        $html .= '<th colspan="2">Rendering Engine</th>';
        $html .= '<th colspan="2">Operating System</th>';
        $html .= '<th colspan="5">Device</th>';
        $html .= '<th colspan="3"></th>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<th>Provider</th>';

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
         * Test suite
         */
        $html .= '<tbody>';
        $html .= '<tr><th colspan="17" class="green lighten-3">';
        $html .= 'Test suite';
        $html .= '</th></tr>';

        foreach ($this->results as $result) {
            if (!array_key_exists('proType', $result) || 'testSuite' !== $result['proType']) {
                continue;
            }

            $html .= $this->getRow($result);
        }

        /*
         * Providers
         */
        $html .= '<tr><th colspan="17" class="green lighten-3">';
        $html .= 'Providers';
        $html .= '</th></tr>';

        foreach ($this->results as $result) {
            if (!array_key_exists('proType', $result) || 'real' !== $result['proType']) {
                continue;
            }

            $html .= $this->getRow($result);
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * @param array<mixed> $result
     *
     * @throws void
     */
    private function getRow(array $result): string
    {
        $html = '<tr>';

        $html .= '<th>';

        if ($result['proIsLocal']) {
            $html .= '<div><span class="material-icons">public_off</span>';

            switch ($result['proLanguage']) {
                case 'PHP':
                    $html .= '<span class="material-icons">php</span>';

                    break;
                case 'JavaScript':
                    $html .= '<span class="material-icons">javascript</span>';

                    break;
            }

            $html .= '</div>';

            $html .= '<div>';

            if ($result['proPackageName']) {
                switch ($result['proLanguage']) {
                    case 'PHP':
                        $html .= '<a href="https://packagist.org/packages/' . $result['proPackageName'] . '">' . $result['proName'] . '</a>';

                        break;
                    case 'JavaScript':
                        $html .= '<a href="https://www.npmjs.com/package/' . $result['proPackageName'] . '">' . $result['proName'] . '</a>';

                        break;
                    default:
                        $html .= $result['proName'];
                }
            } else {
                $html .= $result['proName'];
            }

            $html .= '<br /><small>' . $result['proVersion'] . '</small>';

            if (null !== $result['proLastReleaseDate']) {
                $html .= '<br /><small>' . $result['proLastReleaseDate'] . '</small>';
            }

            $html .= '</div>';
        } elseif ($result['proIsApi']) {
            $html .= '<div><span class="material-icons">public</span></div>';

            $html .= '<div>';

            if ($result['proHomepage']) {
                $html .= '<a href="' . $result['proHomepage'] . '">' . $result['proName'] . '</a>';
            } else {
                $html .= $result['proName'];
            }

            $html .= '</div>';
        }

        $html .= '</th>';

        if ($result['resResultError']) {
            $html .= '
                    <td colspan="13" class="center-align red lighten-1">
                        <strong>Error during detection</strong>
                    </td>
                ';
        } elseif (!$result['resResultFound']) {
            $html .= '
                    <td colspan="13" class="center-align red lighten-1">
                        <strong>No result found</strong>
                    </td>
                ';
        } else {
            /*
             * General
             */
            if ($result['proCanDetectClientName']) {
                $html .= '<td>' . $result['resClientName'] . '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($result['proCanDetectClientVersion']) {
                $html .= '<td>' . $result['resClientVersion'] . '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($result['proCanDetectClientType']) {
                $html .= '<td>' . $result['resClientType'] . '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if (array_key_exists('proCanDetectClientIsBot', $result) && null !== $result['proCanDetectClientIsBot']) {
                if ($result['resClientIsBot']) {
                    $html .= '<td>yes</td>';
                } else {
                    $html .= '<td>no</td>';
                }
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($result['proCanDetectEngineName']) {
                $html .= '<td>' . $result['resEngineName'] . '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($result['proCanDetectEngineVersion']) {
                $html .= '<td>' . $result['resEngineVersion'] . '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($result['proCanDetectOsName']) {
                $html .= '<td>' . $result['resOsName'] . '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($result['proCanDetectOsVersion']) {
                $html .= '<td>' . $result['resOsVersion'] . '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            /*
             * Device
             */
            if ($result['proCanDetectDeviceBrand']) {
                $html .= '<td>' . $result['resDeviceBrand'] . '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($result['proCanDetectDeviceName']) {
                $html .= '<td>' . $result['resDeviceName'] . '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($result['proCanDetectDeviceType']) {
                $html .= '<td>' . $result['resDeviceType'] . '</td>';
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($result['proCanDetectDeviceIsMobile']) {
                if ($result['resDeviceIsMobile']) {
                    $html .= '<td>yes</td>';
                } else {
                    $html .= '<td>no</td>';
                }
            } else {
                $html .= '<td class="center-align">x</td>';
            }

            if ($result['proCanDetectDeviceDisplayIsTouch']) {
                if ($result['resDeviceDisplayIsTouch']) {
                    $html .= '<td>yes</td>';
                } else {
                    $html .= '<td>no</td>';
                }
            } else {
                $html .= '<td class="center-align">x</td>';
            }
        }

        $html .= '<td>' . number_format(round((float) $result['resParseTime'] * 1000, 3), 3) . '</td>';

        $html .= '<td>' . number_format(round((float) $result['resMemoryUsed'], 2), 2) . '</td>';

        $html .= '<td>

<!-- Modal Trigger -->
<a class="modal-trigger btn waves-effect waves-light" href="#" data-modal="modal-' . $result['proId'] . '">Detail</a>

<!-- Modal Structure -->
<dialog id="modal-' . $result['proId'] . '">
    <div class="modal-content">
        <h4>' . $result['proName'] . ' result detail</h4>
        <p><pre><code class="php">' . print_r($result['resRawResult'], true) . '</code></pre></p>
    </div>
    <div class="modal-footer">
        <a href="#!" class="modal-action modal-close waves-effect waves-green btn-flat ">close</a>
    </div>
</dialog>

                </td>';

        $html .= '</tr>';

        return $html;
    }
}
