<?php
require_once( "logdump.php" );
define('OAUTH2_CLIENT_ID', '9e2e7978-c242-4f95-bdfe-e5f919da81e6');
define('OAUTH2_CLIENT_SECRET', 'MMJSYiU1VJbvg5dnxJXDbhgpQoWUuprvxT4qwjzx');
global $htmlstarted; $htmlstarted = false; // can't do HTML stuff on OAUTH handshake
$authorizeURL = 'https://slickplan.com/api/v1/authorize';
$tokenURL = 'https://slickplan.com/api/v1/token';
$apiURLBase = 'https://slickplan.com/api/v1';

if ($_GET['uri']) {
	$SITEMAPID = $_GET['uri'];
	$BRAND = $_GET['name'];
} else {
	header("Location: /");
	exit();
}
// $SITEMAPID = '684774';
// $BRAND = 'hh-trailers';
// $SITEMAPID = '650560';
// $BRAND = 'midsota';

session_start();

if (_get('action') == 'logout') {
	error_log( "Logging out" );
	unset($_SESSION['access_token']);
	redirect_to( get_current_base_url() . "?action=none");
	exit();
} else if (_session('access_token')) {
	_start_html();
	if ( _get('action') !== 'none') {
		$user = GET("{$apiURLBase}/me");

		echo '<a href="?action=logout">Log Out of Slickplan</a><br>';
		echo "<h2>Step 1: Click this button to download the file list to pluto: /tmp/files-{$BRAND}</h3>";
		echo "<a href=\"slurp.php?uri={$SITEMAPID}&name={$BRAND}&action=slurp\"><button>Slurp {$BRAND} ({$SITEMAPID})</button></a>";
	}

	if ( _get('action') == 'slurp') {
		$fh = fopen("/tmp/files-{$BRAND}", "w");
		$structure = GET("{$apiURLBase}/sitemaps/{$SITEMAPID}/structure");
		$images = 0;
		$folders = 0;
		foreach ( $structure->svgmainsection as $page ) {
			if ( isset($page->has_content ) )
			{
				$pagedata = GET("{$apiURLBase}/sitemaps/{$SITEMAPID}/page/{$page->id}/content");
				foreach ( $pagedata->body as $content ) {
					if ( $content->type == 'file' ) {
						$folders++;
						foreach ( $content->content as $file ) {
							$images++;
							$outline = fprintf( $fh, "%s\t%s/%s\n",
										$file->url,
										str_replace(' ', '_', preg_replace('/[[:^print:]]/', '', $page->text )),
										$file->filename );
						}
					}
				}
			}
		}
		fclose($fh);
		chmod("/tmp/files-{$BRAND}", 0664);

		echo "<h3>Done. Found {$images} images in {$folders} folders.</h3>";
		echo "<h2>Step 2: Enter a path on Jobs Server where you want the files copied. A folder called \"Slickplan Images\" will be created in that folder.</h2>";
        echo "Paste in the path that you want in whatever form you have. As long as it includes the _CLIENTS parts, we should be able to figure it out.</br>";
        echo "<input type=\"text\" name=\"jobserverpath\" id=\"jobsserverpath\" size=\"100\" onchange=\"updatehref()\" /><br/>\n";
        echo "<br/>Expect this to take 2-3 minutes to run.<br/><br/>";
		echo "<a href=\"slurp.php?uri={$SITEMAPID}&name={$BRAND}&action=download-and-copy&filelist=/tmp/files-{$BRAND}\" id=\"downloadbutton\"><button>Download & Copy {$BRAND} ({$SITEMAPID})</button></a>";

        echo <<<END
<script type="text/javascript">
function updatehref() {
    const input = document.getElementById("jobsserverpath");
    const link = document.getElementById("downloadbutton");

    //console.log(input);
    //console.log(link)

    const url = new URL(link.href);
    const newPath = input.value;

    url.searchParams.set("jspath", newPath); // adds or updates 'path'

    link.href = url.toString();
    //console.log(link.href);
}
</script>
END;
	}

    if (_get('action') == "download-and-copy") {
        // Example jspath:
        //     smb://fai-server.fai2.com/jobs%20server/_CLIENTS/NOVAE/NOVA6037%20Compass%20Trailers%20Website

        $jspath = _get('jspath');
        error_log("jspath (1): \"$jspath\"");

        $jspath = str_replace('\\', '/', $jspath);
        $jspath = preg_replace('/^.*[\\/]_CLIENTS/', '_CLIENTS', $jspath);
        $jspath = preg_replace('/\s+$/', '', $jspath);
        $jspath = urldecode($jspath);
        error_log("jspath (2): \"$jspath\"");

        $cmd = getcwd() . "/slurp-copy-slickplan-images.sh \"" . _get('filelist') . "\" \"" . $jspath . "\"";
        error_log($cmd);
        error_log(shell_exec($cmd));
    }

} else {
	_start_html();
	echo '<h3>Not logged in</h3>';
	echo '<p><a href="?action=login">Log In</a></p>';
}
_end_html();
exit();

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
    curl_close($curl);
	return $response ? json_decode($response) : $response;
}

function GET($url)
{
	if ( $_SESSION['access_token'] ) {
		// error_log( "Session Token Size GET: " . strlen($_SESSION['access_token']) );
		$url .= '?access_token=' . $_SESSION['access_token'];
	}
	// error_log( "GET URL: " . $url );
	$curl = curl_init($url);
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

	$response = curl_exec($curl);
	// error_	( "Response: " . $response );
	curl_close($curl);
	return $response ? json_decode($response) : $response;
}
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
		button {
			font-size: 1.5rem;
			padding: 1rem 2rem;
			background-color: darkblue;
			color: white;
			border-radius: 0.5rem;
			border-style: none;
		}
		button:hover {
			background-color: #aaa;
			border-style: solid;
		}
	</style>
</head>
<body>
<h1>Slickplan Download Images</h1>
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
