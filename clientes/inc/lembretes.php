<?php
// Lembretes das sessões aos formandos com inscrição confirmada: o botão "Enviar lembrete" na lista de inscritos de uma
// edição (na véspera ou quando for preciso, para a sessão escolhida) e o envio automático antes de cada sessão (1 hora, por
// omissão, ou a antecedência escolhida na edição). O envio automático corre na
// tarefa agendada do alojamento (clientes/lembretes.php, com a chave mostrada em Configuração) e, como reserva, nas
// visitas à área reservada e às páginas de formação, no máximo de 5 em 5 minutos. Cada envio fica registado na tabela
// "lembretes"; o automático é único por sessão, mesmo que duas verificações corram ao mesmo tempo.

declare(strict_types=1);

require_once __DIR__ . '/nucleo.php';
require_once __DIR__ . '/paginas-cliente.php';

const LEMBRETE_ANTECEDENCIA = 60; // minutos antes do início da sessão, quando a edição não define outra antecedência
// Antecedências propostas na ficha da edição (minutos); 0 = sem envio automático nessa edição
const ANTECEDENCIAS = [0 => 'Não enviar automaticamente', 15 => '15 minutos antes', 30 => '30 minutos antes', 60 => '1 hora antes', 120 => '2 horas antes',
    180 => '3 horas antes', 360 => '6 horas antes', 720 => '12 horas antes', 1440 => '24 horas antes (véspera)', 2880 => '48 horas antes'];
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

// "hoje às 18:30", "amanhã às 18:30" ou "na terça-feira, 10 de novembro de 2026, às 18:30"
function quando_sessao(string $inicio): string
{
    $hora = date('H:i', strtotime($inicio));
    $dia = dia_sessao($inicio);
    return strpos($dia, 'hoje, ') === 0 ? 'hoje às ' . $hora : (strpos($dia, 'amanhã, ') === 0 ? 'amanhã às ' . $hora : 'na ' . $dia . ', às ' . $hora);
}

// Antecedência do envio automático de uma edição, em minutos (0 = desligado)
function antecedencia_edicao(array $f): int
{
    return isset($f['lembrete_antecedencia']) && $f['lembrete_antecedencia'] !== null ? max(0, (int) $f['lembrete_antecedencia']) : LEMBRETE_ANTECEDENCIA;
}

// "1 hora", "2 horas", "1 h 30 min", "45 minutos"
function texto_antecedencia(int $min): string
{
    if ($min % 60 === 0) {
        return ($min / 60) . ($min === 60 ? ' hora' : ' horas');
    }
    return $min > 60 ? intdiv($min, 60) . ' h ' . ($min % 60) . ' min' : $min . ' minutos';
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
            'dia' => dia_sessao($s['inicio']), 'hora' => hora_lembrete($s), 'inicio' => date('H:i', strtotime($s['inicio'])), 'quando' => quando_sessao($s['inicio']), 'local' => modalidade_e_local($i),
        ], bloco_lembrete($i, $s, $contacto !== null));
        $ok ? $enviados++ : $falhados++;
    }
    return [$enviados, $falhados];
}

