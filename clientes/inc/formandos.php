<?php
// Área do formando: perfil Formando criado ao confirmar a inscrição (com uma ligação, válida 7 dias, para definir a
// palavra-passe), registo de presença em cada sessão pelo próprio formando (de 2 horas antes do início até 1 dia depois
// do fim, com email de confirmação) e presenças marcadas ou retiradas pela administração em qualquer momento.
// Os formandos sem email não têm acesso: a administração marca as presenças na edição.

declare(strict_types=1);

const PRESENCA_ANTES = 2 * 3600;            // o registo de presença abre 2 horas antes do início da sessão
const PRESENCA_DEPOIS = 86400;              // e fecha 1 dia depois do fim da sessão
const VALIDADE_LIGACAO_SENHA = 7 * 86400;   // ligação para definir a palavra-passe

/* Sessões e presenças */

// Sessões de uma edição (número, total, início, fim e nome), a partir de uma inscrição ou da própria edição
function sessoes_da_inscricao(array $i): array
{
    $f = linha_bd('SELECT * FROM formacoes WHERE id = ?', [(int) ($i['formacao_id'] ?? $i['id'])]);
    return $f ? sessoes_edicao($f) : [];
}

// [abertura, fecho] do registo de presença de uma sessão, em segundos
function janela_presenca(array $s): array
{
    $inicio = strtotime($s['inicio']);
    $fim = !empty($s['fim']) ? strtotime($s['fim']) : $inicio;
    return [$inicio - PRESENCA_ANTES, $fim + PRESENCA_DEPOIS];
}

function presenca_aberta(array $s, ?int $agora = null): bool
{
    [$abre, $fecha] = janela_presenca($s);
    $agora = $agora ?? time();
    return $agora >= $abre && $agora <= $fecha;
}

// Presenças de uma inscrição, por número de sessão
function presencas_inscricao(int $inscricao_id): array
{
    $r = [];
    foreach (consulta('SELECT * FROM presencas WHERE inscricao_id = ? ORDER BY sessao', [$inscricao_id]) as $p) {
        $r[(int) $p['sessao']] = $p;
    }
    return $r;
}

// Regista a presença (devolve false se já estava registada)
function marcar_presenca(int $inscricao_id, int $sessao, string $origem, ?int $utilizador_id): bool
{
    $st = bd()->prepare('INSERT OR IGNORE INTO presencas (inscricao_id, sessao, registado, origem, utilizador_id) VALUES (?, ?, ?, ?, ?)');
    $st->execute([$inscricao_id, $sessao, agora(), $origem, $utilizador_id]);
    return $st->rowCount() > 0;
}

// "Presente" com quem registou e quando
function presenca_html(?array $p): string
{
    if (!$p) {
        return '';
    }
    return etiqueta('Presente', 'ok') . ' <span class="texto-suave">' . ($p['origem'] === 'formando' ? 'registada às ' . h(date('H:i', strtotime($p['registado']))) . ' de ' . h(data_hora($p['registado'], false)) : 'marcada pela Gadgetmetodo') . '</span>';
}

/* Acesso do formando */

function link_definir_senha(string $token): string
{
    return endereco_site() . '/clientes/area.php?' . http_build_query(['p' => 'senha', 't' => $token]);
}

// Gera uma ligação nova (a anterior deixa de valer) e envia-a ao utilizador
function enviar_ligacao_senha(array $u, string $curso = ''): bool
{
    $token = bin2hex(random_bytes(24));
    $validade = date('Y-m-d H:i:s', time() + VALIDADE_LIGACAO_SENHA);
    executar('UPDATE utilizadores SET token_senha = ?, token_validade = ? WHERE id = ?', [hash('sha256', $token), $validade, $u['id']]);
    $link = link_definir_senha($token);
    $quando = data_hora($validade);
    return enviar_mensagem('acesso_formando', (string) $u['email'], [
        'nome' => nome_curto((string) $u['nome']), 'nome_completo' => $u['nome'], 'formando' => $u['nome'], 'email' => $u['email'],
        'curso' => $curso, 'no_curso' => $curso !== '' ? 'no ' . $curso : 'na formação', 'validade' => $quando,
    ], [
        '<p style="margin:0 0 6px;"><strong>Nome de utilizador:</strong> ' . h((string) $u['email']) . '</p>'
        . '<p style="margin:0 0 18px;"><strong>Ligação válida até:</strong> ' . h($quando) . '</p>'
        . '<p style="margin:0 0 4px;"><a href="' . h($link) . '" style="display:inline-block;padding:11px 20px;background:#00636A;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:6px;">Definir a palavra-passe</a></p>',
        'Nome de utilizador: ' . $u['email'] . "\nDefinir a palavra-passe (ligação válida até " . $quando . '): ' . $link,
    ]);
}

