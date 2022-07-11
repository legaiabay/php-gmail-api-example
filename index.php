<?php
	require_once 'vendor/autoload.php';

	use Google\Client;
	use Google\Service\Gmail;
	use Google\SErvice\Gmail\Message;

	if(!isset($_SESSION)) session_start();

	// for debugging
	function va($data)
	{
	    echo "<pre>";
	    print_r($data); // or var_dump($data);
	    echo "</pre>";
	}

	// decode email data
	function decodeEmailData($data){
		$rawData = $data;
		$sanitizedData = strtr($rawData,'-_', '+/');
		$decodedMessage = base64_decode($sanitizedData);

		return $decodedMessage;
	}
	
	// set client auth params
	$gClientId = "<your oauth client id>";
	$gClientSecret = "<your oauth client secret>";
	$gRedirectUri = "<your oauth redirect uri>";
		   
	// create Client Request to access Google API
	$client = new Client();
	$client->setClientId($gClientId);
	$client->setClientSecret($gClientSecret);
	$client->setRedirectUri($gRedirectUri);
	$client->addScope('https://www.googleapis.com/auth/gmail.modify');

	// authenticate code from Google OAuth Flow
	if (isset($_SESSION['access_token'])){
		$client->setAccessToken($_SESSION['access_token']);

		if($client->isAccessTokenExpired()){
			if($client->getRefreshToken()){
				$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
			}
		}
	}
	else if (isset($_GET['code'])) {
		$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
		$client->setAccessToken($token['access_token']);

		$_SESSION['code'] = $_GET['code'];
		$_SESSION['access_token'] = $token['access_token'];
		$_SESSION['current_page'] = 0;
		$_SESSION['page_token_list'][0]['token'] = '';

	} else {
	  echo "<a href='".$client->createAuthUrl()."'>Google Login</a>";
	  exit;
	}


	// For Gmail PHP documentation : 
	// https://developers.google.com/resources/api-libraries/documentation/gmail/v1/php/latest/
	$gmail = new Gmail($client);

	try {

	    // Gmail own account
	    $user = 'me';

	    // ====================
	    // Get labels
	    // ====================
	    
	    $showLabels = [];
	    $results = $gmail->users_labels->listUsersLabels($user);
	    if (count($results->getLabels()) == 0) {
	        $showLabels[] = "No labels found.";
	    } else {
	        foreach ($results->getLabels() as $label) {
	        	$showLabels[] = $label->getName();
	        }
	    }



	    // ====================
	    // Get messages
	    // ====================

	   	// pagination test
	   	// note : pagination only works for next or previous page. jump to specific page number is not allowed by API.
	   	
		$pageTo = "next"; // 'next', 'prev', or empty string. if empty, it will stay in current page.
		if($pageTo == "next"){
			$_SESSION['current_page']++;
		} 
		elseif($pageTo == "prev") {
			$_SESSION['current_page'] = $_SESSION['current_page'] <= 0 ? 0 : $_SESSION['current_page'] - 1; 
		}
	    
	    // set options parameter
	    $optParams = [];
		$optParams['maxResults'] = 2;
		$optParams['labelIds'] = 'INBOX';
		$optParams['pageToken'] = $_SESSION['page_token_list'][$_SESSION['current_page']]['token'];

		// get messages
		$messages = $gmail->users_messages->listUsersMessages($user,$optParams);
		$listMessages = $messages->getMessages();

		// set next page token if not defined yet
		$nextPageToken = $messages->getNextPageToken();
		if(!isset($_SESSION['page_token_list'][$_SESSION['current_page']+1])){
			$_SESSION['page_token_list'][$_SESSION['current_page']+1]['token'] = $nextPageToken;
		}

		// contain message to array
		$showMessages = [];
		foreach ($listMessages as $index => $msg) {

			// set options
			$optParamsGet = [];
			$optParamsGet['format'] = 'full'; // Display message in payload

			// get message
			$message = $gmail->users_messages->get('me',$msg->getId(),$optParamsGet);
			
			// get message payload
			$messagePayload = $message->getPayload();
			
			// get headers data
			$showHeaders = [];
			$headers = $messagePayload->getHeaders();
			foreach ($headers as $key => $val) {
				$showHeaders[$val->name] = $val->value;
			}
			
			// get body data
			$parts = $messagePayload->getParts();

			// parts[0] -> text/plain
			// parts[1] -> text/html
			$body = $parts[1]['body'];

			// save messages body to array
			$showMessages[$index]['id'] = $msg->getId();
			$showMessages[$index]['headers'] = $showHeaders; 
			$showMessages[$index]['body'] = decodeEmailData($body->data);
        }

	}
	catch(Exception $e) {
	    // TODO(developer) - handle error appropriately
	    echo 'Message: ' .$e->getMessage();
	}
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Gmail API Demo</title>
</head>
<body>
	<div>
		<h2> SESSION lists : </h2>
		<?=va($_SESSION)?>
	</div>

	<div>
		<h2> Label lists : </h2>
		<ul>
			<? foreach ($showLabels as $key => $lbl) { ?>
				<li><?=$lbl?></li>	
			<? } ?>
		</ul>
	</div>

	<br>

	<div>
		<h2> Message lists : </h2>
		<? foreach ($showMessages as $key => $msg) { ?>
			<div data-id="<?=$msg['id']?>">
				<div>
					<div>
						Subject : <?=$msg['headers']['Subject']?> 
					</div>
					<div>
						From : <?=$msg['headers']['From']?> 
					</div>
					<div>
						To : <?=$msg['headers']['To']?> 
					</div>
					<div>
						Date : <?=$msg['headers']['Date']?> 
					</div>
				</div>
				<div>
					<?=$msg['body']?>	
				</div>
			</div>
		<? } ?>
	</div>

</body>
</html>