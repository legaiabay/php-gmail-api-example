<?
	require_once 'vendor/autoload.php';

	use Google\Client;
	use Google\Service\Gmail;
	use Google\Service\Gmail\Message;
	use Google\Service\Gmail\Draft;

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
	// get credentials from your Google Developer account
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
		// set default user
		$user = "me";

		// dummy email data
		$sender = "sender.dummy@gmail.com";
		$to = "to.dummy@gmail.com";
		$subject = "testing dummy email";
		$messageText = '<div><img src="https://i.ytimg.com/vi/L2k--IEgDng/maxresdefault.jpg"></div>';

		// Create message
		$message = new Gmail\Message();

		$rawMessageString = "From: <{$sender}>\r\n";
		$rawMessageString .= "To: <{$to}>\r\n";
		$rawMessageString .= 'Subject: =?utf-8?B?' . base64_encode($subject) . "?=\r\n";
		$rawMessageString .= "MIME-Version: 1.0\r\n";
		$rawMessageString .= "Content-Type: text/html; charset=utf-8\r\n";
		$rawMessageString .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
		$rawMessageString .= "{$messageText}\r\n";

		$rawMessage = strtr(base64_encode($rawMessageString), array('+' => '-', '/' => '_'));
		$message->setRaw($rawMessage);

		// Create draft
		$draft = new Gmail\Draft();
		$draft->setMessage($message);

		// Setup message draft
		$messageDraft = $gmail->users_drafts->create($sender, $draft);

		// Send email
		$sendEmail = $gmail->users_messages->send($sender, $message);
	}
	catch(Exception $e) {
	    // TODO(developer) - handle error appropriately
	    echo 'Message: ' .$e->getMessage();
	}

	echo "Email sent!";
?>