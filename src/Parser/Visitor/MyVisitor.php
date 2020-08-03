<?php

namespace Sphpera\Parser\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeVisitorAbstract;
use Sphpera\Stack;

class MyVisitor extends NodeVisitorAbstract
{
    /** @var array */
    private $config;

    /** @var Stack */
    private $stack;

    public function __construct(array $config, Stack $stack)
    {
        $this->config = $config;
        $this->stack = $stack;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Foreach_ || $node instanceof While_ || $node instanceof For_) {
            // TODO get number of cycle iterations
            $this->stack->startCycle($node->getStartLine(), $node->getEndLine());
            return null;
        }

        $this->stack->checkCycle($node->getStartLine());

        if ($node instanceof Namespace_) {
            $this->stack->actualNamespace(implode('\\', $node->name->parts));
            return null;
        }

        if ($node instanceof Class_) {
            $this->stack->actualClass($node->name->name);
            return null;
        }

        if ($node instanceof ClassMethod) {
            $this->stack->actualMethod($node->name->name);
            return null;
        }

        if ($node instanceof MethodCall) {
            // TODO resolve class name from variables etc
            $className = '';
            $methodName = $node->name->name;
            foreach ($this->config['methods'] as $class => $methods) {
                if (!preg_match('/' . str_replace('*', '(.*?)', $class) . '/', $className)) {
                    continue;
                }
                foreach ($methods as $method => $score) {
                    if (preg_match('/' . str_replace('*', '(.*?)', $method) . '/', $methodName)) {
                        $this->stack->add($score);
                        return null;
                    }
                }
            }
            $this->stack->add($this->config['default']);
            return null;
        }

        if ($node instanceof FuncCall) {
            $name = $node->name;
            // TODO resolve final name
            if ($name instanceof Name) {
                $functionName = implode('\\', $name->parts);
            } elseif ($name instanceof Variable) {
                $functionName = $name->name;
            }
            foreach ($this->config['functions'] as $function => $score) {
                if (preg_match('/' . str_replace('*', '(.*?)', $function) . '/', $functionName)) {
                    $this->stack->add($score);
                    return null;
                }
            }
            $this->stack->add($this->config['default']);
            return null;
        }

        return null;
    }
}
