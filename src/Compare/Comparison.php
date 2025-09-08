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

namespace UserAgentParserComparison\Compare;

use function array_keys;
use function is_array;
use function is_bool;
use function is_string;
use function mb_strtolower;

final class Comparison
{
    /** @var array<int|string, array<int|string, ValuePairs>> */
    private array $data             = [];
    private string | null $testname = null;

    /** @var array<mixed> */
    private array $test = [];

    /**
     * @param array<int|string, array<int|string, array<mixed>>> $expectedData
     * @param array<int|string, array<int|string, array<mixed>>> $actualData
     *
     * @throws void
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

                if (is_bool($expected)) {
                    $expected = $expected ? 'true' : 'false';
                }

                if (!is_string($expected) && $expected !== null) {
                    $expected = (string) $expected;
                }

                if (is_bool($actual)) {
                    $actual = $actual ? 'true' : 'false';
                }

                if (!is_string($actual) && $actual !== null) {
                    $actual = (string) $actual;
                }

                $pair = new ValuePairs();
                $pair->setExpected($expected === null ? null : mb_strtolower($expected));
                $pair->setActual($actual === null ? null : mb_strtolower($actual));

                $this->data[$compareKey][$compareSubKey] = $pair;
            }
        }
    }

    /** @throws void */
    public function getTestname(): string | null
    {
        return $this->testname;
    }

    /** @throws void */
    public function setTestname(string | null $testname): void
    {
        $this->testname = $testname;
    }

    /**
     * @return array<mixed>
     *
     * @throws void
     */
    public function getTest(): array
    {
        return $this->test;
    }

    /**
     * @param array<mixed> $test
     *
     * @throws void
     */
    public function setTest(array $test): void
    {
        $this->test = $test;
    }

    /**
     * @return array<mixed>
     *
     * @throws void
     */
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

                if ($expectedValue !== '[n/a]' && $actualValue !== '[n/a]') {
                    $comparison[$compareKey][$compareSubKey][$expectedValue]['expected']['hasFailures'] = true;
                }
            }
        }

        return $comparison;
    }

    /**
     * @return array<int|string, array<int|string, array{expected: mixed, actual: mixed, diff: bool, unset: bool}>>
     *
     * @throws void
     */
    public function getFailures(): array
    {
        $failures = [];

        foreach ($this->data as $compareKey => $compareValues) {
            $failures[$compareKey] = [];

            foreach ($compareValues as $compareSubKey => $pair) {
                /** @var ValuePairs $pair */
                $expectedValue = $pair->getExpected();
                $actualValue   = $pair->getActual();
                $diff          = true;
                $unset         = false;

                if ($expectedValue === $actualValue) {
                    $diff = false;
                }

                if ($expectedValue === null) {
                    $diff  = false;
                    $unset = true;
                }

                $failures[$compareKey][$compareSubKey] = ['expected' => $expectedValue, 'actual' => $actualValue, 'diff' => $diff, 'unset' => $unset];
            }
        }

        return $failures;
    }
}
