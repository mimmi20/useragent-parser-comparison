<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Compare;

use Exception;
use function Safe\array_flip;
use function Safe\file_get_contents;
use function Safe\json_decode;
use function Safe\ksort;
use function Safe\sort;
use function Safe\uasort;
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
    private $data = [];

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
                $pair = new ValuePairs();
                $pair->setExpected($expectedData[$compareKey][$compareSubKey]);
                $pair->setActual($actualData[$compareKey][$compareSubKey] ?? null);

                $this->data[$compareKey][$compareSubKey] = $pair;
            }
        }
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

                if (null === $expectedValue || null === $actualValue || $expectedValue === $actualValue) {
                    continue;
                }

                $failures[$compareKey][$compareSubKey] = ['expected' => $expectedValue, 'actual' => $actualValue];
            }
        }

        return $failures;
    }
}
