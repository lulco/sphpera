<?php

declare(strict_types=1);

namespace Sphpera\Aggregation;

use DateTimeImmutable;
use Sphpera\Model\AnalysisResult;
use Sphpera\Model\AnalysisSummary;
use Sphpera\Model\ClassHotspot;
use Sphpera\Model\LineContribution;
use Sphpera\Model\MethodHotspot;

final class ResultAggregator
{
    /**
     * @param list<string> $files
     * @param list<LineContribution> $contributions
     */
    public function aggregate(array $files, array $contributions, DateTimeImmutable $generatedAt): AnalysisResult
    {
        /** @var array<string, list<LineContribution>> $byMethod */
        $byMethod = [];
        foreach ($contributions as $contribution) {
            $key = $contribution->file . '|' . $contribution->className . '|' . $contribution->methodName;
            $byMethod[$key][] = $contribution;
        }

        $methods = [];
        foreach ($byMethod as $methodContributions) {
            $sample = $methodContributions[0];
            $totalScore = 0.0;
            /** @var array<int, float> $lineScores */
            $lineScores = [];

            foreach ($methodContributions as $contribution) {
                $totalScore += $contribution->finalCost;
                $lineSpan = max(1, $contribution->endLine - $contribution->startLine + 1);
                $perLineCost = $contribution->finalCost / $lineSpan;
                for ($line = $contribution->startLine; $line <= $contribution->endLine; $line++) {
                    $lineScores[$line] = ($lineScores[$line] ?? 0.0) + $perLineCost;
                }
            }

            ksort($lineScores);
            $methods[] = new MethodHotspot(
                $sample->file,
                $sample->className,
                $sample->methodName,
                $totalScore,
                $lineScores,
                $methodContributions,
            );
        }

        usort($methods, static fn (MethodHotspot $a, MethodHotspot $b): int => $b->totalScore <=> $a->totalScore);

        /** @var array<string, list<MethodHotspot>> $byClass */
        $byClass = [];
        foreach ($methods as $method) {
            $key = $method->file . '|' . $method->className;
            $byClass[$key][] = $method;
        }

        $classes = [];
        foreach ($byClass as $classMethods) {
            $sample = $classMethods[0];
            $totalScore = 0.0;
            foreach ($classMethods as $method) {
                $totalScore += $method->totalScore;
            }

            usort($classMethods, static fn (MethodHotspot $a, MethodHotspot $b): int => $b->totalScore <=> $a->totalScore);
            $classes[] = new ClassHotspot($sample->file, $sample->className, $totalScore, $classMethods);
        }

        usort($classes, static fn (ClassHotspot $a, ClassHotspot $b): int => $b->totalScore <=> $a->totalScore);

        $summary = new AnalysisSummary(
            count($files),
            count($classes),
            count($methods),
            count($contributions),
        );

        return new AnalysisResult($summary, $classes, $methods, $contributions, $generatedAt);
    }
}
