<?php

namespace Sphpera\Command;

use InvalidArgumentException;
use Sphpera\Config\Config;
use Sphpera\ScoreResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class AnalyseCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('analyse')
            ->addArgument('dirs', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'List of dirs to analyse')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to custom config file')
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
        $scoreResolver = new ScoreResolver($config);

        /** @var string[] $dirs */
        $dirs = $input->getArgument('dirs');
        $slowest = 0;
        $slowestName = '';
        foreach (Finder::create()->in($dirs)->name('*.php') as $path) {
            $path = (string)$path;
            $scores = $scoreResolver->resolve($path);

            print_R($scores);

            foreach ($scores as $class => $methods) {
                foreach ($methods as $method => $score) {
                    if ($slowest < $score) {
                        $slowest = $score;
                        $slowestName = $class . '::' . $method;
                    }
                }
            }
        }
        $output->writeln('Slowest method is "' . $slowestName . '" with score ' . $slowest);

        return 0;
    }
}
