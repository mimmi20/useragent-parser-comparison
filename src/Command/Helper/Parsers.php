<?php

/**
 * This file is part of the browser-detector-version package.
 *
 * Copyright (c) 2016-2024, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use DateTimeImmutable;
use FilesystemIterator;
use JsonException;
use SplFileInfo;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function assert;
use function count;
use function escapeshellarg;
use function file_exists;
use function file_get_contents;
use function is_string;
use function json_decode;
use function reset;
use function shell_exec;
use function sort;
use function str_replace;
use function trim;

use const JSON_THROW_ON_ERROR;
use const SORT_FLAG_CASE;
use const SORT_NATURAL;

final class Parsers extends Helper
{
    private string $parsersDir = __DIR__ . '/../../../parsers';

    /** @throws void */
    public function getName(): string
    {
        return 'parsers';
    }

    /**
     * @return array<string, array{name: string, path: string, metadata: array<string, mixed>, parse-ua: Closure}>
     *
     * @throws void
     */
    public function getParsers(InputInterface $input, OutputInterface $output, bool $multiple = true): array
    {
        $rows    = [];
        $names   = [];
        $parsers = [];

        foreach ($this->getAllParsers($output) as $parserPath => $parserConfig) {
            $parsers[$parserPath] = $parserConfig;

            $rows[] = [
                $parserConfig['metadata']['name'] ?? $parserPath,
                $parserConfig['metadata']['language'] ?? '',
                $parserConfig['metadata']['local'] ? 'yes' : 'no',
                $parserConfig['metadata']['api'] ? 'yes' : 'no',
            ];

            $names[$parserConfig['metadata']['name'] ?? $parserPath] = $parserPath;
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Language', 'Local', 'API']);
        $table->setRows($rows);
        $table->render();

        $questions = array_keys($names);
        sort($questions, SORT_FLAG_CASE | SORT_NATURAL);

        if ($multiple === true) {
            $questions[] = 'All Parsers';
        }

        if ($multiple === true) {
            $questionText = 'Choose which parsers to use, separate multiple with commas (press enter to use all)';
            $default      = count($questions) - 1;
        } else {
            $questionText = 'Select the parser to use';
            $default      = null;
        }

        $question = new ChoiceQuestion($questionText, $questions, $default);

        if ($multiple === true) {
            $question->setMultiselect(true);
        }

        $helper = $this->helperSet->get('question');
        assert($helper instanceof QuestionHelper);
        $answers = $helper->ask($input, $output, $question);

        $answers         = (array) $answers;
        $selectedParsers = [];

        foreach ($answers as $name) {
            if ($name === 'All Parsers') {
                $selectedParsers = $parsers;

                break;
            }

            $selectedParsers[$names[$name]] = $parsers[$names[$name]];
        }

        return $selectedParsers;
    }

    /**
     * @return iterable<string, array{name: string, path: string, metadata: array<string, mixed>, parse-ua: Closure}>
     *
     * @throws void
     */
    public function getAllParsers(OutputInterface $output): iterable
    {
        foreach (new FilesystemIterator($this->parsersDir) as $parserDir) {
            assert($parserDir instanceof SplFileInfo);
            $metadata = [];

            $pathName = $parserDir->getPathname();
            $pathName = str_replace('\\', '/', $pathName);

            if (file_exists($pathName . '/metadata.json')) {
                $contents = @file_get_contents($pathName . '/metadata.json');

                if ($contents === false) {
                    $output->writeln(
                        '<error>Could not read metadata file for parser in ' . $pathName . '</error>',
                    );

                    continue;
                }

                try {
                    $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $output->writeln(
                        '<error>An error occured while parsing metadata for parser ' . $pathName . '</error>',
                    );

                    continue;
                }
            }

            $isActive = $metadata['isActive'] ?? false;

            if (!$isActive) {
                $output->writeln('<error>parser ' . $pathName . ' is not active, skipping</error>');

                continue;
            }

            $language = $metadata['language'] ?? '';
//            $local    = $metadata['local'] ?? false;
//            $api      = $metadata['api'] ?? false;

            if (is_string($metadata['packageName'])) {
                switch ($language) {
                    case 'PHP':
                        $metadata['version']      = $this->getVersionPHP(
                            $pathName,
                            $metadata['packageName'],
                        );
                        $metadata['release-date'] = $this->getUpdateDatePHP(
                            $pathName,
                            $metadata['packageName'],
                        );

                        break;
                    case 'JavaScript':
                        $metadata['version']      = $this->getVersionJS(
                            $pathName,
                            $metadata['packageName'],
                        );
                        $metadata['release-date'] = $this->getUpdateDateJS(
                            $pathName,
                            $metadata['packageName'],
                        );

                        break;
                    default:
                        $output->writeln(
                            '<error>could not detect version and release date for parser ' . $pathName . '</error>',
                        );
                }
            }

            yield $parserDir->getFilename() => [
                'name' => $pathName,
                'path' => $parserDir->getFilename(),
                'metadata' => $metadata,
                'parse-ua' => static function (string $useragent) use ($pathName, $output, $language, $parserDir): array | null {
                    switch ($language) {
                        case 'PHP':
                            $command = match ($parserDir->getFilename()) {
                                'php-get-browser' => 'php -d browscap=' . $pathName . '/data/browscap.ini ' . $pathName . '/scripts/parse-ua.php --ua ' . escapeshellarg(
                                    $useragent,
                                ),
                                default => 'php ' . $pathName . '/scripts/parse-ua.php --ua ' . escapeshellarg(
                                    $useragent,
                                ),
                            };

                            break;
                        case 'JavaScript':
                            $command = 'node ' . $pathName . '/scripts/parse-ua.js --ua ' . escapeshellarg(
                                $useragent,
                            );

                            break;
                        default:
                            return null;
                    }

                    $result = shell_exec($command);

                    if ($result === null) {
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
        }
    }

    /**
     * Return the version of the provider
     *
     * @throws void
     */
    private function getVersionPHP(string $path, string $packageName): string | null
    {
        $content = @file_get_contents($path . '/vendor/composer/installed.json');

        if ($content === false) {
            return null;
        }

        $installed = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (!is_array($installed)) {
            return null;
        }

        $filtered = array_filter(
            $installed['packages'],
            static fn (array $value): bool => array_key_exists(
                'name',
                $value,
            ) && $packageName === $value['name'],
        );

        if ($filtered === []) {
            return null;
        }

        $filtered = reset($filtered);

        if ($filtered === [] || !array_key_exists('time', $filtered)) {
            return null;
        }

        return $filtered['version'];
    }

    /**
     * Get the last change date of the provider
     *
     * @throws void
     */
    private function getUpdateDatePHP(string $path, string $packageName): DateTimeImmutable | null
    {
        $content = @file_get_contents($path . '/vendor/composer/installed.json');

        if ($content === false) {
            return null;
        }

        $installed = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (!is_array($installed)) {
            return null;
        }

        $filtered = array_filter(
            $installed['packages'],
            static fn (array $value): bool => array_key_exists(
                'name',
                $value,
            ) && $packageName === $value['name'],
        );

        if ($filtered === []) {
            return null;
        }

        $filtered = reset($filtered);

        if ($filtered === [] || !array_key_exists('time', $filtered)) {
            return null;
        }

        return new DateTimeImmutable($filtered['time']);
    }

    /**
     * Return the version of the provider
     *
     * @throws void
     */
    private function getVersionJS(string $path, string $packageName): string | null
    {
        $content = @file_get_contents($path . '/npm-shrinkwrap.json');

        if ($content === false) {
            return null;
        }

        $installed = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (!is_array($installed)) {
            return null;
        }

        if (isset($installed['packages']['node_modules/' . $packageName]['version'])) {
            return $installed['packages']['node_modules/' . $packageName]['version'];
        }

        return $installed['dependencies'][$packageName]['version'] ?? null;
    }

    /**
     * Get the last change date of the provider
     *
     * @throws void
     */
    private function getUpdateDateJS(string $path, string $packageName): DateTimeImmutable | null
    {
        $content = @file_get_contents($path . '/npm-shrinkwrap.json');

        if ($content === false) {
            return null;
        }

        $installed = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (!is_array($installed)) {
            return null;
        }

        if (isset($installed['packages']['node_modules/' . $packageName]['time'])) {
            return new DateTimeImmutable(
                $installed['packages']['node_modules/' . $packageName]['time'],
            );
        }

        if (isset($installed['dependencies'][$packageName]['time'])) {
            return new DateTimeImmutable($installed['dependencies'][$packageName]['time']);
        }

        return null;
    }
}
