<?php

declare(strict_types=1);

namespace Sphpera\Model;

use DateTimeImmutable;

final readonly class AnalysisResult
{
    /**
     * @param list<ClassHotspot> $classes
     * @param list<MethodHotspot> $methods
     * @param list<LineContribution> $lineContributions
     */
    public function __construct(
        public AnalysisSummary $summary,
        public array $classes,
        public array $methods,
        public array $lineContributions,
        public DateTimeImmutable $generatedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary->toArray(),
            'classes' => array_map(static fn (ClassHotspot $class): array => $class->toArray(), $this->classes),
            'methods' => array_map(static fn (MethodHotspot $method): array => $method->toArray(), $this->methods),
            'lineContributions' => array_map(static fn (LineContribution $contribution): array => $contribution->toArray(), $this->lineContributions),
            'generatedAt' => $this->generatedAt->format(DATE_ATOM),
        ];
    }
}
