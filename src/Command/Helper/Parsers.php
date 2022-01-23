<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use FilesystemIterator;
use SplFileInfo;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Throwable;

use function array_keys;
use function assert;
use function count;
use function escapeshellarg;
use function file_exists;
use function implode;
use function file_get_contents;
use function json_decode;
use function ksort;
use function sort;
use function shell_exec;
use function trim;

class Parsers extends Helper
{
    private string $parsersDir = __DIR__ . '/../../../parsers';

    public function getName(): string
    {
        return 'parsers';
    }

    public function getParsers(InputInterface $input, OutputInterface $output, bool $multiple = true): array
    {
        $rows    = [];
        $names   = [];
        $parsers = [];

        foreach (new FilesystemIterator($this->parsersDir) as $parserDir) {
            assert($parserDir instanceof SplFileInfo);
            $metadata = [];

            if (file_exists($parserDir->getPathname() . '/metadata.json')) {
                try {
                    $contents = file_get_contents($parserDir->getPathname() . '/metadata.json');

                    try {
                        $metadata = json_decode($contents, true);
                    } catch (Throwable $e) {
                        $output->writeln('<error>An error occured while parsing metadata for parser ' . $parserDir->getPathname() . '</error>');
                    }
                } catch (Throwable $e) {
                    $output->writeln('<error>Could not read metadata file for parser in ' . $parserDir->getPathname() . '</error>');
                }
            }

            $parsers[$parserDir->getFilename()] = [
                'path'     => $parserDir->getPathname(),
                'metadata' => $metadata,
                'parse'    => static function (string $file, bool $benchmark = false) use ($parserDir, $output): ?array {
                    $args = [
                        escapeshellarg($file),
                    ];
                    if (true === $benchmark) {
                        $args[] = '--benchmark';
                    }

                    $result = shell_exec($parserDir->getPathname() . '/parse.sh ' . implode(' ', $args));

                    if (null !== $result) {
                        $result = trim($result);

                        try {
                            $result = json_decode($result, true);
                        } catch (Throwable $e) {
                            $output->writeln('<error>' . $result . $e . '</error>');

                            return null;
                        }
                    }

                    return $result;
                },
            ];

            $rows[] = [
                $metadata['name'] ?? $parserDir->getFilename(),
                $metadata['language'] ?? '',
                $metadata['data_source'] ?? '',
            ];

            $names[$metadata['name'] ?? $parserDir->getFilename()] = $parserDir->getFilename();
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Language', 'Data Source']);
        $table->setRows($rows);
        $table->render();

        $questions = array_keys($names);
        sort($questions);

        if (true === $multiple) {
            $questions[] = 'All Parsers';
        }

        if (true === $multiple) {
            $questionText = 'Choose which parsers to use, separate multiple with commas (press enter to use all)';
            $default      = count($questions) - 1;
        } else {
            $questionText = 'Select the parser to use';
            $default      = null;
        }

        $question = new ChoiceQuestion(
            $questionText,
            $questions,
            $default
        );

        if (true === $multiple) {
            $question->setMultiselect(true);
        }

        $helper  = $this->helperSet->get('question');
        $answers = $helper->ask($input, $output, $question);

        $answers         = (array) $answers;
        $selectedParsers = [];

        foreach ($answers as $name) {
            if ('All Parsers' === $name) {
                $selectedParsers = $parsers;

                break;
            }

            $selectedParsers[$names[$name]] = $parsers[$names[$name]];
        }

        ksort($selectedParsers);

        return $selectedParsers;
    }
}
