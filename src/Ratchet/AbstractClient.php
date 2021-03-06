<?php

namespace Localhook\Localhook\Ratchet;

use Exception;
use Ratchet\Client\WebSocket;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AbstractClient
{
    /** @var WebSocket */
    protected $conn;

    /** @var SymfonyStyle */
    private $io;

    /** @var callable[] */
    protected $callbacks = [];

    /** @var callable[] */
    protected $errorCallbacks = [];

    /** @var string */
    private $url;

    /** @var array */
    protected $defaultFields = [];

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function setIo($io)
    {
        $this->io = $io;
    }

    public function start(callable $onConnect)
    {
        \Ratchet\Client\connect($this->url)->then(function ($conn) use ($onConnect) {
            $this->conn = $conn;
            $this->parseMessages();
            $onConnect();
        }, function (Exception $e) {
            $this->io->error('Error when trying to connect to the socket "' . $this->url . "\": {$e->getMessage()}\n");
            $this->stop();
        });
    }

    public function parseMessages()
    {
        $this->conn->on('message', function ($msg) {
            $this->verboseLog("MESSAGE RECEIVED: {$msg}", 'info');
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
            if (isset($this->callbacks[$comKey])) {
                $this->callbacks[$comKey]($msg);
            } else {
                throw new Exception(
                    'No callback function found for received response: ' .
                    json_encode($msg) . ' Registered callbacks comKeys was: ' .
                    implode(', ', array_keys($this->callbacks))
                );
            }
        } else {
            if (isset($this->errorCallbacks[$comKey])) {
                $this->errorCallbacks[$comKey]($msg);
            } else {
                $this->io->error('The server said: "' . $msg['message'] . '".');
                $this->stop();
            }
        }
    }

    protected function defaultExecute($type, array $msg, callable $onSuccess, callable $onError = null)
    {
        $comKey = rand(100000, 999999);
        $msg = json_encode(array_merge([
            'type'   => $type,
            'comKey' => $comKey,
        ], $this->defaultFields, $msg));
        $this->verboseLog("MESSAGE SENT: {$msg}", 'comment');
        $this->conn->send($msg);
        $this->callbacks[$comKey] = $onSuccess;
        if ($onError) {
            $this->errorCallbacks[$comKey] = $onError;
        }
    }

    /**
     * @param string $msg
     * @param        $color
     */
    protected function verboseLog($msg, $color)
    {
        if ($this->io && $this->io->getVerbosity() === OutputInterface::VERBOSITY_DEBUG) {
            $this->io->comment('<' . $color . '>' . date('[Y-m-d H:i:s]') . $msg . '</' . $color . '>');
        }
    }

    /**
     * @param string $msg
     */
    protected function text($msg)
    {
        if ($this->io) {
            $this->io->writeln(date('[Y-m-d H:i:s]') . $msg);
        }
    }

    /**
     * @param string $msg
     */
    protected function error($msg)
    {
        if ($this->io) {
            $this->io->error(date('[Y-m-d H:i:s]') . $msg);
        }
    }

    /**
     * @param string $msg
     */
    protected function warning($msg)
    {
        if ($this->io) {
            $this->io->warning(date('[Y-m-d H:i:s]') . $msg);
        }
    }
}
