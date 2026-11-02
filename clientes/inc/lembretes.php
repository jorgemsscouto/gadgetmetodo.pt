<?php
// Lembretes das sessões aos formandos com inscrição confirmada: o botão "Enviar lembrete" na lista de inscritos de uma
// edição (para usar na véspera) e o envio automático cerca de 1 hora antes de cada sessão. O envio automático corre na
// tarefa agendada do alojamento (clientes/lembretes.php, com a chave mostrada em Configuração) e, como reserva, nas
// visitas à área reservada e às páginas de formação, no máximo de 5 em 5 minutos. Cada envio fica registado na tabela
// "lembretes"; o automático é único por sessão, mesmo que duas verificações corram ao mesmo tempo.

declare(strict_types=1);

require_once __DIR__ . '/nucleo.php';
require_once __DIR__ . '/paginas-cliente.php';

const LEMBRETE_ANTECEDENCIA = 3600; // segundos antes do início da sessão
const LEMBRETES_INTERVALO_VISITAS = 300; // segundos entre verificações nas visitas

// Sessões de uma edição, pela ordem: número, total, início, fim e "Sessão 2 de 6: tema" (vazio numa formação de uma só data)
function sessoes_edicao(array $f): array
{
    $lista = sessoes_formacao((int) $f['id']) ?: [['inicio' => $f['inicio'], 'fim' => $f['fim'] ?? '', 'modulo_id' => $f['modulo_id'] ?? null]];
    $total = count($lista);
    $r = [];
    foreach ($lista as $n => $s) {
        $m = !empty($s['modulo_id']) ? linha_bd('SELECT nome FROM modulos WHERE id = ?', [(int) $s['modulo_id']]) : null;
        $nome = $total > 1 ? 'Sessão ' . ($n + 1) . ' de ' . $total . ($m ? ': ' . $m['nome'] : '') : '';
        $r[] = ['n' => $n + 1, 'total' => $total, 'inicio' => (string) $s['inicio'], 'fim' => (string) ($s['fim'] ?? ''), 'nome' => $nome];
    }
    return $r;
}

// Próxima sessão que ainda não começou (null quando a edição já decorreu)
function proxima_sessao(array $f): ?array
{
    foreach (sessoes_edicao($f) as $s) {
        if (strtotime($s['inicio']) > time()) {
            return $s;
        }
    }
    return null;
}

// "amanhã, terça-feira, 3 de novembro de 2026" ("hoje" no próprio dia; sem prefixo nos outros dias)
function dia_sessao(string $inicio, bool $relativo = true): string
{
    $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    $t = strtotime($inicio);
    $dia = date('Y-m-d', $t);
    $prefixo = !$relativo ? '' : ($dia === date('Y-m-d') ? 'hoje, ' : ($dia === date('Y-m-d', strtotime('tomorrow')) ? 'amanhã, ' : ''));
    return $prefixo . $dias[(int) date('w', $t)] . ', ' . data_extensa($inicio);
}

// "das 18:30 às 19:30" ou "às 18:30"
function hora_lembrete(array $s): string
{
    return !empty($s['fim']) ? 'das ' . hora_sessao($s) : 'às ' . date('H:i', strtotime($s['inicio']));
}

// Dados da sessão no email: formando (quando o email vai para quem o inscreveu), sessão, data, horário, local e ligação
function bloco_lembrete(array $i, array $s, bool $para_contacto): array
{
    $linhas = array_filter([
        'Formando' => $para_contacto ? (string) $i['formando'] : '',
        'Sessão' => $s['nome'],
        'Data' => dia_sessao($s['inicio'], false),
        'Horário' => hora_sessao($s),
        'Local' => modalidade_e_local($i, ': '),
    ], 'strlen');
    $html = '';
    $texto = '';
    foreach ($linhas as $rotulo => $valor) {
        $html .= '<p style="margin:0 0 4px;"><strong>' . h($rotulo) . ':</strong> ' . h($valor) . '</p>';
        $texto .= $rotulo . ': ' . $valor . "\n";
    }
    [$reuniao_html, $reuniao_texto] = bloco_reuniao($i);
    return ['<div style="margin:0 0 14px;">' . $html . '</div>' . $reuniao_html, trim($texto . $reuniao_texto)];
}

