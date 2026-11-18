<?php
// Diagnósticos enviados pela administração (menu "Diagnósticos"): escolhem-se um ou vários cursos e os utilizadores
// (Formando ou Cliente) e cada um recebe por email uma só ligação pessoal (um diagnóstico com os cursos pedidos), válida 30 dias e de uso único, para responder ao diagnóstico
// de IA sem iniciar sessão (area.php?p=responder&t=…; guarda-se só o resumo SHA-256). As respostas ficam com o
// resultado e o curso aconselhado, que só a Gadgetmetodo vê; a administração recebe um aviso quando há resposta.

declare(strict_types=1);

const VALIDADE_DIAGNOSTICO = 30 * 86400;


// "A", "A e B", "A, B e C"
function juntar_lista(array $itens): string
{
    $itens = array_values(array_filter(array_map('strval', $itens), 'strlen'));
    return count($itens) > 1 ? implode(', ', array_slice($itens, 0, -1)) . ' e ' . end($itens) : (string) ($itens[0] ?? '');
}

// Cursos de um diagnóstico, pela ordem do catálogo (ids em "cursos"; os primeiros diagnósticos só têm curso_id).
// Os cursos entretanto apagados deixam de aparecer.
function cursos_do_diagnostico(array $d): array
{
    $ids = json_decode((string) ($d['cursos'] ?? ''), true);
    $ids = is_array($ids) && $ids ? array_map('intval', $ids) : array_filter([(int) ($d['curso_id'] ?? 0)]);
    return array_values(array_filter(array_map(function ($id) { return curso($id); }, $ids)));
}

function nomes_cursos_diagnostico(array $d): string
{
    return juntar_lista(array_column(cursos_do_diagnostico($d), 'nome'));
}

// Curso aconselhado: o do percurso do primeiro curso pedido que faça parte de um percurso, com a indicação de estar
// (ou não) entre os cursos pedidos
function aconselhado_do_diagnostico(array $d, array $r): ?array
{
    $cursos = cursos_do_diagnostico($d);
    foreach ($cursos as $c) {
        if ($ind = curso_indicado($r, $c)) {
            $ind['pedido'] = in_array((int) $ind['curso']['id'], array_map('intval', array_column($cursos, 'id')), true);
            return $ind;
        }
    }
    return null;
}

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
    $d = linha_bd('SELECT * FROM diagnosticos WHERE id = ?', [$id]);
    if (!$d || $d['respondido'] !== null) {
        return false;
    }
    $token = bin2hex(random_bytes(24));
    $validade = date('Y-m-d H:i:s', time() + VALIDADE_DIAGNOSTICO);
    executar('UPDATE diagnosticos SET token = ?, validade = ?, enviado = ?, enviado_por = ? WHERE id = ?', [hash('sha256', $token), $validade, agora(), utilizador()['id'] ?? null, $id]);
    $link = link_diagnostico($token);
    $quando = data_hora($validade);
    $cursos = cursos_do_diagnostico($d);
    $curso = juntar_lista(array_column($cursos, 'nome'));
    $rotulo = count($cursos) > 1 ? 'Cursos' : 'Curso';
    return enviar_mensagem('diagnostico_convite', (string) $d['email'], [
        'nome' => nome_curto((string) $d['nome']), 'nome_completo' => $d['nome'], 'email' => $d['email'], 'curso' => $curso,
        'no_curso' => $cursos ? juntar_lista(array_map(function ($c) { return 'no ' . $c['nome']; }, $cursos)) : 'na formação', 'validade' => $quando,
    ], [
        ($curso !== '' ? '<p style="margin:0 0 6px;"><strong>' . $rotulo . ':</strong> ' . h($curso) . '</p>' : '')
        . '<p style="margin:0 0 18px;"><strong>Responder até:</strong> ' . h($quando) . '</p>'
        . '<p style="margin:0 0 4px;"><a href="' . h($link) . '" style="display:inline-block;padding:13px 26px;background:#0A0F26;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:999px;">Responder ao diagnóstico</a></p>',
        ($curso !== '' ? $rotulo . ': ' . $curso . "\n" : '') . 'Responder ao diagnóstico (até ' . $quando . '): ' . $link,
    ]);
}

/* Inscrever numa edição quem respondeu ao diagnóstico (sem pré-inscrição) */

// Edições onde se pode inscrever: não canceladas, dos últimos 6 meses em diante
function edicoes_para_inscrever(): array
{
    return consulta("SELECT * FROM formacoes WHERE estado != 'cancelada' AND inicio >= ? ORDER BY inicio", [date('Y-m-d 00:00:00', strtotime('-6 months'))]);
}

