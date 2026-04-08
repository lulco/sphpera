<?php

declare(strict_types=1);

namespace Sphpera\Report\Html;

use Sphpera\Model\AnalysisResult;
use Sphpera\Model\ClassHotspot;
use Sphpera\Model\MethodHotspot;

final class HtmlReportBuilder
{
    /**
     * @param array{classDeltas: array<string, float>, methodDeltas: array<string, float>}|null $comparison
     */
    public function generate(AnalysisResult $result, string $outputDir, ?array $comparison = null): void
    {
        $this->ensureDir($outputDir);
        $this->ensureDir($outputDir . '/classes');
        $this->ensureDir($outputDir . '/dirs');
        $this->ensureDir($outputDir . '/files');

        $indexData = $this->buildIndexData($result);

        file_put_contents($outputDir . '/style.css', $this->styleCss());
        $dashboardHtml = $this->renderDashboard($result, $comparison);
        file_put_contents($outputDir . '/index.html', $dashboardHtml);
        file_put_contents($outputDir . '/dashboard.html', $dashboardHtml);
        file_put_contents($outputDir . '/index-tree.html', $this->renderDirectoryPage('', $indexData, '../', false));

        foreach ($indexData['directories'] as $directoryPath => $_directoryData) {
            if ($directoryPath === '') {
                continue;
            }
            file_put_contents(
                $outputDir . '/dirs/' . $this->pathSlug($directoryPath) . '.html',
                $this->renderDirectoryPage($directoryPath, $indexData, '../', true),
            );
        }

        foreach ($indexData['files'] as $filePath => $fileData) {
            file_put_contents(
                $outputDir . '/files/' . $this->pathSlug($filePath) . '.html',
                $this->renderFilePage($filePath, $fileData, $comparison),
            );
        }

        foreach ($result->classes as $classHotspot) {
            file_put_contents(
                $outputDir . '/classes/' . $this->classFileName($classHotspot) . '.html',
                $this->renderClassDetail($classHotspot, $comparison),
            );
        }
    }

    /**
     * @param array{classDeltas: array<string, float>, methodDeltas: array<string, float>}|null $comparison
     */
    private function renderDashboard(AnalysisResult $result, ?array $comparison): string
    {
        $rows = '';
        foreach ($result->classes as $classHotspot) {
            $deltaText = '';
            if ($comparison !== null) {
                $classKey = $this->classKey($classHotspot->file, $classHotspot->className);
                $deltaText = $this->formatDelta($comparison['classDeltas'][$classKey] ?? 0.0);
            }
            $rows .= sprintf(
                '<tr><td><a href="classes/%s.html">%s</a></td><td>%s</td><td>%s</td><td>%d</td></tr>',
                htmlspecialchars($this->classFileName($classHotspot), ENT_QUOTES),
                htmlspecialchars($classHotspot->className, ENT_QUOTES),
                $this->formatScore($classHotspot->totalScore),
                htmlspecialchars($deltaText, ENT_QUOTES),
                count($classHotspot->methods),
            );
        }

        $topMethods = '';
        foreach (array_slice($result->methods, 0, 30) as $methodHotspot) {
            $deltaText = '';
            if ($comparison !== null) {
                $methodKey = $this->methodKey($methodHotspot->file, $methodHotspot->className, $methodHotspot->methodName);
                $deltaText = $this->formatDelta($comparison['methodDeltas'][$methodKey] ?? 0.0);
            }
            $topMethods .= sprintf(
                '<tr><td>%s::%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($methodHotspot->className, ENT_QUOTES),
                htmlspecialchars($methodHotspot->methodName, ENT_QUOTES),
                htmlspecialchars($methodHotspot->file, ENT_QUOTES),
                $this->formatScore($methodHotspot->totalScore),
                htmlspecialchars($deltaText, ENT_QUOTES),
            );
        }

        $summary = $result->summary;

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Sphpera Report</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
' . $this->renderTabs('dashboard', '') . '
<h1>Sphpera Performance Hotspots</h1>
<p class="muted">Generated at ' . htmlspecialchars($result->generatedAt->format('Y-m-d H:i:s'), ENT_QUOTES) . '</p>
<div class="cards">
<div class="card"><span>Files</span><strong>' . $summary->files . '</strong></div>
<div class="card"><span>Classes</span><strong>' . $summary->classes . '</strong></div>
<div class="card"><span>Methods</span><strong>' . $summary->methods . '</strong></div>
<div class="card"><span>Line contributions</span><strong>' . $summary->contributions . '</strong></div>
</div>
<h2>Class Ranking</h2>
<table>
<thead><tr><th>Class</th><th>Total Score</th><th>Delta</th><th>Methods</th></tr></thead>
<tbody>' . $rows . '</tbody>
</table>
<h2>Top Methods</h2>
<table>
<thead><tr><th>Method</th><th>File</th><th>Score</th><th>Delta</th></tr></thead>
<tbody>' . $topMethods . '</tbody>
</table>
</div>
</body>
</html>';
    }

