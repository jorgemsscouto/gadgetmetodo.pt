<?php
// Diagnósticos enviados pela administração (menu "Diagnósticos"): escolhe-se o curso e os utilizadores (Formando ou
// Cliente) e cada um recebe por email uma ligação pessoal, válida 30 dias e de uso único, para responder ao diagnóstico
// de IA sem iniciar sessão (area.php?p=responder&t=…; guarda-se só o resumo SHA-256). As respostas ficam com o
// resultado e o curso aconselhado, que só a Gadgetmetodo vê; a administração recebe um aviso quando há resposta.

declare(strict_types=1);

const VALIDADE_DIAGNOSTICO = 30 * 86400;

function link_diagnostico(string $token): string
{
    return endereco_site() . '/clientes/area.php?' . http_build_query(['p' => 'responder', 't' => $token]);
}

// Estado de um diagnóstico enviado: [texto, tipo da etiqueta]
function estado_diagnostico(array $d): array
{
    if ($d['respondido'] !== null) {
        return ['Respondido', 'ok'];
    }
    return strtotime((string) $d['validade']) >= time() ? ['Por responder', 'info'] : ['Expirado', ''];
}

// Gera uma ligação nova (a anterior deixa de valer) e envia-a por email
function enviar_ligacao_diagnostico(int $id): bool
{
    $d = linha_bd('SELECT d.*, c.nome AS curso FROM diagnosticos d LEFT JOIN cursos c ON c.id = d.curso_id WHERE d.id = ?', [$id]);
    if (!$d || $d['respondido'] !== null) {
        return false;
    }
    $token = bin2hex(random_bytes(24));
    $validade = date('Y-m-d H:i:s', time() + VALIDADE_DIAGNOSTICO);
    executar('UPDATE diagnosticos SET token = ?, validade = ?, enviado = ?, enviado_por = ? WHERE id = ?', [hash('sha256', $token), $validade, agora(), utilizador()['id'] ?? null, $id]);
    $link = link_diagnostico($token);
    $quando = data_hora($validade);
    $curso = (string) ($d['curso'] ?? '');
    return enviar_mensagem('diagnostico_convite', (string) $d['email'], [
        'nome' => nome_curto((string) $d['nome']), 'nome_completo' => $d['nome'], 'email' => $d['email'],
        'curso' => $curso, 'no_curso' => $curso !== '' ? 'no ' . $curso : 'na formação', 'validade' => $quando,
    ], [
        ($curso !== '' ? '<p style="margin:0 0 6px;"><strong>Curso:</strong> ' . h($curso) . '</p>' : '')
        . '<p style="margin:0 0 18px;"><strong>Responder até:</strong> ' . h($quando) . '</p>'
        . '<p style="margin:0 0 4px;"><a href="' . h($link) . '" style="display:inline-block;padding:13px 26px;background:#0A0F26;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:999px;">Responder ao diagnóstico</a></p>',
        ($curso !== '' ? 'Curso: ' . $curso . "\n" : '') . 'Responder ao diagnóstico (até ' . $quando . '): ' . $link,
    ]);
}

/* Administração */

