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
        $linhas .= '<tr><td>' . h(data_hora($i['criado'])) . '</td><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . cliente_html($i) . etiqueta_origem($i) . '</td><td>' . h($i['titulo']) . ', ' . h(data_hora($i['inicio'], false)) . '</td><td>' . nivel_etiqueta($i['nivel']) . '</td></tr>';
    }
    pagina_area('painel', 'Painel', '
      <div class="numeros">
        <div><strong>' . $n("SELECT COUNT(*) AS n FROM formacoes WHERE inicio >= '" . date('Y-m-d') . "' AND estado != 'cancelada'") . '</strong>formações agendadas</div>
        <div><strong>' . $n("SELECT COUNT(*) AS n FROM inscricoes WHERE estado != 'cancelada'") . '</strong>inscrições ativas</div>
        <div><strong>' . $n('SELECT COUNT(*) AS n FROM clientes') . '</strong>clientes</div>
        <div><a href="' . h(url('inscricoes', ['origem' => 'por_associar'])) . '"><strong>' . $n("SELECT COUNT(*) AS n FROM inscricoes WHERE cliente_id IS NULL AND estado != 'cancelada'") . '</strong>inscrições do site por associar</a></div>
        <div><a href="' . h(url('inscricoes', ['estado' => 'preinscricao'])) . '"><strong>' . $n("SELECT COUNT(*) AS n FROM preinscricoes WHERE estado = 'nova'") . '</strong>pré-inscrições por tratar</a></div>
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
      <a class="botao botao-secundario" href="#lista">Ver em lista</a>
      <a class="botao botao-secundario" href="' . h(url('calendario', $passadas ? [] : ['ver' => 'passadas'])) . '">' . ($passadas ? 'Ver próximas' : 'Ver formações passadas') . '</a>
      <a class="botao botao-secundario" href="/formacoes/" target="_blank" rel="noopener">Ver página pública</a></div>
      ' . calendario_mensal(mes_pedido()) . '
      <h2 class="espaco-topo" id="lista">' . ($passadas ? 'Edições passadas' : 'Próximas edições') . '</h2>
      ' . calendario($formacoes, function ($f) {
          return '<a href="' . h(url('formacao', ['id' => $f['id']])) . '">Gerir e ver inscritos</a>'
              . ($f['estado'] !== 'cancelada' ? '<a href="' . h(url('inscrever', ['formacao' => $f['id']])) . '">Inscrever formando</a>' : '')
              . (!empty($f['reuniao']) ? '<a href="' . h($f['reuniao']) . '" target="_blank" rel="noopener">Abrir reunião</a>' : '');
      }), 'Formação');
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
    if ($post && in_array($acao, ['pre_convidar_edicao', 'pre_convidar_todas_edicao'], true) && $f) {
        tratar_preinscritos_edicao($f);
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
        if (!$erros) {
            $id = gravar_sessao($d, $curso_id, $f ? $id : 0);
            $associadas = $f ? 0 : associar_preinscricoes($id);
            aviso($f ? 'Formação atualizada.' : 'Formação criada e visível para os clientes e na área de formação do site.'
                . ($associadas ? ' ' . $associadas . ' pré-inscrição(ões) do curso associada(s) a esta edição: veja "Pré-inscritos", mais abaixo.' : ''));
            redirecionar('formacao', ['id' => $id]);
        }
        $f = ['id' => $id ?: null, 'titulo' => '', 'curso_id' => $curso_id, 'modulo_id' => $f['modulo_id'] ?? null, 'inicio' => $d['data'] . ' ' . $d['hora_inicio'], 'fim' => $d['hora_fim'] ? $d['data'] . ' ' . $d['hora_fim'] : '', 'modalidade' => $d['modalidade'], 'local' => $d['local'], 'vagas' => $d['vagas'], 'estado' => $d['estado'], 'notas' => $d['notas'], 'reuniao' => $d['reuniao']];
    }
    $v = $f ?: ['curso_id' => (int) ($_GET['curso'] ?? 0), 'modulo_id' => null, 'inicio' => '', 'fim' => '', 'modalidade' => 'presencial', 'local' => '', 'vagas' => 12, 'estado' => 'aberta', 'notas' => '', 'reuniao' => ''];
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
    $n_mods = count(modulos_curso((int) ($v['curso_id'] ?? 0)));
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
        </div>
        ' . campos_sessoes_curso($selecionado, sessoes_do_formulario((int) $id)) . '
        <div class="campo"><label for="c-reuniao">Ligação da reunião online (Google Meet)</label><input id="c-reuniao" name="reuniao" type="url" value="' . h((string) $v['reuniao']) . '" maxlength="300" placeholder="https://meet.google.com/abc-defg-hij" aria-describedby="ajuda-reuniao">
          <p class="ajuda" id="ajuda-reuniao">Só é enviada às inscrições confirmadas (no email de confirmação e na área reservada); nunca aparece na página pública. Para a criar: ' . $atalhos . '</p></div>
        ' . campo('notas', 'Notas internas', (string) $v['notas'], 'textarea', 'maxlength="2000"') . '
        <div><button class="botao" type="submit">' . ($id ? 'Guardar alterações' : 'Criar formação') . '</button></div>
      </form>';
    $inscritos = '';
    if ($id) {
        $confirmadas = (int) (linha_bd("SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ? AND estado = 'confirmada'", [$id])['n'] ?? 0);
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
        $inscritos .= preinscritos_edicao_html(linha_bd('SELECT * FROM formacoes WHERE id = ?', [$id]));
        $inscritos .= juntar_edicoes_html(linha_bd('SELECT * FROM formacoes WHERE id = ?', [$id]));
        $inscritos .= '<p class="espaco-topo">' . botao_apagar('formacao', $id, 'Apagar esta edição') . '</p>';
    }
    pagina_area('calendario', $id ? 'Editar formação' : 'Nova formação', $form . $inscritos, 'Calendário');
}

/* Juntar edições do mesmo curso e pôr as sessões por ordem */

