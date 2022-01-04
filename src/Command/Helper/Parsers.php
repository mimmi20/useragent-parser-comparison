<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Command\Helper;

use Exception;
use FilesystemIterator;
use function file_get_contents;
use function json_decode;
use function ksort;
use function sort;
use SplFileInfo;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Parsers extends Helper
{
    /**
     * @var string
     */
    private $parsersDir = __DIR__ . '/../../../parsers';

    public function getName(): string
    {
        return 'parsers';
    }

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

        $question = new ChoiceQuestion(
            $questionText,
            $questions,
            $default
        );

        if ($multiple === true) {
            $question->setMultiselect(true);
        }

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper  = $this->helperSet->get('question');
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

    public function getAllParsers(OutputInterface $output): iterable
    {
        /** @var SplFileInfo $parserDir */
        foreach (new FilesystemIterator($this->parsersDir) as $parserDir) {
            $metadata = [];

            $pathName = $parserDir->getPathname();
            $pathName = str_replace('\\', '/', $pathName);

            if (file_exists($pathName . '/metadata.json')) {
                $contents = @file_get_contents($pathName . '/metadata.json');

                if (false === $contents) {
                    $output->writeln('<error>Could not read metadata file for parser in ' . $pathName . '</error>');
                    continue;
                }

                try {
                    $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $output->writeln('<error>An error occured while parsing metadata for parser ' . $pathName . '</error>');
                    continue;
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

            if (is_string($metadata['packageName'])) {
                switch ($language) {
                    case 'PHP':
                        $metadata['version'] = $this->getVersionPHP($pathName, $metadata['packageName']);
                        $metadata['release-date'] = $this->getUpdateDatePHP($pathName, $metadata['packageName']);
                        break;
                    case 'JavaScript':
                        $metadata['version'] = $this->getVersionJS($pathName, $metadata['packageName']);
                        $metadata['release-date'] = $this->getUpdateDateJS($pathName, $metadata['packageName']);
                        break;
                    default:
                        $output->writeln('<error>could not detect version and release date for parser ' . $pathName . '</error>');
                }
            }

            yield $parserDir->getFilename() => [
                'name'     => $pathName,
                'path'     => $parserDir->getFilename(),
                'metadata' => $metadata,
                'parse-ua' => static function (string $useragent) use ($pathName, $output, $language, $parserDir): ?array {
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
                    } catch (\JsonException $e) {
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
     * @return string|null
     */
    private function getVersionPHP(string $path, string $packageName): ?string
    {
        $installed = json_decode(file_get_contents($path . '/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);

        $filtered = array_filter(
            $installed['packages'],
            function (array $value) use ($packageName): bool {
                return array_key_exists('name', $value) && $packageName === $value['name'];
            }
        );

        if ([] === $filtered) {
            return null;
        }

        $filtered = reset($filtered);

        if ([] === $filtered || !array_key_exists('time', $filtered)) {
            return null;
        }

        return $filtered['version'];
    }

    /**
     * Get the last change date of the provider
     *
     * @return \DateTimeImmutable|null
     */
    private function getUpdateDatePHP(string $path, string $packageName): ?\DateTimeImmutable
    {
        $installed = json_decode(file_get_contents($path . '/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);

        $filtered = array_filter(
            $installed['packages'],
            function (array $value) use ($packageName): bool {
                return array_key_exists('name', $value) && $packageName === $value['name'];
            }
        );

        if ([] === $filtered) {
            return null;
        }

        $filtered = reset($filtered);

        if ([] === $filtered || !array_key_exists('time', $filtered)) {
            return null;
        }

        return new \DateTimeImmutable($filtered['time']);
    }

    /**
     * Return the version of the provider
     *
     * @return string|null
     */
    private function getVersionJS(string $path, string $packageName): ?string
    {
        $installed = json_decode(file_get_contents($path . '/npm-shrinkwrap.json'), true, 512, JSON_THROW_ON_ERROR);

        if (isset($installed['packages']['node_modules/' . $packageName]['version'])) {
            return $installed['packages']['node_modules/' . $packageName]['version'];
        }

        if (isset($installed['dependencies'][$packageName]['version'])) {
            return $installed['dependencies'][$packageName]['version'];
        }

        return null;
    }

    /**
     * Get the last change date of the provider
     *
     * @return \DateTimeImmutable|null
     */
    private function getUpdateDateJS(string $path, string $packageName): ?\DateTimeImmutable
    {
        $installed = json_decode(file_get_contents($path . '/npm-shrinkwrap.json'), true, 512, JSON_THROW_ON_ERROR);

        if (isset($installed['packages']['node_modules/' . $packageName]['time'])) {
            return new \DateTimeImmutable($installed['packages']['node_modules/' . $packageName]['time']);
        }

        if (isset($installed['dependencies'][$packageName]['time'])) {
            return new \DateTimeImmutable($installed['dependencies'][$packageName]['time']);
        }

        return null;
    }
}
