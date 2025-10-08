<?php
// Abilita CORS per richieste da qualsiasi origine
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Accept");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Rispondi subito alle richieste preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// api.php - proxy e helper verso wallet-api (Mevacoin)
// Debug: /tmp/api_debug.log e /tmp/curl_verbose.log

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = '/tmp/api_debug.log';
$verboseFile = '/tmp/curl_verbose.log';

// Configurazioni (puoi sovrascrivere con variabili d'ambiente)
$apiHost = getenv('MEVACOIN_API_HOST') ?: 'https://www.mevacoin.com/';
$apiKey  = getenv('API_KEY') ?: 'desy2011';

if (!file_exists($logFile)) @touch($logFile);
if (!file_exists($verboseFile)) @touch($verboseFile);
@chmod($logFile, 0664);
@chmod($verboseFile, 0664);

file_put_contents($logFile, "===== Nuova chiamata ===== ".date('Y-m-d H:i:s')."\n", FILE_APPEND);

set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logFile){
    file_put_contents($logFile, "PHP Error [$errno] $errstr in $errfile:$errline\n", FILE_APPEND);
});
register_shutdown_function(function() use ($logFile){
    $error = error_get_last();
    if ($error) file_put_contents($logFile, "Fatal error: ".print_r($error,true)."\n", FILE_APPEND);
});

// Read input
$inputRaw = file_get_contents('php://input');
file_put_contents($logFile, "Input raw: $inputRaw\n", FILE_APPEND);
$input = json_decode($inputRaw, true) ?: [];
file_put_contents($logFile, "Input decodificato: ".print_r($input, true)."\n", FILE_APPEND);

// Basic params (sanitizzo il filename)
$action    = $input['action'] ?? 'create';
$filename  = isset($input['filename']) ? basename($input['filename']) : 'testwallet_1.wallet';
$password  = $input['password'] ?? 'desy2011';
$address   = $input['address'] ?? '';
$amount    = $input['amount'] ?? 0;
$paymentID = $input['paymentID'] ?? '';
$keys_spend = $input['spend_key'] ?? '';
$keys_view  = $input['view_key'] ?? '';
$mnemonic   = $input['mnemonic'] ?? '';
$filepath   = $input['filepath'] ?? '/tmp/export_wallet.json';
$startHeight = isset($input['startHeight']) ? intval($input['startHeight']) : null;
$endHeight   = isset($input['endHeight']) ? intval($input['endHeight']) : null;

// base dir per file wallet nel filesystem del demone (adatta al tuo sistema)
$baseDir  = '/opt/mevacoin/build/src/';
$fullPath = $baseDir . $filename;

$url = '';
$method = 'GET'; // default
$payload = null; // array or null

