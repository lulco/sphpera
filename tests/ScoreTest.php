<?php

namespace SphperaTest;

use GuzzleHttp\Client;
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
            ],
        ]);

        $expectedScores = [
            Sample::class => [
                'stringManipulations' => 0.0003,
                'readFileWithFileGetContents' => 2.0,
                'callApiWithCurl' => 10.0004,
//                'callApiWithGuzzle' => 10.0003,
//                'pdoSelect' => 3.0002,
                'doSomethingInForeach' => 0.01,
                'doSomethingInFor' => 0.0101,
//                'doSomethingInForWithKnownNumberOfSteps' => 0.0005,
            ],
        ];

        $resolver = new ScoreResolver($config);
        $result = $resolver->resolve(__DIR__ . '/Sample/Sample.php');

        $this->assertEquals($expectedScores, $result);
    }
}
