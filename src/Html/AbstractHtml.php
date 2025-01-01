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

use PDO;

use function date;
use function htmlspecialchars;
use function mb_substr;
use function number_format;
use function round;

abstract class AbstractHtml
{
    private int | null $userAgentCount = null;

    /** @throws void */
    public function __construct(protected PDO $pdo, protected string | null $title = null)
    {
    }

    /** @throws void */
    abstract public function getHtml(): string;

    /** @throws void */
    final protected function getUserAgentCount(): int
    {
        if ($this->userAgentCount === null) {
            $statementCountAllResults = $this->pdo->prepare(
                'SELECT COUNT(*) AS `count` FROM `userAgent`',
            );
            $statementCountAllResults->execute();

            $this->userAgentCount = $statementCountAllResults->fetch(PDO::FETCH_COLUMN);
        }

        return $this->userAgentCount;
    }

    /** @throws void */
    final protected function getPercentCircle(
        int $countOfUseragents,
        int $resultFound,
        int | null $resultFound2 = null,
    ): string {
        $html = '
            <div class="svg-item">
                <svg width="100%" height="100%" viewBox="0 0 40 40" class="donut">
                    <circle class="donut-ring"></circle>
                    ';

        if ($resultFound2 !== null) {
            $html .= '<circle class="donut-segment donut-segment-3" stroke-dasharray="' . $this->calculatePercent(
                $resultFound2,
                $countOfUseragents / 100,
                2,
            ) . ' ' . 100 - $this->calculatePercent(
                $resultFound2,
                $countOfUseragents / 100,
                2,
            ) . '"></circle>
                    ';
        }

        return $html . ('<circle class="donut-segment donut-segment-2" stroke-dasharray="' . $this->calculatePercent(
            $resultFound,
            $countOfUseragents / 100,
            2,
        ) . ' ' . 100 - $this->calculatePercent(
            $resultFound,
            $countOfUseragents / 100,
            2,
        ) . '"></circle>
                    <g class="donut-text">

                        <text y="50%" transform="translate(0, 2)">
                            <tspan x="50%" text-anchor="middle" class="donut-percent">' . $this->calculatePercent(
            $resultFound,
            $countOfUseragents / 100,
            2,
) . '%</tspan>
                        </text>
                    </g>
                </svg>
            </div>
        ');
    }

    /** @throws void */
    protected function calculatePercent(int $resultFound, float $onePercent, int $decimals = 4): string
    {
        return number_format(round($resultFound / $onePercent, 6), $decimals);
    }

    /** @throws void */
    protected function getUserAgentUrl(string $uaId): string
    {
        $url = '../../user-agent-detail/' . mb_substr($uaId, 0, 2) . '/' . mb_substr($uaId, 2, 2);

        return $url . ('/' . $uaId . '.html');
    }

    /** @throws void */
    protected function getHtmlCombined(string $body, string $script = ''): string
    {
        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />

    <title>' . htmlspecialchars($this->title) . '</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style type="text/css">
        .svg-item {
            width: 100px;
            height: 100px;
            font-size: 16px;
            margin: 0 auto;
        }

        .donut circle {
            cx: 20;
            cy: 20;
            r: 15.91549430918954;
            fill: transparent;
            stroke-width: 8;
        }

        .donut-ring {
            stroke: #EBEBEB;
        }

        .donut-segment {
            transform-origin: center;
            stroke-dashoffset: 25;
        }

        .donut-segment-2 {
            stroke: #D9E021;
        }

        .donut-segment-3 {
            stroke: #FF6200;
        }

        .donut-percent {
            font-size: 0.5em;
            line-height: 1;
            transform: translateY(0.5em);
            font-weight: bold;
        }

        .donut-text {
            font-family: Arial, Helvetica, sans-serif;
            fill: #d9e021;
        }
    </style>
</head>

<body>
<div>
    ' . $body . '

    <div class="section">
        <h1 class="header center orange-text">About this comparison</h1>

        <div class="row center">
            <h5 class="header light">
                The primary goal of this project is simple<br />

                I wanted to know which user agent parser is the most accurate in each part - device detection, bot detection and so on...<br />
                <br />
                The secondary goal is to provide a source for all user agent parsers to improve their detection based on this results.<br />
                <br />
                You can also improve this further, by suggesting ideas at <a href="https://github.com/ThaDafinser/UserAgentParserComparison">ThaDafinser/UserAgentParserComparison</a><br />
                <br />
                The comparison is based on the abstraction by <a href="https://github.com/ThaDafinser/UserAgentParserComparison">ThaDafinser/UserAgentParserComparison</a>
            </h5>
        </div>

    </div>

    <div class="card">
        <div class="card-content">
            Comparison created <i>' . date('Y-m-d H:i:s') . '</i> | by
            <a href="https://github.com/ThaDafinser">ThaDafinser</a>
        </div>
    </div>

</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/list.js/2.3.1/list.min.js"></script>

    <script>
    ' . $script . '
    </script>

</body>
</html>';
    }
}
