<?php
// Gestão de formandos (menu "Formandos"): um fluxo único, centrado no formando, do diagnóstico ao encerramento.
// Vistas: Formandos (uma linha por pessoa), Inscrições (uma linha por formando e curso) e Diagnósticos (uma linha por
// diagnóstico). Ficha do formando: dados, diagnósticos e um cartão por inscrição com as 10 etapas, o estado de cada
// uma (por fazer, em curso, concluída, bloqueada) e a ação direta.

declare(strict_types=1);

const ETAPAS = [1 => 'Diagnóstico', 2 => 'Inscrição', 3 => 'Email de confirmação', 4 => 'Dados do certificado', 5 => 'Curso e formato',
    6 => 'Turma', 7 => 'Manual e áudio', 8 => 'Assiduidade', 9 => 'Certificado', 10 => 'Encerramento'];
const ESTADOS_ETAPA = ['feita' => 'Concluída', 'em_curso' => 'Em curso', 'por_fazer' => 'Por fazer', 'bloqueada' => 'Bloqueada'];
const AVALIACOES = ['por_fazer' => 'Por fazer', 'aprovado' => 'Aprovado', 'nao_aprovado' => 'Não aprovado'];
const TIPOS_MATERIAL = ['manual' => 'Manual', 'audio' => 'Áudio', 'outro' => 'Outro material'];

/* Estados */

