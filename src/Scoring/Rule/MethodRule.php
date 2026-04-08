<?php

declare(strict_types=1);

namespace Sphpera\Scoring\Rule;

final class MethodRule
{
    public function __construct(
        private readonly AbstractPatternRule $classRule,
        private readonly AbstractPatternRule $methodRule,
    ) {
    }

    public function matches(string $className, string $methodName): bool
    {
        return $this->classRule->matches($className) && $this->methodRule->matches($methodName);
    }

    public function getCost(): float
    {
        return $this->methodRule->getCost();
    }
}