// Build request depending on action
switch ($action) {
    //
    // WALLET: create / open / import / close
    //
    case 'create':
        $url = $apiHost . '/wallet/create';
        $method = 'POST';
        $payload = ['filename' => $fullPath, 'password' => $password];
        break;
    case 'open':
        $url = $apiHost . '/wallet/open';
        $method = 'POST';
        $payload = ['filename' => $fullPath, 'password' => $password];
        break;
    case 'close':
        $url = $apiHost . '/wallet';
        $method = 'DELETE';
        break;
    case 'import_seed':
    case 'import/seed':
        $url = $apiHost . '/wallet/import/seed';
        $method = 'POST';
        $payload = ['filename' => $fullPath, 'password' => $password, 'mnemonic' => $mnemonic];
        break;
    case 'import_key':
    case 'import/key':
        $url = $apiHost . '/wallet/import/key';
        $method = 'POST';
        $payload = ['filename' => $fullPath, 'password' => $password, 'privateSpendKey' => $keys_spend, 'privateViewKey' => $keys_view];
        break;
    case 'import_view':
    case 'import/view':
        $url = $apiHost . '/wallet/import/view';
        $method = 'POST';
        $payload = ['filename' => $fullPath, 'password' => $password, 'publicSpendKey' => $input['publicSpendKey'] ?? '', 'privateViewKey' => $keys_view];
        break;

    //
    // ADDRESSES
    //
    case 'addresses':
        $url = $apiHost . '/addresses';
        $method = 'GET';
        break;
    case 'address': // primary
        $url = $apiHost . '/addresses/primary';
        $method = 'GET';
        break;
    case 'address_create':
        $url = $apiHost . '/addresses/create';
        $method = 'POST';
        $payload = (isset($input['label']) ? ['label' => $input['label']] : null);
        break;
    case 'address_import':
        $url = $apiHost . '/addresses/import';
        $method = 'POST';
        $payload = ['privateSpendKey' => $keys_spend];
        break;
    case 'address_import_view':
        $url = $apiHost . '/addresses/import/view';
        $method = 'POST';
        $payload = ['publicSpendKey' => $input['publicSpendKey'] ?? '', 'address' => $input['address'] ?? ''];
        break;
    case 'address_delete':
        if (empty($address)) { echo json_encode(['status'=>'error','message'=>'Serve l\'indirizzo da cancellare']); exit; }
        $url = $apiHost . '/addresses/' . rawurlencode($address);
        $method = 'DELETE';
        break;
    case 'address_integrated':
        if (empty($address) || !isset($input['paymentID'])) { echo json_encode(['status'=>'error','message'=>'Serve address e paymentID']); exit; }
        $url = $apiHost . '/addresses/' . rawurlencode($address) . '/' . rawurlencode($input['paymentID']);
        $method = 'GET';
        break;

    //
    // KEYS / SEED
    //
    case 'keys':
        $url = $apiHost . '/keys';
        $method = 'GET';
        break;
    case 'keys_address':
        if (empty($address)) { echo json_encode(['status'=>'error','message'=>'Serve l\'indirizzo per keys']); exit; }
        $url = $apiHost . '/keys/' . rawurlencode($address);
        $method = 'GET';
        break;
    case 'keys_mnemonic':
    case 'mnemonic':
        if (empty($address)) { echo json_encode(['status'=>'error','message'=>'Serve l\'indirizzo per mnemonic']); exit; }
        $url = $apiHost . '/keys/mnemonic/' . rawurlencode($address);
        $method = 'GET';
        break;

    //
    // TRANSAZIONI
    //
    case 'transactions':
        $url = $apiHost . '/transactions';
        $method = 'GET';
        break;
    case 'transactions_hash':
        if (empty($input['hash'])) { echo json_encode(['status'=>'error','message'=>'Serve hash']); exit; }
        $url = $apiHost . '/transactions/hash/' . rawurlencode($input['hash']);
        $method = 'GET';
        break;
    case 'transactions_unconfirmed':
        $url = $apiHost . '/transactions/unconfirmed';
        $method = 'GET';
        break;
    case 'transactions_unconfirmed_addr':
        if (empty($address)) { echo json_encode(['status'=>'error','message'=>'Serve indirizzo']); exit; }
        $url = $apiHost . '/transactions/unconfirmed/' . rawurlencode($address);
        $method = 'GET';
        break;
    case 'transactions_start':
        if ($startHeight === null) { echo json_encode(['status'=>'error','message'=>'Serve startHeight']); exit; }
        $url = $apiHost . '/transactions/' . intval($startHeight);
        $method = 'GET';
        break;
    case 'transactions_range':
        if ($startHeight === null || $endHeight === null) { echo json_encode(['status'=>'error','message'=>'Serve startHeight ed endHeight']); exit; }
        $url = $apiHost . '/transactions/' . intval($startHeight) . '/' . intval($endHeight);
        $method = 'GET';
        break;
    case 'transactions_address_range':
        if (empty($address) || $startHeight === null || $endHeight === null) { echo json_encode(['status'=>'error','message'=>'Serve address, startHeight ed endHeight']); exit; }
        $url = $apiHost . '/transactions/address/' . rawurlencode($address) . '/' . intval($startHeight) . '/' . intval($endHeight);
        $method = 'GET';
        break;
    case 'transactions_send_basic':
    case 'send':
        $url = $apiHost . '/transactions/send/basic';
        $method = 'POST';
        $payload = ['address' => $address, 'amount' => (int)$amount, 'paymentID' => $paymentID];
        break;
    case 'transactions_prepare_basic':
        $url = $apiHost . '/transactions/prepare/basic';
        $method = 'POST';
        $payload = ['address' => $address, 'amount' => (int)$amount, 'paymentID' => $paymentID];
        break;
    case 'transactions_send_advanced':
        $url = $apiHost . '/transactions/send/advanced';
        $method = 'POST';
        $payload = $input['tx'] ?? null;
        break;
    case 'transactions_prepare_advanced':
        $url = $apiHost . '/transactions/prepare/advanced';
        $method = 'POST';
        $payload = $input['tx'] ?? null;
        break;
    case 'transactions_send_prepared':
        if (empty($input['prepared'])) { echo json_encode(['status'=>'error','message'=>'Serve il prepared tx']); exit; }
        $url = $apiHost . '/transactions/send/prepared';
        $method = 'POST';
        $payload = ['prepared' => $input['prepared']];
        break;
    case 'transactions_cancel_prepared':
        if (empty($input['hash'])) { echo json_encode(['status'=>'error','message'=>'Serve hash della prepared']); exit; }
        $url = $apiHost . '/transactions/prepared/' . rawurlencode($input['hash']);
        $method = 'DELETE';
        break;
    case 'transactions_fusion_basic':
        $url = $apiHost . '/transactions/send/fusion/basic';
        $method = 'POST';
        $payload = $input['params'] ?? null;
        break;
    case 'transactions_fusion_advanced':
        $url = $apiHost . '/transactions/send/fusion/advanced';
        $method = 'POST';
        $payload = $input['params'] ?? null;
        break;
    case 'transactions_privatekey':
        if (empty($input['hash'])) { echo json_encode(['status'=>'error','message'=>'Serve hash TX']); exit; }
        $url = $apiHost . '/transactions/privatekey/' . rawurlencode($input['hash']);
        $method = 'GET';
        break;

    //
    // BALANCE
    //
    case 'balance':
        $url = $apiHost . '/balance';
        $method = 'GET';
        break;
    case 'balance_addr':
        if (empty($address)) { echo json_encode(['status'=>'error','message'=>'Serve indirizzo per bilancio']); exit; }
        $url = $apiHost . '/balance/' . rawurlencode($address);
        $method = 'GET';
        break;
    case 'balances':
        $url = $apiHost . '/balances';
        $method = 'GET';
        break;

    //
    // MISC
    //
    case 'save':
        $url = $apiHost . '/save';
        $method = 'PUT';
        break;
    case 'export_json':
        $url = $apiHost . '/export/json';
        $method = 'POST';
        $payload = ['filepath' => $filepath];
        break;
    case 'reset':
        $url = $apiHost . '/reset';
        $method = 'PUT';
        $payload = (isset($input['startHeight']) ? ['startHeight' => intval($input['startHeight'])] : null);
        break;
    case 'addresses_validate':
        $url = $apiHost . '/addresses/validate';
        $method = 'POST';
        $payload = ['address' => $address];
        break;
    case 'status':
        $url = $apiHost . '/status';
        $method = 'GET';
        break;

    //
    // DOWNLOAD WALLET (genera token temporaneo)
    //
    case 'download_wallet':
        // Controllo file esista
        if (!file_exists($fullPath)) {
            echo json_encode(['status'=>'error','message'=>"File wallet non trovato: $filename"]);
            exit;
        }
        // Genero un token temporaneo
        try {
            $token = bin2hex(random_bytes(16));
            $tokenFile = sys_get_temp_dir() . "/wallet_token_$token.json";
            file_put_contents($tokenFile, json_encode(['file'=>$fullPath, 'expires'=>time()+300]));
            chmod($tokenFile, 0644);
            echo json_encode(['status'=>'success','token'=>$token]);
            exit;
        } catch (Exception $e) {
            file_put_contents($logFile, "Errore generazione token: ".$e->getMessage()."\n", FILE_APPEND);
            echo json_encode(['status'=>'error','message'=>'Impossibile generare token']);
            exit;
        }
        break;

    //
    // NODE
    //
    case 'node_get':
        $url = $apiHost . '/node';
        $method = 'GET';
        break;
    case 'node_put':
        $url = $apiHost . '/node';
        $method = 'PUT';
        $payload = ['node' => $input['node'] ?? '', 'port' => intval($input['port'] ?? 0)];
        break;

    //
    // CUSTOM: invia qualunque endpoint (path deve iniziare con /)
    //
    case 'custom':
        $endpoint = $input['endpoint'] ?? '';
        if (empty($endpoint) || $endpoint[0] !== '/') { echo json_encode(['status'=>'error','message'=>'endpoint custom mancante (deve iniziare con /)']); exit; }
        $url = rtrim($apiHost, '/') . $endpoint;
        $method = strtoupper($input['method'] ?? 'GET');
        $payload = $input['data'] ?? null;
        break;

    default:
        echo json_encode(['status'=>'error','message'=>'Azione non supportata: '.$action]);
        exit;
}