// Estados automáticos: Inscrito → Em formação quando a turma começa; Em formação → Concluído quando a turma termina
// com a avaliação "Aprovado"; Certificado quando há certificado emitido. As saídas (Desistiu, Não concluído) e o
// Encerrado não mudam sozinhos.
function sincronizar_estados(): void
{
    $agora = date('Y-m-d H:i:s');
    executar("UPDATE inscricoes SET estado = 'certificado' WHERE estado IN ('inscrito', 'em_formacao', 'concluido') AND EXISTS (SELECT 1 FROM certificados c WHERE c.inscricao_id = inscricoes.id)");
    executar("UPDATE inscricoes SET estado = 'em_formacao' WHERE estado = 'inscrito' AND formacao_id IS NOT NULL
        AND (SELECT MIN(COALESCE(s.inicio, f.inicio)) FROM formacoes f LEFT JOIN sessoes s ON s.formacao_id = f.id WHERE f.id = inscricoes.formacao_id) <= ?", [$agora]);
    executar("UPDATE inscricoes SET estado = 'concluido' WHERE estado = 'em_formacao' AND avaliacao = 'aprovado'
        AND (SELECT MAX(COALESCE(s.fim, s.inicio, f.fim, f.inicio)) FROM formacoes f LEFT JOIN sessoes s ON s.formacao_id = f.id WHERE f.id = inscricoes.formacao_id) <= ?", [$agora]);
}

// Estado de um formando: o da inscrição mais recente ou, antes de se inscrever, a situação do diagnóstico
function estado_formando(array $inscricoes, array $diagnosticos): string
{
    if ($inscricoes) {
        return ESTADOS_INSCRICAO[$inscricoes[0]['estado']] ?? $inscricoes[0]['estado'];
    }
    if (!$diagnosticos) {
        return 'Diagnóstico por enviar';
    }
    return $diagnosticos[0]['respondido'] !== null ? 'Diagnóstico respondido' : 'Diagnóstico enviado';
}

/* Etapas de uma inscrição */

function materiais_inscricao(array $i): array
{
    return consulta('SELECT * FROM materiais WHERE curso_id = ? OR (formacao_id IS NOT NULL AND formacao_id = ?) ORDER BY ordem, id', [(int) $i['curso_id'], (int) ($i['formacao_id'] ?? 0)]);
}

function acessos_inscricao(int $inscricao_id): array
{
    return consulta('SELECT a.*, m.titulo FROM materiais_acessos a LEFT JOIN materiais m ON m.id = a.material_id WHERE a.inscricao_id = ? ORDER BY a.registado DESC', [$inscricao_id]);
}

// Tudo o que as etapas precisam, numa só leitura
function contexto_inscricao(array $i): array
{
    $sessoes = $i['formacao_id'] ? sessoes_formacao_cache((int) $i['formacao_id']) : [];
    $presencas = presencas_inscricao((int) $i['id']);
    $iniciadas = count(array_filter($sessoes, function ($s) { return strtotime($s['inicio']) <= time(); }));
    $fim = $sessoes ? max(array_map(function ($s) { return strtotime($s['fim'] ?: $s['inicio']); }, $sessoes)) : 0;
    $diag = $i['diagnostico_id'] ? linha_bd('SELECT * FROM diagnosticos WHERE id = ?', [(int) $i['diagnostico_id']]) : null;
    return [
        'diag' => $diag, 'ficha' => ficha_certificado((string) $i['email']), 'materiais' => materiais_inscricao($i), 'acessos' => acessos_inscricao((int) $i['id']),
        'sessoes' => $sessoes, 'presencas' => $presencas, 'iniciadas' => $iniciadas, 'terminada' => $fim > 0 && $fim <= time(),
        'percentagem' => $sessoes ? (int) round(100 * count($presencas) / count($sessoes)) : 0, 'certificado' => certificado_da_inscricao((int) $i['id']),
        'curso' => curso((int) $i['curso_id']), 'turma' => $i['formacao_id'] ? linha_bd('SELECT * FROM formacoes WHERE id = ?', [(int) $i['formacao_id']]) : null,
    ];
}

// Motivos que bloqueiam a emissão do certificado (vazio: pode emitir)
function bloqueios_certificado(array $i, array $ctx): array
{
    $r = [];
    if (!$ctx['turma']) {
        $r[] = 'falta a turma';
    }
    if (!ficha_completa($ctx['ficha'])) {
        $r[] = 'faltam dados para o certificado' . (($f = campos_em_falta($ctx['ficha'])) ? ' (' . mb_strtolower(implode(', ', $f)) . ')' : '');
    }
    if (!$ctx['presencas']) {
        $r[] = 'não há presenças registadas';
    }
    if (empty($i['assiduidade_validada'])) {
        $r[] = 'falta a validação da assiduidade pelo formador';
    }
    if (($i['avaliacao'] ?? 'por_fazer') !== 'aprovado') {
        $r[] = 'a avaliação não está concluída com "Aprovado"';
    }
    return $r;
}

// As 10 etapas: [n => ['estado' => feita|em_curso|por_fazer|bloqueada, 'detalhe' => texto]]
function etapas_inscricao(array $i, array $ctx): array
{
    $e = [];
    $d = $ctx['diag'];
    $e[1] = $d && $d['respondido'] !== null ? ['feita', 'Respondido em ' . data_hora($d['respondido'], false) . ($d['nivel'] ? ' · ' . $d['nivel'] : '')]
        : ($d ? ['em_curso', 'Enviado em ' . data_hora($d['enviado'], false) . ', por responder'] : (!empty($i['respostas']) ? ['feita', 'Respondido na inscrição' . ($i['nivel'] ? ' · ' . $i['nivel'] : '')] : ['por_fazer', 'Sem diagnóstico']));
    $e[2] = ['feita', 'Inscrito em ' . data_hora($i['criado'], false)];
    $e[3] = !empty($i['email_inscricao']) ? ['feita', 'Enviado em ' . data_hora($i['email_inscricao'], false)] : ((string) $i['email'] === '' ? ['bloqueada', 'Sem email'] : ['por_fazer', 'Por enviar']);
    $falta = campos_em_falta($ctx['ficha']);
    $e[4] = ficha_completa($ctx['ficha']) ? ['feita', 'Completos'] : (!empty($i['dados_pedidos']) ? ['em_curso', 'Pedidos em ' . data_hora($i['dados_pedidos'], false) . '; faltam: ' . mb_strtolower(implode(', ', $falta))] : ['por_fazer', 'Em falta: ' . mb_strtolower(implode(', ', $falta))]);
    $n_temas = $ctx['curso'] ? count(modulos_curso((int) $ctx['curso']['id'])) : 0;
    $e[5] = $ctx['curso'] ? ['feita', $ctx['curso']['nome'] . ($n_temas ? ' (' . $n_temas . ' sessões)' : '')] : ['por_fazer', 'Sem curso'];
    $e[6] = $ctx['turma'] ? ['feita', horario($ctx['turma']) . ' · ' . modalidade_e_local($ctx['turma'])] : ['por_fazer', 'Por escolher'];
    $acessos = $ctx['acessos'];
    $e[7] = !$ctx['materiais'] ? ['bloqueada', 'Sem materiais no curso ou na turma'] : ($acessos ? ['feita', implode('; ', array_unique(array_map(function ($a) {
        return (['enviado' => 'Enviado', 'acedido' => 'Acedido', 'entregue' => 'Entregue'][$a['tipo']] ?? $a['tipo']) . ' em ' . data_hora($a['registado'], false);
    }, array_slice($acessos, 0, 3))))] : ['por_fazer', count($ctx['materiais']) . ' material(is) por enviar']);
    $assiduidade = $ctx['sessoes'] ? count($ctx['presencas']) . ' de ' . count($ctx['sessoes']) . ' sessões (' . $ctx['percentagem'] . '%)' : '';
    $e[8] = !$ctx['turma'] ? ['bloqueada', 'Depois de escolher a turma'] : (!empty($i['assiduidade_validada']) ? ['feita', $assiduidade . ' · validada pelo formador']
        : ($ctx['iniciadas'] ? ['em_curso', $assiduidade . ' · por validar'] : ['por_fazer', 'A turma ainda não começou']));
    $bloq = bloqueios_certificado($i, $ctx);
    $e[9] = $ctx['certificado'] ? ['feita', 'N.º ' . numero_certificado($ctx['certificado']) . ', emitido em ' . data_hora($ctx['certificado']['emitido'], false)]
        : ($bloq ? ['bloqueada', ucfirst(implode('; ', $bloq))] : ['por_fazer', 'Pronto a emitir']);
    $e[10] = $i['estado'] === 'encerrado' ? ['feita', 'Encerrada' . (!empty($i['encerrada']) ? ' em ' . data_hora($i['encerrada'], false) : '')]
        : (in_array($i['estado'], ESTADOS_SAIDA, true) ? ['feita', ESTADOS_INSCRICAO[$i['estado']]] : ['por_fazer', 'Aberta']);
    return array_map(function ($x) { return ['estado' => $x[0], 'detalhe' => $x[1]]; }, $e);
}

function progresso(array $etapas): int
{
    return count(array_filter($etapas, function ($e) { return $e['estado'] === 'feita'; }));
}

/* Menu "Formandos": 3 vistas */

const VISTAS_FORMANDOS = ['formandos' => 'Formandos', 'inscricoes' => 'Inscrições', 'diagnosticos' => 'Diagnósticos'];

function separadores_formandos(string $atual): string
{
    $html = '';
    foreach (VISTAS_FORMANDOS as $k => $nome) {
        $html .= '<li><a href="' . h(url('formandos', $k === 'formandos' ? [] : ['vista' => $k])) . '"' . ($k === $atual ? ' aria-current="page"' : '') . '>' . h($nome) . '</a></li>';
    }
    return '<nav class="area-subnav" aria-label="Formandos"><ul>' . $html . '</ul></nav>';
}

function admin_gestao_formandos(): void
{
    sincronizar_estados();
    $vista = (string) ($_GET['vista'] ?? 'formandos');
    if ($vista === 'inscricoes') {
        vista_inscricoes();
    } elseif ($vista === 'diagnosticos') {
        admin_diagnosticos();
    } else {
        vista_formandos();
    }
}

// Rotas antigas: Inscrições e Diagnósticos passaram a vistas de Formandos (os filtros e a exportação mantêm-se)
function redirecionar_inscricoes(): void
{
    redirecionar('formandos', ['vista' => 'inscricoes'] + array_diff_key(array_filter($_GET, 'is_string'), ['p' => 1, 'vista' => 1]));
}

function redirecionar_diagnosticos(): void
{
    redirecionar('formandos', ['vista' => 'diagnosticos'] + array_diff_key(array_filter($_GET, 'is_string'), ['p' => 1, 'vista' => 1]));
}

function opcoes_cursos(): array
{
    return array_column(consulta('SELECT id, nome FROM cursos WHERE ativo = 1 ORDER BY nome'), 'nome', 'id');
}

// Vista Formandos: uma linha por pessoa, com o progresso da inscrição mais recente
function vista_formandos(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'novo_formando') {
        $nome = texto('nome', 120);
        $email = texto('email', 160);
        $cliente = (int) ($_POST['cliente_id'] ?? 0) ?: null;
        $curso = (int) ($_POST['curso_id'] ?? 0);
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            aviso('Indique o nome e um email válido.', 'erro');
            redirecionar('formandos', [], 'novo');
        }
        if (!empty($_POST['enviar']) && !curso($curso)) {
            aviso('Escolha o curso do diagnóstico.', 'erro');
            redirecionar('formandos', [], 'novo');
        }
        $existia = (bool) formando_por_email($email);
        $fid = formando_para($nome, $email, $cliente);
        $msg = $existia ? 'Já existia um formando com este email: foi usado esse.' : 'Formando criado.';
        if (!empty($_POST['enviar'])) {
            [, $ok] = enviar_diagnostico_formando($fid, $curso);
            $msg .= $ok ? ' Diagnóstico enviado para ' . h($email) . ' (ligação válida 30 dias).' : ' O email do diagnóstico não foi enviado: verifique a Comunicação.';
        }
        aviso($msg, !empty($_POST['enviar']) && empty($ok) ? 'erro' : 'ok');
        redirecionar('formando', ['id' => $fid]);
    }
    $formandos = consulta('SELECT f.*, c.nome AS cliente FROM formandos f LEFT JOIN clientes c ON c.id = f.cliente_id ORDER BY f.nome COLLATE NOCASE');
    $linhas = '';
    foreach ($formandos as $f) {
        $insc = consulta('SELECT i.*, ' . "COALESCE(t.titulo, (SELECT c2.nome FROM cursos c2 WHERE c2.id = i.curso_id)) AS titulo, t.inicio, t.fim" . ' FROM inscricoes i LEFT JOIN formacoes t ON t.id = i.formacao_id WHERE i.formando_id = ? ORDER BY i.id DESC', [$f['id']]);
        $diag = consulta('SELECT * FROM diagnosticos WHERE formando_id = ? ORDER BY id DESC', [$f['id']]);
        $ultima = $insc[0] ?? null;
        $progresso = '';
        $turma = '';
        $cert = '';
        if ($ultima) {
            $ctx = contexto_inscricao($ultima);
            $n = progresso(etapas_inscricao($ultima, $ctx));
            $progresso = '<span class="progresso" style="--p:' . ($n * 10) . '%">' . $n . '/10</span>';
            $turma = $ctx['turma'] ? date('d/m/Y', strtotime($ctx['turma']['inicio'])) : 'Por escolher';
            $cert = $ctx['certificado'] ? 'Emitido' : (inscricao_ativa($ultima) ? 'Por emitir' : '');
        }
        $u = (string) $f['email'] !== '' ? utilizador_por_email((string) $f['email']) : null;
        $linhas .= '<tr><td><input type="checkbox" form="acoes-formandos" name="formando[]" value="' . (int) $f['id'] . '" aria-label="Escolher ' . h($f['nome']) . '"></td>'
            . '<td><a href="' . h(url('formando', ['id' => $f['id']])) . '" translate="no">' . h($f['nome']) . '</a></td><td translate="no">' . h((string) $f['email']) . '</td><td>' . h((string) $f['cliente']) . '</td>'
            . '<td>' . h(estado_formando($insc, $diag)) . '</td><td>' . h(implode(', ', array_unique(array_map(function ($x) { return curso_curto((string) $x['titulo']); }, $insc)))) . '</td>'
            . '<td>' . h($turma) . '</td><td data-valor="' . ($ultima ? progresso(etapas_inscricao($ultima, $ctx)) : 0) . '">' . $progresso . '</td><td>' . h($cert) . '</td>'
            . '<td data-valor="' . h((string) ($u['ultimo_acesso'] ?? '')) . '">' . h($u && $u['ultimo_acesso'] ? data_hora($u['ultimo_acesso'], false) : '') . '</td></tr>';
    }
    $cursos = opcoes_cursos();
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    pagina_area('formandos', 'Formandos', separadores_formandos('formandos') . '
      <p class="texto-suave">Um fluxo por formando, do diagnóstico ao encerramento do curso: crie o formando e envie-lhe o diagnóstico; quando o responder, fica inscrito no curso indicado e recebe o email da inscrição. Abra a ficha para seguir as etapas de cada inscrição. Use os filtros por baixo dos títulos (curso, turma, estado, certificado) e o botão "Colunas".</p>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table data-filtros="formandos2" class="tabela-escolha"><thead><tr><th data-sem-filtro><input type="checkbox" class="marcar-todos" aria-label="Escolher todos os formandos visíveis"></th><th>Nome</th><th>Email</th><th data-filtro="lista">Cliente</th><th data-filtro="lista">Estado</th><th data-filtro="lista">Curso</th><th data-filtro="lista">Turma</th><th>Progresso</th><th data-filtro="lista">Certificado</th><th>Último acesso</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
        <form method="post" id="acoes-formandos" class="formulario espaco-topo-curto">' . campo_csrf() . '<input type="hidden" name="acao" value="diagnostico_lote">
          <div class="campos-2">' . campo_select('curso_lote', 'Enviar o diagnóstico aos escolhidos, para o curso', $cursos, '', 'Escolha o curso') . '</div>
          <div><button class="botao botao-secundario" type="submit" formaction="' . h(url('formandos', ['vista' => 'diagnosticos'])) . '">Enviar o diagnóstico</button></div>
        </form>' : '<p class="texto-suave">Ainda não há formandos.</p>') . '
      <h2 class="espaco-topo" id="novo">Novo formando</h2>
      <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="novo_formando">
        <div class="campos-2">
          ' . campo('nome', 'Nome *', '', 'text', 'required maxlength="120"') . '
          ' . campo('email', 'Email *', '', 'email', 'required maxlength="160"') . '
          ' . campo_select('cliente_id', 'Cliente ou empresa (opcional)', $clientes, '', 'Sem cliente') . '
          ' . campo_select('curso_id', 'Curso do diagnóstico', $cursos, '', 'Escolha o curso') . '
        </div>
        <label class="opcao consentimento"><input type="checkbox" name="enviar" value="1" checked> <span>Enviar já o diagnóstico por email (ligação pessoal, válida 30 dias). Quando o formando responder, fica inscrito neste curso e recebe o email da inscrição.</span></label>
        <div><button class="botao" type="submit">Criar formando</button></div>
      </form>', 'Formação');
}

// Vista Inscrições: uma linha por formando e curso, com filtros e exportação para Excel
function vista_inscricoes(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'turma_lote') {
        $t = linha_bd("SELECT * FROM formacoes WHERE id = ? AND estado NOT IN ('cancelada', 'encerrada')", [(int) ($_POST['turma'] ?? 0)]);
        $ids = array_map('intval', array_filter((array) ($_POST['insc'] ?? []), 'is_numeric'));
        $feitas = 0;
        $falhas = [];
        foreach ($ids as $iid) {
            $i = linha_bd(SQL_INSCRICAO, [$iid]);
            if (!$t || !$i) {
                continue;
            }
            $erro = atribuir_turma($i, $t, false);
            $erro ? $falhas[] = $i['formando'] . ' (' . $erro . ')' : $feitas++;
        }
        aviso(!$t ? 'Escolha a turma.' : ($feitas . ' inscrição(ões) passaram para a turma de ' . h(horario($t)) . '.' . ($falhas ? ' Sem alteração: ' . h(implode('; ', $falhas)) . '.' : '')), !$t || ($falhas && !$feitas) ? 'erro' : ($falhas ? 'info' : 'ok'));
        redirecionar('formandos', ['vista' => 'inscricoes']);
    }
    $lista = consulta('SELECT i.*, ' . SQL_CLIENTE . ", COALESCE(t.titulo, (SELECT c2.nome FROM cursos c2 WHERE c2.id = i.curso_id)) AS titulo, t.inicio
        FROM inscricoes i LEFT JOIN clientes c ON c.id = i.cliente_id LEFT JOIN formacoes t ON t.id = i.formacao_id"
        . (!empty($_GET['formacao']) ? ' WHERE i.formacao_id = ' . (int) $_GET['formacao'] : '') . ' ORDER BY i.id DESC');
    $linhas = '';
    $csv = [];
    foreach ($lista as $i) {
        $ctx = contexto_inscricao($i);
        $et = etapas_inscricao($i, $ctx);
        $n = progresso($et);
        $turma = $ctx['turma'] ? date('d/m/Y', strtotime($ctx['turma']['inicio'])) : 'Por escolher';
        $cert = $ctx['certificado'] ? 'Emitido' : ($et[9]['estado'] === 'bloqueada' ? 'Bloqueado' : 'Por emitir');
        $assid = $ctx['sessoes'] ? $ctx['percentagem'] . '%' : '';
        $linhas .= '<tr><td><input type="checkbox" form="acoes-inscricoes" name="insc[]" value="' . (int) $i['id'] . '" aria-label="Escolher ' . h($i['formando']) . '"></td>'
            . '<td><a href="' . h(url('formando', ['id' => $i['formando_id']])) . '#inscricao-' . (int) $i['id'] . '" translate="no">' . h($i['formando']) . '</a></td><td>' . h((string) $i['cliente']) . '</td>'
            . '<td>' . h(curso_curto((string) $i['titulo'])) . '</td><td>' . h($turma) . '</td><td>' . h(ESTADOS_INSCRICAO[$i['estado']] ?? $i['estado']) . '</td>'
            . '<td data-valor="' . $n . '"><span class="progresso" style="--p:' . ($n * 10) . '%">' . $n . '/10</span></td><td data-valor="' . $ctx['percentagem'] . '">' . h($assid) . '</td>'
            . '<td>' . h(AVALIACOES[$i['avaliacao']] ?? '') . '</td><td>' . h($cert) . '</td></tr>';
        $csv[] = [$i['formando'], $i['email'], $i['cliente'], $i['titulo'], $ctx['turma'] ? data_hora($ctx['turma']['inicio']) : '', ESTADOS_INSCRICAO[$i['estado']] ?? $i['estado'], $n . '/10',
            $assid, AVALIACOES[$i['avaliacao']] ?? '', $ctx['certificado'] ? numero_certificado($ctx['certificado']) : '', $i['nivel'], data_hora($i['criado'])];
    }
    if (!empty($_GET['exportar'])) {
        exportar_csv('gadgetmetodo-inscricoes', ['Formando', 'Email', 'Cliente', 'Curso', 'Turma', 'Estado', 'Progresso', 'Assiduidade', 'Avaliação', 'Certificado', 'Nível de IA', 'Inscrito em'], $csv);
    }
    $turmas = [];
    foreach (consulta("SELECT * FROM formacoes WHERE estado NOT IN ('cancelada', 'encerrada') ORDER BY inicio") as $t) {
        $turmas[$t['id']] = $t['titulo'] . ' · ' . horario($t) . ' · ' . inscritos((int) $t['id']) . '/' . (int) $t['vagas'] . ' vagas ocupadas';
    }
    pagina_area('formandos', 'Inscrições', separadores_formandos('inscricoes') . '
      <p class="texto-suave">Uma linha por formando e curso. O nome abre a ficha do formando, no cartão desta inscrição. <a href="' . h(url('formandos', ['vista' => 'inscricoes', 'exportar' => 1])) . '">Exportar para Excel</a></p>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table data-filtros="inscricoes2" class="tabela-escolha"><thead><tr><th data-sem-filtro><input type="checkbox" class="marcar-todos" aria-label="Escolher todas as inscrições visíveis"></th><th>Formando</th><th data-filtro="lista">Cliente</th><th data-filtro="lista">Curso</th><th data-filtro="lista">Turma</th><th data-filtro="lista">Estado</th><th>Progresso</th><th>Assiduidade</th><th data-filtro="lista">Avaliação</th><th data-filtro="lista">Certificado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
        <form method="post" id="acoes-inscricoes" class="formulario espaco-topo-curto">' . campo_csrf() . '<input type="hidden" name="acao" value="turma_lote">
          <div class="campos-2">' . campo_select('turma', 'Passar as escolhidas para a turma', $turmas, '', 'Escolha a turma') . '</div>
          <div><button class="botao botao-secundario" type="submit">Atribuir a turma</button></div>
          <p class="ajuda">Só para inscrições no mesmo curso da turma e enquanto houver vagas. Não envia emails; para avisar o formando, use a ação da turma na ficha.</p>
        </form>' : '<p class="texto-suave">Ainda não há inscrições.</p>'), 'Formação');
}

// Turma de uma inscrição (etapa 6): mesmo curso, turma não cancelada nem encerrada e com vagas. Devolve '' ou o motivo.
function atribuir_turma(array $i, array $t, bool $avisar): string
{
    if ((int) $t['curso_id'] !== (int) $i['curso_id']) {
        return 'a turma é de outro curso';
    }
    if (in_array($t['estado'], ['cancelada', 'encerrada'], true)) {
        return 'a turma está ' . ($t['estado'] === 'cancelada' ? 'cancelada' : 'encerrada');
    }
    if ((int) ($i['formacao_id'] ?? 0) === (int) $t['id']) {
        return 'já está nesta turma';
    }
    if (inscritos((int) $t['id']) >= (int) $t['vagas']) {
        return 'a turma não tem vagas';
    }
    if ((string) $i['email'] !== '' && linha_bd("SELECT id FROM inscricoes WHERE formacao_id = ? AND email = ? COLLATE NOCASE AND estado NOT IN ('desistiu', 'nao_concluido') AND id != ?", [$t['id'], $i['email'], $i['id']])) {
        return 'já tem outra inscrição nesta turma';
    }
    executar('UPDATE inscricoes SET formacao_id = ? WHERE id = ?', [$t['id'], $i['id']]);
    sincronizar_estados();
    if ($avisar) {
        $GLOBALS['turma_avisada'] = avisar_turma((int) $i['id']);
    }
    return '';
}

// Email com a turma (datas, local e ligação das sessões): a quem fez a inscrição (cliente ou site), com cópia ao
// formando, ou ao próprio formando
function avisar_turma(int $inscricao_id): bool
{
    $i = linha_bd(SQL_INSCRICAO, [$inscricao_id]);
    $contacto = $i ? (contacto_inscricao($i) ?? (filter_var((string) $i['email'], FILTER_VALIDATE_EMAIL) ? ['email' => (string) $i['email'], 'nome' => (string) $i['formando'], 'area' => false] : null)) : null;
    return $contacto ? avisar_estado_inscricao($i, 'inscrito', $contacto) : false;
}

/* Ficha do formando */

// Formando pedido: ?id= (formando), ou ?i= (inscrição) e ?e= (email), das ligações antigas
function formando_pedido(): ?array
{
    if (!empty($_GET['id'])) {
        return formando((int) $_GET['id']);
    }
    if (!empty($_GET['i'])) {
        $i = linha_bd('SELECT formando_id FROM inscricoes WHERE id = ?', [(int) $_GET['i']]);
        return $i ? formando((int) $i['formando_id']) : null;
    }
    if (is_string($_GET['e'] ?? null) && ($f = formando_por_email($_GET['e']))) {
        return formando((int) $f['id']);
    }
    return null;
}

function admin_ficha_formando(): void
{
    $f = formando_pedido();
    if (!$f) {
        pagina_erro('Formando não encontrado', 'O formando pedido não existe.');
    }
    $fid = (int) $f['id'];
    $ancora = '';
    $erros_ficha = [[], []];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $acao = (string) ($_POST['acao'] ?? '');
        $iid = (int) ($_POST['inscricao'] ?? 0);
        $i = $iid ? linha_bd(SQL_INSCRICAO, [$iid]) : null;
        if ($i && (int) $i['formando_id'] !== $fid) {
            $i = null;
        }
        $ancora = $i ? 'inscricao-' . $iid : '';
        $me = (int) utilizador()['id'];
        switch ($acao) {
            case 'dados':
                $nome = texto('nome', 120);
                $email = texto('email', 160);
                if ($nome === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                    aviso('Indique o nome e um email válido.', 'erro');
                    break;
                }
                if ($email !== '' && ($outro = formando_por_email($email)) && (int) $outro['id'] !== $fid) {
                    aviso('Já existe outro formando com este email.', 'erro');
                    break;
                }
                executar('UPDATE formandos SET nome = ?, email = ?, telefone = ?, cliente_id = ?, notas = ? WHERE id = ?',
                    [$nome, $email ?: null, texto('telefone', 30) ?: null, (int) ($_POST['cliente_id'] ?? 0) ?: null, texto('notas', 2000, true) ?: null, $fid]);
                // Os dados repetidos nas inscrições acompanham o formando
                executar('UPDATE inscricoes SET formando = ?, email = ? WHERE formando_id = ?', [$nome, $email ?: null, $fid]);
                if (!empty($_POST['consentimento']) && empty($f['consentimento'])) {
                    executar('UPDATE formandos SET consentimento = ? WHERE id = ?', [agora(), $fid]);
                }
                aviso('Dados do formando guardados.');
                break;
            case 'enviar_diagnostico':
                $curso = (int) ($_POST['curso_id'] ?? 0);
                if (!curso($curso)) {
                    aviso('Escolha o curso do diagnóstico.', 'erro');
                    break;
                }
                try {
                    [, $ok] = enviar_diagnostico_formando($fid, $curso);
                    aviso_envio($ok, 'Diagnóstico enviado para ' . h((string) $f['email']) . ' (ligação válida 30 dias). Quando o responder, fica inscrito no ' . h(curso($curso)['nome']) . '.');
                } catch (InvalidArgumentException $e) {
                    aviso(h($e->getMessage()), 'erro');
                }
                $ancora = 'diagnosticos';
                break;
            case 'nova_inscricao':
                $curso = (int) ($_POST['curso_id'] ?? 0);
                if (!curso($curso)) {
                    aviso('Escolha o curso.', 'erro');
                    break;
                }
                $nova = criar_inscricao_curso($fid, $curso, null, 'area');
                if (!empty($_POST['email_inscricao'])) {
                    aviso_envio(enviar_email_inscricao($nova), 'Inscrição criada e email da inscrição enviado para ' . h((string) $f['email']) . '.');
                } else {
                    aviso('Inscrição criada no ' . h(curso($curso)['nome']) . ', sem enviar email.');
                }
                $ancora = 'inscricao-' . $nova;
                break;
            case 'reenviar_email':
                if ($i) {
                    aviso_envio(enviar_email_inscricao($iid), 'Email da inscrição enviado para ' . h((string) $i['email']) . ', com uma ligação de ativação nova (se a conta ainda não estiver ativa).');
                }
                break;
            case 'pedir_dados':
                if ($i) {
                    aviso_envio(pedir_dados_certificado($i), 'Pedido dos dados para o certificado enviado para ' . h((string) $i['email']) . '.');
                }
                break;
            case 'ficha_certificado':
                if ((string) $f['email'] === '') {
                    aviso('O formando não tem email: a ficha para o certificado fica associada ao email.', 'erro');
                    break;
                }
                $erros_ficha = ler_ficha_certificado();
                if (!$erros_ficha[1]) {
                    gravar_ficha_certificado((string) $f['email'], $erros_ficha[0], $me);
                    aviso('Dados para o certificado guardados.');
                    $erros_ficha = [[], []];
                } else {
                    aviso('Reveja os dados para o certificado.', 'erro');
                }
                $ancora = 'certificado';
                break;
            case 'turma':
                $t = $i ? linha_bd('SELECT * FROM formacoes WHERE id = ?', [(int) ($_POST['formacao_id'] ?? 0)]) : null;
                if (!$t) {
                    aviso('Escolha a turma.', 'erro');
                    break;
                }
                $erro = atribuir_turma($i, $t, !empty($_POST['avisar']));
                $avisado = !empty($GLOBALS['turma_avisada']);
                aviso($erro ? 'Turma não alterada: ' . h($erro) . '.' : 'Turma atribuída: ' . h(horario($t)) . '.'
                    . (!empty($_POST['avisar']) ? ($avisado ? ' Aviso enviado por email, com as datas, o local e a ligação das sessões.' : ' O aviso por email não foi enviado (sem email ou falha no envio).') : ''), $erro ? 'erro' : (!empty($_POST['avisar']) && !$avisado ? 'info' : 'ok'));
                break;
            case 'materiais_enviar':
                if ($i) {
                    aviso_envio(enviar_materiais($i), 'Materiais enviados por email para ' . h((string) $i['email']) . '.');
                }
                break;
            case 'material_entregue':
                if ($i) {
                    executar("INSERT INTO materiais_acessos (inscricao_id, material_id, tipo, registado, utilizador_id) VALUES (?, ?, 'entregue', ?, ?)",
                        [$iid, (int) ($_POST['material'] ?? 0) ?: null, agora(), $me]);
                    aviso('Entrega do material registada.');
                }
                break;
            case 'presencas':
                if ($i && $i['formacao_id']) {
                    $marcadas = is_array($_POST['p'] ?? null) ? $_POST['p'] : [];
                    $atuais = presencas_inscricao($iid);
                    $novas = 0;
                    $retiradas = 0;
                    foreach (sessoes_formacao_cache((int) $i['formacao_id']) as $s) {
                        $quer = !empty($marcadas[$s['n']]);
                        if ($quer && !isset($atuais[$s['n']])) {
                            $novas += marcar_presenca($iid, (int) $s['n'], 'admin', $me) ? 1 : 0;
                        } elseif (!$quer && isset($atuais[$s['n']])) {
                            executar('DELETE FROM presencas WHERE inscricao_id = ? AND sessao = ?', [$iid, $s['n']]);
                            $retiradas++;
                        }
                    }
                    aviso($novas || $retiradas ? 'Presenças guardadas: ' . $novas . ' marcada(s), ' . $retiradas . ' retirada(s).' : 'Nada a alterar nas presenças.', $novas || $retiradas ? 'ok' : 'info');
                }
                break;
            case 'validar_assiduidade':
                if ($i) {
                    $sim = !empty($_POST['validada']);
                    executar('UPDATE inscricoes SET assiduidade_validada = ?, assiduidade_por = ? WHERE id = ?', [$sim ? agora() : null, $sim ? $me : null, $iid]);
                    aviso($sim ? 'Assiduidade validada pelo formador.' : 'Validação da assiduidade retirada.');
                }
                break;
            case 'avaliacao':
                if ($i && isset(AVALIACOES[$_POST['avaliacao'] ?? ''])) {
                    executar('UPDATE inscricoes SET avaliacao = ?, avaliacao_nota = ?, avaliacao_obs = ? WHERE id = ?',
                        [$_POST['avaliacao'], texto('avaliacao_nota', 20) ?: null, texto('avaliacao_obs', 1000, true) ?: null, $iid]);
                    sincronizar_estados();
                    aviso('Avaliação guardada.');
                }
                break;
            case 'emitir':
                if ($i) {
                    $ctx = contexto_inscricao($i);
                    if ($b = bloqueios_certificado($i, $ctx)) {
                        aviso('O certificado não pode ser emitido: ' . h(implode('; ', $b)) . '.', 'erro');
                        break;
                    }
                    $c = emitir_certificado($i, $ctx['ficha']);
                    executar("UPDATE inscricoes SET estado = 'certificado' WHERE id = ? AND estado IN ('inscrito', 'em_formacao', 'concluido')", [$iid]);
                    $enviado = !empty($_POST['enviar']) && enviar_certificado($c, $i);
                    aviso('Certificado n.º ' . h(numero_certificado($c)) . ' emitido.' . ($enviado ? ' Enviado por email para ' . h((string) $i['email']) . '.' : ''));
                }
                break;
            case 'estado':
                $novo = (string) ($_POST['estado'] ?? '');
                if ($i && isset(ESTADOS_INSCRICAO[$novo])) {
                    executar('UPDATE inscricoes SET estado = ?, encerrada = ? WHERE id = ?', [$novo, $novo === 'encerrado' ? agora() : null, $iid]);
                    aviso('Estado da inscrição: ' . h(ESTADOS_INSCRICAO[$novo]) . '.');
                }
                break;
        }
        if (!$erros_ficha[1]) {
            redirecionar('formando', ['id' => $fid], $ancora);
        }
    }
    pagina_ficha_formando(formando($fid), $erros_ficha);
}

