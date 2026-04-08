<?php

namespace Sphpera\Parser\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Expr\BinaryOp\SmallerOrEqual;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeVisitorAbstract;
use Sphpera\Config\Config;
use Sphpera\Stack;

class MyVisitor extends NodeVisitorAbstract
{
    /** @var Config */
    private $config;

    /** @var Stack */
    private $stack;
    /** @var array<string, string> */
    private $variableTypes = [];
    /** @var array<string, string> */
    private $uses = [];
    /** @var string */
    private $currentNamespace = '';

    public function __construct(Config $config, Stack $stack)
    {
        $this->config = $config;
        $this->stack = $stack;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Foreach_ || $node instanceof While_ || $node instanceof Do_) {
            // TODO get number of cycle iterations
            $this->stack->startCycle($node->getStartLine(), $node->getEndLine());
            return null;
        }
        if ($node instanceof For_) {
            $this->stack->startCycle($node->getStartLine(), $node->getEndLine(), $this->resolveForMultiplier($node));
            return null;
        }

        $this->stack->checkCycle($node->getStartLine());

        if ($node instanceof Namespace_) {
            $name = $node->name;
            $namespace = null;
            if ($name instanceof Name) {
                $namespace = $name->toString();
            }
            $this->currentNamespace = $namespace ?? '';
            $this->uses = [];
            $this->stack->actualNamespace($namespace);
            return null;
        }

        if ($node instanceof Use_) {
            foreach ($node->uses as $useUse) {
                $fqcn = $useUse->name->toString();
                $alias = $useUse->alias ? $useUse->alias->toString() : $useUse->name->getLast();
                $this->uses[$alias] = $fqcn;
            }
            return null;
        }

        if ($node instanceof Class_) {
            $name = $node->name;
            $className = null;
            if ($name) {
                $className = $name->name;
            }
            $this->stack->actualClass($className);
            return null;
        }

        if ($node instanceof ClassMethod) {
            $this->variableTypes = [];
            $this->stack->actualMethod($node->name->name);
            return null;
        }

        if ($node instanceof Assign) {
            $this->registerVariableType($node);
            return null;
        }

        if ($node instanceof MethodCall) {
            $className = $this->resolveMethodCallClass($node);

            $nodeName = $node->name;
            $methodName = null;
            // TODO resolve final method name
            if ($nodeName instanceof Identifier) {
                $methodName = $nodeName->name;
            } elseif ($nodeName instanceof Variable && is_string($nodeName->name)) {
                $methodName = $nodeName->name;
            }

            if (!$methodName) {
                return null;
            }

            $methodNames = [$methodName];
            if ($className === 'GuzzleHttp\\Client' && in_array($methodName, ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'], true)) {
                $methodNames[] = 'request';
            }

            foreach ($this->config->getMethods() as $class => $methods) {
                if (!$this->matchesPattern($class, $className)) {
                    continue;
                }
                foreach ($methods as $method => $score) {
                    foreach ($methodNames as $candidateMethodName) {
                        if ($this->matchesPattern($method, $candidateMethodName)) {
                            $this->stack->add($score);
                            return null;
                        }
                    }
                }
            }
            $this->stack->add($this->config->getDefault());
            return null;
        }

        if ($node instanceof FuncCall) {
            $nodeName = $node->name;
            $functionName = null;
            // TODO resolve final name
            if ($nodeName instanceof Name) {
                $functionName = $nodeName->toString();
            } elseif ($nodeName instanceof Variable && is_string($nodeName->name)) {    // TODO get final function name if name->name is expr
                $functionName = $nodeName->name;
            }

            if (!$functionName) {
                return null;
            }

            foreach ($this->config->getFunctions() as $function => $score) {
                if ($this->matchesPattern($function, $functionName)) {
                    $this->stack->add($score);
                    return null;
                }
            }
            $this->stack->add($this->config->getDefault());
            return null;
        }

        return null;
    }

    private function resolveForMultiplier(For_ $for): ?int
    {
        foreach ($for->cond as $condition) {
            if ($condition instanceof Smaller && $condition->right instanceof LNumber) {
                return $condition->right->value;
            }
            if ($condition instanceof SmallerOrEqual && $condition->right instanceof LNumber) {
                return $condition->right->value + 1;
            }
        }
        return null;
    }

    private function registerVariableType(Assign $assign): void
    {
        if (!$assign->var instanceof Variable || !is_string($assign->var->name)) {
            return;
        }

        $type = null;
        if ($assign->expr instanceof New_) {
            $type = $this->resolveClassNameFromExpr($assign->expr->class);
        } elseif ($assign->expr instanceof MethodCall) {
            $type = $this->resolveMethodCallReturnClass($assign->expr);
        }

        if ($type) {
            $this->variableTypes[$assign->var->name] = $type;
        }
    }

    private function resolveMethodCallClass(MethodCall $methodCall): string
    {
        $var = $methodCall->var;
        if ($var instanceof Variable && is_string($var->name)) {
            return $this->variableTypes[$var->name] ?? '';
        }
        if ($var instanceof New_) {
            return $this->resolveClassNameFromExpr($var->class);
        }
        if ($var instanceof MethodCall) {
            return $this->resolveMethodCallReturnClass($var) ?? '';
        }
        return '';
    }

    private function resolveMethodCallReturnClass(MethodCall $methodCall): ?string
    {
        $className = $this->resolveMethodCallClass($methodCall);
        $methodName = $methodCall->name instanceof Identifier ? $methodCall->name->name : null;
        if (!$className || !$methodName) {
            return null;
        }

        if ($className === 'PDO' && $methodName === 'query') {
            return 'PDOStatement';
        }
        if ($className === 'GuzzleHttp\\Client' && in_array($methodName, ['request', 'get', 'post', 'put', 'patch', 'delete', 'head', 'options'], true)) {
            return 'Psr\\Http\\Message\\ResponseInterface';
        }
        if ($className === 'Psr\\Http\\Message\\ResponseInterface' && $methodName === 'getBody') {
            return 'Psr\\Http\\Message\\StreamInterface';
        }

        return null;
    }

    private function resolveClassNameFromExpr(Node $expr): ?string
    {
        if ($expr instanceof Name) {
            $name = ltrim($expr->toString(), '\\');
            if (isset($this->uses[$name])) {
                return $this->uses[$name];
            }
            if (strpos($name, '\\') !== false) {
                return $name;
            }
            if ($this->currentNamespace !== '') {
                return $this->currentNamespace . '\\' . $name;
            }
            return $name;
        }
        return null;
    }

    private function matchesPattern(string $pattern, string $value): bool
    {
        $escaped = preg_quote($pattern, '/');
        $regex = '/^' . str_replace('\*', '.*', $escaped) . '$/';
        return preg_match($regex, $value) === 1;
    }
}
