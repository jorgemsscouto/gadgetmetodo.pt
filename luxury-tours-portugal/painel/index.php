<?php
// Inquérito Luxury Tours Portugal: painel de gestão (em português).
// Acesso com a palavra-passe definida em config.php (senha_painel).

declare(strict_types=1);

require dirname(__DIR__) . '/inc/nucleo.php';
require dirname(__DIR__) . '/inc/painel.php';
require dirname(__DIR__) . '/inc/email.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');

const LTP_SESSAO_MAXIMA = 7200;

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('ltp_painel');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/',
    'httponly' => true,
    'secure' => $https,
    'samesite' => 'Strict',
]);
session_start();

if (!empty($_SESSION['entrou']) && time() - (int) ($_SESSION['atividade'] ?? 0) > LTP_SESSAO_MAXIMA) {
    $_SESSION = [];
}
$_SESSION['atividade'] = time();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function voltar(string $consulta = '', ?string $mensagem = null, string $tipo = 'ok'): void
{
    if ($mensagem !== null) {
        $_SESSION['aviso'] = [$tipo, $mensagem];
    }
    header('Location: ./' . ($consulta !== '' ? '?' . $consulta : ''), true, 303);
    exit;
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . ltp_h($_SESSION['csrf']) . '">';
}

function idioma_filtro(): ?string
{
    $i = is_string($_GET['idioma'] ?? null) ? $_GET['idioma'] : '';
    return in_array($i, LTP_IDIOMAS, true) ? $i : null;
}

$configurado = ltp_configurado();
$entrou = $configurado && !empty($_SESSION['entrou']);
$pdo = ltp_bd();

