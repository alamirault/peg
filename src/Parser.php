<?php

namespace WouterJ\Peg;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class Parser
{
    /** @var Definition[] */
    private $definitions;

    /**
     * @param Definition[] $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->identifier()] = $definition;
        }
    }

    /** @return Result */
    public function parse($definitionId, $input, $offset = 0)
    {
        $rule = $this->definitions[$definitionId]->rule();

        try {
            return $this->parseOperator($rule, $input, $offset);
        } catch (\LogicException $e) {
            throw new \LogicException(sprintf('Invalid definition `%s`: %s', $definitionId, $e->getMessage()), 0, $e);
        }
    }

    /** @return Result */
    private function parseOperator($operator, $input, $offset)
    {
        $method = 'parse'.ucfirst($operator[0]);
        if (method_exists($this, $method)) {
            return $this->$method($operator, $input, $offset);
        }

        throw new \LogicException(sprintf('Undefined operator `%s`.', $operator[0]));
    }

    private function parseLiteral($operator, $input, $offset)
    {
        if (substr($input, $offset, strlen($operator[1])) === $operator[1]) {
            return Result::match($operator[1], $offset);
        }

        return Result::noMatch($offset);
    }

    private function parseIdentifier($operator, $input, $offset)
    {
        return $this->parse($operator[1], $input, $offset);
    }

    private function parseRepeat($operator, $input, $offset)
    {
        $_offset = $offset;
        $childOperator = $operator[1];
        $min = $operator[2] ?? 0;
        $max = $operator[3] ?? INF;
        $match = '';
        $inputLen = strlen($input);

        $i = 0;
        while (++$i <= $max) {
            $result = $this->parseOperator($childOperator, $input, $offset);

            $offset = $result->newOffset();
            $match .= $result->str();
            if (!$result->isMatch() || $offset > $inputLen) {
                if ($i <= $min) {
                    return Result::noMatch($_offset);
                }

                break;
            }
        }

        return Result::match($match, $_offset);
    }

    private function parseCharacterClass($operator, $input, $offset)
    {
        $regex = '{^['.$operator[1].']}';

        if (preg_match($regex, substr($input, $offset), $match)) {
            return Result::match($match[0], $offset);
        }

        return Result::noMatch($offset);
    }

    private function parseSequence($operator, $input, $offset)
    {
        $_offset = $offset;
        $sequence = $operator[1];
        $match = '';

        foreach ($sequence as $operator) {
            $result = $this->parseOperator($operator, $input, $offset);

            if (!$result->isMatch()) {
                return Result::noMatch($_offset);
            }

            $offset = $result->newOffset();
            $match .= $result->str();
        }

        return Result::match($match, $_offset);
    }

    private function parseChoice($operator, $input, $offset)
    {
        $operators = $operator[1];

        foreach ($operators as $operator) {
            $result = $this->parseOperator($operator, $input, $offset);

            if ($result->isMatch()) {
                return $result;
            }
        }

        return Result::noMatch($offset);
    }

    private function parseAny($operator, $input, $offset)
    {
        if ((strlen($input) - $offset) >= 1) {
            return Result::match(substr($input, $offset, 1), $offset);
        }

        return Result::noMatch($offset);
    }

    private function parseNot($operator, $input, $offset)
    {
        $result = $this->parseOperator($operator[1], $input, $offset);

        if ($result->isMatch()) {
            return Result::noMatch($offset);
        }

        return Result::match('', $offset);
    }

    private function parseAnd($operator, $input, $offset)
    {
        $result = $this->parseOperator($operator[1], $input, $offset);

        if ($result->isMatch()) {
            return Result::match('', $offset);
        }

        return Result::noMatch($offset);
    }
}