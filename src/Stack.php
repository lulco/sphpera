<?php

namespace Sphpera;

class Stack
{
    /** @var string|null */
    private $actualNamespace;

    /** @var string|null */
    private $actualClass;

    /** @var string|null */
    private $actualMethod;

    /** @var bool */
    private $inCycle = false;

    /** @var int|null */
    private $cycleStart = null;

    /** @var int|null */
    private $cycleEnd = null;

    /** @var int|null */
    private $cycleMultiplier = null;

    /** @var array<string, array<string, float>> */
    private $scores = [];

    public function actualNamespace(?string $namespace): void
    {
        $this->actualNamespace = $namespace;
    }

    public function actualClass(?string $class): void
    {
        $this->actualClass = $class;
    }

    public function actualMethod(?string $method): void
    {
        $this->actualMethod = $method;
    }

    public function startCycle(int $startLine, int $endLine, ?int $multiplier = null): void
    {
        $this->inCycle = true;
        $this->cycleStart = $startLine;
        $this->cycleEnd = $endLine;
        $this->cycleMultiplier = $multiplier ?: 100;
    }

    public function checkCycle(int $line): bool
    {
        if ($this->inCycle === false) {
            return false;
        }

        if ($this->cycleStart <= $line && $this->cycleEnd >= $line) {
            return true;
        }

        $this->inCycle = false;
        $this->cycleStart = null;
        $this->cycleEnd = null;
        $this->cycleMultiplier = null;
        return false;
    }

    public function add(float $score): void
    {
        $className = implode('\\', array_filter([$this->actualNamespace, $this->actualClass]));
        $methodName = $this->actualMethod ?: '';

        if (!isset($this->scores[$className][$methodName])) {
            $this->scores[$className][$methodName] = 0;
        }
        if ($this->inCycle && $this->cycleMultiplier) {
            $score *= $this->cycleMultiplier;
        }
        $this->scores[$className][$methodName] += $score;
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function getScores(): array
    {
        return $this->scores;
    }
}
