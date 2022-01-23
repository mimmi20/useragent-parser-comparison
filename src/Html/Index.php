<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Html;

class Index extends AbstractHtml
{
    private function getButtons(): string
    {
        $html = '';

        for ($i = COMPARISON_VERSION; $i > 0; $i--) {
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
}