function admin_diagnosticos(): void
{
    $cursos = array_column(consulta('SELECT id, nome FROM cursos WHERE ativo = 1 ORDER BY id'), 'nome', 'id');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $curso_id = (int) ($_POST['curso_id'] ?? 0);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['utilizadores'] ?? [])))));
        if (!isset($cursos[$curso_id])) {
            aviso('Escolha o curso.', 'erro');
            redirecionar('diagnosticos');
        }
        if (!$ids) {
            aviso('Escolha pelo menos um utilizador.', 'erro');
            redirecionar('diagnosticos', ['curso' => $curso_id]);
        }
        $enviados = [];
        $falhados = [];
        foreach ($ids as $uid) {
            $u = linha_bd("SELECT * FROM utilizadores WHERE id = ? AND ativo = 1 AND papel IN ('formando', 'cliente')", [$uid]);
            if (!$u) {
                continue;
            }
            // Um diagnóstico por responder do mesmo curso recebe uma ligação nova em vez de se criar outro
            $d = linha_bd('SELECT id FROM diagnosticos WHERE utilizador_id = ? AND curso_id = ? AND respondido IS NULL', [$uid, $curso_id]);
            $id = $d ? (int) $d['id'] : executar('INSERT INTO diagnosticos (utilizador_id, nome, email, curso_id, enviado, enviado_por) VALUES (?, ?, ?, ?, ?, ?)',
                [$uid, $u['nome'], trim((string) $u['email']), $curso_id, agora(), utilizador()['id']]);
            if (enviar_ligacao_diagnostico($id)) {
                $enviados[] = h($u['nome']);
            } else {
                $falhados[] = h($u['nome']) . ' (' . h($u['email']) . ')';
            }
        }
        if ($enviados) {
            aviso('Diagnóstico do ' . h($cursos[$curso_id]) . ' enviado a ' . count($enviados) . ' utilizador(es): ' . implode(', ', $enviados) . '.');
        }
        if ($falhados) {
            aviso('Não foi possível enviar o email a: ' . implode(', ', $falhados) . '. Verifique o envio de emails em Configuração, Comunicação.', 'erro');
        }
        redirecionar('diagnosticos', ['curso' => $curso_id]);
    }

    // Utilizadores a quem se pode enviar (Formando e Cliente, ativos), com o último diagnóstico de cada um
    $lista = consulta("SELECT u.id, u.nome, u.email, u.papel, c.nome AS cliente,
            (SELECT MAX(d.enviado) FROM diagnosticos d WHERE d.utilizador_id = u.id) AS ultimo
        FROM utilizadores u LEFT JOIN clientes c ON c.id = u.cliente_id
        WHERE u.ativo = 1 AND u.papel IN ('formando', 'cliente') ORDER BY u.nome COLLATE NOCASE");
    $linhas = '';
    foreach ($lista as $u) {
        $linhas .= '<tr><td><input type="checkbox" name="utilizadores[]" value="' . (int) $u['id'] . '" id="u-' . (int) $u['id'] . '" aria-label="Enviar a ' . h($u['nome']) . '"></td>'
            . '<td><label for="u-' . (int) $u['id'] . '">' . h($u['nome']) . '</label></td><td>' . h($u['email']) . '</td><td>' . h(PAPEIS[$u['papel']]) . '</td><td>' . h((string) $u['cliente']) . '</td>'
            . '<td data-valor="' . h((string) $u['ultimo']) . '">' . h(data_hora($u['ultimo'], false)) . '</td></tr>';
    }
    $envio = $lista
        ? '<form method="post" class="formulario">' . campo_csrf() . '
            ' . campo_select('curso_id', 'Curso *', $cursos, (int) ($_GET['curso'] ?? 0), 'Escolha o curso', 'required') . '
            <div class="conteudo tabela-rolante"><table data-filtros class="tabela-escolha"><thead><tr><th data-sem-filtro><input type="checkbox" class="marcar-todos" aria-label="Escolher todos os utilizadores visíveis"></th><th>Nome</th><th>Email</th><th data-filtro="lista">Perfil</th><th data-filtro="lista">Cliente</th><th>Último diagnóstico</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
            <div><button class="botao" type="submit">Enviar diagnóstico</button></div>
          </form>'
        : '<p class="texto-suave">Ainda não há utilizadores com o perfil Formando ou Cliente. Crie-os em Configuração, Utilizadores.</p>';

    // Diagnósticos enviados
    $enviados = consulta('SELECT d.*, c.nome AS curso FROM diagnosticos d LEFT JOIN cursos c ON c.id = d.curso_id ORDER BY d.enviado DESC');
    $linhas = '';
    foreach ($enviados as $d) {
        [$estado, $tipo] = estado_diagnostico($d);
        $resultado = '';
        $aconselhado = '';
        if ($d['respondido'] !== null && is_array($r = json_decode((string) $d['respostas'], true))) {
            $resultado = resumo_diagnostico(avaliar_diagnostico($r));
            $ind = curso_indicado($r, curso((int) $d['curso_id']));
            $aconselhado = $ind ? (string) $ind['curso']['nome'] : '';
        }
        $linhas .= '<tr><td><a href="' . h(url('diagnostico', ['id' => $d['id']])) . '">' . h($d['nome']) . '</a></td><td>' . h($d['email']) . '</td><td>' . h((string) $d['curso']) . '</td>'
            . '<td data-valor="' . h($d['enviado']) . '">' . h(data_hora($d['enviado'], false)) . '</td><td>' . etiqueta($estado, $tipo) . '</td>'
            . '<td data-valor="' . h((string) $d['pontuacao']) . '">' . h($resultado) . '</td><td>' . h($aconselhado) . '</td></tr>';
    }
    pagina_area('diagnosticos', 'Diagnósticos', '
      <p class="texto-suave">Envie o diagnóstico de IA antes da formação: escolha o curso e os utilizadores. Cada pessoa recebe por email uma ligação pessoal, válida 30 dias e de uso único, e responde sem precisar de entrar na área reservada. São ' . (count(DIAGNOSTICO) + 1) . ' perguntas: ' . count(DIAGNOSTICO_V2) . ' sobre a utilização da IA, ' . count(DIAGNOSTICO_CONHECIMENTO) . ' de conhecimentos e as ferramentas usadas. O resultado e o curso aconselhado só aparecem aqui.</p>
      <h2 class="espaco-topo">Enviar o diagnóstico</h2>
      ' . $envio . '
      <h2 class="espaco-topo">Diagnósticos enviados</h2>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table data-filtros><thead><tr><th>Nome</th><th>Email</th><th data-filtro="lista">Curso</th><th>Enviado</th><th data-filtro="lista">Estado</th><th>Resultado</th><th data-filtro="lista">Curso aconselhado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>'
        : '<p class="texto-suave">Ainda não foi enviado nenhum diagnóstico.</p>'), 'Formação');
}

