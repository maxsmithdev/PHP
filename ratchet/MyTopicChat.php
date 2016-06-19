<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Ratchet\Wamp\WampServerInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
// Make sure composer dependencies have been installed
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/catfan/medoo/medoo.php';

class MyTopicChat implements WampServerInterface {
	
	protected $topicList = [];
	private $data = [];
	private $database = null;

	public function __construct(){
        $this->log = new Logger('chat');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/log/wamp.log', Logger::INFO));

        $this->database = new medoo([
        	'database_type' => 'mysql',
        	'database_name' => 'ratchet_chat',
        	'server' => 'localhost',
        	'username' => 'root',
        	'password' => '123123123',
        	'charset' => 'utf8'
        ]);
	}

	/**
     * An RPC call has been received
     * @param \Ratchet\ConnectionInterface $conn
     * @param string                       $id The unique ID of the RPC, required to respond to
     * @param string|Topic                 $topic The topic to execute the call against
     * @param array                        $params Call parameters received from the client
     */
    function onCall(ConnectionInterface $conn, $id, $topic, array $params){
    	$this->log->info("onCall");

    	if($params != null && is_array($params)){
            $date = new DateTime("NOW");
            $params = $params[0] != null ? $params[0] : $params;
            $players = $params['players'];
            $channelId = $params['channel_id'];

            $channel = $this->database->select("channel", ["id"], ["channel_id"=>$channelId]);
            if($channel == null){
                $currentDate = $date->format(DateTime::RSS);
                $this->database->insert("channel", [
                    "channel_id" => $channelId,
                    "player_id" => $players[0],
                    "created_at" => $currentDate
                ]);

                $this->database->insert("channel", [
                    "channel_id" => $channelId,
                    "player_id" => $players[1],
                    "created_at" => $currentDate
                ]);
            }
    	}

    	print_r($params);
    	//$topic->broadcast($params[0]);
    }

    /**
     * A request to subscribe to a topic has been made
     * @param \Ratchet\ConnectionInterface $conn
     * @param string|Topic                 $topic The topic to subscribe to
     */
    function onSubscribe(ConnectionInterface $conn, $topic){
		//$this->topicList[$topic->getId()] = $topic;
		$channel = $this->database->get("channel", ["id"], ["channel_id" => $topic->getId()]);
		if($channel == null){
			$this->log->info("onSubscribe : [Add new Channel : " . $topic->getId() . "]");
		}

		$this->log->info("onSubscribe : [Channel : " . $topic->getId() . "]");
    }

    /**
     * A request to unsubscribe from a topic has been made
     * @param \Ratchet\ConnectionInterface $conn
     * @param string|Topic                 $topic The topic to unsubscribe from
     */
    function onUnSubscribe(ConnectionInterface $conn, $topic){
		$this->log->info("onUnSubscribe");
		//unset($this->topicList[$topic->getId()]);
    }

    /**
     * A client is attempting to publish content to a subscribed connections on a URI
     * @param \Ratchet\ConnectionInterface $conn
     * @param string|Topic                 $topic The topic the user has attempted to publish to
     * @param string                       $event Payload of the publish
     * @param array                        $exclude A list of session IDs the message should be excluded from (blacklist)
     * @param array                        $eligible A list of session Ids the message should be send to (whitelist)
     */
    function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible){
        $this->log->info("onPublish [".$topic."]");
        if(is_array($event)){
            $date = new DateTime("NOW");
            $params = $event[0];
            if(isset($params['channel_id'], $params['player_id'])){
                $this->log->info("onPublish [".$topic."] [insert db]");
            	$last_id = $this->database->insert('message', [
            			'channel_id' => $topic->getId(),
            			'player_id' => $params['player_id'],
            			'text' => $params['text'],
                        'created_at' => $date->format(DateTime::RSS)
        		]);

                if(isset($params['name'], $params['basename'], $params['type'], $params['extension'], $params['size'])){
                    $this->database->insert('attachment', [
                        'mid' => $last_id,
                        'name' => $params['name'],
                        'basename' => $params['basename'],
                        'type' => $params['type'],
                        'extension' => $params['extension'],
                        'size' => $params['size'],
                        'created_at' => $date->format(DateTime::RSS)
                    ]);
                }
            }
        }

    	print_r($params);
    	$topic->broadcast($event);
    }

    function onOpen(ConnectionInterface $conn){
		$this->log->info("onOpen");
    }

    function onClose(ConnectionInterface $conn){
    	$this->log->info("onClose");
    }

    function onError(ConnectionInterface $conn, \Exception $e){
    	$this->log->info("onError");
    }

}
