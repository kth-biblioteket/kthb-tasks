<?php

require_once "config.php";
include_once('dbconnect.php');
date_default_timezone_set("Europe/Stockholm");

session_name("KTHB_TASKS_SESSID");

session_start();

//210519 OpenID Connect framework(myits)
require_once($_SERVER['DOCUMENT_ROOT'] .  '/myits/vendor/autoload.php');

use Its\Sso\OpenIDConnectClient;
use Its\Sso\OpenIDConnectClientException;

//Funktioner för att läsa JWTtokens från KTH-login(id_token)
function base64url_decode($base64url) {
	return base64_decode(b64url2b64($base64url));
  }
  
function b64url2b64($base64url) {
	$padding = strlen($base64url) % 4;
	if ($padding > 0) {
		$base64url .= str_repeat("=", 4 - $padding);
	}
	return strtr($base64url, '-_', '+/');
}

function decodeJWT($jwt, $section = 0) {
	$parts = explode(".", $jwt);
	return json_decode(base64url_decode($parts[$section]));
}

try {
	$oidc = new OpenIDConnectClient(
		$kth_auth_endpoint,
		$kth_client_id,
		$kth_client_secret
	);

	$oidc->addScope('openid email profile');
  
	// remove this if in production mode
	$oidc->setVerifyHost(false);
	$oidc->setVerifyPeer(false);

	//redirect tillbaks till denna sida!
    $oidc->setRedirectURL(html_entity_decode("https://" . $_SERVER['HTTP_HOST'] . "/tasks/login.php")); // must be the same as you registered

	
	//Skickar vidare till login på KTH om användaren inte redan är inloggad
	$oidc->authenticate();
	//Vid redan inloggad exekveras koden nedan 

	//id_token innehåller den användarinfo tjänsten prenumererar på(här används kthid)
	$_SESSION['id_token'] = $oidc->getIdToken();
	$userinfo = decodeJWT($_SESSION['id_token'], 1);
	$_SESSION['kth_id'] = $userinfo->kthid;

	//finns ett kthid så startas applikationen
	if(isset($_SESSION['kth_id']) && $_SESSION['kth_id'] != "") {
		$userid = $_SESSION['kth_id']  ;
		$returl = str_replace('ampersand','&',$returl);
		registerTandemLogin($userid);
		header("location: https://" . $_SERVER['HTTP_HOST'] . "/tasks");
	}
  
} catch (OpenIDConnectClientException $e) {
	echo $e->getMessage();
}


function registerTandemLogin($userid)
{
	$loginyear 	= date("Y") ;
	$loginmonth = date("m") ;
	$loginday 	= date("d") ;
	$loginTime 	= date("H:i:s"); 
	$lastlogin = $loginyear."-".$loginmonth."-".$loginday." ".$loginTime ; 

	$sql = "INSERT INTO  tll_logincounts (userid, loginyear, loginmonth, loginday, logintime) 
				VALUES ('$userid' , '$loginyear', '$loginmonth', '$loginday', '$loginTime')" ;
	$res = mysqli_query($con, $sql) ;
	
	$sqlCheck = "select * from tll_users where id = '$userid' " ;
	$resultCheck = mysqli_query($con, $sqlCheck) ;
	$rowUser 	= mysqli_fetch_assoc($resultCheck) ;
	if($rowUser){
		$lastlogin = $loginyear."-".$loginmonth."-".$loginday." ".$loginTime ;
		$sql = "UPDATE tll_users SET last_login = '$lastlogin', lastloginyear = '$loginyear' ,  lastloginmonth = '$loginmonth'  WHERE id = '$userid' " ;
		$res = mysqli_query($con, $sql) ;
	} else {
		$sql = "INSERT INTO tll_users (id, lastloginyear, lastloginmonth, reg_date, last_login) 
				VALUES ('$userid', '$loginyear', '$loginmonth' , '$lastlogin', '$lastlogin')  " ;
		$res = mysqli_query($con, $sql) ;
		//error_log("insert res: " . $sql);
	}
}