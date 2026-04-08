<?php

declare(strict_types=1);

namespace Sphpera\Model;

final readonly class LineContribution
{
    public function __construct(
        public string $file,
        public string $className,
        public string $methodName,
        public int $startLine,
        public int $endLine,
        public string $kind,
        public string $reason,
        public float $baseCost,
        public int $multiplier,
        public float $finalCost,
        public float $confidence = 1.0,
    ) {
    }

    /**
     * @return array<string, int|float|string>
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'class' => $this->className,
            'method' => $this->methodName,
            'startLine' => $this->startLine,
            'endLine' => $this->endLine,
            'kind' => $this->kind,
            'reason' => $this->reason,
            'baseCost' => $this->baseCost,
            'multiplier' => $this->multiplier,
            'finalCost' => $this->finalCost,
            'confidence' => $this->confidence,
        ];
    }
}
