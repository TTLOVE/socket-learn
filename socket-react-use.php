<?php

require 'vendor/autoload.php';

use React\Socket\ConnectionInterface;

$loop   = React\EventLoop\Factory::create();
$socket = new React\Socket\Server('127.0.0.1:9000', $loop);

function handleHeader($line)
{
    $headers = array();
    $lines   = preg_split("/\r\n/", $line);
    foreach ($lines as $line) {
        $line = chop($line);
        if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
            $headers[$matches[1]] = $matches[2];
        }
    }

    return $headers;
}
/**
 * 握手处理
 * @param $newClient socket
 * @return int  接收到的信息
 */
function handshaking($line)
{
    $headers   = handleHeader($line);
    $secKey    = $headers['Sec-WebSocket-Key'];
    $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    return "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "WebSocket-Origin: 127.0.0.1\r\n" .
        "WebSocket-Location: ws://127.0.0.1:9000/websocket/websocket\r\n" .
        "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
}

/**
 *打包消息
 **/
function encode($buffer)
{
    $first_byte = "\x81";
    $len        = strlen($buffer);
    if ($len <= 125) {
        $encode_buffer = $first_byte . chr($len) . $buffer;
    } else {
        if ($len <= 65535) {
            $encode_buffer = $first_byte . chr(126) . pack("n", $len) . $buffer;
        } else {
            $encode_buffer = $first_byte . chr(127) . pack("xxxxN", $len) . $buffer;
        }
    }
    return $encode_buffer;
}

$socket->on('connection', function (ConnectionInterface $connection) {
    echo $connection->getRemoteAddress() . PHP_EOL;
    // $connection->write('Hi');
    $connection->on('data', function ($data) use ($connection) {
        echo "\n\n";
        var_export($data);
        echo "\n\n";
        $headers = handleHeader($data);
        if (isset($headers['Sec-WebSocket-Key'])) {
            $data = handshaking($data);
        }
        echo "\n\n";
        var_export($data);
        echo "\n\n";
        $connection->write($data);
    });
});

echo "Listening on {$socket->getAddress()}\n";

$loop->run();
