<?php

declare(strict_types=1);

namespace Sphpera\Analysis;

use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Throwable;

final class AstParser
{
    /**
     * @return array<int, mixed>|null
     */
    public function parseFile(string $file): ?array
    {
        $code = (string) file_get_contents($file);

        $factory = new ParserFactory();
        $parser = $factory->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
            if ($ast === null) {
                return null;
            }

            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());

            /** @var array<int, mixed> $resolved */
            $resolved = $traverser->traverse($ast);
            return $resolved;
        } catch (Throwable) {
            return null;
        }
    }
}
