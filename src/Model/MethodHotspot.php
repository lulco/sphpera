<?php

declare(strict_types=1);

namespace Sphpera\Model;

final readonly class MethodHotspot
{
    /**
     * @param array<int, float> $lineScores
     * @param list<LineContribution> $contributions
     */
    public function __construct(
        public string $file,
        public string $className,
        public string $methodName,
        public float $totalScore,
        public array $lineScores,
        public array $contributions,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'class' => $this->className,
            'method' => $this->methodName,
            'totalScore' => $this->totalScore,
            'lineScores' => $this->lineScores,
            'contributions' => array_map(static fn (LineContribution $contribution): array => $contribution->toArray(), $this->contributions),
        ];
    }
}
