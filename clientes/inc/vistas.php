<?php
// Área de clientes: estrutura das páginas, entrada e páginas de erro.

declare(strict_types=1);

function menu_area(string $atual): string
{
    $itens = eh_admin()
        ? ['painel' => 'Painel', 'calendario' => 'Calendário', 'cursos' => 'Cursos', 'inscricoes' => 'Inscrições', 'clientes' => 'Clientes', 'utilizadores' => 'Utilizadores', 'pedidos' => 'Pedidos do site', 'conta' => 'A minha conta']
        : ['painel' => 'Painel', 'calendario' => 'Formações', 'inscricoes' => 'Inscrições', 'conta' => 'A minha conta'];
    $html = '';
    foreach ($itens as $p => $nome) {
        $html .= '<li><a href="' . h(url($p)) . '"' . ($p === $atual ? ' aria-current="page"' : '') . '>' . h($nome) . '</a></li>';
    }
    return '<nav class="area-nav" aria-label="Área reservada"><div class="contentor"><ul>' . $html . '</ul></div></nav>';
}

// Acrescenta ao endereço a data de alteração do ficheiro, para os navegadores não usarem cópias antigas
function versao_ficheiro(string $caminho): string
{
    $t = @filemtime(dirname(__DIR__, 2) . $caminho);
    return $caminho . ($t ? '?v=' . $t : '');
}

function documento(string $titulo, string $corpo): void
{
    echo '<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>' . h($titulo) . ' | Área reservada | Gadgetmetodo, Lda.</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="' . h(versao_ficheiro('/assets/css/site.css')) . '">
  <link rel="stylesheet" href="' . h(versao_ficheiro('/assets/css/area.css')) . '">
</head>
<body class="area">
' . $corpo . '
  <footer class="area-rodape"><div class="contentor">© 2026 Gadgetmetodo, Lda. · <a href="/">Website</a> · <a href="/privacidade/">Política de privacidade</a></div></footer>
  <script src="' . h(versao_ficheiro('/assets/js/copiar.js')) . '" defer></script>
</body>
</html>
';
    exit;
}

