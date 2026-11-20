<?php
// Versão inglesa (EN-UK) do Catálogo de Serviços: traduz documentos/catalogo/catalogo.html com o dicionário do site
// (clientes/inc/traducoes/en.json) e grava documentos/catalogo/catalogue-en.html, para converter em PDF.
// Utilização (a partir desta pasta, depois de npm run catalogo): php traduzir-catalogo.php
// As frases sem tradução ficam em português e são listadas no fim (acrescentá-las a en.json e repetir).

declare(strict_types=1);

define('IDIOMA', 'en');
function cfg(): array
{
    return ['registar_traducoes' => true];
}
require __DIR__ . '/../clientes/inc/idioma.php';

$pasta = __DIR__ . '/../documentos/catalogo';
$html = traduzir_html((string) file_get_contents($pasta . '/catalogo.html'));
$html = str_replace('<html lang="pt-PT">', '<html lang="en-GB">', $html);
file_put_contents($pasta . '/catalogue-en.html', $html);

$falta = array_keys($GLOBALS['traducoes_em_falta'] ?? []);
$GLOBALS['traducoes_em_falta'] = [];
if ($falta) {
    fwrite(STDERR, count($falta) . " frase(s) sem tradução:\n" . json_encode(array_fill_keys($falta, ''), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    exit(1);
}
echo "documentos/catalogo/catalogue-en.html gerado, sem frases em falta.\n";
