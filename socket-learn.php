<?php
// /* Open a server socket to port 1234 on localhost */
// $server = stream_socket_server('tcp://127.0.0.1:9000');

// /* Accept a connection */
// $socket = stream_socket_accept($server);

// /* Grab a packet (1500 is a typical MTU size) of OOB data */
// echo "Received Out-Of-Band: '" . stream_socket_recvfrom($socket, 1500, STREAM_OOB) . "'\n";

// /* Take a peek at the normal in-band data, but don't consume it. */
// echo "Data: '" . stream_socket_recvfrom($socket, 1500, STREAM_PEEK) . "'\n";

// /* Get the exact same packet again, but remove it from the buffer this time. */
// echo "Data: '" . stream_socket_recvfrom($socket, 1500) . "'\n";

// /* Close it up */
// fclose($socket);
// fclose($server);

// function handleShanke($line, $host, $port)
// {
//     $headers = array();
//     $lines   = preg_split("/\r\n/", $line);
//     foreach ($lines as $line) {
//         $line = chop($line);
//         if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
//             $headers[$matches[1]] = $matches[2];
//         }
//     }
//     $secKey    = $headers['Sec-WebSocket-Key'];
//     $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
//     $upgrade   = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
//         "Upgrade: websocket\r\n" .
//         "Connection: Upgrade\r\n" .
//         "WebSocket-Origin: $host\r\n" .
//         "WebSocket-Location: ws://$host:$port/websocket/websocket\r\n" .
//         "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
//     return $upgrade;
// }

// $host       = '127.0.0.1';
// $port       = '9000';
// $tcp_socket = stream_socket_server("tcp://{$host}:$port", $errno, $errstr);
// //直接创建一个监听tcp的套接字
// $tcp_socket || die("$errstr ($errno)\n");
// stream_set_blocking($tcp_socket, 0); //设置非阻塞
// $client_arr = [];
// while (1) {
//     set_error_handler(function () {}); //socket错误拦截
//     $connection = stream_socket_accept($tcp_socket, 0, $remote_address);
//     restore_error_handler();
//     if ($connection) { //如果有新链接,则处理新链接
//         echo $connection . "连接成功\n";
//         $client_arr[$connection] = $connection;
//         stream_set_blocking($connection, 0); //设置客户端非阻塞
//     }
//     //处理客户端
//     foreach ($client_arr as $client) {
//         $data = fread($client, 65535);
//         if ($data === '' || $data === false) {
//             if ((feof($client) || $buffer === false)) { //客户端关闭
//                 echo $client . "客户端关闭\n";
//                 unset($client_arr[$client]);
//             }
//         }
//         if (!empty($data)) {
//             $data = handleShanke($data, $host, $port);
//             fwrite($client, $data); //发送给客户端
//             echo $client . "客户端发送消息:" . $data . "\n";
//         } else {
//             continue;
//         }
//     }
//     usleep(1); //避免while true死循环卡死
// }

class SocketService
{
    public $host = "tcp://127.0.0.1:9000";
    private $address;
    private $port;
    private $_sockets;
    public $clients;
    public $maxid = 1000;
    public function __construct($address = '', $port = '')
    {
        if (!empty($address)) {
            $this->address = $address;
        }
        if (!empty($port)) {
            $this->port = $port;
        }
        // $this->host = "tcp://" . $this->address . ":" . $this->port;
    }

    public function onConnect($client_id)
    {
        echo "Client client_id:{$client_id}   \n";

    }

    public function onMessage($client_id, $msg)
    {
        //发给所有的
        foreach ($this->clients as $kk => $cc) {
            if ($kk > 0) {
                $this->send($cc, $msg);
            }
        }
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
        $client_arr = [];
        // $this->clients[] = $this->_sockets;
        while (true) {
            set_error_handler(function () {}); //socket错误拦截
            $connection = stream_socket_accept($this->_sockets, 0, $remote_address);
            restore_error_handler();
            if ($connection) { //如果有新链接,则处理新链接
                echo $connection . "连接成功\n";
                $client_arr[$connection] = $connection;
                stream_set_blocking($connection, 0); //设置客户端非阻塞
            }
            if (empty($client_arr)) {
                usleep(1);
                continue;
            }
            //处理客户端
            foreach ($client_arr as $client) {
                $data = trim(stream_socket_recvfrom($client, 1024));
                // $data = fread($client, 65535);
                if ($data === '' || $data === false) {
                    if ((feof($client) || $buffer === false)) { //客户端关闭
                        echo $client . "客户端关闭\n";
                        unset($client_arr[$client]);
                    }
                }
                if (!empty($data)) {
                    echo $client . "客户端发送消息:\n";
                    $this->handshaking($client, $data);
                } else {
                    continue;
                }
            }
            usleep(1); //避免while true死循环卡死
            // $changes = $this->clients;
            // //$write = NULL;
            // //$except = NULL;
            // // stream_select($changes, $write, $except, null);
            // foreach ($changes as $key => $_sock) {
            //     if ($this->_sockets == $_sock) { //判断是不是新接入的socket
            //         if (($newClient = stream_socket_accept($_sock)) === false) {
            //             unset($this->clients[$key]);
            //             continue;
            //         }
            //         $line = trim(stream_socket_recvfrom($newClient, 1024));
            //         if (empty($line)) {
            //             continue;
            //         }
            //         //握手
            //         $this->handshaking($newClient, $line);
            //         $this->maxid++;
            //         $this->clients[$this->maxid] = $newClient;
            //         $this->onConnect($this->maxid);
            //     } else {
            //         $res = @stream_socket_recvfrom($_sock, 2048);
            //         //客户端主动关闭
            //         if (strlen($res) < 9) {
            //             stream_socket_shutdown($this->clients[$key], STREAM_SHUT_RDWR);
            //             unset($this->clients[$key]);
            //             $this->onClose($key);
            //         } else {
            //             //解密
            //             $msg = $this->decode($res);
            //             $this->onMessage($key, $msg);
            //         }

            //     }
            // }
        }
    }

    /**
     * 握手处理
     * @param $newClient socket
     * @return int  接收到的信息
     */
    public function handshaking($newClient, $line)
    {

        $headers = array();
        $lines   = preg_split("/\r\n/", $line);
        foreach ($lines as $line) {
            $line = chop($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }
        $secKey    = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade   = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $this->address\r\n" .
            "WebSocket-Location: ws://$this->address:$this->port/websocket/websocket\r\n" .
            "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        return stream_socket_sendto($newClient, $upgrade);
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
