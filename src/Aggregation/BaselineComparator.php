<?php

declare(strict_types=1);

namespace Sphpera\Aggregation;

use Sphpera\Model\AnalysisResult;

final class BaselineComparator
{
    /**
     * @param array<string, mixed> $baselineData
     * @return array{classDeltas: array<string, float>, methodDeltas: array<string, float>}
     */
    public function compare(AnalysisResult $current, array $baselineData): array
    {
        if (isset($baselineData['result']) && is_array($baselineData['result'])) {
            /** @var array<string, mixed> $baselineData */
            $baselineData = $baselineData['result'];
        }

        $baselineClassScores = $this->extractClassScores($baselineData);
        $baselineMethodScores = $this->extractMethodScores($baselineData);

        $classDeltas = [];
        foreach ($current->classes as $class) {
            $key = $this->classKey($class->file, $class->className);
            $baselineScore = $baselineClassScores[$key] ?? 0.0;
            $classDeltas[$key] = $class->totalScore - $baselineScore;
        }

        $methodDeltas = [];
        foreach ($current->methods as $method) {
            $key = $this->methodKey($method->file, $method->className, $method->methodName);
            $baselineScore = $baselineMethodScores[$key] ?? 0.0;
            $methodDeltas[$key] = $method->totalScore - $baselineScore;
        }

        return ['classDeltas' => $classDeltas, 'methodDeltas' => $methodDeltas];
    }

    /**
     * @param array<string, mixed> $baselineData
     * @return array<string, float>
     */
    private function extractClassScores(array $baselineData): array
    {
        $result = [];
        if (!isset($baselineData['classes']) || !is_array($baselineData['classes'])) {
            return $result;
        }

        foreach ($baselineData['classes'] as $class) {
            if (!is_array($class)) {
                continue;
            }
            $file = (string) ($class['file'] ?? '');
            $className = (string) ($class['class'] ?? '');
            $score = (float) ($class['totalScore'] ?? 0.0);
            if ($file === '' || $className === '') {
                continue;
            }
            $result[$this->classKey($file, $className)] = $score;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $baselineData
     * @return array<string, float>
     */
    private function extractMethodScores(array $baselineData): array
    {
        $result = [];
        if (!isset($baselineData['methods']) || !is_array($baselineData['methods'])) {
            return $result;
        }

        foreach ($baselineData['methods'] as $method) {
            if (!is_array($method)) {
                continue;
            }
            $file = (string) ($method['file'] ?? '');
            $className = (string) ($method['class'] ?? '');
            $methodName = (string) ($method['method'] ?? '');
            $score = (float) ($method['totalScore'] ?? 0.0);
            if ($file === '' || $className === '' || $methodName === '') {
                continue;
            }
            $result[$this->methodKey($file, $className, $methodName)] = $score;
        }

        return $result;
    }

    private function classKey(string $file, string $className): string
    {
        return $file . '|' . $className;
    }

    private function methodKey(string $file, string $className, string $methodName): string
    {
        return $file . '|' . $className . '|' . $methodName;
    }
}
