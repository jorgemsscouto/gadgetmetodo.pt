<?php
// Área de clientes: páginas da administração (Gadgetmetodo).

declare(strict_types=1);

function admin_painel(): void
{
    $n = function (string $sql) { return (int) (linha_bd($sql)['n'] ?? 0); };
    $proximas = consulta("SELECT * FROM formacoes WHERE inicio >= ? AND estado != 'cancelada' ORDER BY inicio LIMIT 5", [date('Y-m-d 00:00:00')]);
    $ultimas = consulta('SELECT i.*, ' . SQL_CLIENTE . ', f.titulo, f.inicio FROM inscricoes i LEFT JOIN clientes c ON c.id = i.cliente_id JOIN formacoes f ON f.id = i.formacao_id ORDER BY i.criado DESC LIMIT 8');
    $linhas = '';
    foreach ($ultimas as $i) {
        $linhas .= '<tr><td>' . h(data_hora($i['criado'])) . '</td><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . h($i['cliente']) . etiqueta_origem($i) . '</td><td>' . h($i['titulo']) . ', ' . h(data_hora($i['inicio'], false)) . '</td><td>' . nivel_etiqueta($i['nivel']) . '</td></tr>';
    }
    pagina_area('painel', 'Painel', '
      <div class="numeros">
        <div><strong>' . $n("SELECT COUNT(*) AS n FROM formacoes WHERE inicio >= '" . date('Y-m-d') . "' AND estado != 'cancelada'") . '</strong>formações agendadas</div>
        <div><strong>' . $n("SELECT COUNT(*) AS n FROM inscricoes WHERE estado != 'cancelada'") . '</strong>inscrições ativas</div>
        <div><strong>' . $n('SELECT COUNT(*) AS n FROM clientes') . '</strong>clientes</div>
        <div><a href="' . h(url('inscricoes', ['origem' => 'por_associar'])) . '"><strong>' . $n("SELECT COUNT(*) AS n FROM inscricoes WHERE cliente_id IS NULL AND estado != 'cancelada'") . '</strong>inscrições do site por associar</a></div>
        <div><strong>' . $n('SELECT COUNT(*) AS n FROM utilizadores WHERE ativo = 1') . '</strong>utilizadores ativos</div>
      </div>
      <div class="acoes mb-0" style="margin-top:0"><a class="botao" href="' . h(url('formacao')) . '">Nova formação</a><a class="botao botao-secundario" href="' . h(url('utilizadores')) . '">Novo utilizador</a></div>
      <h2 class="espaco-topo">Próximas formações</h2>
      ' . calendario($proximas, function ($f) { return '<a href="' . h(url('formacao', ['id' => $f['id']])) . '">Gerir</a>'; }) . '
      <h2 class="espaco-topo">Últimas inscrições</h2>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Data</th><th>Formando</th><th>Cliente</th><th>Formação</th><th>Nível de IA</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há inscrições.</p>'), 'Gadgetmetodo');
}

// Assinala as inscrições feitas no site e as que ainda não têm cliente associado
function etiqueta_origem(array $i): string
{
    if (($i['origem'] ?? '') !== 'site') {
        return '';
    }
    return ' ' . etiqueta($i['cliente_id'] === null ? 'Site, por associar' : 'Site', $i['cliente_id'] === null ? 'info' : '');
}

/* Calendário de formações */

function admin_calendario(): void
{
    $passadas = ($_GET['ver'] ?? '') === 'passadas';
    $formacoes = $passadas
        ? consulta('SELECT * FROM formacoes WHERE inicio < ? ORDER BY inicio DESC', [date('Y-m-d 00:00:00')])
        : consulta('SELECT * FROM formacoes WHERE inicio >= ? ORDER BY inicio', [date('Y-m-d 00:00:00')]);
    pagina_area('calendario', 'Calendário de formações', '
      <div class="acoes" style="margin-top:0"><a class="botao" href="' . h(url('formacao')) . '">Nova formação</a>
      <a class="botao botao-secundario" href="' . h(url('calendario', $passadas ? [] : ['ver' => 'passadas'])) . '">' . ($passadas ? 'Ver próximas' : 'Ver formações passadas') . '</a>
      <a class="botao botao-secundario" href="/formacoes/" target="_blank" rel="noopener">Ver página pública</a></div>
      ' . calendario($formacoes, function ($f) {
          return '<a href="' . h(url('formacao', ['id' => $f['id']])) . '">Gerir e ver inscritos</a>'
              . ($f['estado'] !== 'cancelada' ? '<a href="' . h(url('inscrever', ['formacao' => $f['id']])) . '">Inscrever formando</a>' : '')
              . (!empty($f['reuniao']) ? '<a href="' . h($f['reuniao']) . '" target="_blank" rel="noopener">Abrir reunião</a>' : '');
      }), 'AI Essentials');
}

