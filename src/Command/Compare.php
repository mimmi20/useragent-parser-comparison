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

namespace UserAgentParserComparison\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function date;

final class Compare extends Command
{
    protected function configure(): void
    {
        $this->setName('compare')
            ->setDescription('Runs tests, normalizes the results then analyzes the results')
            ->addOption('run', 'r', InputOption::VALUE_OPTIONAL, 'The name of the test run, if omitted will be generated from date')
            ->addOption('import', null, InputOption::VALUE_NONE, 'Whether to import providers and useragents')
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to a file to use as the source of useragents rather than test suites')
            ->setHelp('This command is a "meta" command that will execute the Test, Normalize and Analyze commands in order');
    }

    /** @throws Exception */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');

        // Prepare our test directory to store the data from this run
        $name = $input->getOption('run');

        $application = $this->getApplication();

        if (null === $application) {
            throw new Exception('Could not retrieve Symfony Application, aborting');
        }

        if (empty($name)) {
            $name = date('YmdHis');
        }

        $doImport = $input->getOption('import');

        if ($doImport && !$file) {
            $command   = $application->find('init-provider');
            $arguments = ['command' => 'init-provider'];

            $initProviderInput = new ArrayInput($arguments);
            $returnCode        = $command->run($initProviderInput, $output);

            if (0 < $returnCode) {
                $output->writeln('<error>There was an error executing the "init-provider" command, cannot continue.</error>');

                return $returnCode;
            }

            $command   = $application->find('init-useragents');
            $arguments = ['command' => 'init-useragents'];

            $initProviderInput = new ArrayInput($arguments);
            $returnCode        = $command->run($initProviderInput, $output);

            if (0 < $returnCode) {
                $output->writeln('<error>There was an error executing the "init-useragents" command, cannot continue.</error>');

                return $returnCode;
            }
        }

        if ($file) {
            $command   = $application->find('parse');
            $arguments = [
                'command' => 'parse',
                'file' => $file,
                'run' => $name,
            ];

            $parseInput = new ArrayInput($arguments);
            $returnCode = $command->run($parseInput, $output);

            if (0 < $returnCode) {
                $output->writeln('<error>There was an error executing the "parse" command, cannot continue.</error>');

                return $returnCode;
            }
        } else {
            $command   = $application->find('test');
            $arguments = [
                'command' => 'test',
                'run' => $name,
            ];

            $testInput  = new ArrayInput($arguments);
            $returnCode = $command->run($testInput, $output);

            if (0 < $returnCode) {
                $output->writeln('<error>There was an error executing the "test" command, cannot continue.</error>');

                return $returnCode;
            }
        }

        $command   = $application->find('normalize');
        $arguments = [
            'command' => 'normalize',
            'run' => $name,
        ];

        $normalizeInput = new ArrayInput($arguments);
        $returnCode     = $command->run($normalizeInput, $output);

        if (0 < $returnCode) {
            $output->writeln('<error>There was an error executing the "normalize" command, cannot continue.</error>');

            return $returnCode;
        }

        $command   = $application->find('generate-reports');
        $arguments = [
            'command' => 'generate-reports',
            'run' => $name,
        ];

        $generateInput = new ArrayInput($arguments);
        $returnCode    = $command->run($generateInput, $output);

        if (0 < $returnCode) {
            $output->writeln('<error>There was an error executing the "generate-reports" command, cannot continue.</error>');

            return $returnCode;
        }

        return self::SUCCESS;

        $command   = $application->find('analyze');
        $arguments = [
            'command' => 'analyze',
            'run' => $name,
        ];

        $analyzeInput = new ArrayInput($arguments);
        $returnCode   = $command->run($analyzeInput, $output);

        if (0 < $returnCode) {
            $output->writeln('<error>There was an error executing the "analyze" command, cannot continue.</error>');

            return $returnCode;
        }

        return self::SUCCESS;
    }
}