function pagina_area(string $atual, string $titulo, string $conteudo, string $sobretitulo = ''): void
{
    $u = utilizador();
    $quem = h($u['nome']) . ' · ' . h($u['papel'] === 'admin' ? 'Gadgetmetodo' : (string) $u['cliente_nome']);
    $cabecalho = '<header class="cabecalho"><div class="contentor">
    <a class="marca" href="/"><img src="/assets/marca/logotipo.svg" alt="Gadgetmetodo, Lda." width="234" height="42"></a>
    <div class="area-utilizador"><span>' . $quem . '</span><a class="botao botao-secundario" href="' . h(url('sair')) . '">Terminar sessão</a></div>
  </div></header>';
    documento($titulo, $cabecalho . menu_area($atual) . '
  <main class="secao area-conteudo"><div class="contentor">
    ' . avisos() . '
    ' . ($sobretitulo ? '<span class="sobretitulo">' . h($sobretitulo) . '</span>' : '') . '
    <h1>' . h($titulo) . '</h1>
    ' . $conteudo . '
  </div></main>');
}

function pagina_entrada(string $erro = ''): void
{
    $primeiro = (int) (linha_bd('SELECT COUNT(*) AS n FROM utilizadores')['n'] ?? 0) === 0;
    documento('Entrar', '<header class="cabecalho"><div class="contentor"><a class="marca" href="/"><img src="/assets/marca/logotipo.svg" alt="Gadgetmetodo, Lda." width="234" height="42"></a></div></header>
  <main class="secao"><div class="contentor estreito">
    ' . avisos() . '
    <span class="sobretitulo">Área reservada</span>
    <h1>Entrar</h1>
    <p class="texto-suave">Acesso reservado a clientes e à equipa da Gadgetmetodo. Para obter acesso, contacte <a href="mailto:info@gadgetmetodo.pt">info@gadgetmetodo.pt</a>.</p>
    ' . ($primeiro ? '<div class="aviso aviso-info">Primeiro acesso: entre com o email info@gadgetmetodo.pt e a palavra-passe da área reservada (<code>./configurar-formulario.sh --acesso</code>). Fica criada a conta de administração.</div>' : '') . '
    <form method="post" action="' . h(url('entrar')) . '" class="formulario" style="max-width:440px">' . campo_csrf() . '
      <div class="campo"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" required></div>
      <div class="campo"><label for="senha">Palavra-passe</label><input id="senha" name="senha" type="password" autocomplete="current-password" required>
      ' . ($erro ? '<p class="erro-campo">' . h($erro) . '</p>' : '') . '</div>
      <div><button class="botao" type="submit">Entrar</button></div>
    </form>
  </div></main>');
}

function pagina_erro(string $titulo, string $texto): void
{
    $voltar = utilizador() ? '<a class="botao" href="' . h(url('painel')) . '">Voltar ao painel</a>' : '<a class="botao" href="area.php">Entrar</a>';
    documento($titulo, '<main class="secao"><div class="contentor estreito"><span class="sobretitulo">Área reservada</span><h1>' . h($titulo) . '</h1><p class="lead">' . h($texto) . '</p><div class="acoes">' . $voltar . '</div></div></main>');
}

/* Componentes */

function opcoes_select(array $opcoes, $selecionado, string $vazio = ''): string
{
    $html = $vazio !== '' ? '<option value="">' . h($vazio) . '</option>' : '';
    foreach ($opcoes as $valor => $rotulo) {
        $html .= '<option value="' . h((string) $valor) . '"' . ((string) $valor === (string) $selecionado ? ' selected' : '') . '>' . h($rotulo) . '</option>';
    }
    return $html;
}

function campo(string $nome, string $rotulo, string $valor = '', string $tipo = 'text', string $extra = '', string $id = ''): string
{
    $id = $id ?: 'c-' . $nome;
    if ($tipo === 'textarea') {
        return '<div class="campo"><label for="' . $id . '">' . $rotulo . '</label><textarea id="' . $id . '" name="' . h($nome) . '" ' . (strpos($extra, 'rows=') === false ? 'rows="4" ' : '') . $extra . '>' . h($valor) . '</textarea></div>';
    }
    return '<div class="campo"><label for="' . $id . '">' . $rotulo . '</label><input id="' . $id . '" name="' . h($nome) . '" type="' . $tipo . '" value="' . h($valor) . '" ' . $extra . '></div>';
}

function campo_select(string $nome, string $rotulo, array $opcoes, $selecionado, string $vazio = '', string $extra = ''): string
{
    return '<div class="campo"><label for="c-' . $nome . '">' . $rotulo . '</label><select id="c-' . $nome . '" name="' . h($nome) . '" ' . $extra . '>' . opcoes_select($opcoes, $selecionado, $vazio) . '</select></div>';
}

function nivel_etiqueta(?string $nivel): string
{
    $tipos = ['Inicial' => 'nivel1', 'Exploratório' => 'nivel2', 'Em adoção' => 'nivel3', 'Avançado' => 'nivel4'];
    return $nivel ? etiqueta($nivel, $tipos[$nivel] ?? '') : '';
}

function estado_etiqueta(string $estado, array $nomes): string
{
    $tipos = ['aberta' => 'ok', 'confirmada' => 'ok', 'pendente' => 'info', 'fechada' => '', 'cancelada' => 'erro'];
    return etiqueta($nomes[$estado] ?? $estado, $tipos[$estado] ?? '');
}

// Lista de formações agrupada por mês, em formato de calendário.
// Na versão pública mostra as vagas disponíveis em vez do número de inscritos.
function calendario(array $formacoes, callable $acoes, bool $publico = false): string
{
    if (!$formacoes) {
        return '<p class="texto-suave">Não há formações agendadas.</p>';
    }
    $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $abrev = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    $dias = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    $html = '';
    $mes_atual = '';
    foreach ($formacoes as $f) {
        $t = strtotime($f['inicio']);
        $mes = $meses[(int) date('n', $t)] . ' de ' . date('Y', $t);
        if ($mes !== $mes_atual) {
            $html .= ($mes_atual ? '</div>' : '') . '<h2 class="calendario-mes">' . h($mes) . '</h2><div class="calendario">';
            $mes_atual = $mes;
        }
        $n = inscritos((int) $f['id']);
        $restantes = max(0, (int) $f['vagas'] - $n);
        $local = trim((MODALIDADES[$f['modalidade']] ?? '') . ($f['local'] && strcasecmp(trim($f['local']), MODALIDADES[$f['modalidade']] ?? '') !== 0 ? ' · ' . $f['local'] : ''));
        $vagas = $publico
            ? ($f['estado'] !== 'aberta' ? '' : ($restantes === 0 ? ' · Esgotada' : ($restantes <= 3 ? ' · Últimas vagas' : ' · ' . $restantes . ' vagas disponíveis')))
            : ' · ' . $n . ' inscrito(s), ' . $restantes . ' vaga(s)';
        $html .= '<article class="evento">
          <div class="evento-data"><strong>' . date('j', $t) . '</strong><span>' . $abrev[(int) date('n', $t)] . '</span><small>' . $dias[(int) date('w', $t)] . '</small></div>
          <div class="evento-info">
            <h3>' . ($publico && !empty($f['curso_id']) ? '<a href="' . h(link_curso_publico(['id' => $f['curso_id']])) . '">' . h($f['titulo']) . '</a>' : h($f['titulo'])) . (formacao_nova($f) && $f['estado'] === 'aberta' ? ' ' . etiqueta('Nova', 'nova') : '') . '</h3>
            <p class="mb-0">' . h(nome_modulo($f)) . '</p>
            <p class="evento-meta mb-0">' . h(date('H:i', $t) . (!empty($f['fim']) ? ' às ' . date('H:i', strtotime($f['fim'])) : '')) . ($local ? ' · ' . h($local) : '') . h($vagas) . '</p>
            ' . programa_html($f) . '
          </div>
          <div class="evento-acoes">' . ($publico && $f['estado'] === 'aberta' ? '' : estado_etiqueta($f['estado'], ESTADOS_FORMACAO)) . $acoes($f, $restantes) . '</div>
        </article>';
    }
    return $html . '</div>';
}
