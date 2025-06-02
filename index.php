<?php
// https://github.com/slickplan/api-docs

require_once("logdump.php");
define('OAUTH2_CLIENT_ID', '9e2e7978-c242-4f95-bdfe-e5f919da81e6');
define('OAUTH2_CLIENT_SECRET', 'MMJSYiU1VJbvg5dnxJXDbhgpQoWUuprvxT4qwjzx');
global $htmlstarted; $htmlstarted = false; // can't do HTML stuff on OAUTH handshake
$authorizeURL = 'https://slickplan.com/api/v1/authorize';
$tokenURL = 'https://slickplan.com/api/v1/token';
$apiURLBase = 'https://slickplan.com/api/v1/';

session_start();

if (_get('action') == 'logout') {
	error_log("Logging out");
	unset($_SESSION['access_token']);
	redirect_to(get_current_base_url() . "?action=none");
	exit();
} else if (_get('action') == 'login') {
	error_log("Logging in");
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
	redirect_to($authDest);
	exit();
} else if (_get('code')) {
	error_log("Code Sent");
	// Verify the state matches our stored state
	//	if (!_get('state') || $_SESSION['state'] != _get('state')) {
	//	error_log( "State mismatch" );
	//		redirect_to($_SERVER['PHP_SELF']);
	//}

	// Exchange the auth code for a token
	error_log("POST for token");
	$form = http_build_query([
		'grant_type' => 'authorization_code',
		'client_id' => OAUTH2_CLIENT_ID,
		'client_secret' => OAUTH2_CLIENT_SECRET,
		'redirect_uri' => 'https://slickplan.ric.fergdev.com/',
		'code' => _get('code')
		// 'state' => _session('state'),
	]);
	$response = POST($tokenURL, $form);

	log_dump($response, 'TOKEN');
	$token = $response->access_token;
	$_SESSION['access_token'] = $token;
	// error_log( "Token Size: " . strlen($token) );
	// error_log( "Token: " . $token );
	// error_log( "Session Token Size: " . strlen($_SESSION['access_token']) );
	error_log("Token Match: " . ($_SESSION['access_token'] === $token ? 'Yes' : 'No'));
	redirect_to(get_current_base_url());
	exit();
} else if (_session('access_token')) {
	if (_get('action') !== 'none') {
		$user = GET($apiURLBase . 'me');
		_start_html();
		echo '<h3>Logged In</h3>';
		echo '<a href="?action=logout">Log Out</a><br>';
		echo '<h4>USER: ' . (isset($user) && isset($user->username) ? $user->username : 'Unknown User') . '</h4>';
		echo "<h2>Start Here</h2>\n";
		echo '<a href="?action=sitemaps">Sitemap List</a><br>';
/*
		echo "<h2>Test Stuff</h2>\n";
		echo '<a href="?action=structure">Structure</a><br>';
		echo '<a href="?action=page">Page</a><br>';
		echo '<a href="?action=content">Page Content</a><br>';
*/
	}
	if (_get('action') == 'sitemaps') {
		$test = GET($apiURLBase . 'sitemaps');

		echo "<pre>";
		foreach ($test as $sitemap) {
			echo "<br><b>{$sitemap->title}</b>\n";
			$name = strtolower(str_replace(' ', '-', $sitemap->title));
			$name = preg_replace('/[^-a-zA-Z0-9]/', '', $name);
			foreach ($sitemap->versions as $version) {
				// $disp = print_r( $version, true );
				printf("    Version: %s (Created: %.10s)\n", $version->version, $version->date_created);
				printf("    <a href='https://theferg.slickplan.com/project/%s/content' target='_slickplan'>Link to Slickplan Content</a>\n", $version->uri_alias);
				printf("    <a href='slurp.php?uri=%s&name=%s'>Download Slickplan Images</a>\n", $version->uri_alias, $name);
				// printf( "Version: %s (Created: %.10s) -> %s\n", $version->version, $version->date_created, $version->uri_alias );
			}
		}
		echo "</pre>";
	}
	if (_get('action') == 'structure') {
		$test = GET($apiURLBase . 'sitemaps/649958/content/');
		_start_html();
		echo "<pre>";
		print_r($test);
		echo "</pre>";
	}
	if (_get('action') == 'page') {
		$test = GET($apiURLBase . 'sitemaps/649958/page/svgwgwgo5gfgd18elua');
		_start_html();
		echo "<pre>";
		print_r($test);
		echo "</pre>";
	}
	if (_get('action') == 'content') {
		$test = GET($apiURLBase . 'sitemaps/649958/page/svgagqz33otnr5t9e50/content');
		_start_html();
		echo "<pre>";
		print_r($test);
		echo "</pre>";
	}
} else {
	_start_html();
	echo '<h3>Not logged in</h3>';
	echo '<p><a href="?action=login">Log In</a></p>';
}
_end_html();
exit();

function _start_html() {
	global $htmlstarted;
	$htmlstarted == true;
	echo <<< EOHTML
<html>
<head>
	<style>
		body {
			font-family: sans-serif;
			margin: 3rem;
		}
		pre, pre a {
			font-family: monospace;
		}
	</style>
</head>
<body>
<h1>Slickplan API Content Tool</h1>
EOHTML;
}

function _end_html() {
	global $htmlstarted;
	if ( !$htmlstarted ) return;
	echo <<< EOHTML
</body>
</html>
EOHTML;
}

function _get($key, $default = NULL) {
	return isset($_GET[$key]) ? $_GET[$key] : $default;
}

function _session($key, $default = NULL) {
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

function POST($url, $builtdata) {
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

	if ($_SESSION['access_token']) {
		$headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
	}

	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	$response = curl_exec($curl);
	curl_close($curl);
	return $response ? json_decode($response) : $response;
}

function GET($url) {
	if ($_SESSION['access_token']) {
		error_log("Session Token Size GET: " . strlen($_SESSION['access_token']));
		$url .= '?access_token=' . $_SESSION['access_token'];
	}
	error_log("GET URL: " . $url);
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
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

	$response = curl_exec($curl);
	error_log("Response: " . $response);
	curl_close($curl);
	return $response ? json_decode($response) : $response;
}
