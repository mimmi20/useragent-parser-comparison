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

namespace UserAgentParserComparison\Command;

use Override;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function is_string;
use function sprintf;

final class Normalize extends Command
{
    /** @throws void */
    public function __construct(private readonly PDO $pdo)
    {
        parent::__construct();
    }

    /** @throws void */
    #[Override]
    protected function configure(): void
    {
        $this->setName('normalize')
            ->setDescription('Normalizes data from a test run for better analysis')
            ->addArgument(
                'run',
                InputArgument::OPTIONAL,
                'The name of the test run directory that you want to normalize',
            )
            ->setHelp('');
    }

    /** @throws void */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $thisRunName = $input->getArgument('run');
        assert(is_string($thisRunName) || $thisRunName === null);

        if (empty($thisRunName)) {
            // @todo Show user the available runs, perhaps limited to 10 or something, for now, throw an error
            $output->writeln('<error>run argument is required</error>');

            return self::FAILURE;
        }

        $statementSelectResultRun = $this->pdo->prepare(
            'SELECT `result`.* FROM `result` WHERE `result`.`run` = :run',
        );
        $statementSelectResultRun->bindValue(':run', $thisRunName, PDO::PARAM_STR);
        $statementSelectResultRun->execute();

        $statementSelectResultSource = $this->pdo->prepare(
            'SELECT `result`.* FROM `result` WHERE `result`.`run` = :run AND `result`.`userAgent_id` = :uaId',
        );

        $normalizeHelper = $this->getHelper('normalize');
        assert($normalizeHelper instanceof Helper\Normalize);

        $resultHelper = $this->getHelper('normalized-result');
        assert($resultHelper instanceof Helper\NormalizedResult);

        $output->writeln(
            sprintf('<comment>Normalizing data from test run: %s</comment>', $thisRunName),
        );

        while ($runRow = $statementSelectResultRun->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
            $statementSelectResultSource->bindValue(':run', '0', PDO::PARAM_STR);
            $statementSelectResultSource->bindValue(':uaId', $runRow['userAgent_id'], PDO::PARAM_STR);

            $statementSelectResultSource->execute();

            $sourceRow = $statementSelectResultSource->fetch(PDO::FETCH_ASSOC);

            if ($sourceRow === false) {
                $output->writeln(
                    sprintf(
                        '<error>Normalizing data from test run: %s - source for UA "%s" not found</error>',
                        $thisRunName,
                        $runRow['userAgent_id'],
                    ),
                );

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
