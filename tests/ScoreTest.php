<?php

namespace SphperaTest;

use GuzzleHttp\Client;
use PDO;
use PHPUnit\Framework\TestCase;
use Sphpera\Config\Config;
use Sphpera\ScoreResolver;
use SphperaTest\Sample\Sample;

class ScoreTest extends TestCase
{
    public function testSample()
    {
        $config = new Config([
            'functions' => [
                'curl_exec' => 10,
                'file_*' => 1,
            ],
            'methods' => [
                Client::class => [
                    'request' => 10,
                ],
                PDO::class => [
                    'query' => 3,
                ],
            ],
        ]);

        $expectedScores = [
            Sample::class => [
                'stringManipulations' => 0.0003,
                'readFileWithFileGetContents' => 2.0,
                'callApiWithCurl' => 10.0004,
                'callApiWithGuzzle' => 10.0001,
                'pdoSelect' => 38.0,
                'doSomethingInForeach' => 0.01,
                'doSomethingInFor' => 0.0101,
                'doSomethingInForWithKnownNumberOfSteps' => 0.0005,
            ],
        ];

        $resolver = new ScoreResolver($config);
        $result = $resolver->resolve(__DIR__ . '/Sample/Sample.php');

        foreach ($expectedScores as $class => $methods) {
            foreach ($methods as $method => $expectedScore) {
                $this->assertArrayHasKey($class, $result);
                $this->assertArrayHasKey($method, $result[$class]);
                $this->assertEqualsWithDelta($expectedScore, $result[$class][$method], 0.0000001);
            }
        }
    }
}
