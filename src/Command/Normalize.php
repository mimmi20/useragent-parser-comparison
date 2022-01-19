<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command;

use Exception;
use FilesystemIterator;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function mkdir;
use function sprintf;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Normalize extends Command
{
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
        $this->setName('normalize')
            ->setDescription('Normalizes data from a test run for better analysis')
            ->addArgument('run', InputArgument::OPTIONAL, 'The name of the test run directory that you want to normalize')
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $thisRunName */
        $thisRunName = $input->getArgument('run');

        if (empty($thisRunName)) {
            // @todo Show user the available runs, perhaps limited to 10 or something, for now, throw an error
            $output->writeln('<error>run argument is required</error>');

            return self::FAILURE;
        }

        $statementSelectResultRun  = $this->pdo->prepare('SELECT `result`.* FROM `result` WHERE `result`.`run` = :run');
        $statementSelectResultRun->bindValue(':run', $thisRunName, \PDO::PARAM_STR);
        $statementSelectResultRun->execute();

        $statementSelectResultSource  = $this->pdo->prepare('SELECT `result`.* FROM `result` WHERE `result`.`run` = :run AND `result`.`userAgent_id` = :uaId');

        /** @var Helper\Normalize $normalizeHelper */
        $normalizeHelper = $this->getHelper('normalize');

        /** @var Helper\NormalizedResult $resultHelper */
        $resultHelper = $this->getHelper('normalized-result');

        $output->writeln(sprintf('<comment>Normalizing data from test run: %s</comment>', $thisRunName));

        while ($runRow = $statementSelectResultRun->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT)) {
            $statementSelectResultSource->bindValue(':run', '0', \PDO::PARAM_STR);
            $statementSelectResultSource->bindValue(':uaId', $runRow['userAgent_id'], \PDO::PARAM_STR);

            $statementSelectResultSource->execute();

            $sourceRow = $statementSelectResultSource->fetch(\PDO::FETCH_ASSOC);

            if (false === $sourceRow) {
                $output->writeln(sprintf('<error>Normalizing data from test run: %s - source for UA "%s" not found</error>', $thisRunName, $runRow['userAgent_id']));
                continue;
            }

            $sourceNormRow = $normalizeHelper->normalize($sourceRow);
            $resultHelper->storeResult($sourceRow['resId'], $sourceNormRow);

            $runNormRow = $normalizeHelper->normalize($runRow);
            $resultHelper->storeResult($runRow['resId'], $runNormRow);
        }

        $output->writeln('<info>done!</info>');

        return self::SUCCESS;
    }
}
