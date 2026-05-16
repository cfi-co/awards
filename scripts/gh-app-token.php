<?php
/**
 * Mint a short-lived GitHub App installation access token for pushing.
 *
 * Reads root-only config (NOT in the repo): /root/.config/awards-archive/app.env
 *   APP_ID=123456
 *   PEM=/root/.config/awards-archive/app-private-key.pem
 *   OWNER=cfi-co
 *   REPO=awards
 *
 * Prints the token to stdout. Token lives ~1h; minted fresh on every sync.
 * Usage: php8.2 scripts/gh-app-token.php
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$envfile = getenv('AWARDS_APP_ENV') ?: '/root/.config/awards-archive/app.env';
if (!is_readable($envfile)) {
    fwrite(STDERR, "Config not found: $envfile\n");
    exit(2);
}
$cfg = array();
foreach (file($envfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    $l = trim($l);
    if ($l === '' || $l[0] === '#' || strpos($l, '=') === false) continue;
    list($k, $v) = explode('=', $l, 2);
    $cfg[trim($k)] = trim($v);
}
foreach (array('APP_ID', 'PEM', 'OWNER', 'REPO') as $k) {
    if (empty($cfg[$k])) { fwrite(STDERR, "Missing $k in $envfile\n"); exit(2); }
}

$pem = @file_get_contents($cfg['PEM']);
if ($pem === false) { fwrite(STDERR, "Cannot read PEM: {$cfg['PEM']}\n"); exit(2); }
$key = openssl_pkey_get_private($pem);
if ($key === false) { fwrite(STDERR, "Invalid private key\n"); exit(2); }

/* --- Build the App JWT (RS256) --- */
$b64 = function ($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); };
$now = time();
$header  = $b64(json_encode(array('alg' => 'RS256', 'typ' => 'JWT')));
$payload = $b64(json_encode(array(
    'iat' => $now - 60,
    'exp' => $now + 540,            // max 10 min
    'iss' => $cfg['APP_ID'],
)));
$sig = '';
openssl_sign($header . '.' . $payload, $sig, $key, OPENSSL_ALGO_SHA256);
$jwt = $header . '.' . $payload . '.' . $b64($sig);

/* --- Helper: GitHub API call --- */
$api = function ($method, $url, $bearer) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array(
            'Authorization: Bearer ' . $bearer,
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: cfi-co-awards-archive-sync',
        ),
        CURLOPT_TIMEOUT => 30,
    ));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($body === false) { fwrite(STDERR, 'curl: ' . curl_error($ch) . "\n"); exit(3); }
    curl_close($ch);
    return array($code, json_decode($body, true), $body);
};

/* --- Discover installation for this repo, then mint token --- */
list($c, $j) = $api('GET',
    "https://api.github.com/repos/{$cfg['OWNER']}/{$cfg['REPO']}/installation", $jwt);
if ($c !== 200 || empty($j['id'])) {
    fwrite(STDERR, "Cannot find App installation (HTTP $c). Is the App installed on {$cfg['OWNER']}/{$cfg['REPO']}?\n");
    exit(3);
}
$instId = $j['id'];

list($c, $j) = $api('POST',
    "https://api.github.com/app/installations/{$instId}/access_tokens", $jwt);
if ($c !== 201 || empty($j['token'])) {
    fwrite(STDERR, "Token request failed (HTTP $c)\n");
    exit(3);
}
echo $j['token'];
