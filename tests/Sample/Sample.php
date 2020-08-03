<?php

namespace SphperaTest\Sample;

use GuzzleHttp\Client;
use PDO;

class Sample
{
    public function stringManipulations(string $input): string
    {
        return lcfirst(strtoupper(str_replace('a', 'b', $input)));
    }

    public function readFileWithFileGetContents(string $filename): ?string
    {
        if (file_exists($filename)) {
            return file_get_contents($filename);
        }
        return null;
    }

    public function callApiWithCurl(): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://myexample.com');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

//    public function callApiWithGuzzle(): string
//    {
//        $client = new Client();
//        $response = $client->get('https://myexample.com');
//        return (string)$response->getBody();
//    }
//
//    public function pdoSelect(): array
//    {
//        $pdo = new PDO('');
//        return $pdo->query('SELECT * FROM tests WHERE is_deleted = 0')->fetchAll(PDO::FETCH_ASSOC);
//    }

    public function doSomethingInForeach(array $input): array
    {
        $data = [];
        foreach ($input as $key => $value) {
            $data[] = strtolower($value);
        }
        return $data;
    }

    public function doSomethingInFor(array $input): array
    {
        $data = [];
        $steps = count($input);
        for ($i = 0; $i < $steps; $i++) {
            $data[] = strtolower($input[$i]);
        }
        return $data;
    }

//    public function doSomethingInForWithKnownNumberOfSteps(array $input): array
//    {
//        $data = [];
//        for ($i = 0; $i < 5; $i++) {
//            $data[] = strtolower($input[$i]);
//        }
//        return $data;
//    }
}