    /**
     * @param array{root:string, directories:array<string, array{name:string, parent:string, path:string, children:list<string>, files:list<string>, stats:array<string, float|int>}>, files:array<string, array{name:string, path:string, directory:string, classes:list<ClassHotspot>, stats:array<string, float|int>}>} $indexData
     */
    private function renderDirectoryPage(string $directoryPath, array $indexData, string $rootPrefix, bool $inSubDir): string
    {
        $dir = $indexData['directories'][$directoryPath] ?? null;
        if (!is_array($dir)) {
            return '';
        }

        $titlePath = $directoryPath !== '' ? $directoryPath : $indexData['root'];
        $crumbs = $this->renderDirectoryBreadcrumbs($directoryPath, $indexData, $inSubDir ? '../' : '');

        $rows = '';
        foreach ($dir['children'] as $childPath) {
            $child = $indexData['directories'][$childPath] ?? null;
            if (!is_array($child)) {
                continue;
            }
            $stats = $child['stats'];
            $rows .= sprintf(
                '<tr class="risk-%s"><td>📁 <a href="%s">%s</a></td><td>Directory</td><td>%d</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->riskClass((float) $stats['risk']),
                htmlspecialchars(($inSubDir ? '../' : '') . 'dirs/' . $this->pathSlug($childPath) . '.html', ENT_QUOTES),
                htmlspecialchars((string) $child['name'], ENT_QUOTES),
                (int) $stats['classes'],
                (int) $stats['methods'],
                $this->formatScore((float) $stats['min']),
                $this->formatScore((float) $stats['avg']),
                $this->formatScore((float) $stats['median']),
                $this->formatScore((float) $stats['max']),
                $this->formatRisk((float) $stats['risk']),
            );
        }

        foreach ($dir['files'] as $filePath) {
            $file = $indexData['files'][$filePath] ?? null;
            if (!is_array($file)) {
                continue;
            }
            $stats = $file['stats'];
            $rows .= sprintf(
                '<tr class="risk-%s"><td>📄 <a href="%s">%s</a></td><td>File</td><td>%d</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->riskClass((float) $stats['risk']),
                htmlspecialchars(($inSubDir ? '../' : '') . 'files/' . $this->pathSlug($filePath) . '.html', ENT_QUOTES),
                htmlspecialchars((string) $file['name'], ENT_QUOTES),
                (int) $stats['classes'],
                (int) $stats['methods'],
                $this->formatScore((float) $stats['min']),
                $this->formatScore((float) $stats['avg']),
                $this->formatScore((float) $stats['median']),
                $this->formatScore((float) $stats['max']),
                $this->formatRisk((float) $stats['risk']),
            );
        }

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Index - ' . htmlspecialchars($titlePath, ENT_QUOTES) . '</title>
<link rel="stylesheet" href="' . htmlspecialchars(($inSubDir ? '../' : '') . 'style.css', ENT_QUOTES) . '">
</head>
<body>
<div class="container">
' . $this->renderTabs('index', $inSubDir ? '../' : '') . '
<h1>Index</h1>
<p class="muted">Hotspot navigation by directory and file.</p>
<p class="breadcrumbs">' . $crumbs . '</p>
<table>
<thead><tr><th>Name</th><th>Type</th><th>Classes</th><th>Methods</th><th>Min</th><th>Avg</th><th>Median</th><th>Max</th><th>Risk</th></tr></thead>
<tbody>' . $rows . '</tbody>
</table>
<p class="muted">Risk metric = 50% median + 35% average + 15% max (normalized by global P95 class score).</p>
</div>
</body>
</html>';
    }

