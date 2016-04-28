<?php

namespace Localhook\Localhook\Ratchet;

use Exception;
use Ratchet\Client\WebSocket;
use Symfony\Component\Console\Style\SymfonyStyle;

class AbstractClient
{
    /** @var WebSocket */
    protected $conn;

    /** @var SymfonyStyle */
    protected $io;

    /** @var callable[] */
    protected $callbacks = [];

    /** @var string */
    private $url;

    /** @var array */
    protected $defaultFields = [];

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function start(callable $onConnect)
    {
        \Ratchet\Client\connect($this->url)->then(function ($conn) use ($onConnect) {
            $this->conn = $conn;
            $this->parseMessages();
            $onConnect();
        }, function ($e) {
            throw new $e;
        });
    }

    public function parseMessages()
    {
        $this->conn->on('message', function ($msg) {
            $msg = json_decode($msg, true);
            $type = $msg['type'];
            unset($msg['type']);
            $comKey = $msg['comKey'];
            unset($msg['type']);
            $this->routeInputEvents($type, $msg, $comKey);
        });
    }

    public function routeInputEvents($type, $msg, $comKey)
    {
        throw new Exception('routeInputEvents method should be implemented.');
    }

    public function getConnexionId()
    {
        return $this->conn->resourceId;
    }

    public function stop()
    {
        $this->conn->close();
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    protected function defaultReceive($msg, $comKey)
    {
        if ($msg['status'] == 'ok') {
            $this->callbacks[$comKey]($msg);
        } else {
            throw new Exception('Error received: ' . json_encode($msg));
        }
    }

    protected function defaultExecute($type, array $msg, callable $onSuccess)
    {
        $comKey = rand(100000, 999999);
        $this->conn->send(json_encode(array_merge([
            'type'   => $type,
            'comKey' => $comKey,
        ], $this->defaultFields, $msg)));
        $this->callbacks[$comKey] = $onSuccess;
    }
}
