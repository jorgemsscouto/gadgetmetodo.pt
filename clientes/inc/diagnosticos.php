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

/* Formandos, vista Diagnósticos: lista dos diagnósticos e envio a formandos */

// Envia o diagnóstico de um curso aos formandos indicados (ids); devolve [enviados, falhados]
function enviar_diagnostico_lote(array $ids, int $curso): array
{
    $enviados = [];
    $falhados = [];
    $inscritos = [];
    foreach ($ids as $fid) {
        $f = formando((int) $fid);
        if (!$f) {
            continue;
        }
        try {
            [, $ok, $como] = enviar_diagnostico_formando((int) $fid, $curso);
            if ($como === 'inscrito') {
                $inscritos[] = $f['nome'];
            } else {
                $ok ? $enviados[] = $f['nome'] : $falhados[] = $f['nome'] . ' (falha no envio)';
            }
        } catch (InvalidArgumentException $e) {
            $falhados[] = $f['nome'] . ' (' . mb_strtolower(rtrim($e->getMessage(), '.')) . ')';
        }
    }
    return [$enviados, $falhados, $inscritos];
}

function admin_diagnosticos(): void
{
    $cursos = opcoes_cursos();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $lote = ($_POST['acao'] ?? '') === 'diagnostico_lote';
        $curso = (int) ($lote ? ($_POST['curso_lote'] ?? 0) : ($_POST['curso'] ?? 0));
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST[$lote ? 'formando' : 'formandos'] ?? [])))));
        if (!isset($cursos[$curso])) {
            aviso('Escolha o curso: quem responder fica inscrito nesse curso.', 'erro');
        } elseif (!$ids) {
            aviso('Escolha pelo menos um formando.', 'erro');
        } else {
            [$enviados, $falhados, $inscritos] = enviar_diagnostico_lote($ids, $curso);
            if ($inscritos) {
                aviso(count($inscritos) . ' formando(s) com diagnóstico inicial ficaram inscritos diretamente no ' . h($cursos[$curso]) . ', sem novo diagnóstico: ' . h(implode(', ', $inscritos)) . '.');
            }
            if ($enviados) {
                aviso('Diagnóstico do ' . h($cursos[$curso]) . ' enviado a ' . count($enviados) . ' formando(s): ' . h(implode(', ', $enviados)) . '. Quem responder fica inscrito neste curso.');
            }
            if ($falhados) {
                aviso('Sem envio: ' . h(implode('; ', $falhados)) . '.', 'erro');
            }
        }
        redirecionar('formandos', $lote ? [] : ['vista' => 'diagnosticos']);
    }

    // Diagnósticos enviados, com o formando, o curso e a inscrição criada
    $enviados = consulta('SELECT d.*, c.nome AS cliente FROM diagnosticos d LEFT JOIN formandos fo ON fo.id = d.formando_id LEFT JOIN clientes c ON c.id = fo.cliente_id ORDER BY d.enviado DESC');
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
        $nome = $d['formando_id'] ? '<a href="' . h(url('formando', ['id' => $d['formando_id']])) . '" translate="no">' . h($d['nome']) . '</a>' : '<span translate="no">' . h($d['nome']) . '</span>';
        $linhas .= '<tr><td>' . $nome . '</td><td translate="no">' . h($d['email']) . '</td><td>' . h((string) $d['cliente']) . '</td><td><a href="' . h(url('diagnostico', ['id' => $d['id']])) . '">' . h(nomes_cursos_diagnostico($d)) . '</a></td>'
            . '<td data-valor="' . h($d['enviado']) . '">' . h(data_hora($d['enviado'], false)) . '</td><td>' . etiqueta($estado, $tipo) . '</td>'
            . '<td data-valor="' . h((string) $d['pontuacao']) . '">' . h($resultado) . '</td><td>' . h($aconselhado) . '</td>'
            . '<td>' . ($d['inscricao_id'] ? '<a href="' . h(url('formando', ['id' => $d['formando_id']])) . '#inscricao-' . (int) $d['inscricao_id'] . '">Inscrito</a>' : '') . '</td></tr>';
    }
    // Envio: formandos com email
    $lista = consulta("SELECT f.*, c.nome AS cliente, (SELECT MAX(d.enviado) FROM diagnosticos d WHERE d.formando_id = f.id) AS ultimo
        FROM formandos f LEFT JOIN clientes c ON c.id = f.cliente_id WHERE trim(COALESCE(f.email, '')) != '' ORDER BY f.nome COLLATE NOCASE");
    $opcoes = '';
    foreach ($lista as $f) {
        $opcoes .= '<tr><td><input type="checkbox" name="formandos[]" value="' . (int) $f['id'] . '" id="f-' . (int) $f['id'] . '" aria-label="Enviar a ' . h($f['nome']) . '"></td>'
            . '<td><label for="f-' . (int) $f['id'] . '" translate="no">' . h($f['nome']) . '</label></td><td translate="no">' . h($f['email']) . '</td><td>' . h((string) $f['cliente']) . '</td>'
            . '<td data-valor="' . h((string) $f['ultimo']) . '">' . h(data_hora($f['ultimo'], false)) . '</td></tr>';
    }
    pagina_area('formandos', 'Diagnósticos', separadores_formandos('diagnosticos') . '
      <p class="texto-suave">O diagnóstico de IA é a 1.ª etapa: cada formando recebe por email uma ligação pessoal, válida 30 dias e de uso único, para o curso escolhido. Quando responde, fica inscrito nesse curso e recebe o email da inscrição. São ' . (count(DIAGNOSTICO) + 1) . ' perguntas: ' . count(DIAGNOSTICO_V2) . ' sobre a utilização da IA, ' . count(DIAGNOSTICO_CONHECIMENTO) . ' de conhecimentos e as ferramentas usadas. O resultado e o curso aconselhado só aparecem aqui.</p>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table data-filtros="enviados2"><thead><tr><th>Nome</th><th>Email</th><th data-filtro="lista">Cliente</th><th data-filtro="lista">Curso</th><th>Enviado</th><th data-filtro="lista">Estado</th><th>Resultado</th><th data-filtro="lista">Curso aconselhado</th><th data-filtro="lista">Inscrição</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>'
        : '<p class="texto-suave">Ainda não foi enviado nenhum diagnóstico.</p>') . '
      <h2 class="espaco-topo" id="enviar">Enviar o diagnóstico</h2>
      ' . ($lista ? '<form method="post" class="formulario">' . campo_csrf() . '
          <div class="campos-2">' . campo_select('curso', 'Curso (quem responder fica inscrito neste curso) *', $cursos, '', 'Escolha o curso', 'required') . '</div>
          <div class="conteudo tabela-rolante"><table data-filtros="escolha2" class="tabela-escolha"><thead><tr><th data-sem-filtro><input type="checkbox" class="marcar-todos" aria-label="Escolher todos os formandos visíveis"></th><th>Nome</th><th>Email</th><th data-filtro="lista">Cliente</th><th>Último diagnóstico</th></tr></thead><tbody>' . $opcoes . '</tbody></table></div>
          <div><button class="botao" type="submit">Enviar diagnóstico</button></div>
        </form>' : '<p class="texto-suave">Ainda não há formandos com email. Crie-os em <a href="' . h(url('formandos')) . '#novo">Formandos, Novo formando</a>.</p>'), 'Formação');
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
    pagina_area('formandos', 'Diagnóstico de ' . $d['nome'], $dados . $resultado . '
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
        if ($erro === '' && empty($_POST['consentimento'])) {
            $erro = 'Para continuar, aceite a política de privacidade.';
        }
        if ($erro === '') {
            $a = avaliar_diagnostico($respostas);
            $ferramentas = array_merge($v['ferramentas'], $v['ferramenta_outra'] !== '' ? ['Outra: ' . $v['ferramenta_outra']] : []);
            executar('UPDATE diagnosticos SET respostas = ?, ferramentas = ?, pontuacao = ?, nivel = ?, respondido = ?, token = NULL WHERE id = ? AND respondido IS NULL',
                [json_encode($respostas), json_encode($ferramentas, JSON_UNESCAPED_UNICODE), $a['pontuacao'], $a['nivel'], agora(), $d['id']]);
            // Consentimento (RGPD) registado no formando; inscrição automática no curso indicado e email da inscrição
            if (!empty($d['formando_id'])) {
                executar('UPDATE formandos SET consentimento = COALESCE(consentimento, ?) WHERE id = ?', [agora(), $d['formando_id']]);
            }
            $iid = inscrever_apos_diagnostico((int) $d['id']);
            aviso_diagnostico_respondido((int) $d['id']);
            documento('Diagnóstico enviado', $cabecalho . '<main class="secao"><div class="contentor estreito"><div class="entrada-cartao"><span class="sobretitulo">Diagnóstico de IA</span>
              <h1>Obrigado, ' . h(nome_curto((string) $d['nome'])) . '</h1>
              <p class="lead">As suas respostas foram registadas. A Gadgetmetodo vai analisá-las para preparar a formação de acordo com o seu ponto de partida.</p>
              ' . ($iid ? '<p>Está inscrito(a) no ' . h((string) (curso((int) $d['curso_id'])['nome'] ?? '')) . '. Enviámos para ' . h((string) $d['email']) . ' a confirmação da inscrição, com a ligação para ativar a sua área de formando e o pedido dos dados para o certificado.</p>' : '') . '</div></div></main>');
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
        <label class="opcao consentimento"><input type="checkbox" name="consentimento" value="1" required> <span>Li a <a href="/privacidade/" target="_blank" rel="noopener">Política de privacidade</a> e autorizo o tratamento destes dados pela Gadgetmetodo, Lda. para a minha inscrição e formação. *</span></label>
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
