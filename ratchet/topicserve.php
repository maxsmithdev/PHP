<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Wamp\WampServer;
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as LoopFactory;

require __DIR__ . '/vendor/autoload.php';	
require_once __DIR__ . "/MyTopicChat.php";

// $collection  = new RouteCollection;
// $collection->add('pubsub', new Route('/demo', array(
// 	'_controller' => new MyTopicChat, 
// 	'allowedOrigins' => '*'
// )));

// $collection->add('chatRoom', new Route('/demo', array(
// 	'_controller' => new ChatRoom, 
// 	'allowedOrigins' => 'socketo.me'
// )));

// $collection->add('echo', new Route('/echo', array(
// 	'_controller' => new AbFuzzyServer, 
// 	'allowedOrigins' => '*'
// )));

$server = IoServer::factory(
    new HttpServer(
    	new WsServer(
			new WampServer(
				new MyTopicChat()
			)
		)
	), 8010);

$server->run();

// $server = IoServer::factory(
//     new HttpServer(
//         new WsServer(
//             new MyTopicChat()
//     )
// ), 8010);

// $server->run();

// $app = new Ratchet\App('localhost', 8010);
// $app->route('/pubsub', new MyTopicChat);
// //$app->route('/pubsubecho', new Ratchet\Server\EchoServer, array('*'));
// $app->run();


?>