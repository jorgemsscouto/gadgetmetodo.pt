<?php
// Ligação do catálogo enviada na confirmação dos pedidos: regista a abertura e encaminha para o PDF.
// Sem referência válida, encaminha na mesma, sem registar nada.

declare(strict_types=1);

require __DIR__ . '/dados.php';

date_default_timezone_set('Europe/Lisbon');

$ref = (string) ($_GET['r'] ?? '');
$sig = (string) ($_GET['s'] ?? '');
$ficheiro_config = __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && preg_match('/^GM-\d{8}-[0-9A-F]{4}$/', $ref)
    && preg_match('/^[0-9a-f]{16}$/', $sig)
    && is_file($ficheiro_config)) {
    $cfg = require $ficheiro_config;
    if (!empty($cfg['chave']) && hash_equals(assinatura($cfg, $ref), $sig)) {
        try {
            guardar_abertura($cfg, $ref, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        } catch (Throwable $e) {
            error_log('Catálogo: falha ao registar abertura de ' . $ref . ': ' . $e->getMessage());
        }
    }
}

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Location: ' . CATALOGO_PDF, true, 302);
