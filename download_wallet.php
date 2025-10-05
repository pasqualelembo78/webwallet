<?php
$token = $_GET['token'] ?? '';
$tokenFile = "/tmp/wallet_token_$token.json";
if (!file_exists($tokenFile)) {
    http_response_code(403);
    echo "Token non valido o scaduto.";
    exit;
}
$data = json_decode(file_get_contents($tokenFile), true);
if (!$data || time() > ($data['expires'] ?? 0)) {
    unlink($tokenFile);
    http_response_code(403);
    echo "Token scaduto.";
    exit;
}
$walletFile = $data['file'];
if (!file_exists($walletFile)) {
    http_response_code(404);
    echo "Wallet non trovato.";
    exit;
}
// Invio file con headers
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($walletFile).'"');
header('Content-Length: '.filesize($walletFile));
readfile($walletFile);
unlink($tokenFile);
exit;