/* Ações (POST) */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if ($acao === 'entrar' && $configurado) {
        if (hash_equals((string) ltp_cfg()['senha_painel'], (string) ($_POST['senha'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['entrou'] = true;
            voltar();
        }
        sleep(1);
        voltar('', 'Palavra-passe incorreta.', 'erro');
    }
    if (!$entrou || !hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        voltar('', 'A sessão expirou. Entre novamente.', 'erro');
    }
    if ($acao === 'sair') {
        $_SESSION = [];
        session_destroy();
        voltar();
    }
    if (!$pdo) {
        voltar('', 'Sem base de dados: o alojamento precisa de PHP com SQLite e de uma pasta com permissão de escrita.', 'erro');
    }
    switch ($acao) {
        case 'importar':
            $f = $_FILES['ficheiro'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 2 * 1024 * 1024) {
                voltar('v=clientes', 'Escolha um ficheiro CSV com menos de 2 MB.', 'erro');
            }
            $rel = ltp_importar_csv($pdo, (string) file_get_contents($f['tmp_name']));
            if (isset($rel['erro'])) {
                voltar('v=clientes', $rel['erro'], 'erro');
            }
            $msg = sprintf('Importação concluída: %d novos, %d atualizados.', $rel['inseridos'], $rel['atualizados']);
            if ($rel['sem_idioma']) {
                $msg .= sprintf(' %d sem idioma válido (a página usará o idioma do navegador ou inglês).', $rel['sem_idioma']);
            }
            if ($rel['ignorados']) {
                $msg .= ' Ignorados: ' . implode(' | ', array_slice($rel['ignorados'], 0, 10)) . (count($rel['ignorados']) > 10 ? ' (…)' : '');
            }
            voltar('v=clientes', $msg, $rel['ignorados'] ? 'aviso' : 'ok');
        case 'apagar_cliente':
            $pdo->prepare('DELETE FROM clientes WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            voltar('v=clientes', 'Cliente e respetiva resposta apagados.');
        case 'revisto':
            $i = (string) ($_POST['idioma'] ?? '');
            if (in_array($i, LTP_IDIOMAS, true)) {
                ltp_definir('revisto_' . $i, ($_POST['valor'] ?? '') === '1' ? '1' : '0');
            }
            voltar('v=idiomas', 'Estado da revisão atualizado.');
        case 'gerar_demo':
            $n = ltp_gerar_demo($pdo);
            voltar('', $n ? "Dados de demonstração criados: $n clientes fictícios e respetivas respostas." : 'Os dados de demonstração já existiam.');
        case 'apagar_demo':
            ltp_apagar_demo($pdo);
            voltar('', 'Dados de demonstração apagados.');
        case 'apagar_tudo':
            if (($_POST['confirmar'] ?? '') !== 'APAGAR') {
                voltar('v=clientes', 'Para apagar todos os dados, escreva APAGAR no campo de confirmação.', 'erro');
            }
            $pdo->exec('DELETE FROM respostas');
            $pdo->exec('DELETE FROM clientes');
            voltar('v=clientes', 'Todos os clientes e respostas foram apagados.');
    }
    voltar();
}

/* Descarregamentos (GET, só com sessão) */

$descarregar = is_string($_GET['descarregar'] ?? null) ? $_GET['descarregar'] : '';
if ($entrou && $descarregar !== '') {
    $idioma = idioma_filtro();
    if ($descarregar === 'modelo_csv') {
        ltp_enviar_csv('modelo-clientes.csv', ['name', 'email', 'language', 'form', 'country', 'reference'], [
            ['Anna Smith', 'anna.smith@example.com', 'en', 'f', 'United Kingdom', 'C-0001'],
            ['Sr. João Silva', 'joao.silva@example.com', 'pt', 'm', 'Portugal', 'C-0002'],
            ['Νίκος Παπαδόπουλος', 'nikos@example.com', 'el', 'm', 'Ελλάδα', 'C-0003'],
        ]);
        exit;
    }
    if (in_array($descarregar, ['email_html', 'email_txt'], true)) {
        $lingua = $idioma ?? LTP_IDIOMA_PADRAO;
        $html = $descarregar === 'email_html';
        $corpo = $html ? ltp_email_html($lingua, '{{greeting}}', '{{link}}') : ltp_email_texto($lingua, '{{greeting}}', '{{link}}');
        header('Content-Type: ' . ($html ? 'text/html' : 'text/plain') . '; charset=utf-8');
        if (empty($_GET['ver'])) {
            header('Content-Disposition: attachment; filename="convite-' . $lingua . ($html ? '.html' : '.txt') . '"');
        }
        echo $corpo;
        exit;
    }
    if (!$pdo) {
        voltar('', 'Sem base de dados.', 'erro');
    }
    if ($descarregar === 'respostas') {
        ltp_exportar_respostas($pdo, $idioma);
        exit;
    }
    if ($descarregar === 'ranking') {
        ltp_exportar_ranking($pdo, $idioma);
        exit;
    }
    if ($descarregar === 'mail_merge') {
        ltp_exportar_mail_merge($pdo, $idioma, !empty($_GET['pendentes']));
        exit;
    }
}

/* Páginas */

$vista = is_string($_GET['v'] ?? null) ? $_GET['v'] : 'resumo';
$vistas = ['resumo' => 'Resumo', 'clientes' => 'Clientes e convites', 'respostas' => 'Respostas', 'idiomas' => 'Idiomas', 'emails' => 'Email de convite'];
if (!isset($vistas[$vista])) {
    $vista = 'resumo';
}
$aviso = $_SESSION['aviso'] ?? null;
unset($_SESSION['aviso']);
$filtro = idioma_filtro();

function seletor_idioma(?string $atual, string $vista, bool $todos = true): string
{
    $html = '<form class="filtro" method="get"><input type="hidden" name="v" value="' . ltp_h($vista) . '"><label>Idioma <select name="idioma" onchange="this.form.submit()">';
    if ($todos) {
        $html .= '<option value="">Todos</option>';
    }
    foreach (LTP_NOMES_IDIOMAS as $c => $n) {
        $html .= '<option value="' . $c . '"' . ($atual === $c ? ' selected' : '') . '>' . ltp_h($n) . '</option>';
    }
    return $html . '</select></label><noscript><button type="submit">Filtrar</button></noscript></form>';
}

function barras(array $contagem, int $total, callable $rotulo): string
{
    $max = max(1, max($contagem ?: [0]));
    $html = '<table class="barras"><tbody>';
    foreach ($contagem as $codigo => $n) {
        $largura = round(100 * $n / $max, 1);
        $pct = ltp_percentagem($n, $total);
        $html .= '<tr title="' . ltp_h($rotulo($codigo) . ': ' . $n . ' (' . $pct . ')') . '"><th scope="row">' . ltp_h($rotulo($codigo)) . '</th>'
            . '<td class="barra-celula"><span class="barra" style="inline-size:' . $largura . '%"></span></td>'
            . '<td class="num">' . $n . '</td><td class="num suave">' . $pct . '</td></tr>';
    }
    return $html . '</tbody></table>';
}

?>
<!doctype html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Painel | Inquérito Luxury Tours Portugal</title>
<link rel="icon" type="image/png" href="../assets/img/selo.png">
<link rel="stylesheet" href="../<?= ltp_h(ltp_versao('assets/css/painel.css')) ?>">
</head>
<body>
<header class="cabecalho">
  <div class="cabecalho-marca">
    <img src="../assets/img/selo.png" width="40" height="38" alt="">
    <div>
      <strong>Inquérito Luxury Tours Portugal</strong>
      <span>Painel de gestão</span>
    </div>
  </div>
<?php if ($entrou): ?>
  <div class="cabecalho-acoes">
    <a href="../" target="_blank" rel="noopener">Ver inquérito</a>
    <form method="post"><?= campo_csrf() ?><button type="submit" name="acao" value="sair" class="ligacao">Sair</button></form>
  </div>
<?php endif; ?>
</header>

<?php if (!$configurado): ?>
<main class="conteudo estreito">
  <section class="bloco">
    <h1>Falta configurar o painel</h1>
    <p>Para ativar o painel, copie <code>config.exemplo.php</code> para <code>config.php</code> (na pasta <code>luxury-tours-portugal</code>), defina a palavra-passe em <code>senha_painel</code> e publique o ficheiro. O <code>config.php</code> fica fora do Git.</p>
    <p>O inquérito funciona sem esta configuração, em modo de demonstração.</p>
  </section>
</main>
<?php elseif (!$entrou): ?>
<main class="conteudo estreito">
  <section class="bloco">
    <h1>Entrar</h1>
<?php if ($aviso): ?>
    <p class="mensagem mensagem-<?= ltp_h($aviso[0]) ?>" role="alert"><?= ltp_h($aviso[1]) ?></p>
<?php endif; ?>
    <form method="post" class="formulario">
      <input type="hidden" name="acao" value="entrar">
      <label>Palavra-passe <input type="password" name="senha" autocomplete="current-password" required autofocus></label>
      <button type="submit" class="botao">Entrar</button>
    </form>
  </section>
</main>
<?php else: ?>
<nav class="separadores" aria-label="Secções do painel">
<?php foreach ($vistas as $c => $n): ?>
  <a href="?v=<?= $c ?>"<?= $c === $vista ? ' aria-current="page"' : '' ?>><?= ltp_h($n) ?></a>
<?php endforeach; ?>
</nav>
<main class="conteudo">
<?php if ($aviso): ?>
  <p class="mensagem mensagem-<?= ltp_h($aviso[0]) ?>" role="status"><?= ltp_h($aviso[1]) ?></p>
<?php endif; ?>
<?php if (!$pdo): ?>
  <p class="mensagem mensagem-erro">Sem base de dados: o alojamento precisa de PHP com SQLite e de uma pasta com permissão de escrita.</p>
<?php else:
    $temDemo = (bool) $pdo->query('SELECT 1 FROM clientes WHERE demo = 1 UNION SELECT 1 FROM respostas WHERE demo = 1 LIMIT 1')->fetchColumn();
    if (ltp_demo() || $temDemo): ?>
  <div class="mensagem mensagem-aviso">
    <?php if (ltp_demo()): ?>Modo de demonstração ativo: o inquérito mostra os 6 idiomas, com a indicação "protótipo" e o aviso de tradução por rever. <?php endif; ?>
    <?php if ($temDemo): ?>Os números incluem dados de demonstração fictícios.
      <form method="post" class="em-linha" data-confirmar="Apagar todos os dados de demonstração?"><?= campo_csrf() ?><button type="submit" name="acao" value="apagar_demo" class="ligacao">Apagar dados de demonstração</button></form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($vista === 'resumo'):
    $respostas = ltp_respostas($pdo, $filtro);
    $n = count($respostas);
    $taxas = ltp_taxas($pdo);
    if ($filtro !== null) {
        $convidados = $taxas[$filtro]['convidados'];
        $responderam = $taxas[$filtro]['responderam'];
    } else {
        $convidados = array_sum(array_column($taxas, 'convidados'));
        $responderam = array_sum(array_column($taxas, 'responderam'));
    }
    $gerais = count(array_filter($respostas, fn($r) => $r['cliente_id'] === null));
    $contacto = count(array_filter($respostas, fn($r) => $r['contacto'] === 'contact_yes'));
    $ranking = ltp_ranking($respostas);
    $maxPontos = max(1, $ranking[0]['pontos'] ?? 0);
    $q = $filtro ? '&idioma=' . $filtro : '';
?>
  <div class="titulo-linha">
    <h1>Resumo<?= $filtro ? ': ' . ltp_h(LTP_NOMES_IDIOMAS[$filtro]) : '' ?></h1>
    <?= seletor_idioma($filtro, 'resumo') ?>
  </div>

  <div class="indicadores">
    <div class="indicador"><span class="valor"><?= $n ?></span><span class="rotulo">Respostas<?= $filtro ? ' neste idioma' : '' ?></span></div>
    <div class="indicador"><span class="valor"><?= ltp_percentagem($responderam, $convidados) ?></span><span class="rotulo">Taxa de resposta (<?= $responderam ?> de <?= $convidados ?> convidados<?= $filtro ? ' com este idioma na lista' : '' ?>)</span></div>
    <div class="indicador"><span class="valor"><?= $gerais ?></span><span class="rotulo">Respostas pela ligação geral, sem convite</span></div>
    <div class="indicador"><span class="valor"><?= ltp_percentagem($contacto, $n) ?></span><span class="rotulo">Aceitam ser contactados (<?= $contacto ?>)</span></div>
  </div>

  <section class="bloco">
    <div class="titulo-linha">
      <h2>Ranking das regiões</h2>
      <a class="botao botao-secundario" href="?descarregar=ranking<?= $q ?>">Exportar ranking (CSV)</a>
    </div>
    <p class="nota">Pontuação ponderada: 3 pontos pelo 1.º lugar, 2 pelo 2.º e 1 pelo 3.º. Em caso de empate, conta o número de vezes em 1.º lugar.</p>
<?php if ($n === 0): ?>
    <p class="vazio">Ainda não há respostas<?= $filtro ? ' neste idioma' : '' ?>.</p>
<?php else: ?>
    <div class="tabela-rolante">
    <table class="tabela ranking">
      <thead><tr><th scope="col">#</th><th scope="col">Região</th><th scope="col" class="coluna-barra">Pontos</th><th scope="col" class="num">Pontos</th><th scope="col" class="num">1.º lugar</th><th scope="col" class="num">Menções</th><th scope="col" class="num">% respondentes</th></tr></thead>
      <tbody>
<?php foreach ($ranking as $i => $l): ?>
        <tr title="<?= ltp_h(ltp_rotulo_regiao($l['codigo']) . ': ' . $l['pontos'] . ' pontos') ?>">
          <td class="posicao"><?= $i + 1 ?>.º</td>
          <th scope="row"><?= ltp_h(ltp_rotulo_regiao($l['codigo'])) ?></th>
          <td class="barra-celula"><span class="barra" style="inline-size:<?= round(100 * $l['pontos'] / $maxPontos, 1) ?>%"></span></td>
          <td class="num forte"><?= $l['pontos'] ?></td>
          <td class="num"><?= $l['primeiro'] ?></td>
          <td class="num"><?= $l['mencoes'] ?></td>
          <td class="num suave"><?= ltp_percentagem($l['mencoes'], $n) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
<?php
    $outras = array_values(array_filter(array_map(fn($r) => trim((string) $r['regiao_outra']), $respostas)));
    if ($outras): ?>
    <p class="nota">Outras regiões sugeridas: <?= ltp_h(implode('; ', $outras)) ?></p>
<?php endif; endif; ?>
  </section>

<?php if ($n > 0): ?>
  <div class="grelha">
<?php foreach (LTP_ROTULOS_CAMPOS as $campo => $titulo): ?>
    <section class="bloco">
      <h2><?= ltp_h($titulo) ?></h2>
      <?= barras(ltp_distribuicao($respostas, $campo), $n, 'ltp_rotulo_opcao') ?>
    </section>
<?php endforeach; ?>
  </div>
<?php endif; ?>

  <section class="bloco">
    <h2>Taxa de resposta por idioma</h2>
    <p class="nota">Convidados e respostas por convite contam pelo idioma da lista de clientes. As respostas pela ligação geral contam pelo idioma em que foram preenchidas.</p>
    <div class="tabela-rolante">
    <table class="tabela">
      <thead><tr><th scope="col">Idioma</th><th scope="col" class="num">Convidados</th><th scope="col" class="num">Responderam</th><th scope="col" class="num">Taxa</th><th scope="col" class="num">Ligação geral</th></tr></thead>
      <tbody>
<?php foreach ($taxas as $c => $l): if ($c === '' && !$l['convidados']) { continue; } ?>
        <tr<?= $filtro === $c ? ' class="destaque"' : '' ?>>
          <th scope="row"><?= $c === '' ? 'Sem idioma na lista' : ltp_h(LTP_NOMES_IDIOMAS[$c]) ?></th>
          <td class="num"><?= $l['convidados'] ?></td>
          <td class="num"><?= $l['responderam'] ?></td>
          <td class="num forte"><?= ltp_percentagem($l['responderam'], $l['convidados']) ?></td>
          <td class="num"><?= $l['ligacao_geral'] ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>

  <section class="bloco">
    <h2>Exportações</h2>
    <p class="acoes-linha">
      <a class="botao" href="?descarregar=respostas<?= $q ?>">Respostas (CSV)</a>
      <a class="botao botao-secundario" href="?descarregar=ranking<?= $q ?>">Ranking (CSV)</a>
    </p>
    <p class="nota">Ficheiros para Excel (separador ponto e vírgula, UTF-8), com os rótulos em português<?= $filtro ? ' e filtrados pelo idioma escolhido' : '' ?>.</p>
  </section>

<?php if (!$temDemo && $n === 0): ?>
  <section class="bloco">
    <h2>Dados de demonstração</h2>
    <p>Para apresentar o painel antes do envio real, pode criar 60 clientes fictícios com respostas simuladas. Ficam marcados e podem ser apagados à parte.</p>
    <form method="post"><?= campo_csrf() ?><button type="submit" name="acao" value="gerar_demo" class="botao">Criar dados de demonstração</button></form>
  </section>
<?php endif; ?>

<?php elseif ($vista === 'clientes'):
    $clientes = $pdo->query('SELECT c.*, r.atualizado AS respondeu, r.idioma AS idioma_resposta FROM clientes c LEFT JOIN respostas r ON r.cliente_id = c.id ORDER BY c.criado DESC, c.id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
  <h1>Clientes e convites</h1>

  <div class="grelha">
    <section class="bloco">
      <h2>Importar lista de clientes</h2>
      <p>Ficheiro CSV (gravado no Excel como "CSV UTF-8" ou "CSV separado por vírgulas"). Colunas reconhecidas na primeira linha:</p>
      <ul class="lista-colunas">
        <li><code>name</code> ou <code>nome</code>: como tratar a pessoa (por exemplo, "Anna" ou "Sr. Silva")</li>
        <li><code>email</code> (obrigatório; identifica o cliente, uma nova importação atualiza os dados)</li>
        <li><code>language</code> ou <code>idioma</code>: pt, en, es, fr, el, ar ("gr" é lido como grego). Em falta ou inválido: idioma do navegador, se suportado; caso contrário, inglês</li>
        <li><code>form</code> ou <code>tratamento</code>: m ou f, para a saudação (opcional; sem valor usa uma forma neutra)</li>
        <li><code>country</code>, <code>reference</code> (opcionais)</li>
      </ul>
      <form method="post" enctype="multipart/form-data" class="formulario">
        <?= campo_csrf() ?>
        <input type="file" name="ficheiro" accept=".csv,text/csv" required>
        <p class="acoes-linha">
          <button type="submit" name="acao" value="importar" class="botao">Importar</button>
          <a class="botao botao-secundario" href="?descarregar=modelo_csv">Modelo CSV</a>
        </p>
      </form>
    </section>

    <section class="bloco">
      <h2>Ficheiro para mail merge</h2>
      <p>Uma linha por cliente, com <code>language</code>, a saudação já no idioma certo, o assunto, a ligação pessoal e a data limite. Os modelos do email estão no separador Email de convite.</p>
      <form method="get" class="formulario">
        <input type="hidden" name="descarregar" value="mail_merge">
        <label>Idioma
          <select name="idioma"><option value="">Todos</option>
<?php foreach (LTP_NOMES_IDIOMAS as $c => $nome): ?>
            <option value="<?= $c ?>"><?= ltp_h($nome) ?></option>
<?php endforeach; ?>
          </select>
        </label>
        <label class="caixa"><input type="checkbox" name="pendentes" value="1" checked> Só clientes que ainda não responderam (para lembretes)</label>
        <button type="submit" class="botao">Exportar CSV</button>
      </form>
<?php if (!ltp_demo() && count(ltp_idiomas_publicados()) < count(LTP_IDIOMAS)): ?>
      <p class="nota">Clientes com idiomas ainda não revistos recebem a ligação no idioma disponível mais próximo (inglês, se revisto).</p>
<?php endif; ?>
    </section>
  </div>

  <section class="bloco">
    <div class="titulo-linha">
      <h2>Lista (<?= count($clientes) ?>)</h2>
    </div>
<?php if (!$clientes): ?>
    <p class="vazio">Ainda não há clientes. Importe a lista ou crie dados de demonstração no Resumo.</p>
<?php else: ?>
    <div class="tabela-rolante">
    <table class="tabela">
      <thead><tr><th scope="col">Nome</th><th scope="col">Email</th><th scope="col">Idioma</th><th scope="col">Estado</th><th scope="col">Ligação pessoal</th><th scope="col"><span class="sr">Ações</span></th></tr></thead>
      <tbody>
<?php foreach ($clientes as $c): ?>
        <tr>
          <td><?= ltp_h($c['nome']) ?><?= $c['demo'] ? ' <span class="etiqueta">demo</span>' : '' ?></td>
          <td><?= ltp_h($c['email']) ?></td>
          <td><?= $c['idioma'] ? ltp_h(LTP_NOMES_IDIOMAS[$c['idioma']] ?? $c['idioma']) : '<span class="suave">Navegador</span>' ?></td>
          <td><?= $c['respondeu'] ? 'Respondeu em ' . ltp_h(substr($c['respondeu'], 0, 10)) . ($c['idioma_resposta'] && $c['idioma_resposta'] !== $c['idioma'] ? ' (' . ltp_h(LTP_NOMES_IDIOMAS[$c['idioma_resposta']]) . ')' : '') : '<span class="suave">Por responder</span>' ?></td>
          <td><input class="ligacao-pessoal" type="text" readonly value="<?= ltp_h(ltp_ligacao_pessoal($c['token'])) ?>" aria-label="Ligação pessoal de <?= ltp_h($c['nome']) ?>"></td>
          <td><form method="post" data-confirmar="Apagar este cliente e a sua resposta?"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button type="submit" name="acao" value="apagar_cliente" class="ligacao perigo">Apagar</button></form></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
<?php endif; ?>
  </section>

  <section class="bloco">
    <h2>Dados de demonstração e eliminação</h2>
    <div class="acoes-linha">
      <form method="post"><?= campo_csrf() ?><button type="submit" name="acao" value="gerar_demo" class="botao botao-secundario">Criar dados de demonstração</button></form>
      <form method="post" data-confirmar="Apagar todos os dados de demonstração?"><?= campo_csrf() ?><button type="submit" name="acao" value="apagar_demo" class="botao botao-secundario">Apagar dados de demonstração</button></form>
    </div>
    <form method="post" class="formulario perigo-zona" data-confirmar="Apagar TODOS os clientes e respostas? Esta ação não pode ser desfeita.">
      <?= campo_csrf() ?>
      <label>Para apagar todos os clientes e respostas (reais e de demonstração), escreva APAGAR <input type="text" name="confirmar" autocomplete="off"></label>
      <button type="submit" name="acao" value="apagar_tudo" class="botao perigo">Apagar tudo</button>
    </form>
  </section>

<?php elseif ($vista === 'respostas'):
    $respostas = ltp_respostas($pdo, $filtro);
    $q = $filtro ? '&idioma=' . $filtro : '';
?>
  <div class="titulo-linha">
    <h1>Respostas (<?= count($respostas) ?>)</h1>
    <?= seletor_idioma($filtro, 'respostas') ?>
    <a class="botao" href="?descarregar=respostas<?= $q ?>">Exportar (CSV)</a>
  </div>
<?php if (!$respostas): ?>
  <p class="vazio">Ainda não há respostas<?= $filtro ? ' neste idioma' : '' ?>.</p>
<?php else: ?>
  <div class="tabela-rolante">
  <table class="tabela compacta">
    <thead><tr><th scope="col">Data</th><th scope="col">Idioma</th><th scope="col">Cliente</th><th scope="col">1.º lugar</th><th scope="col">2.º lugar</th><th scope="col">3.º lugar</th><th scope="col">Intenção</th><th scope="col">Orçamento</th><th scope="col">Contacto</th><th scope="col">Comentário</th></tr></thead>
    <tbody>
<?php foreach ($respostas as $r): ?>
      <tr>
        <td class="sem-quebra"><?= ltp_h(substr($r['atualizado'], 0, 16)) ?></td>
        <td><?= ltp_h(LTP_NOMES_IDIOMAS[$r['idioma']] ?? $r['idioma']) ?></td>
        <td><?= $r['cliente_id'] ? ltp_h($r['nome']) : '<span class="suave">Ligação geral</span>' ?><?= $r['demo'] ? ' <span class="etiqueta">demo</span>' : '' ?></td>
        <td><?= ltp_h(ltp_rotulo_regiao($r['regiao_1'])) ?></td>
        <td><?= ltp_h(ltp_rotulo_regiao($r['regiao_2'])) ?></td>
        <td><?= ltp_h(ltp_rotulo_regiao($r['regiao_3'])) ?></td>
        <td><?= ltp_h(ltp_rotulo_opcao($r['intencao'])) ?></td>
        <td><?= ltp_h(ltp_rotulo_opcao($r['orcamento'])) ?></td>
        <td><?= $r['contacto'] === 'contact_yes' ? 'Sim' : 'Não' ?></td>
        <td><?= ltp_h($r['comentario']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<?php elseif ($vista === 'idiomas'): ?>
  <h1>Idiomas e revisão das traduções</h1>
  <p class="nota">As traduções foram geradas a partir do inglês e estão marcadas como "PENDING NATIVE REVIEW" nos ficheiros <code>i18n/*.json</code>. Fora do modo de demonstração, só os idiomas com a revisão concluída aparecem no inquérito e no seletor.</p>
  <section class="bloco">
    <div class="tabela-rolante">
    <table class="tabela">
      <thead><tr><th scope="col">Idioma</th><th scope="col">Código</th><th scope="col">Direção</th><th scope="col">Ficheiro</th><th scope="col">Revisão por falante nativo</th><th scope="col">No inquérito</th><th scope="col"><span class="sr">Ações</span></th></tr></thead>
      <tbody>
<?php foreach (LTP_IDIOMAS as $c):
    $tx = ltp_textos($c);
    $rev = ltp_revisto($c);
    $publicado = in_array($c, ltp_idiomas_publicados(), true); ?>
        <tr>
          <th scope="row"><?= ltp_h(LTP_NOMES_IDIOMAS[$c]) ?> <span class="suave" lang="<?= $c ?>">(<?= ltp_h($tx['native_name'] ?? '') ?>)</span></th>
          <td><code><?= $c ?></code></td>
          <td><?= ($tx['dir'] ?? 'ltr') === 'rtl' ? 'Direita para a esquerda' : 'Esquerda para a direita' ?></td>
          <td><?= ltp_h($tx['_review'] ?? '') ?></td>
          <td><?= $rev ? '<strong class="ok">Concluída</strong>' : '<span class="pendente">Pendente</span>' ?></td>
          <td><?= $publicado ? ($rev ? 'Publicado' : 'Visível (demonstração)') : 'Oculto' ?></td>
          <td class="acoes-celula">
            <a href="../?lang=<?= $c ?>" target="_blank" rel="noopener">Pré-visualizar</a>
            <form method="post" class="em-linha"><?= campo_csrf() ?><input type="hidden" name="idioma" value="<?= $c ?>"><input type="hidden" name="valor" value="<?= $rev ? '0' : '1' ?>"><button type="submit" name="acao" value="revisto" class="ligacao"><?= $rev ? 'Reabrir revisão' : 'Marcar como revisto' ?></button></form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>

<?php elseif ($vista === 'emails'):
    $lingua = $filtro ?? LTP_IDIOMA_PADRAO;
    $exemplo = ltp_email_html($lingua, ltp_email_saudacao($lingua, 'Alex Morgan'), ltp_endereco() . '?t=EXEMPLO');
?>
  <div class="titulo-linha">
    <h1>Email de convite</h1>
    <?= seletor_idioma($lingua, 'emails', false) ?>
  </div>
  <div class="grelha grelha-email">
    <section class="bloco">
      <h2>Assunto</h2>
      <p class="assunto" lang="<?= $lingua ?>" dir="<?= ltp_h(ltp_textos($lingua)['dir'] ?? 'ltr') ?>"><?= ltp_h(ltp_email_assunto($lingua)) ?></p>
      <h2>Modelos para descarregar</h2>
      <p class="acoes-linha">
        <a class="botao" href="?descarregar=email_html&amp;idioma=<?= $lingua ?>">HTML</a>
        <a class="botao botao-secundario" href="?descarregar=email_txt&amp;idioma=<?= $lingua ?>">Texto simples</a>
        <a class="botao botao-secundario" href="?descarregar=email_html&amp;idioma=<?= $lingua ?>&amp;ver=1" target="_blank" rel="noopener">Abrir modelo</a>
      </p>
      <p class="nota">Nos modelos, <code>{{greeting}}</code> e <code>{{link}}</code> correspondem às colunas do CSV de mail merge. Ajuste a sintaxe dos campos à ferramenta de envio (Mailchimp, Brevo, Outlook ou outra). O idioma de cada envio segue a coluna <code>language</code>.</p>
      <p class="nota">Pré-visualização com dados de exemplo; as imagens carregam a partir de <?= ltp_h(ltp_endereco()) ?>.</p>
    </section>
    <section class="bloco bloco-previsao">
      <iframe class="previsao" title="Pré-visualização do email" srcdoc="<?= ltp_h($exemplo) ?>" sandbox></iframe>
    </section>
  </div>
<?php endif; ?>
<?php endif; ?>
</main>
<?php endif; ?>
<script src="../<?= ltp_h(ltp_versao('assets/js/painel.js')) ?>" defer></script>
</body>
</html>
