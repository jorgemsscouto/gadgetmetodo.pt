<?php
// Calendário mensal da administração (uma grelha de segunda a domingo com as sessões de cada dia) e pré-inscrições
// associadas às edições: enquanto um curso não tem edições agendadas, as pré-inscrições ficam só em Inscrições; ao
// agendar uma edição, as pré-inscrições desse curso por converter passam a aparecer nela, para inscrever ou convidar.

declare(strict_types=1);

const MESES_CALENDARIO = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

// "2026-11" a partir do pedido (o mês atual quando não é válido)
function mes_pedido(): string
{
    $m = is_string($_GET['mes'] ?? null) ? $_GET['mes'] : '';
    return preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $m) ? $m : date('Y-m');
}

// "Curso 1" a partir de "Curso 1: Iniciação à IA Generativa no Trabalho"; outros títulos ficam inteiros
function rotulo_curto(string $titulo): string
{
    return preg_match('/^(Curso \d+)\s*:/u', $titulo, $m) ? $m[1] : $titulo;
}

// Sessões de todas as edições entre duas datas (as edições sem datas por sessão contam como uma sessão)
function sessoes_entre(string $de, string $ate): array
{
    return consulta("SELECT s.inicio, s.fim, s.ordem AS n, (SELECT COUNT(*) FROM sessoes s2 WHERE s2.formacao_id = f.id) AS total, f.id AS formacao_id, f.titulo, f.estado, f.curso_id, f.modalidade
        FROM sessoes s JOIN formacoes f ON f.id = s.formacao_id WHERE s.inicio BETWEEN ? AND ?
        UNION ALL
        SELECT f.inicio, f.fim, 1, 1, f.id, f.titulo, f.estado, f.curso_id, f.modalidade FROM formacoes f
        WHERE NOT EXISTS (SELECT 1 FROM sessoes s WHERE s.formacao_id = f.id) AND f.inicio BETWEEN ? AND ?
        ORDER BY 1", [$de, $ate, $de, $ate]);
}

// Grelha do mês, com navegação para o mês anterior e o seguinte
function calendario_mensal(string $mes): string
{
    $t0 = strtotime($mes . '-01');
    $ultimo = strtotime(date('Y-m-t', $t0));
    $inicio = strtotime('-' . ((int) date('N', $t0) - 1) . ' days', $t0);
    $fim = strtotime('+' . (7 - (int) date('N', $ultimo)) . ' days', $ultimo);
    $por_dia = [];
    foreach (sessoes_entre(date('Y-m-d 00:00:00', $inicio), date('Y-m-d 23:59:59', $fim)) as $s) {
        $por_dia[substr($s['inicio'], 0, 10)][] = $s;
    }
    $cursos = array_column(consulta('SELECT id, chave FROM cursos'), 'chave', 'id');
    $semana = ['segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado', 'domingo'];
    $corpo = '';
    $n_mes = 0;
    for ($d = $inicio; $d <= $fim; $d = strtotime('+1 day', $d)) {
        $dow = (int) date('N', $d);
        if ($dow === 1) {
            $corpo .= '<tr>';
        }
        $dia = date('Y-m-d', $d);
        $fora = date('Y-m', $d) !== $mes;
        $itens = '';
        foreach ($por_dia[$dia] ?? [] as $s) {
            $n_mes += $fora ? 0 : 1;
            $chave = (string) ($cursos[$s['curso_id']] ?? '');
            $cor = preg_match('/-(\d+)$/', $chave, $m) ? 'cal-c' . $m[1] : ($chave !== '' ? 'cal-outro' : 'cal-avulsa');
            $sessao = (int) $s['total'] > 1 ? ' · S' . (int) $s['n'] . '/' . (int) $s['total'] : '';
            $itens .= '<li><a class="cal-sessao ' . $cor . ($s['estado'] === 'cancelada' ? ' cal-cancelada' : '') . '" href="' . h(url('formacao', ['id' => $s['formacao_id']])) . '" title="' . h($s['titulo'] . ($s['estado'] === 'cancelada' ? ' (cancelada)' : '')) . '">'
                . '<span class="cal-hora">' . h(date('H:i', strtotime($s['inicio']))) . '</span> ' . h(rotulo_curto((string) $s['titulo']) . $sessao) . '</a></li>';
        }
        $classes = array_filter([$fora ? 'cal-fora' : '', $dia === date('Y-m-d') ? 'cal-hoje' : '', $itens === '' ? 'cal-vazio' : '']);
        $corpo .= '<td' . ($classes ? ' class="' . implode(' ', $classes) . '"' : '') . '><span class="cal-dia"><span class="cal-semana">' . $semana[$dow - 1] . ', </span>' . (int) date('j', $d) . '</span>'
            . ($itens !== '' ? '<ul>' . $itens . '</ul>' : '') . '</td>';
        if ($dow === 7) {
            $corpo .= '</tr>';
        }
    }
    $anterior = date('Y-m', strtotime('-1 month', $t0));
    $seguinte = date('Y-m', strtotime('+1 month', $t0));
    $nome = function (string $m) { return MESES_CALENDARIO[(int) substr($m, 5, 2)]; };
    $curtos = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    return '<section class="cal-mes" aria-labelledby="cal-titulo">
      <div class="cal-cabecalho">
        <a class="cal-seta" href="' . h(url('calendario', ['mes' => $anterior])) . '" aria-label="Mês anterior: ' . h($nome($anterior)) . '">&lsaquo;</a>
        <h2 id="cal-titulo">' . h($nome($mes)) . ' de ' . h(substr($mes, 0, 4)) . '</h2>
        ' . ($mes !== date('Y-m') ? '<a class="cal-hoje-ligacao" href="' . h(url('calendario')) . '">Hoje</a>' : '') . '
        <a class="cal-seta" href="' . h(url('calendario', ['mes' => $seguinte])) . '" aria-label="Mês seguinte: ' . h($nome($seguinte)) . '">&rsaquo;</a>
      </div>
      <div class="tabela-rolante"><table class="cal-tabela"><thead><tr>' . implode('', array_map(function ($d, $c) { return '<th scope="col"><abbr title="' . h(ucfirst($d)) . '">' . h($c) . '</abbr></th>'; }, $semana, $curtos)) . '</tr></thead><tbody>' . $corpo . '</tbody></table></div>
      <p class="cal-nota">' . ($n_mes ? $n_mes . ' sessão(ões) neste mês.' : 'Sem sessões neste mês.') . ' Cada entrada é uma sessão (S2/6: sessão 2 de 6); abra-a para gerir a edição e os inscritos.</p>
    </section>';
}

/* Pré-inscrições associadas às edições */

// Edição futura e não cancelada a que as pré-inscrições de um curso ficam associadas (a mais próxima)
function proxima_edicao_curso(int $curso_id): ?array
{
    return linha_bd("SELECT * FROM formacoes WHERE curso_id = ? AND estado != 'cancelada' AND inicio >= ? ORDER BY inicio LIMIT 1", [$curso_id, date('Y-m-d 00:00:00')]);
}

// Associa à edição as pré-inscrições do curso por converter (novas ou com convite) sem edição futura associada.
// Devolve quantas foram associadas.
function associar_preinscricoes(int $formacao_id): int
{
    $f = linha_bd("SELECT * FROM formacoes WHERE id = ? AND estado != 'cancelada'", [$formacao_id]);
    if (!$f || empty($f['curso_id']) || strtotime($f['inicio']) < strtotime('today')) {
        return 0;
    }
    $st = bd()->prepare("UPDATE preinscricoes SET formacao_id = ? WHERE curso_id = ? AND estado IN ('nova', 'convidada')
        AND (formacao_id IS NULL OR formacao_id NOT IN (SELECT id FROM formacoes WHERE estado != 'cancelada' AND inicio >= ?))");
    $st->execute([$formacao_id, $f['curso_id'], date('Y-m-d 00:00:00')]);
    return $st->rowCount();
}

// Uma vez: associa as pré-inscrições existentes à próxima edição do respetivo curso, se já houver
function associar_preinscricoes_existentes(PDO $pdo): void
{
    if (definicao($pdo, 'preinscricoes_edicoes') !== null) {
        return;
    }
    definir($pdo, 'preinscricoes_edicoes', date('Y-m-d H:i:s'));
    foreach ($pdo->query("SELECT DISTINCT curso_id FROM preinscricoes WHERE estado IN ('nova', 'convidada')")->fetchAll(PDO::FETCH_COLUMN) as $curso_id) {
        if ($f = proxima_edicao_curso((int) $curso_id)) {
            associar_preinscricoes((int) $f['id']);
        }
    }
}

// Pré-inscrições associadas a uma edição (por converter)
function preinscritos_edicao(int $formacao_id): array
{
    return consulta("SELECT * FROM preinscricoes WHERE formacao_id = ? AND estado IN ('nova', 'convidada') ORDER BY estado = 'nova' DESC, criado", [$formacao_id]);
}

// Ações da edição: convite a uma pré-inscrição ou a todas as novas
function tratar_preinscritos_edicao(array $f): void
{
    $acao = (string) ($_POST['acao'] ?? '');
    $c = curso((int) $f['curso_id']);
    $todas = preinscritos_edicao((int) $f['id']);
    $lista = $acao === 'pre_convidar_edicao'
        ? array_values(array_filter($todas, function ($p) { return (int) $p['id'] === (int) ($_POST['pre_id'] ?? 0); }))
        : array_values(array_filter($todas, function ($p) { return $p['estado'] === 'nova'; }));
    if (!$c || !$lista) {
        aviso('Não há pré-inscrições para convidar.', 'erro');
    } elseif ($f['estado'] !== 'aberta') {
        aviso('Abra as inscrições desta edição antes de enviar convites: o convite leva o candidato às datas e à inscrição.', 'erro');
    } else {
        $enviados = 0;
        foreach ($lista as $p) {
            if (convite_curso($c, (string) $p['nome'], (string) $p['email'], '', 'convite_preinscricao')) {
                executar("UPDATE preinscricoes SET estado = 'convidada' WHERE id = ?", [$p['id']]);
                $enviados++;
            }
        }
        aviso_envio($enviados > 0, count($lista) === 1 ? 'Convite enviado para ' . h((string) $lista[0]['email']) . '.' : $enviados . ' de ' . count($lista) . ' convites enviados.');
    }
    redirecionar('formacao', ['id' => $f['id']], 'preinscritos');
}

// Bloco "Pré-inscritos" da edição: candidatos do curso por converter, com Inscrever e Enviar convite
function preinscritos_edicao_html(array $f): string
{
    $lista = preinscritos_edicao((int) $f['id']);
    if (!$lista) {
        return '';
    }
    $c = curso((int) $f['curso_id']);
    $aberta = $f['estado'] === 'aberta';
    $novas = count(array_filter($lista, function ($p) { return $p['estado'] === 'nova'; }));
    $linhas = '';
    foreach ($lista as $p) {
        $linhas .= '<tr><td><a href="' . h(url('preinscricao', ['id' => $p['id']])) . '"><strong>' . h($p['nome']) . '</strong></a>' . ($p['tipo_cliente'] === 'particular' ? '<br>' . etiqueta('Particular') : ($p['empresa'] ? '<br>' . h($p['empresa']) : ''))
            . ((int) $p['formandos'] > 1 ? '<br><span class="texto-suave">' . (int) $p['formandos'] . ' formandos previstos</span>' : '') . '</td>'
            . '<td>' . h($p['email']) . ($p['telefone'] ? '<br><span class="texto-suave">' . h($p['telefone']) . '</span>' : '') . '</td>'
            . '<td>' . ($c ? diagnostico_preinscricao($p, $c) : '') . '</td>'
            . '<td>' . etiqueta(ESTADOS_PREINSCRICAO[$p['estado']] ?? $p['estado'], $p['estado'] === 'nova' ? 'pre' : '') . '<br><span class="texto-suave">' . h(data_hora($p['criado'], false)) . '</span></td>'
            . '<td class="acoes-celula"><a class="botao botao-pequeno" href="' . h(url('preinscricao', ['id' => $p['id'], 'sessao' => $f['id']])) . '#inscrever">Inscrever</a>'
            . ($aberta ? '<form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="pre_convidar_edicao"><input type="hidden" name="pre_id" value="' . (int) $p['id'] . '"><button class="botao botao-secundario botao-pequeno" type="submit">' . ($p['estado'] === 'convidada' ? 'Reenviar convite' : 'Enviar convite') . '</button></form>' : '')
            . botao_apagar('preinscricao', (int) $p['id'], 'Apagar', 'botao botao-secundario botao-pequeno botao-apagar') . '</td></tr>';
    }
    return '<h2 class="espaco-topo" id="preinscritos">Pré-inscritos (' . count($lista) . ')</h2>
      <p class="texto-suave">Pré-inscrições ' . h(curso_com_artigo((string) ($c['nome'] ?? $f['titulo']))) . ' associadas a esta edição quando foi agendada. "Inscrever" converte a pré-inscrição numa inscrição nesta edição; "Enviar convite" leva o candidato às datas e à inscrição no site.' . ($aberta ? '' : ' Para enviar convites, abra as inscrições desta edição.') . '</p>'
      . ($aberta && $novas > 1 ? '<form method="post" class="espaco-topo-curto">' . campo_csrf() . '<input type="hidden" name="acao" value="pre_convidar_todas_edicao"><button class="botao" type="submit">Enviar convite às ' . $novas . ' pré-inscrições novas</button></form>' : '')
      . '<div class="conteudo tabela-rolante"><table><thead><tr><th>Candidato</th><th>Contacto</th><th>Diagnóstico</th><th>Estado</th><th>Ações</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>';
}
