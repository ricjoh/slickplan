use Slickplan\OAuth2\Client\Provider\Slickplan as SlickplanProvider;

$provider = new SlickplanProvider([
    'clientId' => '{client-id}',
    'clientSecret' => '{client-secret}',
    'redirectUri' => 'https://example.com/slickplan/redirect-uri/',
]);

if (isset($_GET['error'])) {
    print_r($_GET);
    exit;
} elseif (!isset($_GET['code'])) {
    header('Location: ' . $provider->getAuthorizationUrl(['scope' => 'all_read']));
    exit;
}

try {
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code'],
    ]);
    $user = $provider->getResourceOwner($token);
} catch (Exception $e) {
    exit('Error: ' . $e->getMessage());
}

echo 'Hello ' . $user->getFirstName() . '!<br>';
echo 'Your API token: <code>' . $token->getToken() . '</code>';
