<?php

require_once __DIR__ . "/MyChat.php";

$app = new Ratchet\App('localhost', 8010);
$app->route('/chat', new MyChat);
$app->route('/echo', new Ratchet\Server\EchoServer, array('*'));
$app->run();