// Outras edições do mesmo curso que podem ser juntadas a esta (não canceladas, dos últimos 6 meses em diante)
function edicoes_para_juntar(array $f): array
{
    return consulta("SELECT f.*, (SELECT COUNT(*) FROM inscricoes i WHERE i.formacao_id = f.id AND i.estado != 'cancelada') AS n_inscritos,
            (SELECT COUNT(*) FROM inscricoes i WHERE i.formacao_id = f.id AND i.estado = 'confirmada') AS n_confirmadas
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
            executar('UPDATE preinscricoes SET formacao_id = ? WHERE formacao_id = ?', [$id, $e['id']]);
            executar('DELETE FROM sessoes WHERE formacao_id = ?', [$e['id']]);
            executar('DELETE FROM formacoes WHERE id = ?', [$e['id']]);
        }
        $pessoa = function (string $t) { return "COALESCE(NULLIF(lower($t.email), ''), lower($t.formando))"; };
        $st = $pdo->prepare("UPDATE inscricoes SET estado = 'cancelada' WHERE formacao_id = ? AND estado != 'cancelada'
            AND id != (SELECT i2.id FROM inscricoes i2 WHERE i2.formacao_id = inscricoes.formacao_id AND i2.estado != 'cancelada' AND " . $pessoa('i2') . ' = ' . $pessoa('inscricoes') . "
                ORDER BY i2.estado = 'confirmada' DESC, i2.id LIMIT 1)");
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

// Lê e valida os campos de uma sessão (formulário da sessão ou "Nova sessão" na pré-inscrição)
function ler_sessao(): array
{
    $d = [
        'curso' => (int) ($_POST['curso'] ?? 0),
        'data' => texto('data', 10), 'hora_inicio' => texto('hora_inicio', 5), 'hora_fim' => texto('hora_fim', 5),
        'modalidade' => isset(MODALIDADES[$_POST['modalidade'] ?? '']) ? $_POST['modalidade'] : 'presencial',
        'local' => texto('local', 160), 'vagas' => max(1, min(500, (int) ($_POST['vagas'] ?? 12))),
        'estado' => isset(ESTADOS_FORMACAO[$_POST['estado'] ?? '']) ? $_POST['estado'] : 'aberta',
        'notas' => texto('notas', 2000, true), 'reuniao' => texto('reuniao', 300),
    ];
    $erros = [];
    // Cada formação é uma edição do curso completo: a inscrição é sempre em todas as sessões
    $curso_id = curso($d['curso']) ? $d['curso'] : 0;
    if (!$curso_id) {
        $erros[] = 'Escolha o curso.';
    }
    // Cursos com várias sessões: uma linha por sessão (as linhas sem data são ignoradas)
    $d['sessoes'] = [];
    if ($curso_id && count(modulos_curso($curso_id)) > 1) {
        $ids = array_map('intval', array_column(modulos_curso($curso_id), 'id'));
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
            $mod = (int) ($l['modulo'] ?? 0);
            $d['sessoes'][] = ['modulo_id' => in_array($mod, $ids, true) ? $mod : null, 'inicio' => $data . ' ' . $ini . ':00', 'fim' => $fim !== '' ? $data . ' ' . $fim . ':00' : null];
        }
        if ($d['sessoes']) {
            usort($d['sessoes'], function ($a, $b) { return strcmp($a['inicio'], $b['inicio']); });
            // A data e o horário gerais passam a ser os da 1.ª sessão
            $d['data'] = substr($d['sessoes'][0]['inicio'], 0, 10);
            $d['hora_inicio'] = substr($d['sessoes'][0]['inicio'], 11, 5);
            $d['hora_fim'] = $d['sessoes'][0]['fim'] ? substr($d['sessoes'][0]['fim'], 11, 5) : '';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['data']) && preg_match('/^\d{2}:\d{2}$/', $d['hora_inicio'])) {
            // Sem datas nas linhas: uma sessão por semana a partir da data indicada, à mesma hora
            foreach (modulos_curso($curso_id) as $n => $m) {
                $dia = date('Y-m-d', strtotime($d['data'] . ' +' . $n . ' week'));
                $d['sessoes'][] = ['modulo_id' => (int) $m['id'], 'inicio' => $dia . ' ' . $d['hora_inicio'] . ':00', 'fim' => $d['hora_fim'] !== '' && $d['hora_fim'] > $d['hora_inicio'] ? $dia . ' ' . $d['hora_fim'] . ':00' : null];
            }
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
    $valores = [curso($curso_id)['nome'], $curso_id, null, $inicio, $fim, $d['modalidade'], $d['local'], $d['vagas'], $d['estado'], $d['notas'], $d['reuniao'] ?: null];
    if ($id) {
        executar('UPDATE formacoes SET titulo = ?, curso_id = ?, modulo_id = ?, inicio = ?, fim = ?, modalidade = ?, local = ?, vagas = ?, estado = ?, notas = ?, reuniao = ? WHERE id = ?', array_merge($valores, [$id]));
    } else {
        $id = executar('INSERT INTO formacoes (titulo, curso_id, modulo_id, inicio, fim, modalidade, local, vagas, estado, notas, reuniao, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($valores, [agora()]));
    }
    executar('DELETE FROM sessoes WHERE formacao_id = ?', [$id]);
    foreach (count($sessoes) > 1 ? $sessoes : [] as $n => $s) {
        executar('INSERT INTO sessoes (formacao_id, ordem, modulo_id, inicio, fim) VALUES (?, ?, ?, ?, ?)', [$id, $n + 1, $s['modulo_id'], $s['inicio'], $s['fim']]);
    }
    sessoes_formacao($id, true);
    return $id;
}

// Lista das sessões do curso, para escolher a data, o horário e a sessão do curso de cada linha.
// Há um bloco por curso com várias sessões; só o do curso escolhido fica visível e ativo (assets/js/area.js troca-os).
function campos_sessoes_curso(int $escolhido, array $atuais = []): string
{
    $html = '';
    foreach (consulta('SELECT * FROM cursos WHERE ativo = 1 OR id = ? ORDER BY nome', [$escolhido]) as $c) {
        $mods = modulos_curso((int) $c['id']);
        if (count($mods) < 2) {
            continue;
        }
        $ativo = (int) $c['id'] === $escolhido;
        // As linhas gravadas (ou enviadas) e, a seguir, as sessões do curso que ainda não têm linha, pela ordem do curso
        $linhas = $ativo ? $atuais : [];
        $com_linha = array_map('intval', array_column($linhas, 'modulo_id'));
        foreach ($mods as $m) {
            if (!in_array((int) $m['id'], $com_linha, true)) {
                $linhas[] = ['modulo_id' => (int) $m['id'], 'inicio' => '', 'fim' => ''];
            }
        }
        $linhas = array_merge($linhas, array_fill(0, 2, ['modulo_id' => 0, 'inicio' => '', 'fim' => '', 'extra' => true]));
        $u = strtolower(unidade($c));
        $corpo = '';
        foreach ($linhas as $n => $l) {
            $opcoes = '<option value="">' . h(ucfirst($u)) . ' do curso</option>' . implode('', array_map(function ($m) use ($l) {
                return '<option value="' . (int) $m['id'] . '"' . ((int) $l['modulo_id'] === (int) $m['id'] ? ' selected' : '') . '>' . h(rotulo_modulo($m)) . '</option>';
            }, $mods));
            $t = $l['inicio'] ? strtotime($l['inicio']) : false;
            $campo = 'sessoes[' . (int) $c['id'] . '][' . $n . ']';
            $corpo .= '<tr' . (empty($l['extra']) ? ' data-linha-curso' : '') . '><td class="n">' . ($n + 1) . '</td>'
                . '<td><select name="' . $campo . '[modulo]" aria-label="' . h(ucfirst($u)) . ' do curso, linha ' . ($n + 1) . '">' . $opcoes . '</select></td>'
                . '<td><input type="date" name="' . $campo . '[data]" value="' . ($t ? date('Y-m-d', $t) : '') . '" aria-label="Data, linha ' . ($n + 1) . '"></td>'
                . '<td><input type="time" name="' . $campo . '[inicio]" value="' . ($t ? date('H:i', $t) : '') . '" aria-label="Início, linha ' . ($n + 1) . '"></td>'
                . '<td><input type="time" name="' . $campo . '[fim]" value="' . (!empty($l['fim']) ? date('H:i', strtotime($l['fim'])) : '') . '" aria-label="Fim, linha ' . ($n + 1) . '"></td></tr>';
        }
        $html .= '<fieldset class="campo-grupo sessoes-curso" data-curso="' . (int) $c['id'] . '"' . ($ativo ? '' : ' hidden disabled') . '>
          <legend>Datas das ' . count($mods) . ' ' . ($u === 'sessão' ? 'sessões' : 'módulos') . ' de ' . h($c['nome']) . '</legend>
          <p class="ajuda">Uma linha por ' . h($u) . ', pela ordem definida no curso: indique a data e o horário de cada uma e, se precisar, troque a ' . h($u) . ' da linha. As duas últimas linhas servem para ' . ($u === 'sessão' ? 'sessões' : 'módulos') . ' extra. As linhas sem data são ignoradas; se não preencher nenhuma, ficam marcadas semanalmente a partir da data e do horário indicados acima.</p>
          <div class="tabela-rolante"><table class="tabela-sessoes"><thead><tr><th scope="col">#</th><th scope="col">' . h(ucfirst($u)) . ' do curso</th><th scope="col">Data</th><th scope="col">Início</th><th scope="col">Fim</th></tr></thead><tbody>' . $corpo . '</tbody></table></div>
          <p class="mb-0"><button class="botao botao-secundario botao-pequeno" type="button" data-preencher-sessoes>Propor datas semanais a partir da data acima</button></p>
        </fieldset>';
    }
    return $html;
}

// Linhas a mostrar no formulário: as enviadas (depois de um erro) ou as gravadas. Numa formação antiga numa só data,
// as linhas ficam vazias e são propostas semanalmente a partir dessa data.
function sessoes_do_formulario(int $id): array
{
    $curso = (int) ($_POST['curso'] ?? 0);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['sessoes'][$curso])) {
        $r = [];
        foreach (array_values(array_filter((array) $_POST['sessoes'][$curso], 'is_array')) as $l) {
            $data = trim((string) ($l['data'] ?? ''));
            if (empty($l['modulo']) && $data === '') {
                continue;
            }
            $ok = preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && preg_match('/^\d{2}:\d{2}$/', (string) ($l['inicio'] ?? ''));
            $r[] = ['modulo_id' => (int) ($l['modulo'] ?? 0), 'inicio' => $ok ? $data . ' ' . $l['inicio'] . ':00' : '', 'fim' => $ok && preg_match('/^\d{2}:\d{2}$/', (string) ($l['fim'] ?? '')) ? $data . ' ' . $l['fim'] . ':00' : ''];
        }
        return $r;
    }
    if ($id && ($s = sessoes_formacao($id))) {
        return array_map(function ($x) { return ['modulo_id' => (int) $x['modulo_id'], 'inicio' => $x['inicio'], 'fim' => (string) $x['fim']]; }, $s);
    }
    return [];
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
            aviso('Para repor o catálogo, confirme que aceita apagar a agenda, as inscrições, as pré-inscrições e os cursos.', 'erro');
        } else {
            $n = repor_catalogo(bd());
            aviso('Agenda e cursos limpos. Catálogo carregado com ' . $n . ' cursos.');
        }
        redirecionar('cursos');
    }
    $lista = consulta("SELECT c.*, (SELECT COUNT(*) FROM modulos m WHERE m.curso_id = c.id) AS n_modulos,
        (SELECT COUNT(*) FROM formacoes f WHERE f.curso_id = c.id AND f.inicio >= ? AND f.estado != 'cancelada') AS n_sessoes,
        (SELECT COUNT(*) FROM preinscricoes p WHERE p.curso_id = c.id AND p.estado = 'nova') AS n_pre
        FROM cursos c ORDER BY c.ativo DESC, c.nome", [date('Y-m-d 00:00:00')]);
    $linhas = '';
    foreach ($lista as $c) {
        $linhas .= '<tr><td><a href="' . h(url('curso', ['id' => $c['id']])) . '">' . h($c['nome']) . '</a>' . ($c['percurso'] ? '<br><span class="texto-suave">' . h($c['percurso']) . '</span>' : '') . '</td><td>' . h($c['tipologia']) . '</td><td>' . (int) $c['n_modulos'] . '</td><td>' . (int) $c['n_sessoes'] . '</td><td>' . ($c['n_pre'] ? '<a href="' . h(url('curso', ['id' => $c['id']])) . '#preinscricoes">' . (int) $c['n_pre'] . ' por tratar</a>' : '0') . '</td><td>' . ($c['diagnostico'] ? 'Sim' : 'Não') . '</td><td>' . ($c['ativo'] ? etiqueta('Ativo', 'ok') : etiqueta('Inativo')) . '</td>'
            . '<td>' . ($c['ativo'] ? '<a href="' . h(link_ficha($c)) . '" target="_blank" rel="noopener">Ficha em PDF</a>' : '') . '</td></tr>';
    }
    $versao = (string) (catalogo()['versao'] ?? '');
    pagina_area('cursos', 'Cursos', ($versao !== '' && catalogo_pendente() ? '<div class="aviso aviso-info">Há um catálogo de cursos novo por carregar ("' . h((string) (catalogo()['comum']['percurso'] ?? $versao)) . '"). Não foi carregado automaticamente porque já existem inscrições ou pré-inscrições. Use "Repor o catálogo", no fim desta página, quando quiser substituir a agenda e os cursos.</div>' : '') . '
      <p class="texto-suave">Cada curso tem tipologia, descrição, destinatários, objetivo, pré-requisitos, resultado esperado, avaliação, requisitos e módulos (ou sessões) com os temas e o caso prático. As sessões do calendário são de um curso completo ou de um módulo. O diagnóstico de utilização de IA pode ser ativado em cada curso. Cada curso tem uma ficha para exportar em PDF.</p>
      <div class="acoes" style="margin-top:0"><a class="botao" href="' . h(url('curso')) . '">Novo curso</a></div>
      ' . ($linhas ? '<div class="conteudo tabela-rolante espaco-topo-curto"><table><thead><tr><th>Curso</th><th>Tipologia</th><th>Módulos ou sessões</th><th>Sessões agendadas</th><th>Pré-inscrições</th><th>Diagnóstico de IA</th><th>Estado</th><th>Ficha</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há cursos.</p>') . '
      <div class="zona-perigo espaco-topo">
        <h2 class="mt-0">Repor o catálogo</h2>
        <p>Apaga <strong>todas</strong> as sessões do calendário, as respetivas inscrições, as pré-inscrições e todos os cursos, e carrega o catálogo do ficheiro de dados (' . h((string) (catalogo()['comum']['percurso'] ?? 'catálogo')) . ', ' . count(catalogo()['cursos'] ?? []) . ' cursos). Os clientes, os utilizadores e os pedidos do site não são afetados. Esta ação não pode ser desfeita: exporte antes as inscrições para Excel, se precisar delas.</p>
        <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="repor_catalogo">
          <label class="opcao consentimento"><input type="checkbox" name="confirmo" value="1" required> <span>Confirmo que quero apagar a agenda, as inscrições, as pré-inscrições e os cursos e carregar o catálogo.</span></label>
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
        } elseif (in_array($acao, ['pre_convidar', 'pre_convidar_todas'], true) && $c) {
            // Convite às pré-inscrições: só quando o curso tem sessões abertas com vagas
            $lista = $acao === 'pre_convidar'
                ? consulta('SELECT * FROM preinscricoes WHERE id = ? AND curso_id = ?', [(int) ($_POST['pre_id'] ?? 0), $id])
                : consulta("SELECT * FROM preinscricoes WHERE curso_id = ? AND estado = 'nova' ORDER BY criado", [$id]);
            if (!sessoes_com_vagas($id)) {
                aviso('Agende primeiro uma edição do curso com inscrições abertas: o convite leva o candidato às datas do curso.', 'erro');
            } else {
                $enviados = 0;
                foreach ($lista as $pre) {
                    if (convite_curso($c, (string) $pre['nome'], (string) $pre['email'], '', 'convite_preinscricao')) {
                        executar("UPDATE preinscricoes SET estado = 'convidada' WHERE id = ?", [$pre['id']]);
                        $enviados++;
                    }
                }
                aviso_envio($enviados > 0, count($lista) === 1 ? 'Convite enviado para ' . h((string) $lista[0]['email']) . '.' : $enviados . ' de ' . count($lista) . ' convites enviados.');
            }
        } elseif ($acao === 'pre_estado' && $c) {
            $estado = (string) ($_POST['estado'] ?? '');
            if (isset(estados_preinscricao_manuais()[$estado])) {
                executar('UPDATE preinscricoes SET estado = ? WHERE id = ? AND curso_id = ?', [$estado, (int) ($_POST['pre_id'] ?? 0), $id]);
                aviso('Estado da pré-inscrição atualizado.');
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
        $sessoes = consulta('SELECT * FROM formacoes WHERE curso_id = ? AND inicio >= ? ORDER BY inicio', [$id, date('Y-m-d 00:00:00')]);
        $html .= '<h2 class="espaco-topo">Sessões agendadas</h2>'
            . ($sessoes ? calendario($sessoes, function ($f) { return '<a href="' . h(url('formacao', ['id' => $f['id']])) . '">Gerir</a>'; }) : '<p class="texto-suave">Este curso não tem sessões agendadas.</p>')
            . '<p class="espaco-topo-curto"><a class="botao" href="' . h(url('formacao', ['curso' => $id])) . '">Agendar edição deste curso</a></p>';
        $pre = (int) (linha_bd("SELECT COUNT(*) AS n FROM preinscricoes WHERE curso_id = ? AND estado IN ('nova', 'convidada')", [$id])['n'] ?? 0);
        $html .= '<h2 class="espaco-topo" id="preinscricoes">Pré-inscrições</h2><p class="texto-suave">' . ($pre ? $pre . ' pré-inscrição(ões) por converter neste curso. ' : 'Não há pré-inscrições por converter neste curso. ')
            . 'As pré-inscrições geram-se em <a href="' . h(url('inscricoes', ['estado' => 'preinscricao'])) . '">Inscrições, Pré-inscrições</a>; quando agenda uma edição do curso no calendário, ficam associadas a essa edição, onde pode inscrever os candidatos ou enviar-lhes o convite.</p>';
        $html .= '<p class="espaco-topo">' . botao_apagar('curso', $id, 'Apagar este curso') . '</p>';
    }
    pagina_area('cursos', $c ? $c['nome'] : 'Novo curso', $html, 'Cursos');
}

/* Pré-inscrição: ficha, estado e inscrição numa edição já agendada ou numa edição nova (definir datas) */

// Estados que se escolhem à mão; "Inscrita" só resulta de inscrever o candidato numa sessão
function estados_preinscricao_manuais(string $atual = ''): array
{
    return $atual === 'inscrita' ? ESTADOS_PREINSCRICAO : array_diff_key(ESTADOS_PREINSCRICAO, ['inscrita' => true]);
}

function admin_preinscricao(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $p = linha_bd('SELECT * FROM preinscricoes WHERE id = ?', [$id]);
    $c = $p ? curso((int) $p['curso_id']) : null;
    if (!$p || !$c) {
        pagina_erro('Pré-inscrição não encontrada', 'A pré-inscrição pedida não existe ou já foi apagada.');
    }
    $respostas = json_decode((string) $p['respostas'], true);
    $respostas = is_array($respostas) ? $respostas : [];
    $erros = [];
    $acao = (string) ($_POST['acao'] ?? '');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if ($acao === 'estado' && isset(estados_preinscricao_manuais()[$_POST['estado'] ?? ''])) {
            executar('UPDATE preinscricoes SET estado = ? WHERE id = ?', [$_POST['estado'], $id]);
            aviso('Estado da pré-inscrição atualizado.');
            redirecionar('preinscricao', ['id' => $id]);
        }
        if ($acao === 'curso') {
            $novo = (int) ($_POST['curso_associado'] ?? 0);
            if (in_array($novo, array_map('intval', array_column(cursos_do_percurso($c) ?: [$c], 'id')), true)) {
                executar('UPDATE preinscricoes SET curso_id = ?, formacao_id = ? WHERE id = ?', [$novo, proxima_edicao_curso($novo)['id'] ?? null, $id]);
                aviso('Curso associado: ' . h((string) curso($novo)['nome']) . '.');
            }
            redirecionar('preinscricao', ['id' => $id]);
        }
        if ($acao === 'inscrever') {
            $formando = texto('formando', 120) ?: (string) $p['nome'];
            $email = texto('email_formando', 160);
            $estado = ($_POST['estado_inscricao'] ?? '') === 'pendente' ? 'pendente' : 'confirmada';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erros[] = 'O email do formando não é válido.';
            }
            $f = null;
            $nova = null;
            if (($_POST['sessao'] ?? '') === 'nova') {
                [$d, $curso_id, $e] = ler_sessao();
                if (!$e && strtotime($d['data']) < strtotime('today')) {
                    $e[] = 'A data da 1.ª sessão já passou.';
                }
                $erros = array_merge($erros, $e);
                $nova = [$d, $curso_id];
            } elseif (!($f = linha_bd("SELECT * FROM formacoes WHERE id = ? AND estado != 'cancelada'", [(int) ($_POST['sessao'] ?? 0)]))) {
                $erros[] = 'Escolha a edição ou "Nova edição".';
            }
            if (!$erros) {
                if ($nova) {
                    $f = linha_bd('SELECT * FROM formacoes WHERE id = ?', [gravar_sessao(...$nova)]);
                    associar_preinscricoes((int) $f['id']);
                }
                $r = gravar_inscricao($f, ['cliente_id' => null, 'utilizador_id' => utilizador()['id'], 'origem' => 'site', 'tipo_cliente' => $p['tipo_cliente'], 'empresa' => $p['empresa'], 'nif' => null,
                    'contacto_nome' => $p['nome'], 'contacto_email' => $p['email'], 'contacto_telefone' => $p['telefone'],
                    'formando' => $formando, 'email' => $email, 'funcao' => '', 'ferramentas' => $p['ferramentas'] ? explode(', ', (string) $p['ferramentas']) : [], 'expectativas' => ''], $respostas, true);
                if ($r['erro']) {
                    $erros[] = $r['erro'];
                } else {
                    executar('UPDATE inscricoes SET estado = ? WHERE id = ?', [$estado, $r['id']]);
                    executar("UPDATE preinscricoes SET estado = 'inscrita', inscricao_id = ? WHERE id = ?", [$r['id'], $id]);
                    $i = linha_bd(SQL_INSCRICAO, [$r['id']]);
                    $avisado = $estado === 'confirmada' && !empty($_POST['avisar']) && ($contacto = contacto_inscricao($i)) && avisar_estado_inscricao($i, 'confirmada', $contacto);
                    aviso('Inscrição de ' . h($formando) . ' registada: ' . h(horario($f)) . ($nova ? ' (formação nova criada no calendário)' : '') . '.'
                        . ($avisado ? ' Confirmação enviada por email para ' . h((string) $p['email']) . '.' : ''));
                    if ($estado === 'confirmada' && ($acesso = acesso_apos_confirmacao((int) $r['id']))) {
                        aviso(...$acesso);
                    }
                    redirecionar('inscricao', ['id' => $r['id']]);
                }
            }
        }
    }

    // Sessões futuras dos cursos do mesmo percurso (ou do próprio curso), para escolher; ou uma sessão nova
    $cursos = array_column(cursos_do_percurso($c) ?: [$c], 'nome', 'id');
    $sessoes = consulta('SELECT * FROM formacoes WHERE curso_id IN (' . implode(',', array_map('intval', array_keys($cursos))) . ") AND inicio >= ? AND estado != 'cancelada' ORDER BY inicio", [date('Y-m-d 00:00:00')]);
    $escolha = (string) ($_POST['sessao'] ?? ($_GET['sessao'] ?? ($p['formacao_id'] ?? '')));
    if ($escolha === '' || ($escolha !== 'nova' && !in_array($escolha, array_map('strval', array_column($sessoes, 'id')), true))) {
        $propria = array_values(array_filter($sessoes, function ($f) use ($c) { return (int) $f['curso_id'] === (int) $c['id']; }));
        $escolha = $propria ? (string) $propria[0]['id'] : 'nova';
    }
    $opcoes = '';
    foreach ($sessoes as $f) {
        $livres = (int) $f['vagas'] - inscritos((int) $f['id']);
        $opcoes .= '<label class="opcao"><input type="radio" name="sessao" value="' . (int) $f['id'] . '"' . ($escolha === (string) $f['id'] ? ' checked' : '') . '> <span><strong>' . h($f['titulo']) . '</strong> · ' . h(nome_modulo($f)) . '<br>'
            . h(horario($f)) . ' · ' . h(MODALIDADES[$f['modalidade']] ?? '') . ' · ' . ($livres > 0 ? $livres . ' vaga(s) livre(s)' : 'sem vagas livres') . ($f['estado'] !== 'aberta' ? ' · ' . h(ESTADOS_FORMACAO[$f['estado']]) : '') . '</span></label>';
    }
    $opcoes .= '<label class="opcao"><input type="radio" name="sessao" value="nova"' . ($escolha === 'nova' ? ' checked' : '') . '> <span><strong>Nova edição</strong>: definir agora as datas e o horário das sessões (fica também no calendário e no site)</span></label>';
    $v = $_POST + ['curso' => $c['id'], 'data' => '', 'hora_inicio' => '09:30', 'hora_fim' => '10:30', 'modalidade' => $p['modalidade'] ?: 'presencial', 'local' => '', 'vagas' => '12', 'reuniao' => '', 'formando' => $p['nome'], 'email_formando' => $p['email'], 'estado_inscricao' => 'confirmada'];
    $a = $respostas ? avaliar_diagnostico($respostas) : null;
    $rec = $respostas ? recomendacao_de(['respostas' => json_encode($respostas), 'curso_id' => $c['id']]) : '';
    $ind = $respostas ? curso_indicado($respostas, $c) : null;
    $origem = $p['curso_origem'] ? curso((int) $p['curso_origem']) : null;
    // Curso associado: à partida, o aconselhado pelo diagnóstico; pode trocar-se por outro curso do percurso
    $lista_cursos = [];
    foreach (cursos_do_percurso($c) ?: [$c] as $x) {
        $lista_cursos[$x['id']] = $x['nome'] . ($ind && (int) $ind['curso']['id'] === (int) $x['id'] ? ' (aconselhado)' : '');
    }
    $form_curso = count($lista_cursos) > 1 && $p['estado'] !== 'inscrita' ? '<form method="post" class="formulario linha-form">' . campo_csrf() . '<input type="hidden" name="acao" value="curso">
          ' . campo_select('curso_associado', 'Curso associado', $lista_cursos, (string) $c['id']) . '
          <div></div><div><button class="botao botao-secundario" type="submit">Associar este curso</button></div></form>' : '';
    $dados = array_filter([
        'Curso associado' => '<a href="' . h(url('curso', ['id' => $c['id']])) . '">' . h($c['nome']) . '</a>'
            . ($ind ? ((int) $ind['curso']['id'] === (int) $c['id'] ? ' ' . etiqueta('Aconselhado pelo diagnóstico', 'ok') : ' ' . etiqueta('Diferente do aconselhado', 'info')) : ''),
        'Curso aconselhado' => $ind ? '<strong>' . h($ind['curso']['nome']) . '</strong><br><span class="texto-suave">' . h(ucfirst($ind['motivo'])) . '</span>' : '',
        'Pedido a partir de' => $origem ? '<a href="' . h(url('curso', ['id' => $origem['id']])) . '">' . h($origem['nome']) . '</a>' : '',
        'Pedido em' => h(data_hora($p['criado'])),
        'Cliente' => $p['tipo_cliente'] === 'particular' ? 'Particular' : h((string) $p['empresa']),
        'Contactos' => '<a href="mailto:' . h($p['email']) . '">' . h($p['email']) . '</a>' . ($p['telefone'] ? ' · ' . h($p['telefone']) : ''),
        'Formandos previstos' => (string) (int) $p['formandos'],
        'Modalidade preferida' => h(MODALIDADES[$p['modalidade']] ?? 'Indiferente'),
        'Preferências de datas' => nl2br(h((string) $p['preferencias'])),
        'Ferramentas de IA' => h((string) $p['ferramentas']),
        'Estado' => etiqueta('Pré-inscrição', 'pre') . ' ' . h(ESTADOS_PREINSCRICAO[$p['estado']] ?? $p['estado'])
            . ($p['estado'] === 'inscrita' && $p['inscricao_id'] ? ' · <a href="' . h(url('inscricao', ['id' => $p['inscricao_id']])) . '">Ver a inscrição</a>' : ''),
    ], function ($x) { return $x !== ''; });
    $tabela = '';
    foreach ($dados as $k => $val) {
        $tabela .= '<tr><th scope="row">' . h($k) . '</th><td>' . $val . '</td></tr>';
    }
    $html = ($erros ? '<div class="aviso aviso-erro" role="alert">' . h(implode(' ', $erros)) . '</div>' : '')
        . ($p['estado'] === 'inscrita' ? '<div class="aviso aviso-ok">Esta pré-inscrição já deu origem a uma inscrição.' . ($p['inscricao_id'] ? ' <a href="' . h(url('inscricao', ['id' => $p['inscricao_id']])) . '">Ver a inscrição</a>.' : '') . '</div>' : '')
        . '<div class="conteudo"><table class="tabela-ficha"><tbody>' . $tabela . '</tbody></table></div>
        <form method="post" class="formulario linha-form">' . campo_csrf() . '<input type="hidden" name="acao" value="estado">
          ' . campo_select('estado', 'Estado da pré-inscrição', estados_preinscricao_manuais($p['estado']), $p['estado']) . '
          <div></div><div><button class="botao botao-secundario" type="submit">Guardar estado</button></div></form>'
        . $form_curso
        . ($a ? '<h2 class="espaco-topo">Diagnóstico</h2>
        <div class="diagnostico-resultado">
          <div><span class="texto-suave">Nível</span>' . nivel_etiqueta($a['nivel']) . '</div>
          <div><span class="texto-suave">Pontuação</span><span><strong>' . (int) $a['pontuacao'] . '</strong> em ' . $a['maximo'] . '</span></div>
          <div><span class="texto-suave">Uso responsável</span><span><strong>' . (int) $a['responsavel'] . '</strong> em 9</span></div>
        </div>
        ' . ($rec ? '<p><strong>Recomendação:</strong> ' . h($rec) . '</p>' : '') . '
        <details class="programa"><summary>Ver as respostas</summary><div class="programa-corpo conteudo tabela-rolante">' . tabela_diagnostico(['respostas' => json_encode($respostas)]) . '</div></details>' : '');
    if ($p['estado'] !== 'inscrita') {
        $html .= '<h2 class="espaco-topo" id="inscrever">Inscrever e definir datas</h2>
        <p class="texto-suave">Escolha uma edição já agendada ou crie uma edição nova, com as datas das sessões. A inscrição inclui sempre todas as sessões do curso. A pré-inscrição passa a "Inscrita" e a inscrição aparece em Inscrições, com os contactos e o diagnóstico.' . ((int) $p['formandos'] > 1 ? ' Esta pré-inscrição prevê ' . (int) $p['formandos'] . ' formandos: os restantes inscrevem-se depois, na mesma edição.' : '') . '</p>
        <form method="post" class="formulario" action="' . h(url('preinscricao', ['id' => $id])) . '#inscrever">' . campo_csrf() . '<input type="hidden" name="acao" value="inscrever">
          <fieldset class="campo-grupo"><legend>Sessão *</legend><div class="opcoes">' . $opcoes . '</div></fieldset>
          <fieldset class="campo-grupo nova-sessao"><legend>Nova edição (preencher só se escolheu "Nova edição")</legend>
            <div class="campos-2">
              ' . seletor_curso((int) $v['curso']) . '
              ' . campo('data', 'Data (da 1.ª sessão)', (string) $v['data'], 'date') . '
              <div class="campos-2">' . campo('hora_inicio', 'Início', (string) $v['hora_inicio'], 'time') . campo('hora_fim', 'Fim', (string) $v['hora_fim'], 'time') . '</div>
              ' . campo_select('modalidade', 'Modalidade', MODALIDADES, (string) $v['modalidade']) . '
              ' . campo('local', 'Local', (string) $v['local'], 'text', 'maxlength="160" placeholder="Morada, nas sessões presenciais ou mistas"') . '
              ' . campo('vagas', 'Vagas', (string) $v['vagas'], 'number', 'min="1" max="500"') . '
            </div>
            ' . campos_sessoes_curso((int) $v['curso'], sessoes_do_formulario(0)) . '
            ' . campo('reuniao', 'Ligação da reunião online (Google Meet)', (string) $v['reuniao'], 'url', 'maxlength="300" placeholder="https://meet.google.com/abc-defg-hij"') . '
          </fieldset>
          <div class="campos-2">
            ' . campo('formando', 'Nome do formando', (string) $v['formando'], 'text', 'maxlength="120"') . '
            ' . campo('email_formando', 'Email do formando', (string) $v['email_formando'], 'email', 'maxlength="160"') . '
            ' . campo_select('estado_inscricao', 'Estado da inscrição', ['confirmada' => 'Confirmada', 'pendente' => 'Pendente'], (string) $v['estado_inscricao']) . '
          </div>
          <label class="opcao consentimento"><input type="checkbox" name="avisar" value="1" checked> <span>Enviar a confirmação por email ao candidato, com as datas, o local e a ligação das sessões online (só quando o estado é Confirmada)</span></label>
          <div><button class="botao" type="submit">Inscrever</button></div>
        </form>';
    }
    $html .= '<p class="espaco-topo">' . botao_apagar('preinscricao', $id, 'Apagar esta pré-inscrição') . '</p>
      <p class="espaco-topo"><a href="' . h(url('inscricoes', ['estado' => 'preinscricao'])) . '">Voltar às pré-inscrições</a></p>';
    pagina_area('inscricoes', (string) $p['nome'], $html, 'Pré-inscrição');
}

