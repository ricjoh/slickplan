<?php
define('OAUTH2_CLIENT_ID', '');
define('OAUTH2_CLIENT_SECRET', '');

$authorizeURL = 'https://github.com/login/oauth/authorize';
$tokenURL = 'https://github.com/login/oauth/access_token';
$apiURLBase = 'https://api.github.com/';

session_start();

// Start the login process by sending the user to Github's authorization page
if(get('action') == 'login') {
  // Generate a random hash and store in the session for security
  $_SESSION['state'] = hash('sha256', microtime(TRUE) . rand() . $_SERVER['REMOTE_ADDR']);
  unset($_SESSION['access_token']);

  // Redirect the user to Github's authorization page
  redirect_to($authorizeURL . '?' . http_build_query([
    'client_id' => OAUTH2_CLIENT_ID,
    'redirect_uri' => get_current_base_url(),
    'state' => $_SESSION['state'],
    'scope' => 'user:email'
  ]));
}

// When Github redirects the user back here, there will be a "code" and "state" parameter in the query string
if(get('code')) {
  // Verify the state matches our stored state
  if(!get('state') || $_SESSION['state'] != get('state')) {
    redirect_to($_SERVER['PHP_SELF']);
  }

  // Exchange the auth code for a token
  $token = apiRequest($tokenURL . '?' . http_build_query([
    'client_id' => OAUTH2_CLIENT_ID,
    'client_secret' => OAUTH2_CLIENT_SECRET,
    'state' => session('state'),
    'code' => get('code')
  ]));

  $_SESSION['access_token'] = $token->access_token;

  redirect_to(get_current_base_url());
}

if(session('access_token')) {
  $user = apiRequest($apiURLBase . 'user?access_token=' . session('access_token'));

  echo '<h3>Logged In</h3>';
  echo '<h4>' . $user->name . '</h4>';
  echo '<pre>';
  print_r($user);
  echo '</pre>';

} else {
  echo '<h3>Not logged in</h3>';
  echo '<p><a href="?action=login">Log In</a></p>';
}

function apiRequest($url) {
  $context  = stream_context_create([
    'http' => [
      'user_agent' => 'CWestify GitHub OAuth Login',
      'header' => 'Accept: application/json'
    ]
  ]);
  $response = @file_get_contents($url, false, $context);
  return $response ? json_decode($response) : $response;
}

function get($key, $default=NULL) {
  return isset($_GET[$key]) ? $_GET[$key] : $default;
}

function session($key, $default=NULL) {
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
  die();
}
