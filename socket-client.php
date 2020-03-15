<?php

require __DIR__ . '/vendor/autoload.php';

use React\Socket\Connector;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

$host = isset($argv[1]) ? $argv[1] : '127.0.0.1';

$loop      = Factory::create();
$connector = new Connector($loop);

$stdin  = new ReadableResourceStream(STDIN, $loop);
$stdout = new WritableResourceStream(STDOUT, $loop);

$connector
    ->connect('127.0.0.1:8080')
    ->then(
        function (ConnectionInterface $conn) use ($stdin, $stdout) {
            $conn->on('data', function ($data) use ($stdout) {
                $stdout->write($data);
            });

            $stdin->on('data', function ($data) use ($conn) {
                $conn->write($data);
            });
        },
        function (Exception $exception) use ($loop) {
            // reject
        });

$loop->run();

// $connector->connect($host. ':8080')->then(function (ConnectionInterface $connection) use ($host) {
//     $connection->on('data', function ($data) {
//         echo $data;
//     });
//     $connection->on('close', function () {
//         echo '[CLOSED]' . PHP_EOL;
//     });

//     $connection->write("GET / HTTP/1.0\r\nHost: $host\r\n\r\n");
// }, 'printf');

// $loop->run();
