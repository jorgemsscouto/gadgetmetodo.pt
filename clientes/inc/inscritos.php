<?php
// Inscrições (administração): lista dos formandos inscritos em qualquer curso, agrupados por pessoa (pelo email do
// formando; sem email, cada inscrição conta como um formando), e ficha do formando com os dados, os cursos e as
// presenças por sessão. A lista por inscrição (com filtros e exportação) e as pré-inscrições continuam disponíveis.

declare(strict_types=1);

// Separadores de Inscrições: formandos, pré-inscrições e todas as inscrições
function submenu_inscricoes(string $atual): string
{
    $pre = (int) (linha_bd("SELECT COUNT(*) AS n FROM preinscricoes WHERE estado != 'inscrita'")['n'] ?? 0);
    $itens = [
        'formandos' => ['Formandos', url('inscricoes')],
        'pre' => ['Pré-inscrições' . ($pre ? ' (' . $pre . ')' : ''), url('inscricoes', ['estado' => 'preinscricao'])],
        'todas' => ['Todas as inscrições', url('inscricoes', ['vista' => 'todas'])],
    ];
    $html = '';
    foreach ($itens as $k => [$nome, $link]) {
        $html .= '<li><a href="' . h($link) . '"' . ($k === $atual ? ' aria-current="page"' : '') . '>' . h($nome) . '</a></li>';
    }
    return '<nav class="area-subnav" aria-label="Inscrições"><ul>' . $html . '</ul></nav>';
}

// Chave de agrupamento: o email do formando (sem distinguir maiúsculas) ou, sem email, a própria inscrição
function chave_formando(array $i): string
{
    $email = mb_strtolower(trim((string) $i['email']));
    return $email !== '' ? 'e:' . $email : 'i:' . (int) $i['id'];
}

// Sessões de cada edição, com memória por pedido
function sessoes_formacao_cache(int $formacao_id): array
{
    static $cache = [];
    if (!isset($cache[$formacao_id])) {
        $f = linha_bd('SELECT * FROM formacoes WHERE id = ?', [$formacao_id]);
        $cache[$formacao_id] = $f ? sessoes_edicao($f) : [];
    }
    return $cache[$formacao_id];
}

// Presenças de uma inscrição confirmada: [presenças, sessões já iniciadas, total de sessões]
function resumo_presencas(array $i): array
{
    $sessoes = sessoes_formacao_cache((int) $i['formacao_id']);
    $decorridas = count(array_filter($sessoes, function ($s) { return strtotime($s['inicio']) <= time(); }));
    return [count(presencas_inscricao((int) $i['id'])), $decorridas, count($sessoes)];
}

// Estado do acesso à área do formando a partir do email
function estado_acesso_formando(string $email): array
{
    if (trim($email) === '') {
        return ['sem_email', 'Sem email', null];
    }
    $u = utilizador_por_email($email);
    if (!$u) {
        return ['sem_conta', 'Sem acesso', null];
    }
    if ($u['papel'] !== 'formando') {
        return ['outro', 'Utilizador ' . mb_strtolower(PAPEIS[$u['papel']] ?? $u['papel']), $u];
    }
    if (!(int) $u['ativo']) {
        return ['inativo', 'Acesso inativo', $u];
    }
    if ($u['token_senha'] !== null && $u['ultimo_acesso'] === null) {
        return strtotime((string) $u['token_validade']) >= time()
            ? ['ligacao', 'Ligação enviada, válida até ' . data_hora($u['token_validade']), $u]
            : ['expirada', 'Ligação expirada sem uso', $u];
    }
    return ['ativo', $u['ultimo_acesso'] ? 'Último acesso em ' . data_hora($u['ultimo_acesso']) : 'Com acesso', $u];
}

const SQL_INSCRICOES_FORMANDO = 'SELECT i.*, cl.nome AS cliente_registado, COALESCE(cl.nome, NULLIF(i.empresa, \'\'), i.contacto_nome) AS cliente, COALESCE(cl.tipo, i.tipo_cliente, \'empresa\') AS cliente_tipo,
    f.titulo, f.inicio, f.fim, f.curso_id, f.modalidade, f.local, f.estado AS estado_formacao
    FROM inscricoes i LEFT JOIN clientes cl ON cl.id = i.cliente_id JOIN formacoes f ON f.id = i.formacao_id';

