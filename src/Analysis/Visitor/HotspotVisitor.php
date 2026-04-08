<?php

declare(strict_types=1);

namespace Sphpera\Analysis\Visitor;

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
use Sphpera\Model\LineContribution;
use Sphpera\Scoring\RuleSet;
use Sphpera\Type\TypeResolverInterface;

final class HotspotVisitor extends NodeVisitorAbstract
{
    /** @var list<LineContribution> */
    private array $contributions = [];

    private string $file = '';
    private string $namespace = '';
    private string $className = '';
    private string $methodName = '';

    /** @var array<string, string> */
    private array $uses = [];

    /** @var array<string, array{class:string, confidence:float}> */
    private array $variableTypes = [];

    /** @var list<array{start:int,end:int,multiplier:int}> */
    private array $loopStack = [];

    public function __construct(
        private readonly RuleSet $ruleSet,
        private readonly TypeResolverInterface $typeResolver,
    ) {
    }

    public function setFile(string $file): void
    {
        $this->file = $file;
    }

    /**
     * @return list<LineContribution>
     */
    public function getContributions(): array
    {
        return $this->contributions;
    }

    public function enterNode(Node $node)
    {
        $line = $node->getStartLine();
        $this->popFinishedLoops($line);

        if ($node instanceof Namespace_) {
            $this->namespace = $node->name instanceof Name ? $node->name->toString() : '';
            $this->uses = [];
            return null;
        }

        if ($node instanceof Use_) {
            foreach ($node->uses as $useUse) {
                $alias = $useUse->alias ? $useUse->alias->toString() : $useUse->name->getLast();
                $this->uses[$alias] = ltrim($useUse->name->toString(), '\\');
            }
            return null;
        }

        if ($node instanceof Class_) {
            $this->className = $node->name ? $node->name->name : '';
            return null;
        }

        if ($node instanceof ClassMethod) {
            $this->methodName = $node->name->name;
            $this->variableTypes = [];
            return null;
        }

        if ($node instanceof Foreach_ || $node instanceof While_ || $node instanceof Do_) {
            $this->loopStack[] = [
                'start' => $node->getStartLine(),
                'end' => $node->getEndLine(),
                'multiplier' => 100,
            ];
            return null;
        }

        if ($node instanceof For_) {
            $this->loopStack[] = [
                'start' => $node->getStartLine(),
                'end' => $node->getEndLine(),
                'multiplier' => $this->resolveForMultiplier($node),
            ];
            return null;
        }

        if ($node instanceof Assign) {
            $this->registerVariableType($node);
            return null;
        }

        if ($node instanceof FuncCall) {
            $functionName = $this->resolveFunctionName($node);
            if ($functionName === null) {
                return null;
            }

            $baseCost = $this->ruleSet->resolveFunctionCost($functionName);
            $multiplier = $this->getCurrentMultiplier();
            $confidence = $node->name instanceof Name ? 1.0 : 0.55;
            $this->addContribution($node, 'function', 'Function call ' . $functionName, $baseCost, $multiplier, $confidence);

            return null;
        }

        if ($node instanceof MethodCall) {
            $methodData = $this->resolveMethodNameWithConfidence($node);
            if ($methodData === null) {
                return null;
            }
            $methodName = $methodData['name'];
            $methodConfidence = $methodData['confidence'];

            $target = $this->resolveMethodCallTarget($node);
            $className = $target['class'] ?? '';
            $classConfidence = $target['confidence'];

            $baseCost = $className !== '' ? $this->ruleSet->resolveMethodCost($className, $methodName) : $this->ruleSet->getDefaultCost();
            $usedHttpVerbAlias = false;
            if ($className === 'GuzzleHttp\\Client' && in_array($methodName, ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'], true)) {
                $mappedCost = $this->ruleSet->resolveMethodCost($className, 'request');
                if ($mappedCost !== $this->ruleSet->getDefaultCost()) {
                    $baseCost = $mappedCost;
                    $usedHttpVerbAlias = true;
                }
            }

            $multiplier = $this->getCurrentMultiplier();
            $reason = $className !== '' ? 'Method call ' . $className . '::' . $methodName : 'Method call ' . $methodName;
            $confidence = max(0.2, min(1.0, $classConfidence * $methodConfidence));
            if ($className === '') {
                $confidence = min($confidence, 0.45);
            } elseif ($baseCost === $this->ruleSet->getDefaultCost()) {
                $confidence *= 0.75;
            }
            if ($usedHttpVerbAlias) {
                $confidence *= 0.95;
            }

            $this->addContribution($node, 'method', $reason, $baseCost, $multiplier, $confidence);

            return null;
        }

        return null;
    }

    private function addContribution(Node $node, string $kind, string $reason, float $baseCost, int $multiplier, float $confidence): void
    {
        if ($this->className === '' || $this->methodName === '') {
            return;
        }

        $className = $this->namespace !== '' ? $this->namespace . '\\' . $this->className : $this->className;
        $finalCost = $baseCost * $multiplier;

        $this->contributions[] = new LineContribution(
            $this->file,
            $className,
            $this->methodName,
            $node->getStartLine(),
            $node->getEndLine(),
            $kind,
            $reason,
            $baseCost,
            $multiplier,
            $finalCost,
            $confidence,
        );
    }

    private function resolveFunctionName(FuncCall $call): ?string
    {
        if ($call->name instanceof Name) {
            return ltrim($call->name->toString(), '\\');
        }

        if ($call->name instanceof Variable && is_string($call->name->name)) {
            return $call->name->name;
        }

        return null;
    }

    /**
     * @return array{name:string, confidence:float}|null
     */
    private function resolveMethodNameWithConfidence(MethodCall $call): ?array
    {
        if ($call->name instanceof Identifier) {
            return ['name' => $call->name->name, 'confidence' => 1.0];
        }

        if ($call->name instanceof Variable && is_string($call->name->name)) {
            return ['name' => $call->name->name, 'confidence' => 0.6];
        }

        return null;
    }

    /**
     * @return array{class:?string, confidence:float}
     */
    private function resolveMethodCallTarget(MethodCall $call): array
    {
        $var = $call->var;

        if ($var instanceof Variable && is_string($var->name)) {
            $knownType = $this->variableTypes[$var->name] ?? null;
            if ($knownType !== null) {
                return ['class' => $knownType['class'], 'confidence' => $knownType['confidence']];
            }
            return ['class' => null, 'confidence' => 0.35];
        }

        if ($var instanceof New_) {
            return ['class' => $this->resolveClassName($var->class), 'confidence' => 0.95];
        }

        if ($var instanceof MethodCall) {
            $receiver = $this->resolveMethodCallTarget($var);
            $receiverClass = $receiver['class'];
            $receiverMethodData = $this->resolveMethodNameWithConfidence($var);
            if ($receiverClass === null || $receiverMethodData === null) {
                return ['class' => null, 'confidence' => 0.35];
            }
            $receiverMethod = $receiverMethodData['name'];

            $returnClasses = $this->typeResolver->resolveMethodReturnClasses($receiverClass, $receiverMethod);
            if ($returnClasses !== []) {
                return ['class' => $returnClasses[0], 'confidence' => min(0.9, $receiver['confidence'])];
            }

            if ($receiverClass === 'PDO' && $receiverMethod === 'query') {
                return ['class' => 'PDOStatement', 'confidence' => min(0.65, $receiver['confidence'])];
            }
            if ($receiverClass === 'GuzzleHttp\\Client' && in_array($receiverMethod, ['request', 'get', 'post', 'put', 'patch', 'delete', 'head', 'options'], true)) {
                return ['class' => 'Psr\\Http\\Message\\ResponseInterface', 'confidence' => min(0.7, $receiver['confidence'])];
            }
            if ($receiverClass === 'Psr\\Http\\Message\\ResponseInterface' && $receiverMethod === 'getBody') {
                return ['class' => 'Psr\\Http\\Message\\StreamInterface', 'confidence' => min(0.7, $receiver['confidence'])];
            }
        }

        return ['class' => null, 'confidence' => 0.35];
    }

    private function registerVariableType(Assign $assign): void
    {
        if (!$assign->var instanceof Variable || !is_string($assign->var->name)) {
            return;
        }

        $variableName = $assign->var->name;

        if ($assign->expr instanceof New_) {
            $className = $this->resolveClassName($assign->expr->class);
            if ($className !== null) {
                $this->variableTypes[$variableName] = ['class' => $className, 'confidence' => 0.95];
            }
            return;
        }

        if ($assign->expr instanceof MethodCall) {
            $target = $this->resolveMethodCallTarget($assign->expr);
            if ($target['class'] !== null) {
                $this->variableTypes[$variableName] = ['class' => $target['class'], 'confidence' => $target['confidence']];
            }
        }
    }

    private function resolveClassName(Node $node): ?string
    {
        if (!$node instanceof Name) {
            return null;
        }

        $raw = ltrim($node->toString(), '\\');

        if (isset($this->uses[$raw])) {
            $raw = $this->uses[$raw];
        } elseif (strpos($raw, '\\') === false && $this->namespace !== '') {
            $raw = $this->namespace . '\\' . $raw;
        }

        return $this->typeResolver->normalizeClassName($raw) ?? $raw;
    }

    private function popFinishedLoops(int $line): void
    {
        while ($this->loopStack !== [] && $line > $this->loopStack[array_key_last($this->loopStack)]['end']) {
            array_pop($this->loopStack);
        }
    }

    private function getCurrentMultiplier(): int
    {
        if ($this->loopStack === []) {
            return 1;
        }

        $multiplier = 1;
        foreach ($this->loopStack as $loop) {
            $multiplier *= max(1, $loop['multiplier']);
        }

        return $multiplier;
    }

    private function resolveForMultiplier(For_ $for): int
    {
        foreach ($for->cond as $condition) {
            if ($condition instanceof Smaller && $condition->right instanceof LNumber) {
                return max(1, $condition->right->value);
            }

            if ($condition instanceof SmallerOrEqual && $condition->right instanceof LNumber) {
                return max(1, $condition->right->value + 1);
            }
        }

        return 100;
    }
}