// Email com os materiais da inscrição (manual, áudio…): ligações da área do formando, que registam o acesso
function enviar_materiais(array $i): bool
{
    $materiais = materiais_inscricao($i);
    if (!$materiais || !filter_var((string) $i['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $base = endereco_site() . '/clientes/area.php?';
    $html = '<ul style="margin:0 0 14px;padding-left:20px;">';
    $texto = '';
    foreach ($materiais as $m) {
        $link = $base . http_build_query(['p' => 'material', 'i' => $i['id'], 'm' => $m['id']]);
        $html .= '<li><a href="' . h($link) . '" style="color:#00636A;">' . h($m['titulo']) . '</a> <span style="color:#687280;">(' . h(TIPOS_MATERIAL[$m['tipo']] ?? $m['tipo']) . ')</span></li>';
        $texto .= '- ' . $m['titulo'] . ': ' . $link . "\n";
    }
    $ok = enviar_mensagem('materiais_formando', (string) $i['email'], ['nome' => nome_curto((string) $i['formando']), 'formando' => $i['formando'], 'curso' => (string) $i['titulo']],
        [$html . '</ul>', trim($texto)]);
    if ($ok) {
        foreach ($materiais as $m) {
            executar("INSERT INTO materiais_acessos (inscricao_id, material_id, tipo, registado, utilizador_id) VALUES (?, ?, 'enviado', ?, ?)", [$i['id'], $m['id'], agora(), utilizador()['id'] ?? null]);
        }
    }
    return $ok;
}

// Material aberto pelo formando (ligação da área do formando ou do email): regista o acesso e abre o material
function pagina_material(): void
{
    $u = utilizador();
    $i = linha_bd(SQL_INSCRICAO, [(int) ($_GET['i'] ?? 0)]);
    $m = linha_bd('SELECT * FROM materiais WHERE id = ?', [(int) ($_GET['m'] ?? 0)]);
    $deste = $i && $m && ((int) $m['curso_id'] === (int) $i['curso_id'] || ($m['formacao_id'] && (int) $m['formacao_id'] === (int) $i['formacao_id']));
    if (!$deste || (!eh_admin() && !mesmo_email((string) $i['email'], (string) $u['email']))) {
        http_response_code(404);
        pagina_erro('Material não encontrado', 'O material pedido não existe ou não é de uma formação sua.');
    }
    if (!eh_admin()) {
        executar("INSERT INTO materiais_acessos (inscricao_id, material_id, tipo, registado, utilizador_id) VALUES (?, ?, 'acedido', ?, ?)", [$i['id'], $m['id'], agora(), $u['id']]);
    }
    header('Location: ' . $m['url'], true, 302);
    exit;
}

function pagina_ficha_formando(array $f, array $erros_ficha): void
{
    sincronizar_estados();
    $fid = (int) $f['id'];
    $inscricoes = consulta('SELECT id FROM inscricoes WHERE formando_id = ? ORDER BY id DESC', [$fid]);
    $inscricoes = array_map(function ($x) { return linha_bd(SQL_INSCRICAO, [$x['id']]); }, $inscricoes);
    $diagnosticos = consulta('SELECT * FROM diagnosticos WHERE formando_id = ? ORDER BY id DESC', [$fid]);
    $cursos = opcoes_cursos();
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    [, $acesso] = estado_acesso_formando((string) $f['email']);
    $form = function (string $acao, string $conteudo, int $iid = 0, string $classe = 'formulario') use ($fid) {
        return '<form method="post" action="' . h(url('formando', ['id' => $fid])) . '" class="' . $classe . '">' . campo_csrf() . '<input type="hidden" name="acao" value="' . $acao . '">'
            . ($iid ? '<input type="hidden" name="inscricao" value="' . $iid . '">' : '') . $conteudo . '</form>';
    };

    // Cabeçalho
    $cab = '<div class="ficha-formando-topo">
      <dl class="factos-area">
        <div><dt>Email</dt><dd translate="no">' . ((string) $f['email'] !== '' ? '<a href="mailto:' . h($f['email']) . '">' . h($f['email']) . '</a>' : 'Sem email') . '</dd></div>
        <div><dt>Telefone</dt><dd translate="no">' . h((string) ($f['telefone'] ?: '')) . '</dd></div>
        <div><dt>Cliente</dt><dd>' . h((string) ($f['cliente'] ?: 'Sem cliente')) . '</dd></div>
        <div><dt>Consentimento (RGPD)</dt><dd>' . ($f['consentimento'] ? 'Dado em ' . h(data_hora($f['consentimento'], false)) : etiqueta('Por registar', 'info')) . '</dd></div>
        <div><dt>Estado</dt><dd>' . h(estado_formando($inscricoes, $diagnosticos)) . '</dd></div>
        <div><dt>Área do formando</dt><dd>' . h($acesso) . '</dd></div>
      </dl>
      <details><summary>Editar os dados</summary>' . $form('dados', '<div class="campos-2">'
        . campo('nome', 'Nome *', (string) $f['nome'], 'text', 'required maxlength="120"') . campo('email', 'Email', (string) $f['email'], 'email', 'maxlength="160"')
        . campo('telefone', 'Telefone', (string) $f['telefone'], 'tel', 'maxlength="30"') . campo_select('cliente_id', 'Cliente ou empresa', $clientes, (string) $f['cliente_id'], 'Sem cliente')
        . '</div>' . campo('notas', 'Notas internas', (string) $f['notas'], 'textarea', 'maxlength="2000" rows="2"')
        . (!$f['consentimento'] ? '<label class="opcao consentimento"><input type="checkbox" name="consentimento" value="1"> <span>Registar que o formando deu o consentimento para o tratamento dos dados (por exemplo, por escrito ou por email)</span></label>' : '')
        . '<div><button class="botao botao-secundario" type="submit">Guardar</button></div>') . '</details>
    </div>';

    // Diagnósticos
    $ld = '';
    foreach ($diagnosticos as $d) {
        [$est, $tipo] = estado_diagnostico($d);
        $ld .= '<tr><td><a href="' . h(url('diagnostico', ['id' => $d['id']])) . '">' . h(nomes_cursos_diagnostico($d)) . '</a></td><td>' . h(data_hora($d['enviado'], false)) . '</td><td>' . etiqueta($est, $tipo) . '</td>'
            . '<td>' . h((string) $d['nivel']) . '</td><td>' . h(['email' => 'Email', 'publico' => 'Site', 'pre-inscricao' => 'Pré-registo antigo'][$d['origem'] ?? 'email'] ?? '') . '</td></tr>';
    }
    $diag = '<h2 class="espaco-topo" id="diagnosticos">Diagnósticos</h2>'
        . ($ld ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Curso</th><th>Enviado</th><th>Estado</th><th>Nível</th><th>Origem</th></tr></thead><tbody>' . $ld . '</tbody></table></div>' : '<p class="texto-suave">Ainda não foi enviado nenhum diagnóstico.</p>')
        . ((string) $f['email'] !== '' ? $form('enviar_diagnostico', '<div class="campos-2">' . campo_select('curso_id', 'Curso', $cursos, '', 'Escolha o curso') . '</div>'
            . '<p class="ajuda">O formando recebe por email a ligação pessoal (válida 30 dias); quando responder, fica inscrito neste curso e recebe o email da inscrição. Um diagnóstico ainda por responder é reaproveitado, com uma ligação nova.</p>'
            . '<div><button class="botao botao-secundario" type="submit">Enviar o diagnóstico</button></div>', 0, 'formulario espaco-topo-curto') : '');

    // Inscrições: um cartão por inscrição
    $cartoes = '';
    foreach ($inscricoes as $i) {
        $cartoes .= cartao_inscricao($i, $form);
    }
    $nova = $form('nova_inscricao', '<div class="campos-2">' . campo_select('curso_id', 'Inscrever noutro curso, sem diagnóstico', $cursos, '', 'Escolha o curso') . '</div>'
        . '<label class="opcao consentimento"><input type="checkbox" name="email_inscricao" value="1" checked> <span>Enviar o email da inscrição (ligação de ativação e pedido dos dados para o certificado)</span></label>'
        . '<div><button class="botao botao-secundario" type="submit">Criar a inscrição</button></div>', 0, 'formulario espaco-topo-curto');

    pagina_area('formandos', (string) $f['nome'], $cab . $diag
        . '<h2 class="espaco-topo">Inscrições (' . count($inscricoes) . ')</h2>' . ($cartoes ?: '<p class="texto-suave">Ainda sem inscrições: quando responder ao diagnóstico, fica inscrito no curso indicado.</p>') . $nova
        . ((string) $f['email'] !== '' ? ficha_certificado_admin_html((string) $f['email'], url('formando', ['id' => $fid]), ...$erros_ficha) : '')
        . '<p class="espaco-topo"><a href="' . h(url('formandos')) . '">Voltar aos formandos</a></p>', 'Formando');
}

// Cartão de uma inscrição: as 10 etapas (estado e detalhe) e as ações de cada uma
function cartao_inscricao(array $i, callable $form): string
{
    $iid = (int) $i['id'];
    $ctx = contexto_inscricao($i);
    $et = etapas_inscricao($i, $ctx);
    $n = progresso($et);
    $linha = '';
    foreach (ETAPAS as $k => $nome) {
        $e = $et[$k];
        $linha .= '<li class="fluxo-etapa fluxo-etapa-' . h($e['estado']) . '"><span class="fluxo-etapa-n" aria-hidden="true">' . $k . '</span><span class="fluxo-etapa-texto"><strong>' . h($nome) . '</strong>'
            . '<span class="fluxo-etapa-estado">' . h(ESTADOS_ETAPA[$e['estado']]) . '</span><small>' . h($e['detalhe']) . '</small></span></li>';
    }
    $acoes = [];
    if ((string) $i['email'] !== '') {
        $acoes[] = ['Email de confirmação', $form('reenviar_email', '<p class="ajuda mt-0">Reenvia o email da inscrição: nome de utilizador, ligação de ativação nova (se a conta ainda não estiver ativa) e pedido dos dados para o certificado.</p><div><button class="botao botao-secundario botao-pequeno" type="submit">' . (empty($i['email_inscricao']) ? 'Enviar o email da inscrição' : 'Reenviar o email da inscrição') . '</button></div>', $iid)];
        if (!ficha_completa($ctx['ficha'])) {
            $acoes[] = ['Dados do certificado', $form('pedir_dados', '<p class="ajuda mt-0">Pede por email ao formando que complete a ficha na área do formando. Também pode preenchê-la aqui, em <a href="#certificado">Dados para o certificado</a>.</p><div><button class="botao botao-secundario botao-pequeno" type="submit">Pedir os dados por email</button></div>', $iid)];
        }
    }
    if (!in_array($i['estado'], ['encerrado', 'certificado'], true)) {
        $turmas = [];
        foreach (consulta("SELECT * FROM formacoes WHERE curso_id = ? AND estado NOT IN ('cancelada', 'encerrada') ORDER BY inicio", [(int) $i['curso_id']]) as $t) {
            $livres = (int) $t['vagas'] - inscritos((int) $t['id']);
            $turmas[$t['id']] = horario($t) . ' · ' . modalidade_e_local($t) . ' · ' . ($livres > 0 ? $livres . ' vaga(s)' : 'sem vagas');
        }
        $acoes[] = ['Turma', $turmas ? $form('turma', '<div class="campos-2">' . campo_select('formacao_id', 'Turma do ' . h(curso_curto((string) ($ctx['curso']['nome'] ?? ''))), $turmas, (string) ($i['formacao_id'] ?? ''), 'Escolha a turma') . '</div>'
            . '<label class="opcao consentimento"><input type="checkbox" name="avisar" value="1" checked> <span>Avisar o formando por email, com as datas, o local e a ligação das sessões</span></label><div><button class="botao botao-secundario botao-pequeno" type="submit">Guardar a turma</button></div>', $iid)
            : '<p class="texto-suave mb-0">Este curso ainda não tem turmas abertas. <a href="' . h(url('formacao', ['curso' => $i['curso_id']])) . '">Agendar uma turma no Calendário</a>.</p>'];
    }
    if ($ctx['materiais']) {
        $mat = '<ul class="lista-datas">' . implode('', array_map(function ($m) use ($iid) {
            return '<li><a href="' . h(url('material', ['i' => $iid, 'm' => $m['id']])) . '" target="_blank" rel="noopener">' . h($m['titulo']) . '</a> <span class="texto-suave">(' . h(TIPOS_MATERIAL[$m['tipo']] ?? $m['tipo']) . ')</span></li>';
        }, $ctx['materiais'])) . '</ul>';
        $hist = $ctx['acessos'] ? '<p class="texto-suave">' . implode('<br>', array_map(function ($a) {
            return h((['enviado' => 'Enviado por email', 'acedido' => 'Aberto pelo formando', 'entregue' => 'Entrega registada'][$a['tipo']] ?? $a['tipo']) . ': ' . ($a['titulo'] ?: 'todos os materiais') . ', ' . data_hora($a['registado']));
        }, array_slice($ctx['acessos'], 0, 6))) . '</p>' : '';
        $acoes[] = ['Manual e áudio', $mat . $hist . ((string) $i['email'] !== '' ? $form('materiais_enviar', '<div><button class="botao botao-secundario botao-pequeno" type="submit">Enviar os materiais por email</button></div>', $iid) : '')
            . $form('material_entregue', '<div class="campos-2">' . campo_select('material', 'Registar a entrega (por exemplo, em papel ou na sessão)', array_column($ctx['materiais'], 'titulo', 'id'), '', 'Todos os materiais') . '</div><div><button class="botao-texto" type="submit">Registar a entrega</button></div>', $iid, 'formulario espaco-topo-curto')];
    }
    if ($ctx['sessoes']) {
        $linhas = '';
        foreach ($ctx['sessoes'] as $s) {
            $p = $ctx['presencas'][$s['n']] ?? null;
            $linhas .= '<tr><td class="c-sessao"><input type="checkbox" name="p[' . (int) $s['n'] . ']" value="1"' . ($p ? ' checked' : '') . ' aria-label="Presente na ' . h($s['nome'] ?: 'sessão') . '"></td><td>' . h($s['nome'] ?: 'Sessão única') . '</td><td>' . h(dia_sessao($s['inicio'], false)) . '<br><span class="texto-suave">' . h(hora_sessao($s)) . '</span></td><td>' . ($p ? presenca_html($p) : (strtotime($s['inicio']) <= time() ? etiqueta('Sem registo') : '<span class="texto-suave">Por realizar</span>')) . '</td></tr>';
        }
        $acoes[] = ['Assiduidade', '<p class="mb-0"><strong>' . count($ctx['presencas']) . ' de ' . count($ctx['sessoes']) . ' sessões (' . $ctx['percentagem'] . '%)</strong></p>'
            . $form('presencas', '<div class="conteudo tabela-rolante"><table class="tabela-presencas grelha-presencas"><thead><tr><th class="c-sessao">Presente</th><th>Sessão</th><th>Data</th><th>Registo</th></tr></thead><tbody>' . $linhas . '</tbody></table></div><div><button class="botao botao-secundario botao-pequeno" type="submit">Guardar presenças</button></div>', $iid)
            . $form('validar_assiduidade', '<label class="opcao consentimento"><input type="checkbox" name="validada" value="1"' . (!empty($i['assiduidade_validada']) ? ' checked' : '') . '> <span>Assiduidade validada pelo formador (decisão do formador; necessária para o certificado)</span></label><div><button class="botao-texto" type="submit">Guardar a validação</button></div>', $iid, 'formulario espaco-topo-curto')];
    }
    $bloq = bloqueios_certificado($i, $ctx);
    $cert = $ctx['certificado'];
    $acoes[] = ['Avaliação e certificado', $form('avaliacao', '<div class="campos-2">' . campo_select('avaliacao', 'Avaliação', AVALIACOES, (string) $i['avaliacao'])
        . campo('avaliacao_nota', 'Nota (opcional)', (string) ($i['avaliacao_nota'] ?? ''), 'text', 'maxlength="20"') . '</div>' . campo('avaliacao_obs', 'Observações (opcional)', (string) ($i['avaliacao_obs'] ?? ''), 'textarea', 'maxlength="1000" rows="2"')
        . '<div><button class="botao botao-secundario botao-pequeno" type="submit">Guardar a avaliação</button></div>', $iid)
        . ($cert ? '<p class="espaco-topo-curto">Certificado <a href="' . h(url('certificado', ['i' => $iid])) . '" target="_blank" rel="noopener">n.º ' . h(numero_certificado($cert)) . '</a>, emitido em ' . h(data_hora($cert['emitido'], false)) . ($cert['enviado'] ? ', enviado em ' . h(data_hora($cert['enviado'], false)) : ', por enviar') . '.</p>' : '')
        . ($bloq ? '<div class="aviso aviso-info espaco-topo-curto">Certificado bloqueado: ' . h(implode('; ', $bloq)) . '.</div>'
            : $form('emitir', '<label class="opcao consentimento"><input type="checkbox" name="enviar" value="1" checked> <span>Enviar o certificado por email ao formando</span></label><div><button class="botao botao-pequeno" type="submit">' . ($cert ? 'Emitir de novo (mantém o número)' : 'Emitir o certificado') . '</button></div>', $iid, 'formulario espaco-topo-curto'))];
    $estados = ['inscrito' => 'Inscrito (reabrir)', 'encerrado' => 'Encerrado', 'desistiu' => 'Desistiu', 'nao_concluido' => 'Não concluído'];
    $acoes[] = ['Encerramento e estado', $form('estado', '<div class="campos-2">' . campo_select('estado', 'Mudar o estado para', $estados, '', 'Escolha o estado') . '</div>'
        . '<p class="ajuda">Os outros estados mudam sozinhos: Em formação quando a turma começa, Concluído quando termina com a avaliação "Aprovado", Certificado ao emitir o certificado. Encerrar a turma (no Calendário) encerra todas as inscrições dessa turma.</p>'
        . '<div><button class="botao botao-secundario botao-pequeno" type="submit">Mudar o estado</button></div>', $iid)];
    $paineis = '';
    foreach ($acoes as [$titulo, $conteudo]) {
        $paineis .= '<details class="programa"><summary>' . h($titulo) . '</summary><div class="programa-corpo">' . $conteudo . '</div></details>';
    }
    return '<section class="cartao-inscricao" id="inscricao-' . $iid . '">
      <header><h3 class="mt-0">' . h((string) ($ctx['curso']['nome'] ?? $i['titulo'])) . ' ' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</h3>
        <span class="progresso" style="--p:' . ($n * 10) . '%">' . $n . '/10</span>
        <p class="texto-suave mb-0">' . ($ctx['turma'] ? 'Turma: <a href="' . h(url('formacao', ['id' => $ctx['turma']['id']])) . '">' . h(horario($ctx['turma'])) . '</a>' : 'Turma por escolher') . ' · <a href="' . h(url('inscricao', ['id' => $iid])) . '">Ficha da inscrição</a></p></header>
      <ol class="fluxo-etapas">' . $linha . '</ol>
      <div class="acoes-etapas">' . $paineis . '</div>
    </section>';
}

/* Materiais do curso ou da turma (página do curso e página da turma) */

// Ações "material_novo" e "material_apagar"; devolve true se tratou o pedido
function tratar_materiais(?int $curso_id, ?int $formacao_id): bool
{
    $acao = (string) ($_POST['acao'] ?? '');
    if ($acao === 'material_novo') {
        $titulo = texto('material_titulo', 160);
        $url_m = texto('material_url', 500);
        $tipo = isset(TIPOS_MATERIAL[$_POST['material_tipo'] ?? '']) ? $_POST['material_tipo'] : 'manual';
        if ($titulo === '' || !filter_var($url_m, FILTER_VALIDATE_URL) || !preg_match('#^(https://|/)#', $url_m)) {
            aviso('Indique o título e um endereço completo do material (começado por https://).', 'erro');
        } else {
            executar('INSERT INTO materiais (curso_id, formacao_id, titulo, tipo, url, ordem, criado) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$formacao_id ? null : $curso_id, $formacao_id, $titulo, $tipo, $url_m, (int) (linha_bd('SELECT COUNT(*) AS n FROM materiais')['n'] ?? 0), agora()]);
            aviso('Material acrescentado.');
        }
        return true;
    }
    if ($acao === 'material_apagar') {
        $id = (int) ($_POST['material'] ?? 0);
        executar('DELETE FROM materiais WHERE id = ? AND (curso_id = ? OR formacao_id = ?)', [$id, (int) $curso_id, (int) $formacao_id]);
        aviso('Material retirado. Os registos de envio e de acesso já feitos mantêm-se na ficha do formando.');
        return true;
    }
    return false;
}

function materiais_html(?int $curso_id, ?int $formacao_id): string
{
    $lista = $formacao_id ? consulta('SELECT * FROM materiais WHERE formacao_id = ? ORDER BY ordem, id', [$formacao_id]) : consulta('SELECT * FROM materiais WHERE curso_id = ? ORDER BY ordem, id', [(int) $curso_id]);
    $linhas = '';
    foreach ($lista as $m) {
        $linhas .= '<tr><td><a href="' . h($m['url']) . '" target="_blank" rel="noopener">' . h($m['titulo']) . '</a></td><td>' . h(TIPOS_MATERIAL[$m['tipo']] ?? $m['tipo']) . '</td><td>'
            . '<form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="material_apagar"><input type="hidden" name="material" value="' . (int) $m['id'] . '"><button class="botao-texto" type="submit">Retirar</button></form></td></tr>';
    }
    return '<h2 class="espaco-topo" id="materiais">Materiais ' . ($formacao_id ? 'desta turma' : 'do curso') . '</h2>
      <p class="texto-suave">' . ($formacao_id ? 'Só para os formandos desta turma; os materiais do curso aparecem também.' : 'Para todos os formandos do curso, em qualquer turma.') . ' Os formandos abrem-nos na área do formando ou no email "Materiais do curso"; o envio e cada acesso ficam registados na ficha do formando (etapa Manual e áudio).</p>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Material</th><th>Tipo</th><th><span class="visualmente-oculto">Ações</span></th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '') . '
      <form method="post" class="formulario espaco-topo-curto">' . campo_csrf() . '<input type="hidden" name="acao" value="material_novo">
        <div class="campos-2">' . campo('material_titulo', 'Título *', '', 'text', 'required maxlength="160" placeholder="Por exemplo, Manual do Formando em áudio"')
        . campo('material_url', 'Endereço *', '', 'url', 'required maxlength="500" placeholder="https://www.gadgetmetodo.pt/formacoes/manual-curso-1/"')
        . campo_select('material_tipo', 'Tipo', TIPOS_MATERIAL, 'manual') . '</div>
        <div><button class="botao botao-secundario" type="submit">Acrescentar material</button></div>
      </form>';
}

/* Encerramento da turma (etapa 10) */

function encerrar_turma(int $formacao_id): int
{
    executar("UPDATE formacoes SET estado = 'encerrada', encerrada = ? WHERE id = ?", [agora(), $formacao_id]);
    $st = bd()->prepare("UPDATE inscricoes SET estado = 'encerrado', encerrada = ? WHERE formacao_id = ? AND estado IN ('inscrito', 'em_formacao', 'concluido', 'certificado')");
    $st->execute([agora(), $formacao_id]);
    return $st->rowCount();
}

function encerramento_turma_html(array $t): string
{
    if ($t['estado'] === 'encerrada') {
        return '<h2 class="espaco-topo" id="encerramento">Encerramento</h2><p class="texto-suave">Turma encerrada' . (!empty($t['encerrada']) ? ' em ' . h(data_hora($t['encerrada'])) : '') . '. As inscrições desta turma estão encerradas.</p>';
    }
    $abertas = (int) (linha_bd("SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ? AND estado IN ('inscrito', 'em_formacao', 'concluido', 'certificado')", [$t['id']])['n'] ?? 0);
    $sem_cert = (int) (linha_bd("SELECT COUNT(*) AS n FROM inscricoes i WHERE i.formacao_id = ? AND i.estado IN ('inscrito', 'em_formacao', 'concluido') AND NOT EXISTS (SELECT 1 FROM certificados c WHERE c.inscricao_id = i.id)", [$t['id']])['n'] ?? 0);
    return '<h2 class="espaco-topo" id="encerramento">Encerramento</h2>
      <p class="texto-suave">Encerrar a turma passa as ' . $abertas . ' inscrição(ões) em curso para Encerrado (as que desistiram ou não concluíram ficam como estão) e tira a turma das escolhas de turma. ' . ($sem_cert ? 'Atenção: ' . $sem_cert . ' inscrição(ões) ainda sem certificado.' : '') . '</p>
      <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="encerrar_turma">
        <label class="opcao consentimento"><input type="checkbox" name="confirmar" value="1" required> <span>Confirmo que quero encerrar esta turma e as suas inscrições</span></label>
        <div><button class="botao botao-secundario" type="submit">Encerrar a turma</button></div>
      </form>';
}
