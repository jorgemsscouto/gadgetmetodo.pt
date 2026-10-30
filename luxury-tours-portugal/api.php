<?php
// Inquérito Luxury Tours Portugal: receção das respostas (JSON). Guarda só códigos internos neutros.

declare(strict_types=1);

require __DIR__ . '/inc/nucleo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function responder(int $codigo, array $corpo): void
{
    http_response_code($codigo);
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['ok' => false, 'error' => 'method']);
}

// Só aceita pedidos da própria página (mesma origem)
$origem = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origem !== '' && parse_url($origem, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    responder(403, ['ok' => false, 'error' => 'origin']);
}

$corpo = json_decode((string) file_get_contents('php://input', false, null, 0, 20000), true);
if (!is_array($corpo)) {
    responder(400, ['ok' => false, 'error' => 'json']);
}

// Campo isco: preenchido apenas por robôs
if (!empty($corpo['website'])) {
    responder(200, ['ok' => true]);
}

if (ltp_fechado()) {
    responder(410, ['ok' => false, 'error' => 'closed']);
}

$pdo = ltp_bd();
if (!$pdo) {
    responder(503, ['ok' => false, 'error' => 'storage']);
}

$idioma = is_string($corpo['lang'] ?? null) ? $corpo['lang'] : '';
if (!in_array($idioma, ltp_idiomas_publicados(), true)) {
    responder(422, ['ok' => false, 'error' => 'lang']);
}

$cliente = null;
$anonimo = null;
if (is_string($corpo['token'] ?? null) && $corpo['token'] !== '') {
    $cliente = ltp_cliente_por_token($corpo['token']);
    if (!$cliente) {
        responder(403, ['ok' => false, 'error' => 'token']);
    }
} else {
    if (!ltp_cfg()['acesso_publico']) {
        responder(403, ['ok' => false, 'error' => 'token']);
    }
    $anonimo = is_string($corpo['anon'] ?? null) && preg_match('/^[a-f0-9-]{16,40}$/', $corpo['anon']) ? $corpo['anon'] : null;
    if ($anonimo === null) {
        responder(422, ['ok' => false, 'error' => 'anon']);
    }
}

[$validas, $erro] = ltp_validar(is_array($corpo['answers'] ?? null) ? $corpo['answers'] : []);
if ($erro !== null) {
    responder(422, ['ok' => false, 'error' => 'invalid', 'field' => $erro]);
}

try {
    ltp_guardar_resposta($pdo, $validas, $idioma, $cliente ? (int) $cliente['id'] : null, $anonimo, $cliente ? (bool) $cliente['demo'] : false);
} catch (Throwable $e) {
    error_log('Luxury Tours Portugal: ' . $e->getMessage());
    responder(500, ['ok' => false, 'error' => 'storage']);
}

responder(200, ['ok' => true]);
