<?php

declare(strict_types=1);

namespace Sphpera\Model;

final readonly class ClassHotspot
{
    /**
     * @param list<MethodHotspot> $methods
     */
    public function __construct(
        public string $file,
        public string $className,
        public float $totalScore,
        public array $methods,
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
            'totalScore' => $this->totalScore,
            'methods' => array_map(static fn (MethodHotspot $method): array => $method->toArray(), $this->methods),
        ];
    }
}