// Formandos inscritos, um por pessoa, com as inscrições que cumprem os filtros (da mais recente para a mais antiga)
function formandos_inscritos(array $filtros): array
{
    $sql = SQL_INSCRICOES_FORMANDO . ' WHERE 1 = 1';
    $par = [];
    if ($filtros['curso']) { $sql .= ' AND f.curso_id = ?'; $par[] = $filtros['curso']; }
    if ($filtros['cliente']) { $sql .= ' AND i.cliente_id = ?'; $par[] = $filtros['cliente']; }
    if ($filtros['estado']) { $sql .= ' AND i.estado = ?'; $par[] = $filtros['estado']; }
    if ($filtros['q'] !== '') { $sql .= ' AND (i.formando LIKE ? OR i.email LIKE ?)'; $par[] = '%' . $filtros['q'] . '%'; $par[] = '%' . $filtros['q'] . '%'; }
    $grupos = [];
    foreach (consulta($sql . ' ORDER BY f.inicio DESC, i.id DESC', $par) as $i) {
        $grupos[chave_formando($i)][] = $i;
    }
    uasort($grupos, function ($a, $b) { return strcoll(mb_strtolower($a[0]['formando']), mb_strtolower($b[0]['formando'])); });
    return $grupos;
}

function admin_formandos(): void
{
    $estados = ESTADOS_INSCRICAO;
    $filtros = [
        'q' => trim(mb_substr(is_string($_GET['q'] ?? null) ? $_GET['q'] : '', 0, 80)),
        'curso' => (int) ($_GET['curso'] ?? 0),
        'cliente' => (int) ($_GET['cliente_f'] ?? 0),
        'estado' => isset($estados[$_GET['estado_f'] ?? '']) ? $_GET['estado_f'] : '',
    ];
    $grupos = formandos_inscritos($filtros);
    $linhas_csv = [];
    $linhas = '';
    foreach ($grupos as $lista) {
        $i0 = $lista[0];
        [, $acesso] = estado_acesso_formando((string) $i0['email']);
        $cursos = '';
        $presentes = 0;
        $decorridas = 0;
        $cursos_csv = [];
        foreach ($lista as $i) {
            $cursos .= '<li>' . h($i['titulo']) . ' ' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '<br><span class="texto-suave">' . h(data_hora($i['inicio'], false)) . '</span></li>';
            $cursos_csv[] = $i['titulo'] . ' (' . (ESTADOS_INSCRICAO[$i['estado']] ?? $i['estado']) . ')';
            if ($i['estado'] === 'confirmada') {
                [$p, $d] = resumo_presencas($i);
                $presentes += $p;
                $decorridas += $d;
            }
        }
        $clientes = array_values(array_unique(array_filter(array_column($lista, 'cliente'))));
        $linhas .= '<tr><td><a href="' . h(url('formando', ['i' => $i0['id']])) . '">' . h($i0['formando']) . '</a>' . ($i0['funcao'] ? '<br><span class="texto-suave">' . h($i0['funcao']) . '</span>' : '') . '</td>'
            . '<td>' . h($i0['email']) . '</td><td>' . h(implode(', ', $clientes)) . '</td>'
            . '<td><ul class="lista-simples">' . $cursos . '</ul></td>'
            . '<td>' . ($decorridas ? $presentes . ' de ' . $decorridas : '<span class="texto-suave">Ainda sem sessões</span>') . '</td>'
            . '<td><span class="texto-suave">' . h($acesso) . '</span></td></tr>';
        $linhas_csv[] = [$i0['formando'], $i0['email'], $i0['funcao'], implode(', ', $clientes), implode('; ', $cursos_csv), $decorridas ? $presentes . ' de ' . $decorridas : '', $acesso];
    }
    if (!empty($_GET['exportar_formandos'])) {
        exportar_csv('gadgetmetodo-formandos', ['Formando', 'Email', 'Função', 'Cliente', 'Cursos (estado)', 'Presenças (sessões já iniciadas)', 'Acesso à área do formando'], $linhas_csv);
    }
    $cursos = array_column(consulta('SELECT id, nome FROM cursos ORDER BY id'), 'nome', 'id');
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    $novas = (int) (linha_bd("SELECT COUNT(*) AS n FROM preinscricoes WHERE estado = 'nova'")['n'] ?? 0);
    pagina_area('inscricoes', 'Inscrições', submenu_inscricoes('formandos')
      . ($novas ? '<div class="aviso aviso-info">' . $novas . ' pré-inscrição(ões) nova(s) por tratar. <a href="' . h(url('inscricoes', ['estado' => 'preinscricao'])) . '">Ver as pré-inscrições</a></div>' : '') . '
      <p class="texto-suave">Formandos inscritos em qualquer curso, um por pessoa (pelo email do formando). Abra o formando para ver os dados, os cursos e as presenças em cada sessão.</p>
      <form method="get" class="filtros">
        <input type="hidden" name="p" value="inscricoes">
        ' . campo_get('q', 'Nome ou email', $filtros['q']) . campo_select('curso', 'Curso', $cursos, $filtros['curso'], 'Todos') . campo_select('cliente_f', 'Cliente', $clientes, $filtros['cliente'], 'Todos') . campo_select('estado_f', 'Estado da inscrição', $estados, $filtros['estado'], 'Todos') . '
        <div class="filtros-acoes"><button class="botao" type="submit">Filtrar</button><a class="botao botao-secundario" href="' . h(url('inscricoes', array_filter(['q' => $filtros['q'], 'curso' => $filtros['curso'], 'cliente_f' => $filtros['cliente'], 'estado_f' => $filtros['estado']]) + ['exportar_formandos' => 1])) . '">Exportar para Excel</a></div>
      </form>
      ' . ($linhas ? '<p class="texto-suave">' . count($grupos) . ' formando(s).</p><div class="conteudo tabela-rolante"><table class="tabela-formandos"><thead><tr><th>Formando</th><th>Email</th><th>Cliente</th><th>Cursos</th><th>Presenças</th><th>Área do formando</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>'
        : '<p class="texto-suave">Sem formandos para os filtros escolhidos.</p>'), 'Formações');
}

