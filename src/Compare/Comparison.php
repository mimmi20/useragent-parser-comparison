<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Compare;

use Exception;
use function array_flip;
use function file_get_contents;
use function json_decode;
use function ksort;
use function sort;
use function uasort;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Comparison
{
    private array $data = [];

    private ?string $testname = null;

    private array $test = [];

    /**
     * Comparison constructor.
     *
     * @param array $expectedData
     * @param array $actualData
     */
    public function __construct(array $expectedData, array $actualData)
    {
        foreach ($expectedData as $compareKey => $compareValues) {
            $this->data[$compareKey] = [];

            foreach (array_keys($compareValues) as $compareSubKey) {
                $expected = $compareValues[$compareSubKey];
                $actual   = $actualData[$compareKey][$compareSubKey] ?? null;

                if (is_array($expected) || is_array($actual)) {
                    continue;
                }

                $pair = new ValuePairs();
                $pair->setExpected($expected);
                $pair->setActual($actual);

                $this->data[$compareKey][$compareSubKey] = $pair;
            }
        }
    }

    /**
     * @return string|null
     */
    public function getTestname(): ?string
    {
        return $this->testname;
    }

    /**
     * @param string|null $testname
     */
    public function setTestname(?string $testname): void
    {
        $this->testname = $testname;
    }

    /**
     * @return array
     */
    public function getTest(): array
    {
        return $this->test;
    }

    /**
     * @param array $test
     */
    public function setTest(array $test): void
    {
        $this->test = $test;
    }

    public function getComparison(string $parserName, int $countUseragent): array
    {
        $comparison = [];

        foreach ($this->data as $compareKey => $compareValues) {
            $comparison[$compareKey] = [];

            foreach ($compareValues as $compareSubKey => $pair) {
                /** @var \UserAgentParserComparison\Compare\ValuePairs $pair */
                $expectedValue = $pair->getExpected() ?? '[n/a]';
                $actualValue   = $pair->getActual() ?? '[n/a]';

                if (!isset($comparison[$compareKey][$compareSubKey][$expectedValue])) {
                    $comparison[$compareKey][$compareSubKey][$expectedValue] = [
                        'expected'  => [
                            'count'  => 0,
                            'agents' => [],
                        ],
                        $parserName => [],
                    ];
                }

                ++$comparison[$compareKey][$compareSubKey][$expectedValue]['expected']['count'];
                $comparison[$compareKey][$compareSubKey][$expectedValue]['expected']['agents'][] = $countUseragent;

                if (!isset($comparison[$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue])) {
                    $comparison[$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue] = [
                        'count'  => 0,
                        'agents' => [],
                    ];
                }

                ++$comparison[$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue]['count'];
                $comparison[$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue]['agents'][] = $countUseragent;

                if ($expectedValue !== $actualValue) {
                    if ($expectedValue !== '[n/a]' && $actualValue !== '[n/a]') {
                        $comparison[$compareKey][$compareSubKey][$expectedValue]['expected']['hasFailures'] = true;
                    }
                }
            }
        }

        return $comparison;
    }

    public function getFailures(): array
    {
        $failures = [];

        foreach ($this->data as $compareKey => $compareValues) {
            $failures[$compareKey] = [];

            foreach ($compareValues as $compareSubKey => $pair) {
                /** @var \UserAgentParserComparison\Compare\ValuePairs $pair */
                $expectedValue = $pair->getExpected();
                $actualValue   = $pair->getActual();

                if ($expectedValue === $actualValue) {
                    continue;
                }

                if ($expectedValue === null) {
                    continue;
                }

                $failures[$compareKey][$compareSubKey] = ['expected' => $expectedValue, 'actual' => $actualValue];
            }
        }

        return $failures;
    }
}
