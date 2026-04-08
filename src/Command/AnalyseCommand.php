<?php

namespace Sphpera\Command;

use InvalidArgumentException;
use RuntimeException;
use Sphpera\Aggregation\BaselineComparator;
use Sphpera\Aggregation\ResultAggregator;
use Sphpera\Analysis\Analyzer;
use Sphpera\Analysis\AstParser;
use Sphpera\Analysis\FileScanner;
use Sphpera\Config\Config;
use Sphpera\Model\AnalysisResult;
use Sphpera\Report\Html\HtmlReportBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('analyse')
            ->addArgument('dirs', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'List of dirs to analyse')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to custom config file')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text|json|html', 'text')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output path for json or output directory for html', 'build/sphpera-report')
            ->addOption('top', null, InputOption::VALUE_REQUIRED, 'Top method count for text output', '20')
            ->addOption('compare-baseline', null, InputOption::VALUE_REQUIRED, 'Path to JSON baseline for delta comparison')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = [];
        /** @var string|null $configPath */
        $configPath = $input->getOption('config');
        if ($configPath) {
            if (!file_exists($configPath)) {
                throw new InvalidArgumentException('File "' . $configPath . '" not found');
            }
            $configuration = require $configPath;
        }

        if (!is_array($configuration)) {
            throw new InvalidArgumentException('Configuration is not array');
        }

        $config = new Config($configuration);
        $format = (string) $input->getOption('format');
        if (!in_array($format, ['text', 'json', 'html'], true)) {
            throw new InvalidArgumentException('Unsupported format "' . $format . '"');
        }

        /** @var string[] $dirs */
        $dirs = $input->getArgument('dirs');
        $analyzer = new Analyzer(new FileScanner(), new AstParser(), new ResultAggregator());
        $result = $analyzer->analyze($dirs, $config, getcwd());
        $comparison = $this->loadComparison($result, (string) ($input->getOption('compare-baseline') ?? ''));

        if ($format === 'json') {
            $outputPath = (string) $input->getOption('output');
            $payload = ['result' => $result->toArray()];
            if ($comparison !== null) {
                $payload['comparison'] = $comparison;
            }
            $json = (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (str_ends_with($outputPath, '.json')) {
                $directory = dirname($outputPath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }
                file_put_contents($outputPath, $json);
                $output->writeln('JSON report generated in "' . $outputPath . '"');
                return 0;
            }

            $output->writeln($json);
            return 0;
        }

        if ($format === 'html') {
            $outputDir = (string) $input->getOption('output');
            (new HtmlReportBuilder())->generate($result, $outputDir, $comparison);
            $output->writeln('HTML report generated in "' . $outputDir . '/index.html"');
            return 0;
        }

        $top = max(1, (int) $input->getOption('top'));
        $output->writeln('Sphpera Top ' . $top . ' Methods');
        $output->writeln(str_repeat('=', 80));
        foreach (array_slice($result->methods, 0, $top) as $index => $method) {
            $deltaText = '';
            if ($comparison !== null) {
                $methodKey = $method->file . '|' . $method->className . '|' . $method->methodName;
                $delta = $comparison['methodDeltas'][$methodKey] ?? 0.0;
                $deltaText = sprintf(' | delta: %+.6f', $delta);
            }

            $output->writeln(sprintf(
                '%d. %s::%s | score: %.6f%s | %s',
                $index + 1,
                $method->className,
                $method->methodName,
                $method->totalScore,
                $deltaText,
                $method->file,
            ));
        }

        return 0;
    }

    /**
     * @return array{classDeltas: array<string, float>, methodDeltas: array<string, float>}|null
     */
    private function loadComparison(AnalysisResult $result, string $baselinePath): ?array
    {
        if ($baselinePath === '') {
            return null;
        }
        if (!is_file($baselinePath)) {
            throw new InvalidArgumentException('Baseline file "' . $baselinePath . '" not found');
        }

        $decoded = json_decode((string) file_get_contents($baselinePath), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Baseline file is not valid JSON object');
        }

        return (new BaselineComparator())->compare($result, $decoded);
    }
}