// Nível e curso aconselhado pelo diagnóstico de uma pré-inscrição, com aviso quando difere do curso associado
function diagnostico_preinscricao(array $p, array $c): string
{
    $r = json_decode((string) ($p['respostas'] ?? ''), true);
    if (!is_array($r) || !$r) {
        return '<span class="texto-suave">Sem diagnóstico</span>';
    }
    $a = avaliar_diagnostico($r);
    $ind = curso_indicado($r, $c);
    return nivel_etiqueta($a['nivel']) . ($ind ? '<br>Aconselhado: ' . h(partes_nome($ind['curso'])[0])
        . ((int) $ind['curso']['id'] !== (int) $c['id'] ? ' ' . etiqueta('Diferente do curso associado', 'info') : '') : '')
        . ($a['responsavel'] <= 4 ? '<br><span class="texto-suave">Uso responsável baixo (' . (int) $a['responsavel'] . ' em 9)</span>' : '');
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

// Lista de inscrições; as pré-inscrições ainda sem inscrição aparecem no topo com o estado "Pré-inscrição"
function admin_inscricoes(): void
{
    if (($_GET['vista'] ?? '') !== 'todas' && !array_intersect_key($_GET, array_flip(['formacao', 'cliente', 'estado', 'origem', 'exportar']))) {
        admin_formandos();
        return;
    }
    $origens = ['site' => 'Site', 'por_associar' => 'Site, por associar a cliente', 'area' => 'Área reservada'];
    $estados = ['preinscricao' => 'Pré-inscrição'] + ESTADOS_INSCRICAO;
    $filtros = ['formacao' => (int) ($_GET['formacao'] ?? 0), 'cliente' => (int) ($_GET['cliente'] ?? 0), 'estado' => isset($estados[$_GET['estado'] ?? '']) ? $_GET['estado'] : '', 'origem' => isset($origens[$_GET['origem'] ?? '']) ? $_GET['origem'] : ''];
    $com_pre = !$filtros['formacao'] && !$filtros['cliente'] && in_array($filtros['estado'], ['', 'preinscricao'], true) && in_array($filtros['origem'], ['', 'site', 'por_associar'], true);
    $pre = $com_pre ? consulta("SELECT p.*, c.nome AS curso, fe.inicio AS edicao_inicio FROM preinscricoes p JOIN cursos c ON c.id = p.curso_id LEFT JOIN formacoes fe ON fe.id = p.formacao_id AND fe.estado != 'cancelada' WHERE p.estado != 'inscrita' ORDER BY p.estado = 'nova' DESC, p.criado DESC") : [];
    $sql = 'SELECT i.*, ' . SQL_CLIENTE . ', f.titulo, f.inicio FROM inscricoes i LEFT JOIN clientes c ON c.id = i.cliente_id JOIN formacoes f ON f.id = i.formacao_id WHERE ' . ($filtros['estado'] === 'preinscricao' ? '0 = 1' : '1 = 1');
    $par = [];
    if ($filtros['formacao']) { $sql .= ' AND i.formacao_id = ?'; $par[] = $filtros['formacao']; }
    if ($filtros['cliente']) { $sql .= ' AND i.cliente_id = ?'; $par[] = $filtros['cliente']; }
    if ($filtros['estado'] && $filtros['estado'] !== 'preinscricao') { $sql .= ' AND i.estado = ?'; $par[] = $filtros['estado']; }
    if ($filtros['origem'] === 'por_associar') { $sql .= ' AND i.cliente_id IS NULL'; }
    elseif ($filtros['origem']) { $sql .= ' AND i.origem = ?'; $par[] = $filtros['origem']; }
    $lista = consulta($sql . ' ORDER BY f.inicio DESC, i.criado', $par);
    if (!empty($_GET['exportar'])) {
        $linhas = [];
        foreach ($pre as $p) {
            $a = ($r = json_decode((string) $p['respostas'], true)) ? avaliar_diagnostico($r) : ['nivel' => '', 'pontuacao' => '', 'responsavel' => ''];
            $linhas[] = [$p['curso'], '', $p['empresa'] ?: $p['nome'], TIPOS_CLIENTE[$p['tipo_cliente'] ?? ''] ?? '', 'Site (pré-inscrição)', $p['nome'], $p['email'], $p['telefone'], $p['nome'], $p['email'], '', 'Pré-inscrição: ' . (ESTADOS_PREINSCRICAO[$p['estado']] ?? $p['estado']), $a['nivel'], $a['pontuacao'], $a['responsavel'], $p['ferramentas'], $p['preferencias'], data_hora($p['criado'])];
        }
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
    foreach ($pre as $p) {
        $r = json_decode((string) $p['respostas'], true);
        $linhas .= '<tr class="linha-pre"><td><a href="' . h(url('preinscricao', ['id' => $p['id']])) . '">' . h($p['nome']) . '</a>' . ((int) $p['formandos'] > 1 ? '<br><span class="texto-suave">' . (int) $p['formandos'] . ' formandos previstos</span>' : '') . '</td>'
            . '<td>' . cliente_html(['cliente' => $p['empresa'] ?: $p['nome'], 'cliente_tipo' => $p['tipo_cliente']]) . ' ' . etiqueta('Site') . '</td><td>' . h($p['curso']) . '<br><span class="texto-suave">'
            . (!empty($p['edicao_inicio']) ? 'Associada à edição de <a href="' . h(url('formacao', ['id' => $p['formacao_id']])) . '">' . h(data_hora($p['edicao_inicio'], false)) . '</a>' : 'Sem edição agendada') . '</span></td>'
            . '<td>' . (($cp = curso((int) $p['curso_id'])) ? diagnostico_preinscricao($p, $cp) : '') . '</td>'
            . '<td>' . etiqueta('Pré-inscrição', 'pre') . ($p['estado'] !== 'nova' ? '<br><span class="texto-suave">' . h(ESTADOS_PREINSCRICAO[$p['estado']] ?? $p['estado']) . '</span>' : '') . '</td><td>' . h(data_hora($p['criado'])) . '</td></tr>';
    }
    foreach ($lista as $i) {
        $linhas .= '<tr><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . cliente_html($i) . etiqueta_origem($i) . '</td><td>' . h($i['titulo']) . '<br><span class="texto-suave">' . h(data_hora($i['inicio'])) . '</span></td><td>' . nivel_etiqueta($i['nivel']) . '</td><td>' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</td><td>' . h(data_hora($i['criado'])) . '</td></tr>';
    }
    pagina_area('inscricoes', 'Inscrições', submenu_inscricoes($filtros['estado'] === 'preinscricao' ? 'pre' : 'todas') . '
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
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Cliente</th><th>Tipo</th><th>NIF</th><th>Contacto</th><th>Utilizadores</th><th>Inscrições</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há clientes.</p>') . '
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
        $cliente_id = $papel === 'cliente' ? (int) ($_POST['cliente_id'] ?? 0) : null;
        $senha = (string) ($_POST['senha'] ?? '');
        $erro = '';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Indique o nome e um email válido.';
        } elseif ($papel === 'cliente' && !isset($clientes[$cliente_id])) {
            $erro = 'Escolha o cliente deste utilizador.';
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
            executar('INSERT INTO utilizadores (email, nome, senha, papel, cliente_id, criado) VALUES (?, ?, ?, ?, ?, ?)', [$email, $nome, password_hash($senha, PASSWORD_DEFAULT), $papel, $cliente_id, agora()]);
        }
        aviso('Utilizador criado. Dados de acesso para comunicar a ' . h($nome) . ':<br>Endereço: <strong>' . h(endereco_site()) . '/clientes/</strong><br>Email: <strong>' . h($email) . '</strong><br>Palavra-passe: <strong class="senha">' . h($senha) . '</strong><br>' . ($gerada ? 'Esta palavra-passe não volta a ser mostrada.' : ''));
        if (!empty($_POST['enviar_acesso'])) {
            aviso_envio(enviar_acesso(['nome' => $nome, 'email' => $email, 'papel' => $papel, 'cliente_id' => $cliente_id], $senha), 'Dados de acesso enviados por email para ' . h($email) . '.');
        }
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
      <h2 class="espaco-topo" id="novo">Novo utilizador</h2>
      <p class="texto-suave">Perfil Gadgetmetodo: vê e gere tudo. Perfil Cliente: vê apenas as formações e as inscrições do seu cliente. Perfil Formando: vê as suas formações confirmadas (pelo email das inscrições) e regista a presença em cada sessão; é criado automaticamente quando uma inscrição com email é confirmada.</p>
      <form method="post" class="formulario">' . campo_csrf() . '<div class="campos-2">
        ' . campo('nome', 'Nome *', '', 'text', 'required maxlength="120"') . '
        ' . campo('email', 'Email (é o nome de utilizador) *', '', 'email', 'required maxlength="160" autocomplete="off"') . '
        ' . campo_select('papel', 'Perfil *', PAPEIS, 'cliente') . '
        ' . campo_select('cliente_id', 'Cliente (perfil Cliente)', $clientes, $pre, 'Escolha o cliente') . '
        ' . campo('senha', 'Palavra-passe', '', 'text', 'minlength="10" autocomplete="new-password" placeholder="Vazio: é gerada automaticamente"') . '
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
        $tabela .= '<tr><td style="padding:7px 12px 7px 0;border-bottom:1px solid #E5E7EB;color:#687280;width:130px;">' . h($k) . '</td><td style="padding:7px 0;border-bottom:1px solid #E5E7EB;font-weight:700;">' . h($v) . '</td></tr>';
    }
    return enviar_mensagem('acesso', (string) $u['email'], ['nome' => nome_curto((string) $u['nome']), 'nome_completo' => $u['nome'], 'email' => $u['email'], 'cliente' => $cliente], [
        '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 4px;">' . $tabela . '</table>'
        . '<p style="margin:22px 0 0;"><a href="' . h($endereco) . '" style="display:inline-block;padding:11px 20px;background:#00636A;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:6px;">Entrar na área reservada</a></p>',
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
