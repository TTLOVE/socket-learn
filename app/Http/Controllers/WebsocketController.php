<?php

namespace App\Http\Controllers;

use React\Socket\Connector;
use Illuminate\Http\Request;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

class WebsocketController extends Controller
{
    public function startWebsocket(Request $request)
    {
        return view('websocket');
    }

    public function newSocket()
    {
        return view('websocket-new');
    }
}
