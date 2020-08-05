<?php

namespace Sphpera\Config;

class Config
{
    /** @var array<string, mixed> */
    private $configuration = [
        'default' => 0.0001,
        'functions' => [],
        'methods' => [],
    ];

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = array_merge($this->configuration, $configuration);
    }

    public function getDefault(): float
    {
        return $this->configuration['default'];
    }

    /**
     * @return array<string, float>
     */
    public function getFunctions(): array
    {
        return $this->configuration['functions'];
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function getMethods(): array
    {
        return $this->configuration['methods'];
    }
}