// Campo de texto de um formulário GET (pesquisa)
function campo_get(string $nome, string $rotulo, string $valor): string
{
    return '<div class="campo"><label for="c-' . h($nome) . '">' . h($rotulo) . '</label><input id="c-' . h($nome) . '" name="' . h($nome) . '" type="search" value="' . h($valor) . '" maxlength="80"></div>';
}

// Ficha do formando: dados, acesso à área do formando, cursos e presenças por sessão (a administração marca ou retira)
function admin_formando(): void
{
    $base = linha_bd(SQL_INSCRICOES_FORMANDO . ' WHERE i.id = ?', [(int) ($_GET['i'] ?? 0)]);
    if (!$base) {
        pagina_erro('Formando não encontrado', 'O formando pedido não existe.');
    }
    $email = trim((string) $base['email']);
    $lista = $email !== '' ? consulta(SQL_INSCRICOES_FORMANDO . ' WHERE ' . sprintf(SQL_EMAIL_IGUAL, 'i.email') . ' ORDER BY f.inicio DESC, i.id DESC', [$email]) : [$base];
    $ids = array_map('intval', array_column($lista, 'id'));
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $acao = (string) ($_POST['acao'] ?? '');
        $iid = (int) ($_POST['inscricao'] ?? 0);
        if ($acao === 'presencas' && in_array($iid, $ids, true)) {
            $i = $lista[array_search($iid, $ids, true)];
            $marcadas = is_array($_POST['p'] ?? null) ? $_POST['p'] : [];
            $atuais = presencas_inscricao($iid);
            $novas = 0;
            $retiradas = 0;
            foreach (sessoes_formacao_cache((int) $i['formacao_id']) as $s) {
                $quer = !empty($marcadas[$s['n']]);
                if ($quer && !isset($atuais[$s['n']])) {
                    $novas += marcar_presenca($iid, (int) $s['n'], 'admin', (int) utilizador()['id']) ? 1 : 0;
                } elseif (!$quer && isset($atuais[$s['n']])) {
                    executar('DELETE FROM presencas WHERE inscricao_id = ? AND sessao = ?', [$iid, $s['n']]);
                    $retiradas++;
                }
            }
            aviso($novas || $retiradas ? 'Presenças guardadas: ' . $novas . ' marcada(s), ' . $retiradas . ' retirada(s).' : 'Nada a alterar nas presenças.', $novas || $retiradas ? 'ok' : 'info');
            redirecionar('formando', ['i' => $base['id']], 'inscricao-' . $iid);
        }
        if ($acao === 'acesso' && $email !== '') {
            $confirmada = array_values(array_filter($lista, function ($x) { return $x['estado'] === 'confirmada'; }))[0] ?? null;
            [$estado, , $u] = estado_acesso_formando($email);
            if ($u && $u['papel'] === 'formando') {
                aviso_envio(enviar_ligacao_senha($u, (string) ($confirmada['titulo'] ?? '')), 'Ligação para definir a palavra-passe (válida 7 dias) enviada para ' . h($email) . '.');
            } elseif ($estado === 'sem_conta' && $confirmada) {
                $r = acesso_apos_confirmacao((int) $confirmada['id']);
                aviso(...($r ?? ['Nada a fazer.', 'info']));
            } else {
                aviso('O acesso à área do formando só é criado com uma inscrição confirmada.', 'erro');
            }
            redirecionar('formando', ['i' => $base['id']]);
        }
        redirecionar('formando', ['i' => $base['id']]);
    }
    [$estado, $texto_acesso, $u] = estado_acesso_formando($email);
    $tem_confirmada = (bool) array_filter($lista, function ($x) { return $x['estado'] === 'confirmada'; });
    $botao_acesso = '';
    if ($u && $u['papel'] === 'formando') {
        $botao_acesso = '<form method="post" class="linha-botoes">' . campo_csrf() . '<input type="hidden" name="acao" value="acesso"><button class="botao botao-secundario" type="submit">Enviar nova ligação para definir a palavra-passe</button> <a href="' . h(url('utilizador', ['id' => $u['id']])) . '">Ver o utilizador</a></form>';
    } elseif ($estado === 'sem_conta' && $tem_confirmada) {
        $botao_acesso = '<form method="post" class="linha-botoes">' . campo_csrf() . '<input type="hidden" name="acao" value="acesso"><button class="botao botao-secundario" type="submit">Criar o acesso e enviar a ligação</button></form>';
    }
    $clientes = array_values(array_unique(array_filter(array_map(function ($x) { return $x['cliente'] ? $x['cliente'] . ($x['cliente_registado'] ? '' : ' (por associar)') : ''; }, $lista))));
    $dados = array_filter([
        'Nome' => h($base['formando']),
        'Email' => $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<span class="texto-suave">Sem email: não tem acesso à área do formando</span>',
        'Função' => h(implode(', ', array_unique(array_filter(array_column($lista, 'funcao'))))),
        'Cliente' => h(implode(', ', $clientes)),
        'Inscrito por' => h(implode(', ', array_unique(array_filter(array_map(function ($x) { return trim((string) $x['contacto_nome'] . ($x['contacto_email'] ? ' <' . $x['contacto_email'] . '>' : '')); }, $lista))))),
        'Área do formando' => h($texto_acesso) . $botao_acesso,
    ], 'strlen');
    $ficha = '';
    foreach ($dados as $k => $v) {
        $ficha .= '<tr><th scope="row">' . h($k) . '</th><td>' . $v . '</td></tr>';
    }
    $cursos = '';
    foreach ($lista as $i) {
        $sessoes = sessoes_formacao_cache((int) $i['formacao_id']);
        $presencas = presencas_inscricao((int) $i['id']);
        $confirmada = $i['estado'] === 'confirmada';
        $linhas = '';
        foreach ($sessoes as $s) {
            $p = $presencas[$s['n']] ?? null;
            $passada = strtotime($s['inicio']) <= time();
            $estado_s = $p ? presenca_html($p) : ($passada ? etiqueta('Sem registo') : '<span class="texto-suave">Por realizar</span>');
            $caixa = $confirmada ? '<input type="checkbox" name="p[' . (int) $s['n'] . ']" value="1"' . ($p ? ' checked' : '') . ' aria-label="Presente na ' . h($s['nome'] ?: 'sessão') . '">' : '';
            $linhas .= '<tr><td class="c-sessao">' . $caixa . '</td><td>' . h($s['nome'] ?: 'Sessão única') . '</td><td>' . h(dia_sessao($s['inicio'], false)) . '<br><span class="texto-suave">' . h(hora_sessao($s)) . '</span></td><td>' . $estado_s . '</td></tr>';
        }
        [$pp, $dd, $tt] = resumo_presencas($i);
        $cursos .= '<section class="bloco-formando" id="inscricao-' . (int) $i['id'] . '">
          <h3>' . h($i['titulo']) . ' ' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</h3>
          <p class="texto-suave">' . h(horario($i)) . ' · ' . h(modalidade_e_local($i)) . ($confirmada ? ' · Presenças: ' . $pp . ' de ' . $tt . ($dd < $tt ? ' (' . $dd . ' sessão(ões) já iniciada(s))' : '') : '') . ' · <a href="' . h(url('inscricao', ['id' => $i['id']])) . '">Ficha da inscrição</a> · <a href="' . h(url('formacao', ['id' => $i['formacao_id']])) . '">Edição</a></p>
          ' . ($sessoes ? '<form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="presencas"><input type="hidden" name="inscricao" value="' . (int) $i['id'] . '">
            <div class="conteudo tabela-rolante"><table class="tabela-presencas grelha-presencas"><thead><tr><th class="c-sessao" scope="col">' . ($confirmada ? 'Presente' : '') . '</th><th scope="col">Sessão</th><th scope="col">Data</th><th scope="col">Registo</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
            ' . ($confirmada ? '<div class="espaco-topo"><button class="botao botao-secundario" type="submit">Guardar presenças</button></div>' : '<p class="texto-suave">As presenças registam-se nas inscrições confirmadas.</p>') . '</form>' : '') . '
        </section>';
    }
    pagina_area('inscricoes', $base['formando'], '
      <div class="conteudo"><table class="tabela-ficha"><tbody>' . $ficha . '</tbody></table></div>
      <h2 class="espaco-topo">Cursos e presenças (' . count($lista) . ')</h2>
      <p class="texto-suave">A administração marca ou retira presenças em qualquer momento; as marcadas aqui não enviam email ao formando.</p>
      ' . $cursos . '
      <p class="espaco-topo"><a href="' . h(url('inscricoes')) . '">Voltar aos formandos</a></p>', 'Formando');
}
