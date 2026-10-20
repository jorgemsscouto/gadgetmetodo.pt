<?php
// Área de clientes: páginas do cliente e páginas comuns aos dois perfis (inscrição e conta).

declare(strict_types=1);

const FERRAMENTAS_IA = ['ChatGPT', 'Microsoft Copilot', 'Google Gemini', 'Claude', 'Perplexity', 'Outras'];

function proximas_formacoes(): array
{
    return consulta("SELECT * FROM formacoes WHERE inicio >= ? AND estado != 'cancelada' ORDER BY inicio", [date('Y-m-d 00:00:00')]);
}

function botao_inscrever(array $f, int $restantes): string
{
    if ($f['estado'] !== 'aberta') {
        return '';
    }
    return $restantes > 0
        ? '<a class="botao" href="' . h(url('inscrever', ['formacao' => $f['id']])) . '">Inscrever formando</a>'
        : '<span class="texto-suave">Sem vagas</span>';
}

function cliente_painel(): void
{
    $u = utilizador();
    $minhas = consulta("SELECT i.*, f.titulo, f.inicio, f.fim, f.modalidade, f.local FROM inscricoes i JOIN formacoes f ON f.id = i.formacao_id WHERE i.cliente_id = ? AND f.inicio >= ? AND i.estado != 'cancelada' ORDER BY f.inicio", [$u['cliente_id'], date('Y-m-d 00:00:00')]);
    $linhas = '';
    foreach ($minhas as $i) {
        $linhas .= '<tr><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . h($i['titulo']) . '<br><span class="texto-suave">' . h(horario($i)) . '</span></td><td>' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</td></tr>';
    }
    pagina_area('painel', 'Bem-vindo, ' . $u['nome'], '
      <p class="lead">Nesta área pode consultar o calendário das formações AI Essentials, inscrever formandos da ' . h($u['cliente_nome']) . ' e acompanhar o estado das inscrições.</p>
      <div class="acoes" style="margin-top:0"><a class="botao" href="' . h(url('calendario')) . '">Ver formações e inscrever</a><a class="botao botao-secundario" href="' . h(url('inscricoes')) . '">As nossas inscrições</a></div>
      <h2 class="espaco-topo">Próximas formações com formandos inscritos</h2>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Formando</th><th>Formação</th><th>Estado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há formandos inscritos em formações futuras.</p>'), $u['cliente_nome']);
}

function cliente_formacoes(): void
{
    pagina_area('calendario', 'Calendário de formações', '
      <p class="lead">Formações AI Essentials agendadas. Cada inscrição inclui um diagnóstico curto do uso de inteligência artificial pelo formando, que permite ajustar os exemplos e os exercícios ao nível do grupo.</p>
      ' . calendario(proximas_formacoes(), 'botao_inscrever') . '
      <p class="texto-suave espaco-topo">Precisa de uma data ou de um programa à medida para a sua equipa? Escreva para <a href="mailto:info@gadgetmetodo.pt">info@gadgetmetodo.pt</a>.</p>', 'AI Essentials');
}

function cliente_inscricoes(): void
{
    $u = utilizador();
    $lista = consulta('SELECT i.*, f.titulo, f.inicio, f.fim FROM inscricoes i JOIN formacoes f ON f.id = i.formacao_id WHERE i.cliente_id = ? ORDER BY f.inicio DESC, i.formando', [$u['cliente_id']]);
    if (!empty($_GET['exportar'])) {
        $linhas = [];
        foreach ($lista as $i) {
            $linhas[] = [$i['titulo'], data_hora($i['inicio']), $i['formando'], $i['email'], $i['funcao'], ESTADOS_INSCRICAO[$i['estado']] ?? $i['estado'], $i['nivel'], data_hora($i['criado'])];
        }
        exportar_csv('inscricoes', ['Formação', 'Data da formação', 'Formando', 'Email', 'Função', 'Estado', 'Nível de IA', 'Data da inscrição'], $linhas);
    }
    $linhas = '';
    foreach ($lista as $i) {
        $linhas .= '<tr><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a><br><span class="texto-suave">' . h($i['funcao']) . '</span></td><td>' . h($i['titulo']) . '<br><span class="texto-suave">' . h(horario($i)) . '</span></td><td>' . nivel_etiqueta($i['nivel']) . '</td><td>' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</td></tr>';
    }
    pagina_area('inscricoes', 'Inscrições', '
      <p class="texto-suave">Formandos da ' . h($u['cliente_nome']) . ' inscritos nas formações. A Gadgetmetodo confirma cada inscrição por email.</p>
      ' . ($linhas ? '<p><a class="botao botao-secundario" href="' . h(url('inscricoes', ['exportar' => 1])) . '">Exportar para Excel</a></p><div class="conteudo tabela-rolante"><table><thead><tr><th>Formando</th><th>Formação</th><th>Nível de IA</th><th>Estado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há inscrições. <a href="' . h(url('calendario')) . '">Ver formações</a>.</p>'), utilizador()['cliente_nome']);
}

/* Inscrição de um formando, com diagnóstico (cliente e Gadgetmetodo) */

function pagina_inscrever(): void
{
    $u = utilizador();
    $admin = eh_admin();
    $f = linha_bd('SELECT * FROM formacoes WHERE id = ?', [(int) ($_GET['formacao'] ?? 0)]);
    if (!$f || $f['estado'] === 'cancelada' || (!$admin && ($f['estado'] !== 'aberta' || strtotime($f['inicio']) < strtotime('today')))) {
        pagina_erro('Inscrições indisponíveis', 'Esta formação não existe ou já não aceita inscrições.');
    }
    $clientes = $admin ? array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id') : [];
    $erros = [];
    $v = ['formando' => '', 'email' => '', 'funcao' => '', 'expectativas' => '', 'ferramentas' => [], 'diag' => [], 'cliente_id' => 0];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $v = [
            'formando' => texto('formando', 120), 'email' => texto('email', 160), 'funcao' => texto('funcao', 120),
            'expectativas' => texto('expectativas', 1500, true),
            'ferramentas' => array_values(array_intersect(FERRAMENTAS_IA, array_filter((array) ($_POST['ferramentas'] ?? []), 'is_string'))),
            'diag' => array_filter(array_intersect_key((array) ($_POST['diag'] ?? []), DIAGNOSTICO), 'is_string'),
            'cliente_id' => $admin ? (int) ($_POST['cliente_id'] ?? 0) : (int) $u['cliente_id'],
        ];
        $respostas = respostas_diagnostico();
        if ($v['formando'] === '') {
            $erros[] = 'Indique o nome do formando.';
        }
        if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'O email do formando não é válido.';
        }
        if ($admin && !isset($clientes[$v['cliente_id']])) {
            $erros[] = 'Escolha o cliente.';
        }
        if ($respostas === null) {
            $erros[] = 'Responda a todas as perguntas do diagnóstico.';
        }
        if (empty($_POST['consentimento'])) {
            $erros[] = 'Confirme que o formando foi informado do tratamento dos dados.';
        }
        if (!$erros) {
            $pdo = bd();
            $pdo->exec('BEGIN IMMEDIATE');
            $repetida = $v['email'] !== '' && linha_bd("SELECT id FROM inscricoes WHERE formacao_id = ? AND email = ? COLLATE NOCASE AND estado != 'cancelada'", [$f['id'], $v['email']]);
            if ($repetida) {
                $erros[] = 'Este formando já está inscrito nesta formação.';
            } elseif (!$admin && inscritos((int) $f['id']) >= (int) $f['vagas']) {
                // A Gadgetmetodo pode inscrever acima do limite de vagas; o cliente não
                $erros[] = 'Já não há vagas nesta formação.';
            }
            if ($erros) {
                $pdo->exec('ROLLBACK');
            } else {
                $a = avaliar_diagnostico($respostas);
                $id = executar('INSERT INTO inscricoes (formacao_id, cliente_id, utilizador_id, formando, email, funcao, respostas, pontuacao, responsavel, nivel, ferramentas, expectativas, estado, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$f['id'], $v['cliente_id'], $u['id'], $v['formando'], $v['email'], $v['funcao'], json_encode($respostas), $a['pontuacao'], $a['responsavel'], $a['nivel'], implode(', ', $v['ferramentas']), $v['expectativas'], 'pendente', agora()]);
                $pdo->exec('COMMIT');
                emails_nova_inscricao($id);
                aviso('Inscrição de ' . h($v['formando']) . ' registada. Resultado do diagnóstico: <strong>' . h($a['nivel']) . '</strong> (' . $a['pontuacao'] . ' em 24). Recebe a confirmação por email.');
                redirecionar('inscricao', ['id' => $id]);
            }
        }
    }
    $restantes = max(0, (int) $f['vagas'] - inscritos((int) $f['id']));
    $ferramentas = '';
    foreach (FERRAMENTAS_IA as $nome) {
        $ferramentas .= '<label class="opcao"><input type="checkbox" name="ferramentas[]" value="' . h($nome) . '"' . (in_array($nome, $v['ferramentas'], true) ? ' checked' : '') . '> ' . h($nome) . '</label>';
    }
    pagina_area('calendario', 'Inscrever formando', '
      <div class="resumo-formacao"><strong>' . h($f['titulo']) . '</strong> · ' . h(MODULOS[$f['modulo']] ?? '') . '<br>' . h(horario($f)) . ' · ' . h(MODALIDADES[$f['modalidade']] ?? '') . ($f['local'] ? ' · ' . h($f['local']) : '') . '<br><span class="texto-suave">' . $restantes . ' vaga(s) disponível(eis)</span></div>
      ' . ($erros ? '<div class="aviso aviso-erro">' . h(implode(' ', $erros)) . '</div>' : '') . '
      <form method="post" class="formulario">' . campo_csrf() . '
        <h2 class="mb-0">1. Dados do formando</h2>
        <div class="campos-2">
          ' . ($admin ? campo_select('cliente_id', 'Cliente *', $clientes, $v['cliente_id'], 'Escolha o cliente', 'required') : '') . '
          ' . campo('formando', 'Nome completo *', $v['formando'], 'text', 'required maxlength="120" autocomplete="off"') . '
          ' . campo('email', 'Email profissional', $v['email'], 'email', 'maxlength="160" autocomplete="off"') . '
          ' . campo('funcao', 'Função ou departamento', $v['funcao'], 'text', 'maxlength="120"') . '
        </div>
        <h2 class="mb-0 espaco-topo">2. Diagnóstico de utilização de IA</h2>
        <p class="texto-suave mb-0">Oito perguntas, cerca de dois minutos. Devem ser respondidas pelo formando ou com base no que ele indicar. Não há respostas certas: o objetivo é ajustar a formação ao ponto de partida de cada pessoa.</p>
        ' . campos_diagnostico($v['diag']) . '
        <fieldset class="campo-grupo"><legend>Que ferramentas de IA usa atualmente?</legend><div class="opcoes opcoes-linha">' . $ferramentas . '</div></fieldset>
        ' . campo('expectativas', 'O que espera da formação? Há algum caso concreto do seu trabalho que gostaria de ver tratado?', $v['expectativas'], 'textarea', 'maxlength="1500"') . '
        <label class="opcao consentimento"><input type="checkbox" name="consentimento" value="1" required> <span>Confirmo que o formando foi informado de que estes dados são tratados pela Gadgetmetodo, Lda. para organizar a formação e adaptar os conteúdos, nos termos da <a href="/privacidade/" target="_blank" rel="noopener">Política de privacidade</a>. *</span></label>
        <div><button class="botao" type="submit">Enviar inscrição</button></div>
      </form>', 'AI Essentials');
}

function emails_nova_inscricao(int $id): void
{
    $i = linha_bd('SELECT i.*, c.nome AS cliente, f.titulo, f.modulo, f.inicio, f.fim, f.modalidade, f.local, u.nome AS autor, u.email AS autor_email, u.papel AS autor_papel FROM inscricoes i JOIN clientes c ON c.id = i.cliente_id JOIN formacoes f ON f.id = i.formacao_id LEFT JOIN utilizadores u ON u.id = i.utilizador_id WHERE i.id = ?', [$id]);
    if (!$i) {
        return;
    }
    $cfg = cfg();
    $quando = horario($i) . ' · ' . (MODALIDADES[$i['modalidade']] ?? '') . ($i['local'] ? ' · ' . $i['local'] : '');
    $a = avaliar_diagnostico((array) json_decode((string) $i['respostas'], true));
    $link = rtrim((string) ($cfg['site'] ?? 'https://www.gadgetmetodo.pt'), '/') . '/clientes/' . url('inscricao', ['id' => $id]);
    $linhas = [
        'Formação' => $i['titulo'] . ' (' . (MODULOS[$i['modulo']] ?? '') . ')', 'Data' => $quando, 'Cliente' => $i['cliente'],
        'Formando' => $i['formando'], 'Email' => $i['email'], 'Função' => $i['funcao'],
        'Diagnóstico' => $i['nivel'] . ' (' . $i['pontuacao'] . ' em 24; uso responsável ' . $i['responsavel'] . ' em 9)',
        'Ferramentas' => $i['ferramentas'], 'Expectativas' => $i['expectativas'], 'Inscrito por' => $i['autor'] . ' (' . $i['autor_email'] . ')',
    ];
    $tabela = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">';
    $texto = '';
    foreach ($linhas as $k => $val) {
        if ((string) $val === '') {
            continue;
        }
        $tabela .= '<tr><td style="padding:7px 12px 7px 0;border-bottom:1px solid #E5E7EB;color:#687280;width:130px;vertical-align:top;">' . h($k) . '</td><td style="padding:7px 0;border-bottom:1px solid #E5E7EB;">' . nl2br(h((string) $val)) . '</td></tr>';
        $texto .= $k . ': ' . $val . "\n";
    }
    $tabela .= '</table>';
    $botao = '<p style="margin:22px 0 0;"><a href="' . h($link) . '" style="display:inline-block;padding:11px 20px;background:#00636A;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:6px;">Ver inscrição na área de clientes</a></p>';

    // Aviso interno para a Gadgetmetodo
    $destino = (string) ($cfg['destinatario'] ?? 'info@gadgetmetodo.pt');
    enviar_aviso($destino, 'Nova inscrição: ' . $i['formando'] . ' (' . $i['cliente'] . '), ' . data_hora($i['inicio'], false), 'Nova inscrição em formação',
        '<p style="margin:0 0 16px;">Foi registada uma nova inscrição, pendente de confirmação.</p>' . $tabela
        . '<p style="margin:16px 0 0;"><strong>Recomendação:</strong> ' . h($a['recomendacao']) . '</p>' . $botao,
        "Foi registada uma nova inscrição, pendente de confirmação.\n\n" . $texto . "\nRecomendação: " . $a['recomendacao'] . "\n\n" . $link,
        (string) $i['autor_email'], (string) ($cfg['bcc'] ?? ''));

    // Confirmação de receção para o utilizador do cliente que inscreveu
    if ($i['autor_papel'] === 'cliente' && $i['autor_email']) {
        enviar_aviso((string) $i['autor_email'], 'Inscrição recebida: ' . $i['formando'] . ', ' . $i['titulo'] . ' de ' . data_hora($i['inicio'], false), 'Inscrição recebida',
            '<p style="margin:0 0 14px;">' . h(nome_curto((string) $i['autor'])) . ',</p><p style="margin:0 0 16px;">Recebemos a inscrição de <strong>' . h($i['formando']) . '</strong> na formação ' . h($i['titulo']) . ', em ' . h($quando) . '. A Gadgetmetodo confirma a inscrição por email nos próximos dias úteis.</p>'
            . '<p style="margin:0 0 16px;">O diagnóstico indica o nível <strong>' . h($i['nivel']) . '</strong> de utilização de IA. Vamos usá-lo para ajustar os exemplos e os exercícios ao ponto de partida do grupo.</p>' . $botao
            . '<p style="margin:22px 0 0;color:#687280;font-size:13px;">Para alterar ou cancelar a inscrição, use a área de clientes ou responda a este email.</p>',
            nome_curto((string) $i['autor']) . ",\n\nRecebemos a inscrição de " . $i['formando'] . ' na formação ' . $i['titulo'] . ', em ' . $quando . ". A Gadgetmetodo confirma a inscrição por email nos próximos dias úteis.\n\nNível de utilização de IA indicado pelo diagnóstico: " . $i['nivel'] . ".\n\nVer a inscrição: " . $link,
            $destino);
    }
}

function nome_curto(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    return $partes[0] ?? $nome;
}

/* Consulta de uma inscrição */

function pagina_inscricao(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $i = linha_bd('SELECT i.*, c.nome AS cliente, f.titulo, f.modulo, f.inicio, f.fim, f.modalidade, f.local, f.estado AS estado_formacao, u.nome AS autor, u.email AS autor_email, u.papel AS autor_papel FROM inscricoes i JOIN clientes c ON c.id = i.cliente_id JOIN formacoes f ON f.id = i.formacao_id LEFT JOIN utilizadores u ON u.id = i.utilizador_id WHERE i.id = ?', [$id]);
    $admin = eh_admin();
    if (!$i || (!$admin && (int) $i['cliente_id'] !== (int) utilizador()['cliente_id'])) {
        // A mesma resposta para inexistente e para alheia: não revela que a inscrição existe
        http_response_code(404);
        pagina_erro('Inscrição não encontrada', 'A inscrição pedida não existe ou não pertence ao seu cliente.');
    }
    $futura = strtotime($i['inicio']) >= strtotime('today');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'cancelar' && $i['estado'] !== 'cancelada' && ($admin || $futura)) {
            executar("UPDATE inscricoes SET estado = 'cancelada' WHERE id = ?", [$id]);
            if (!$admin) {
                $cfg = cfg();
                enviar_aviso((string) ($cfg['destinatario'] ?? 'info@gadgetmetodo.pt'), 'Inscrição cancelada pelo cliente: ' . $i['formando'] . ' (' . $i['cliente'] . ')', 'Inscrição cancelada pelo cliente',
                    '<p style="margin:0;">' . h(utilizador()['nome']) . ' cancelou a inscrição de <strong>' . h($i['formando']) . '</strong> (' . h($i['cliente']) . ') na formação ' . h($i['titulo']) . ' de ' . h(horario($i)) . '.</p>',
                    utilizador()['nome'] . ' cancelou a inscrição de ' . $i['formando'] . ' (' . $i['cliente'] . ') na formação ' . $i['titulo'] . ' de ' . horario($i) . '.', '', (string) ($cfg['bcc'] ?? ''));
            }
            aviso('Inscrição cancelada.');
        } elseif ($acao === 'estado' && $admin && isset(ESTADOS_INSCRICAO[$_POST['estado'] ?? ''])) {
            $novo = $_POST['estado'];
            executar('UPDATE inscricoes SET estado = ? WHERE id = ?', [$novo, $id]);
            if (!empty($_POST['avisar']) && $novo !== $i['estado'] && $i['autor_papel'] === 'cliente' && $i['autor_email']) {
                $frase = $novo === 'confirmada'
                    ? 'A inscrição de ' . $i['formando'] . ' na formação ' . $i['titulo'] . ', em ' . horario($i) . ', está confirmada.'
                    : 'A inscrição de ' . $i['formando'] . ' na formação ' . $i['titulo'] . ', em ' . horario($i) . ', passou ao estado: ' . strtolower(ESTADOS_INSCRICAO[$novo]) . '.';
                $local = $novo === 'confirmada' ? (MODALIDADES[$i['modalidade']] ?? '') . ($i['local'] ? ': ' . $i['local'] : '') : '';
                enviar_aviso((string) $i['autor_email'], ($novo === 'confirmada' ? 'Inscrição confirmada: ' : 'Inscrição atualizada: ') . $i['formando'] . ', ' . data_hora($i['inicio'], false), $novo === 'confirmada' ? 'Inscrição confirmada' : 'Inscrição atualizada',
                    '<p style="margin:0 0 14px;">' . h(nome_curto((string) $i['autor'])) . ',</p><p style="margin:0 0 14px;">' . h($frase) . '</p>' . ($local ? '<p style="margin:0 0 14px;"><strong>Local:</strong> ' . h($local) . '</p>' : '') . '<p style="margin:0;">Para qualquer questão, basta responder a este email.</p>',
                    nome_curto((string) $i['autor']) . ",\n\n" . $frase . ($local ? "\n\nLocal: " . $local : '') . "\n\nPara qualquer questão, basta responder a este email.",
                    (string) (cfg()['destinatario'] ?? 'info@gadgetmetodo.pt'));
                aviso('Estado atualizado e cliente avisado por email.');
            } else {
                aviso('Estado atualizado.');
            }
        }
        redirecionar('inscricao', ['id' => $id]);
    }
    $acoes = '';
    if ($admin) {
        $acoes = '<form method="post" class="formulario linha-form">' . campo_csrf() . '<input type="hidden" name="acao" value="estado">
          ' . campo_select('estado', 'Estado da inscrição', ESTADOS_INSCRICAO, $i['estado']) . '
          <label class="opcao consentimento"><input type="checkbox" name="avisar" value="1" checked> Avisar o cliente por email</label>
          <div><button class="botao" type="submit">Guardar estado</button></div></form>';
    } elseif ($i['estado'] !== 'cancelada' && $futura) {
        $acoes = '<form method="post" class="espaco-topo">' . campo_csrf() . '<input type="hidden" name="acao" value="cancelar"><button class="botao botao-secundario" type="submit">Cancelar esta inscrição</button></form>';
    }
    $dados = [
        'Formação' => h($i['titulo']) . '<br><span class="texto-suave">' . h(MODULOS[$i['modulo']] ?? '') . '</span>',
        'Data e local' => h(horario($i)) . '<br><span class="texto-suave">' . h(MODALIDADES[$i['modalidade']] ?? '') . ($i['local'] ? ' · ' . h($i['local']) : '') . '</span>',
        'Cliente' => h($i['cliente']), 'Formando' => h($i['formando']), 'Email' => h($i['email']), 'Função' => h($i['funcao']),
        'Estado' => estado_etiqueta($i['estado'], ESTADOS_INSCRICAO),
        'Inscrito por' => h($i['autor']) . ', ' . h(data_hora($i['criado'])),
    ];
    $tabela = '';
    foreach ($dados as $k => $val) {
        $tabela .= '<tr><th scope="row">' . h($k) . '</th><td>' . $val . '</td></tr>';
    }
    pagina_area('inscricoes', $i['formando'], ($acoes && $admin ? $acoes : '') . '
      <div class="conteudo"><table class="tabela-ficha"><tbody>' . $tabela . '</tbody></table></div>
      <h2 class="espaco-topo">Diagnóstico de utilização de IA</h2>
      <div class="diagnostico-resultado">
        <div><span class="texto-suave">Nível</span>' . nivel_etiqueta($i['nivel']) . '</div>
        <div><span class="texto-suave">Pontuação</span><span><strong>' . (int) $i['pontuacao'] . '</strong> em 24</span></div>
        <div><span class="texto-suave">Uso responsável</span><span><strong>' . (int) $i['responsavel'] . '</strong> em 9</span></div>
      </div>
      <p><strong>Recomendação:</strong> ' . h(recomendacao_de($i)) . '</p>
      <div class="conteudo tabela-rolante">' . tabela_diagnostico($i) . '</div>
      ' . ($i['ferramentas'] ? '<p><strong>Ferramentas usadas:</strong> ' . h($i['ferramentas']) . '</p>' : '') . '
      ' . ($i['expectativas'] ? '<p><strong>Expectativas:</strong> ' . nl2br(h($i['expectativas'])) . '</p>' : '') . '
      ' . (!$admin ? $acoes : '') . '
      <p class="espaco-topo"><a href="' . h(url('inscricoes')) . '">Voltar às inscrições</a></p>', 'Inscrição');
}

/* A minha conta: alteração da palavra-passe */

function pagina_conta(): void
{
    $u = utilizador();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $atual = (string) ($_POST['atual'] ?? '');
        $nova = (string) ($_POST['nova'] ?? '');
        $guardada = linha_bd('SELECT senha FROM utilizadores WHERE id = ?', [$u['id']])['senha'] ?? '';
        if (!password_verify($atual, $guardada)) {
            aviso('A palavra-passe atual não está correta.', 'erro');
        } elseif (strlen($nova) < 10) {
            aviso('A nova palavra-passe tem de ter pelo menos 10 caracteres.', 'erro');
        } elseif ($nova !== (string) ($_POST['repetir'] ?? '')) {
            aviso('As duas palavras-passe novas não coincidem.', 'erro');
        } else {
            executar('UPDATE utilizadores SET senha = ? WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            aviso('Palavra-passe alterada.');
        }
        redirecionar('conta');
    }
    pagina_area('conta', 'A minha conta', '
      <div class="conteudo"><table class="tabela-ficha"><tbody>
        <tr><th scope="row">Nome</th><td>' . h($u['nome']) . '</td></tr>
        <tr><th scope="row">Email</th><td>' . h($u['email']) . '</td></tr>
        <tr><th scope="row">Perfil</th><td>' . h(PAPEIS[$u['papel']]) . ($u['cliente_nome'] ? ' · ' . h($u['cliente_nome']) : '') . '</td></tr>
      </tbody></table></div>
      <h2 class="espaco-topo">Alterar palavra-passe</h2>
      <form method="post" class="formulario" style="max-width:440px">' . campo_csrf() . '
        <input type="text" name="utilizador" value="' . h($u['email']) . '" autocomplete="username" hidden>
        ' . campo('atual', 'Palavra-passe atual', '', 'password', 'required autocomplete="current-password"') . '
        ' . campo('nova', 'Nova palavra-passe (mínimo 10 caracteres)', '', 'password', 'required minlength="10" autocomplete="new-password"') . '
        ' . campo('repetir', 'Repetir a nova palavra-passe', '', 'password', 'required minlength="10" autocomplete="new-password"') . '
        <div><button class="botao" type="submit">Alterar palavra-passe</button></div>
      </form>', 'Conta');
}