function admin_formacao(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $f = $id ? linha_bd('SELECT * FROM formacoes WHERE id = ?', [$id]) : null;
    if ($id && !$f) {
        pagina_erro('Formação não encontrada', 'A formação pedida não existe.');
    }
    $erros = [];
    $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    $acao = (string) ($_POST['acao'] ?? '');
    if ($post && $acao === 'apagar' && $f) {
        // Só se apaga uma formação sem inscrições; com inscrições, deve ser cancelada
        if ((int) (linha_bd('SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ?', [$id])['n'] ?? 0) > 0) {
            aviso('Esta formação tem inscrições e não pode ser apagada. Altere o estado para Cancelada.', 'erro');
            redirecionar('formacao', ['id' => $id]);
        }
        executar('DELETE FROM formacoes WHERE id = ?', [$id]);
        aviso('Formação apagada.');
        redirecionar('calendario');
    }
    if ($post && $acao === 'convite' && $f) {
        $convite = ler_convite();
        if (is_string($convite)) {
            aviso(h($convite), 'erro');
        } else {
            $quando = horario($f) . ' · ' . (MODALIDADES[$f['modalidade']] ?? '') . ($f['local'] ? ' · ' . $f['local'] : '');
            enviar_convite($convite['nome'], $convite['email'], $convite['mensagem'], 'Convite: ' . $f['titulo'] . ', ' . data_hora($f['inicio'], false),
                '<p style="margin:0 0 4px;"><strong>' . h($f['titulo']) . '</strong> · ' . h(nome_modulo($f)) . '</p><p style="margin:0;color:#687280;">' . h($quando) . '</p>',
                $f['titulo'] . ' · ' . nome_modulo($f) . "\n" . $quando, endereco_site() . '/formacoes/inscricao.php?f=' . $id, 'Inscrever-me nesta sessão');
            aviso('Convite enviado para ' . h($convite['email']) . '.');
        }
        redirecionar('formacao', ['id' => $id]);
    }
    if ($post && $acao === 'enviar_reuniao' && $f) {
        $n = enviar_reuniao_confirmados($f);
        aviso($n ? 'Ligação da reunião enviada para ' . $n . ' inscrição(ões) confirmada(s).' : 'Não há inscrições confirmadas com email para enviar a ligação.', $n ? 'ok' : 'erro');
        redirecionar('formacao', ['id' => $id]);
    }
    if ($post && $acao === '') {
        $d = [
            'curso_modulo' => (string) ($_POST['curso_modulo'] ?? ''),
            'data' => texto('data', 10), 'hora_inicio' => texto('hora_inicio', 5), 'hora_fim' => texto('hora_fim', 5),
            'modalidade' => isset(MODALIDADES[$_POST['modalidade'] ?? '']) ? $_POST['modalidade'] : 'presencial',
            'local' => texto('local', 160), 'vagas' => max(1, min(500, (int) ($_POST['vagas'] ?? 12))),
            'estado' => isset(ESTADOS_FORMACAO[$_POST['estado'] ?? '']) ? $_POST['estado'] : 'aberta',
            'notas' => texto('notas', 2000, true), 'reuniao' => texto('reuniao', 300),
        ];
        [$curso_id, $modulo_id] = curso_e_modulo($d['curso_modulo']);
        if (!$curso_id) {
            $erros[] = 'Escolha o curso e o módulo.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['data']) || !strtotime($d['data'])) {
            $erros[] = 'Indique a data.';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $d['hora_inicio'])) {
            $erros[] = 'Indique a hora de início.';
        }
        if ($d['hora_fim'] !== '' && (!preg_match('/^\d{2}:\d{2}$/', $d['hora_fim']) || $d['hora_fim'] <= $d['hora_inicio'])) {
            $erros[] = 'A hora de fim tem de ser posterior à de início.';
        }
        if ($d['reuniao'] !== '' && (!filter_var($d['reuniao'], FILTER_VALIDATE_URL) || stripos($d['reuniao'], 'https://') !== 0)) {
            $erros[] = 'A ligação da reunião tem de ser um endereço completo começado por https:// (por exemplo, https://meet.google.com/abc-defg-hij).';
        }
        if (!$erros) {
            $inicio = $d['data'] . ' ' . $d['hora_inicio'] . ':00';
            $fim = $d['hora_fim'] !== '' ? $d['data'] . ' ' . $d['hora_fim'] . ':00' : null;
            $valores = [curso($curso_id)['nome'], $curso_id, $modulo_id, $inicio, $fim, $d['modalidade'], $d['local'], $d['vagas'], $d['estado'], $d['notas'], $d['reuniao'] ?: null];
            if ($f) {
                executar('UPDATE formacoes SET titulo = ?, curso_id = ?, modulo_id = ?, inicio = ?, fim = ?, modalidade = ?, local = ?, vagas = ?, estado = ?, notas = ?, reuniao = ? WHERE id = ?', array_merge($valores, [$id]));
                aviso('Formação atualizada.');
            } else {
                $id = executar('INSERT INTO formacoes (titulo, curso_id, modulo_id, inicio, fim, modalidade, local, vagas, estado, notas, reuniao, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($valores, [agora()]));
                aviso('Formação criada e visível para os clientes e no calendário público.');
            }
            redirecionar('formacao', ['id' => $id]);
        }
        $f = ['id' => $id ?: null, 'titulo' => '', 'curso_id' => $curso_id, 'modulo_id' => $modulo_id, 'inicio' => $d['data'] . ' ' . $d['hora_inicio'], 'fim' => $d['hora_fim'] ? $d['data'] . ' ' . $d['hora_fim'] : '', 'modalidade' => $d['modalidade'], 'local' => $d['local'], 'vagas' => $d['vagas'], 'estado' => $d['estado'], 'notas' => $d['notas'], 'reuniao' => $d['reuniao']];
    }
    $v = $f ?: ['curso_id' => (int) ($_GET['curso'] ?? 0), 'modulo_id' => null, 'inicio' => '', 'fim' => '', 'modalidade' => 'presencial', 'local' => '', 'vagas' => 12, 'estado' => 'aberta', 'notas' => '', 'reuniao' => ''];
    $t = $v['inicio'] ? strtotime($v['inicio']) : false;
    $selecionado = !empty($v['modulo_id']) ? 'm:' . $v['modulo_id'] : (!empty($v['curso_id']) ? 'c:' . $v['curso_id'] : '');
    $atalhos = $id && $t
        ? '<a href="' . h(link_google_calendar($v + ['titulo' => curso((int) $v['curso_id'])['nome'] ?? ''])) . '" target="_blank" rel="noopener">abra esta sessão no Google Calendar</a> (data e hora já preenchidas), escolha "Adicionar videoconferência do Google Meet", guarde e copie a ligação para aqui; ou <a href="https://meet.new" target="_blank" rel="noopener">crie uma reunião rápida no Google Meet</a>.'
        : 'guarde primeiro a sessão para ter o atalho do Google Calendar com a data preenchida, ou <a href="https://meet.new" target="_blank" rel="noopener">crie uma reunião rápida no Google Meet</a> e cole aqui a ligação.';
    $form = ($erros ? '<div class="aviso aviso-erro">' . h(implode(' ', $erros)) . '</div>' : '') . '
      <form method="post" class="formulario">' . campo_csrf() . '
        <div class="campos-2">
          ' . seletor_curso_modulo($selecionado, (int) ($v['curso_id'] ?? 0)) . '
          ' . campo('data', 'Data *', $t ? date('Y-m-d', $t) : '', 'date', 'required') . '
          <div class="campos-2">' . campo('hora_inicio', 'Início *', $t ? date('H:i', $t) : '09:30', 'time', 'required') . campo('hora_fim', 'Fim', $v['fim'] ? date('H:i', strtotime($v['fim'])) : '13:00', 'time') . '</div>
          ' . campo_select('modalidade', 'Modalidade *', MODALIDADES, $v['modalidade']) . '
          ' . campo('local', 'Local', (string) $v['local'], 'text', 'maxlength="160" placeholder="Morada, nas sessões presenciais ou mistas"') . '
          ' . campo('vagas', 'Vagas *', (string) $v['vagas'], 'number', 'min="1" max="500" required') . '
          ' . campo_select('estado', 'Estado *', ESTADOS_FORMACAO, $v['estado']) . '
        </div>
        <div class="campo"><label for="c-reuniao">Ligação da reunião online (Google Meet)</label><input id="c-reuniao" name="reuniao" type="url" value="' . h((string) $v['reuniao']) . '" maxlength="300" placeholder="https://meet.google.com/abc-defg-hij" aria-describedby="ajuda-reuniao">
          <p class="ajuda" id="ajuda-reuniao">Só é enviada às inscrições confirmadas (no email de confirmação e na área de clientes); nunca aparece na página pública. Para a criar: ' . $atalhos . '</p></div>
        ' . campo('notas', 'Notas internas', (string) $v['notas'], 'textarea', 'maxlength="2000"') . '
        <div><button class="botao" type="submit">' . ($id ? 'Guardar alterações' : 'Criar formação') . '</button></div>
      </form>';
    $inscritos = '';
    if ($id) {
        $confirmadas = (int) (linha_bd("SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ? AND estado = 'confirmada'", [$id])['n'] ?? 0);
        if (!empty($v['reuniao']) && $confirmadas) {
            $form .= '<form method="post" class="espaco-topo">' . campo_csrf() . '<input type="hidden" name="acao" value="enviar_reuniao"><button class="botao botao-secundario" type="submit">Enviar a ligação da reunião às ' . $confirmadas . ' inscrição(ões) confirmada(s)</button>
              <p class="ajuda">Envia a cada formando (ou, sem email do formando, a quem o inscreveu) uma mensagem com a data, a hora e a ligação. Útil quando a ligação é criada ou alterada depois das confirmações, ou como lembrete.</p></form>';
        }
        $programa = programa_html($v, true);
        if ($v['estado'] === 'aberta' && strtotime($v['inicio']) >= strtotime('today')) {
            $form .= partilha_e_convite(endereco_site() . '/formacoes/inscricao.php?f=' . $id, 'Ligação de inscrição nesta sessão',
                'Quem abrir esta ligação vê o programa da sessão e faz a inscrição diretamente, sem conta. A inscrição fica pendente até a confirmar.');
        }
        $lista = consulta('SELECT i.*, ' . SQL_CLIENTE . ' FROM inscricoes i LEFT JOIN clientes c ON c.id = i.cliente_id WHERE i.formacao_id = ? ORDER BY i.criado', [$id]);
        $linhas = '';
        foreach ($lista as $i) {
            $linhas .= '<tr><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . h($i['funcao']) . '</td><td>' . h($i['cliente']) . etiqueta_origem($i) . '</td><td>' . nivel_etiqueta($i['nivel']) . '</td><td>' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</td></tr>';
        }
        $inscritos = ($programa ? '<div class="espaco-topo">' . $programa . '</div>' : '')
            . '<h2 class="espaco-topo">Inscritos (' . inscritos($id) . ' de ' . (int) $v['vagas'] . ')</h2>'
            . '<p><a class="botao" href="' . h(url('inscrever', ['formacao' => $id])) . '">Inscrever formando</a>'
            . ($linhas ? ' <a class="botao botao-secundario" href="' . h(url('inscricoes', ['formacao' => $id, 'exportar' => 1])) . '">Exportar inscritos para Excel</a>' : '') . '</p>'
            . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Formando</th><th>Função</th><th>Cliente</th><th>Nível de IA</th><th>Estado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há inscrições nesta formação.</p>');
        if (!$linhas && !linha_bd('SELECT id FROM inscricoes WHERE formacao_id = ?', [$id])) {
            $inscritos .= '<form method="post" class="espaco-topo">' . campo_csrf() . '<input type="hidden" name="acao" value="apagar"><button class="botao botao-secundario" type="submit">Apagar esta formação</button></form>';
        }
    }
    pagina_area('calendario', $id ? 'Editar formação' : 'Nova formação', $form . $inscritos, 'Calendário');
}

