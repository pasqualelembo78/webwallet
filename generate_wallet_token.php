 
<?php
// generate_wallet_token.php
// POST { filename, api_key } -> { token, expires_at }
// Protected: requires API key or header X-API-KEY

header('Content-Type: application/json');
date_default_timezone_set('UTC');

$LOG = '/var/log/wallet_download.log'; // o /tmp/wallet_download.log se preferisci
$BASE_DIR = '/opt/mevacoin/build/src/'; // path dove stanno i wallet
$API_KEY = 'desy2011'; // CAMBIA questa chiave in produzione
$TOKEN_STORE = '/tmp/wallet_tokens.json'; // file dove memorizziamo token (persistenza semplice)
$TOKEN_TTL = 300; // token valido per 300 secondi = 5 minuti

// helpers
function write_log($msg) {
    global $LOG;
    $line = date('Y-m-d H:i:s') . " " . $msg . PHP_EOL;
    @file_put_contents($LOG, $line, FILE_APPEND | LOCK_EX);
}
function load_tokens() {
    global $TOKEN_STORE;
    if (!file_exists($TOKEN_STORE)) return [];
    $c = @file_get_contents($TOKEN_STORE);
    $j = json_decode($c, true);
    return is_array($j) ? $j : [];
}
function save_tokens($arr) {
    global $TOKEN_STORE;
    @file_put_contents($TOKEN_STORE, json_encode($arr), LOCK_EX);
}

// read input
$body = file_get_contents('php://input');
$data = json_decode($body, true) ?: $_POST;

$provided_key = $_SERVER['HTTP_X_API_KEY'] ?? ($data['api_key'] ?? '');
$filename = $data['filename'] ?? '';

if (!$provided_key || $provided_key !== $API_KEY) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'API key mancante o non valida']);
    write_log("GEN_TOKEN FAIL: invalid api key from " . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    exit;
}

if (!$filename) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'filename mancante']);
    exit;
}

// prevent path traversal
$filename = basename($filename);
$fullpath = $BASE_DIR . $filename;

if (!file_exists($fullpath) || !is_file($fullpath)) {
    http_response_code(404);
    echo json_encode(['status'=>'error','message'=>'file non trovato']);
    write_log("GEN_TOKEN FAIL: file not found {$fullpath} by " . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    exit;
}

// Optional: check ownership/permissions — require owner or mode?
// e.g. if you want only wallets owned by mevacoinuser
// $stat = stat($fullpath);

// generate token
$token = bin2hex(random_bytes(24));
$expires = time() + $TOKEN_TTL;

$tokens = load_tokens();
// store token -> {filename, expires, created_by_ip}
$tokens[$token] = [
    'filename' => $filename,
    'expires' => $expires,
    'created_by' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
    'created_at' => time()
];
// cleanup old tokens
foreach ($tokens as $t => $meta) {
    if ($meta['expires'] < time()) unset($tokens[$t]);
}
save_tokens($tokens);

write_log("GEN_TOKEN OK: {$token} for {$filename} by {$_SERVER['REMOTE_ADDR']} expires " . date('c',$expires));

echo json_encode(['status'=>'success','token'=>$token,'expires_at'=>date('c',$expires)]);
exit;