function admin_diagnostico(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $d = linha_bd('SELECT d.*, c.nome AS curso, u.nome AS por FROM diagnosticos d LEFT JOIN cursos c ON c.id = d.curso_id LEFT JOIN utilizadores u ON u.id = d.enviado_por WHERE d.id = ?', [$id]);
    if (!$d) {
        pagina_erro('Diagnóstico não encontrado', 'O diagnóstico pedido não existe.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'reenviar') {
        if ($d['respondido'] !== null) {
            aviso('Este diagnóstico já foi respondido.', 'info');
        } else {
            aviso_envio(enviar_ligacao_diagnostico($id), 'Nova ligação enviada para ' . h($d['email']) . ' (válida 30 dias; a anterior deixou de valer).');
        }
        redirecionar('diagnostico', ['id' => $id]);
    }
    [$estado, $tipo] = estado_diagnostico($d);
    $dados = '<dl class="factos-area"><div><dt>Email</dt><dd>' . h($d['email']) . '</dd></div><div><dt>Curso</dt><dd>' . h((string) $d['curso']) . '</dd></div>'
        . '<div><dt>Enviado</dt><dd>' . h(data_hora($d['enviado'])) . ($d['por'] ? ' por ' . h($d['por']) : '') . '</dd></div>'
        . '<div><dt>Estado</dt><dd>' . etiqueta($estado, $tipo) . ' ' . ($d['respondido'] !== null ? 'em ' . h(data_hora($d['respondido'])) : 'ligação válida até ' . h(data_hora($d['validade']))) . '</dd></div></dl>';
    $resultado = '';
    if ($d['respondido'] !== null && is_array($r = json_decode((string) $d['respostas'], true))) {
        $a = avaliar_diagnostico($r);
        $ind = curso_indicado($r, curso((int) $d['curso_id']));
        $fer = json_decode((string) $d['ferramentas'], true) ?: [];
        $resultado = '<h2 class="espaco-topo">Resultado</h2>
          <p><strong>' . h(resumo_diagnostico($a)) . '</strong></p>
          ' . ($ind ? '<p>Curso aconselhado: <strong>' . h($ind['curso']['nome']) . '</strong> (' . h($ind['motivo']) . ').</p>' : '') . '
          <p class="texto-suave">' . h($a['recomendacao']) . '</p>
          <p>Ferramentas de IA que utiliza: ' . h($fer ? implode(', ', $fer) : 'nenhuma indicada') . '</p>
          <h2 class="espaco-topo">Respostas</h2>
          <div class="conteudo tabela-rolante tabela-diagnostico">' . tabela_diagnostico(['respostas' => $d['respostas']]) . '</div>';
    }
    pagina_area('diagnosticos', 'Diagnóstico de ' . $d['nome'], $dados . $resultado . '
      <div class="acoes espaco-topo">'
        . ($d['respondido'] === null ? '<form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="reenviar"><button class="botao" type="submit">Reenviar a ligação</button></form>' : '')
        . '<a class="botao botao-secundario" href="' . h(url('diagnosticos')) . '">Voltar aos diagnósticos</a>'
        . botao_apagar('diagnostico', $id, 'Apagar este diagnóstico') . '</div>', 'Formação');
}

/* Resposta (ligação enviada por email; sem sessão iniciada) */

function pagina_responder_diagnostico(): void
{
    $t = is_string($_GET['t'] ?? null) ? $_GET['t'] : '';
    $d = preg_match('/^[a-f0-9]{48}$/', $t) && !limite_excedido('diagnostico', 30, 900, false)
        ? linha_bd('SELECT d.*, c.nome AS curso FROM diagnosticos d LEFT JOIN cursos c ON c.id = d.curso_id WHERE d.token = ?', [hash('sha256', $t)]) : null;
    $valido = $d && strtotime((string) $d['validade']) >= time();
    $cabecalho = '<header class="cabecalho"><div class="contentor"><a class="marca" href="/"><img src="/assets/marca/logotipo.svg" alt="Gadgetmetodo, Lda." width="234" height="42"></a></div></header>';
    if (!$valido) {
        limite_excedido('diagnostico', 30, 900, true);
        http_response_code($d ? 410 : 404);
        documento('Diagnóstico indisponível', $cabecalho . '<main class="secao"><div class="contentor estreito"><div class="entrada-cartao"><span class="sobretitulo">Diagnóstico de IA</span>
          <h1>' . ($d ? 'Ligação expirada' : 'Ligação inválida') . '</h1>
          <p class="lead">' . ($d ? 'O prazo para responder a este diagnóstico terminou.' : 'Esta ligação não é válida ou o diagnóstico já foi respondido.') . ' Se precisar de uma ligação nova, escreva para <a href="mailto:info@gadgetmetodo.pt">info@gadgetmetodo.pt</a>.</p></div></div></main>');
    }
    $erro = '';
    $v = ['diag' => [], 'ferramentas' => []];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        verificar_csrf();
        $respostas = respostas_diagnostico();
        $v = ['diag' => array_map('strval', array_filter((array) ($_POST['diag'] ?? []), 'is_string'))] + ferramentas_post();
        $erro = $respostas === null ? 'Responda a todas as perguntas (falta pelo menos uma).' : erro_ferramentas($v);
        if ($erro === '') {
            $a = avaliar_diagnostico($respostas);
            $ferramentas = array_merge($v['ferramentas'], $v['ferramenta_outra'] !== '' ? ['Outra: ' . $v['ferramenta_outra']] : []);
            executar('UPDATE diagnosticos SET respostas = ?, ferramentas = ?, pontuacao = ?, nivel = ?, respondido = ?, token = NULL WHERE id = ? AND respondido IS NULL',
                [json_encode($respostas), json_encode($ferramentas, JSON_UNESCAPED_UNICODE), $a['pontuacao'], $a['nivel'], agora(), $d['id']]);
            aviso_diagnostico_respondido((int) $d['id']);
            documento('Diagnóstico enviado', $cabecalho . '<main class="secao"><div class="contentor estreito"><div class="entrada-cartao"><span class="sobretitulo">Diagnóstico de IA</span>
              <h1>Obrigado, ' . h(nome_curto((string) $d['nome'])) . '</h1>
              <p class="lead">As suas respostas foram registadas. A Gadgetmetodo vai analisá-las para preparar a formação de acordo com o seu ponto de partida.</p></div></div></main>');
        }
    }
    $n = count(DIAGNOSTICO) + 1;
    documento('Diagnóstico de IA', $cabecalho . '<main class="secao"><div class="contentor estreito">
      <span class="sobretitulo">Diagnóstico de IA</span>
      <h1>' . h(nome_curto((string) $d['nome'])) . ', bem-vindo ao diagnóstico</h1>
      <p class="lead">' . ($d['curso'] ? 'Antes do ' . h($d['curso']) . ', ' : 'Antes da formação, ') . 'queremos conhecer o seu ponto de partida. São ' . $n . ' perguntas, cerca de seis minutos: as ' . count(DIAGNOSTICO_V2) . ' primeiras descrevem a forma como usa a IA (não há respostas certas) e as ' . count(DIAGNOSTICO_CONHECIMENTO) . ' seguintes avaliam conhecimentos. Pode responder até ' . h(data_hora($d['validade'])) . '.</p>
      ' . ($erro !== '' ? '<div class="aviso aviso-erro" role="alert">' . h($erro) . '</div>' : '') . '
      <form method="post" action="' . h(url('responder', ['t' => $t])) . '" class="formulario formulario-inscricao">' . campo_csrf() . '
        ' . campos_diagnostico($v['diag']) . '
        ' . campo_ferramentas($v, $n) . '
        <div><button class="botao" type="submit">Enviar respostas</button></div>
      </form>
    </div></main>');
}

// Aviso à administração com o resultado e o curso aconselhado
function aviso_diagnostico_respondido(int $id): void
{
    $d = linha_bd('SELECT d.*, c.nome AS curso FROM diagnosticos d LEFT JOIN cursos c ON c.id = d.curso_id WHERE d.id = ?', [$id]);
    $r = $d ? json_decode((string) $d['respostas'], true) : null;
    if (!is_array($r)) {
        return;
    }
    $a = avaliar_diagnostico($r);
    $ind = curso_indicado($r, curso((int) $d['curso_id']));
    $linhas = array_filter(['Email' => (string) $d['email'], 'Curso' => (string) $d['curso'], 'Resultado' => resumo_diagnostico($a),
        'Curso aconselhado' => $ind ? $ind['curso']['nome'] . ' (' . $ind['motivo'] . ')' : ''], 'strlen');
    $html = '';
    foreach ($linhas as $rotulo => $valor) {
        $html .= '<p style="margin:0 0 4px;"><strong>' . h($rotulo) . ':</strong> ' . h($valor) . '</p>';
    }
    $endereco = endereco_site() . '/clientes/area.php?' . http_build_query(['p' => 'diagnostico', 'id' => $id]);
    enviar_mensagem('diagnostico_aviso', email_admin(), ['formando' => $d['nome'], 'email' => $d['email'], 'curso' => (string) $d['curso']], [
        $html . '<p style="margin:22px 0 0;"><a href="' . h($endereco) . '" style="display:inline-block;padding:13px 26px;background:#0A0F26;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:999px;">Ver as respostas</a></p>',
        implode("\n", array_map(function ($k, $v) { return $k . ': ' . $v; }, array_keys($linhas), $linhas)) . "\nVer as respostas: " . $endereco,
    ], ['responder_a' => (string) $d['email']]);
}
