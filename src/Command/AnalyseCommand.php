<?php

namespace Sphpera\Command;

use Sphpera\ScoreResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class AnalyseCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('analyse')
            ->addArgument('dirs', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'List of dirs to analyse')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = [
            'default' => 0.0001,
            'functions' => [],
            'methods' => [],
        ];

        $scoreResolver = new ScoreResolver($config);

        $dirs = $input->getArgument('dirs');
        $slowest = 0;
        $slowestName = '';
        foreach (Finder::create()->in($dirs)->name('*.php') as $path) {
            $path = (string)$path;
            $scores = $scoreResolver->resolve($path);
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