// Se $url non è stato impostato (ad esempio per download_wallet abbiamo già fatto exit), errore
if (empty($url)) {
    echo json_encode(['status'=>'error','message'=>'Nessuna URL impostata per l\'azione richiesta']);
    exit;
}

// Prepare curl
$ch = curl_init($url);
$verboseHandle = fopen($verboseFile, 'a+');

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_STDERR, $verboseHandle);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// headers - X-API-KEY from env
$headers = ["Accept: application/json"];
if (!empty($apiKey)) $headers[] = "X-API-KEY: $apiKey";

// If payload present, add content-type
if (!is_null($payload)) $headers[] = 'Content-Type: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Set method & body
$method = strtoupper($method);
if ($method === 'GET') {
    // default - nothing to do
} elseif ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    if (!is_null($payload)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
} elseif ($method === 'PUT') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    if (!is_null($payload)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
} elseif ($method === 'DELETE') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    if (!is_null($payload)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
} else {
    // Other custom methods
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if (!is_null($payload)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
}

// Exec
$response = curl_exec($ch);
$curlError = curl_error($ch);
$curlInfo = curl_getinfo($ch);
$httpCode = $curlInfo['http_code'] ?? 0;

if (is_resource($verboseHandle)) fclose($verboseHandle);
curl_close($ch);

file_put_contents($logFile, "Request: [$method] $url\nPayload: ".json_encode($payload)."\nHTTP_CODE: $httpCode\nCurlErr: $curlError\nResponse: $response\n\n", FILE_APPEND);

// Decodifica la risposta JSON interna, se valida
$decodedResponse = json_decode($response, true);

// Prepara output uniforme
$output = [
    'status' => ($curlError || ($httpCode !== 200 && $httpCode !== 204 && $httpCode !== 201)) ? 'error' : 'success',
    'http_code' => $httpCode,
    'wallet_response' => $decodedResponse !== null ? $decodedResponse : $response
];

if ($curlError || ($httpCode !== 200 && $httpCode !== 204 && $httpCode !== 201)) {
    http_response_code(500);
}

echo json_encode($output, JSON_PRETTY_PRINT);
