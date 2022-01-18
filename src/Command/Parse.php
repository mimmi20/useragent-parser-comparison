<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use Ramsey\Uuid\Uuid;
use function fclose;
use function file_put_contents;
use function fopen;
use function fputcsv;
use function json_encode;
use function mkdir;
use function rewind;
use function stream_get_contents;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Parse extends Command
{
    /**
     * @var string
     */
    private $runDir = __DIR__ . '/../../data/test-runs';

    private \PDO $pdo;

    /**
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('parse')
            ->setDescription('Parses useragents in a file using the selected parser(s)')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file to parse')
            ->addArgument('run', InputArgument::OPTIONAL, 'Name of the run, for storing results')
            ->setHelp('Parses the useragent strings (one per line) from the passed in file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $filename */
        $filename  = $input->getArgument('file');

        /** @var string|null $thisRunName */
        $thisRunName     = $input->getArgument('run');

        $statementSelectUa       = $this->pdo->prepare('SELECT * FROM `userAgent` WHERE `uaHash` = :uaHash');
        $statementInsertUa       = $this->pdo->prepare('INSERT INTO `useragent` (`uaId`, `uaHash`, `uaString`, `uaAdditionalHeaders`) VALUES (:uaId, :uaHash, :uaString, :uaAdditionalHeaders)');
        $statementSelectProvider = $this->pdo->prepare('SELECT `proId` FROM `real-provider` WHERE `proName` = :proName');

        /** @var \UserAgentParserComparison\Command\Helper\Parsers $parserHelper */
        $parserHelper = $this->getHelper('parsers');
        $parsers      = $parserHelper->getParsers($input, $output);
        $actualTest   = 0;

        $file   = new \SplFileObject($filename);
        $file->setFlags(\SplFileObject::DROP_NEW_LINE);

        $providers  = [];
        $nameLength = 0;

        foreach ($parsers as $parserPath => $parserConfig) {
            $proName = $parserConfig['metadata']['name'] ?? $parserPath;

            $statementSelectProvider->bindValue(':proName', $proName, \PDO::PARAM_STR);
            $statementSelectProvider->execute();

            $proId = $statementSelectProvider->fetch(\PDO::FETCH_COLUMN);

            if (!$proId) {
                $output->writeln(sprintf('<error>no provider found with name %s</error>', $proName));
                continue;
            }

            $nameLength = max($nameLength, mb_strlen($proName));

            $providers[$proName] = [$parserPath, $parserConfig, $proId];
        }

        /** @var Helper\Result $resultHelper */
        $resultHelper = $this->getHelper('result');

        $inserted = 0;
        $updated  = 0;

        while (!$file->eof()) {
            $agentString = $file->fgets();
            ++$actualTest;

            if (empty($agentString)) {
                continue;
            }

            $agent = addcslashes($agentString, PHP_EOL);
            $agentToShow = $agent;

            if (mb_strlen($agentToShow) > 100) {
                $agentToShow = mb_substr($agentToShow, 0, 96) . ' ...';
            }

            $basicTestMessage = sprintf(
                '<info>parsing</info> [%s] UA: <fg=yellow>%s</>',
                $actualTest,
                $agentToShow
            );

            $output->write("\r" . $basicTestMessage);
            $messageLength = mb_strlen($basicTestMessage);

            $uaHash = bin2hex(sha1($agentString, true));

            /*
             * insert UA itself
             */
            $statementSelectUa->bindValue(':uaHash', $uaHash, \PDO::PARAM_STR);

            $statementSelectUa->execute();

            $dbResultUa = $statementSelectUa->fetch(\PDO::FETCH_ASSOC);

            if (false !== $dbResultUa) {
                // update!
                $uaId = $dbResultUa['uaId'];

                ++$updated;
            } else {
                $uaId = Uuid::uuid4()->toString();

                $additionalHeaders = null;

                $statementInsertUa->bindValue(':uaId', $uaId, \PDO::PARAM_STR);
                $statementInsertUa->bindValue(':uaHash', $uaHash, \PDO::PARAM_STR);
                $statementInsertUa->bindValue(':uaString', $agent, \PDO::PARAM_STR);
                $statementInsertUa->bindValue(':uaAdditionalHeaders', json_encode($additionalHeaders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                $statementInsertUa->execute();

                ++$inserted;
            }

            /*
             * Result
             */
            $resultHelper->storeResult('0', $proId, $uaId, []);

            foreach ($providers as $parserName => $provider) {

                [, $parserConfig, $proId] = $provider;

                $testMessage = $basicTestMessage . ' against the <fg=green;options=bold,underscore>' . $parserName . '</> parser...';

                if (mb_strlen($testMessage) > $messageLength) {
                    $messageLength = mb_strlen($testMessage);
                }

                $output->write("\r" . str_pad($testMessage, $messageLength));

                $singleResult = $parserConfig['parse-ua']($agentString);

                if (empty($singleResult)) {
                    $testMessage = $basicTestMessage . ' <error>The <fg=red;options=bold,underscore>' . $parserName . '</> parser did not return any data, there may have been an error</error>';

                    if (mb_strlen($testMessage) > $messageLength) {
                        $messageLength = mb_strlen($testMessage);
                    }

                    $output->writeln("\r" . str_pad($testMessage, $messageLength));

                    continue;
                }

                $resultHelper->storeResult($thisRunName, $proId, $uaId, $singleResult);
            }

            $testMessage = $basicTestMessage . ' <info>done!</info>';

            if (mb_strlen($testMessage) > $messageLength) {
                $messageLength = mb_strlen($testMessage);
            }

            $output->writeln("\r" . str_pad($testMessage, $messageLength));
        }

        $output->writeln('<info>done!</info>');

        return self::SUCCESS;
    }
}
