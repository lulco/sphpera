<?php

declare(strict_types=1);

namespace Sphpera\Type;

final class CompositeTypeResolver implements TypeResolverInterface
{
    /** @var list<TypeResolverInterface> */
    private array $resolvers;

    public function __construct(TypeResolverInterface ...$resolvers)
    {
        $this->resolvers = $resolvers;
    }

    public function resolveMethodReturnClasses(string $className, string $methodName): array
    {
        foreach ($this->resolvers as $resolver) {
            $classes = $resolver->resolveMethodReturnClasses($className, $methodName);
            if ($classes !== []) {
                return $classes;
            }
        }

        return [];
    }

    public function normalizeClassName(string $className): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $normalized = $resolver->normalizeClassName($className);
            if ($normalized !== null && $normalized !== '') {
                return $normalized;
            }
        }

        return $className !== '' ? $className : null;
    }
}
