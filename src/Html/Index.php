<?php
/**
 * This file is part of the browser-detector-version package.
 *
 * Copyright (c) 2016-2022, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Html;

final class Index extends AbstractHtml
{
    public function getHtml(): string
    {
        $body = '
<div class="section">
    <h1 class="header center orange-text">UserAgentParserComparison comparison</h1>

    <div class="row center">
        <p>
            See the comparison of different user agent parsers
        </p>

        ' . $this->getButtons() . '

        by Martin Keckeis (@ThaDafinser)
    </div>
</div>
';

        return parent::getHtmlCombined($body);
    }

    private function getButtons(): string
    {
        $html = '';

        for ($i = COMPARISON_VERSION; 0 < $i; --$i) {
            $txt = 'Version ' . $i;

            if (COMPARISON_VERSION === $i) {
                $txt .= ' (latest)';
            }

            $html .= '
                <a class="modal-trigger btn waves-effect waves-light"
                    href="v' . $i . '/index.html">
                    ' . $txt . '
                </a><br /><br />
            ';
        }

        return $html;
    }
}