    /**
     * @param array{name:string, path:string, directory:string, classes:list<ClassHotspot>, stats:array<string, float|int>} $fileData
     * @param array{classDeltas: array<string, float>, methodDeltas: array<string, float>}|null $comparison
     */
    private function renderFilePage(string $filePath, array $fileData, ?array $comparison): string
    {
        $rows = '';
        foreach ($fileData['classes'] as $classHotspot) {
            $deltaText = '';
            if ($comparison !== null) {
                $deltaText = $this->formatDelta($comparison['classDeltas'][$this->classKey($classHotspot->file, $classHotspot->className)] ?? 0.0);
            }
            $rows .= sprintf(
                '<tr><td><a href="../classes/%s.html">%s</a></td><td>%s</td><td>%s</td><td>%d</td></tr>',
                htmlspecialchars($this->classFileName($classHotspot), ENT_QUOTES),
                htmlspecialchars($classHotspot->className, ENT_QUOTES),
                $this->formatScore($classHotspot->totalScore),
                htmlspecialchars($deltaText, ENT_QUOTES),
                count($classHotspot->methods),
            );
        }

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>File - ' . htmlspecialchars($filePath, ENT_QUOTES) . '</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="container">
' . $this->renderTabs('index', '../') . '
<h1>File: ' . htmlspecialchars($filePath, ENT_QUOTES) . '</h1>
<p class="muted"><a href="../' . htmlspecialchars($this->directoryLink((string) $fileData['directory']), ENT_QUOTES) . '">Back to directory</a></p>
<table>
<thead><tr><th>Class</th><th>Score</th><th>Delta</th><th>Methods</th></tr></thead>
<tbody>' . $rows . '</tbody>
</table>
</div>
</body>
</html>';
    }

    /**
     * @param array{classDeltas: array<string, float>, methodDeltas: array<string, float>}|null $comparison
     */
    private function renderClassDetail(ClassHotspot $classHotspot, ?array $comparison): string
    {
        $source = @file($classHotspot->file, FILE_IGNORE_NEW_LINES);
        if (!is_array($source)) {
            $source = [];
        }

        $methodTables = '';
        foreach ($classHotspot->methods as $methodHotspot) {
            $methodDelta = null;
            if ($comparison !== null) {
                $methodDelta = $comparison['methodDeltas'][$this->methodKey($methodHotspot->file, $methodHotspot->className, $methodHotspot->methodName)] ?? 0.0;
            }
            $methodTables .= $this->renderMethodBlock($methodHotspot, $methodDelta);
        }

        /** @var array<int, float> $lineScores */
        $lineScores = [];
        foreach ($classHotspot->methods as $methodHotspot) {
            foreach ($methodHotspot->lineScores as $line => $score) {
                $lineScores[$line] = ($lineScores[$line] ?? 0.0) + $score;
            }
        }

        $maxLineScore = $lineScores !== [] ? max($lineScores) : 0.0;
        $hotRanges = $this->buildHotspotRanges($lineScores);
        $hotRangeLines = [];
        foreach ($hotRanges as $range) {
            for ($line = $range['start']; $line <= $range['end']; $line++) {
                $hotRangeLines[$line] = true;
            }
        }

        $codeRows = '';
        foreach ($source as $lineNo => $lineContent) {
            $actualLine = $lineNo + 1;
            $lineScore = $lineScores[$actualLine] ?? 0.0;
            $severity = $maxLineScore > 0.0 ? min(1.0, $lineScore / $maxLineScore) : 0.0;
            $alpha = (string) number_format($severity, 2, '.', '');
            $rowClass = isset($hotRangeLines[$actualLine]) ? ' class="hotspot-line"' : '';

            $codeRows .= sprintf(
                '<tr%s style="background-color: rgba(255, 84, 84, %s)"><td class="line">%d</td><td class="score">%s</td><td><pre>%s</pre></td></tr>',
                $rowClass,
                $alpha,
                $actualLine,
                $lineScore > 0 ? $this->formatScore($lineScore) : '',
                htmlspecialchars($lineContent, ENT_QUOTES),
            );
        }

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>' . htmlspecialchars($classHotspot->className, ENT_QUOTES) . '</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="container">
' . $this->renderTabs('dashboard', '../') . '
<p><a href="../index.html">Back to dashboard</a></p>
<h1>' . htmlspecialchars($classHotspot->className, ENT_QUOTES) . '</h1>
<p class="muted">File: ' . htmlspecialchars($classHotspot->file, ENT_QUOTES) . '</p>
<p><strong>Total score:</strong> ' . $this->formatScore($classHotspot->totalScore) . '</p>
' . $methodTables . '
<h2>Line Heatmap</h2>
<table class="code">
<thead><tr><th>Line</th><th>Score</th><th>Code</th></tr></thead>
<tbody>' . $codeRows . '</tbody>
</table>
</div>
</body>
</html>';
    }

