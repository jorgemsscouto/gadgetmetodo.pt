<?php
// Calendário mensal da administração: uma grelha de segunda a domingo com as sessões de cada dia.

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
// Navegação do mês (mês anterior, título, "Hoje" e mês seguinte). Na página do calendário fica na barra de cima,
// na mesma linha do seletor Mês | Lista e dos botões
function cabecalho_calendario(string $mes): string
{
    $t0 = strtotime($mes . '-01');
    $anterior = date('Y-m', strtotime('-1 month', $t0));
    $seguinte = date('Y-m', strtotime('+1 month', $t0));
    $nome = function (string $m) { return MESES_CALENDARIO[(int) substr($m, 5, 2)]; };
    return '<div class="cal-cabecalho">
        <a class="cal-seta" href="' . h(url('calendario', ['mes' => $anterior])) . '" aria-label="Mês anterior: ' . h($nome($anterior)) . '">&lsaquo;</a>
        <h2 id="cal-titulo">' . h($nome($mes)) . ' de ' . h(substr($mes, 0, 4)) . '</h2>
        ' . ($mes !== date('Y-m') ? '<a class="cal-hoje-ligacao" href="' . h(url('calendario')) . '">Hoje</a>' : '') . '
        <a class="cal-seta" href="' . h(url('calendario', ['mes' => $seguinte])) . '" aria-label="Mês seguinte: ' . h($nome($seguinte)) . '">&rsaquo;</a>
      </div>';
}

function calendario_mensal(string $mes, bool $com_cabecalho = true): string
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
    $curtos = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    return '<section class="cal-mes" aria-labelledby="cal-titulo">
      ' . ($com_cabecalho ? cabecalho_calendario($mes) : '') . '
      <div class="tabela-rolante"><table class="cal-tabela"><thead><tr>' . implode('', array_map(function ($d, $c) { return '<th scope="col"><abbr title="' . h(ucfirst($d)) . '">' . h($c) . '</abbr></th>'; }, $semana, $curtos)) . '</tr></thead><tbody>' . $corpo . '</tbody></table></div>
      <p class="cal-nota">' . ($n_mes ? $n_mes . ' sessão(ões) neste mês.' : 'Sem sessões neste mês.') . ' Cada entrada é uma sessão (S2/6: sessão 2 de 6); abra-a para gerir a edição e os inscritos.</p>
    </section>';
}
