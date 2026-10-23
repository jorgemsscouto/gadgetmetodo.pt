<?php
// Área reservada: pedidos recebidos pelo formulário e aberturas do catálogo, com exportação para Excel.

declare(strict_types=1);

require __DIR__ . '/dados.php';

date_default_timezone_set('Europe/Lisbon');

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$ficheiro_config = __DIR__ . '/config.php';
$cfg = is_file($ficheiro_config) ? require $ficheiro_config : [];
if (empty($cfg['admin_sha256'])) {
    http_response_code(503);
    exit('Acesso aos registos por configurar: corra ./configurar-formulario.sh e publique.');
}

function h(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function data_pt(string $data): string
{
    $t = strtotime($data);
    return $t ? date('d/m/Y H:i', $t) : '';
}

// Limita tentativas de entrada por origem (10 por hora)
function tentativas_excedidas(): bool
{
    $ficheiro = sys_get_temp_dir() . '/gm-registos-' . hash('sha256', 'registos|' . ($_SERVER['REMOTE_ADDR'] ?? '')) . '.json';
    $agora = time();
    $lista = is_readable($ficheiro) ? json_decode((string) @file_get_contents($ficheiro), true) : [];
    $lista = array_values(array_filter(is_array($lista) ? $lista : [], function ($t) use ($agora) { return is_int($t) && $t > $agora - 3600; }));
    if (count($lista) >= 10) {
        return true;
    }
    $lista[] = $agora;
    @file_put_contents($ficheiro, json_encode($lista), LOCK_EX);
    return false;
}

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('gm_registos');
session_set_cookie_params(['lifetime' => 0, 'path' => '/formulario/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

if (isset($_GET['sair'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: registos.php', true, 303);
    exit;
}

$erro = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['senha'])) {
    if (tentativas_excedidas()) {
        $erro = 'Demasiadas tentativas. Tente mais tarde.';
    } elseif (hash_equals((string) $cfg['admin_sha256'], hash('sha256', (string) $_POST['senha']))) {
        session_regenerate_id(true);
        $_SESSION['autenticado'] = true;
        header('Location: registos.php', true, 303);
        exit;
    } else {
        $erro = 'Palavra-passe incorreta.';
    }
}

$autenticado = !empty($_SESSION['autenticado']);
$registos = $autenticado ? ler_registos($cfg) : [];

// Exportação para Excel (separador ; e codificação UTF-8 com BOM)
if ($autenticado && ($_GET['exportar'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gadgetmetodo-pedidos-' . date('Y-m-d') . '.csv"');
    $saida = fopen('php://output', 'wb');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, ['Referência', 'Data do pedido', 'Tipo', 'Nome', 'Empresa', 'Email', 'Telemóvel', 'Serviços', 'Aberturas do catálogo', 'Aberturas prováveis automáticas', 'Primeira abertura', 'Última abertura'], ';', '"', '\\');
    foreach ($registos as $p) {
        $ab = $p['aberturas'];
        $auto = count(array_filter($ab, function ($a) { return $a['automatica']; }));
        fputcsv($saida, array_map('celula_csv', [
            $p['ref'], data_pt($p['criado']), $p['tipo'], $p['nome'], $p['empresa'] ?: 'Particular', $p['email'], $p['telemovel'], $p['servicos'],
            count($ab), $auto, $ab ? data_pt($ab[0]['aberto']) : '', $ab ? data_pt(end($ab)['aberto']) : '',
        ]), ';', '"', '\\');
    }
    fclose($saida);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$total = count($registos);
$abriram = count(array_filter($registos, function ($p) {
    return (bool) array_filter($p['aberturas'], function ($a) { return !$a['automatica']; });
}));
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Registos | Gadgetmetodo, Lda.</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/site.css">
  <style>
    .registos td:nth-child(-n+2), .registos th:nth-child(-n+2), .registos li { white-space: nowrap; }
    .registos td:last-child { min-width: 180px; }
    .registos td.quebra { min-width: 200px; }
    .etiqueta { display: inline-block; padding: 2px 8px; font-size: 0.78rem; border-radius: 6px; background: var(--nevoa); color: var(--cinzento); }
    .etiqueta.aberto { background: var(--petroleo-tinta); color: var(--profundo); font-weight: 600; }
    .registos details summary { cursor: pointer; color: var(--petroleo); }
    .resumo-numeros { display: flex; flex-wrap: wrap; gap: 24px; margin: 24px 0 32px; }
    .resumo-numeros div { padding: 16px 20px; border: 1px solid var(--cinzento-claro); border-radius: var(--raio-pequeno); }
    .resumo-numeros strong { display: block; font-size: 1.6rem; }
  </style>
</head>
<body>
  <header class="cabecalho">
    <div class="contentor">
      <a class="marca" href="/"><img src="/assets/marca/logotipo.svg" alt="Gadgetmetodo, Lda." width="234" height="42"></a>
      <?php if ($autenticado): ?><a class="botao botao-secundario" href="registos.php?sair=1">Terminar sessão</a><?php endif; ?>
    </div>
  </header>
  <main class="secao">
    <div class="contentor">
<?php if (!$autenticado): ?>
      <div class="estreito" style="padding:0">
        <span class="sobretitulo">Área reservada</span>
        <h1>Registos de pedidos</h1>
        <form method="post" class="formulario" style="max-width:420px">
          <div class="campo">
            <label for="senha">Palavra-passe</label>
            <input id="senha" name="senha" type="password" autocomplete="current-password" required style="width:100%;padding:12px 14px;border:1px solid #c9ced6;border-radius:10px;font:inherit">
            <?php if ($erro): ?><p class="erro-campo"><?= h($erro) ?></p><?php endif; ?>
          </div>
          <div><button class="botao" type="submit">Entrar</button></div>
        </form>
      </div>
<?php else: ?>
      <span class="sobretitulo">Área reservada</span>
      <h1>Pedidos e aberturas do catálogo</h1>
      <p class="texto-suave">Registos dos últimos 12 meses; os mais antigos são apagados automaticamente. As aberturas assinaladas como automáticas foram feitas por sistemas de segurança de email ou nos dois minutos seguintes ao pedido, e podem não corresponder a uma leitura real.</p>
      <div class="resumo-numeros">
        <div><strong><?= $total ?></strong>pedidos</div>
        <div><strong><?= $abriram ?></strong>abriram o catálogo</div>
        <div><strong><?= $total ? round($abriram / $total * 100) : 0 ?>%</strong>taxa de abertura</div>
      </div>
      <p><a class="botao" href="registos.php?exportar=csv">Exportar para Excel (CSV)</a></p>
      <div class="conteudo tabela-rolante">
        <table class="registos">
          <thead><tr><th>Pedido</th><th>Data</th><th>Tipo</th><th>Nome</th><th>Empresa</th><th>Contactos</th><th>Serviços</th><th>Catálogo</th></tr></thead>
          <tbody>
<?php if (!$registos): ?>
            <tr><td colspan="8">Ainda não há pedidos registados.</td></tr>
<?php endif; ?>
<?php foreach ($registos as $p):
    $reais = array_filter($p['aberturas'], function ($a) { return !$a['automatica']; }); ?>
            <tr>
              <td><?= h($p['ref']) ?></td>
              <td><?= h(data_pt($p['criado'])) ?></td>
              <td><?= h(str_replace('Pedido de ', '', $p['tipo'])) ?></td>
              <td><?= h($p['nome']) ?></td>
              <td><?= h($p['empresa'] ?: 'Particular') ?></td>
              <td><a href="mailto:<?= h($p['email']) ?>"><?= h($p['email']) ?></a><br><?= h($p['telemovel']) ?></td>
              <td class="quebra"><?= h($p['servicos']) ?></td>
              <td>
<?php if (!$p['aberturas']): ?>
                <span class="etiqueta">Não aberto</span>
<?php else: ?>
                <span class="etiqueta<?= $reais ? ' aberto' : '' ?>"><?= $reais ? 'Aberto' : 'Só automático' ?></span>
                <details><summary><?= count($p['aberturas']) ?> abertura(s)</summary>
                  <ul class="mb-0" style="padding-left:1.1em">
<?php foreach ($p['aberturas'] as $a): ?>
                    <li><?= h(data_pt($a['aberto'])) ?><?= $a['automatica'] ? ' <span class="etiqueta">automática</span>' : '' ?></li>
<?php endforeach; ?>
                  </ul>
                </details>
<?php endif; ?>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </main>
</body>
</html>
