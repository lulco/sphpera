<?php

namespace Sphpera;

class Stack
{
    private $actualNamespace;

    private $actualClass;

    private $actualMethod;

    private $inCycle = false;

    private $cycleStart = null;

    private $cycleEnd = null;

    private $cycleMultiplier = null;

    private $scores = [];

    public function actualNamespace(string $namespace): void
    {
        $this->actualNamespace = $namespace;
    }

    public function actualClass(string $class): void
    {
        $this->actualClass = $class;
    }

    public function actualMethod(string $method): void
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
        if (!isset($this->scores[$this->actualNamespace . '\\' . $this->actualClass][$this->actualMethod])) {
            $this->scores[$this->actualNamespace . '\\' . $this->actualClass][$this->actualMethod] = 0;
        }
        if ($this->inCycle && $this->cycleMultiplier) {
            $score *= $this->cycleMultiplier;
        }
        $this->scores[$this->actualNamespace . '\\' . $this->actualClass][$this->actualMethod] += $score;
    }

    public function getScores(): array
    {
        return $this->scores;
    }
}
