<?php
namespace UserAgentParserComparison\Html;

abstract class AbstractHtml
{

    protected ?string $title = null;

    protected \PDO $pdo;

    private ?int $userAgentCount = null;

    public function __construct(\PDO $pdo, ?string $title = null)
    {
        $this->pdo = $pdo;
        $this->title = $title;
    }

    protected function getUserAgentCount(): int
    {
        if ($this->userAgentCount === null) {
            $statementCountAllResults = $this->pdo->prepare('SELECT COUNT(*) AS `count` FROM `userAgent`');
            $statementCountAllResults->execute();
            
            $this->userAgentCount = $statementCountAllResults->fetch(\PDO::FETCH_COLUMN);
        }
        
        return $this->userAgentCount;
    }

    protected function getPercentCircle(int $resultFound, ?int $uniqueFound = null): string
    {
        $onePercent = $this->getUserAgentCount() / 100;
        
        $html = '
            <div class="c100 p' . $this->calculatePercent($resultFound, $onePercent, 0) . ' small green-circle">
                <span>' . $this->calculatePercent($resultFound, $onePercent) . '%</span>
                <div class="slice">
                    <div class="bar"></div>
                    <div class="fill"></div>
                </div>
            </div>
        ';
        
        $html .= 'Tot.' . $resultFound;
        
        if ($uniqueFound !== null) {
            $html .= '<br />' . 'Unq.' . $uniqueFound;
        }
        
        return $html;
    }

    protected function calculatePercent(int $resultFound, float $onePercent, int $decimals = 4): string
    {
        return number_format(round($resultFound / $onePercent, 6), $decimals);
    }

    protected function getUserAgentUrl(string $uaId): string
    {
        $url = '../../user-agent-detail/' . substr($uaId, 0, 2) . '/' . substr($uaId, 2, 2);
        $url .= '/' . $uaId . '.html';
        
        return $url;
    }

    protected function getHtmlCombined(string $body, string $script = ''): string
    {
        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
            
    <title>' . htmlspecialchars($this->title) . '</title>
        
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.97.3/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="../circle.css" rel="stylesheet">
</head>
        
<body>
<div class="container">
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
                
    <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.97.3/js/materialize.min.js"></script>
    <script src="http://cdnjs.cloudflare.com/ajax/libs/list.js/1.2.0/list.min.js"></script>
        
    <script>
    ' . $script . '
    </script>
        
</body>
</html>';
    }

    abstract public function getHtml(): string;
}