// Inscrições não canceladas de um email: "Curso 1 (06/10/2026)", com a ligação para a inscrição
function inscricoes_do_email_html(string $email): string
{
    if (trim($email) === '') {
        return '';
    }
    $partes = [];
    foreach (consulta("SELECT i.id, f.titulo, f.inicio FROM inscricoes i JOIN formacoes f ON f.id = i.formacao_id WHERE i.estado != 'cancelada' AND " . sprintf(SQL_EMAIL_IGUAL, 'i.email') . ' ORDER BY f.inicio', [$email]) as $i) {
        $curso = preg_match('/^(Curso\s+\d+)\s*:/u', (string) $i['titulo'], $m) ? $m[1] : (string) $i['titulo'];
        $partes[] = '<a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($curso) . '</a> <span class="texto-suave">(' . h(date('d/m/Y', strtotime($i['inicio']))) . ')</span>';
    }
    return implode('<br>', $partes);
}

// Inscreve na edição escolhida, confirmadas e sem enviar nenhum email, as pessoas dos diagnósticos escolhidos, com as
// respostas e as ferramentas do diagnóstico (quando já respondido) e a empresa cliente do utilizador
function inscrever_diagnosticos_sem_emails(): void
{
    $f = linha_bd("SELECT * FROM formacoes WHERE id = ? AND estado != 'cancelada'", [(int) ($_POST['edicao'] ?? 0)]);
    $ids = array_values(array_unique(array_map('intval', array_filter((array) ($_POST['diag'] ?? []), 'is_numeric'))));
    if (!$f || !$ids) {
        aviso(!$f ? 'Escolha a edição.' : 'Escolha as pessoas a inscrever.', 'erro');
        redirecionar('diagnosticos', [], 'enviados');
    }
    $feitas = [];
    $falhas = [];
    $vistos = [];
    foreach ($ids as $id) {
        $d = linha_bd('SELECT d.*, u.cliente_id, u.papel FROM diagnosticos d LEFT JOIN utilizadores u ON u.id = d.utilizador_id WHERE d.id = ?', [$id]);
        $email = trim((string) ($d['email'] ?? ''));
        if (!$d || isset($vistos[strtolower($email)])) {
            continue;
        }
        $vistos[strtolower($email)] = true;
        $respostas = $d['respondido'] !== null ? json_decode((string) $d['respostas'], true) : null;
        $ferramentas = json_decode((string) $d['ferramentas'], true);
        $cliente = !empty($d['cliente_id']) ? linha_bd('SELECT * FROM clientes WHERE id = ?', [(int) $d['cliente_id']]) : null;
        $r = gravar_inscricao($f, ['cliente_id' => $cliente['id'] ?? null, 'utilizador_id' => utilizador()['id'], 'origem' => 'area',
            'tipo_cliente' => $cliente['tipo'] ?? null, 'empresa' => null, 'nif' => null, 'contacto_nome' => $d['nome'], 'contacto_email' => $email, 'contacto_telefone' => null,
            'formando' => (string) $d['nome'], 'email' => $email, 'funcao' => '', 'ferramentas' => is_array($ferramentas) ? array_map('strval', $ferramentas) : [], 'expectativas' => ''],
            is_array($respostas) ? $respostas : [], true);
        if ($r['erro'] === '') {
            executar("UPDATE inscricoes SET estado = 'confirmada' WHERE id = ?", [$r['id']]);
            $feitas[] = (string) $d['nome'];
        } else {
            $falhas[] = $d['nome'] . ' (' . rtrim($r['erro'], '.') . ')';
        }
    }
    aviso(($feitas ? count($feitas) . ' pessoa(s) inscrita(s) em ' . h($f['titulo']) . ' (' . h(horario($f)) . '), confirmada(s), sem enviar nenhum email: ' . h(implode(', ', $feitas)) . '. Os emails enviam-se depois na <a href="' . h(url('formacao', ['id' => $f['id']])) . '">ficha da edição</a>.' : 'Ninguém foi inscrito.')
        . ($falhas ? ' Não inscritas: ' . h(implode('; ', $falhas)) . '.' : ''), $falhas ? ($feitas ? 'info' : 'erro') : 'ok');
    redirecionar('diagnosticos', [], 'enviados');
}

/* Administração */