// Envia o lembrete de uma sessão a cada inscrição confirmada da edição: ao formando ou, sem email do formando, a quem o
// inscreveu. Devolve [enviados, falhados].
function enviar_lembretes(array $f, array $s, string $tipo): array
{
    $enviados = 0;
    $falhados = 0;
    foreach (consulta("SELECT id FROM inscricoes WHERE formacao_id = ? AND estado = 'confirmada' ORDER BY id", [$f['id']]) as $linha) {
        $i = linha_bd(SQL_INSCRICAO, [$linha['id']]);
        $contacto = $i['email'] ? null : contacto_inscricao($i);
        $para = $i['email'] ?: ($contacto['email'] ?? '');
        if ($para === '') {
            $falhados++;
            continue;
        }
        $nome = $contacto ? ($contacto['nome'] ?: $i['formando']) : $i['formando'];
        $ok = enviar_mensagem($tipo === 'hora' ? 'lembrete_hora' : 'lembrete_vespera', $para, [
            'nome' => nome_curto($nome), 'formando' => $i['formando'], 'formacao' => $i['titulo'], 'sessao' => $s['nome'],
            'dia' => dia_sessao($s['inicio']), 'hora' => hora_lembrete($s), 'inicio' => date('H:i', strtotime($s['inicio'])), 'local' => modalidade_e_local($i),
        ], bloco_lembrete($i, $s, $contacto !== null));
        $ok ? $enviados++ : $falhados++;
    }
    return [$enviados, $falhados];
}

