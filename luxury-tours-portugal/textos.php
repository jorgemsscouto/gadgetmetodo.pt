<?php
// Inquérito Luxury Tours Portugal: textos de um idioma para a página (ficheiro i18n com as alterações feitas no painel).
// Usado quando quem responde muda de idioma no seletor.

declare(strict_types=1);

require __DIR__ . '/inc/nucleo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$idioma = is_string($_GET['lang'] ?? null) ? $_GET['lang'] : '';
if (!in_array($idioma, ltp_idiomas_publicados(), true)) {
    http_response_code(404);
    echo '{}';
    exit;
}
echo json_encode(ltp_textos($idioma), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
