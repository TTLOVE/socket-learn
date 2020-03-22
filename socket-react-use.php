<?php

require 'vendor/autoload.php';

// use React\Socket\ConnectionInterface;

// $loop   = React\EventLoop\Factory::create();
// $socket = new React\Socket\Server('127.0.0.1:9000', $loop);

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
 * 解析接收数据
 * @param $buffer
 * @return null|string
 */
function decode($buffer)
{
    $len = $masks = $data = $decoded = null;
    $len = ord($buffer[1]) & 127;
    if ($len === 126) {
        $masks = substr($buffer, 4, 4);
        $data  = substr($buffer, 8);
    } else if ($len === 127) {
        $masks = substr($buffer, 10, 4);
        $data  = substr($buffer, 14);
    } else {
        $masks = substr($buffer, 2, 4);
        $data  = substr($buffer, 6);
    }
    for ($index = 0; $index < strlen($data); $index++) {
        $decoded .= $data[$index] ^ $masks[$index % 4];
    }
    return $decoded;
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

use React\Socket\ConnectionInterface;

class ConnectionsPool
{
    /** @var SplObjectStorage  */
    private $connections;

    public $userId = 1000;

    public function __construct()
    {
        $this->connections = new SplObjectStorage();
    }

    public function add(ConnectionInterface $connection)
    {
        // $connection->write("Enter your name: ");
        $this->initEvents($connection);
        $this->setConnectionData($connection, []);
    }

    /**
     * @param ConnectionInterface $connection
     */
    private function initEvents(ConnectionInterface $connection)
    {
        // On receiving the data we loop through other connections
        // from the pool and write this data to them
        $connection->on('data', function ($data) use ($connection) {
            $connectionData = $this->getConnectionData($connection);

            // It is the first data received, so we consider it as
            // a user's name.
            if (empty($connectionData)) {
                $this->addNewMember($data, $this->userId, $connection);
                return;
            }

            // $name = $connectionData['name'];
            $data = encode(decode($data));
            $this->sendAll($data, $connection);
            // $this->sendAll("$name: $data", $connection);
        });

        // When connection closes detach it from the pool
        $connection->on('close', function () use ($connection) {
            $data = $this->getConnectionData($connection);
            $name = $data['name'] ?? '';

            $this->connections->offsetUnset($connection);
            $this->sendAll("User $name leaves the chat\n", $connection);
        });
    }

    private function addNewMember($data, $name, $connection)
    {
        $name = str_replace(["\n", "\r"], "", $name);
        $this->setConnectionData($connection, ['name' => $name]);
        $data = handshaking($data);
        $connection->write($data);
    }

    private function setConnectionData(ConnectionInterface $connection, $data)
    {
        $this->connections->offsetSet($connection, $data);
    }

    private function getConnectionData(ConnectionInterface $connection)
    {
        return $this->connections->offsetGet($connection);
    }

    /**
     * Send data to all connections from the pool except
     * the specified one.
     *
     * @param mixed $data
     * @param ConnectionInterface $except
     */
    private function sendAll($data, ConnectionInterface $except)
    {
        foreach ($this->connections as $conn) {
            if ($conn != $except) {
                $conn->write($data);
            }

        }
    }
}

$loop   = React\EventLoop\Factory::create();
$socket = new React\Socket\Server('127.0.0.1:9000', $loop);
$pool   = new ConnectionsPool();

$socket->on('connection', function (ConnectionInterface $connection) use ($pool) {
    $pool->add($connection);
});

echo "Listening on {$socket->getAddress()}\n";

$loop->run();
