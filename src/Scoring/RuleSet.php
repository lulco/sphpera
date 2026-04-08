<?php

declare(strict_types=1);

namespace Sphpera\Scoring;

use Sphpera\Config\Config;
use Sphpera\Scoring\Rule\FunctionRule;
use Sphpera\Scoring\Rule\MethodRule;
use Sphpera\Scoring\Rule\RuleInterface;

final class RuleSet
{
    /** @var list<RuleInterface> */
    private array $functionRules = [];

    /** @var list<MethodRule> */
    private array $methodRules = [];

    private float $defaultCost;
    /** @var array<string, float> */
    private array $functionCache = [];
    /** @var array<string, float> */
    private array $methodCache = [];

    public function __construct(Config $config)
    {
        $this->defaultCost = $config->getDefault();

        foreach ($config->getFunctions() as $pattern => $cost) {
            $this->functionRules[] = new FunctionRule($pattern, $cost);
        }

        foreach ($config->getMethods() as $classPattern => $methods) {
            foreach ($methods as $methodPattern => $cost) {
                $this->methodRules[] = new MethodRule(
                    new FunctionRule($classPattern, $cost),
                    new FunctionRule($methodPattern, $cost),
                );
            }
        }
    }

    public function resolveFunctionCost(string $functionName): float
    {
        if (isset($this->functionCache[$functionName])) {
            return $this->functionCache[$functionName];
        }

        foreach ($this->functionRules as $rule) {
            if ($rule->matches($functionName)) {
                $resolved = $rule->getCost();
                $this->functionCache[$functionName] = $resolved;
                return $resolved;
            }
        }

        $shortName = $functionName;
        if (str_contains($functionName, '\\')) {
            $shortName = substr($functionName, (int) strrpos($functionName, '\\') + 1);
            foreach ($this->functionRules as $rule) {
                if ($rule->matches($shortName)) {
                    $resolved = $rule->getCost();
                    $this->functionCache[$functionName] = $resolved;
                    return $resolved;
                }
            }
        }

        $this->functionCache[$functionName] = $this->defaultCost;
        return $this->defaultCost;
    }

    public function resolveMethodCost(string $className, string $methodName): float
    {
        $key = $className . '|' . $methodName;
        if (isset($this->methodCache[$key])) {
            return $this->methodCache[$key];
        }

        foreach ($this->methodRules as $rule) {
            if ($rule->matches($className, $methodName)) {
                $resolved = $rule->getCost();
                $this->methodCache[$key] = $resolved;
                return $resolved;
            }
        }

        $this->methodCache[$key] = $this->defaultCost;
        return $this->defaultCost;
    }

    public function getDefaultCost(): float
    {
        return $this->defaultCost;
    }
}