// Valor do seletor "Curso e módulo": c:ID (curso completo) ou m:ID (um módulo)
function curso_e_modulo(string $valor): array
{
    if (preg_match('/^c:(\d+)$/', $valor, $r) && curso((int) $r[1])) {
        return [(int) $r[1], null];
    }
    if (preg_match('/^m:(\d+)$/', $valor, $r) && ($m = linha_bd('SELECT id, curso_id FROM modulos WHERE id = ?', [(int) $r[1]]))) {
        return [(int) $m['curso_id'], (int) $m['id']];
    }
    return [0, null];
}

function seletor_curso_modulo(string $selecionado, int $curso_atual): string
{
    $opcoes = '<option value="">Escolha o curso e o módulo</option>';
    $opcao = function (string $valor, string $rotulo) use ($selecionado) {
        return '<option value="' . h($valor) . '"' . ($valor === $selecionado ? ' selected' : '') . '>' . h($rotulo) . '</option>';
    };
    foreach (consulta('SELECT * FROM cursos WHERE ativo = 1 OR id = ? ORDER BY nome', [$curso_atual]) as $c) {
        $mods = modulos_curso((int) $c['id']);
        $opcoes .= '<optgroup label="' . h($c['nome'] . ($c['tipologia'] ? ' · ' . $c['tipologia'] : '')) . '">'
            . $opcao('c:' . $c['id'], count($mods) > 1 ? $c['nome'] . ': curso completo (' . count($mods) . ' módulos)' : $c['nome'] . ': curso completo');
        foreach ($mods as $m) {
            $opcoes .= $opcao('m:' . $m['id'], $c['nome'] . ': ' . rotulo_modulo($m));
        }
        $opcoes .= '</optgroup>';
    }
    return '<div class="campo"><label for="c-curso_modulo">Curso e módulo *</label><select id="c-curso_modulo" name="curso_modulo" required>' . $opcoes . '</select></div>';
}

/* Cursos: configuração de cursos, tipologias, módulos e temas */

