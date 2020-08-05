<?php

namespace Sphpera;

use Exception;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeTraverserInterface;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Sphpera\Config\Config;
use Sphpera\Parser\Visitor\MyVisitor;

class ScoreResolver
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $path
     * @return array<string, array<string, float>>
     * @throws Exception
     */
    public function resolve(string $path): array
    {
        $stack = new Stack();
        $traverser = $this->createTraverser($stack);

        $code = (string)file_get_contents($path);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $ast = null;
        try {
            $ast = $parser->parse($code);
        } catch (Error $error) {
            // TODO log error
            return [];
        }
        if (!$ast) {
            throw new Exception('Cannot parse file "' . $path . '"');
        }

        $traverser->traverse($ast);
        return $stack->getScores();
    }

    private function createTraverser(Stack $stack): NodeTraverserInterface
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new MyVisitor($this->config, $stack));
        return $traverser;
    }
}