    private function renderMethodBlock(MethodHotspot $methodHotspot, ?float $delta = null): string
    {
        $weightedTotal = 0.0;
        $totalFinal = 0.0;

        $ranges = $this->buildHotspotRanges($methodHotspot->lineScores);
        $rangeRows = '';
        foreach ($ranges as $range) {
            $rangeRows .= sprintf(
                '<tr><td>%d-%d</td><td>%s</td><td>%s</td></tr>',
                $range['start'],
                $range['end'],
                $this->formatScore($range['score']),
                $this->formatScore($range['peak']),
            );
        }
        $rangeTable = $rangeRows !== ''
            ? '<h3>Hotspot ranges</h3><table><thead><tr><th>Lines</th><th>Range score</th><th>Peak line score</th></tr></thead><tbody>' . $rangeRows . '</tbody></table>'
            : '';

        $rows = '';
        foreach ($methodHotspot->contributions as $contribution) {
            $weighted = $contribution->finalCost * $contribution->confidence;
            $weightedTotal += $weighted;
            $totalFinal += $contribution->finalCost;

            $rows .= sprintf(
                '<tr><td>%d-%d</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%.2f</td><td>%s</td></tr>',
                $contribution->startLine,
                $contribution->endLine,
                htmlspecialchars($contribution->reason, ENT_QUOTES),
                $this->formatScore($contribution->baseCost),
                $contribution->multiplier,
                $this->formatScore($contribution->finalCost),
                $contribution->confidence,
                $this->formatScore($weighted),
            );
        }

        $deltaText = $delta !== null ? ' | delta ' . $this->formatDelta($delta) : '';
        $averageConfidence = $totalFinal > 0.0 ? $weightedTotal / $totalFinal : 0.0;

        return '<section class="method">
<h2>' . htmlspecialchars($methodHotspot->methodName, ENT_QUOTES) . ' <small>(' . $this->formatScore($methodHotspot->totalScore) . $deltaText . ')</small></h2>
<p class="muted">Confidence-weighted score: ' . $this->formatScore($weightedTotal) . ' (avg confidence ' . number_format($averageConfidence, 2, '.', '') . ')</p>
' . $rangeTable . '
<table>
<thead><tr><th>Lines</th><th>Reason</th><th>Base</th><th>Multiplier</th><th>Final</th><th>Confidence</th><th>Weighted</th></tr></thead>
<tbody>' . $rows . '</tbody>
</table>
</section>';
    }

    private function renderTabs(string $active, string $prefix): string
    {
        $dashboardClass = $active === 'dashboard' ? 'active' : '';
        $indexClass = $active === 'index' ? 'active' : '';

        return '<div class="tabs">'
            . '<a class="tab ' . $dashboardClass . '" href="' . htmlspecialchars($prefix . 'index.html', ENT_QUOTES) . '">Dashboard</a>'
            . '<a class="tab ' . $indexClass . '" href="' . htmlspecialchars($prefix . 'index-tree.html', ENT_QUOTES) . '">Index</a>'
            . '</div>';
    }