function admin_diagnosticos(): void
{
    $cursos = array_column(consulta('SELECT id, nome FROM cursos WHERE ativo = 1 ORDER BY id'), 'nome', 'id');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'inscrever_edicao') {
        inscrever_diagnosticos_sem_emails();
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        // Cursos escolhidos, pela ordem do catálogo
        $marcados = array_map('intval', array_filter((array) ($_POST['cursos'] ?? []), 'is_string'));
        $escolhidos = array_values(array_filter(array_keys($cursos), function ($id) use ($marcados) { return in_array((int) $id, $marcados, true); }));
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['utilizadores'] ?? [])))));
        if (!$escolhidos) {
            aviso('Escolha pelo menos um curso.', 'erro');
            redirecionar('diagnosticos');
        }
        if (!$ids) {
            aviso('Escolha pelo menos um utilizador.', 'erro');
            redirecionar('diagnosticos', ['cursos' => implode(',', $escolhidos)]);
        }
        $enviados = [];
        $falhados = [];
        foreach ($ids as $uid) {
            $u = linha_bd("SELECT * FROM utilizadores WHERE id = ? AND ativo = 1 AND papel IN ('formando', 'cliente')", [$uid]);
            if (!$u) {
                continue;
            }
            // Quem já tem um diagnóstico por responder recebe esse, com os cursos agora escolhidos e uma ligação nova
            $d = linha_bd('SELECT id FROM diagnosticos WHERE utilizador_id = ? AND respondido IS NULL ORDER BY id DESC LIMIT 1', [$uid]);
            if ($d) {
                $id = (int) $d['id'];
                executar('UPDATE diagnosticos SET cursos = ?, curso_id = ? WHERE id = ?', [json_encode($escolhidos), $escolhidos[0], $id]);
            } else {
                $id = executar('INSERT INTO diagnosticos (utilizador_id, nome, email, curso_id, cursos, enviado, enviado_por) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$uid, $u['nome'], trim((string) $u['email']), $escolhidos[0], json_encode($escolhidos), agora(), utilizador()['id']]);
            }
            if (enviar_ligacao_diagnostico($id)) {
                $enviados[] = h($u['nome']);
            } else {
                $falhados[] = h($u['nome']) . ' (' . h($u['email']) . ')';
            }
        }
        if ($enviados) {
            aviso('Diagnóstico (' . h(juntar_lista(array_map(function ($id) use ($cursos) { return $cursos[$id]; }, $escolhidos))) . ') enviado a ' . count($enviados) . ' utilizador(es): ' . implode(', ', $enviados) . '.');
        }
        if ($falhados) {
            aviso('Não foi possível enviar o email a: ' . implode(', ', $falhados) . '. Verifique o envio de emails em Configuração, Comunicação.', 'erro');
        }
        redirecionar('diagnosticos', ['cursos' => implode(',', $escolhidos)]);
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
    $pre = array_map('intval', explode(',', (string) ($_GET['cursos'] ?? '')));
    $opcoes = '';
    foreach ($cursos as $id => $nome) {
        $opcoes .= '<label class="opcao"><input type="checkbox" name="cursos[]" value="' . (int) $id . '"' . (in_array((int) $id, $pre, true) ? ' checked' : '') . '> ' . h($nome) . '</label>';
    }
    $envio = $lista
        ? '<form method="post" class="formulario">' . campo_csrf() . '
            <fieldset class="campo-grupo escolha-cursos"><legend>Cursos *</legend>
              <p class="ajuda">Escolha um ou vários. Cada pessoa recebe um só diagnóstico, com os cursos escolhidos indicados no email.</p>
              <div class="opcoes opcoes-grelha">' . $opcoes . '</div></fieldset>
            <div class="conteudo tabela-rolante"><table data-filtros="escolha" class="tabela-escolha"><thead><tr><th data-sem-filtro><input type="checkbox" class="marcar-todos" aria-label="Escolher todos os utilizadores visíveis"></th><th>Nome</th><th>Email</th><th data-filtro="lista">Perfil</th><th data-filtro="lista">Cliente</th><th>Último diagnóstico</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
            <div><button class="botao" type="submit">Enviar diagnóstico</button></div>
          </form>'
        : '<p class="texto-suave">Ainda não há utilizadores com o perfil Formando ou Cliente. Crie-os em Configuração, Utilizadores.</p>';

    // Diagnósticos enviados
    // Cliente: o associado ao utilizador que recebeu o diagnóstico (empresa do Formando ou cliente do utilizador Cliente)
    $enviados = consulta('SELECT d.*, c.nome AS cliente FROM diagnosticos d LEFT JOIN utilizadores u ON u.id = d.utilizador_id LEFT JOIN clientes c ON c.id = u.cliente_id ORDER BY d.enviado DESC');
    $linhas = '';
    foreach ($enviados as $d) {
        [$estado, $tipo] = estado_diagnostico($d);
        $resultado = '';
        $aconselhado = '';
        if ($d['respondido'] !== null && is_array($r = json_decode((string) $d['respostas'], true))) {
            $resultado = resumo_diagnostico(avaliar_diagnostico($r));
            $ind = aconselhado_do_diagnostico($d, $r);
            $aconselhado = $ind ? $ind['curso']['nome'] . ($ind['pedido'] ? '' : ' (não pedido)') : '';
        }
        $inscrito = inscricoes_do_email_html((string) $d['email']);
        $linhas .= '<tr><td><input type="checkbox" form="inscrever-diagnosticos" name="diag[]" value="' . (int) $d['id'] . '" aria-label="Escolher ' . h($d['nome']) . '"></td>'
            . '<td><a href="' . h(url('diagnostico', ['id' => $d['id']])) . '">' . h($d['nome']) . '</a></td><td>' . h($d['email']) . '</td><td>' . h((string) $d['cliente']) . '</td><td>' . h(nomes_cursos_diagnostico($d)) . '</td>'
            . '<td data-valor="' . h($d['enviado']) . '">' . h(data_hora($d['enviado'], false)) . '</td><td>' . etiqueta($estado, $tipo) . '</td>'
            . '<td data-valor="' . h((string) $d['pontuacao']) . '">' . h($resultado) . '</td><td>' . h($aconselhado) . '</td>'
            . '<td data-valor="' . ($inscrito !== '' ? 'Inscrito' : 'Sem inscrição') . '">' . ($inscrito !== '' ? $inscrito : '<span class="texto-suave">Sem inscrição</span>') . '</td></tr>';
    }
    $edicoes = '';
    foreach (edicoes_para_inscrever() as $f) {
        $edicoes .= '<option value="' . (int) $f['id'] . '">' . h($f['titulo'] . ' · ' . horario($f) . ' · ' . (ESTADOS_FORMACAO[$f['estado']] ?? $f['estado'])) . '</option>';
    }
    $inscrever = $linhas && $edicoes !== ''
        ? '<form method="post" id="inscrever-diagnosticos" class="formulario espaco-topo-curto">' . campo_csrf() . '<input type="hidden" name="acao" value="inscrever_edicao">
            <div class="campo"><label for="c-edicao">Inscrever os escolhidos na edição</label><select id="c-edicao" name="edicao" required><option value="">Escolha a edição</option>' . $edicoes . '</select></div>
            <div><button class="botao botao-secundario" type="submit">Inscrever sem enviar emails</button></div>
            <p class="ajuda">Para quem fez o diagnóstico sem pré-inscrição: fica inscrito na edição, confirmado, com o nome, o email, a empresa cliente do utilizador e as respostas do diagnóstico (se já respondeu). Não é enviado nenhum email nem criado o acesso à área do formando; envie-os depois na ficha da edição. Quem já está inscrito nessa edição não é inscrito de novo.</p>
          </form>' : '';
    pagina_area('diagnosticos', 'Diagnósticos', '
      <p class="texto-suave">Envie o diagnóstico de IA antes da formação: escolha um ou vários cursos e os utilizadores. Cada pessoa recebe por email uma só ligação pessoal, válida 30 dias e de uso único, e responde sem precisar de entrar na área reservada. São ' . (count(DIAGNOSTICO) + 1) . ' perguntas: ' . count(DIAGNOSTICO_V2) . ' sobre a utilização da IA, ' . count(DIAGNOSTICO_CONHECIMENTO) . ' de conhecimentos e as ferramentas usadas. O resultado e o curso aconselhado só aparecem aqui.</p>
      <h2 class="espaco-topo" id="enviados">Diagnósticos enviados</h2>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table data-filtros="enviados" class="tabela-escolha"><thead><tr><th data-sem-filtro><input type="checkbox" class="marcar-todos" aria-label="Escolher todos os diagnósticos visíveis"></th><th>Nome</th><th>Email</th><th data-filtro="lista">Cliente</th><th>Cursos</th><th>Enviado</th><th data-filtro="lista">Estado</th><th>Resultado</th><th data-filtro="lista">Curso aconselhado</th><th data-filtro="lista">Inscrição</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' . $inscrever
        : '<p class="texto-suave">Ainda não foi enviado nenhum diagnóstico.</p>') . '
      <h2 class="espaco-topo" id="enviar">Enviar o diagnóstico</h2>
      ' . $envio, 'Formação');
}

