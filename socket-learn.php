<?php

class SocketService
{
    public $host = "tcp://127.0.0.1:9000";
    private $address;
    private $port;
    private $_sockets;
    public $clients;
    public $handShakedClients;
    public $maxid = 1000;
    public function __construct($address = '', $port = '')
    {
        if (!empty($address)) {
            $this->address = $address;
        }
        if (!empty($port)) {
            $this->port = $port;
        }
    }

    public function onConnect($client_id)
    {
        echo "Client client_id:{$client_id}   \n";

    }

    public function onMessage($client_id, $msg)
    {
        //发给所有的
        $this->send($this->clients[$client_id], $msg);
    }

    public function onClose($client_id)
    {
        echo "$client_id close \n";
    }

    public function service()
    {
        //获取tcp协议号码。
        $tcp  = getprotobyname("tcp");
        $sock = stream_socket_server($this->host, $errno, $errstr);

        if (!$sock) {
            throw new Exception("failed to create socket: " . socket_strerror($sock) . "\n");
        }
        stream_set_blocking($sock, 0);
        $this->_sockets = $sock;
        echo "listen on $this->address $this->host ... \n";
    }

    public function run()
    {
        $this->service();
        $this->clients[] = $this->_sockets;
        while (true) {
            $changes = $this->clients;
            stream_select($changes, $write, $except, null);
            echo "\nclients\n";
            var_dump($this->clients);
            echo "\nthis_sockets\n";
            var_dump($this->_sockets);
            echo "\nchanges\n";
            var_dump($changes);
            echo "\n\n";
            foreach ($changes as $key => $_sock) {
                if ($this->_sockets == $_sock) { //判断是不是新接入的socket
                    echo "\ncreate_client\n";
                    echo "\n\n";
                    if (($newClient = stream_socket_accept($_sock)) === false) {
                        unset($this->clients[$key]);
                        continue;
                    }
                    $this->maxid++;
                    $this->clients[$this->maxid] = $newClient;
                    $this->onConnect($this->maxid);
                } else {
                    if (!isset($this->handShakedClients[$key])) {
                        $line = trim(stream_socket_recvfrom($newClient, 1024));
                        //握手
                        $this->handshaking($key, $line);
                        $this->handShakedClients[$key] = true;
                        continue;
                    }
                    $res = @stream_socket_recvfrom($_sock, 2048);
                    echo "\nres\n";
                    var_export($res);
                    echo "\n\n";
                    var_export(strlen($res));
                    echo "\n\n";
                    //客户端主动关闭
                    if (strlen($res) < 9) {
                        stream_socket_shutdown($this->clients[$key], STREAM_SHUT_RDWR);
                        unset($this->clients[$key]);
                        $this->onClose($key);
                    } else {
                        //解密
                        $msg = $this->decode($res);
                        foreach ($this->clients as $clientKey => $_) {
                            if ($clientKey > 0 && $clientKey != $key) {
                                echo "\ncompare_key\n";
                                var_export($clientKey . "   " . $key);
                                echo "\n\n";
                                $this->onMessage($clientKey, $msg);
                            }
                        }
                    }

                }
            }
        }
    }

    /**
     * 握手处理
     * @param $newClient socket
     * @return int  接收到的信息
     */
    public function handshaking($clientKey, $line)
    {
        $headers = array();
        $lines   = preg_split("/\r\n/", $line);
        foreach ($lines as $line) {
            $line = chop($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }
        echo "\nheaders\n";
        var_export($headers);
        echo "\n\n";
        $secKey    = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade   = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $this->address\r\n" .
            "WebSocket-Location: ws://$this->address:$this->port/websocket/websocket\r\n" .
            "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        return stream_socket_sendto($this->clients[$clientKey], $upgrade);
    }

    /**
     * 发送数据
     * @param $newClinet 新接入的socket
     * @param $msg   要发送的数据
     * @return int|string
     */
    public function send($newClinet, $msg)
    {
        $msg = $this->encode($msg);
        stream_socket_sendto($newClinet, $msg);
    }
    /**
     * 解析接收数据
     * @param $buffer
     * @return null|string
     */
    public function decode($buffer)
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
    public function encode($buffer)
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

    /**
     * 关闭socket
     */
    public function close()
    {
        return socket_close($this->_sockets);
    }
}

$sock = new SocketService('127.0.0.1', '9000');
$sock->run();