    /**
     * @param array{root:string, directories:array<string, array{name:string, parent:string, path:string, children:list<string>, files:list<string>, stats:array<string, float|int>}>, files:array<string, array{name:string, path:string, directory:string, classes:list<ClassHotspot>, stats:array<string, float|int>}>} $indexData
     */
    private function renderDirectoryBreadcrumbs(string $directoryPath, array $indexData, string $prefix): string
    {
        if ($directoryPath === '') {
            return htmlspecialchars($indexData['root'], ENT_QUOTES);
        }

        $parts = explode('/', $directoryPath);
        $crumbs = ['<a href="' . htmlspecialchars($prefix . 'index-tree.html', ENT_QUOTES) . '">' . htmlspecialchars($indexData['root'], ENT_QUOTES) . '</a>'];
        $acc = '';
        foreach ($parts as $part) {
            $acc = $acc === '' ? $part : ($acc . '/' . $part);
            $crumbs[] = '<a href="' . htmlspecialchars($prefix . 'dirs/' . $this->pathSlug($acc) . '.html', ENT_QUOTES) . '">' . htmlspecialchars($part, ENT_QUOTES) . '</a>';
        }

        return implode(' / ', $crumbs);
    }

    /**
     * @param array<int, float> $lineScores
     * @return list<array{start:int,end:int,score:float,peak:float}>
     */
    private function buildHotspotRanges(array $lineScores): array
    {
        if ($lineScores === []) {
            return [];
        }

        ksort($lineScores);
        $maxLineScore = max($lineScores);
        if ($maxLineScore <= 0.0) {
            return [];
        }

        $threshold = $maxLineScore * 0.25;
        $ranges = [];
        $currentStart = null;
        $currentEnd = null;
        $currentScore = 0.0;
        $currentPeak = 0.0;

        foreach ($lineScores as $line => $score) {
            $isHot = $score >= $threshold;
            if (!$isHot) {
                if ($currentStart !== null && $currentEnd !== null) {
                    $ranges[] = [
                        'start' => $currentStart,
                        'end' => $currentEnd,
                        'score' => $currentScore,
                        'peak' => $currentPeak,
                    ];
                }
                $currentStart = null;
                $currentEnd = null;
                $currentScore = 0.0;
                $currentPeak = 0.0;
                continue;
            }

            if ($currentStart === null) {
                $currentStart = $line;
                $currentEnd = $line;
                $currentScore = $score;
                $currentPeak = $score;
                continue;
            }

            if ($line === $currentEnd + 1) {
                $currentEnd = $line;
                $currentScore += $score;
                $currentPeak = max($currentPeak, $score);
                continue;
            }

            $ranges[] = [
                'start' => $currentStart,
                'end' => $currentEnd,
                'score' => $currentScore,
                'peak' => $currentPeak,
            ];
            $currentStart = $line;
            $currentEnd = $line;
            $currentScore = $score;
            $currentPeak = $score;
        }

        if ($currentStart !== null && $currentEnd !== null) {
            $ranges[] = [
                'start' => $currentStart,
                'end' => $currentEnd,
                'score' => $currentScore,
                'peak' => $currentPeak,
            ];
        }

        usort($ranges, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($ranges, 0, 12);
    }

    /**
     * @return array{root:string, directories:array<string, array{name:string, parent:string, path:string, children:list<string>, files:list<string>, stats:array<string, float|int>}>, files:array<string, array{name:string, path:string, directory:string, classes:list<ClassHotspot>, stats:array<string, float|int>}>}
     */
    private function buildIndexData(AnalysisResult $result): array
    {
        $allClassScores = [];
        foreach ($result->classes as $classHotspot) {
            $allClassScores[] = $classHotspot->totalScore;
        }

        $globalP95 = $this->percentile($allClassScores, 95.0);
        if ($globalP95 <= 0.0) {
            $globalP95 = max($allClassScores ?: [1.0]);
        }

        $fileClasses = [];
        foreach ($result->classes as $classHotspot) {
            $path = $this->normalizePath($classHotspot->file);
            $fileClasses[$path][] = $classHotspot;
        }

        $root = $this->computeCommonRoot(array_keys($fileClasses));
        $directories = [
            '' => ['name' => $root !== '' ? $root : '.', 'parent' => '', 'path' => '', 'children' => [], 'files' => [], 'stats' => []],
        ];
        $files = [];

        foreach ($fileClasses as $filePath => $classes) {
            $relativeFile = $this->relativeToRoot($filePath, $root);
            $directory = dirname($relativeFile);
            if ($directory === '.') {
                $directory = '';
            }

            $parts = $directory === '' ? [] : explode('/', $directory);
            $acc = '';
            foreach ($parts as $part) {
                $parent = $acc;
                $acc = $acc === '' ? $part : ($acc . '/' . $part);
                if (!isset($directories[$acc])) {
                    $directories[$acc] = [
                        'name' => $part,
                        'parent' => $parent,
                        'path' => $acc,
                        'children' => [],
                        'files' => [],
                        'stats' => [],
                    ];
                }
                if (!in_array($acc, $directories[$parent]['children'], true)) {
                    $directories[$parent]['children'][] = $acc;
                }
            }

            if (!isset($directories[$directory])) {
                $directories[$directory] = [
                    'name' => $directory,
                    'parent' => '',
                    'path' => $directory,
                    'children' => [],
                    'files' => [],
                    'stats' => [],
                ];
            }

            $directories[$directory]['files'][] = $relativeFile;

            $classScores = array_map(static fn (ClassHotspot $classHotspot): float => $classHotspot->totalScore, $classes);
            $methodCount = array_sum(array_map(static fn (ClassHotspot $classHotspot): int => count($classHotspot->methods), $classes));
            $stats = $this->computeStats($classScores, $methodCount, $globalP95);

            $files[$relativeFile] = [
                'name' => basename($relativeFile),
                'path' => $relativeFile,
                'directory' => $directory,
                'classes' => $classes,
                'stats' => $stats,
            ];
        }

        foreach ($directories as $dirPath => &$directoryData) {
            sort($directoryData['children']);
            sort($directoryData['files']);

            $scores = [];
            $methodCount = 0;
            foreach ($files as $filePath => $fileData) {
                if ($dirPath !== '' && !str_starts_with($filePath, $dirPath . '/')) {
                    continue;
                }
                if ($dirPath === '' || str_starts_with($filePath, $dirPath)) {
                    foreach ($fileData['classes'] as $classHotspot) {
                        $scores[] = $classHotspot->totalScore;
                        $methodCount += count($classHotspot->methods);
                    }
                }
            }
            $directoryData['stats'] = $this->computeStats($scores, $methodCount, $globalP95);
        }
        unset($directoryData);

        ksort($directories);
        ksort($files);

        return ['root' => $root !== '' ? $root : '.', 'directories' => $directories, 'files' => $files];
    }

    /**
     * @param list<float> $scores
     * @return array{classes:int,methods:int,min:float,avg:float,median:float,max:float,risk:float}
     */
    private function computeStats(array $scores, int $methodCount, float $globalP95): array
    {
        if ($scores === []) {
            return ['classes' => 0, 'methods' => $methodCount, 'min' => 0.0, 'avg' => 0.0, 'median' => 0.0, 'max' => 0.0, 'risk' => 0.0];
        }

        sort($scores);
        $min = $scores[0];
        $max = $scores[count($scores) - 1];
        $avg = array_sum($scores) / count($scores);
        $median = $this->median($scores);

        $normalize = static function (float $value) use ($globalP95): float {
            $base = $globalP95 > 0.0 ? $globalP95 : 1.0;
            return min(1.0, $value / $base);
        };
        $risk = (0.50 * $normalize($median)) + (0.35 * $normalize($avg)) + (0.15 * $normalize($max));

        return [
            'classes' => count($scores),
            'methods' => $methodCount,
            'min' => $min,
            'avg' => $avg,
            'median' => $median,
            'max' => $max,
            'risk' => min(1.0, max(0.0, $risk)),
        ];
    }

    /**
     * @param list<float> $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);
        if ($count % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2;
        }
        return $values[$mid];
    }

    /**
     * @param list<float> $values
     */
    private function percentile(array $values, float $percent): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $index = (($percent / 100.0) * (count($values) - 1));
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        if ($lower === $upper) {
            return $values[$lower];
        }
        $weight = $index - $lower;
        return $values[$lower] + ($values[$upper] - $values[$lower]) * $weight;
    }

