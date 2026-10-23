<?php
// Ficha do curso em formato A4, para guardar em PDF a partir do navegador (/formacoes/ficha.php?c=ID).
// Usa os mesmos dados da página pública do curso; com ?imprimir=1 abre logo a janela de impressão.

declare(strict_types=1);

require_once __DIR__ . '/publico.php';

const MESES_FICHA = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

// Texto seguro dentro de uma cadeia de caracteres de CSS (rodapé das páginas impressas)
function texto_css(string $t): string
{
    return '"' . str_replace(['\\', '"', "\n", "\r", '<'], ['\\\\', '\\"', ' ', ' ', '\\3C '], $t) . '"';
}

function ficha_curso(): void
{
    header('Cache-Control: no-cache');
    header('X-Robots-Tag: noindex');
    $c = bd() ? linha_bd('SELECT * FROM cursos WHERE id = ? AND ativo = 1', [(int) ($_GET['c'] ?? 0)]) : null;
    if (!$c) {
        http_response_code(404);
        echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Ficha não encontrada | Gadgetmetodo, Lda.</title><link rel="stylesheet" href="' . h(versao_ficheiro('/assets/css/ficha.css')) . '"></head>'
            . '<body class="ficha-ecra"><main class="folha"><h1>Ficha não encontrada</h1><p>Este curso não existe ou já não está disponível.</p><p><a href="/formacoes/">Ver a área de formação</a></p></main></body></html>';
        return;
    }
    $mods = modulos_curso((int) $c['id']);
    $u = unidade($c);
    $min_caso = minutos_caso($c);
    $factos = factos_curso($c, $mods);
    unset($factos['Tipologia']);
    $sessoes = consulta("SELECT * FROM formacoes WHERE curso_id = ? AND inicio >= ? AND estado = 'aberta' ORDER BY inicio LIMIT 8", [$c['id'], date('Y-m-d 00:00:00')]);
    $link = SITE_CANONICO . link_curso_publico($c);
    $hoje = date('j') . ' de ' . MESES_FICHA[(int) date('n')] . ' de ' . date('Y');

    // Secções numeradas: só entram as que têm conteúdo
    $seccoes = [];
    if ($c['descricao']) {
        $seccoes[] = ['Sobre o curso', paragrafos_html((string) $c['descricao'])];
    }
    if ($c['objetivo'] || $c['resultado']) {
        $seccoes[] = ['Objetivo e resultado esperado', '<div class="duas">'
            . ($c['objetivo'] ? '<div class="caixa"><h3>Objetivo</h3>' . paragrafos_html((string) $c['objetivo']) . '</div>' : '')
            . ($c['resultado'] ? '<div class="caixa"><h3>Resultado esperado</h3>' . paragrafos_html((string) $c['resultado']) . '</div>' : '') . '</div>'];
    }
    if ($mods) {
        $linhas = '';
        $com_caso = (bool) array_filter(array_column($mods, 'caso_pratico'));
        foreach ($mods as $n => $m) {
            $linhas .= '<tr><td class="n">' . ($n + 1) . '</td><td><strong>' . h($m['nome']) . '</strong>' . ($m['horas'] ? ' <span class="suave">· ' . h(horas((string) $m['horas'])) . '</span>' : '')
                . ($m['descricao'] ? '<p>' . h($m['descricao']) . '</p>' : '') . lista_html(temas($m['temas'])) . '</td>'
                . ($com_caso ? '<td>' . h((string) $m['caso_pratico']) . '</td>' : '') . '</tr>';
        }
        $seccoes[] = ['Programa', '<table class="programa"><thead><tr><th scope="col" class="n">' . h($u) . '</th><th scope="col">Tema e conteúdos</th>'
            . ($com_caso ? '<th scope="col">Caso prático' . ($min_caso ? ' (' . $min_caso . ' min)' : '') . '</th>' : '') . '</tr></thead><tbody>' . $linhas . '</tbody></table>'];
    }
    // Sessão-tipo e avaliação lado a lado, para a ficha caber em menos páginas
    $e = estrutura_html($c);
    $a = avaliacao_html($c);
    if ($e || $a) {
        $seccoes[] = [$e && $a ? 'Sessão-tipo e avaliação' : ($e ? 'Como funciona uma sessão' : 'Avaliação'), $e && $a
            ? '<div class="duas"><div><h3>Como funciona uma sessão (minutos)</h3>' . $e . '</div><div><h3>Avaliação</h3>' . $a . '</div></div>'
            : $e . $a];
    }
    if ($r = lista_html(temas($c['requisitos']))) {
        $seccoes[] = ['Requisitos técnicos e condições', $r];
    }
    if ($c['investimento']) {
        $seccoes[] = ['Investimento', paragrafos_html((string) $c['investimento'])];
    }
    $datas = '';
    foreach ($sessoes as $f) {
        $datas .= '<li><strong>' . h(horario($f)) . '</strong> · ' . h(nome_modulo($f)) . ' · ' . h(MODALIDADES[$f['modalidade']] ?? '') . ($f['local'] && strcasecmp(trim($f['local']), MODALIDADES[$f['modalidade']] ?? '') !== 0 ? ' · ' . h($f['local']) : '') . '</li>';
    }
    $inscricoes = '<div class="duas fecho"><div>' . ($datas ? '<ul class="datas">' . $datas . '</ul>' : '<p>Ainda não há datas agendadas. Na página do curso pode fazer a pré-inscrição e receber o convite quando forem marcadas.</p>')
        . '<p>Programa, datas e inscrição online:<br><a href="' . h($link) . '">' . h(preg_replace('#^https?://#', '', $link)) . '</a></p></div>'
        . '<div class="contactos"><p class="contactos-titulo">Informações e inscrições</p><p>Gadgetmetodo, Lda.<br>Rua das Cruzes, 625, 4100-195 Porto, Portugal<br>info@gadgetmetodo.pt<br>+351 915 416 112 (chamada para a rede móvel nacional)<br>www.gadgetmetodo.pt</p></div></div>';

    $corpo = '';
    foreach ($seccoes as $n => [$titulo, $html]) {
        $corpo .= '<section class="seccao"><h2><span class="secnum">' . sprintf('%02d', $n + 1) . '</span>' . h($titulo) . '</h2>' . $html . '</section>';
    }
    if ($c['enquadramento']) {
        $corpo .= '<aside class="nota-legal"><h2>Enquadramento legal</h2>' . paragrafos_html((string) $c['enquadramento']) . '</aside>';
    }
    $corpo .= '<section class="seccao seccao-fecho"><h2><span class="secnum">' . sprintf('%02d', count($seccoes) + 1) . '</span>Próximas sessões e inscrições</h2>' . $inscricoes . '</section>';
    $titulo_doc = 'Gadgetmetodo - Ficha de curso - ' . preg_replace('/[:\/\\\\|?*"<>]+/', ' -', (string) $c['nome']);

    echo '<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>' . h($titulo_doc) . '</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="' . h(versao_ficheiro('/assets/css/ficha.css')) . '">
  <style>@page { @bottom-left { content: ' . texto_css('Gadgetmetodo, Lda.  ·  Ficha de curso: ' . $c['nome']) . '; } }</style>
</head>
<body class="ficha-ecra">
  <div class="barra-ficha">
    <div class="barra-ficha-interior">
      <a href="' . h(link_curso_publico($c)) . '">Ver a página do curso</a>
      <span class="barra-acoes">
        <button type="button" data-copiar="' . h($link) . '">Copiar ligação</button>
        <button type="button" class="principal" data-imprimir>Guardar em PDF</button>
      </span>
      <p class="estado-copia" role="status" aria-live="polite"></p>
    </div>
    <p class="barra-ajuda">Na janela de impressão, escolha "Guardar como PDF" e ative "Gráficos de fundo".</p>
  </div>
  <main class="folha ficha">
    <header class="capa">
      <div class="capa-topo">
        <img src="/assets/img/assinatura/gadgetmetodo-logotipo.png" alt="Gadgetmetodo, Lda." width="184" height="33">
        <span class="doc-tipo">Ficha de curso<br><span>' . h($hoje) . '</span></span>
      </div>
      <div class="capa-faixa">
        ' . ($c['percurso'] ? '<p class="capa-percurso">' . h($c['percurso']) . '</p>' : '') . '
        <h1>' . h($c['nome']) . '</h1>
        ' . ($c['tipologia'] ? '<p class="capa-sub">' . h($c['tipologia']) . '</p>' : '') . '
      </div>
      ' . ($factos ? '<dl class="factos">' . implode('', array_map(function ($k, $x) { return '<div' . (strlen($x) > 30 ? ' class="largo"' : '') . '><dt>' . h($k) . '</dt><dd>' . h($x) . '</dd></div>'; }, array_keys($factos), $factos)) . '</dl>' : '') . '
    </header>
    ' . $corpo . '
    <p class="nota-documento">Documento informativo gerado em ' . h($hoje) . ' a partir da página do curso, onde se encontra sempre a versão atualizada.</p>
  </main>
  <script src="' . h(versao_ficheiro('/assets/js/copiar.js')) . '" defer></script>
</body>
</html>
';
}
