<?php
// Inquérito Luxury Tours Portugal: painel de gestão (em português).
// Cada pessoa entra com o seu email e palavra-passe; os perfis estão em inc/utilizadores.php.
// A palavra-passe de config.php (senha_painel) só serve para o primeiro acesso, até existir um administrador.

declare(strict_types=1);

require dirname(__DIR__) . '/inc/nucleo.php';
require dirname(__DIR__) . '/inc/painel.php';
require dirname(__DIR__) . '/inc/email.php';
require dirname(__DIR__) . '/inc/utilizadores.php';

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

if (isset($_SESSION['utilizador']) && time() - (int) ($_SESSION['atividade'] ?? 0) > LTP_SESSAO_MAXIMA) {
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

$pdo = ltp_bd();
$configurado = ltp_ha_utilizadores($pdo) || (string) ltp_cfg()['senha_painel'] !== '';
$eu = ltp_utilizador_sessao($pdo);
$entrou = $eu !== null;
$inicial = $entrou && (int) $eu['id'] === 0;

/* Ações (POST) */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if ($acao === 'entrar' && $configurado) {
        $email = trim((string) ($_POST['email'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');
        $u = $email !== '' && $pdo ? ltp_autenticar($pdo, $email, $senha) : null;
        if ($u) {
            session_regenerate_id(true);
            $_SESSION['utilizador'] = (int) $u['id'];
            voltar();
        }
        if ($email === '' && ltp_acesso_inicial_disponivel($pdo) && hash_equals((string) ltp_cfg()['senha_painel'], $senha)) {
            session_regenerate_id(true);
            $_SESSION['utilizador'] = 0;
            voltar('v=utilizadores', 'Acesso inicial. Crie a conta de administrador; a partir daí, a palavra-passe de configuração deixa de dar acesso.', 'aviso');
        }
        sleep(1);
        voltar('', 'Email ou palavra-passe incorretos.', 'erro');
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
    $permissoesAcoes = [
        'importar' => 'clientes', 'apagar_cliente' => 'clientes', 'gerar_demo' => 'clientes', 'apagar_demo' => 'clientes',
        'revisto' => 'idiomas', 'apagar_tudo' => 'apagar_tudo', 'criar_utilizador' => 'utilizadores',
        'editar_utilizador' => 'utilizadores', 'repor_senha' => 'utilizadores', 'apagar_utilizador' => 'utilizadores',
    ];
    if (isset($permissoesAcoes[$acao]) && !ltp_pode($eu, $permissoesAcoes[$acao])) {
        voltar('', 'O seu perfil não permite esta ação.', 'erro');
    }
    switch ($acao) {
        case 'criar_utilizador':
            $erro = ltp_criar_utilizador($pdo, (string) ($_POST['nome'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['papel'] ?? ''), (string) ($_POST['senha'] ?? ''));
            if ($erro !== null) {
                voltar('v=utilizadores', $erro, 'erro');
            }
            if ($inicial && ltp_ha_admin($pdo)) {
                unset($_SESSION['utilizador']);
                voltar('', 'Conta de administrador criada. Entre com esse email e palavra-passe.');
            }
            voltar('v=utilizadores', 'Utilizador criado. Comunique a palavra-passe inicial por um canal seguro.');
        case 'editar_utilizador':
            $id = (int) ($_POST['id'] ?? 0);
            $papel = (string) ($_POST['papel'] ?? '');
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $ativo = !empty($_POST['ativo']) ? 1 : 0;
            if (!ltp_utilizador($pdo, $id) || !isset(LTP_PAPEIS[$papel]) || mb_strlen($nome) < 2) {
                voltar('v=utilizadores', 'Dados inválidos.', 'erro');
            }
            if (($papel !== 'admin' || !$ativo) && ltp_ultimo_admin($pdo, $id)) {
                voltar('v=utilizadores', 'Tem de existir pelo menos um administrador ativo.', 'erro');
            }
            $pdo->prepare('UPDATE utilizadores SET nome = ?, papel = ?, ativo = ? WHERE id = ?')->execute([mb_substr($nome, 0, 120), $papel, $ativo, $id]);
            voltar('v=utilizadores', 'Utilizador atualizado.');
        case 'repor_senha':
            $id = (int) ($_POST['id'] ?? 0);
            $senha = (string) ($_POST['senha'] ?? '');
            if ($erro = ltp_erro_senha($senha)) {
                voltar('v=utilizadores', $erro, 'erro');
            }
            $pdo->prepare('UPDATE utilizadores SET senha = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $id]);
            voltar('v=utilizadores', 'Palavra-passe definida. Comunique-a ao utilizador por um canal seguro.');
        case 'apagar_utilizador':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $eu['id']) {
                voltar('v=utilizadores', 'Não pode apagar a sua própria conta.', 'erro');
            }
            if (ltp_ultimo_admin($pdo, $id)) {
                voltar('v=utilizadores', 'Tem de existir pelo menos um administrador ativo.', 'erro');
            }
            $pdo->prepare('DELETE FROM utilizadores WHERE id = ?')->execute([$id]);
            voltar('v=utilizadores', 'Utilizador apagado.');
        case 'alterar_senha':
            if ($inicial) {
                voltar('v=utilizadores', 'Crie primeiro a conta de administrador.', 'erro');
            }
            $atual = ltp_utilizador($pdo, (int) $eu['id']);
            $nova = (string) ($_POST['nova'] ?? '');
            if (!$atual || !password_verify((string) ($_POST['atual'] ?? ''), $atual['senha'])) {
                sleep(1);
                voltar('v=conta', 'A palavra-passe atual não está correta.', 'erro');
            }
            if ($erro = ltp_erro_senha($nova)) {
                voltar('v=conta', $erro, 'erro');
            }
            if ($nova !== (string) ($_POST['confirmar'] ?? '')) {
                voltar('v=conta', 'A confirmação não coincide com a nova palavra-passe.', 'erro');
            }
            $pdo->prepare('UPDATE utilizadores SET senha = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $atual['id']]);
            voltar('v=conta', 'Palavra-passe alterada.');
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
                $msg .= sprintf(' %d sem idioma válido (recebem o inquérito e o email em inglês).', $rel['sem_idioma']);
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
    if (in_array($descarregar, ['modelo_csv', 'mail_merge'], true) && !ltp_pode($eu, 'clientes')) {
        voltar('', 'O seu perfil não permite esta exportação.', 'erro');
    }
    if ($descarregar === 'modelo_csv') {
        ltp_enviar_csv('modelo-clientes.csv', ['name', 'email', 'language', 'form', 'country', 'reference'], [
            ['Anna Smith', 'anna.smith@example.com', 'en', 'f', 'United Kingdom', 'C-0001'],
            ['Sr. João Silva', 'joao.silva@example.com', 'pt', 'm', 'Portugal', 'C-0002'],
            ['Νίκος Παπαδόπουλος', 'nikos@example.com', 'el', 'm', 'Ελλάδα', 'C-0003'],
            ['Frau Müller', 'mueller@example.com', 'de', 'f', 'Deutschland', 'C-0004'],
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
$vistas = ['resumo' => 'Resumo'];
if (ltp_pode($eu, 'clientes')) {
    $vistas['clientes'] = 'Clientes e convites';
}
$vistas['respostas'] = 'Respostas';
if (ltp_pode($eu, 'idiomas')) {
    $vistas['idiomas'] = 'Idiomas';
}
$vistas['emails'] = 'Email de convite';
if (ltp_pode($eu, 'utilizadores')) {
    $vistas['utilizadores'] = 'Utilizadores';
}
if ($inicial) {
    $vistas = ['utilizadores' => 'Utilizadores'];
    $vista = 'utilizadores';
} elseif (!isset($vistas[$vista]) && $vista !== 'conta') {
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
    <span class="quem"><?= ltp_h($eu['nome']) ?> <span class="suave">· <?= ltp_h(LTP_PAPEIS[$eu['papel']]) ?></span></span>
<?php if (!$inicial): ?>
    <a href="?v=conta"<?= $vista === 'conta' ? ' aria-current="page"' : '' ?>>A minha conta</a>
<?php endif; ?>
    <a href="../" target="_blank" rel="noopener">Ver inquérito</a>
    <form method="post"><?= campo_csrf() ?><button type="submit" name="acao" value="sair" class="ligacao">Sair</button></form>
  </div>
<?php endif; ?>
</header>

<?php if (!$configurado): ?>
<main class="conteudo estreito">
  <section class="bloco">
    <h1>Falta configurar o painel</h1>
    <p>Para o primeiro acesso, copie <code>config.exemplo.php</code> para <code>config.php</code> (na pasta <code>luxury-tours-portugal</code>), defina uma palavra-passe em <code>senha_painel</code> e publique o ficheiro. Com ela, crie a conta de administrador; a partir daí, cada pessoa entra com o seu email. O <code>config.php</code> fica fora do Git.</p>
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
      <label>Email <input type="email" name="email" autocomplete="username" autofocus<?= ltp_acesso_inicial_disponivel($pdo) ? '' : ' required' ?>></label>
      <label>Palavra-passe <input type="password" name="senha" autocomplete="current-password" required></label>
      <button type="submit" class="botao">Entrar</button>
    </form>
<?php if (ltp_acesso_inicial_disponivel($pdo)): ?>
    <p class="nota">Primeiro acesso: deixe o email em branco e use a palavra-passe de configuração (<code>senha_painel</code> em <code>config.php</code>). Serve apenas para criar a conta de administrador.</p>
<?php endif; ?>
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
    <?php if (ltp_demo()): ?>Modo de demonstração ativo: o inquérito mostra os <?= count(LTP_IDIOMAS) ?> idiomas, com a indicação "protótipo" e o aviso de tradução por rever. <?php endif; ?>
    <?php if ($temDemo): ?>Os números incluem dados de demonstração fictícios.
      <?php if (ltp_pode($eu, 'clientes')): ?><form method="post" class="em-linha" data-confirmar="Apagar todos os dados de demonstração?"><?= campo_csrf() ?><button type="submit" name="acao" value="apagar_demo" class="ligacao">Apagar dados de demonstração</button></form><?php endif; ?>
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

<?php if (!$temDemo && $n === 0 && ltp_pode($eu, 'clientes')): ?>
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
        <li><code>language</code> ou <code>idioma</code>: pt, en, es, fr, de, el, ar ("gr" é lido como grego). Em falta ou inválido: inglês</li>
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
      <h2>Ficheiro para envio em série (mail merge)</h2>
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
          <td><?= ltp_h($c['nome']) ?><?= $c['demo'] ? ' <span class="etiqueta">fictício</span>' : '' ?></td>
          <td><?= ltp_h($c['email']) ?></td>
          <td><?= $c['idioma'] ? ltp_h(LTP_NOMES_IDIOMAS[$c['idioma']] ?? $c['idioma']) : '<span class="suave">Inglês (sem idioma na lista)</span>' ?></td>
          <td><?= $c['respondeu'] ? 'Respondeu em ' . ltp_h(ltp_data_pt($c['respondeu'], false)) . ($c['idioma_resposta'] && $c['idioma_resposta'] !== $c['idioma'] ? ' (' . ltp_h(LTP_NOMES_IDIOMAS[$c['idioma_resposta']]) . ')' : '') : '<span class="suave">Por responder</span>' ?></td>
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
<?php if (ltp_pode($eu, 'apagar_tudo')): ?>
    <form method="post" class="formulario perigo-zona" data-confirmar="Apagar TODOS os clientes e respostas? Esta ação não pode ser desfeita.">
      <?= campo_csrf() ?>
      <label>Para apagar todos os clientes e respostas (reais e de demonstração), escreva APAGAR <input type="text" name="confirmar" autocomplete="off"></label>
      <button type="submit" name="acao" value="apagar_tudo" class="botao perigo">Apagar tudo</button>
    </form>
<?php endif; ?>
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
        <td class="sem-quebra"><?= ltp_h(ltp_data_pt($r['atualizado'])) ?></td>
        <td><?= ltp_h(LTP_NOMES_IDIOMAS[$r['idioma']] ?? $r['idioma']) ?></td>
        <td><?php if ($r['cliente_id']): ?><?= ltp_h($r['nome']) ?><?php else: ?><?= ltp_h($r['ident_nome'] ?: 'Sem identificação') ?> <span class="etiqueta">ligação geral</span><br><span class="suave"><?= ltp_h(implode(' · ', array_filter([$r['ident_email'], $r['ident_telemovel'], ltp_paises()[$r['ident_pais'] ?? ''] ?? '']))) ?></span><?php endif; ?><?= $r['demo'] ? ' <span class="etiqueta">fictício</span>' : '' ?></td>
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

<?php elseif ($vista === 'utilizadores'):
    $utilizadores = $pdo->query('SELECT * FROM utilizadores ORDER BY ativo DESC, nome COLLATE NOCASE')->fetchAll(PDO::FETCH_ASSOC);
?>
  <h1>Utilizadores</h1>
  <p class="nota">Pessoas da empresa com acesso a este painel. Cada utilizador entra com o seu email e a sua palavra-passe (mínimo <?= LTP_SENHA_MINIMA ?> caracteres), que pode alterar em "A minha conta".</p>
  <div class="grelha">
    <section class="bloco">
      <h2><?= $inicial ? 'Criar a conta de administrador' : 'Novo utilizador' ?></h2>
      <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <label>Nome <input type="text" name="nome" required maxlength="120" autocomplete="off"></label>
        <label>Email <input type="email" name="email" required autocomplete="off"></label>
        <label>Perfil
          <select name="papel"<?= $inicial ? ' disabled' : '' ?>>
<?php foreach (LTP_PAPEIS as $c => $n): ?>
            <option value="<?= $c ?>"<?= $c === ($inicial ? 'admin' : 'gestor') ? ' selected' : '' ?>><?= ltp_h($n) ?></option>
<?php endforeach; ?>
          </select>
        </label>
<?php if ($inicial): ?>
        <input type="hidden" name="papel" value="admin">
<?php endif; ?>
        <label>Palavra-passe inicial <input type="password" name="senha" required minlength="<?= LTP_SENHA_MINIMA ?>" autocomplete="new-password"></label>
        <button type="submit" name="acao" value="criar_utilizador" class="botao">Criar</button>
      </form>
    </section>
    <section class="bloco">
      <h2>Perfis</h2>
      <dl class="perfis">
<?php foreach (LTP_PAPEIS as $c => $n): ?>
        <dt><?= ltp_h($n) ?></dt><dd><?= ltp_h(LTP_DESCRICAO_PAPEIS[$c]) ?></dd>
<?php endforeach; ?>
      </dl>
    </section>
  </div>
<?php if ($utilizadores): ?>
  <section class="bloco">
    <h2>Lista (<?= count($utilizadores) ?>)</h2>
    <div class="tabela-rolante">
    <table class="tabela utilizadores">
      <thead><tr><th scope="col">Nome</th><th scope="col">Email</th><th scope="col">Perfil</th><th scope="col">Ativo</th><th scope="col">Último acesso</th><th scope="col"><span class="sr">Guardar</span></th><th scope="col">Nova palavra-passe</th><th scope="col"><span class="sr">Apagar</span></th></tr></thead>
      <tbody>
<?php foreach ($utilizadores as $u): $f = 'u' . (int) $u['id']; ?>
        <tr<?= (int) $u['ativo'] ? '' : ' class="inativo"' ?>>
          <td><input form="<?= $f ?>" type="text" name="nome" value="<?= ltp_h($u['nome']) ?>" required maxlength="120" aria-label="Nome"><?= (int) $u['id'] === (int) $eu['id'] ? ' <span class="etiqueta">eu</span>' : '' ?></td>
          <td><?= ltp_h($u['email']) ?></td>
          <td><select form="<?= $f ?>" name="papel" aria-label="Perfil de <?= ltp_h($u['nome']) ?>">
<?php foreach (LTP_PAPEIS as $c => $n): ?>
            <option value="<?= $c ?>"<?= $c === $u['papel'] ? ' selected' : '' ?>><?= ltp_h($n) ?></option>
<?php endforeach; ?>
          </select></td>
          <td><input form="<?= $f ?>" type="checkbox" name="ativo" value="1"<?= (int) $u['ativo'] ? ' checked' : '' ?> aria-label="Ativo"></td>
          <td class="sem-quebra suave"><?= $u['ultimo_acesso'] ? ltp_h(ltp_data_pt($u['ultimo_acesso'])) : 'Nunca' ?></td>
          <td><form method="post" id="<?= $f ?>"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button type="submit" name="acao" value="editar_utilizador" class="botao botao-secundario">Guardar</button></form></td>
          <td><form method="post" class="em-linha-campos"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="password" name="senha" minlength="<?= LTP_SENHA_MINIMA ?>" required autocomplete="new-password" aria-label="Nova palavra-passe de <?= ltp_h($u['nome']) ?>"><button type="submit" name="acao" value="repor_senha" class="ligacao">Definir</button></form></td>
          <td><?php if ((int) $u['id'] !== (int) $eu['id']): ?><form method="post" data-confirmar="Apagar o utilizador <?= ltp_h($u['nome']) ?>?"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button type="submit" name="acao" value="apagar_utilizador" class="ligacao perigo">Apagar</button></form><?php endif; ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="nota">Desativar um utilizador corta o acesso de imediato, sem apagar a conta. Tem de existir sempre pelo menos um administrador ativo.</p>
  </section>
<?php endif; ?>

<?php elseif ($vista === 'conta'): ?>
  <h1>A minha conta</h1>
  <div class="grelha">
    <section class="bloco">
      <h2>Dados</h2>
      <dl class="perfis">
        <dt>Nome</dt><dd><?= ltp_h($eu['nome']) ?></dd>
        <dt>Email</dt><dd><?= ltp_h($eu['email']) ?></dd>
        <dt>Perfil</dt><dd><?= ltp_h(LTP_PAPEIS[$eu['papel']]) ?>: <?= ltp_h(LTP_DESCRICAO_PAPEIS[$eu['papel']]) ?></dd>
      </dl>
    </section>
    <section class="bloco">
      <h2>Alterar a palavra-passe</h2>
      <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="text" name="email" value="<?= ltp_h($eu['email']) ?>" autocomplete="username" hidden>
        <label>Palavra-passe atual <input type="password" name="atual" required autocomplete="current-password"></label>
        <label>Nova palavra-passe (mínimo <?= LTP_SENHA_MINIMA ?> caracteres) <input type="password" name="nova" required minlength="<?= LTP_SENHA_MINIMA ?>" autocomplete="new-password"></label>
        <label>Confirmar a nova palavra-passe <input type="password" name="confirmar" required minlength="<?= LTP_SENHA_MINIMA ?>" autocomplete="new-password"></label>
        <button type="submit" name="acao" value="alterar_senha" class="botao">Alterar</button>
      </form>
    </section>
  </div>
<?php endif; ?>
<?php endif; ?>
</main>
<?php endif; ?>
<script src="../<?= ltp_h(ltp_versao('assets/js/painel.js')) ?>" defer></script>
</body>
</html>
