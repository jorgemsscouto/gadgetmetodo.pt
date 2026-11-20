<?php
// Área de clientes: páginas da administração (Gadgetmetodo).

declare(strict_types=1);

function admin_painel(): void
{
    $n = function (string $sql) { return (int) (linha_bd($sql)['n'] ?? 0); };
    $proximas = consulta("SELECT * FROM formacoes WHERE inicio >= ? AND estado != 'cancelada' ORDER BY inicio LIMIT 5", [date('Y-m-d 00:00:00')]);
    $ultimas = consulta('SELECT i.*, ' . SQL_CLIENTE . ', COALESCE(f.titulo, (SELECT c2.nome FROM cursos c2 WHERE c2.id = i.curso_id)) AS titulo, f.inicio FROM inscricoes i LEFT JOIN clientes c ON c.id = i.cliente_id LEFT JOIN formacoes f ON f.id = i.formacao_id ORDER BY i.criado DESC LIMIT 8');
    $linhas = '';
    foreach ($ultimas as $i) {
        $linhas .= '<tr><td>' . h(data_hora($i['criado'])) . '</td><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . cliente_html($i) . etiqueta_origem($i) . '</td><td>' . h($i['titulo']) . ', ' . h(data_hora($i['inicio'], false)) . '</td><td>' . nivel_etiqueta($i['nivel']) . '</td></tr>';
    }
    pagina_area('painel', 'Painel', '
      <div class="numeros">
        <div><strong>' . $n("SELECT COUNT(*) AS n FROM formacoes WHERE inicio >= '" . date('Y-m-d') . "' AND estado != 'cancelada'") . '</strong>formações agendadas</div>
        <div><strong>' . $n("SELECT COUNT(*) AS n FROM inscricoes WHERE estado NOT IN ('desistiu', 'nao_concluido')") . '</strong>inscrições ativas</div>
        <div><strong>' . $n('SELECT COUNT(*) AS n FROM clientes') . '</strong>clientes</div>
        <div><a href="' . h(url('inscricoes', ['origem' => 'por_associar'])) . '"><strong>' . $n("SELECT COUNT(*) AS n FROM inscricoes WHERE cliente_id IS NULL AND estado NOT IN ('desistiu', 'nao_concluido')") . '</strong>inscrições do site por associar</a></div>
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
    // Duas vistas, como no seletor "Semana | Mês" do portal de referência: "Mês" (grelha com as sessões de cada dia)
    // e "Lista" (edições próximas ou passadas)
    $lista = ($_GET['vista'] ?? '') === 'lista';
    $passadas = ($_GET['ver'] ?? '') === 'passadas';
    $mes = mes_pedido();
    $alternador = '<div class="alternador" role="group" aria-label="Vista do calendário">'
        . '<a href="' . h(url('calendario', ['mes' => $mes])) . '"' . ($lista ? '' : ' aria-current="page"') . '>Mês</a>'
        . '<a href="' . h(url('calendario', ['vista' => 'lista'])) . '"' . ($lista ? ' aria-current="page"' : '') . '>Lista</a></div>';
    $conteudo = calendario_mensal($mes, false);
    if ($lista) {
        $formacoes = $passadas
            ? consulta('SELECT * FROM formacoes WHERE inicio < ? ORDER BY inicio DESC', [date('Y-m-d 00:00:00')])
            : consulta('SELECT * FROM formacoes WHERE inicio >= ? ORDER BY inicio', [date('Y-m-d 00:00:00')]);
        $conteudo = '<h2 id="lista">' . ($passadas ? 'Edições passadas' : 'Próximas edições') . '</h2>'
            . calendario($formacoes, function ($f) {
                return '<a href="' . h(url('formacao', ['id' => $f['id']])) . '">Gerir e ver inscritos</a>'
                    . ($f['estado'] !== 'cancelada' ? '<a href="' . h(url('inscrever', ['formacao' => $f['id']])) . '">Inscrever formando</a>' : '')
                    . (!empty($f['reuniao']) ? '<a href="' . h($f['reuniao']) . '" target="_blank" rel="noopener">Abrir reunião</a>' : '');
            });
    }
    pagina_area('calendario', 'Calendário de formações', '
      <div class="barra-calendario' . ($lista ? '' : ' com-mes') . '">' . $alternador . ($lista ? '' : cabecalho_calendario($mes)) . '
        <div class="acoes"><a class="botao" href="' . h(url('formacao')) . '">Nova formação</a>
        ' . ($lista ? '<a class="botao botao-secundario" href="' . h(url('calendario', $passadas ? ['vista' => 'lista'] : ['vista' => 'lista', 'ver' => 'passadas'])) . '">' . ($passadas ? 'Ver próximas' : 'Ver formações passadas') . '</a>' : '') . '
        <a class="botao botao-secundario" href="/formacoes/" target="_blank" rel="noopener">Ver página pública</a></div>
      </div>
      ' . $conteudo, 'Formação');
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
    if ($post && $acao === 'convite' && $f) {
        $convite = ler_convite();
        if (is_string($convite)) {
            aviso(h($convite), 'erro');
        } else {
            $quando = horario($f) . ' · ' . modalidade_e_local($f);
            $ok = enviar_convite('convite', $convite['nome'], $convite['email'], $convite['mensagem'], ['formacao' => $f['titulo'], 'data' => data_hora($f['inicio'], false)],
                '<p style="margin:0 0 4px;"><strong>' . h($f['titulo']) . '</strong> · ' . h(nome_modulo($f)) . '</p><p style="margin:0;color:#687280;">' . h($quando) . '</p>',
                $f['titulo'] . ' · ' . nome_modulo($f) . "\n" . $quando, endereco_site() . '/formacoes/inscricao.php?f=' . $id, 'Inscrever-me nesta edição');
            aviso_envio($ok, 'Convite enviado para ' . h($convite['email']) . '.');
        }
        redirecionar('formacao', ['id' => $id]);
    }
    if ($post && $acao === 'enviar_reuniao' && $f) {
        $n = enviar_reuniao_confirmados($f);
        aviso($n ? 'Ligação da reunião enviada para ' . $n . ' inscrição(ões) confirmada(s).' : 'Não há inscrições confirmadas com email para enviar a ligação.', $n ? 'ok' : 'erro');
        redirecionar('formacao', ['id' => $id]);
    }
    if ($post && $acao === 'juntar' && $f) {
        $ids = array_map('intval', array_filter((array) ($_POST['edicoes'] ?? []), 'is_numeric'));
        $ordenar = !empty($_POST['ordenar']);
        if (!$ids && !$ordenar) {
            aviso('Escolha as edições a juntar ou a opção de pôr as sessões por ordem.', 'erro');
            redirecionar('formacao', ['id' => $id], 'juntar');
        }
        [$juntadas, $movidas, $repetidas, $n_sessoes] = juntar_edicoes($f, $ids, !empty($_POST['datas']), $ordenar);
        $partes = [];
        if ($juntadas) {
            $partes[] = ($juntadas === 1 ? '1 edição juntada' : $juntadas . ' edições juntadas') . ': ' . $movidas . ' inscrição(ões) passaram para esta edição'
                . ($repetidas ? ' (' . ($repetidas === 1 ? '1 inscrição repetida da mesma pessoa ficou cancelada' : $repetidas . ' inscrições repetidas da mesma pessoa ficaram canceladas') . ', sem se perderem dados)' : '') . '.';
        }
        if ($ordenar && $n_sessoes > 1) {
            $partes[] = 'As ' . $n_sessoes . ' sessões ficaram por ordem de data, cada uma com a sessão do curso correspondente.';
        }
        $vagas = (int) (linha_bd('SELECT vagas FROM formacoes WHERE id = ?', [$id])['vagas'] ?? 0);
        if (inscritos($id) > $vagas) {
            $partes[] = 'Atenção: a edição tem agora ' . inscritos($id) . ' inscritos para ' . $vagas . ' vagas.';
        }
        aviso($partes ? implode(' ', $partes) : 'Nada a alterar.', $partes ? 'ok' : 'info');
        redirecionar('formacao', ['id' => $id]);
    }
    if ($post && in_array($acao, ['presencas', 'acessos'], true) && $f) {
        tratar_presencas_edicao($f);
    }
    if ($post && in_array($acao, ['cert_pedir', 'cert_emitir', 'cert_enviar'], true) && $f) {
        tratar_certificados_edicao($f);
    }
    if ($post && $acao === 'lembrete' && $f) {
        [$enviados, $falhados, $s] = enviar_lembrete_sessao($f, (int) ($_POST['sessao'] ?? 0));
        if (!$s) {
            aviso('Escolha a sessão do lembrete.', 'erro');
        } elseif ($enviados) {
            aviso('Lembrete da ' . h($s['nome'] ? mb_strtolower(mb_substr($s['nome'], 0, 1)) . mb_substr($s['nome'], 1) : 'sessão') . ' (' . h(dia_sessao($s['inicio'])) . ', ' . h(hora_sessao($s)) . ') enviado para ' . $enviados . ' formando(s).'
                . ($falhados ? ' ' . $falhados . ' sem envio: sem email ou com falha no envio.' : ''), $falhados ? 'info' : 'ok');
        } else {
            aviso('O lembrete não foi enviado: não há inscrições confirmadas com email, ou o envio de emails falhou.', 'erro');
        }
        redirecionar('formacao', ['id' => $id], 'lembretes');
    }
    if ($post && $acao === 'antecedencia' && $f) {
        $escolha = (string) ($_POST['antecedencia'] ?? '');
        $min = $escolha === 'outra' ? (int) ($_POST['antecedencia_min'] ?? 0) : (int) $escolha;
        if ($escolha === 'outra' && ($min < 5 || $min > 10080)) {
            aviso('Indique a antecedência em minutos, entre 5 e 10080 (7 dias).', 'erro');
        } elseif ($escolha !== 'outra' && !isset(ANTECEDENCIAS[$min])) {
            aviso('Escolha a antecedência do lembrete automático.', 'erro');
        } else {
            executar('UPDATE formacoes SET lembrete_antecedencia = ? WHERE id = ?', [$min, $id]);
            aviso($min ? 'Lembrete automático desta edição: ' . texto_antecedencia($min) . ' antes de cada sessão.' : 'Esta edição deixa de ter lembrete automático.');
        }
        redirecionar('formacao', ['id' => $id], 'lembretes');
    }
    if ($post && $acao === '') {
        [$d, $curso_id, $erros] = ler_sessao();
        // "Aplicar o formato": volta ao formulário com as sessões do novo formato, sem gravar
        $aplicar = !empty($_POST['aplicar_formato']);
        $erros = $aplicar ? [] : $erros;
        if (!$erros && !$aplicar) {
            $id = gravar_sessao($d, $curso_id, $f ? $id : 0);
            aviso($f ? 'Formação atualizada.' : 'Formação criada e visível para os clientes e na área de formação do site.');
            redirecionar('formacao', ['id' => $id]);
        }
        $f = ['id' => $id ?: null, 'titulo' => '', 'curso_id' => $curso_id, 'modulo_id' => $f['modulo_id'] ?? null, 'inicio' => $d['data'] . ' ' . $d['hora_inicio'], 'fim' => $d['hora_fim'] ? $d['data'] . ' ' . $d['hora_fim'] : '', 'modalidade' => $d['modalidade'], 'local' => $d['local'], 'vagas' => $d['vagas'], 'estado' => $d['estado'], 'notas' => $d['notas'], 'reuniao' => $d['reuniao'], 'formador_id' => $d['formador_id']];
    }
    $v = $f ?: ['curso_id' => (int) ($_GET['curso'] ?? 0), 'modulo_id' => null, 'inicio' => '', 'fim' => '', 'modalidade' => 'presencial', 'local' => '', 'vagas' => 12, 'estado' => 'aberta', 'notas' => '', 'reuniao' => '', 'formador_id' => null];
    $t = $v['inicio'] ? strtotime($v['inicio']) : false;
    $selecionado = (int) ($v['curso_id'] ?? 0);
    $datas = $id ? sessoes_formacao((int) $id) : [];
    $primeira = $datas ? ['inicio' => $datas[0]['inicio'], 'fim' => $datas[0]['fim'], 'modulo_id' => $datas[0]['modulo_id']] + $v : $v;
    $atalhos = $id && $t
        ? '<a href="' . h(link_google_calendar($primeira + ['titulo' => curso((int) $v['curso_id'])['nome'] ?? ''])) . '" target="_blank" rel="noopener">abra ' . ($datas ? 'a 1.ª sessão' : 'esta sessão') . ' no Google Calendar</a> (data e hora já preenchidas), escolha "Adicionar videoconferência do Google Meet", guarde e copie a ligação para aqui; ou <a href="https://meet.new" target="_blank" rel="noopener">crie uma reunião rápida no Google Meet</a>.'
        : 'guarde primeiro a formação para ter o atalho do Google Calendar com a data preenchida, ou <a href="https://meet.new" target="_blank" rel="noopener">crie uma reunião rápida no Google Meet</a> e cole aqui a ligação.';
    // Formações antigas criadas só para um módulo: ao guardar passam a ser uma edição do curso completo
    $antiga = !empty($v['modulo_id']) && ($m = linha_bd('SELECT * FROM modulos WHERE id = ?', [(int) $v['modulo_id']]))
        ? '<div class="aviso aviso-info">Esta formação foi criada só para "' . h(rotulo_modulo($m)) . '". As inscrições são agora sempre no curso completo, com todas as sessões: ao guardar, a formação passa a ter as datas de todas as sessões indicadas abaixo.</div>' : '';
    // Edição com datas só para parte das sessões do curso (por exemplo, juntada a partir de formações agendadas sessão a sessão)
    // Sessões previstas: as do formato gravado na edição ou, sem formato, uma por tema do curso
    $n_mods = count(duracoes_formato((string) ($v['formato'] ?? '')) ?: modulos_curso((int) ($v['curso_id'] ?? 0)));
    $incompleta = $id && !$antiga && $n_mods > 1 && max(1, count($datas)) < $n_mods
        ? '<div class="aviso aviso-info">Esta edição tem data para ' . max(1, count($datas)) . ' das ' . $n_mods . ' sessões do curso. Indique as datas das restantes na lista abaixo e guarde.</div>' : '';
    $form = ($erros ? '<div class="aviso aviso-erro">' . h(implode(' ', $erros)) . '</div>' : '') . $antiga . $incompleta . '
      <form method="post" class="formulario">' . campo_csrf() . '
        <div class="campos-2">
          ' . seletor_curso($selecionado) . '
          ' . campo('data', 'Data (da 1.ª sessão) *', $t ? date('Y-m-d', $t) : '', 'date', 'required') . '
          <div class="campos-2">' . campo('hora_inicio', 'Início *', $t ? date('H:i', $t) : '09:30', 'time', 'required') . campo('hora_fim', 'Fim', ($s1 = sessoes_formacao((int) $id)) ? ($s1[0]['fim'] ? date('H:i', strtotime($s1[0]['fim'])) : '') : ($v['fim'] ? date('H:i', strtotime($v['fim'])) : '13:00'), 'time') . '</div>
          ' . campo_select('modalidade', 'Modalidade *', MODALIDADES, $v['modalidade']) . '
          ' . campo('local', 'Local', (string) $v['local'], 'text', 'maxlength="160" placeholder="Morada, nas sessões presenciais ou mistas"') . '
          ' . campo('vagas', 'Vagas *', (string) $v['vagas'], 'number', 'min="1" max="500" required') . '
          ' . campo_select('estado', 'Estado *', ESTADOS_FORMACAO, $v['estado']) . '
          ' . campo_select('formador_id', 'Formador (assina os certificados)', array_map(function ($x) { return $x['nome'] . ($x['ccp'] ? ' · CCP ' . $x['ccp'] : ''); }, formadores()), (string) ($v['formador_id'] ?? ''), 'Por indicar (' . CERTIFICADO_FORMADOR . ')') . '
        </div>
        ' . campos_sessoes_curso($selecionado, sessoes_do_formulario((int) $id), formato_do_formulario((int) $id)) . '
        <div class="campo"><label for="c-reuniao">Ligação da reunião online (Google Meet)</label><input id="c-reuniao" name="reuniao" type="url" value="' . h((string) $v['reuniao']) . '" maxlength="300" placeholder="https://meet.google.com/abc-defg-hij" aria-describedby="ajuda-reuniao">
          <p class="ajuda" id="ajuda-reuniao">Só é enviada às inscrições confirmadas (no email de confirmação e na área reservada); nunca aparece na página pública. Para a criar: ' . $atalhos . '</p></div>
        ' . campo('notas', 'Notas internas', (string) $v['notas'], 'textarea', 'maxlength="2000"') . '
        <div><button class="botao" type="submit">' . ($id ? 'Guardar alterações' : 'Criar formação') . '</button></div>
      </form>';
    $inscritos = '';
    if ($id) {
        $confirmadas = (int) (linha_bd("SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ? AND estado IN ('inscrito', 'em_formacao', 'concluido', 'certificado', 'encerrado')", [$id])['n'] ?? 0);
        if (!empty($v['reuniao']) && $confirmadas) {
            $form .= '<form method="post" class="espaco-topo">' . campo_csrf() . '<input type="hidden" name="acao" value="enviar_reuniao"><button class="botao botao-secundario" type="submit">Enviar a ligação da reunião às ' . $confirmadas . ' inscrição(ões) confirmada(s)</button>
              <p class="ajuda">Envia a cada formando (ou, sem email do formando, a quem o inscreveu) uma mensagem com a data, a hora e a ligação. Útil quando a ligação é criada ou alterada depois das confirmações. Para lembrar a próxima sessão, use "Lembretes aos formandos", na lista de inscritos.</p></form>';
        }
        $programa = programa_html($v, true);
        if (count($datas) > 1) {
            $linhas_datas = '';
            foreach (linhas_sessoes($v) as $n => $texto) {
                $s = $datas[$n];
                $linhas_datas .= '<li>' . h($texto) . ' · <a href="' . h(link_google_calendar(['inicio' => $s['inicio'], 'fim' => $s['fim'], 'modulo_id' => $s['modulo_id']] + $v + ['titulo' => curso((int) $v['curso_id'])['nome'] ?? ''])) . '" target="_blank" rel="noopener">Google Calendar</a></li>';
            }
            $programa = '<h2 class="espaco-topo">Datas das ' . count($datas) . ' sessões</h2><p class="texto-suave">Os atalhos abrem o Google Calendar com a data e a hora de cada sessão já preenchidas. Pode usar a mesma ligação do Google Meet em todas.</p><ol class="lista-datas">' . $linhas_datas . '</ol>' . $programa;
        }
        if ($v['estado'] === 'aberta' && strtotime($v['inicio']) >= strtotime('today')) {
            $form .= partilha_e_convite(endereco_site() . '/formacoes/inscricao.php?f=' . $id, 'Ligação de inscrição nesta edição',
                'Quem abrir esta ligação vê o programa e as datas desta edição e faz a inscrição diretamente, sem conta. A inscrição inclui todas as sessões do curso e fica pendente até a confirmar.');
        }
        $lista = consulta('SELECT i.*, ' . SQL_CLIENTE . ' FROM inscricoes i LEFT JOIN clientes c ON c.id = i.cliente_id WHERE i.formacao_id = ? ORDER BY i.criado', [$id]);
        $linhas = '';
        foreach ($lista as $i) {
            $linhas .= '<tr><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . h($i['funcao']) . '</td><td>' . cliente_html($i) . etiqueta_origem($i) . '</td><td>' . nivel_etiqueta($i['nivel']) . '</td><td>' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</td></tr>';
        }
        $inscritos = ($programa ? '<div class="espaco-topo">' . $programa . '</div>' : '')
            . '<h2 class="espaco-topo">Inscritos (' . inscritos($id) . ' de ' . (int) $v['vagas'] . ')</h2>'
            . '<p><a class="botao" href="' . h(url('inscrever', ['formacao' => $id])) . '">Inscrever formando</a>'
            . ($linhas ? ' <a class="botao botao-secundario" href="' . h(url('inscricoes', ['formacao' => $id, 'exportar' => 1])) . '">Exportar inscritos para Excel</a>' : '') . '</p>'
            . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Formando</th><th>Função</th><th>Cliente</th><th>Nível de IA</th><th>Estado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há inscrições nesta formação.</p>');
        $inscritos .= $linhas ? lembretes_edicao(linha_bd('SELECT * FROM formacoes WHERE id = ?', [$id]), $confirmadas) : '';
        $inscritos .= $confirmadas ? presencas_edicao_html(linha_bd('SELECT * FROM formacoes WHERE id = ?', [$id])) : '';
        $inscritos .= $confirmadas ? certificados_edicao_html(linha_bd('SELECT * FROM formacoes WHERE id = ?', [$id])) : '';
        $inscritos .= juntar_edicoes_html(linha_bd('SELECT * FROM formacoes WHERE id = ?', [$id]));
        $inscritos .= '<p class="espaco-topo">' . botao_apagar('formacao', $id, 'Apagar esta edição') . '</p>';
    }
    pagina_area('calendario', $id ? 'Editar formação' : 'Nova formação', $form . $inscritos, 'Calendário');
}

/* Juntar edições do mesmo curso e pôr as sessões por ordem */

// Outras edições do mesmo curso que podem ser juntadas a esta (não canceladas, dos últimos 6 meses em diante)
function edicoes_para_juntar(array $f): array
{
    return consulta("SELECT f.*, (SELECT COUNT(*) FROM inscricoes i WHERE i.formacao_id = f.id AND i.estado NOT IN ('desistiu', 'nao_concluido')) AS n_inscritos,
            (SELECT COUNT(*) FROM inscricoes i WHERE i.formacao_id = f.id AND i.estado IN ('inscrito', 'em_formacao', 'concluido', 'certificado', 'encerrado')) AS n_confirmadas
        FROM formacoes f WHERE f.curso_id = ? AND f.id != ? AND f.estado != 'cancelada' AND COALESCE(f.fim, f.inicio) >= ? ORDER BY f.inicio",
        [(int) $f['curso_id'], (int) $f['id'], date('Y-m-d 00:00:00', strtotime('-180 days'))]);
}

// Sessões de uma formação com data e hora (as da tabela "sessoes" ou, numa só data, a própria formação)
function datas_da_edicao(array $f): array
{
    return sessoes_formacao((int) $f['id']) ?: [['inicio' => $f['inicio'], 'fim' => $f['fim'] ?? null, 'modulo_id' => $f['modulo_id'] ?? null]];
}

// Junta a esta edição as edições indicadas do mesmo curso: as inscrições passam para esta (a mesma pessoa fica com uma só
// inscrição, a confirmada ou a mais antiga; as outras ficam canceladas) e as edições juntadas são apagadas. Com $datas, as
// datas das sessões dessas edições juntam-se às desta (a mesma data e hora conta uma vez), por ordem de data. Com $ordenar,
// cada data recebe a sessão do curso pela ordem (1.ª data, sessão 1; 2.ª data, sessão 2...).
// Devolve [edições juntadas, inscrições movidas, inscrições repetidas canceladas, n.º de sessões].
function juntar_edicoes(array $f, array $ids, bool $datas, bool $ordenar): array
{
    $pdo = bd();
    $id = (int) $f['id'];
    $origens = [];
    foreach (array_unique($ids) as $o) {
        if ($e = linha_bd('SELECT * FROM formacoes WHERE id = ? AND curso_id = ? AND id != ?', [$o, (int) $f['curso_id'], $id])) {
            $origens[] = $e;
        }
    }
    $pdo->beginTransaction();
    try {
        $sessoes = [];
        foreach (array_merge([$f], $datas ? $origens : []) as $e) {
            foreach (datas_da_edicao($e) as $s) {
                $sessoes[$s['inicio']] = $sessoes[$s['inicio']] ?? $s;
            }
        }
        ksort($sessoes);
        $sessoes = array_values($sessoes);
        if ($ordenar) {
            foreach (modulos_curso((int) $f['curso_id']) as $n => $m) {
                if (isset($sessoes[$n])) {
                    $sessoes[$n]['modulo_id'] = (int) $m['id'];
                }
            }
        }
        if ($datas && $origens || $ordenar) {
            executar('DELETE FROM sessoes WHERE formacao_id = ?', [$id]);
            foreach (count($sessoes) > 1 ? $sessoes : [] as $n => $s) {
                executar('INSERT INTO sessoes (formacao_id, ordem, modulo_id, inicio, fim) VALUES (?, ?, ?, ?, ?)', [$id, $n + 1, $s['modulo_id'] ?: null, $s['inicio'], $s['fim'] ?: null]);
            }
            $fins = array_filter(array_column($sessoes, 'fim'));
            $ultima = $sessoes[count($sessoes) - 1];
            executar('UPDATE formacoes SET inicio = ?, fim = ?, modulo_id = NULL WHERE id = ?', [min(array_column($sessoes, 'inicio')), $ultima['fim'] ?: ($fins ? max($fins) : null), $id]);
        }
        // Local e ligação da reunião: os desta edição; em falta, os da primeira edição juntada que os tenha
        foreach (['local', 'reuniao'] as $campo) {
            if (empty($f[$campo])) {
                foreach ($origens as $e) {
                    if (!empty($e[$campo])) {
                        executar("UPDATE formacoes SET $campo = ? WHERE id = ?", [$e[$campo], $id]);
                        break;
                    }
                }
            }
        }
        $movidas = 0;
        foreach ($origens as $e) {
            $movidas += (int) (linha_bd('SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ?', [$e['id']])['n'] ?? 0);
            executar('UPDATE inscricoes SET formacao_id = ? WHERE formacao_id = ?', [$id, $e['id']]);
            executar('DELETE FROM sessoes WHERE formacao_id = ?', [$e['id']]);
            executar('DELETE FROM formacoes WHERE id = ?', [$e['id']]);
        }
        $pessoa = function (string $t) { return "COALESCE(NULLIF(lower($t.email), ''), lower($t.formando))"; };
        $st = $pdo->prepare("UPDATE inscricoes SET estado = 'desistiu' WHERE formacao_id = ? AND estado NOT IN ('desistiu', 'nao_concluido')
            AND id != (SELECT i2.id FROM inscricoes i2 WHERE i2.formacao_id = inscricoes.formacao_id AND i2.estado NOT IN ('desistiu', 'nao_concluido') AND " . $pessoa('i2') . ' = ' . $pessoa('inscricoes') . "
                ORDER BY i2.estado IN ('inscrito', 'em_formacao', 'concluido', 'certificado', 'encerrado') DESC, i2.id LIMIT 1)");
        $st->execute([$id]);
        $repetidas = $st->rowCount();
        $pdo->commit();
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }
    sessoes_formacao($id, true);
    return [count($origens), $movidas, $repetidas, count($sessoes)];
}

// Bloco "Juntar edições e ordenar as sessões" na ficha da edição
function juntar_edicoes_html(array $f): string
{
    if ($f['estado'] === 'cancelada') {
        return '';
    }
    $outras = edicoes_para_juntar($f);
    $varias = count(modulos_curso((int) $f['curso_id'])) > 1;
    if (!$outras && !$varias) {
        return '';
    }
    $lista = '';
    foreach ($outras as $e) {
        $lista .= '<li><label class="opcao-juntar"><input type="checkbox" name="edicoes[]" value="' . (int) $e['id'] . '"> <span><strong>' . h(horario($e)) . '</strong> · '
            . h(modalidade_e_local($e)) . ' · ' . (int) $e['n_inscritos'] . ' inscrição(ões), ' . (int) $e['n_confirmadas'] . ' confirmada(s)'
            . ' · <a href="' . h(url('formacao', ['id' => $e['id']])) . '">abrir</a></span></label></li>';
    }
    return '<div class="partilha espaco-topo" id="juntar"><h3 class="mt-0">' . ($outras ? 'Juntar edições e ordenar as sessões' : 'Ordenar as sessões') . '</h3>
      <form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="juntar">'
        . ($outras ? '<p>Há outras edições deste curso. Para ter todos os inscritos nesta edição, escolha as que quer juntar:</p><ul class="lista-juntar">' . $lista . '</ul>'
            . '<label class="opcao-juntar"><input type="checkbox" name="datas" value="1" checked> <span>Juntar também as datas das sessões dessas edições às desta</span></label>' : '')
        . ($varias ? '<label class="opcao-juntar"><input type="checkbox" name="ordenar" value="1" checked> <span>Pôr as sessões por ordem de data: a 1.ª data fica com a sessão 1 do curso, a 2.ª com a sessão 2, e assim por diante</span></label>' : '')
        . '<p class="espaco-topo-curto"><button class="botao" type="submit">' . ($outras ? 'Juntar e ordenar' : 'Pôr as sessões por ordem') . '</button></p>
        <p class="ajuda">' . ($outras ? 'As inscrições das edições escolhidas passam para esta. Se a mesma pessoa estiver inscrita em mais de uma, fica com uma só inscrição (a confirmada ou a mais antiga); as outras ficam canceladas, sem se perderem dados. As edições juntadas deixam de existir. ' : '')
        . 'Ninguém recebe emails com esta operação; depois confira as datas na lista de sessões acima.</p>
      </form></div>';
}

// Lê e valida os campos de uma sessão (formulário da edição)
function ler_sessao(): array
{
    $d = [
        'curso' => (int) ($_POST['curso'] ?? 0),
        'data' => texto('data', 10), 'hora_inicio' => texto('hora_inicio', 5), 'hora_fim' => texto('hora_fim', 5),
        'modalidade' => isset(MODALIDADES[$_POST['modalidade'] ?? '']) ? $_POST['modalidade'] : 'presencial',
        'local' => texto('local', 160), 'vagas' => max(1, min(500, (int) ($_POST['vagas'] ?? 12))),
        'estado' => isset(ESTADOS_FORMACAO[$_POST['estado'] ?? '']) ? $_POST['estado'] : 'aberta',
        'notas' => texto('notas', 2000, true), 'reuniao' => texto('reuniao', 300),
        'formador_id' => isset(formadores()[(int) ($_POST['formador_id'] ?? 0)]) ? (int) $_POST['formador_id'] : null,
    ];
    $erros = [];
    // Cada formação é uma edição do curso completo: a inscrição é sempre em todas as sessões
    $curso_id = curso($d['curso']) ? $d['curso'] : 0;
    if (!$curso_id) {
        $erros[] = 'Escolha o curso.';
    }
    // Cursos com vários temas: formato das sessões e uma linha por sessão (as linhas sem data são ignoradas)
    $d['sessoes'] = [];
    $d['formato'] = null;
    if ($curso_id && count(modulos_curso($curso_id)) > 1) {
        $mods = modulos_curso($curso_id);
        $ids = array_map('intval', array_column($mods, 'id'));
        $duracoes = isset($_POST['formato'][$curso_id]) ? duracoes_formato(formato_enviado($curso_id)) : duracoes_formato(formato_por_tema($mods));
        if (!$duracoes) {
            $erros[] = 'Indique o formato das sessões: as durações em horas, separadas por "+" (por exemplo, 2 + 1,5 + 2,5).';
            $duracoes = duracoes_formato(formato_por_tema($mods));
        }
        $d['formato'] = formato_de_duracoes($duracoes);
        foreach (array_values(array_filter((array) ($_POST['sessoes'][$curso_id] ?? []), 'is_array')) as $n => $l) {
            $data = trim((string) ($l['data'] ?? ''));
            if ($data === '') {
                continue;
            }
            $ini = trim((string) ($l['inicio'] ?? ''));
            $fim = trim((string) ($l['fim'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !strtotime($data) || !preg_match('/^\d{2}:\d{2}$/', $ini)) {
                $erros[] = 'Sessão da linha ' . ($n + 1) . ': indique a data e a hora de início.';
                continue;
            }
            if ($fim !== '' && (!preg_match('/^\d{2}:\d{2}$/', $fim) || $fim <= $ini)) {
                $erros[] = 'Sessão da linha ' . ($n + 1) . ': a hora de fim tem de ser posterior à de início.';
                continue;
            }
            $inicio = $data . ' ' . $ini . ':00';
            $horas = (float) ($l['horas'] ?? 0);
            $temas = temas_da_linha((string) ($l['temas'] ?? ''), $ids);
            $d['sessoes'][] = ['temas' => $temas, 'inicio' => $inicio,
                'fim' => $fim !== '' ? $data . ' ' . $fim . ':00' : ($horas > 0 ? date('Y-m-d H:i:s', strtotime($inicio) + (int) round($horas * 3600)) : null)];
        }
        if ($d['sessoes']) {
            usort($d['sessoes'], function ($a, $b) { return strcmp($a['inicio'], $b['inicio']); });
            // A data e o horário gerais passam a ser os da 1.ª sessão
            $d['data'] = substr($d['sessoes'][0]['inicio'], 0, 10);
            $d['hora_inicio'] = substr($d['sessoes'][0]['inicio'], 11, 5);
            $d['hora_fim'] = $d['sessoes'][0]['fim'] ? substr($d['sessoes'][0]['fim'], 11, 5) : '';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['data']) && preg_match('/^\d{2}:\d{2}$/', $d['hora_inicio'])) {
            // Sem datas nas linhas: uma sessão por semana a partir da data indicada, à mesma hora, com a duração do formato
            foreach (distribuir_temas($mods, $duracoes) as $k => $temas) {
                $inicio = date('Y-m-d', strtotime($d['data'] . ' +' . $k . ' week')) . ' ' . $d['hora_inicio'] . ':00';
                $d['sessoes'][] = ['temas' => $temas, 'inicio' => $inicio, 'fim' => date('Y-m-d H:i:s', strtotime($inicio) + (int) round($duracoes[$k] * 3600))];
            }
            $d['hora_fim'] = substr($d['sessoes'][0]['fim'], 11, 5);
        }
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
    return [$d, $curso_id, $erros];
}

// Cria (sem $id) ou atualiza uma formação (edição do curso completo) e as datas das suas sessões; devolve o id
function gravar_sessao(array $d, int $curso_id, int $id = 0): int
{
    $sessoes = $d['sessoes'] ?? [];
    $inicio = $sessoes ? $sessoes[0]['inicio'] : $d['data'] . ' ' . $d['hora_inicio'] . ':00';
    $fim = $sessoes ? $sessoes[count($sessoes) - 1]['fim'] : ($d['hora_fim'] !== '' ? $d['data'] . ' ' . $d['hora_fim'] . ':00' : null);
    $valores = [curso($curso_id)['nome'], $curso_id, null, $inicio, $fim, $d['modalidade'], $d['local'], $d['vagas'], $d['estado'], $d['notas'], $d['reuniao'] ?: null, $d['formador_id'] ?? null, $d['formato'] ?? null];
    if ($id) {
        executar('UPDATE formacoes SET titulo = ?, curso_id = ?, modulo_id = ?, inicio = ?, fim = ?, modalidade = ?, local = ?, vagas = ?, estado = ?, notas = ?, reuniao = ?, formador_id = ?, formato = ? WHERE id = ?', array_merge($valores, [$id]));
    } else {
        $id = executar('INSERT INTO formacoes (titulo, curso_id, modulo_id, inicio, fim, modalidade, local, vagas, estado, notas, reuniao, formador_id, formato, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($valores, [agora()]));
    }
    executar('DELETE FROM sessoes WHERE formacao_id = ?', [$id]);
    // Uma edição numa só sessão (formato de uma sessão) fica sem linhas, com a data e o horário da edição
    foreach (count($sessoes) > 1 ? $sessoes : [] as $n => $s) {
        $temas = $s['temas'] ?? (!empty($s['modulo_id']) ? [(int) $s['modulo_id']] : []);
        executar('INSERT INTO sessoes (formacao_id, ordem, modulo_id, inicio, fim, modulos) VALUES (?, ?, ?, ?, ?, ?)', [$id, $n + 1, $temas[0] ?? null, $s['inicio'], $s['fim'], $temas ? json_encode($temas) : null]);
    }
    sessoes_formacao($id, true);
    return $id;
}

// Lista das sessões do curso, uma linha por sessão do formato escolhido (temas, duração, data e horário). Há um bloco
// por curso com vários temas; só o do curso escolhido fica visível e ativo (assets/js/area.js troca-os).
// $atuais: linhas do curso escolhido (sessoes_do_formulario); $formato: formato do curso escolhido (formato_do_formulario).
function campos_sessoes_curso(int $escolhido, array $atuais = [], string $formato = ''): string
{
    $html = '';
    foreach (consulta('SELECT * FROM cursos WHERE ativo = 1 OR id = ? ORDER BY nome', [$escolhido]) as $c) {
        $mods = modulos_curso((int) $c['id']);
        if (count($mods) < 2) {
            continue;
        }
        $cid = (int) $c['id'];
        $ativo = $cid === $escolhido;
        $f_curso = $ativo && $formato !== '' ? $formato : formato_por_tema($mods);
        $duracoes = duracoes_formato($f_curso);
        $linhas = $ativo && $atuais ? $atuais : linhas_do_formato($mods, $duracoes);
        $linhas[] = ['temas' => [], 'horas' => null, 'inicio' => '', 'fim' => '', 'extra' => true];
        $numero = [];
        foreach ($mods as $k => $m) {
            $numero[(int) $m['id']] = $k + 1;
        }
        $presets = formatos_curso($mods);
        $opcoes = '';
        foreach ($presets as $valor => $rotulo) {
            $valor = (string) $valor; // "6" (uma só sessão) fica com chave numérica no PHP
            $opcoes .= '<option value="' . h($valor) . '"' . ($valor === $f_curso ? ' selected' : '') . '>' . h($rotulo) . '</option>';
        }
        $outro = !isset($presets[$f_curso]);
        $opcoes .= '<option value="outro"' . ($outro ? ' selected' : '') . '>Outro (indicar as durações)</option>';
        $temas = '';
        foreach ($mods as $k => $m) {
            $temas .= '<li>' . h($m['nome']) . ' <span class="texto-suave">(' . h(numero_horas(horas_modulo($m))) . ' h)</span></li>';
        }
        $corpo = '';
        foreach ($linhas as $n => $l) {
            $campo = 'sessoes[' . $cid . '][' . $n . ']';
            $t = $l['inicio'] ? strtotime($l['inicio']) : false;
            $nums = array_values(array_filter(array_map(function ($id) use ($numero) { return $numero[(int) $id] ?? 0; }, $l['temas'])));
            $nomes = implode(' + ', array_map(function ($x) use ($mods) { return $mods[$x - 1]['nome']; }, $nums));
            $horas = $l['horas'] ? numero_horas((float) $l['horas']) : '';
            $corpo .= '<tr' . (empty($l['extra']) ? ' data-linha-curso' : '') . ($horas !== '' ? ' data-horas="' . h(str_replace(',', '.', $horas)) . '"' : '') . '><td class="n">' . ($n + 1) . '</td>'
                . '<td><input type="text" name="' . $campo . '[temas]" value="' . h(implode(', ', $nums)) . '" inputmode="numeric" class="campo-temas" aria-label="Temas da sessão ' . ($n + 1) . ' (números)" placeholder="' . (empty($l['extra']) ? '' : 'extra') . '">'
                . ($nomes !== '' ? '<br><span class="texto-suave">' . h($nomes) . '</span>' : '') . '</td>'
                . '<td>' . ($horas !== '' ? h($horas) . ' h' : '') . '<input type="hidden" name="' . $campo . '[horas]" value="' . h(str_replace(',', '.', $horas)) . '"></td>'
                . '<td><input type="date" data-campo="data" name="' . $campo . '[data]" value="' . ($t ? date('Y-m-d', $t) : '') . '" aria-label="Data, sessão ' . ($n + 1) . '"></td>'
                . '<td><input type="time" data-campo="inicio" name="' . $campo . '[inicio]" value="' . ($t ? date('H:i', $t) : '') . '" aria-label="Início, sessão ' . ($n + 1) . '"></td>'
                . '<td><input type="time" data-campo="fim" name="' . $campo . '[fim]" value="' . (!empty($l['fim']) ? date('H:i', strtotime($l['fim'])) : '') . '" aria-label="Fim, sessão ' . ($n + 1) . '"></td></tr>';
        }
        $html .= '<fieldset class="campo-grupo sessoes-curso" data-curso="' . $cid . '"' . ($ativo ? '' : ' hidden disabled') . '>
          <legend>Sessões de ' . h($c['nome']) . '</legend>
          <div class="campos-2">
            <div class="campo"><label for="c-formato-' . $cid . '">Formato das sessões</label><select id="c-formato-' . $cid . '" name="formato[' . $cid . ']" data-formato>' . $opcoes . '</select></div>
            <div class="campo"><label for="c-formato-outro-' . $cid . '">Durações, em horas (com "Outro")</label><input id="c-formato-outro-' . $cid . '" name="formato_outro[' . $cid . ']" type="text" value="' . h($outro ? str_replace('.', ',', $f_curso) : '') . '" placeholder="Por exemplo, 2 + 1,5 + 2,5" maxlength="120"></div>
          </div>
          <p class="mb-0"><button class="botao botao-secundario botao-pequeno" type="submit" name="aplicar_formato" value="' . $cid . '" formnovalidate data-aplicar-formato>Aplicar o formato</button></p>
          <p class="ajuda">O formato define quantas sessões tem a edição e quanto dura cada uma (por exemplo, 3 sessões de 2 horas). Os temas do curso são distribuídos pelas sessões, pela ordem; em cada sessão pode mudar os temas, com os números da lista. Indique a data e a hora de início: sem hora de fim, conta a duração do formato. As linhas sem data são ignoradas; se não preencher nenhuma, as sessões ficam marcadas semanalmente a partir da data e da hora indicadas acima. A última linha serve para uma sessão extra.</p>
          <details><summary>Temas do curso (' . count($mods) . ', ' . h(numero_horas(array_sum(array_map('horas_modulo', $mods)))) . ' h)</summary><ol class="lista-datas">' . $temas . '</ol></details>
          <div class="tabela-rolante"><table class="tabela-sessoes"><thead><tr><th scope="col">#</th><th scope="col">Temas (n.º)</th><th scope="col">Duração</th><th scope="col">Data</th><th scope="col">Início</th><th scope="col">Fim</th></tr></thead><tbody>' . $corpo . '</tbody></table></div>
          <p class="mb-0"><button class="botao botao-secundario botao-pequeno" type="button" data-preencher-sessoes>Propor datas semanais a partir da data acima</button></p>
        </fieldset>';
    }
    return $html;
}

// Linhas de um formato: os temas distribuídos pelas sessões, sem datas
function linhas_do_formato(array $mods, array $duracoes): array
{
    $r = [];
    foreach (distribuir_temas($mods, $duracoes) as $k => $ids) {
        $r[] = ['temas' => $ids, 'horas' => $duracoes[$k], 'inicio' => '', 'fim' => ''];
    }
    return $r;
}

// Formato escolhido no formulário para o curso ($_POST), ou o gravado na edição, ou "uma sessão por tema"
function formato_enviado(int $curso): string
{
    $v = (string) ($_POST['formato'][$curso] ?? '');
    return $v === 'outro' ? trim((string) ($_POST['formato_outro'][$curso] ?? '')) : $v;
}

function formato_do_formulario(int $id): string
{
    $curso = (int) ($_POST['curso'] ?? 0);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['formato'][$curso])) {
        return duracoes_formato(formato_enviado($curso)) ? formato_de_duracoes(duracoes_formato(formato_enviado($curso))) : formato_enviado($curso);
    }
    $f = $id ? linha_bd('SELECT formato, curso_id FROM formacoes WHERE id = ?', [$id]) : null;
    if ($f && duracoes_formato((string) $f['formato'])) {
        return (string) $f['formato'];
    }
    // Edições anteriores: as durações das sessões gravadas (sem hora de fim, a duração do tema)
    if ($id && ($sessoes = sessoes_formacao($id))) {
        return formato_de_duracoes(array_map(function ($s) {
            $temas = temas_sessao($s);
            return !empty($s['fim']) ? max(0.25, (strtotime($s['fim']) - strtotime($s['inicio'])) / 3600) : ($temas ? array_sum(array_map('horas_modulo', $temas)) : 1.0);
        }, $sessoes));
    }
    return '';
}

// Linhas a mostrar no formulário: as do formato aplicado agora (com datas semanais a partir da data e da hora acima),
// as enviadas (depois de um erro) ou as gravadas. Sem nenhuma, valem as do formato (calculadas no formulário).
function sessoes_do_formulario(int $id): array
{
    $curso = (int) ($_POST['curso'] ?? 0);
    $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    $mods = modulos_curso($curso);
    $ids = array_map('intval', array_column($mods, 'id'));
    if ($post && (int) ($_POST['aplicar_formato'] ?? 0) === $curso && $curso) {
        $duracoes = duracoes_formato(formato_enviado($curso));
        $linhas = linhas_do_formato($mods, $duracoes ?: duracoes_formato(formato_por_tema($mods)));
        $data = trim((string) ($_POST['data'] ?? ''));
        $hora = trim((string) ($_POST['hora_inicio'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && preg_match('/^\d{2}:\d{2}$/', $hora)) {
            foreach ($linhas as $k => &$l) {
                $dia = date('Y-m-d', strtotime($data . ' +' . $k . ' week'));
                $l['inicio'] = $dia . ' ' . $hora . ':00';
                $l['fim'] = date('Y-m-d H:i:s', strtotime($l['inicio']) + (int) round($l['horas'] * 3600));
            }
            unset($l);
        }
        return $linhas;
    }
    if ($post && isset($_POST['sessoes'][$curso])) {
        $r = [];
        foreach (array_values(array_filter((array) $_POST['sessoes'][$curso], 'is_array')) as $l) {
            $data = trim((string) ($l['data'] ?? ''));
            $temas = temas_da_linha((string) ($l['temas'] ?? ''), $ids);
            if (!$temas && $data === '') {
                continue;
            }
            $ok = preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && preg_match('/^\d{2}:\d{2}$/', (string) ($l['inicio'] ?? ''));
            $r[] = ['temas' => $temas, 'horas' => (float) ($l['horas'] ?? 0) ?: null, 'inicio' => $ok ? $data . ' ' . $l['inicio'] . ':00' : '',
                'fim' => $ok && preg_match('/^\d{2}:\d{2}$/', (string) ($l['fim'] ?? '')) ? $data . ' ' . $l['fim'] . ':00' : ''];
        }
        return $r;
    }
    if ($id && ($s = sessoes_formacao($id))) {
        $duracoes = duracoes_formato(formato_do_formulario($id));
        return array_map(function ($x, $k) use ($duracoes) {
            return ['temas' => array_map('intval', array_column(temas_sessao($x), 'id')), 'horas' => $duracoes[$k] ?? null, 'inicio' => $x['inicio'], 'fim' => (string) $x['fim']];
        }, $s, array_keys($s));
    }
    return [];
}

// Ids dos módulos de uma linha a partir dos números escritos ("1, 2" ou "1 2"), pela ordem do curso e sem repetições
function temas_da_linha(string $texto, array $ids): array
{
    $r = [];
    foreach (preg_split('/[^0-9]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) as $n) {
        if (isset($ids[(int) $n - 1]) && !in_array($ids[(int) $n - 1], $r, true)) {
            $r[] = $ids[(int) $n - 1];
        }
    }
    return $r;
}

// Curso de uma formação. Não há formações de um só módulo ou sessão: cada formação é uma edição do curso completo
function seletor_curso(int $selecionado): string
{
    $opcoes = '<option value="">Escolha o curso</option>';
    foreach (consulta('SELECT * FROM cursos WHERE ativo = 1 OR id = ? ORDER BY nome', [$selecionado]) as $c) {
        $n = count(modulos_curso((int) $c['id']));
        $opcoes .= '<option value="' . (int) $c['id'] . '"' . ((int) $c['id'] === $selecionado ? ' selected' : '') . '>' . h($c['nome'] . ($n > 1 ? ' (' . $n . ' ' . unidade($c, true) . ')' : '')) . '</option>';
    }
    return '<div class="campo"><label for="c-curso">Curso *</label><select id="c-curso" name="curso" required>' . $opcoes . '</select></div>';
}

/* Cursos: configuração de cursos, tipologias, módulos e temas */

function admin_cursos(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'repor_catalogo') {
        if (empty($_POST['confirmo'])) {
            aviso('Para repor o catálogo, confirme que aceita apagar a agenda, as inscrições e os cursos.', 'erro');
        } else {
            $n = repor_catalogo(bd());
            aviso('Agenda e cursos limpos. Catálogo carregado com ' . $n . ' cursos.');
        }
        redirecionar('cursos');
    }
    $lista = consulta("SELECT c.*, (SELECT COUNT(*) FROM modulos m WHERE m.curso_id = c.id) AS n_modulos,
        (SELECT COUNT(*) FROM formacoes f WHERE f.curso_id = c.id AND f.inicio >= ? AND f.estado != 'cancelada') AS n_sessoes
        FROM cursos c ORDER BY c.ativo DESC, c.nome", [date('Y-m-d 00:00:00')]);
    $linhas = '';
    foreach ($lista as $c) {
        $linhas .= '<tr><td><a href="' . h(url('curso', ['id' => $c['id']])) . '">' . h($c['nome']) . '</a>' . ($c['percurso'] ? '<br><span class="texto-suave">' . h($c['percurso']) . '</span>' : '') . '</td><td>' . h($c['tipologia']) . '</td><td>' . (int) $c['n_modulos'] . '</td><td>' . (int) $c['n_sessoes'] . '</td><td>' . ($c['diagnostico'] ? 'Sim' : 'Não') . '</td><td>' . ($c['ativo'] ? etiqueta('Ativo', 'ok') : etiqueta('Inativo')) . '</td>'
            . '<td>' . ($c['ativo'] ? '<a href="' . h(link_ficha($c)) . '" target="_blank" rel="noopener">Ficha em PDF</a>' : '') . '</td></tr>';
    }
    $versao = (string) (catalogo()['versao'] ?? '');
    pagina_area('cursos', 'Cursos', ($versao !== '' && catalogo_pendente() ? '<div class="aviso aviso-info">Há um catálogo de cursos novo por carregar ("' . h((string) (catalogo()['comum']['percurso'] ?? $versao)) . '"). Não foi carregado automaticamente porque já existem inscrições. Use "Repor o catálogo", no fim desta página, quando quiser substituir a agenda e os cursos.</div>' : '') . '
      <p class="texto-suave">Cada curso tem tipologia, descrição, destinatários, objetivo, pré-requisitos, resultado esperado, avaliação, requisitos e módulos (ou sessões) com os temas e o caso prático. As sessões do calendário são de um curso completo ou de um módulo. O diagnóstico de utilização de IA pode ser ativado em cada curso. Cada curso tem uma ficha para exportar em PDF.</p>
      <div class="acoes" style="margin-top:0"><a class="botao" href="' . h(url('curso')) . '">Novo curso</a></div>
      ' . ($linhas ? '<div class="conteudo tabela-rolante espaco-topo-curto"><table><thead><tr><th>Curso</th><th>Tipologia</th><th>Módulos ou sessões</th><th>Sessões agendadas</th><th>Diagnóstico de IA</th><th>Estado</th><th>Ficha</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há cursos.</p>') . '
      <div class="zona-perigo espaco-topo">
        <h2 class="mt-0">Repor o catálogo</h2>
        <p>Apaga <strong>todas</strong> as sessões do calendário, as respetivas inscrições e todos os cursos, e carrega o catálogo do ficheiro de dados (' . h((string) (catalogo()['comum']['percurso'] ?? 'catálogo')) . ', ' . count(catalogo()['cursos'] ?? []) . ' cursos). Os clientes, os utilizadores e os pedidos do site não são afetados. Esta ação não pode ser desfeita: exporte antes as inscrições para Excel, se precisar delas.</p>
        <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="repor_catalogo">
          <label class="opcao consentimento"><input type="checkbox" name="confirmo" value="1" required> <span>Confirmo que quero apagar a agenda, as inscrições e os cursos e carregar o catálogo.</span></label>
          <div><button class="botao botao-perigo" type="submit">Limpar agenda e cursos e carregar o catálogo</button></div>
        </form>
      </div>', 'Formação');
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
            $valores = [$nome, texto('tipologia', 80), texto('descricao', 4000, true), texto('publico', 400), texto('duracao', 80), isset($_POST['diagnostico']) ? 1 : 0, isset($_POST['ativo']) ? 1 : 0,
                texto('percurso', 200), ($_POST['unidade'] ?? '') === 'Sessão' ? 'Sessão' : 'Módulo', texto('objetivo', 2000, true), texto('prerequisitos', 1000, true), texto('resultado', 2000, true),
                texto('estrutura', 2000, true), texto('avaliacao', 2000, true), texto('requisitos', 3000, true), texto('enquadramento', 3000, true), texto('investimento', 400, true)];
            if ($c) {
                executar('UPDATE cursos SET nome = ?, tipologia = ?, descricao = ?, publico = ?, duracao = ?, diagnostico = ?, ativo = ?, percurso = ?, unidade = ?, objetivo = ?, prerequisitos = ?, resultado = ?, estrutura = ?, avaliacao = ?, requisitos = ?, enquadramento = ?, investimento = ? WHERE id = ?', array_merge($valores, [$id]));
                // As sessões guardam o nome do curso: acompanham a mudança de nome
                executar('UPDATE formacoes SET titulo = ? WHERE curso_id = ?', [$nome, $id]);
                aviso('Curso atualizado.');
            } else {
                $id = executar('INSERT INTO cursos (nome, tipologia, descricao, publico, duracao, diagnostico, ativo, percurso, unidade, objetivo, prerequisitos, resultado, estrutura, avaliacao, requisitos, enquadramento, investimento, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($valores, [agora()]));
                aviso('Curso criado. Acrescente agora os módulos e os temas a abordar.');
            }
        } elseif ($acao === 'modulo' && $c) {
            $mid = (int) ($_POST['modulo_id'] ?? 0);
            $nome = texto('nome', 160);
            $horas = str_replace(',', '.', texto('horas', 6));
            $valores = [$nome, max(1, min(99, (int) ($_POST['ordem'] ?? 1))), texto('descricao', 2000, true), texto('temas', 4000, true), is_numeric($horas) && (float) $horas > 0 ? min(999, (float) $horas) : null, texto('caso_pratico', 1000, true)];
            if ($nome === '') {
                aviso('Indique o nome do módulo.', 'erro');
            } elseif ($mid) {
                executar('UPDATE modulos SET nome = ?, ordem = ?, descricao = ?, temas = ?, horas = ?, caso_pratico = ? WHERE id = ? AND curso_id = ?', array_merge($valores, [$mid, $id]));
                aviso('Módulo guardado.');
            } else {
                executar('INSERT INTO modulos (nome, ordem, descricao, temas, horas, caso_pratico, curso_id) VALUES (?, ?, ?, ?, ?, ?, ?)', array_merge($valores, [$id]));
                aviso('Módulo acrescentado.');
            }
        } elseif ($acao === 'convite' && $c) {
            $convite = ler_convite();
            if (is_string($convite)) {
                aviso(h($convite), 'erro');
            } else {
                aviso_envio(convite_curso($c, $convite['nome'], $convite['email'], $convite['mensagem']), 'Convite enviado para ' . h($convite['email']) . '.');
            }
        }
        redirecionar('curso', ['id' => $id]);
    }
    $v = ($c ?: []) + array_fill_keys(['nome', 'tipologia', 'descricao', 'publico', 'duracao', 'percurso', 'unidade', 'objetivo', 'prerequisitos', 'resultado', 'estrutura', 'avaliacao', 'requisitos', 'enquadramento', 'investimento'], '') + ['diagnostico' => 0, 'ativo' => 1];
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
        <h3 class="mb-0">Ficha do curso</h3>
        <p class="texto-suave mb-0">Estes campos aparecem na página pública do curso e na ficha em PDF. Os que ficarem vazios não são mostrados.</p>
        <div class="campos-2">
          ' . campo('percurso', 'Percurso (se o curso fizer parte de um)', (string) $v['percurso'], 'text', 'maxlength="200"') . '
          ' . campo_select('unidade', 'Cada parte do curso chama-se', ['Módulo' => 'Módulo', 'Sessão' => 'Sessão'], $v['unidade'] ?: 'Módulo') . '
        </div>
        ' . campo('objetivo', 'Objetivo', (string) $v['objetivo'], 'textarea', 'maxlength="2000" rows="2"') . '
        ' . campo('prerequisitos', 'Pré-requisitos', (string) $v['prerequisitos'], 'textarea', 'maxlength="1000" rows="2"') . '
        ' . campo('resultado', 'Resultado esperado', (string) $v['resultado'], 'textarea', 'maxlength="2000" rows="2"') . '
        <div class="campos-2">
          ' . campo('estrutura', 'Estrutura-tipo da sessão (uma linha por bloco: "Bloco: minutos")', (string) $v['estrutura'], 'textarea', 'maxlength="2000" rows="5"') . '
          ' . campo('avaliacao', 'Avaliação (uma linha por componente: "Componente: peso")', (string) $v['avaliacao'], 'textarea', 'maxlength="2000" rows="5"') . '
        </div>
        ' . campo('requisitos', 'Requisitos técnicos e condições (um por linha)', (string) $v['requisitos'], 'textarea', 'maxlength="3000" rows="4"') . '
        ' . campo('enquadramento', 'Enquadramento legal', (string) $v['enquadramento'], 'textarea', 'maxlength="3000" rows="3"') . '
        ' . campo('investimento', 'Investimento e condições (vazio: não aparece)', (string) $v['investimento'], 'textarea', 'maxlength="400" rows="2"') . '
        <label class="opcao consentimento"><input type="checkbox" name="diagnostico" value="1"' . ($v['diagnostico'] ? ' checked' : '') . '> <span>Incluir o diagnóstico de utilização de IA na inscrição (recomendado nos cursos de inteligência artificial)</span></label>
        <label class="opcao consentimento"><input type="checkbox" name="ativo" value="1"' . ($v['ativo'] ? ' checked' : '') . '> <span>Curso ativo (disponível para agendar edições)</span></label>
        <div><button class="botao" type="submit">' . ($c ? 'Guardar curso' : 'Criar curso') . '</button></div>
      </form>';
    if ($c) {
        $mods = modulos_curso($id);
        $u = unidade($c);
        $html .= '<h2 class="espaco-topo">' . ($u === 'Sessão' ? 'Sessões' : 'Módulos') . ', temas e casos práticos</h2>
          <p class="texto-suave">Os temas aparecem no calendário, no formulário de inscrição, na página pública e na ficha em PDF. Escreva um tema por linha. A ordem define a numeração.</p>';
        foreach ($mods as $n => $m) {
            $html .= form_modulo($m, $n + 1, $u);
        }
        $html .= '<h3 class="espaco-topo-curto">Acrescentar ' . strtolower($u) . '</h3>' . form_modulo(['id' => 0, 'ordem' => count($mods) + 1, 'nome' => '', 'descricao' => '', 'temas' => '', 'horas' => null, 'caso_pratico' => ''], 0, $u);
        if ($c['ativo']) {
            $html .= partilha_e_convite(endereco_site() . link_curso_publico($c), 'Página pública do curso',
                'Mostra a descrição, o programa, os temas e as próximas edições com inscrição. Pode colocá-la no site, nas redes sociais ou enviá-la a um candidato. A ficha em PDF tem o mesmo conteúdo, num documento para enviar ou imprimir.', link_ficha($c));
        }
        $html .= '<p class="espaco-topo">' . botao_apagar('curso', $id, 'Apagar este curso') . '</p>';
    }
    // Edições do curso no topo da página, antes da ficha
    pagina_area('cursos', $c ? $c['nome'] : 'Novo curso', ($c ? edicoes_do_curso_html((int) $c['id']) . '<h2 class="espaco-topo">Dados do curso</h2>' : '') . $html, 'Cursos');
}

// Edições do curso (as próximas primeiro, depois as que já decorreram): datas, formato, formador, inscritos e estado
function edicoes_do_curso_html(int $curso_id): string
{
    $hoje = date('Y-m-d 00:00:00');
    $lista = array_merge(
        consulta('SELECT * FROM formacoes WHERE curso_id = ? AND COALESCE(fim, inicio) >= ? ORDER BY inicio', [$curso_id, $hoje]),
        consulta('SELECT * FROM formacoes WHERE curso_id = ? AND COALESCE(fim, inicio) < ? ORDER BY inicio DESC', [$curso_id, $hoje])
    );
    $linhas = '';
    foreach ($lista as $f) {
        $n = count(sessoes_formacao((int) $f['id'])) ?: 1;
        $formato = texto_formato(duracoes_formato((string) $f['formato']));
        $formador = !empty($f['formador_id']) ? (string) (linha_bd('SELECT nome FROM utilizadores WHERE id = ?', [(int) $f['formador_id']])['nome'] ?? '') : '';
        $confirmadas = (int) (linha_bd("SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ? AND estado IN ('inscrito', 'em_formacao', 'concluido', 'certificado', 'encerrado')", [$f['id']])['n'] ?? 0);
        $decorrida = strtotime((string) ($f['fim'] ?: $f['inicio'])) < strtotime($hoje);
        $linhas .= '<tr><td data-valor="' . h($f['inicio']) . '"><a href="' . h(url('formacao', ['id' => $f['id']])) . '">' . h(horario($f)) . '</a>' . ($decorrida ? '<br>' . etiqueta('Decorrida') : '') . '</td>'
            . '<td>' . h($formato !== '' ? $formato : $n . ($n === 1 ? ' sessão' : ' sessões')) . '</td><td>' . h(modalidade_e_local($f)) . '</td><td>' . h($formador) . '</td>'
            . '<td data-valor="' . inscritos((int) $f['id']) . '">' . inscritos((int) $f['id']) . ' de ' . (int) $f['vagas'] . '<br><span class="texto-suave">' . $confirmadas . ' confirmada(s)</span></td>'
            . '<td>' . estado_etiqueta($f['estado'], ESTADOS_FORMACAO) . '</td></tr>';
    }
    return '<h2 id="edicoes">Edições agendadas (' . count($lista) . ')</h2>'
        . ($linhas ? '<div class="conteudo tabela-rolante"><table data-filtros="edicoes-curso"><thead><tr><th>Datas</th><th>Formato</th><th data-filtro="lista">Modalidade</th><th data-filtro="lista">Formador</th><th>Inscritos</th><th data-filtro="lista">Estado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>'
            : '<p class="texto-suave">Este curso ainda não tem edições agendadas.</p>')
        . '<p class="espaco-topo-curto"><a class="botao" href="' . h(url('formacao', ['curso' => $curso_id])) . '">Agendar nova edição deste curso</a></p>';
}

// Convite por email para a página de um curso, com as próximas edições abertas
function convite_curso(array $c, string $nome, string $email, string $mensagem, string $tipo = 'convite'): bool
{
    $sessoes = consulta("SELECT * FROM formacoes WHERE curso_id = ? AND inicio >= ? AND estado = 'aberta' ORDER BY inicio LIMIT 6", [$c['id'], date('Y-m-d 00:00:00')]);
    $datas = array_map(function ($f) { return horario($f) . ' · ' . nome_modulo($f) . ' · ' . (MODALIDADES[$f['modalidade']] ?? ''); }, $sessoes);
    $detalhe = array_filter([$c['tipologia'], $c['duracao']]);
    return enviar_convite($tipo, $nome, $email, $mensagem, ['formacao' => $c['nome'], 'curso' => $c['nome'], 'no_curso' => 'n' . curso_com_artigo((string) $c['nome']), 'data' => ''],
        '<p style="margin:0 0 4px;"><strong>' . h($c['nome']) . '</strong>' . ($detalhe ? ' · ' . h(implode(' · ', $detalhe)) : '') . '</p>'
        . ($c['descricao'] ? '<p style="margin:0 0 10px;color:#687280;">' . h($c['descricao']) . '</p>' : '')
        . ($datas ? '<p style="margin:0 0 4px;"><strong>Próximas edições</strong></p><ul style="margin:0;padding-left:18px;">' . implode('', array_map(function ($d) { return '<li>' . h($d) . '</li>'; }, $datas)) . '</ul>' : ''),
        $c['nome'] . ($detalhe ? ' · ' . implode(' · ', $detalhe) : '') . ($datas ? "\n\nPróximas edições:\n- " . implode("\n- ", $datas) : ''),
        endereco_site() . link_curso_publico($c), 'Ver o curso e inscrever-me');
}

function form_modulo(array $m, int $numero, string $unidade = 'Módulo'): string
{
    $novo = !$m['id'];
    $p = 'm' . (int) $m['id'] . '-';
    return '<div class="cartao-modulo">
      <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="modulo"><input type="hidden" name="modulo_id" value="' . (int) $m['id'] . '">
        ' . ($novo ? '' : '<span class="sobretitulo mb-0">' . h($unidade) . ' ' . $numero . '</span>') . '
        <div class="campos-modulo">
          ' . campo('nome', 'Nome ou tema *', (string) $m['nome'], 'text', 'required maxlength="160"', $p . 'nome') . '
          ' . campo('ordem', 'Ordem', (string) $m['ordem'], 'number', 'min="1" max="99"', $p . 'ordem') . '
          ' . campo('horas', 'Horas', $m['horas'] ? rtrim(rtrim(number_format((float) $m['horas'], 1, ',', ''), '0'), ',') : '', 'text', 'maxlength="6" inputmode="decimal"', $p . 'horas') . '
        </div>
        ' . campo('descricao', 'Descrição', (string) $m['descricao'], 'textarea', 'maxlength="2000" rows="2"', $p . 'descricao') . '
        ' . campo('temas', 'Temas a abordar (um por linha)', (string) $m['temas'], 'textarea', 'maxlength="4000"', $p . 'temas') . '
        ' . campo('caso_pratico', 'Caso prático', (string) ($m['caso_pratico'] ?? ''), 'textarea', 'maxlength="1000" rows="2"', $p . 'caso') . '
        <div class="acoes-modulo"><button class="botao' . ($novo ? '' : ' botao-secundario') . '" type="submit">' . ($novo ? 'Acrescentar' : 'Guardar') . ' ' . strtolower($unidade) . '</button></div>
      </form>
      ' . ($novo ? '' : '<p class="apagar-modulo">' . botao_apagar('modulo', (int) $m['id'], 'Apagar ' . strtolower($unidade), 'botao-texto') . '</p>') . '
    </div>';
}

/* Inscrições */

// Lista de inscrições, com filtros e exportação para Excel
function admin_inscricoes(): void
{
    if (($_GET['vista'] ?? '') !== 'todas' && !array_intersect_key($_GET, array_flip(['formacao', 'cliente', 'estado', 'origem', 'exportar']))) {
        admin_formandos();
        return;
    }
    $origens = ['site' => 'Site', 'por_associar' => 'Site, por associar a cliente', 'area' => 'Área reservada'];
    $estados = ESTADOS_INSCRICAO;
    $filtros = ['formacao' => (int) ($_GET['formacao'] ?? 0), 'cliente' => (int) ($_GET['cliente'] ?? 0), 'estado' => isset($estados[$_GET['estado'] ?? '']) ? $_GET['estado'] : '', 'origem' => isset($origens[$_GET['origem'] ?? '']) ? $_GET['origem'] : ''];
    $sql = 'SELECT i.*, ' . SQL_CLIENTE . ', COALESCE(f.titulo, (SELECT c2.nome FROM cursos c2 WHERE c2.id = i.curso_id)) AS titulo, f.inicio FROM inscricoes i LEFT JOIN clientes c ON c.id = i.cliente_id LEFT JOIN formacoes f ON f.id = i.formacao_id WHERE 1 = 1';
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
            $linhas[] = [$i['titulo'], data_hora($i['inicio']), $i['cliente'], TIPOS_CLIENTE[$i['cliente_tipo']] ?? '', ORIGENS[$i['origem']] ?? $i['origem'], $i['contacto_nome'], $i['contacto_email'], $i['contacto_telefone'], $i['formando'], $i['email'], $i['funcao'], ESTADOS_INSCRICAO[$i['estado']] ?? $i['estado'], $i['nivel'], $i['pontuacao'], $i['responsavel'], $i['ferramentas'], $i['expectativas'], data_hora($i['criado'])];
        }
        exportar_csv('gadgetmetodo-inscricoes', ['Formação', 'Data da formação', 'Cliente', 'Tipo de cliente', 'Origem', 'Contacto (site)', 'Email do contacto', 'Telefone do contacto', 'Formando', 'Email', 'Função', 'Estado', 'Nível de IA', 'Pontuação', 'Uso responsável (0-9)', 'Ferramentas usadas', 'Expectativas', 'Data da inscrição'], $linhas);
    }
    $formacoes = [];
    foreach (consulta('SELECT id, titulo, inicio FROM formacoes ORDER BY inicio DESC') as $f) {
        $formacoes[$f['id']] = data_hora($f['inicio'], false) . ' · ' . $f['titulo'];
    }
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    $linhas = '';
    foreach ($lista as $i) {
        $linhas .= '<tr><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . cliente_html($i) . etiqueta_origem($i) . '</td><td>' . h($i['titulo']) . '<br><span class="texto-suave">' . h(data_hora($i['inicio'])) . '</span></td><td>' . nivel_etiqueta($i['nivel']) . '</td><td>' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</td><td>' . h(data_hora($i['criado'])) . '</td></tr>';
    }
    pagina_area('inscricoes', 'Inscrições', submenu_inscricoes('todas') . '
      <form method="get" class="filtros">
        <input type="hidden" name="p" value="inscricoes"><input type="hidden" name="vista" value="todas">
        ' . campo_select('formacao', 'Formação', $formacoes, $filtros['formacao'], 'Todas') . campo_select('cliente', 'Cliente', $clientes, $filtros['cliente'], 'Todos') . campo_select('estado', 'Estado', $estados, $filtros['estado'], 'Todos') . campo_select('origem', 'Origem', $origens, $filtros['origem'], 'Todas') . '
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
        $id = executar('INSERT INTO clientes (tipo, nome, nif, contacto, email, telefone, notas, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [tipo_cliente_post() ?: 'empresa', $nome, texto('nif', 20), texto('contacto', 120), texto('email', 160), texto('telefone', 30), texto('notas', 2000, true), agora()]);
        aviso('Cliente criado. Crie agora o acesso de um utilizador deste cliente.');
        redirecionar('utilizadores', ['cliente' => $id]);
    }
    $lista = consulta('SELECT c.*, (SELECT COUNT(*) FROM utilizadores u WHERE u.cliente_id = c.id) AS n_utilizadores, (SELECT COUNT(*) FROM inscricoes i WHERE i.cliente_id = c.id AND i.estado != \'cancelada\') AS n_inscricoes FROM clientes c ORDER BY c.nome');
    $linhas = '';
    foreach ($lista as $c) {
        $linhas .= '<tr><td><a href="' . h(url('cliente', ['id' => $c['id']])) . '">' . h($c['nome']) . '</a></td><td>' . h(TIPOS_CLIENTE[$c['tipo']] ?? '') . '</td><td>' . h($c['nif']) . '</td><td>' . h($c['contacto']) . '<br><span class="texto-suave">' . h($c['email']) . '</span></td><td>' . (int) $c['n_utilizadores'] . '</td><td>' . (int) $c['n_inscricoes'] . '</td></tr>';
    }
    pagina_area('clientes', 'Clientes', '
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table data-filtros="clientes"><thead><tr><th>Cliente</th><th data-filtro="lista">Tipo</th><th>NIF</th><th>Contacto</th><th>Utilizadores</th><th>Inscrições</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há clientes.</p>') . '
      <h2 class="espaco-topo">Novo cliente</h2>
      ' . form_cliente([]), 'Gestão');
}

function form_cliente(array $c): string
{
    return '<form method="post" class="formulario">' . campo_csrf() . '<div class="campos-2">
        ' . campo_select('tipo_cliente', 'Tipo de cliente *', TIPOS_CLIENTE, (string) ($c['tipo'] ?? 'empresa')) . '
        ' . campo('nome', 'Nome da empresa ou da pessoa *', (string) ($c['nome'] ?? ''), 'text', 'required maxlength="160"') . '
        ' . campo('nif', 'NIF', (string) ($c['nif'] ?? ''), 'text', 'maxlength="20"') . '
        ' . campo('contacto', 'Pessoa de contacto (nas empresas)', (string) ($c['contacto'] ?? ''), 'text', 'maxlength="120"') . '
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
            executar('UPDATE clientes SET tipo = ?, nome = ?, nif = ?, contacto = ?, email = ?, telefone = ?, notas = ? WHERE id = ?',
                [tipo_cliente_post() ?: $c['tipo'], $nome, texto('nif', 20), texto('contacto', 120), texto('email', 160), texto('telefone', 30), texto('notas', 2000, true), $id]);
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
      <p><a class="botao botao-secundario" href="' . h(url('utilizadores', ['cliente' => $id])) . '">Criar utilizador</a> <a class="botao botao-secundario" href="' . h(url('inscricoes', ['cliente' => $id])) . '">Ver inscrições</a></p>
      <p class="espaco-topo">' . botao_apagar('cliente', $id, 'Apagar este cliente') . '</p>', 'Cliente');
}

/* Utilizadores */

function admin_utilizadores(): void
{
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $nome = texto('nome', 120);
        $email = texto('email', 160);
        $papel = papel_enviado();
        [$cliente_id, $erro_cliente] = cliente_do_utilizador($papel, $clientes);
        $senha = (string) ($_POST['senha'] ?? '');
        $erro = '';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Indique o nome e um email válido.';
        } elseif ($erro_cliente !== '') {
            $erro = $erro_cliente;
        } elseif ($senha !== '' && strlen($senha) < 10) {
            $erro = 'A palavra-passe tem de ter pelo menos 10 caracteres.';
        } elseif (($existente = linha_bd('SELECT id, papel FROM utilizadores WHERE email = ?', [$email])) && ($existente['papel'] !== 'formando' || $papel === 'formando')) {
            $erro = 'Já existe um utilizador com este email.';
        }
        if ($erro) {
            aviso(h($erro), 'erro');
            redirecionar('utilizadores');
        }
        $gerada = $senha === '';
        $senha = $gerada ? gerar_senha() : $senha;
        if ($existente) {
            // O email já tinha acesso de formando: a conta passa ao novo perfil e continua a ver as suas formações e presenças
            executar('UPDATE utilizadores SET nome = ?, senha = ?, papel = ?, cliente_id = ?, ativo = 1, token_senha = NULL, token_validade = NULL WHERE id = ?',
                [$nome, password_hash($senha, PASSWORD_DEFAULT), $papel, $cliente_id, $existente['id']]);
            aviso('Este email já tinha acesso de formando: a conta passou ao perfil ' . h(PAPEIS[$papel]) . ' e mantém as formações e as presenças.', 'info');
        } else {
            executar('INSERT INTO utilizadores (email, nome, senha, papel, cliente_id, criado, ccp) VALUES (?, ?, ?, ?, ?, ?, ?)', [$email, $nome, password_hash($senha, PASSWORD_DEFAULT), $papel, $cliente_id, agora(), in_array($papel, PAPEIS_GESTAO, true) ? (texto('ccp', 60) ?: null) : null]);
        }
        if ($papel === 'formando' && $cliente_id) {
            aviso_formando_associado($email, $cliente_id);
        }
        aviso('Utilizador criado. Dados de acesso para comunicar a ' . h($nome) . ':<br>Endereço: <strong>' . h(endereco_site()) . '/clientes/</strong><br>Email: <strong>' . h($email) . '</strong><br>Palavra-passe: <strong class="senha">' . h($senha) . '</strong><br>' . ($gerada ? 'Esta palavra-passe não volta a ser mostrada.' : ''));
        if (!empty($_POST['enviar_acesso'])) {
            aviso_envio(enviar_acesso(['nome' => $nome, 'email' => $email, 'papel' => $papel, 'cliente_id' => $cliente_id], $senha), 'Dados de acesso enviados por email para ' . h($email) . '.');
        }
        redirecionar('utilizadores');
    }
    $lista = consulta('SELECT u.*, c.nome AS cliente FROM utilizadores u LEFT JOIN clientes c ON c.id = u.cliente_id ORDER BY u.papel, c.nome, u.nome');
    // Emails com inscrições confirmadas próprias: os utilizadores Cliente com um destes emails também são formandos
    $formandos = array_flip(array_column(consulta('SELECT DISTINCT LOWER(TRIM(i.email)) AS e FROM inscricoes i LEFT JOIN formacoes f ON f.id = i.formacao_id'
        . " WHERE i.estado IN ('inscrito', 'em_formacao', 'concluido', 'certificado', 'encerrado') AND COALESCE(f.estado, '') != 'cancelada' AND TRIM(COALESCE(i.email, '')) != ''"), 'e'));
    $linhas = '';
    foreach ($lista as $u) {
        $perfil = PAPEIS[$u['papel']] . ($u['papel'] === 'cliente' && isset($formandos[strtolower(trim((string) $u['email']))]) ? ' + Formando' : '');
        $linhas .= '<tr><td><a href="' . h(url('utilizador', ['id' => $u['id']])) . '">' . h($u['nome']) . '</a></td><td>' . h($u['email']) . '</td><td>' . h($perfil) . '</td><td>' . h($u['cliente']) . '</td><td>' . ($u['ativo'] ? etiqueta('Ativo', 'ok') : etiqueta('Inativo')) . '</td><td data-valor="' . h((string) $u['ultimo_acesso']) . '">' . h(data_hora($u['ultimo_acesso'])) . '</td></tr>';
    }
    $pre = (int) ($_GET['cliente'] ?? 0);
    pagina_area('utilizadores', 'Utilizadores', '
      <div class="conteudo tabela-rolante"><table data-filtros="utilizadores"><thead><tr><th>Nome</th><th>Email</th><th data-filtro="lista">Perfil</th><th data-filtro="lista">Cliente</th><th data-filtro="lista">Estado</th><th>Último acesso</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
      <h2 class="espaco-topo" id="novo">Novo utilizador</h2>
      <p class="texto-suave">Perfil Gadgetmetodo: vê e gere tudo. Perfil Formador: o mesmo acesso que o perfil Gadgetmetodo (vê e gere tudo); pode ser escolhido como formador de uma edição, e é ele quem assina os certificados dessa edição, com o n.º do CCP indicado aqui. Perfil Cliente: vê as formações e as inscrições do seu cliente. Perfil Formando: vê as suas formações confirmadas (pelo email das inscrições) e regista a presença em cada sessão; é criado automaticamente quando uma inscrição com email é confirmada. Pode ser associado à empresa cliente que o inscreve: as suas inscrições ainda sem cliente passam a ser dessa empresa, que as vê na área reservada (as já associadas a outro cliente ficam como estão).</p>
      <p class="texto-suave">Cliente que também é formando (por exemplo, a sócia-gerente inscrita numa formação da empresa): não precisa de outra conta. Se houver inscrições confirmadas com o email do utilizador Cliente, ele passa a ter também "As minhas formações", onde regista só a sua própria presença, com as mesmas regras do Formando, e aparece nesta lista como "Cliente + Formando". Ao confirmar uma inscrição com o email de um utilizador que já existe (Cliente ou Gadgetmetodo), não é criada outra conta e o perfil mantém-se. Os emails comparam-se sem distinguir maiúsculas de minúsculas.</p>
      <form method="post" class="formulario">' . campo_csrf() . '<div class="campos-2">
        ' . campo('nome', 'Nome *', '', 'text', 'required maxlength="120"') . '
        ' . campo('email', 'Email (é o nome de utilizador) *', '', 'email', 'required maxlength="160" autocomplete="off"') . '
        ' . campo_select('papel', 'Perfil *', PAPEIS, 'cliente') . '
        ' . campo_select('cliente_id', 'Cliente (obrigatório no perfil Cliente; no Formando, a empresa a que pertence)', $clientes, $pre, 'Escolha o cliente') . '
        ' . campo('senha', 'Palavra-passe', '', 'text', 'minlength="10" autocomplete="new-password" placeholder="Vazio: é gerada automaticamente"') . '
        ' . campo('ccp', 'N.º do CCP (perfis Gadgetmetodo e Formador)', '', 'text', 'maxlength="60" placeholder="Por exemplo, F625978/2014 (IEFP)"') . '
      </div>
      <label class="opcao consentimento"><input type="checkbox" name="enviar_acesso" value="1"> <span>Enviar os dados de acesso por email ao utilizador, com a palavra-passe (texto em <a href="' . h(url('comunicacao', ['aberto' => 'acesso'])) . '#email-acesso">Comunicação</a>)</span></label>
      <div><button class="botao" type="submit">Criar utilizador</button></div></form>', 'Configuração');
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
            if (!empty($_POST['enviar_acesso'])) {
                aviso_envio(enviar_acesso($u, $senha), 'Nova palavra-passe enviada por email para ' . h($u['email']) . '.');
            }
            redirecionar('utilizador', ['id' => $id]);
        }
        if (($_POST['acao'] ?? '') === 'ligacao') {
            aviso_envio(enviar_ligacao_senha($u), 'Ligação para definir a palavra-passe (válida 7 dias) enviada para ' . h($u['email']) . '.');
            redirecionar('utilizador', ['id' => $id]);
        }
        $nome = texto('nome', 120);
        $email = texto('email', 160);
        $papel = papel_enviado();
        [$cliente_id, $erro_cliente] = cliente_do_utilizador($papel, $clientes);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $erro = '';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Indique o nome e um email válido.';
        } elseif ($erro_cliente !== '') {
            $erro = $erro_cliente;
        } elseif ($eu && (!in_array($papel, PAPEIS_GESTAO, true) || !$ativo)) {
            $erro = 'Não pode retirar o seu próprio acesso de administração.';
        } elseif (linha_bd('SELECT id FROM utilizadores WHERE email = ? AND id != ?', [$email, $id])) {
            $erro = 'Já existe outro utilizador com este email.';
        }
        if ($erro) {
            aviso(h($erro), 'erro');
        } else {
            executar('UPDATE utilizadores SET nome = ?, email = ?, papel = ?, cliente_id = ?, ativo = ?, ccp = ? WHERE id = ?', [$nome, $email, $papel, $cliente_id, $ativo, in_array($papel, PAPEIS_GESTAO, true) ? (texto('ccp', 60) ?: null) : null, $id]);
            aviso('Utilizador atualizado.');
            if ($papel === 'formando' && $cliente_id) {
                aviso_formando_associado($email, $cliente_id);
            }
        }
        redirecionar('utilizador', ['id' => $id]);
    }
    pagina_area('utilizadores', $u['nome'], '
      <form method="post" class="formulario">' . campo_csrf() . '<div class="campos-2">
        ' . campo('nome', 'Nome *', $u['nome'], 'text', 'required maxlength="120"') . '
        ' . campo('email', 'Email *', $u['email'], 'email', 'required maxlength="160"') . '
        ' . campo_select('papel', 'Perfil *', PAPEIS, $u['papel']) . '
        ' . campo_select('cliente_id', 'Cliente (obrigatório no perfil Cliente; no Formando, a empresa a que pertence)', $clientes, $u['cliente_id'], $u['papel'] === 'formando' ? 'Sem empresa' : 'Escolha o cliente') . '
        ' . campo('ccp', 'N.º do CCP (perfis Gadgetmetodo e Formador)', (string) ($u['ccp'] ?? ''), 'text', 'maxlength="60" placeholder="Por exemplo, F625978/2014 (IEFP)"') . '
      </div>
      <label class="opcao consentimento"><input type="checkbox" name="ativo" value="1"' . ($u['ativo'] ? ' checked' : '') . '> Acesso ativo</label>
      <div><button class="botao" type="submit">Guardar alterações</button></div></form>
      <h2 class="espaco-topo">Palavra-passe</h2>
      <p class="texto-suave">Gera uma palavra-passe nova, mostrada uma única vez, para comunicar ao utilizador.</p>
      <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="nova_senha">
        <label class="opcao consentimento"><input type="checkbox" name="enviar_acesso" value="1"> <span>Enviar a nova palavra-passe por email ao utilizador</span></label>
        <div><button class="botao botao-secundario" type="submit">Gerar nova palavra-passe</button></div></form>
      <h2 class="espaco-topo">Ligação para definir a palavra-passe</h2>
      <p class="texto-suave">Envia ao utilizador uma ligação, válida 7 dias, para ser ele a escolher a palavra-passe (é o que recebem os formandos quando a inscrição é confirmada). A ligação anterior deixa de valer.'
        . ($u['token_senha'] !== null ? ' Estado: ' . (strtotime((string) $u['token_validade']) >= time() ? 'ligação enviada, válida até ' . h(data_hora($u['token_validade'])) : 'a última ligação expirou em ' . h(data_hora($u['token_validade'])) . ' sem ser usada') . '.' : '') . '</p>
      <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="ligacao">
        <div><button class="botao botao-secundario" type="submit">Enviar ligação por email</button></div></form>
      <p class="texto-suave espaco-topo">Criado em ' . h(data_hora($u['criado'])) . '. Último acesso: ' . h(data_hora($u['ultimo_acesso']) ?: 'nunca') . '.</p>'
      . ($eu ? '' : '<p class="espaco-topo">' . botao_apagar('utilizador', $id, 'Apagar este utilizador') . '</p>'), 'Configuração');
}

// Perfil escolhido no formulário de utilizador (Cliente quando o valor não é conhecido)
function papel_enviado(): string
{
    $papel = (string) ($_POST['papel'] ?? '');
    return isset(PAPEIS[$papel]) ? $papel : 'cliente';
}

// Email com os dados de acesso à área reservada (endereço, email e palavra-passe), só quando a administração o pede
function enviar_acesso(array $u, string $senha): bool
{
    $endereco = endereco_site() . '/clientes/';
    $cliente = $u['papel'] === 'cliente' ? (string) (linha_bd('SELECT nome FROM clientes WHERE id = ?', [(int) $u['cliente_id']])['nome'] ?? '') : PAPEIS[$u['papel']];
    $tabela = '';
    foreach (['Endereço' => $endereco, 'Email' => $u['email'], 'Palavra-passe' => $senha] as $k => $v) {
        $tabela .= '<tr><td style="padding:7px 12px 7px 0;border-bottom:1px solid #E7DFD4;color:#687280;width:130px;">' . h($k) . '</td><td style="padding:7px 0;border-bottom:1px solid #E7DFD4;font-weight:700;">' . h($v) . '</td></tr>';
    }
    return enviar_mensagem('acesso', (string) $u['email'], ['nome' => nome_curto((string) $u['nome']), 'nome_completo' => $u['nome'], 'email' => $u['email'], 'cliente' => $cliente], [
        '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 4px;">' . $tabela . '</table>'
        . '<p style="margin:22px 0 0;"><a href="' . h($endereco) . '" style="display:inline-block;padding:13px 26px;background:#0A0F26;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:999px;">Entrar na área reservada</a></p>',
        'Endereço: ' . $endereco . "\nEmail: " . $u['email'] . "\nPalavra-passe: " . $senha,
    ]);
}

/* Pedidos do site e aberturas do catálogo */

function admin_pedidos(): void
{
    $registos = ler_registos(cfg());
    if (!empty($_GET['exportar'])) {
        $linhas = [];
        foreach ($registos as $p) {
            $ab = $p['aberturas'];
            $linhas[] = [$p['ref'], data_hora($p['criado']), $p['tipo'], $p['nome'], $p['empresa'] ?: 'Particular', $p['email'], $p['telemovel'], $p['servicos'], count($ab), $ab ? data_hora($ab[0]['aberto']) : '', $ab ? data_hora($ab[count($ab) - 1]['aberto']) : ''];
        }
        exportar_csv('gadgetmetodo-pedidos', ['Referência', 'Data do pedido', 'Tipo', 'Nome', 'Empresa', 'Email', 'Telemóvel', 'Serviços', 'Aberturas do catálogo', 'Primeira abertura', 'Última abertura'], $linhas);
    }
    $linhas = '';
    foreach ($registos as $p) {
        $reais = array_filter($p['aberturas'], function ($a) { return !$a['automatica']; });
        $ab = $p['aberturas'] ? implode('<br>', array_map(function ($a) { return h(data_hora($a['aberto'])) . ($a['automatica'] ? ' ' . etiqueta('automática') : ''); }, $p['aberturas'])) : etiqueta('Não aberto');
        $linhas .= '<tr><td>' . h($p['ref']) . '<br><span class="texto-suave">' . h(data_hora($p['criado'])) . '</span></td><td>' . h($p['nome']) . '<br><span class="texto-suave">' . h($p['empresa'] ?: 'Particular') . '</span></td><td><a href="mailto:' . h($p['email']) . '">' . h($p['email']) . '</a><br>' . h($p['telemovel']) . '</td><td>' . h($p['servicos']) . '</td><td>' . ($reais ? etiqueta('Aberto', 'ok') . '<br>' : '') . $ab . '</td><td>' . botao_apagar('pedido', (string) $p['ref'], 'Apagar', 'botao botao-secundario botao-pequeno botao-apagar') . '</td></tr>';
    }
    pagina_area('pedidos', 'Pedidos do site', '
      <p class="texto-suave">Pedidos de orçamento e de informações recebidos pelo formulário do site e aberturas do catálogo enviado na confirmação. Registos dos últimos 12 meses.</p>
      <p><a class="botao botao-secundario" href="' . h(url('pedidos', ['exportar' => 1])) . '">Exportar para Excel</a></p>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Pedido</th><th>Nome e empresa</th><th>Contactos</th><th>Serviços</th><th>Catálogo</th><th><span class="visualmente-oculto">Ações</span></th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há pedidos registados.</p>'), 'Formulário do site');
}

/* Partilha de ligações e convites a candidatos */

function ler_convite()
{
    $c = ['nome' => texto('convite_nome', 120), 'email' => texto('convite_email', 160), 'mensagem' => texto('convite_mensagem', 1500, true)];
    if ($c['nome'] === '' || !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Indique o nome e um email válido do candidato.';
    }
    return $c;
}

function partilha_e_convite(string $link, string $titulo, string $ajuda, string $pdf = ''): string
{
    return '<div class="partilha espaco-topo">
      <h2 class="mt-0">' . h($titulo) . '</h2>
      <p class="texto-suave">' . h($ajuda) . '</p>
      <div class="partilha-ligacao"><label class="visualmente-oculto" for="ligacao-publica">' . h($titulo) . '</label><input id="ligacao-publica" type="text" readonly value="' . h($link) . '"><button class="botao botao-secundario" type="button" data-copiar="' . h($link) . '">Copiar ligação</button><a class="botao botao-secundario" href="' . h($link) . '" target="_blank" rel="noopener">Abrir</a>' . ($pdf ? '<a class="botao" href="' . h($pdf) . '" target="_blank" rel="noopener">Exportar PDF</a>' : '') . '</div>
      <p class="estado-copia" role="status" aria-live="polite"></p>
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