// Botão da lista de inscritos: lembrete da próxima sessão, enviado agora
function enviar_lembrete_proxima(array $f): array
{
    $s = proxima_sessao($f);
    if (!$s) {
        return [0, 0, null];
    }
    [$enviados, $falhados] = enviar_lembretes($f, $s, 'vespera');
    executar('INSERT INTO lembretes (formacao_id, sessao, tipo, origem, enviados, falhados, utilizador_id, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$f['id'], $s['inicio'], 'vespera', 'botao', $enviados, $falhados, utilizador()['id'] ?? null, agora()]);
    return [$enviados, $falhados, $s];
}

// Sessões que começam na próxima hora, em edições não canceladas com inscrições confirmadas e ainda sem lembrete automático
function sessoes_para_lembrete(): array
{
    $de = date('Y-m-d H:i:s');
    $ate = date('Y-m-d H:i:s', time() + LEMBRETE_ANTECEDENCIA);
    return consulta("SELECT x.formacao_id, x.inicio FROM (
            SELECT s.formacao_id, s.inicio FROM sessoes s
            UNION SELECT f.id, f.inicio FROM formacoes f WHERE NOT EXISTS (SELECT 1 FROM sessoes s WHERE s.formacao_id = f.id)
        ) x JOIN formacoes f ON f.id = x.formacao_id
        WHERE f.estado <> 'cancelada' AND x.inicio > ? AND x.inicio <= ?
          AND EXISTS (SELECT 1 FROM inscricoes i WHERE i.formacao_id = f.id AND i.estado = 'confirmada')
          AND NOT EXISTS (SELECT 1 FROM lembretes l WHERE l.formacao_id = f.id AND l.sessao = x.inicio AND l.tipo = 'hora')
        ORDER BY x.inicio", [$de, $ate]);
}

// Envio automático. Cada sessão é reservada antes do envio (índice único), para não haver lembretes repetidos; se todos
// os envios falharem (por exemplo, servidor de email indisponível), a reserva é retirada e a próxima verificação tenta de novo.
// Devolve o número de emails enviados.
function enviar_lembretes_automaticos(string $origem): int
{
    if (!bd() || !comunicacao()['emails']['lembrete_hora']['ativo']) {
        return 0;
    }
    $total = 0;
    foreach (sessoes_para_lembrete() as $p) {
        $st = bd()->prepare("INSERT OR IGNORE INTO lembretes (formacao_id, sessao, tipo, origem, criado) VALUES (?, ?, 'hora', ?, ?)");
        $st->execute([$p['formacao_id'], $p['inicio'], $origem, agora()]);
        if ($st->rowCount() === 0) {
            continue;
        }
        $registo = (int) bd()->lastInsertId();
        $f = linha_bd('SELECT * FROM formacoes WHERE id = ?', [$p['formacao_id']]);
        $s = null;
        foreach (sessoes_edicao($f) as $x) {
            if ($x['inicio'] === $p['inicio']) {
                $s = $x;
            }
        }
        [$enviados, $falhados] = $s ? enviar_lembretes($f, $s, 'hora') : [0, 0];
        if ($enviados === 0) {
            executar('DELETE FROM lembretes WHERE id = ?', [$registo]);
            continue;
        }
        executar('UPDATE lembretes SET enviados = ?, falhados = ? WHERE id = ?', [$enviados, $falhados, $registo]);
        $total += $enviados;
    }
    return $total;
}

// Chave da tarefa agendada (criada na primeira utilização; "Gerar nova chave" em Configuração invalida a anterior)
function chave_lembretes(bool $nova = false): string
{
    $chave = definicao(bd(), 'lembretes_chave');
    if ($nova || !$chave) {
        $chave = bin2hex(random_bytes(20));
        definir(bd(), 'lembretes_chave', $chave);
    }
    return $chave;
}

function endereco_tarefa_lembretes(): string
{
    return endereco_site() . '/clientes/lembretes.php?chave=' . chave_lembretes();
}

// Reserva da tarefa agendada: no fim de um pedido à área reservada ou às páginas de formação, depois de enviada a
// resposta quando o servidor o permite, envia os lembretes em atraso. No máximo uma verificação a cada 5 minutos.
function verificar_lembretes_na_saida(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    register_shutdown_function(function () {
        try {
            $pdo = bd();
            if (!$pdo || time() - (int) definicao($pdo, 'lembretes_visita') < LEMBRETES_INTERVALO_VISITAS) {
                return;
            }
            definir($pdo, 'lembretes_visita', (string) time());
            if (!sessoes_para_lembrete()) {
                return;
            }
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
            ignore_user_abort(true);
            enviar_lembretes_automaticos('visita');
        } catch (Throwable $e) {
            error_log('Lembretes: ' . $e->getMessage());
        }
    });
}

// Estado do envio automático, para a ficha da edição e para Configuração
function estado_tarefa_lembretes(): array
{
    $ultima = (string) definicao(bd(), 'lembretes_tarefa');
    return ['ativo' => comunicacao()['emails']['lembrete_hora']['ativo'], 'ultima' => $ultima, 'recente' => $ultima !== '' && strtotime($ultima) > time() - 86400];
}

// Bloco "Lembretes aos formandos" na lista de inscritos de uma edição
function lembretes_edicao(array $f, int $confirmadas): string
{
    if ($f['estado'] === 'cancelada') {
        return '';
    }
    $s = proxima_sessao($f);
    $envios = consulta('SELECT * FROM lembretes WHERE formacao_id = ? ORDER BY criado', [$f['id']]);
    $data_curta = function (string $d) { return date('d/m/Y \à\s H:i', strtotime($d)); };
    $html = '<div class="partilha espaco-topo lembretes" id="lembretes"><h3 class="mt-0">Lembretes aos formandos</h3>';
    if ($s) {
        $html .= '<p>Próxima sessão: <strong>' . h($s['nome'] ?: 'Sessão') . '</strong>, ' . h(dia_sessao($s['inicio'])) . ', ' . h(hora_sessao($s)) . '.</p>';
        if (in_array($f['modalidade'], ['online', 'misto'], true) && empty($f['reuniao'])) {
            $html .= '<div class="aviso aviso-info">Esta edição ainda não tem a ligação da reunião online: os lembretes seguem sem ligação. Indique-a acima e guarde antes de enviar.</div>';
        }
        $anteriores = array_values(array_filter($envios, function ($e) use ($s) { return $e['tipo'] === 'vespera' && $e['sessao'] === $s['inicio']; }));
        if ($confirmadas) {
            $html .= '<form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="lembrete"><button class="botao" type="submit">'
                . ($anteriores ? 'Enviar de novo o lembrete' : 'Enviar lembrete') . ' aos ' . $confirmadas . ' formando(s) confirmado(s)</button></form>'
                . '<p class="ajuda">Para usar na véspera: cada formando com inscrição confirmada (ou, sem email do formando, quem o inscreveu) recebe a sessão, a data, o horário, o local e a ligação para entrar.</p>';
        } else {
            $html .= '<p class="texto-suave">Ainda não há inscrições confirmadas: os lembretes só seguem para as inscrições confirmadas.</p>';
        }
        foreach ($anteriores as $e) {
            $html .= '<p class="texto-suave">Lembrete desta sessão enviado em ' . h($data_curta($e['criado'])) . ' para ' . (int) $e['enviados'] . ' formando(s)'
                . ($e['falhados'] ? ', ' . (int) $e['falhados'] . ' sem envio' : '') . '.</p>';
        }
    } else {
        $html .= '<p class="texto-suave">Todas as sessões desta edição já começaram: não há lembretes por enviar.</p>';
    }
    $estado = estado_tarefa_lembretes();
    $html .= '<p class="espaco-topo-curto"><strong>Envio automático 1 hora antes de cada sessão:</strong> '
        . (!$estado['ativo'] ? 'desativado em <a href="' . h(url('comunicacao', ['aberto' => 'lembrete_hora']) . '#email-lembrete_hora') . '">Configuração, Comunicação</a>.'
            : 'cada formando confirmado recebe um lembrete com a hora e a ligação da sessão.'
                . ($estado['recente'] ? '' : ' <a href="' . h(url('configuracao') . '#lembretes') . '">A tarefa agendada que faz este envio ainda não correu nas últimas 24 horas</a>: sem ela, o envio depende de haver visitas ao site nessa hora.'))
        . '</p>';
    $automaticos = array_filter($envios, function ($e) { return $e['tipo'] === 'hora'; });
    if ($automaticos) {
        $html .= '<ul class="lista-datas">' . implode('', array_map(function ($e) use ($data_curta) {
            return '<li>Sessão de ' . h($data_curta($e['sessao'])) . ': lembrete enviado em ' . h($data_curta($e['criado'])) . ' para ' . (int) $e['enviados'] . ' formando(s)' . ($e['falhados'] ? ', ' . (int) $e['falhados'] . ' sem envio' : '') . '</li>';
        }, $automaticos)) . '</ul>';
    }
    return $html . '</div>';
}