// Botão da lista de inscritos: lembrete de uma sessão (n.º 1, 2...; por omissão, a próxima), enviado agora, mesmo que a sessão
// já tenha começado ou decorrido
function enviar_lembrete_sessao(array $f, int $n = 0): array
{
    $todas = sessoes_edicao($f);
    $s = $n > 0 ? ($todas[$n - 1] ?? null) : proxima_sessao($f);
    if (!$s) {
        return [0, 0, null];
    }
    [$enviados, $falhados] = enviar_lembretes($f, $s, 'vespera');
    executar('INSERT INTO lembretes (formacao_id, sessao, tipo, origem, enviados, falhados, utilizador_id, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$f['id'], $s['inicio'], 'vespera', 'botao', $enviados, $falhados, utilizador()['id'] ?? null, agora()]);
    return [$enviados, $falhados, $s];
}

// Sessões que já entraram na antecedência de envio da sua edição (1 hora, por omissão) e ainda não começaram, em edições não
// canceladas, com envio automático ligado, inscrições confirmadas e ainda sem lembrete automático
function sessoes_para_lembrete(): array
{
    $agora = date('Y-m-d H:i:s');
    return consulta("SELECT x.formacao_id, x.inicio FROM (
            SELECT s.formacao_id, s.inicio FROM sessoes s
            UNION SELECT f.id, f.inicio FROM formacoes f WHERE NOT EXISTS (SELECT 1 FROM sessoes s WHERE s.formacao_id = f.id)
        ) x JOIN formacoes f ON f.id = x.formacao_id
        WHERE f.estado <> 'cancelada' AND COALESCE(f.lembrete_antecedencia, " . LEMBRETE_ANTECEDENCIA . ") > 0 AND x.inicio > ?
          AND x.inicio <= datetime(?, '+' || COALESCE(f.lembrete_antecedencia, " . LEMBRETE_ANTECEDENCIA . ") || ' minutes')
          AND EXISTS (SELECT 1 FROM inscricoes i WHERE i.formacao_id = f.id AND i.estado = 'confirmada')
          AND NOT EXISTS (SELECT 1 FROM lembretes l WHERE l.formacao_id = f.id AND l.sessao = x.inicio AND l.tipo = 'hora')
        ORDER BY x.inicio", [$agora, $agora]);
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

// Bloco "Lembretes aos formandos" na lista de inscritos de uma edição: envio pelo botão (a sessão escolhe-se; por omissão,
// a próxima) e antecedência do envio automático desta edição
function lembretes_edicao(array $f, int $confirmadas): string
{
    if ($f['estado'] === 'cancelada') {
        return '';
    }
    $todas = sessoes_edicao($f);
    $proxima = proxima_sessao($f);
    $escolhida = $proxima ? $proxima['n'] : count($todas);
    $envios = consulta('SELECT * FROM lembretes WHERE formacao_id = ? ORDER BY criado', [$f['id']]);
    $data_curta = function (string $d) { return date('d/m/Y \à\s H:i', strtotime($d)); };
    $html = '<div class="partilha espaco-topo lembretes" id="lembretes"><h3 class="mt-0">Lembretes aos formandos</h3>';
    $html .= $proxima ? '<p>Próxima sessão: <strong>' . h($proxima['nome'] ?: 'Sessão') . '</strong>, ' . h(dia_sessao($proxima['inicio'])) . ', ' . h(hora_sessao($proxima)) . '.</p>'
        : '<p class="texto-suave">Todas as sessões desta edição já começaram. Pode, mesmo assim, enviar o lembrete de uma delas.</p>';
    if (in_array($f['modalidade'], ['online', 'misto'], true) && empty($f['reuniao'])) {
        $html .= '<div class="aviso aviso-info">Esta edição ainda não tem a ligação da reunião online: os lembretes seguem sem ligação. Indique-a acima e guarde antes de enviar.</div>';
    }
    if ($confirmadas) {
        $opcoes = '';
        foreach ($todas as $s) {
            $estado = strtotime($s['inicio']) > time() ? '' : (strtotime($s['fim'] ?: $s['inicio']) > time() ? ' (a decorrer)' : ' (já decorreu)');
            $opcoes .= '<option value="' . (int) $s['n'] . '"' . ((int) $s['n'] === $escolhida ? ' selected' : '') . '>'
                . h(($s['nome'] ?: 'Sessão') . ' · ' . dia_sessao($s['inicio']) . ', ' . hora_sessao($s) . $estado) . '</option>';
        }
        $html .= '<form method="post" class="formulario lembrete-envio">' . campo_csrf() . '<input type="hidden" name="acao" value="lembrete">
          <div class="campo"><label for="c-lembrete-sessao">Sessão do lembrete</label><select id="c-lembrete-sessao" name="sessao">' . $opcoes . '</select></div>
          <div><button class="botao" type="submit">Enviar lembrete aos ' . $confirmadas . ' formando(s) confirmado(s)</button></div></form>'
            . '<p class="ajuda">Para usar na véspera ou a qualquer momento, também para uma sessão atrasada ou já começada: cada formando com inscrição confirmada (ou, sem email do formando, quem o inscreveu) recebe a sessão, a data, o horário, o local e a ligação para entrar.</p>';
    } else {
        $html .= '<p class="texto-suave">Ainda não há inscrições confirmadas: os lembretes só seguem para as inscrições confirmadas.</p>';
    }
    $manuais = array_filter($envios, function ($e) { return $e['tipo'] === 'vespera'; });
    if ($manuais) {
        $html .= '<ul class="lista-datas">' . implode('', array_map(function ($e) use ($data_curta) {
            return '<li>Lembrete da sessão de ' . h($data_curta($e['sessao'])) . ' enviado em ' . h($data_curta($e['criado'])) . ' para ' . (int) $e['enviados'] . ' formando(s)' . ($e['falhados'] ? ', ' . (int) $e['falhados'] . ' sem envio' : '') . '</li>';
        }, $manuais)) . '</ul>';
    }
    // Envio automático: antecedência desta edição
    $min = antecedencia_edicao($f);
    $estado = estado_tarefa_lembretes();
    $outra = !isset(ANTECEDENCIAS[$min]);
    $html .= '<h4 class="espaco-topo">Envio automático antes de cada sessão</h4>';
    if (!$estado['ativo']) {
        $html .= '<p>Desativado para todas as edições em <a href="' . h(url('comunicacao', ['aberto' => 'lembrete_hora']) . '#email-lembrete_hora') . '">Configuração, Comunicação</a>.</p>';
    } else {
        $html .= '<p>' . ($min ? 'Cada formando confirmado recebe um lembrete com a hora e a ligação da sessão <strong>' . h(texto_antecedencia($min)) . ' antes</strong> do início de cada sessão.' : 'Esta edição não tem envio automático.')
            . ($min && !$estado['recente'] ? ' <a href="' . h(url('configuracao') . '#lembretes') . '">A tarefa agendada que faz este envio ainda não correu nas últimas 24 horas</a>: sem ela, o envio depende de haver visitas ao site nessa hora.' : '') . '</p>';
    }
    $html .= '<form method="post" class="formulario antecedencia">' . campo_csrf() . '<input type="hidden" name="acao" value="antecedencia">
        <div class="campos-antecedencia">
          <div class="campo"><label for="c-antecedencia">Antecedência</label><select id="c-antecedencia" name="antecedencia" data-outra-escolha>' . opcoes_select(ANTECEDENCIAS + ['outra' => 'Outra antecedência'], $outra ? 'outra' : (string) $min) . '</select></div>
          <div class="campo" data-outra-campo' . ($outra ? '' : ' hidden') . '><label for="c-antecedencia-min">Outra antecedência, em minutos</label><input id="c-antecedencia-min" name="antecedencia_min" type="number" min="5" max="10080" step="5" value="' . ($outra ? $min : '') . '"></div>
          <div class="campo campo-botao"><button class="botao botao-secundario" type="submit">Guardar antecedência</button></div>
        </div></form>';
    $automaticos = array_filter($envios, function ($e) { return $e['tipo'] === 'hora'; });
    if ($automaticos) {
        $html .= '<ul class="lista-datas">' . implode('', array_map(function ($e) use ($data_curta) {
            return '<li>Sessão de ' . h($data_curta($e['sessao'])) . ': lembrete automático enviado em ' . h($data_curta($e['criado'])) . ' para ' . (int) $e['enviados'] . ' formando(s)' . ($e['falhados'] ? ', ' . (int) $e['falhados'] . ' sem envio' : '') . '</li>';
        }, $automaticos)) . '</ul>';
    }
    return $html . '</div>';
}
