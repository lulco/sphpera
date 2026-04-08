<?php

namespace Sphpera\Config;

class Config
{
    private const DEFAULT_DEFAULT_SCORE = 0.0001;

    /** @var array<string, float> */
    private const DEFAULT_FUNCTIONS = [
        'curl_exec' => 120.0,
        'curl_multi_exec' => 130.0,
        'fsockopen' => 110.0,
        'stream_socket_client' => 110.0,
        'file_get_contents' => 25.0,
        'file_put_contents' => 30.0,
        'fopen' => 20.0,
        'fread' => 18.0,
        'fwrite' => 22.0,
        'readfile' => 25.0,
        'scandir' => 8.0,
        'glob' => 8.0,
        'stat' => 6.0,
        'filesize' => 6.0,
        'exec' => 140.0,
        'shell_exec' => 140.0,
        'proc_open' => 150.0,
        'passthru' => 140.0,
        'preg_*' => 6.0,
        'json_encode' => 4.0,
        'json_decode' => 5.0,
        'serialize' => 5.0,
        'unserialize' => 15.0,
        'password_hash' => 30.0,
        'password_verify' => 20.0,
        'openssl_*' => 12.0,
    ];

    /** @var array<string, array<string, float>> */
    private const DEFAULT_METHODS = [
        'PDO' => [
            'query' => 90.0,
            'exec' => 85.0,
            'prepare' => 12.0,
            'beginTransaction' => 20.0,
            'commit' => 25.0,
            'rollBack' => 25.0,
        ],
        'PDOStatement' => [
            'execute' => 80.0,
            'fetch' => 20.0,
            'fetchAll' => 35.0,
            'rowCount' => 10.0,
        ],
        'Doctrine\DBAL\Connection' => [
            'executeQuery' => 85.0,
            'executeStatement' => 85.0,
            'fetchAssociative' => 20.0,
            'fetchAllAssociative' => 35.0,
        ],
        'GuzzleHttp\Client' => [
            'request' => 120.0,
            'get' => 120.0,
            'post' => 120.0,
            'put' => 120.0,
            'patch' => 120.0,
            'delete' => 120.0,
            'head' => 120.0,
            'options' => 120.0,
        ],
        'Symfony\Contracts\HttpClient\HttpClientInterface' => [
            'request' => 110.0,
        ],
        'Redis' => [
            'get' => 4.0,
            'set' => 5.0,
            'mget' => 8.0,
            'eval' => 40.0,
        ],
        'Elasticsearch\Client' => [
            'search' => 70.0,
            'index' => 65.0,
            'bulk' => 90.0,
        ],
    ];

    /** @var array<string, mixed> */
    private array $configuration = [
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
        return (float) $this->configuration['default'];
    }

    /**
     * @return array<string, float>
     */
    public function getFunctions(): array
    {
        /** @var array<string, float> $userFunctions */
        $userFunctions = is_array($this->configuration['functions']) ? $this->configuration['functions'] : [];
        return $this->mergeOrderedAssoc($userFunctions, self::DEFAULT_FUNCTIONS);
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function getMethods(): array
    {
        /** @var array<string, array<string, float>> $userMethods */
        $userMethods = is_array($this->configuration['methods']) ? $this->configuration['methods'] : [];
        /** @var array<string, array<string, float>> $merged */
        $merged = [];

        foreach ($userMethods as $classPattern => $methods) {
            $defaultsForClass = self::DEFAULT_METHODS[$classPattern] ?? [];
            $merged[$classPattern] = $this->mergeOrderedAssoc($methods, $defaultsForClass);
        }

        foreach (self::DEFAULT_METHODS as $classPattern => $methods) {
            if (!isset($merged[$classPattern])) {
                $merged[$classPattern] = $methods;
            }
        }

        return $merged;
    }

    public function getBuiltInDefault(): float
    {
        return self::DEFAULT_DEFAULT_SCORE;
    }

    /**
     * @template TValue
     * @param array<string, TValue> $priority
     * @param array<string, TValue> $fallback
     * @return array<string, TValue>
     */
    private function mergeOrderedAssoc(array $priority, array $fallback): array
    {
        $merged = $priority;
        foreach ($fallback as $key => $value) {
            if (!array_key_exists($key, $merged)) {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }
}
