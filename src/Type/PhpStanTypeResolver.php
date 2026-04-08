<?php

declare(strict_types=1);

namespace Sphpera\Type;

use PHPStan\DependencyInjection\ContainerFactory;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\VerbosityLevel;
use Throwable;

final class PhpStanTypeResolver implements TypeResolverInterface
{
    private ?ReflectionProvider $reflectionProvider = null;
    /** @var array<string, list<string>> */
    private array $methodReturnCache = [];
    /** @var array<string, string|null> */
    private array $normalizeCache = [];

    public function __construct(private readonly string $projectRoot)
    {
        $this->boot();
    }

    public function resolveMethodReturnClasses(string $className, string $methodName): array
    {
        $cacheKey = $className . '|' . $methodName;
        if (array_key_exists($cacheKey, $this->methodReturnCache)) {
            return $this->methodReturnCache[$cacheKey];
        }

        $provider = $this->reflectionProvider;
        if ($provider === null || $className === '' || $methodName === '') {
            $this->methodReturnCache[$cacheKey] = [];
            return [];
        }

        try {
            if (!$provider->hasClass($className)) {
                $this->methodReturnCache[$cacheKey] = [];
                return [];
            }

            $classReflection = $provider->getClass($className);
            if (!$classReflection->hasNativeMethod($methodName)) {
                $this->methodReturnCache[$cacheKey] = [];
                return [];
            }

            $methodReflection = $classReflection->getNativeMethod($methodName);
            $variants = $methodReflection->getVariants();
            if ($variants === []) {
                $this->methodReturnCache[$cacheKey] = [];
                return [];
            }

            $type = $variants[0]->getReturnType();
            $classNames = $type->getObjectClassNames();
            if ($classNames !== []) {
                $resolved = array_values(array_unique($classNames));
                $this->methodReturnCache[$cacheKey] = $resolved;
                return $resolved;
            }

            $describedType = $type->describe(VerbosityLevel::typeOnly());
            $matched = preg_match_all('/[A-Za-z_\\\\][A-Za-z0-9_\\\\]*/', $describedType, $matches);
            if ($matched === false) {
                $this->methodReturnCache[$cacheKey] = [];
                return [];
            }

            $resolved = [];
            foreach ($matches[0] as $candidate) {
                if ($provider->hasClass($candidate)) {
                    $resolved[] = $provider->getClass($candidate)->getName();
                }
            }

            $result = array_values(array_unique($resolved));
            $this->methodReturnCache[$cacheKey] = $result;
            return $result;
        } catch (Throwable) {
            $this->methodReturnCache[$cacheKey] = [];
            return [];
        }
    }

    public function normalizeClassName(string $className): ?string
    {
        if (array_key_exists($className, $this->normalizeCache)) {
            return $this->normalizeCache[$className];
        }

        $provider = $this->reflectionProvider;
        if ($provider === null || $className === '') {
            $resolved = $className !== '' ? $className : null;
            $this->normalizeCache[$className] = $resolved;
            return $resolved;
        }

        try {
            if ($provider->hasClass($className)) {
                $resolved = $provider->getClass($className)->getName();
                $this->normalizeCache[$className] = $resolved;
                return $resolved;
            }
        } catch (Throwable) {
            $this->normalizeCache[$className] = $className;
            return $className;
        }

        $this->normalizeCache[$className] = $className;
        return $className;
    }

    private function boot(): void
    {
        try {
            $factory = new ContainerFactory($this->projectRoot);
            $container = $factory->create(
                sys_get_temp_dir() . '/sphpera-phpstan',
                [],
                [$this->projectRoot],
                [],
                [],
                'max',
                null,
                null,
                null,
                null,
                [],
            );

            /** @var ReflectionProvider $reflectionProvider */
            $reflectionProvider = $container->getByType(ReflectionProvider::class);
            $this->reflectionProvider = $reflectionProvider;
        } catch (Throwable) {
            $this->reflectionProvider = null;
        }
    }
}
