<?php

declare(strict_types=1);

namespace Sphpera\Scoring\Rule;

abstract class AbstractPatternRule implements RuleInterface
{
    public function __construct(
        private readonly string $pattern,
        private readonly float $cost,
    ) {
    }

    final public function matches(string $subject): bool
    {
        $escapedPattern = preg_quote($this->pattern, '/');
        $regex = '/^' . str_replace('\\*', '.*', $escapedPattern) . '$/';

        return preg_match($regex, $subject) === 1;
    }

    final public function getCost(): float
    {
        return $this->cost;
    }
}