// Acesso à área do formando para uma inscrição confirmada. Estados: "criado" (conta nova e ligação enviada), "falhou"
// (conta nova, envio falhado), "reenviado" (ligação anterior expirada sem uso), "existe", "outro_perfil" ou "sem_email".
function garantir_acesso_formando(array $i): string
{
    $email = trim((string) ($i['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'sem_email';
    }
    $curso = (string) ($i['titulo'] ?? '');
    $u = linha_bd('SELECT * FROM utilizadores WHERE email = ?', [$email]);
    if ($u && $u['papel'] !== 'formando') {
        return 'outro_perfil';
    }
    if ($u) {
        // Ainda sem palavra-passe definida e com a ligação expirada: envia uma nova
        if ($u['token_senha'] !== null && strtotime((string) $u['token_validade']) < time() && (int) $u['ativo'] === 1) {
            return enviar_ligacao_senha($u, $curso) ? 'reenviado' : 'falhou';
        }
        return 'existe';
    }
    // Conta nova: a palavra-passe inicial é aleatória e nunca é comunicada; o formando define a sua pela ligação
    $id = executar("INSERT INTO utilizadores (email, nome, senha, papel, criado) VALUES (?, ?, ?, 'formando', ?)",
        [$email, (string) $i['formando'], password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), agora()]);
    return enviar_ligacao_senha(linha_bd('SELECT * FROM utilizadores WHERE id = ?', [$id]), $curso) ? 'criado' : 'falhou';
}

// Depois de uma inscrição passar a confirmada: cria o acesso do formando e devolve [texto do aviso, tipo] ou null
function acesso_apos_confirmacao(int $inscricao_id): ?array
{
    $i = linha_bd(SQL_INSCRICAO, [$inscricao_id]);
    if (!$i || $i['estado'] !== 'confirmada') {
        return null;
    }
    $quem = h((string) $i['formando']);
    switch (garantir_acesso_formando($i)) {
        case 'criado':
            return ['Acesso de ' . $quem . ' à área do formando criado: a ligação para definir a palavra-passe (válida 7 dias) foi enviada para ' . h((string) $i['email']) . '.', 'ok'];
        case 'reenviado':
            return ['Enviada a ' . $quem . ' uma nova ligação para definir a palavra-passe (a anterior tinha expirado).', 'ok'];
        case 'falhou':
            return ['O acesso de ' . $quem . ' à área do formando foi criado, mas o email com a ligação não foi enviado. Pode reenviá-la em Configuração, Utilizadores.', 'erro'];
        case 'outro_perfil':
            return (linha_bd('SELECT papel FROM utilizadores WHERE email = ?', [(string) $i['email']])['papel'] ?? '') === 'cliente'
                ? [$quem . ' já tem acesso à área reservada com o perfil Cliente: vê esta formação e regista as presenças em "As minhas formações".', 'info'] : null;
        case 'sem_email':
            return [$quem . ' não tem email: não tem acesso à área do formando. As presenças marcam-se na edição, em "Presenças".', 'info'];
        default:
            return null;
    }
}

/* Definir a palavra-passe (ligação enviada por email; sem sessão iniciada) */

function pagina_definir_senha(): void
{
    $t = is_string($_GET['t'] ?? null) ? $_GET['t'] : '';
    $u = preg_match('/^[a-f0-9]{48}$/', $t) && !limite_excedido('senha', 20, 900, false)
        ? linha_bd('SELECT * FROM utilizadores WHERE token_senha = ? AND ativo = 1', [hash('sha256', $t)]) : null;
    $valida = $u && strtotime((string) $u['token_validade']) >= time();
    $cabecalho = '<header class="cabecalho"><div class="contentor"><a class="marca" href="/"><img src="/assets/marca/logotipo.svg" alt="Gadgetmetodo, Lda." width="234" height="42"></a></div></header>';
    if (!$valida) {
        limite_excedido('senha', 20, 900, true);
        http_response_code($u ? 410 : 404);
        documento('Ligação expirada', $cabecalho . '<main class="secao"><div class="contentor estreito"><span class="sobretitulo">Área do formando</span>
          <h1>' . ($u ? 'Ligação expirada' : 'Ligação inválida') . '</h1>
          <p class="lead">' . ($u ? 'Esta ligação para definir a palavra-passe já expirou.' : 'Esta ligação não é válida ou já foi usada.') . ' Peça uma nova ligação a <a href="mailto:info@gadgetmetodo.pt">info@gadgetmetodo.pt</a>. Se já definiu a palavra-passe, entre na área reservada.</p>
          <div class="acoes"><a class="botao" href="area.php">Entrar</a></div></div></main>');
    }
    $erro = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        verificar_csrf();
        $nova = is_string($_POST['nova'] ?? null) ? $_POST['nova'] : '';
        if (strlen($nova) < 10) {
            $erro = 'A palavra-passe tem de ter pelo menos 10 caracteres.';
        } elseif ($nova !== (string) ($_POST['repetir'] ?? '')) {
            $erro = 'As duas palavras-passe não coincidem.';
        } else {
            executar('UPDATE utilizadores SET senha = ?, token_senha = NULL, token_validade = NULL WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
            sessao_iniciada((int) $u['id']);
            aviso('Palavra-passe definida. A partir de agora, entre com o seu email e esta palavra-passe.');
            redirecionar('painel');
        }
    }
    documento('Definir a palavra-passe', $cabecalho . '<main class="secao"><div class="contentor estreito">
    ' . avisos() . '
    <span class="sobretitulo">Área do formando</span>
    <h1>Definir a palavra-passe</h1>
    <p class="texto-suave">' . h(nome_curto((string) $u['nome'])) . ', escolha a palavra-passe da sua área de formando. O nome de utilizador é o seu email: <strong>' . h((string) $u['email']) . '</strong>.</p>
    <form method="post" action="' . h(url('senha', ['t' => $t])) . '" class="formulario" style="max-width:440px">' . campo_csrf() . '
      <input type="text" name="utilizador" value="' . h((string) $u['email']) . '" autocomplete="username" hidden>
      ' . campo('nova', 'Palavra-passe (mínimo 10 caracteres)', '', 'password', 'required minlength="10" autocomplete="new-password"') . '
      ' . campo('repetir', 'Repetir a palavra-passe', '', 'password', 'required minlength="10" autocomplete="new-password"') . '
      ' . ($erro ? '<p class="erro-campo">' . h($erro) . '</p>' : '') . '
      <div><button class="botao" type="submit">Guardar e entrar</button></div>
    </form>
  </div></main>');
}

/* Área do formando */

// Endereço da área do formando: o painel dos formandos; para um utilizador Cliente que também é formando, "presencas"
function rota_formando(): string
{
    return eh_formando() ? 'painel' : 'presencas';
}

// Utilizador Cliente com inscrições confirmadas como formando (pelo email): vê também "As minhas formações"
function tambem_formando(): bool
{
    $u = utilizador();
    return $u && $u['papel'] === 'cliente'
        && (bool) linha_bd("SELECT 1 AS x FROM inscricoes WHERE email = ? COLLATE NOCASE AND estado = 'confirmada' LIMIT 1", [$u['email']]);
}

// Inscrições confirmadas do formando (pelo email da conta), da edição mais recente para a mais antiga
function inscricoes_do_formando(array $u): array
{
    $ids = consulta("SELECT i.id FROM inscricoes i JOIN formacoes f ON f.id = i.formacao_id WHERE i.email = ? COLLATE NOCASE AND i.estado = 'confirmada' AND f.estado != 'cancelada' ORDER BY f.inicio DESC, i.id DESC", [$u['email']]);
    return array_map(function ($l) { return linha_bd(SQL_INSCRICAO, [$l['id']]); }, $ids);
}

function formando_painel(): void
{
    $u = utilizador();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'presenca') {
        $id = (int) ($_POST['inscricao'] ?? 0);
        $n = (int) ($_POST['sessao'] ?? 0);
        $i = linha_bd(SQL_INSCRICAO, [$id]);
        $minha = $i && strcasecmp((string) $i['email'], (string) $u['email']) === 0 && $i['estado'] === 'confirmada' && $i['estado_formacao'] !== 'cancelada';
        $s = $minha ? (sessoes_da_inscricao($i)[$n - 1] ?? null) : null;
        if (!$s) {
            aviso('Sessão não encontrada.', 'erro');
        } elseif (!presenca_aberta($s)) {
            [$abre] = janela_presenca($s);
            aviso(time() < $abre ? 'O registo de presença desta sessão abre às ' . h(date('H:i', $abre)) . ' de ' . h(data_hora(date('Y-m-d H:i:s', $abre), false)) . ' (2 horas antes do início).'
                : 'O registo de presença desta sessão já fechou (1 dia depois do fim). Se esteve presente, informe a Gadgetmetodo.', 'erro');
        } elseif (marcar_presenca($id, $n, 'formando', (int) $u['id'])) {
            $p = presencas_inscricao($id)[$n];
            $linhas = array_filter(['Formação' => (string) $i['titulo'], 'Sessão' => $s['nome'], 'Data' => dia_sessao($s['inicio'], false) . ', ' . hora_sessao($s), 'Registo' => data_hora($p['registado'])], 'strlen');
            $html = '';
            foreach ($linhas as $rotulo => $valor) {
                $html .= '<p style="margin:0 0 4px;"><strong>' . h($rotulo) . ':</strong> ' . h($valor) . '</p>';
            }
            $enviado = enviar_mensagem('presenca', (string) $u['email'], ['nome' => nome_curto((string) $i['formando']), 'formando' => $i['formando'], 'formacao' => $i['titulo'],
                'sessao' => $s['nome'], 'dia' => dia_sessao($s['inicio'], false), 'hora' => hora_lembrete($s)],
                ['<div style="margin:0 0 14px;">' . $html . '</div>', implode("\n", array_map(function ($k, $v) { return $k . ': ' . $v; }, array_keys($linhas), $linhas))]);
            aviso('Presença registada' . ($s['nome'] ? ' na ' . h(mb_strtolower(mb_substr($s['nome'], 0, 1)) . mb_substr($s['nome'], 1)) : '') . '.' . ($enviado ? ' Enviámos a confirmação para ' . h((string) $u['email']) . '.' : ''));
        } else {
            aviso('A presença nesta sessão já estava registada.', 'info');
        }
        redirecionar(rota_formando(), [], 'inscricao-' . $id);
    }
    $blocos = '';
    foreach (inscricoes_do_formando($u) as $i) {
        $sessoes = sessoes_da_inscricao($i);
        $presencas = presencas_inscricao((int) $i['id']);
        $online = ($i['modalidade'] ?? '') !== 'presencial' && !empty($i['reuniao']);
        $linhas = '';
        foreach ($sessoes as $s) {
            [$abre, $fecha] = janela_presenca($s);
            $fim = !empty($s['fim']) ? strtotime($s['fim']) : strtotime($s['inicio']) + 3600;
            if (isset($presencas[$s['n']])) {
                $estado = presenca_html($presencas[$s['n']]);
            } elseif (presenca_aberta($s)) {
                $estado = '<form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="presenca"><input type="hidden" name="inscricao" value="' . (int) $i['id'] . '"><input type="hidden" name="sessao" value="' . (int) $s['n'] . '">'
                    . '<button class="botao" type="submit">Estou presente</button></form>';
            } elseif (time() < $abre) {
                $estado = '<span class="texto-suave">Registo a partir das ' . h(date('H:i', $abre)) . ' de ' . h(date('d/m', $abre)) . '</span>';
            } else {
                $estado = etiqueta('Sem registo');
            }
            $entrar = $online && time() >= $abre && time() <= $fim ? ' <a class="botao botao-secundario" href="' . h($i['reuniao']) . '" target="_blank" rel="noopener">Entrar na sessão</a>' : '';
            $linhas .= '<tr><td>' . h($s['nome'] ?: 'Sessão única') . '</td><td>' . h(dia_sessao($s['inicio'], false)) . '<br><span class="texto-suave">' . h(hora_sessao($s)) . '</span></td><td><div class="acoes-presenca">' . $estado . $entrar . '</div></td></tr>';
        }
        $blocos .= '<section class="bloco-formando" id="inscricao-' . (int) $i['id'] . '">
          <h2>' . h($i['titulo']) . '</h2>
          <p class="texto-suave">' . h(horario($i)) . ' · ' . h(modalidade_e_local($i)) . ' · Presenças: ' . count($presencas) . ' de ' . count($sessoes) . '</p>
          <div class="conteudo tabela-rolante"><table class="tabela-presencas"><thead><tr><th>Sessão</th><th>Data</th><th>Presença</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
        </section>';
    }
    pagina_area(rota_formando(), 'As minhas formações', ($blocos !== '' ? '<p class="texto-suave">Em cada sessão, registe a presença com o botão "Estou presente", disponível de 2 horas antes do início até 1 dia depois do fim. Recebe a confirmação por email.</p>' . $blocos
        : '<p class="texto-suave">Ainda não tem formações confirmadas. Quando a sua inscrição for confirmada, a formação aparece aqui.</p>'), 'Área do formando');
}

/* Administração: presenças de uma edição e acessos dos formandos */

// Ações da edição: "presencas" (grava a grelha) e "acessos" (cria o acesso aos formandos confirmados que ainda não o têm)
function tratar_presencas_edicao(array $f): void
{
    $acao = (string) ($_POST['acao'] ?? '');
    $id = (int) $f['id'];
    if ($acao === 'presencas') {
        $sessoes = array_column(sessoes_edicao($f), 'n');
        $marcadas = is_array($_POST['p'] ?? null) ? $_POST['p'] : [];
        $mostradas = is_array($_POST['mostradas'] ?? null) ? array_map('intval', array_keys($_POST['mostradas'])) : [];
        $novas = 0;
        $retiradas = 0;
        foreach (consulta("SELECT id FROM inscricoes WHERE formacao_id = ? AND estado = 'confirmada'", [$id]) as $l) {
            $iid = (int) $l['id'];
            if (!in_array($iid, $mostradas, true)) {
                continue;
            }
            $atuais = presencas_inscricao($iid);
            foreach ($sessoes as $n) {
                $quer = !empty($marcadas[$iid][$n]);
                if ($quer && !isset($atuais[$n])) {
                    $novas += marcar_presenca($iid, (int) $n, 'admin', (int) utilizador()['id']) ? 1 : 0;
                } elseif (!$quer && isset($atuais[$n])) {
                    executar('DELETE FROM presencas WHERE inscricao_id = ? AND sessao = ?', [$iid, $n]);
                    $retiradas++;
                }
            }
        }
        aviso($novas || $retiradas ? 'Presenças guardadas: ' . $novas . ' marcada(s), ' . $retiradas . ' retirada(s).' : 'Nada a alterar nas presenças.', $novas || $retiradas ? 'ok' : 'info');
        redirecionar('formacao', ['id' => $id], 'presencas');
    }
    if ($acao === 'acessos') {
        $contagem = ['criado' => 0, 'reenviado' => 0, 'falhou' => 0];
        foreach (consulta("SELECT id FROM inscricoes WHERE formacao_id = ? AND estado = 'confirmada'", [$id]) as $l) {
            $r = garantir_acesso_formando(linha_bd(SQL_INSCRICAO, [$l['id']]));
            if (isset($contagem[$r])) {
                $contagem[$r]++;
            }
        }
        $partes = array_filter([
            $contagem['criado'] ? $contagem['criado'] . ' acesso(s) criado(s), com a ligação enviada por email' : '',
            $contagem['reenviado'] ? $contagem['reenviado'] . ' ligação(ões) expirada(s) reenviada(s)' : '',
            $contagem['falhou'] ? $contagem['falhou'] . ' email(s) não enviado(s)' : '',
        ]);
        aviso($partes ? ucfirst(implode('; ', $partes)) . '.' : 'Todos os formandos confirmados com email já têm acesso.', $contagem['falhou'] ? 'erro' : ($partes ? 'ok' : 'info'));
        redirecionar('formacao', ['id' => $id], 'presencas');
    }
}

// Grelha de presenças (inscrições confirmadas × sessões) e resumo dos acessos dos formandos
function presencas_edicao_html(array $f): string
{
    $sessoes = sessoes_edicao($f);
    $lista = consulta("SELECT i.id, i.formando, i.email, u.id AS uid, u.papel, u.token_senha, u.token_validade, u.ultimo_acesso FROM inscricoes i
        LEFT JOIN utilizadores u ON u.email = i.email COLLATE NOCASE WHERE i.formacao_id = ? AND i.estado = 'confirmada' ORDER BY i.formando COLLATE NOCASE", [$f['id']]);
    if (!$lista) {
        return '';
    }
    $cab = '';
    foreach ($sessoes as $s) {
        $cab .= '<th class="c-sessao" scope="col">' . ($s['total'] > 1 ? 'S' . $s['n'] : 'Sessão') . '<br><span class="texto-suave">' . h(date('d/m', strtotime($s['inicio']))) . '</span></th>';
    }
    $linhas = '';
    $totais = array_fill(1, count($sessoes), 0);
    $sem_conta = 0;
    $sem_email = 0;
    foreach ($lista as $i) {
        $presencas = presencas_inscricao((int) $i['id']);
        $celulas = '';
        foreach ($sessoes as $s) {
            $p = $presencas[$s['n']] ?? null;
            $totais[$s['n']] += $p ? 1 : 0;
            $titulo = $p ? ($p['origem'] === 'formando' ? 'Registada pelo formando em ' . data_hora($p['registado']) : 'Marcada pela administração em ' . data_hora($p['registado'])) : 'Sem presença';
            $celulas .= '<td class="c-sessao' . ($p && $p['origem'] === 'formando' ? ' pelo-formando' : '') . '"><input type="checkbox" name="p[' . (int) $i['id'] . '][' . (int) $s['n'] . ']" value="1"' . ($p ? ' checked' : '')
                . ' aria-label="' . h($i['formando'] . ', ' . ($s['nome'] ?: 'sessão') . ': ' . $titulo) . '" title="' . h($titulo) . '"></td>';
        }
        if ((string) $i['email'] === '') {
            $acesso = '<span class="texto-suave">Sem email</span>';
            $sem_email++;
        } elseif (!$i['uid']) {
            $acesso = '<span class="texto-suave">Sem acesso</span>';
            $sem_conta++;
        } elseif ($i['papel'] !== 'formando') {
            $acesso = '<span class="texto-suave">Utilizador ' . h(mb_strtolower(PAPEIS[$i['papel']] ?? $i['papel'])) . '</span>';
        } elseif ($i['token_senha'] !== null) {
            $acesso = strtotime((string) $i['token_validade']) >= time() ? '<span class="texto-suave">Ligação enviada</span>' : '<span class="texto-suave">Ligação expirada</span>';
            $sem_conta += strtotime((string) $i['token_validade']) < time() ? 1 : 0;
        } else {
            $acesso = '<span class="texto-suave">' . ($i['ultimo_acesso'] ? 'Entrou em ' . h(data_hora($i['ultimo_acesso'], false)) : 'Com acesso') . '</span>';
        }
        $linhas .= '<tr><th scope="row"><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a><input type="hidden" name="mostradas[' . (int) $i['id'] . ']" value="1"><br>' . $acesso . '</th>' . $celulas . '</tr>';
    }
    $rodape = '';
    foreach ($totais as $t) {
        $rodape .= '<td class="c-sessao">' . $t . '</td>';
    }
    $acessos = $sem_conta
        ? '<form method="post" class="espaco-topo">' . campo_csrf() . '<input type="hidden" name="acao" value="acessos"><button class="botao botao-secundario" type="submit">Criar o acesso e enviar a ligação a ' . $sem_conta . ' formando(s)</button>
          <p class="ajuda">Para as inscrições confirmadas antes da área do formando ou com a ligação expirada. Cada formando recebe por email uma ligação, válida 7 dias, para definir a palavra-passe.</p></form>' : '';
    return '<h2 class="espaco-topo" id="presencas">Presenças</h2>
      <p class="texto-suave">Os formandos registam a presença na área do formando, com o botão "Estou presente", de 2 horas antes do início até 1 dia depois do fim de cada sessão. Aqui pode marcar ou retirar presenças em qualquer momento. As caixas a verde foram registadas pelo próprio formando.' . ($sem_email ? ' ' . $sem_email . ' formando(s) sem email: só a administração marca as presenças.' : '') . '</p>
      <form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="presencas">
        <div class="conteudo tabela-rolante"><table class="tabela-presencas grelha-presencas"><thead><tr><th scope="col">Formando</th>' . $cab . '</tr></thead><tbody>' . $linhas . '</tbody>
        <tfoot><tr><th scope="row">Presentes</th>' . $rodape . '</tr></tfoot></table></div>
        <div class="espaco-topo"><button class="botao" type="submit">Guardar presenças</button></div>
      </form>' . $acessos;
}
