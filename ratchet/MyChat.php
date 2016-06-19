<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

// Make sure composer dependencies have been installed
require __DIR__ . '/vendor/autoload.php';

/**
 * chat.php
 * Send any incoming messages to all connected clients (except sender)
 */
class MyChat implements MessageComponentInterface {
    protected $clients;
    private $log;

    public function __construct() {
        $this->log = new Logger('chat');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/log/socket.log', Logger::INFO));
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $this->log->info("Chat Message : [resourceId : " . $from->resourceId . " , Message : " .  $msg . "]");

        //echo $msg;
        foreach ($this->clients as $client) {
            //if ($from != $client) {
                $this->log->info("Send [". $from->resourceId ."]");
                $client->send($msg);
            //}
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->log->info("Chat Close.");
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->log->info("Chat Error : [Error : ".$e->getMessage()."]");
        $conn->close();
    }
}