function admin_diagnostico(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $d = linha_bd('SELECT d.*, u.nome AS por FROM diagnosticos d LEFT JOIN utilizadores u ON u.id = d.enviado_por WHERE d.id = ?', [$id]);
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
    $dados = '<dl class="factos-area"><div><dt>Email</dt><dd>' . h($d['email']) . '</dd></div><div><dt>' . (count(cursos_do_diagnostico($d)) > 1 ? 'Cursos' : 'Curso') . '</dt><dd>' . h(nomes_cursos_diagnostico($d)) . '</dd></div>'
        . '<div><dt>Enviado</dt><dd>' . h(data_hora($d['enviado'])) . ($d['por'] ? ' por ' . h($d['por']) : '') . '</dd></div>'
        . '<div><dt>Estado</dt><dd>' . etiqueta($estado, $tipo) . ' ' . ($d['respondido'] !== null ? 'em ' . h(data_hora($d['respondido'])) : 'ligação válida até ' . h(data_hora($d['validade']))) . '</dd></div></dl>';
    $resultado = '';
    if ($d['respondido'] !== null && is_array($r = json_decode((string) $d['respostas'], true))) {
        $a = avaliar_diagnostico($r);
        $ind = aconselhado_do_diagnostico($d, $r);
        $fer = json_decode((string) $d['ferramentas'], true) ?: [];
        $resultado = '<h2 class="espaco-topo">Resultado</h2>
          <p><strong>' . h(resumo_diagnostico($a)) . '</strong></p>
          ' . ($ind ? '<p>Curso aconselhado: <strong>' . h($ind['curso']['nome']) . '</strong> (' . h($ind['motivo']) . '). ' . ($ind['pedido'] ? 'Está entre os cursos pedidos.' : 'Não está entre os cursos pedidos.') . '</p>' : '') . '
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
        ? linha_bd('SELECT * FROM diagnosticos WHERE token = ?', [hash('sha256', $t)]) : null;
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
      <h1>' . h(nome_curto((string) $d['nome'])) . ', boas-vindas ao diagnóstico</h1>
      <p class="lead">Para prepararmos a formação ' . h(($c = cursos_do_diagnostico($d)) ? juntar_lista(array_map(function ($x) { return 'no ' . $x['nome']; }, $c)) : '') . ', queremos conhecer o seu ponto de partida. São ' . $n . ' perguntas, cerca de seis minutos: as ' . count(DIAGNOSTICO_V2) . ' primeiras descrevem a forma como usa a IA (não há respostas certas) e as ' . count(DIAGNOSTICO_CONHECIMENTO) . ' seguintes avaliam conhecimentos. Pode responder até ' . h(data_hora($d['validade'])) . '.</p>
      <div class="aviso aviso-info diag-sem-ia"><strong>Sem recurso a IA.</strong> ' . h(DIAGNOSTICO_SEM_IA) . '</div>
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
    $d = linha_bd('SELECT * FROM diagnosticos WHERE id = ?', [$id]);
    $r = $d ? json_decode((string) $d['respostas'], true) : null;
    if (!is_array($r)) {
        return;
    }
    $a = avaliar_diagnostico($r);
    $ind = aconselhado_do_diagnostico($d, $r);
    $cursos = nomes_cursos_diagnostico($d);
    $linhas = array_filter(['Email' => (string) $d['email'], 'Cursos pedidos' => $cursos, 'Resultado' => resumo_diagnostico($a),
        'Curso aconselhado' => $ind ? $ind['curso']['nome'] . ' (' . $ind['motivo'] . ')' . ($ind['pedido'] ? '' : '; não está entre os cursos pedidos') : ''], 'strlen');
    $html = '';
    foreach ($linhas as $rotulo => $valor) {
        $html .= '<p style="margin:0 0 4px;"><strong>' . h($rotulo) . ':</strong> ' . h($valor) . '</p>';
    }
    $endereco = endereco_site() . '/clientes/area.php?' . http_build_query(['p' => 'diagnostico', 'id' => $id]);
    enviar_mensagem('diagnostico_aviso', email_admin(), ['formando' => $d['nome'], 'email' => $d['email'], 'curso' => $cursos], [
        $html . '<p style="margin:22px 0 0;"><a href="' . h($endereco) . '" style="display:inline-block;padding:13px 26px;background:#0A0F26;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:999px;">Ver as respostas</a></p>',
        implode("\n", array_map(function ($k, $v) { return $k . ': ' . $v; }, array_keys($linhas), $linhas)) . "\nVer as respostas: " . $endereco,
    ], ['responder_a' => (string) $d['email']]);
}
