<?php
session_start();
/* Includes */
include __DIR__ . '/../../pgdbcreds.inc.php';
include __DIR__ . '/../libs/emailManager.php';
include __DIR__ . '/../libs/guidv4Generator.php'

/* Load up Composer */
require __DIR__ . '/../vendor/autoload.php';

/* Do not allow the Discord Embed Bot to run through this page */
if($_SERVER['HTTP_USER_AGENT'] == "Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)"){
	die();
}

/* Force Authentication */
if(!isset($_SESSION['loggedInAs']) || empty($_SESSION['loggedInAs']) || !array_key_exists('loggedInAs', $_SESSION)){
	require '../libs/discordAuth.inc.php';
}

/* Include Authentication Library below the forceful authentication */
include __DIR__ . '/../libs/authLib.inc.php';

/* If the user is not an administrator, display an error */
if($userIsAdmin == false){
	echo json_encode(array('success' => 'false', 'details' => 'You do not have permission to view this page. If you think this is in error, please contact the webmaster of this page.'));
  return('No permission');
}

/* If the user is an administrator, procceed and log this to console */
elseif($userIsAdmin == true){

	/* What to do if the POST action is to accept someone */
	if($_POST['Action'] == "Accept"){
		/* Array the two details needed for OwOAPI to generate a token */
       $signupDetailArray = array('username' => $_POST['signupDiscordUsername'], 'email' => $_POST['signupDiscordEmail']);

       $signupDetailJSON = json_encode($signupDetailArray); /* JSONify the above array */
         $tokenGeneratorCurlHandle = curl_init("http://127.0.0.1:9999/users"); /* Define the cURL handle */
         curl_setopt($tokenGeneratorCurlHandle, CURLINFO_HEADER_OUT, true); /* Sending some headers? */
         curl_setopt($tokenGeneratorCurlHandle, CURLOPT_RETURNTRANSFER, true); /* Receive the headers? (Look up) */
         curl_setopt($tokenGeneratorCurlHandle, CURLOPT_HTTPHEADER, array('content-type: application/json', "authorization:" . $_SESSION['userToken'])); /* Sent Headers */
         curl_setopt($tokenGeneratorCurlHandle, CURLOPT_POST, true); /* POST request? */
         curl_setopt($tokenGeneratorCurlHandle, CURLOPT_POSTFIELDS, $signupDetailJSON); /* POST Fields? $content which is defined on top (Our JSONified array) */
         curl_setopt($tokenGeneratorCurlHandle, CURLOPT_VERBOSE, false); /* Debugging? Want some verbose info? Here ya go (Defaults to false) [Debug Option] */
         curl_setopt($tokenGeneratorCurlHandle, CURLOPT_HEADER, false); /* cURL headers (Show the received headers) [Debug Option] */

         /* Execute and get response */
          $tokenGeneratorCurlHandleExec = curl_exec($tokenGeneratorCurlHandle);  /* Execute cURL */
          $jsonout = json_decode($tokenGeneratorCurlHandleExec, true); /* Decode the response */
          $jsonenout = json_encode($jsonout, JSON_PRETTY_PRINT); /* Pretty-Print the response */

        if($jsonout['success'] == true){
         /* Insert the Discord ID of the user to the database */
          $insertDiscordIDToTokenQuery = "UPDATE users SET discordid = '" . $_POST['signupDiscordId'] . "' WHERE id = '" . $jsonout['id'] . "'";
          $insertDiscordIDToTokenExecution = pg_exec($database, $insertDiscordIDToTokenQuery);
          $insertDiscordIDToTokenRow = pg_fetch_array($insertDiscordIDToTokenExecution);

         /* Set the status of the signup request(s) under the POSTed email as Approved */
          $setApprovedStatusQuery = "UPDATE signups SET status = 'Approved' WHERE email = '" . $_POST['signupDiscordEmail'] . "'";
          $setApprovedStatusExecution = pg_exec($database, $setApprovedStatusQuery);
          $setApprovedStatusRow = pg_fetch_array($setApprovedStatusExecution);

          /* Send the Acceptance email */
          sendAcceptanceEmail($_POST['signupDiscordEmail'], $_POST['signupDiscordUsername'], $jsonout['token']);

       }elseif($jsonout['success'] == false){
        echo json_encode(array('success' => 'false', 'details' => 'There was an issue proccessing your request. Full log below'));
        echo $jsonenout;
       }
	}

	/* What to do if the POST action is set to deny someone */
	if($_POST['Action'] == "Deny"){
		$tokenDenialQuery = "UPDATE signups SET status='Denied' WHERE discordid='" . $_POST['signupDiscordId'] . "'";
		$tokenDenialExecution = pg_exec($database, $tokenDenialQuery);
		$tokenDenialRow = pg_fetch_array($tokenDenialExecution);
    echo json_encode(array('success' => 'true', 'details' => 'Requester successfully denied.'));
    sendDeclineEmail($_POST['signupDiscordEmail'], $_POST['signupDiscordUsername']);
	}

	/* If they have accessed the webpage via an invalid authentication way / Have null data, automatically deny all requests with null data */
	if($_POST['signupDiscordUsername'] == "#"){
		$invalidUserNameQuery = "UPDATE signups SET status='Denied' WHERE username='#'";
		$invalidUserNameExecution = pg_exec($database, $invalidUserNameQuery);
		$invalidUserNameRow = pg_fetch_array($database, $invalidUserNameExecution);
    echo json_encode(array('success' => 'true', 'details' => 'Seems like they refused to give us any data; Automatically denied!'));
	}
  elseif(!array_key_exists('Action', $_POST)){
    echo json_encode(array('success' => 'false', 'details' => 'No data provided. If you are visiting this page manually, please use RLPanel\'s webpage to access this API.'));
  }
}
?>
