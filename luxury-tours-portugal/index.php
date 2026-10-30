<?php
// Inquérito Luxury Tours Portugal: página pública. O conteúdo é desenhado por assets/js/inquerito.js
// a partir dos ficheiros de tradução em i18n/; aqui só se resolve o idioma inicial e o convite.
// A tradução automática do navegador fica desligada (translate="no"): o inquérito tem as suas próprias traduções.

declare(strict_types=1);

require __DIR__ . '/inc/nucleo.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$publicados = ltp_idiomas_publicados();
$token = is_string($_GET['t'] ?? null) ? $_GET['t'] : '';
$cliente = $token !== '' ? ltp_cliente_por_token($token) : null;
$idioma = ltp_resolver_idioma(is_string($_GET['lang'] ?? null) ? $_GET['lang'] : null, $cliente['idioma'] ?? null, $publicados);

// Sem convite válido e sem acesso público, ou sem nenhum idioma publicado: página indisponível
$indisponivel = $idioma === null || (!$cliente && !ltp_cfg()['acesso_publico']);
$idiomaPagina = $idioma ?? LTP_IDIOMA_PADRAO;
$textos = ltp_textos($idiomaPagina);

$anterior = $cliente ? ltp_resposta_do_cliente((int) $cliente['id']) : null;
$fecho = ltp_data_fecho();

$dados = [
    'lang' => $idiomaPagina,
    'texts' => $textos,
    'languages' => array_map(fn($c) => ['code' => $c, 'name' => ltp_textos($c)['native_name'] ?? $c, 'dir' => ltp_textos($c)['dir'] ?? 'ltr'], $publicados),
    'reviewed' => array_values(array_filter(LTP_IDIOMAS, 'ltp_revisto')),
    'demo' => ltp_demo(),
    'state' => $indisponivel ? 'unavailable' : (ltp_fechado() ? 'closed' : 'open'),
    'token' => $cliente ? $token : null,
    'name' => $cliente['nome'] ?? '',
    'form' => in_array($cliente['tratamento'] ?? '', LTP_TRATAMENTOS, true) ? $cliente['tratamento'] : '',
    'previous' => $anterior ? ltp_resposta_para_pagina($anterior) : null,
    'closeDate' => $fecho ? $fecho->format('Y-m-d') : null,
    'privacyUrl' => (string) ltp_cfg()['privacidade_url'],
    'structure' => [
        'steps' => LTP_PASSOS,
        'regions' => LTP_REGIOES,
        'maxRegions' => LTP_MAX_REGIOES,
        'maxPriorities' => LTP_MAX_PRIORIDADES,
        'fields' => array_map(fn($c) => ['type' => $c[0], 'options' => $c[1], 'required' => $c[2]], LTP_CAMPOS),
        'budget' => LTP_ORCAMENTO_VALORES,
        'maxComment' => LTP_MAX_COMENTARIO,
        'maxOther' => LTP_MAX_OUTRA,
        'maxName' => LTP_MAX_NOME,
        'maps' => array_combine(LTP_REGIOES, array_map('ltp_ligacao_mapa', LTP_REGIOES)),
        // Só na ligação geral: os países (códigos ISO) para a identificação; os nomes são traduzidos no navegador
        'countries' => $cliente ? [] : array_keys(ltp_paises()),
    ],
];
$json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!doctype html>
<html lang="<?= ltp_h($idiomaPagina) ?>" dir="<?= ltp_h($textos['dir'] ?? 'ltr') ?>" translate="no" class="notranslate">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="google" content="notranslate">
<meta name="referrer" content="no-referrer">
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#faf7f1">
<title><?= ltp_h($textos['meta']['title'] ?? 'Luxury Tours Portugal') ?></title>
<meta name="description" content="<?= ltp_h($textos['meta']['description'] ?? '') ?>">
<link rel="icon" type="image/png" href="assets/img/selo.png">
<link rel="preload" href="assets/fonts/noto-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="assets/fonts/noto-serif-display-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= ltp_h(ltp_versao('assets/css/inquerito.css')) ?>">
</head>
<body>
<header class="topo">
  <div class="topo-barra">
    <label class="idioma">
      <span class="idioma-rotulo" data-t="ui.language"><?= ltp_h($textos['ui']['language'] ?? '') ?></span>
      <select id="idioma" name="idioma">
<?php foreach ($dados['languages'] as $l): ?>
        <option value="<?= ltp_h($l['code']) ?>" lang="<?= ltp_h($l['code']) ?>" dir="<?= ltp_h($l['dir']) ?>"<?= $l['code'] === $idiomaPagina ? ' selected' : '' ?>><?= ltp_h($l['name']) ?></option>
<?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="marca">
    <img src="assets/img/luxury-tours-portugal.png" width="1546" height="262" alt="Luxury Tours Portugal by Wine Tourism in Portugal">
  </div>
</header>

<main id="app" class="palco" tabindex="-1" aria-live="polite">
  <noscript><p class="cartao aviso"><?= ltp_h($textos['ui']['noscript'] ?? '') ?></p></noscript>
</main>

<footer class="rodape">
  <img class="rodape-marca" src="assets/img/wine-tourism-in-portugal.png" width="944" height="141" alt="Wine Tourism in Portugal">
  <p class="rodape-ligacoes" id="rodape-ligacoes"></p>
  <p class="rodape-aviso" id="rodape-aviso"></p>
</footer>

<script type="application/json" id="ltp-dados"><?= $json ?></script>
<script src="<?= ltp_h(ltp_versao('assets/js/inquerito.js')) ?>" defer></script>
</body>
</html>
