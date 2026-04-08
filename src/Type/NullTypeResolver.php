<?php

declare(strict_types=1);

namespace Sphpera\Type;

final class NullTypeResolver implements TypeResolverInterface
{
    public function resolveMethodReturnClasses(string $className, string $methodName): array
    {
        return [];
    }

    public function normalizeClassName(string $className): ?string
    {
        return $className !== '' ? $className : null;
    }
}
