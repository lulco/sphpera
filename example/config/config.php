<?php

use GuzzleHttp\Client;

return [
    'default' => 0.0001,
    'functions' => [
        'curl_exec' => 10,
        'file_*' => 1,
    ],
    'methods' => [
        Client::class => [
            'request' => 10,
        ],
    ],
];
