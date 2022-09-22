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

final class ValuePairs
{
    private $expected;
    private $actual;

    /** @param mixed $expected */
    public function setExpected($expected): void
    {
        $this->expected = $expected;
    }

    /** @param mixed $actual */
    public function setActual($actual): void
    {
        $this->actual = $actual;
    }

    /** @return mixed */
    public function getExpected()
    {
        return $this->expected;
    }

    /** @return mixed */
    public function getActual()
    {
        return $this->actual;
    }

    public function hasValues(): bool
    {
        return null !== $this->expected && null !== $this->actual;
    }

    public function hasDiff(): bool
    {
        return $this->expected !== $this->actual;
    }
}
