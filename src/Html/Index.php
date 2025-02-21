<?php

/**
 * This file is part of the mimmi20/useragent-parser-comparison package.
 *
 * Copyright (c) 2015-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Html;

use Override;

final class Index extends AbstractHtml
{
    /** @throws void */
    #[Override]
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

    /** @throws void */
    private function getButtons(): string
    {
        $html = '';

        for ($i = COMPARISON_VERSION; $i > 0; --$i) {
            $txt = 'Version ' . $i;

            if ($i === COMPARISON_VERSION) {
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
