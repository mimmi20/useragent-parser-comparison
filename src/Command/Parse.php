<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use JsonException;
use PDO;
use Ramsey\Uuid\Uuid;
use SplFileObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UserAgentParserComparison\Command\Helper\Parsers;

use function addcslashes;
use function assert;
use function bin2hex;
use function date;
use function is_string;
use function json_encode;
use function max;
use function mb_strlen;
use function mb_substr;
use function sha1;
use function sprintf;
use function str_pad;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;

final class Parse extends Command
{
    /** @throws void */
    public function __construct(private readonly PDO $pdo)
    {
        parent::__construct();
    }

    /** @throws void */
    protected function configure(): void
    {
        $this->setName('parse')
            ->setDescription('Parses useragents in a file using the selected parser(s)')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file to parse')
            ->addArgument('run', InputArgument::OPTIONAL, 'Name of the run, for storing results')
            ->setHelp('Parses the useragent strings (one per line) from the passed in file');
    }

    /** @throws JsonException */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $proId    = null;
        $filename = $input->getArgument('file');
        assert(is_string($filename));

        $thisRunName = $input->getArgument('run');
        assert(is_string($thisRunName) || null === $thisRunName);

        if (empty($thisRunName)) {
            $thisRunName = date('YmdHis');
        }

        $output->writeln(sprintf('<comment>Parsing data for test run: %s</comment>', $thisRunName));

        $statementSelectUa       = $this->pdo->prepare('SELECT * FROM `userAgent` WHERE `uaHash` = :uaHash');
        $statementInsertUa       = $this->pdo->prepare('INSERT INTO `useragent` (`uaId`, `uaHash`, `uaString`, `uaAdditionalHeaders`) VALUES (:uaId, :uaHash, :uaString, :uaAdditionalHeaders)');
        $statementSelectProvider = $this->pdo->prepare('SELECT `proId` FROM `real-provider` WHERE `proName` = :proName');

        $parserHelper = $this->getHelper('parsers');
        assert($parserHelper instanceof Parsers);
        $parsers    = $parserHelper->getParsers($input, $output);
        $actualTest = 0;

        $file = new SplFileObject($filename);
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        $providers  = [];
        $nameLength = 0;

        foreach ($parsers as $parserPath => $parserConfig) {
            $proName = $parserConfig['metadata']['name'] ?? $parserPath;

            $statementSelectProvider->bindValue(':proName', $proName, PDO::PARAM_STR);
            $statementSelectProvider->execute();

            $proId = $statementSelectProvider->fetch(PDO::FETCH_COLUMN);

            if (!$proId) {
                $output->writeln(sprintf('<error>no provider found with name %s</error>', $proName));

                continue;
            }

            $nameLength = max($nameLength, mb_strlen((string) $proName));

            $providers[$proName] = [$parserPath, $parserConfig, $proId];
        }

        $resultHelper = $this->getHelper('result');
        assert($resultHelper instanceof Helper\Result);

        while (!$file->eof()) {
            $agentString = $file->fgets();
            ++$actualTest;

            if (empty($agentString)) {
                continue;
            }

            $agent       = addcslashes($agentString, PHP_EOL);
            $agentToShow = $agent;

            if (100 < mb_strlen($agentToShow)) {
                $agentToShow = mb_substr($agentToShow, 0, 96) . ' ...';
            }

            $basicTestMessage = sprintf(
                '<info>parsing</info> [%s] UA: <fg=yellow>%s</>',
                $actualTest,
                $agentToShow,
            );

            $output->write("\r" . $basicTestMessage);
            $messageLength = mb_strlen($basicTestMessage);

            $uaHash = bin2hex(sha1($agentString, true));

            /*
             * insert UA itself
             */
            $statementSelectUa->bindValue(':uaHash', $uaHash, PDO::PARAM_STR);

            $statementSelectUa->execute();

            $dbResultUa = $statementSelectUa->fetch(PDO::FETCH_ASSOC);

            if (false !== $dbResultUa) {
                // update!
                $uaId = $dbResultUa['uaId'];
            } else {
                $uaId = Uuid::uuid4()->toString();

                $additionalHeaders = null;

                $statementInsertUa->bindValue(':uaId', $uaId, PDO::PARAM_STR);
                $statementInsertUa->bindValue(':uaHash', $uaHash, PDO::PARAM_STR);
                $statementInsertUa->bindValue(':uaString', $agent, PDO::PARAM_STR);
                $statementInsertUa->bindValue(':uaAdditionalHeaders', json_encode($additionalHeaders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                $statementInsertUa->execute();
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
