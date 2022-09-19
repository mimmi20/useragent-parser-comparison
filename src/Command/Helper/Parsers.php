<?php
/**
 * This file is part of the diablomedia/useragent-parser-comparison package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use FilesystemIterator;
use JsonException;
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
use function file_get_contents;
use function implode;
use function json_decode;
use function ksort;
use function shell_exec;
use function sort;
use function str_replace;
use function trim;

use const JSON_THROW_ON_ERROR;

final class Parsers extends Helper
{
    private string $parsersDir = __DIR__ . '/../../../parsers';

    public function getName(): string
    {
        return 'parsers';
    }

    /** @return mixed[] */
    public function getParsers(InputInterface $input, OutputInterface $output, bool $multiple = true): array
    {
        $rows    = [];
        $names   = [];
        $parsers = [];

        foreach (new FilesystemIterator($this->parsersDir) as $parserDir) {
            assert($parserDir instanceof SplFileInfo);
            $metadata = [];

            $pathName = $parserDir->getPathname();
            $pathName = str_replace('\\', '/', $pathName);

            if (file_exists($parserDir->getPathname() . '/metadata.json')) {
                try {
                    $contents = file_get_contents($parserDir->getPathname() . '/metadata.json');

                    try {
                        $metadata = json_decode($contents, true, JSON_THROW_ON_ERROR);
                    } catch (Throwable) {
                        $output->writeln('<error>An error occured while parsing metadata for parser ' . $parserDir->getPathname() . '</error>');
                    }
                } catch (Throwable) {
                    $output->writeln('<error>Could not read metadata file for parser in ' . $parserDir->getPathname() . '</error>');
                }
            }

            $isActive = $metadata['isActive'] ?? false;

            if (!$isActive) {
                $output->writeln('<error>parser ' . $pathName . ' is not active, skipping</error>');

                continue;
            }

            $language = $metadata['language'] ?? '';
            $local    = $metadata['local'] ?? false;
            $api      = $metadata['api'] ?? false;

            $parsers[$parserDir->getFilename()] = [
                'path' => $parserDir->getPathname(),
                'metadata' => $metadata,
                'parse' => static function (string $file, bool $benchmark = false) use ($parserDir, $output): array | null {
                    $args = [
                        escapeshellarg($file),
                    ];
                    if (true === $benchmark) {
                        $args[] = '--benchmark';
                    }

                    $result = shell_exec('sh ' . $parserDir->getPathname() . '/parse.sh ' . implode(' ', $args));

                    if (null !== $result) {
                        $result = trim($result);

                        try {
                            $result = json_decode($result, true, JSON_THROW_ON_ERROR);
                        } catch (Throwable $e) {
                            $output->writeln('<error>' . $result . $e . '</error>');

                            return null;
                        }
                    }

                    return $result;
                },
                'parse-ua' => static function (string $useragent) use ($pathName, $output, $language, $parserDir): array | null {
                    switch ($language) {
                        case 'PHP':
                            switch ($parserDir->getFilename()) {
                                case 'php-get-browser':
                                    $command = 'php -d browscap=' . $pathName . '/data/browscap.ini ' . $pathName . '/scripts/parse-ua.php --ua ' . escapeshellarg($useragent);

                                    break;
                                default:
                                    $command = 'php ' . $pathName . '/scripts/parse-ua.php --ua ' . escapeshellarg($useragent);

                                    break;
                            }

                            break;
                        case 'JavaScript':
                            $command = 'node ' . $pathName . '/scripts/parse-ua.js --ua ' . escapeshellarg($useragent);

                            break;
                        default:
                            return null;
                    }

                    $result = shell_exec($command);

                    if (null === $result) {
                        return null;
                    }

                    $result = trim($result);

                    try {
                        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        $output->writeln('<error>' . $result . '</error>');
                        $output->writeln('<error>' . $result . $e . '</error>');
                    }

                    return null;
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
            $default,
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