function admin_cursos(): void
{
    $lista = consulta("SELECT c.*, (SELECT COUNT(*) FROM modulos m WHERE m.curso_id = c.id) AS n_modulos,
        (SELECT COUNT(*) FROM formacoes f WHERE f.curso_id = c.id AND f.inicio >= ? AND f.estado != 'cancelada') AS n_sessoes
        FROM cursos c ORDER BY c.ativo DESC, c.nome", [date('Y-m-d 00:00:00')]);
    $linhas = '';
    foreach ($lista as $c) {
        $linhas .= '<tr><td><a href="' . h(url('curso', ['id' => $c['id']])) . '">' . h($c['nome']) . '</a></td><td>' . h($c['tipologia']) . '</td><td>' . (int) $c['n_modulos'] . '</td><td>' . (int) $c['n_sessoes'] . '</td><td>' . ($c['diagnostico'] ? 'Sim' : 'Não') . '</td><td>' . ($c['ativo'] ? etiqueta('Ativo', 'ok') : etiqueta('Inativo')) . '</td></tr>';
    }
    pagina_area('cursos', 'Cursos', '
      <p class="texto-suave">Cada curso tem tipologia, descrição, destinatários e módulos com os temas a abordar. As sessões do calendário são de um curso completo ou de um módulo. O diagnóstico de utilização de IA pode ser ativado em cada curso.</p>
      <div class="acoes" style="margin-top:0"><a class="botao" href="' . h(url('curso')) . '">Novo curso</a></div>
      ' . ($linhas ? '<div class="conteudo tabela-rolante espaco-topo-curto"><table><thead><tr><th>Curso</th><th>Tipologia</th><th>Módulos</th><th>Sessões agendadas</th><th>Diagnóstico de IA</th><th>Estado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há cursos.</p>'), 'Formação');
}

function admin_curso(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $c = $id ? linha_bd('SELECT * FROM cursos WHERE id = ?', [$id]) : null;
    if ($id && !$c) {
        pagina_erro('Curso não encontrado', 'O curso pedido não existe.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'curso') {
            $nome = texto('nome', 160);
            if ($nome === '') {
                aviso('Indique o nome do curso.', 'erro');
                redirecionar('curso', $id ? ['id' => $id] : []);
            }
            $valores = [$nome, texto('tipologia', 80), texto('descricao', 4000, true), texto('publico', 400), texto('duracao', 80), isset($_POST['diagnostico']) ? 1 : 0, isset($_POST['ativo']) ? 1 : 0];
            if ($c) {
                executar('UPDATE cursos SET nome = ?, tipologia = ?, descricao = ?, publico = ?, duracao = ?, diagnostico = ?, ativo = ? WHERE id = ?', array_merge($valores, [$id]));
                // As sessões guardam o nome do curso: acompanham a mudança de nome
                executar('UPDATE formacoes SET titulo = ? WHERE curso_id = ?', [$nome, $id]);
                aviso('Curso atualizado.');
            } else {
                $id = executar('INSERT INTO cursos (nome, tipologia, descricao, publico, duracao, diagnostico, ativo, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array_merge($valores, [agora()]));
                aviso('Curso criado. Acrescente agora os módulos e os temas a abordar.');
            }
        } elseif ($acao === 'modulo' && $c) {
            $mid = (int) ($_POST['modulo_id'] ?? 0);
            $nome = texto('nome', 160);
            $horas = str_replace(',', '.', texto('horas', 6));
            $valores = [$nome, max(1, min(99, (int) ($_POST['ordem'] ?? 1))), texto('descricao', 2000, true), texto('temas', 4000, true), is_numeric($horas) && (float) $horas > 0 ? min(999, (float) $horas) : null];
            if ($nome === '') {
                aviso('Indique o nome do módulo.', 'erro');
            } elseif ($mid) {
                executar('UPDATE modulos SET nome = ?, ordem = ?, descricao = ?, temas = ?, horas = ? WHERE id = ? AND curso_id = ?', array_merge($valores, [$mid, $id]));
                aviso('Módulo guardado.');
            } else {
                executar('INSERT INTO modulos (nome, ordem, descricao, temas, horas, curso_id) VALUES (?, ?, ?, ?, ?, ?)', array_merge($valores, [$id]));
                aviso('Módulo acrescentado.');
            }
        } elseif ($acao === 'apagar_modulo' && $c) {
            $mid = (int) ($_POST['modulo_id'] ?? 0);
            if (linha_bd('SELECT id FROM formacoes WHERE modulo_id = ?', [$mid])) {
                aviso('Este módulo tem sessões no calendário e não pode ser apagado.', 'erro');
            } else {
                executar('DELETE FROM modulos WHERE id = ? AND curso_id = ?', [$mid, $id]);
                aviso('Módulo apagado.');
            }
        } elseif ($acao === 'convite' && $c) {
            $convite = ler_convite();
            if (is_string($convite)) {
                aviso(h($convite), 'erro');
            } else {
                $sessoes = consulta("SELECT * FROM formacoes WHERE curso_id = ? AND inicio >= ? AND estado = 'aberta' ORDER BY inicio LIMIT 6", [$id, date('Y-m-d 00:00:00')]);
                $datas = array_map(function ($f) { return horario($f) . ' · ' . nome_modulo($f) . ' · ' . (MODALIDADES[$f['modalidade']] ?? ''); }, $sessoes);
                $detalhe = array_filter([$c['tipologia'], $c['duracao']]);
                enviar_convite($convite['nome'], $convite['email'], $convite['mensagem'], 'Convite: ' . $c['nome'],
                    '<p style="margin:0 0 4px;"><strong>' . h($c['nome']) . '</strong>' . ($detalhe ? ' · ' . h(implode(' · ', $detalhe)) : '') . '</p>'
                    . ($c['descricao'] ? '<p style="margin:0 0 10px;color:#687280;">' . h($c['descricao']) . '</p>' : '')
                    . ($datas ? '<p style="margin:0 0 4px;"><strong>Próximas sessões</strong></p><ul style="margin:0;padding-left:18px;">' . implode('', array_map(function ($d) { return '<li>' . h($d) . '</li>'; }, $datas)) . '</ul>' : ''),
                    $c['nome'] . ($detalhe ? ' · ' . implode(' · ', $detalhe) : '') . ($datas ? "\n\nPróximas sessões:\n- " . implode("\n- ", $datas) : ''),
                    endereco_site() . link_curso_publico($c), 'Ver o curso e inscrever-me');
                aviso('Convite enviado para ' . h($convite['email']) . '.');
            }
        } elseif ($acao === 'apagar_curso' && $c) {
            if (linha_bd('SELECT id FROM formacoes WHERE curso_id = ?', [$id])) {
                aviso('Este curso tem sessões no calendário e não pode ser apagado. Desative-o para deixar de o poder agendar.', 'erro');
            } else {
                executar('DELETE FROM modulos WHERE curso_id = ?', [$id]);
                executar('DELETE FROM cursos WHERE id = ?', [$id]);
                aviso('Curso apagado.');
                redirecionar('cursos');
            }
        }
        redirecionar('curso', ['id' => $id]);
    }
    $v = $c ?: ['nome' => '', 'tipologia' => '', 'descricao' => '', 'publico' => '', 'duracao' => '', 'diagnostico' => 0, 'ativo' => 1];
    $tipologias = array_unique(array_merge(TIPOLOGIAS, array_column(consulta("SELECT DISTINCT tipologia FROM cursos WHERE tipologia != ''"), 'tipologia')));
    $html = '<form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="curso">
        <div class="campos-2">
          ' . campo('nome', 'Nome do curso *', (string) $v['nome'], 'text', 'required maxlength="160"') . '
          ' . campo('tipologia', 'Tipologia', (string) $v['tipologia'], 'text', 'maxlength="80" list="tipologias" placeholder="Escolha da lista ou escreva uma nova"') . '
          ' . campo('duracao', 'Duração total', (string) $v['duracao'], 'text', 'maxlength="80" placeholder="Por exemplo, 15 horas ou 3 sessões de 4 horas"') . '
          ' . campo('publico', 'Destinatários', (string) $v['publico'], 'text', 'maxlength="400"') . '
        </div>
        <datalist id="tipologias">' . implode('', array_map(function ($t) { return '<option value="' . h($t) . '"></option>'; }, $tipologias)) . '</datalist>
        ' . campo('descricao', 'Descrição', (string) $v['descricao'], 'textarea', 'maxlength="4000"') . '
        <label class="opcao consentimento"><input type="checkbox" name="diagnostico" value="1"' . ($v['diagnostico'] ? ' checked' : '') . '> <span>Incluir o diagnóstico de utilização de IA na inscrição (recomendado nos cursos de inteligência artificial)</span></label>
        <label class="opcao consentimento"><input type="checkbox" name="ativo" value="1"' . ($v['ativo'] ? ' checked' : '') . '> <span>Curso ativo (disponível para agendar sessões)</span></label>
        <div><button class="botao" type="submit">' . ($c ? 'Guardar curso' : 'Criar curso') . '</button></div>
      </form>';
    if ($c) {
        $mods = modulos_curso($id);
        $html .= '<h2 class="espaco-topo">Módulos e temas a abordar</h2>
          <p class="texto-suave">Os temas aparecem no calendário e no formulário de inscrição, em "Programa e temas". Escreva um tema por linha. A ordem define a numeração dos módulos.</p>';
        foreach ($mods as $n => $m) {
            $html .= form_modulo($m, $n + 1);
        }
        $html .= '<h3 class="espaco-topo-curto">Acrescentar módulo</h3>' . form_modulo(['id' => 0, 'ordem' => count($mods) + 1, 'nome' => '', 'descricao' => '', 'temas' => '', 'horas' => null], 0);
        if ($c['ativo']) {
            $html .= partilha_e_convite(endereco_site() . link_curso_publico($c), 'Página pública do curso',
                'Mostra a descrição, os módulos, os temas e as próximas sessões com inscrição. Pode colocá-la no site, nas redes sociais ou enviá-la a um candidato.');
        }
        $sessoes = consulta('SELECT * FROM formacoes WHERE curso_id = ? AND inicio >= ? ORDER BY inicio', [$id, date('Y-m-d 00:00:00')]);
        $html .= '<h2 class="espaco-topo">Sessões agendadas</h2>'
            . ($sessoes ? calendario($sessoes, function ($f) { return '<a href="' . h(url('formacao', ['id' => $f['id']])) . '">Gerir</a>'; }) : '<p class="texto-suave">Este curso não tem sessões agendadas.</p>')
            . '<p class="espaco-topo-curto"><a class="botao" href="' . h(url('formacao', ['curso' => $id])) . '">Agendar sessão deste curso</a></p>';
        if (!linha_bd('SELECT id FROM formacoes WHERE curso_id = ?', [$id])) {
            $html .= '<form method="post" class="espaco-topo">' . campo_csrf() . '<input type="hidden" name="acao" value="apagar_curso"><button class="botao botao-secundario" type="submit">Apagar este curso</button></form>';
        }
    }
    pagina_area('cursos', $c ? $c['nome'] : 'Novo curso', $html, 'Cursos');
}

function form_modulo(array $m, int $numero): string
{
    $novo = !$m['id'];
    $p = 'm' . (int) $m['id'] . '-';
    return '<div class="cartao-modulo">
      <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="modulo"><input type="hidden" name="modulo_id" value="' . (int) $m['id'] . '">
        ' . ($novo ? '' : '<span class="sobretitulo mb-0">Módulo ' . $numero . '</span>') . '
        <div class="campos-modulo">
          ' . campo('nome', 'Nome do módulo *', (string) $m['nome'], 'text', 'required maxlength="160"', $p . 'nome') . '
          ' . campo('ordem', 'Ordem', (string) $m['ordem'], 'number', 'min="1" max="99"', $p . 'ordem') . '
          ' . campo('horas', 'Horas', $m['horas'] ? rtrim(rtrim(number_format((float) $m['horas'], 1, ',', ''), '0'), ',') : '', 'text', 'maxlength="6" inputmode="decimal"', $p . 'horas') . '
        </div>
        ' . campo('descricao', 'Descrição', (string) $m['descricao'], 'textarea', 'maxlength="2000" rows="2"', $p . 'descricao') . '
        ' . campo('temas', 'Temas a abordar (um por linha)', (string) $m['temas'], 'textarea', 'maxlength="4000"', $p . 'temas') . '
        <div class="acoes-modulo"><button class="botao' . ($novo ? '' : ' botao-secundario') . '" type="submit">' . ($novo ? 'Acrescentar módulo' : 'Guardar módulo') . '</button></div>
      </form>
      ' . ($novo ? '' : '<form method="post" class="apagar-modulo">' . campo_csrf() . '<input type="hidden" name="acao" value="apagar_modulo"><input type="hidden" name="modulo_id" value="' . (int) $m['id'] . '"><button class="botao-texto" type="submit">Apagar módulo</button></form>') . '
    </div>';
}

/* Inscrições */

function admin_inscricoes(): void
{
    $origens = ['site' => 'Site', 'por_associar' => 'Site, por associar a cliente', 'area' => 'Área de clientes'];
    $filtros = ['formacao' => (int) ($_GET['formacao'] ?? 0), 'cliente' => (int) ($_GET['cliente'] ?? 0), 'estado' => isset(ESTADOS_INSCRICAO[$_GET['estado'] ?? '']) ? $_GET['estado'] : '', 'origem' => isset($origens[$_GET['origem'] ?? '']) ? $_GET['origem'] : ''];
    $sql = 'SELECT i.*, ' . SQL_CLIENTE . ', f.titulo, f.inicio FROM inscricoes i LEFT JOIN clientes c ON c.id = i.cliente_id JOIN formacoes f ON f.id = i.formacao_id WHERE 1 = 1';
    $par = [];
    if ($filtros['formacao']) { $sql .= ' AND i.formacao_id = ?'; $par[] = $filtros['formacao']; }
    if ($filtros['cliente']) { $sql .= ' AND i.cliente_id = ?'; $par[] = $filtros['cliente']; }
    if ($filtros['estado']) { $sql .= ' AND i.estado = ?'; $par[] = $filtros['estado']; }
    if ($filtros['origem'] === 'por_associar') { $sql .= ' AND i.cliente_id IS NULL'; }
    elseif ($filtros['origem']) { $sql .= ' AND i.origem = ?'; $par[] = $filtros['origem']; }
    $lista = consulta($sql . ' ORDER BY f.inicio DESC, i.criado', $par);
    if (!empty($_GET['exportar'])) {
        $linhas = [];
        foreach ($lista as $i) {
            $linhas[] = [$i['titulo'], data_hora($i['inicio']), $i['cliente'], ORIGENS[$i['origem']] ?? $i['origem'], $i['contacto_nome'], $i['contacto_email'], $i['contacto_telefone'], $i['formando'], $i['email'], $i['funcao'], ESTADOS_INSCRICAO[$i['estado']] ?? $i['estado'], $i['nivel'], $i['pontuacao'], $i['responsavel'], $i['ferramentas'], $i['expectativas'], data_hora($i['criado'])];
        }
        exportar_csv('gadgetmetodo-inscricoes', ['Formação', 'Data da formação', 'Cliente ou empresa', 'Origem', 'Contacto (site)', 'Email do contacto', 'Telefone do contacto', 'Formando', 'Email', 'Função', 'Estado', 'Nível de IA', 'Pontuação (0-24)', 'Uso responsável (0-9)', 'Ferramentas usadas', 'Expectativas', 'Data da inscrição'], $linhas);
    }
    $formacoes = [];
    foreach (consulta('SELECT id, titulo, inicio FROM formacoes ORDER BY inicio DESC') as $f) {
        $formacoes[$f['id']] = data_hora($f['inicio'], false) . ' · ' . $f['titulo'];
    }
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    $linhas = '';
    foreach ($lista as $i) {
        $linhas .= '<tr><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . h($i['cliente']) . etiqueta_origem($i) . '</td><td>' . h($i['titulo']) . '<br><span class="texto-suave">' . h(data_hora($i['inicio'])) . '</span></td><td>' . nivel_etiqueta($i['nivel']) . '</td><td>' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</td><td>' . h(data_hora($i['criado'])) . '</td></tr>';
    }
    pagina_area('inscricoes', 'Inscrições', '
      <form method="get" class="filtros">
        <input type="hidden" name="p" value="inscricoes">
        ' . campo_select('formacao', 'Formação', $formacoes, $filtros['formacao'], 'Todas') . campo_select('cliente', 'Cliente', $clientes, $filtros['cliente'], 'Todos') . campo_select('estado', 'Estado', ESTADOS_INSCRICAO, $filtros['estado'], 'Todos') . campo_select('origem', 'Origem', $origens, $filtros['origem'], 'Todas') . '
        <div class="filtros-acoes"><button class="botao" type="submit">Filtrar</button><a class="botao botao-secundario" href="' . h(url('inscricoes', array_filter($filtros) + ['exportar' => 1])) . '">Exportar para Excel</a></div>
      </form>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Formando</th><th>Cliente</th><th>Formação</th><th>Nível de IA</th><th>Estado</th><th>Inscrito em</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Sem inscrições para os filtros escolhidos.</p>'), 'Formações');
}

/* Clientes */

function admin_clientes(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $nome = texto('nome', 160);
        if ($nome === '') {
            aviso('Indique o nome do cliente.', 'erro');
            redirecionar('clientes');
        }
        $id = executar('INSERT INTO clientes (nome, nif, contacto, email, telefone, notas, criado) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$nome, texto('nif', 20), texto('contacto', 120), texto('email', 160), texto('telefone', 30), texto('notas', 2000, true), agora()]);
        aviso('Cliente criado. Crie agora o acesso de um utilizador deste cliente.');
        redirecionar('utilizadores', ['cliente' => $id]);
    }
    $lista = consulta('SELECT c.*, (SELECT COUNT(*) FROM utilizadores u WHERE u.cliente_id = c.id) AS n_utilizadores, (SELECT COUNT(*) FROM inscricoes i WHERE i.cliente_id = c.id AND i.estado != \'cancelada\') AS n_inscricoes FROM clientes c ORDER BY c.nome');
    $linhas = '';
    foreach ($lista as $c) {
        $linhas .= '<tr><td><a href="' . h(url('cliente', ['id' => $c['id']])) . '">' . h($c['nome']) . '</a></td><td>' . h($c['nif']) . '</td><td>' . h($c['contacto']) . '<br><span class="texto-suave">' . h($c['email']) . '</span></td><td>' . (int) $c['n_utilizadores'] . '</td><td>' . (int) $c['n_inscricoes'] . '</td></tr>';
    }
    pagina_area('clientes', 'Clientes', '
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Cliente</th><th>NIF</th><th>Contacto</th><th>Utilizadores</th><th>Inscrições</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há clientes.</p>') . '
      <h2 class="espaco-topo">Novo cliente</h2>
      ' . form_cliente([]), 'Gestão');
}

function form_cliente(array $c): string
{
    return '<form method="post" class="formulario">' . campo_csrf() . '<div class="campos-2">
        ' . campo('nome', 'Empresa *', (string) ($c['nome'] ?? ''), 'text', 'required maxlength="160"') . '
        ' . campo('nif', 'NIF', (string) ($c['nif'] ?? ''), 'text', 'maxlength="20"') . '
        ' . campo('contacto', 'Pessoa de contacto', (string) ($c['contacto'] ?? ''), 'text', 'maxlength="120"') . '
        ' . campo('email', 'Email', (string) ($c['email'] ?? ''), 'email', 'maxlength="160"') . '
        ' . campo('telefone', 'Telefone', (string) ($c['telefone'] ?? ''), 'tel', 'maxlength="30"') . '
      </div>' . campo('notas', 'Notas internas', (string) ($c['notas'] ?? ''), 'textarea', 'maxlength="2000"') . '
      <div><button class="botao" type="submit">' . (isset($c['id']) ? 'Guardar alterações' : 'Criar cliente') . '</button></div></form>';
}

function admin_cliente(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $c = linha_bd('SELECT * FROM clientes WHERE id = ?', [$id]);
    if (!$c) {
        pagina_erro('Cliente não encontrado', 'O cliente pedido não existe.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $nome = texto('nome', 160);
        if ($nome !== '') {
            executar('UPDATE clientes SET nome = ?, nif = ?, contacto = ?, email = ?, telefone = ?, notas = ? WHERE id = ?',
                [$nome, texto('nif', 20), texto('contacto', 120), texto('email', 160), texto('telefone', 30), texto('notas', 2000, true), $id]);
            aviso('Cliente atualizado.');
        }
        redirecionar('cliente', ['id' => $id]);
    }
    $utilizadores = consulta('SELECT * FROM utilizadores WHERE cliente_id = ? ORDER BY nome', [$id]);
    $lu = '';
    foreach ($utilizadores as $u) {
        $lu .= '<li><a href="' . h(url('utilizador', ['id' => $u['id']])) . '">' . h($u['nome']) . '</a> · ' . h($u['email']) . ($u['ativo'] ? '' : ' ' . etiqueta('Inativo')) . '</li>';
    }
    pagina_area('clientes', $c['nome'], form_cliente($c) . '
      <h2 class="espaco-topo">Utilizadores deste cliente</h2>
      ' . ($lu ? '<ul>' . $lu . '</ul>' : '<p class="texto-suave">Ainda não tem utilizadores.</p>') . '
      <p><a class="botao botao-secundario" href="' . h(url('utilizadores', ['cliente' => $id])) . '">Criar utilizador</a> <a class="botao botao-secundario" href="' . h(url('inscricoes', ['cliente' => $id])) . '">Ver inscrições</a></p>', 'Cliente');
}

/* Utilizadores */

function admin_utilizadores(): void
{
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $nome = texto('nome', 120);
        $email = texto('email', 160);
        $papel = ($_POST['papel'] ?? '') === 'admin' ? 'admin' : 'cliente';
        $cliente_id = $papel === 'cliente' ? (int) ($_POST['cliente_id'] ?? 0) : null;
        $senha = (string) ($_POST['senha'] ?? '');
        $erro = '';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Indique o nome e um email válido.';
        } elseif ($papel === 'cliente' && !isset($clientes[$cliente_id])) {
            $erro = 'Escolha o cliente deste utilizador.';
        } elseif ($senha !== '' && strlen($senha) < 10) {
            $erro = 'A palavra-passe tem de ter pelo menos 10 caracteres.';
        } elseif (linha_bd('SELECT id FROM utilizadores WHERE email = ?', [$email])) {
            $erro = 'Já existe um utilizador com este email.';
        }
        if ($erro) {
            aviso(h($erro), 'erro');
            redirecionar('utilizadores');
        }
        $gerada = $senha === '';
        $senha = $gerada ? gerar_senha() : $senha;
        executar('INSERT INTO utilizadores (email, nome, senha, papel, cliente_id, criado) VALUES (?, ?, ?, ?, ?, ?)', [$email, $nome, password_hash($senha, PASSWORD_DEFAULT), $papel, $cliente_id, agora()]);
        aviso('Utilizador criado. Dados de acesso para comunicar a ' . h($nome) . ':<br>Endereço: <strong>https://www.gadgetmetodo.pt/clientes/</strong><br>Email: <strong>' . h($email) . '</strong><br>Palavra-passe: <strong class="senha">' . h($senha) . '</strong><br>' . ($gerada ? 'Esta palavra-passe não volta a ser mostrada.' : ''));
        redirecionar('utilizadores');
    }
    $lista = consulta('SELECT u.*, c.nome AS cliente FROM utilizadores u LEFT JOIN clientes c ON c.id = u.cliente_id ORDER BY u.papel, c.nome, u.nome');
    $linhas = '';
    foreach ($lista as $u) {
        $linhas .= '<tr><td><a href="' . h(url('utilizador', ['id' => $u['id']])) . '">' . h($u['nome']) . '</a></td><td>' . h($u['email']) . '</td><td>' . h(PAPEIS[$u['papel']]) . '</td><td>' . h($u['cliente']) . '</td><td>' . ($u['ativo'] ? etiqueta('Ativo', 'ok') : etiqueta('Inativo')) . '</td><td>' . h(data_hora($u['ultimo_acesso'])) . '</td></tr>';
    }
    $pre = (int) ($_GET['cliente'] ?? 0);
    pagina_area('utilizadores', 'Utilizadores', '
      <div class="conteudo tabela-rolante"><table><thead><tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Cliente</th><th>Estado</th><th>Último acesso</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
      <h2 class="espaco-topo">Novo utilizador</h2>
      <p class="texto-suave">Perfil Gadgetmetodo: vê e gere tudo. Perfil Cliente: vê apenas as formações e as inscrições do seu cliente.</p>
      <form method="post" class="formulario">' . campo_csrf() . '<div class="campos-2">
        ' . campo('nome', 'Nome *', '', 'text', 'required maxlength="120"') . '
        ' . campo('email', 'Email (é o nome de utilizador) *', '', 'email', 'required maxlength="160" autocomplete="off"') . '
        ' . campo_select('papel', 'Perfil *', PAPEIS, 'cliente') . '
        ' . campo_select('cliente_id', 'Cliente (perfil Cliente)', $clientes, $pre, 'Escolha o cliente') . '
        ' . campo('senha', 'Palavra-passe', '', 'text', 'minlength="10" autocomplete="new-password" placeholder="Vazio: é gerada automaticamente"') . '
      </div><div><button class="botao" type="submit">Criar utilizador</button></div></form>', 'Gestão de acessos');
}

function admin_utilizador(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $u = linha_bd('SELECT * FROM utilizadores WHERE id = ?', [$id]);
    if (!$u) {
        pagina_erro('Utilizador não encontrado', 'O utilizador pedido não existe.');
    }
    $eu = (int) utilizador()['id'] === $id;
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (($_POST['acao'] ?? '') === 'nova_senha') {
            $senha = gerar_senha();
            executar('UPDATE utilizadores SET senha = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $id]);
            aviso('Nova palavra-passe de ' . h($u['nome']) . ': <strong class="senha">' . h($senha) . '</strong><br>Comunique-a ao utilizador; não volta a ser mostrada.');
            redirecionar('utilizador', ['id' => $id]);
        }
        $nome = texto('nome', 120);
        $email = texto('email', 160);
        $papel = ($_POST['papel'] ?? '') === 'admin' ? 'admin' : 'cliente';
        $cliente_id = $papel === 'cliente' ? (int) ($_POST['cliente_id'] ?? 0) : null;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $erro = '';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Indique o nome e um email válido.';
        } elseif ($papel === 'cliente' && !isset($clientes[$cliente_id])) {
            $erro = 'Escolha o cliente deste utilizador.';
        } elseif ($eu && ($papel !== 'admin' || !$ativo)) {
            $erro = 'Não pode retirar o seu próprio acesso de administração.';
        } elseif (linha_bd('SELECT id FROM utilizadores WHERE email = ? AND id != ?', [$email, $id])) {
            $erro = 'Já existe outro utilizador com este email.';
        }
        if ($erro) {
            aviso(h($erro), 'erro');
        } else {
            executar('UPDATE utilizadores SET nome = ?, email = ?, papel = ?, cliente_id = ?, ativo = ? WHERE id = ?', [$nome, $email, $papel, $cliente_id, $ativo, $id]);
            aviso('Utilizador atualizado.');
        }
        redirecionar('utilizador', ['id' => $id]);
    }
    pagina_area('utilizadores', $u['nome'], '
      <form method="post" class="formulario">' . campo_csrf() . '<div class="campos-2">
        ' . campo('nome', 'Nome *', $u['nome'], 'text', 'required maxlength="120"') . '
        ' . campo('email', 'Email *', $u['email'], 'email', 'required maxlength="160"') . '
        ' . campo_select('papel', 'Perfil *', PAPEIS, $u['papel']) . '
        ' . campo_select('cliente_id', 'Cliente (perfil Cliente)', $clientes, $u['cliente_id'], 'Escolha o cliente') . '
      </div>
      <label class="opcao consentimento"><input type="checkbox" name="ativo" value="1"' . ($u['ativo'] ? ' checked' : '') . '> Acesso ativo</label>
      <div><button class="botao" type="submit">Guardar alterações</button></div></form>
      <h2 class="espaco-topo">Palavra-passe</h2>
      <p class="texto-suave">Gera uma palavra-passe nova, mostrada uma única vez, para comunicar ao utilizador.</p>
      <form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="nova_senha"><button class="botao botao-secundario" type="submit">Gerar nova palavra-passe</button></form>
      <p class="texto-suave espaco-topo">Criado em ' . h(data_hora($u['criado'])) . '. Último acesso: ' . h(data_hora($u['ultimo_acesso']) ?: 'nunca') . '.</p>', 'Utilizador');
}

/* Pedidos do site e aberturas do catálogo */

function admin_pedidos(): void
{
    $registos = ler_registos(cfg());
    if (!empty($_GET['exportar'])) {
        $linhas = [];
        foreach ($registos as $p) {
            $ab = $p['aberturas'];
            $linhas[] = [$p['ref'], data_hora($p['criado']), $p['tipo'], $p['nome'], $p['empresa'], $p['email'], $p['telemovel'], $p['servicos'], count($ab), $ab ? data_hora($ab[0]['aberto']) : '', $ab ? data_hora($ab[count($ab) - 1]['aberto']) : ''];
        }
        exportar_csv('gadgetmetodo-pedidos', ['Referência', 'Data do pedido', 'Tipo', 'Nome', 'Empresa', 'Email', 'Telemóvel', 'Serviços', 'Aberturas do catálogo', 'Primeira abertura', 'Última abertura'], $linhas);
    }
    $linhas = '';
    foreach ($registos as $p) {
        $reais = array_filter($p['aberturas'], function ($a) { return !$a['automatica']; });
        $ab = $p['aberturas'] ? implode('<br>', array_map(function ($a) { return h(data_hora($a['aberto'])) . ($a['automatica'] ? ' ' . etiqueta('automática') : ''); }, $p['aberturas'])) : etiqueta('Não aberto');
        $linhas .= '<tr><td>' . h($p['ref']) . '<br><span class="texto-suave">' . h(data_hora($p['criado'])) . '</span></td><td>' . h($p['nome']) . '<br><span class="texto-suave">' . h($p['empresa']) . '</span></td><td><a href="mailto:' . h($p['email']) . '">' . h($p['email']) . '</a><br>' . h($p['telemovel']) . '</td><td>' . h($p['servicos']) . '</td><td>' . ($reais ? etiqueta('Aberto', 'ok') . '<br>' : '') . $ab . '</td></tr>';
    }
    pagina_area('pedidos', 'Pedidos do site', '
      <p class="texto-suave">Pedidos de orçamento e de informações recebidos pelo formulário do site e aberturas do catálogo enviado na confirmação. Registos dos últimos 12 meses.</p>
      <p><a class="botao botao-secundario" href="' . h(url('pedidos', ['exportar' => 1])) . '">Exportar para Excel</a></p>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Pedido</th><th>Nome e empresa</th><th>Contactos</th><th>Serviços</th><th>Catálogo</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há pedidos registados.</p>'), 'Formulário do site');
}

/* Partilha de ligações e convites a candidatos */

function endereco_site(): string
{
    return rtrim((string) (cfg()['site'] ?? 'https://www.gadgetmetodo.pt'), '/');
}

function ler_convite()
{
    $c = ['nome' => texto('convite_nome', 120), 'email' => texto('convite_email', 160), 'mensagem' => texto('convite_mensagem', 1500, true)];
    if ($c['nome'] === '' || !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Indique o nome e um email válido do candidato.';
    }
    return $c;
}

function partilha_e_convite(string $link, string $titulo, string $ajuda): string
{
    return '<div class="partilha espaco-topo">
      <h2 class="mt-0">' . h($titulo) . '</h2>
      <p class="texto-suave">' . h($ajuda) . '</p>
      <div class="partilha-ligacao"><label class="visualmente-oculto" for="ligacao-publica">' . h($titulo) . '</label><input id="ligacao-publica" type="text" readonly value="' . h($link) . '"><a class="botao botao-secundario" href="' . h($link) . '" target="_blank" rel="noopener">Abrir</a></div>
      <h3>Enviar a um candidato</h3>
      <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="convite">
        <div class="campos-2">
          ' . campo('convite_nome', 'Nome do candidato *', '', 'text', 'required maxlength="120" autocomplete="off"') . '
          ' . campo('convite_email', 'Email do candidato *', '', 'email', 'required maxlength="160" autocomplete="off"') . '
        </div>
        ' . campo('convite_mensagem', 'Mensagem (opcional; se ficar em branco, é usado um texto padrão)', '', 'textarea', 'maxlength="1500" rows="3"') . '
        <div><button class="botao" type="submit">Enviar convite por email</button></div>
      </form>
    </div>';
}
