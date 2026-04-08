<?php

declare(strict_types=1);

namespace Sphpera\Analysis;

use DateTimeImmutable;
use PhpParser\NodeTraverser;
use Sphpera\Aggregation\ResultAggregator;
use Sphpera\Analysis\Visitor\HotspotVisitor;
use Sphpera\Config\Config;
use Sphpera\Model\AnalysisResult;
use Sphpera\Scoring\RuleSet;
use Sphpera\Type\CompositeTypeResolver;
use Sphpera\Type\NullTypeResolver;
use Sphpera\Type\PhpStanTypeResolver;

final class Analyzer
{
    public function __construct(
        private readonly FileScanner $fileScanner,
        private readonly AstParser $astParser,
        private readonly ResultAggregator $resultAggregator,
    ) {
    }

    /**
     * @param list<string> $dirs
     */
    public function analyze(array $dirs, Config $config, string $projectRoot): AnalysisResult
    {
        $ruleSet = new RuleSet($config);
        $typeResolver = new CompositeTypeResolver(
            new PhpStanTypeResolver($projectRoot),
            new NullTypeResolver(),
        );

        $reportFiles = $this->fileScanner->scan($dirs);
        $analysisDirs = $dirs;
        $vendorDir = rtrim($projectRoot, '/\\') . '/vendor';
        if (is_dir($vendorDir) && !in_array('vendor', $analysisDirs, true) && !in_array($vendorDir, $analysisDirs, true)) {
            $analysisDirs[] = $vendorDir;
        }

        $analysisFiles = $this->fileScanner->scan($analysisDirs);
        $reportFileMap = array_fill_keys($reportFiles, true);
        $contributions = [];

        foreach ($analysisFiles as $file) {
            $ast = $this->astParser->parseFile($file);
            if ($ast === null) {
                continue;
            }

            $visitor = new HotspotVisitor($ruleSet, $typeResolver);
            $visitor->setFile($file);

            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            foreach ($visitor->getContributions() as $contribution) {
                if (isset($reportFileMap[$contribution->file])) {
                    $contributions[] = $contribution;
                }
            }
        }

        return $this->resultAggregator->aggregate($reportFiles, $contributions, new DateTimeImmutable());
    }
}
