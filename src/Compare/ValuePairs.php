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

namespace UserAgentParserComparison\Compare;

final class ValuePairs
{
    private string | null $expected = null;
    private string | null $actual   = null;

    /** @throws void */
    public function setExpected(string | null $expected): void
    {
        $this->expected = $expected;
    }

    /** @throws void */
    public function setActual(string | null $actual): void
    {
        $this->actual = $actual;
    }

    /** @throws void */
    public function getExpected(): string | null
    {
        return $this->expected;
    }

    /** @throws void */
    public function getActual(): string | null
    {
        return $this->actual;
    }

    /** @throws void */
    public function hasValues(): bool
    {
        return $this->expected !== null && $this->actual !== null;
    }

    /** @throws void */
    public function hasDiff(): bool
    {
        return $this->expected !== $this->actual;
    }
}
