<?php

namespace Sphpera\Config;

class Config
{
    private $configuration = [
        'default' => 0.0001,
        'functions' => [],
        'methods' => [],
    ];

    public function __construct(array $configuration)
    {
        $this->configuration = array_merge($this->configuration, $configuration);
    }

    public function getDefault(): float
    {
        return $this->configuration['default'];
    }

    public function getFunctions(): array
    {
        return $this->configuration['functions'];
    }

    public function getMethods(): array
    {
        return $this->configuration['methods'];
    }
}
