<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/catfan/medoo/medoo.php';

$app = new \Slim\App;
$app->get('/v1/api/{id}/channel[/{page}]', function (Request $request, Response $response, $args) {
    $database = getDatabase();
    $userId = $request->getAttribute('id');
    $page = $args['page'];
    $limit = 10;
    $skip = $limit * $page;
	//$msgSQL = "CONCAT('[', '{count:\'', (SELECT COUNT(*) FROM channel WHERE channel_id = c.channel_id GROUP BY channel_id), '\'}', ']') as message";
    //$msgSQL = "CONCAT('{', '\'count\' : \'', (SELECT COUNT(*) FROM channel WHERE channel_id = c.channel_id GROUP BY channel_id) , '\'', (SELECT CONCAT(', \'text\' : \'', text , '\'', ', \'create_at\' : ', '\'', created_at , '\'') FROM message WHERE channel_id = c.channel_id LIMIT 1), '}') as message";
    $msgSQL =  "(SELECT COUNT(*) FROM message WHERE channel_id = c.channel_id GROUP BY channel_id) as count, ";
    $msgSQL .= "(SELECT COUNT(*) FROM channel WHERE channel_id = c.channel_id GROUP BY channel_id) as players, ";
    $msgSQL .= "(SELECT text FROM message WHERE channel_id = c.channel_id LIMIT 1) as message, ";
    $msgSQL .= "(SELECT created_at FROM message WHERE channel_id = c.channel_id LIMIT 1) as updated_at";

    $channelSQL = "SELECT c.*, $msgSQL FROM channel as c WHERE c.player_id = $userId LIMIT $skip, $limit";
    $channel = $database->query($channelSQL)->fetchAll(PDO::FETCH_ASSOC);
    if($channel != null){
    	return $response->withJson($channel);
    }

    // $channel = $database->query("SELECT * FROM channel WHERE user_id = $userId OR player_id = $userId LIMIT $skip, $limit")->fetchAll();
    //    $data = [];
    //    if($channel != null){
	//     for($i=0;$i<count($channel);$i++){
	//     	$channelId = $channel[$i]['channel_id'];
	//     	$data[$i]['channel_id'] = $channelId;
	//     	$message = $database->query("SELECT COUNT(id) as count, text, created_at FROM message WHERE channel_id = '$channelId' GROUP BY channel_id DESC ORDER BY created_at DESC")->fetchAll();
	// 		$data[$i]['message'] = $message;
	//     	$data[$i]['player_id'] = $channel[$i]['player_id'];
	//     	$data[$i]['user_id'] = $channel[$i]['user_id'];
	//     }
	// 	return $response->withJson($data);
	// }

    return $response->withJson(['code'=>404, 'error'=>'No data found']);
})->setArgument('page', 0);

$app->get('/v1/api/channel/{playerId}/{channelId}[/{page}]', function(Request $request, Response $response, $args){

    $channelId = $request->getAttribute('channelId');
    $playerId = $request->getAttribute('playerId');
    $page = $args['page'];

	if($channelId != null){
		$database = getDatabase();
	    $limit = 2;
	    $skip = $limit * $page;
		$ids = $database->query("SELECT GROUP_CONCAT(c.player_id) as players  FROM channel as c WHERE c.channel_id = '$channelId' GROUP BY channel_id")->fetchAll();
		if($ids != null){
			$players = $ids[0]['players'];
			$playerArr = explode(',', $players);
			if(in_array($playerId, $playerArr)){
				$message = $database->query("SELECT m.*, a.basename, a.name, a.path, a.type, a.extension, a.size FROM message as m LEFT OUTER JOIN attachment as a ON a.mid = m.id WHERE m.player_id IN($players) ORDER BY m.created_at DESC LIMIT $skip, $limit")->fetchAll(PDO::FETCH_ASSOC);
				if($message != null){
					return $response->withJson($message);
				}
			}
		}
	}

	return $response->withJson(['code'=>404, 'error'=>'No data found']);
})->setArgument('page', 0);

$app->post('/v1/api/create', function (Request $request, Response $response) {
    $database = getDatabase();
    $date = new DateTime("NOW");
    $now = $date->getTimestamp();
    $userId = $request->getParam('userId');
    $players = $request->getParam('players');
    $name = $request->getParam('name');
    $channelId = "com.channel." . $userId .".". $now;
    $last_user_id = $database->insert("channel", [
		"player_id" => $userId,
		"channel_id" => $channelId,
		"name" => $name,
		"role" => 1,
		"created_at" => $date->format(DateTime::RSS)
	]);

	$players = json_decode($players);
    foreach($players as $key => $value){
    	 $database->insert("channel", [
			"channel_id" => $channelId,
			"player_id" => $value,
			"name" => $name,
			"role" => 0,
			"created_at" => $date->format(DateTime::RSS)
		]);
    }

    return $response->withJson(["state"=>200]);
});


$app->post('/v1/api/upload[/{type}]', function(Request $request, Response $response){
	try{
		$type = $request->getAttribute('type');
		if(!in_array($type, array('file', 'image', 'sound'))){
			$type = 'file';
		}

		$storage = new \Upload\Storage\FileSystem(__DIR__ ."/public/upload/$type");
		$file = new \Upload\File('foo', $storage);

		// Optionally you can rename the file on upload
		$new_filename = uniqid();
		$file->setName($new_filename);

		// Validate file upload
		// MimeType List => http://www.iana.org/assignments/media-types/media-types.xhtml
		$file->addValidations(array(
		    // Ensure file is of type "image/png"
		    new \Upload\Validation\Mimetype('image/png'),

		    //You can also add multi mimetype validation
		    //new \Upload\Validation\Mimetype(array('image/png', 'image/gif'))

		    // Ensure file is no larger than 5M (use "B", "K", M", or "G")
		    new \Upload\Validation\Size('5M')
		));

		// Access data about the file that has been uploaded
		$data = array(
		    'name'       => $file->getNameWithExtension(),
		    'extension'  => $file->getExtension(),
		    'mime'       => $file->getMimetype(),
		    'size'       => $file->getSize(),
		    'md5'        => $file->getMd5(),
		    'dimensions' => $file->getDimensions()
		);

		// Try to upload file
		try {
		    // Success!
		    $file->upload();
		} catch (\Exception $e) {
		    // Fail!
		    return $file->getErrors();
		}

	}catch(Exception $e){
		return $e->getMessage();
	}

	return "Upload Success.";
})->setArgument('type', 'file');

function getDatabase(){
	$database = new medoo([
		'database_type' => 'mysql',
		'database_name' => 'ratchet_chat',
		'server' => 'localhost',
		'username' => 'root',
		'password' => '123123123',
		'charset' => 'utf8'
	]);
	return $database;
}

$app->run();


?>