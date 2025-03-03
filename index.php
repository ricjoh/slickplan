<?php
require_once( "logdump.php");
define('OAUTH2_CLIENT_ID', '9e2e7978-c242-4f95-bdfe-e5f919da81e6');
define('OAUTH2_CLIENT_SECRET', 'MMJSYiU1VJbvg5dnxJXDbhgpQoWUuprvxT4qwjzx');

$authorizeURL = 'https://slickplan.com/api/v1/authorize';
$tokenURL = 'https://slickplan.com/api/v1/token';
$apiURLBase = 'https://theferg.slickplan.com/api/v1/';

session_start();

if (_get('action') == 'logout') {
	error_log( "Logging out" );
	unset($_SESSION['access_token']);
	redirect_to( get_current_base_url() . "?action=none");
	exit();
} else if (_get('action') == 'login') {
	error_log( "Logging in" );
	// Generate a random hash and store in the session for security
    //	$_SESSION['state'] = hash('sha256', microtime(TRUE) . rand() . $_SERVER['REMOTE_ADDR']);
	unset($_SESSION['access_token']);

	// Redirect the user to Github's authorization page
	$authDest = $authorizeURL . '?' . http_build_query([
		'response_type' => 'code',
		'client_id' => OAUTH2_CLIENT_ID,
		'redirect_uri' => 'https://slickplan.ric.fergdev.com/',
        //	'state' => _session('state'),
		'scope' => 'all_read'
	]);
	redirect_to( $authDest );
	exit();
} else if (_get('code')) {
	error_log( "Code Sent" );
	// Verify the state matches our stored state
    //	if (!_get('state') || $_SESSION['state'] != _get('state')) {
	//	error_log( "State mismatch" );
    //		redirect_to($_SERVER['PHP_SELF']);
	//}

	// Exchange the auth code for a token
	error_log( "POST for token" );
	$form = http_build_query([
        'response_type' => 'code',        
		'grant_type' => 'client_credentials',
		'client_id' => OAUTH2_CLIENT_ID,
		'client_secret' => OAUTH2_CLIENT_SECRET,
		'state' => _session('state'),
		'code' => _get('code')
	]);
	$response = POST($tokenURL, $form);

    log_dump( $response, 'POST response for token' );

    
	$token = $response->access_token;
	$_SESSION['access_token'] = $token;
    // error_log( "Token Size: " . strlen($token) );
	// error_log( "Token: " . $token );
	// error_log( "Session Token Size: " . strlen($_SESSION['access_token']) );
	error_log( "Token Match: " . ($_SESSION['access_token'] === $token ? 'Yes' : 'No'));
	redirect_to(get_current_base_url());
	exit();
} else if (_session('access_token') && _get('action') !== 'none') {
	$user = GET($apiURLBase . 'me');

	echo '<h3>Logged In</h3>';
	echo '<a href="?action=logout">Log Out</a><br>';
	echo '<h4>' . ( isset($user) && isset($user->name) ? $user->name : 'Unknown User') . '</h4>';
	echo '<pre>';
	// print_r($user);
	echo _session('access_token', '') . "\n\n";
	echo '</pre>';
} else {
	echo '<h3>Not logged in</h3>';
	echo '<p><a href="?action=login">Log In</a></p>';
	exit();
}

// function apiRequest($url) {
// 	$context  = stream_context_create([
// 		'http' => [
// 			'user_agent' => 'Ferguson OAuth Login',
// 			'header' => 'Accept: application/json'
// 		]
// 	]);
// 	$response = @file_get_contents($url, false, $context);
// 	return $response ? json_decode($response) : $response;
// }

function _get($key, $default=NULL) {
  return isset($_GET[$key]) ? $_GET[$key] : $default;
}

function _session($key, $default=NULL) {
  return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
}

function get_current_base_url() {
	return get_site_url() . preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI']);
}

function get_site_url() {
	return 'http' . ($_SERVER["HTTPS"] ? 's' : '')
		. "://{$_SERVER['SERVER_NAME']}"
		. ($_SERVER["SERVER_PORT"] !== '80' ? ":{$_SERVER['SERVER_PORT']}" : '');
}

function redirect_to($url) {
	header('Location: ' . $url);
	exit();
}

function POST($url, $builtdata)
{
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $builtdata);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

	$headers = [
		'Accept: application/json',
		'Cache-Control: no-cache',
		'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
		'Referer: https://slickplan.ric.fergdev.com/', // Your referrer address
		'User-Agent: Ferguson OAuth Slickplan'
	];

	if ( $_SESSION['access_token'] ) {
		$headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
	}

	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	$response = curl_exec($curl);

    $response = json_decode($response);
    log_dump( $response, 'POST reply' );
    curl_close($curl);
	return $response; // ? json_decode($response) : $response;
}

function GET($url)
{
	$curl = curl_init($url);
	if ( $_SESSION['access_token'] ) {
		error_log( "Session Token Size GET: " . strlen($_SESSION['access_token']) );
		$url .= '?access_token=' . $_SESSION['access_token'];
	}
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false );
	$headers = [
		'Accept: application/json',
		'Cache-Control: no-cache',
		'Content-Type: text/html; charset=utf-8',
		'Referer: https://slickplan.ric.fergdev.com/', // Your referrer address
		'User-Agent: Ferguson OAuth Slickplan'
	];

	// if ( $_SESSION['access_token'] ) {
	// 	$headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
	// }

	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	// log_dump( curl_getinfo($curl), 'curl-GET' );/

	$response = curl_exec($curl);
	error_log( "Response: " . $response );
	curl_close($curl);
	return $response; // ? json_decode($response) : $response;
}
