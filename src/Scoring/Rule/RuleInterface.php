<?php

declare(strict_types=1);

namespace Sphpera\Scoring\Rule;

interface RuleInterface
{
    public function matches(string $subject): bool;

    public function getCost(): float;
}
