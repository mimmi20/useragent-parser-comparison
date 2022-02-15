<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Html;

use function count;
use function htmlspecialchars;

final class SimpleList extends AbstractHtml
{
    private array $elements = [];

    public function setElements(array $elements): void
    {
        $this->elements = $elements;
    }

    public function getHtml(): string
    {
        $body = '
<div class="section">
    <h1 class="header center orange-text">' . $this->title . '</h1>

    <div class="row center">
        ' . count($this->elements) . ' result(s) found
    </div>
</div>

<div class="section" id="simple-list">
    <form>
        <div class="input-field">
          <input class="search" type="search" placeholder="Search for a user agent">
          <i class="material-icons">close</i>
        </div>

        <a class="sort btn" data-sort="name">Sort by name</a>
        <a class="sort btn" data-sort="detectionCount">Sort by detection count</a>

    </form>

    ' . $this->getList() . '
</div>
';

        $script = '
var options = {
    page: 50000,
    valueNames: [
        \'name\',
        \'detectionCount\',
        \'userAgent\'
    ]
};

var hackerList = new List(\'simple-list\', options);
';

        return parent::getHtmlCombined($body, $script);
    }

    private function getList(): string
    {
        $html = '<ul class="list collection">';

        foreach ($this->elements as $element) {
            $html .= '<li class="collection-item">';

            $html .= '<h4 class="searchable"><span class="name">' . $element['name'] . '</span>';

            /*
             * Optional
             */
            if (isset($element['detectionCount'])) {
                $html .= ' <small class="detectionCount">' . $element['detectionCount'] . 'x detected</small>';
            }

            if (isset($element['detectionCountUnique'])) {
                $html .= ' <small class="detectionCountUnique">(' . $element['detectionCountUnique'] . 'x unique)</small>';
            }

            if (isset($element['detectionValuesDistinct'])) {
                $html .= '<br /><small class="detectionValuesDistinct">' . $element['detectionValuesDistinct'] . '</small>';
            }

            $html .= '</h4>';

            $html .= '<strong>Example user agent</strong><br />';

            $html .= '<span>';
            $html .= '<a href="' . $this->getUserAgentUrl($element['uaId']) . '" class="userAgent">' . htmlspecialchars($element['uaString']) . '</a>';
            $html .= '</span>';

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
