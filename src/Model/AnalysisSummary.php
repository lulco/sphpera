<?php

declare(strict_types=1);

namespace Sphpera\Model;

final readonly class AnalysisSummary
{
    public function __construct(
        public int $files,
        public int $classes,
        public int $methods,
        public int $contributions,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'files' => $this->files,
            'classes' => $this->classes,
            'methods' => $this->methods,
            'contributions' => $this->contributions,
        ];
    }
}
