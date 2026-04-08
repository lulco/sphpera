<?php

declare(strict_types=1);

namespace Sphpera\Type;

interface TypeResolverInterface
{
    /**
     * @return list<string>
     */
    public function resolveMethodReturnClasses(string $className, string $methodName): array;

    public function normalizeClassName(string $className): ?string;
}
