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

class ValuePairs
{
    private $expected;
    private $actual;

    /**
     * @param mixed $expected
     */
    public function setExpected($expected) : void
    {
        $this->expected = $expected;
    }

    /**
     * @param mixed $actual
     */
    public function setActual($actual) : void
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
