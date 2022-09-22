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

namespace UserAgentParserComparison\Compare;

use function array_keys;
use function is_int;
use function is_string;

final class Comparison
{
    private array $data = [];

    private string | null $testname = null;

    private array $test = [];

    public function __construct(array $expectedData, array $actualData)
    {
        foreach ($expectedData as $compareKey => $compareValues) {
            $this->data[$compareKey] = [];

            foreach (array_keys($compareValues) as $compareSubKey) {
                $expected = $compareValues[$compareSubKey];
                $actual   = $actualData[$compareKey][$compareSubKey] ?? null;

                if ((!is_string($expected) && !is_int($expected)) || (!is_string($actual) && !is_int($actual))) {
                    continue;
                }

                $pair = new ValuePairs();
                $pair->setExpected($expected);
                $pair->setActual($actual);

                $this->data[$compareKey][$compareSubKey] = $pair;
            }
        }
    }

    public function getTestname(): string | null
    {
        return $this->testname;
    }

    public function setTestname(string | null $testname): void
    {
        $this->testname = $testname;
    }

    /** @return array */
    public function getTest(): array
    {
        return $this->test;
    }

    /** @param array $test */
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
                /** @var ValuePairs $pair */
                $expectedValue = $pair->getExpected() ?? '[n/a]';
                $actualValue   = $pair->getActual() ?? '[n/a]';

                if (!isset($comparison[$compareKey][$compareSubKey][$expectedValue])) {
                    $comparison[$compareKey][$compareSubKey][$expectedValue] = [
                        'expected' => [
                            'count' => 0,
                            'agents' => [],
                        ],
                        $parserName => [],
                    ];
                }

                ++$comparison[$compareKey][$compareSubKey][$expectedValue]['expected']['count'];
                $comparison[$compareKey][$compareSubKey][$expectedValue]['expected']['agents'][] = $countUseragent;

                if (!isset($comparison[$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue])) {
                    $comparison[$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue] = [
                        'count' => 0,
                        'agents' => [],
                    ];
                }

                ++$comparison[$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue]['count'];
                $comparison[$compareKey][$compareSubKey][$expectedValue][$parserName][$actualValue]['agents'][] = $countUseragent;

                if ($expectedValue === $actualValue) {
                    continue;
                }

                if ('[n/a]' === $expectedValue || '[n/a]' === $actualValue) {
                    continue;
                }

                $comparison[$compareKey][$compareSubKey][$expectedValue]['expected']['hasFailures'] = true;
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
                /** @var ValuePairs $pair */
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