    /**
     * @param list<string> $paths
     */
    private function computeCommonRoot(array $paths): string
    {
        if ($paths === []) {
            return '';
        }

        $segments = explode('/', trim($paths[0], '/'));
        foreach (array_slice($paths, 1) as $path) {
            $pathSegments = explode('/', trim($path, '/'));
            $limit = min(count($segments), count($pathSegments));
            $i = 0;
            while ($i < $limit && $segments[$i] === $pathSegments[$i]) {
                $i++;
            }
            $segments = array_slice($segments, 0, $i);
            if ($segments === []) {
                break;
            }
        }

        return implode('/', $segments);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return trim($path, '/');
    }

    private function relativeToRoot(string $path, string $root): string
    {
        if ($root === '') {
            return $path;
        }

        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }

    private function riskClass(float $risk): string
    {
        if ($risk >= 0.75) {
            return 'high';
        }
        if ($risk >= 0.50) {
            return 'medium';
        }
        if ($risk >= 0.30) {
            return 'low';
        }

        return 'ok';
    }

    private function formatRisk(float $risk): string
    {
        return number_format($risk * 100, 1, '.', '') . '%';
    }

    private function classFileName(ClassHotspot $classHotspot): string
    {
        return preg_replace('/[^a-z0-9\-_]+/i', '-', $classHotspot->className) . '-' . substr(md5($classHotspot->file . $classHotspot->className), 0, 8);
    }

