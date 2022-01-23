<?php

declare(strict_types = 1);

namespace UserAgentParserComparison\Compare;

class ValuePairs
{
    private $expected;
    private $actual;

    /**
     * @param mixed $expected
     */
    public function setExpected($expected): void
    {
        $this->expected = $expected;
    }

    /**
     * @param mixed $actual
     */
    public function setActual($actual): void
    {
        $this->actual = $actual;
    }

    /**
     * @return mixed
     */
    public function getExpected()
    {
        return $this->expected;
    }

    /**
     * @return mixed
     */
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