    private function formatScore(float $score): string
    {
        return number_format($score, 6, '.', '');
    }

    private function formatDelta(float $delta): string
    {
        return sprintf('%+.6f', $delta);
    }

    private function classKey(string $file, string $className): string
    {
        return $file . '|' . $className;
    }

    private function methodKey(string $file, string $className, string $methodName): string
    {
        return $file . '|' . $className . '|' . $methodName;
    }

    private function directoryLink(string $directory): string
    {
        if ($directory === '') {
            return 'index-tree.html';
        }

        return 'dirs/' . $this->pathSlug($directory) . '.html';
    }

    private function pathSlug(string $path): string
    {
        return substr(md5($path), 0, 12);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    private function styleCss(): string
    {
        return 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f6f8fb;color:#111;margin:0}
.container{max-width:1280px;margin:0 auto;padding:20px}
.tabs{display:flex;gap:8px;margin-bottom:18px}
.tab{display:inline-block;padding:8px 12px;border:1px solid #cfd6df;border-radius:6px;background:#eef3f8;color:#104a8e;text-decoration:none;font-weight:600}
.tab.active{background:#1f5fae;color:#fff;border-color:#1f5fae}
a{color:#0057b8;text-decoration:none}
a:hover{text-decoration:underline}
.muted{color:#666}
.breadcrumbs{font-size:14px;margin:8px 0 14px}
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0}
.card{background:#fff;border:1px solid #dde3eb;border-radius:8px;padding:12px}
.card span{display:block;color:#666;font-size:12px}
.card strong{font-size:24px}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #dde3eb;margin-bottom:20px}
th,td{border-bottom:1px solid #eef2f6;padding:8px 10px;text-align:left;vertical-align:top}
th{background:#eef4fb}
tr.risk-high{background:#fbe1e1}
tr.risk-medium{background:#fdf0d8}
tr.risk-low{background:#fff8d9}
tr.risk-ok{background:#edf7ec}
pre{margin:0;white-space:pre-wrap;word-break:break-word}
.code td.line{width:60px;color:#666}
.code td.score{width:120px;font-variant-numeric:tabular-nums}
.hotspot-line td.line{font-weight:700;color:#b80000}
.method h2{margin-bottom:8px}
h3{margin:8px 0}
small{color:#666;font-weight:normal}
@media (max-width:900px){.cards{grid-template-columns:repeat(2,1fr)}}';
    }
}
