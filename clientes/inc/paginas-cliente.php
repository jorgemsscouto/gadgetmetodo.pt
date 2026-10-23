<?php
// Área de clientes: páginas do cliente e páginas comuns aos dois perfis (inscrição e conta).

declare(strict_types=1);

// As seis ferramentas de IA generativa mais usadas; a opção "Outra" pede qual
const FERRAMENTAS_IA = ['ChatGPT', 'Google Gemini', 'Microsoft Copilot', 'Perplexity', 'Claude', 'DeepSeek'];

// Resposta à pergunta das ferramentas de IA: as escolhidas da lista e, se indicada, a outra ("Outra: nome")
function ferramentas_post(bool $ativo = true): array
{
    $marcadas = $ativo ? array_filter((array) ($_POST['ferramentas'] ?? []), 'is_string') : [];
    $outra = $ativo ? texto('ferramenta_outra', 80) : '';
    $lista = array_values(array_intersect(FERRAMENTAS_IA, $marcadas));
    if ($outra !== '') {
        $lista[] = 'Outra: ' . $outra;
    }
    return ['ferramentas' => $lista, 'ferramenta_outra' => $outra, 'outra_marcada' => in_array('Outra', $marcadas, true) || $outra !== ''];
}

function erro_ferramentas(array $v): string
{
    return !empty($v['outra_marcada']) && ($v['ferramenta_outra'] ?? '') === '' ? 'Indique qual é a outra ferramenta de IA que utiliza.' : '';
}

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
      ' . (($novas = formacoes_novas()) ? '<h2 class="espaco-topo">Novas formações</h2><p class="texto-suave">Sessões agendadas nos últimos ' . DIAS_NOVA . ' dias.</p>' . calendario($novas, 'botao_inscrever') : '') . '
      <h2 class="espaco-topo">Próximas formações com formandos inscritos</h2>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Formando</th><th>Formação</th><th>Estado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há formandos inscritos em formações futuras.</p>'), $u['cliente_nome']);
}

function cliente_formacoes(): void
{
    pagina_area('calendario', 'Calendário de formações', '
      <p class="lead">Sessões agendadas dos cursos da Gadgetmetodo. Em cada sessão, abra "Programa e temas" para ver o conteúdo. Nos cursos de inteligência artificial, a inscrição inclui um diagnóstico curto do uso de IA pelo formando, para ajustar os exemplos e os exercícios ao nível do grupo.</p>
      ' . calendario(proximas_formacoes(), 'botao_inscrever') . '
      <p class="texto-suave espaco-topo">Precisa de uma data ou de um programa à medida para a sua equipa? Escreva para <a href="mailto:info@gadgetmetodo.pt">info@gadgetmetodo.pt</a>.</p>', 'Formações');
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
    $diag = com_diagnostico($f);
    $clientes = $admin ? array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id') : [];
    $erros = [];
    $v = ['formando' => '', 'email' => '', 'funcao' => '', 'expectativas' => '', 'ferramentas' => [], 'diag' => [], 'cliente_id' => 0];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $v = [
            'formando' => texto('formando', 120), 'email' => texto('email', 160), 'funcao' => texto('funcao', 120),
            'expectativas' => texto('expectativas', 1500, true),
            'diag' => array_filter(array_intersect_key((array) ($_POST['diag'] ?? []), perguntas_diagnostico(com_nivel($f))), 'is_string'),
            'cliente_id' => $admin ? (int) ($_POST['cliente_id'] ?? 0) : (int) $u['cliente_id'],
        ] + ferramentas_post($diag);
        $respostas = $diag ? respostas_diagnostico(com_nivel($f)) : [];
        if ($v['formando'] === '') {
            $erros[] = 'Indique o nome do formando.';
        }
        if ($e = erro_ferramentas($v)) {
            $erros[] = $e;
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
            // A Gadgetmetodo pode inscrever acima do limite de vagas; o cliente não
            $r = gravar_inscricao($f, ['cliente_id' => $v['cliente_id'], 'utilizador_id' => $u['id'], 'origem' => 'area'] + $v, $respostas, $admin);
            if ($r['erro']) {
                $erros[] = $r['erro'];
            } else {
                emails_nova_inscricao($r['id']);
                $ind = $respostas ? curso_indicado($respostas, curso((int) $f['curso_id'])) : null;
                aviso('Inscrição de ' . h($v['formando']) . ' registada.' . ($r['nivel'] ? ' Resultado do diagnóstico: <strong>' . h($r['nivel']) . '</strong> (' . $r['pontuacao'] . ' em 24).' : '')
                    . ($ind ? ' ' . h(frase_indicacao($ind, curso((int) $f['curso_id']), false)) : '') . ' Recebe a confirmação por email.');
                redirecionar('inscricao', ['id' => $r['id']]);
            }
        }
    }
    $restantes = max(0, (int) $f['vagas'] - inscritos((int) $f['id']));
    pagina_area('calendario', 'Inscrever formando', resumo_sessao($f, $restantes, false) . '
      ' . ($erros ? '<div class="aviso aviso-erro">' . h(implode(' ', $erros)) . '</div>' : '') . '
      <form method="post" class="formulario">' . campo_csrf() . '
        <h2 class="mb-0">1. Dados do formando</h2>
        <div class="campos-2">
          ' . ($admin ? campo_select('cliente_id', 'Cliente *', $clientes, $v['cliente_id'], 'Escolha o cliente', 'required') : '') . '
          ' . campo('formando', 'Nome completo *', $v['formando'], 'text', 'required maxlength="120" autocomplete="off"') . '
          ' . campo('email', 'Email profissional', $v['email'], 'email', 'maxlength="160" autocomplete="off"') . '
          ' . campo('funcao', 'Função ou departamento', $v['funcao'], 'text', 'maxlength="120"') . '
        </div>
        ' . bloco_diagnostico_form($f, $v, 2, 'Devem ser respondidas pelo formando ou com base no que ele indicar.') . '
        ' . campo('expectativas', 'O que espera da formação? Há algum caso concreto do seu trabalho que gostaria de ver tratado?', $v['expectativas'], 'textarea', 'maxlength="1500"') . '
        <label class="opcao consentimento"><input type="checkbox" name="consentimento" value="1" required> <span>Confirmo que o formando foi informado de que estes dados são tratados pela Gadgetmetodo, Lda. para organizar a formação e adaptar os conteúdos, nos termos da <a href="/privacidade/" target="_blank" rel="noopener">Política de privacidade</a>. *</span></label>
        <div><button class="botao" type="submit">Enviar inscrição</button></div>
      </form>', 'Formações');
}

// Resumo da sessão no topo dos formulários de inscrição, com o programa e os temas
function resumo_sessao(array $f, int $restantes, bool $publico): string
{
    $vagas = $publico ? ($restantes <= 3 ? 'Últimas vagas' : $restantes . ' vagas disponíveis') : $restantes . ' vaga(s) disponível(eis)';
    $curso = curso((int) ($f['curso_id'] ?? 0));
    return '<div class="resumo-formacao"><strong>' . h($f['titulo']) . '</strong> · ' . h(nome_modulo($f)) . '<br>' . h(horario($f)) . ' · ' . h(MODALIDADES[$f['modalidade']] ?? '') . ($f['local'] ? ' · ' . h($f['local']) : '') . '<br><span class="texto-suave">' . h($vagas) . '</span>'
        . ($publico && $curso ? ' · <a href="' . h(link_curso_publico($curso)) . '">Ver a página do curso</a>' : '') . sessoes_html($f, true) . programa_html($f, $publico) . '</div>';
}

// Perguntas do diagnóstico de IA e ferramentas usadas, só nos cursos que o têm ativo
// Nos cursos de um percurso, o diagnóstico inclui as perguntas de nível e indica o curso mais adequado
function bloco_diagnostico_form(array $f, array $v, int $numero, string $quem): string
{
    if (!com_diagnostico($f)) {
        return '';
    }
    $nivel = com_nivel($f);
    $ferramentas = '';
    foreach (array_merge(FERRAMENTAS_IA, ['Outra']) as $nome) {
        $marcada = $nome === 'Outra' ? !empty($v['outra_marcada']) : in_array($nome, $v['ferramentas'] ?? [], true);
        $ferramentas .= '<label class="opcao"><input type="checkbox" name="ferramentas[]" value="' . h($nome) . '"' . ($marcada ? ' checked' : '') . '> ' . h($nome) . '</label>';
    }
    return '<h2 class="mb-0 espaco-topo">' . $numero . '. Diagnóstico ' . ($nivel ? 'de nível' : 'de utilização de IA') . '</h2>
        <p class="texto-suave mb-0">' . ($nivel
            ? count(perguntas_diagnostico(true)) . ' perguntas, cerca de três minutos. ' . h($quem) . ' Não há respostas certas: servem para indicar o curso do percurso mais adequado ao ponto de partida do formando e para ajustar os exemplos e os exercícios ao grupo.'
            : 'Oito perguntas, cerca de dois minutos. ' . h($quem) . ' Não há respostas certas: servem para ajustar os exemplos e os exercícios ao ponto de partida do grupo.') . '</p>
        ' . campos_diagnostico($v['diag'], $nivel) . '
        <fieldset class="campo-grupo"><legend>Que ferramentas de IA utiliza?</legend>
          <p class="ajuda">Pode escolher várias. Se ainda não utiliza nenhuma, deixe em branco.</p>
          <div class="opcoes opcoes-linha">' . $ferramentas . '</div>
          ' . campo('ferramenta_outra', 'Se escolheu "Outra", indique qual', (string) ($v['ferramenta_outra'] ?? ''), 'text', 'maxlength="80"') . '
        </fieldset>';
}

function link_curso_publico(array $curso): string
{
    return '/formacoes/curso.php?c=' . (int) $curso['id'];
}

// Ficha do curso em PDF, gerada no servidor
function link_ficha(array $curso): string
{
    return '/formacoes/ficha.php?c=' . (int) $curso['id'];
}

function link_pre_inscricao(int $curso_id): string
{
    return '/formacoes/pre-inscricao.php' . ($curso_id ? '?c=' . $curso_id : '');
}

// Sessões abertas, futuras e com vagas de um curso
function sessoes_com_vagas(int $curso_id): array
{
    $r = [];
    foreach (consulta("SELECT * FROM formacoes WHERE curso_id = ? AND estado = 'aberta' AND inicio >= ? ORDER BY inicio", [$curso_id, date('Y-m-d 00:00:00')]) as $f) {
        if ((int) $f['vagas'] - inscritos((int) $f['id']) > 0) {
            $r[] = $f;
        }
    }
    return $r;
}

// Quem recebe as confirmações de uma inscrição: o utilizador do cliente que a fez ou, no site, o contacto indicado
function contacto_inscricao(array $i): ?array
{
    if (($i['autor_papel'] ?? '') === 'cliente' && !empty($i['autor_email'])) {
        return ['email' => (string) $i['autor_email'], 'nome' => (string) $i['autor'], 'area' => true];
    }
    if (($i['origem'] ?? '') === 'site' && !empty($i['contacto_email'])) {
        return ['email' => (string) $i['contacto_email'], 'nome' => (string) $i['contacto_nome'], 'area' => false];
    }
    return null;
}

const SQL_INSCRICAO = 'SELECT i.*, ' . SQL_CLIENTE . ', f.titulo, f.modulo, f.curso_id, f.modulo_id, f.reuniao, f.inicio, f.fim, f.modalidade, f.local, f.estado AS estado_formacao, u.nome AS autor, u.email AS autor_email, u.papel AS autor_papel FROM inscricoes i LEFT JOIN clientes c ON c.id = i.cliente_id JOIN formacoes f ON f.id = i.formacao_id LEFT JOIN utilizadores u ON u.id = i.utilizador_id WHERE i.id = ?';

// Grava uma inscrição numa transação, para que duas inscrições em simultâneo não ultrapassem as vagas.
// Devolve o identificador e o resultado do diagnóstico, ou a mensagem de erro.
function gravar_inscricao(array $f, array $v, array $respostas, bool $sem_limite_vagas): array
{
    $pdo = bd();
    $pdo->exec('BEGIN IMMEDIATE');
    $erro = '';
    if ($v['email'] !== '' && linha_bd("SELECT id FROM inscricoes WHERE formacao_id = ? AND email = ? COLLATE NOCASE AND estado != 'cancelada'", [$f['id'], $v['email']])) {
        $erro = 'Este formando já está inscrito nesta formação.';
    } elseif (!$sem_limite_vagas && inscritos((int) $f['id']) >= (int) $f['vagas']) {
        $erro = 'Já não há vagas nesta formação.';
    }
    if ($erro) {
        $pdo->exec('ROLLBACK');
        return ['erro' => $erro];
    }
    $a = $respostas ? avaliar_diagnostico($respostas) : ['pontuacao' => null, 'responsavel' => null, 'nivel' => null, 'recomendacao' => ''];
    $id = executar('INSERT INTO inscricoes (formacao_id, cliente_id, utilizador_id, origem, tipo_cliente, empresa, nif, contacto_nome, contacto_email, contacto_telefone, formando, email, funcao, respostas, pontuacao, responsavel, nivel, ferramentas, expectativas, estado, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$f['id'], $v['cliente_id'] ?: null, $v['utilizador_id'] ?? null, $v['origem'], ($v['tipo_cliente'] ?? '') ?: null, ($v['empresa'] ?? '') ?: null, ($v['nif'] ?? '') ?: null, $v['contacto_nome'] ?? null, $v['contacto_email'] ?? null, $v['contacto_telefone'] ?? null,
         $v['formando'], $v['email'], $v['funcao'], $respostas ? json_encode($respostas) : null, $a['pontuacao'], $a['responsavel'], $a['nivel'], implode(', ', $v['ferramentas']), $v['expectativas'], 'pendente', agora()]);
    $pdo->exec('COMMIT');
    return ['erro' => '', 'id' => $id] + $a;
}

function emails_nova_inscricao(int $id): void
{
    $i = linha_bd(SQL_INSCRICAO, [$id]);
    if (!$i) {
        return;
    }
    $cfg = cfg();
    $site = rtrim((string) ($cfg['site'] ?? 'https://www.gadgetmetodo.pt'), '/');
    $publica = $i['origem'] === 'site';
    $quando = horario($i) . ' · ' . (MODALIDADES[$i['modalidade']] ?? '') . ($i['local'] ? ' · ' . $i['local'] : '');
    $a = $i['respostas'] ? avaliar_diagnostico((array) json_decode((string) $i['respostas'], true)) : null;
    $recomendacao = $a ? recomendacao_de($i) : '';
    $ind = $a ? curso_indicado((array) json_decode((string) $i['respostas'], true), curso((int) $i['curso_id'])) : null;
    $indicacao = $ind ? frase_indicacao($ind, curso((int) $i['curso_id'])) : '';
    $link_area = $site . '/clientes/' . url('inscricao', ['id' => $id]);
    $contacto = contacto_inscricao($i);
    $linhas = [
        'Formação' => $i['titulo'] . ' (' . nome_modulo($i) . ')', 'Data' => $quando,
        'Origem' => $publica ? 'Inscrição pública no site, por associar a um cliente' : ORIGENS['area'],
        'Cliente' => $i['cliente'] . ($i['cliente_tipo'] === 'particular' ? ' (particular)' : '') . ($i['nif'] ? ' (NIF ' . $i['nif'] . ')' : ''),
        'Contacto' => $publica ? trim($i['contacto_nome'] . ' · ' . $i['contacto_email'] . ($i['contacto_telefone'] ? ' · ' . $i['contacto_telefone'] : '')) : '',
        'Formando' => $i['formando'], 'Email' => $i['email'], 'Função' => $i['funcao'],
        'Diagnóstico' => $i['nivel'] ? $i['nivel'] . ' (' . $i['pontuacao'] . ' em 24; uso responsável ' . $i['responsavel'] . ' em 9)' : '',
        'Ferramentas' => $i['ferramentas'], 'Expectativas' => $i['expectativas'],
        'Inscrito por' => $i['autor'] ? $i['autor'] . ' (' . $i['autor_email'] . ')' : '',
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
    $botao = function (string $href, string $rotulo) {
        return '<p style="margin:22px 0 0;"><a href="' . h($href) . '" style="display:inline-block;padding:11px 20px;background:#00636A;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:6px;">' . h($rotulo) . '</a></p>';
    };

    // Aviso interno para a Gadgetmetodo
    $destino = (string) ($cfg['destinatario'] ?? 'info@gadgetmetodo.pt');
    enviar_aviso($destino, ($publica ? 'Nova inscrição pelo site: ' : 'Nova inscrição: ') . $i['formando'] . ' (' . $i['cliente'] . '), ' . data_hora($i['inicio'], false), 'Nova inscrição em formação',
        '<p style="margin:0 0 16px;">Foi registada uma nova inscrição, pendente de confirmação.' . ($publica ? ' Veio do site público: associe-a a um cliente na área reservada.' : '') . '</p>' . $tabela
        . ($recomendacao ? '<p style="margin:16px 0 0;"><strong>Recomendação:</strong> ' . h($recomendacao) . '</p>' : '') . $botao($link_area, 'Ver inscrição na área reservada'),
        "Foi registada uma nova inscrição, pendente de confirmação.\n\n" . $texto . ($recomendacao ? "\nRecomendação: " . $recomendacao : '') . "\n\n" . $link_area,
        $contacto['email'] ?? '', (string) ($cfg['bcc'] ?? ''));

    // Confirmação de receção para quem fez a inscrição
    if ($contacto) {
        [$datas_html, $datas_texto] = datas_email($i);
        $ver = $contacto['area'] ? $botao($link_area, 'Ver inscrição na área reservada') : ($i['curso_id'] ? $botao($site . link_curso_publico(['id' => $i['curso_id']]), 'Ver a página do curso') : $botao($site . '/formacoes/', 'Ver a área de formação'));
        $alterar = $contacto['area'] ? 'Para alterar ou cancelar a inscrição, use a área reservada ou responda a este email.' : 'Para alterar ou cancelar a inscrição, basta responder a este email.';
        enviar_aviso($contacto['email'], 'Inscrição recebida: ' . $i['formando'] . ', ' . $i['titulo'] . ' de ' . data_hora($i['inicio'], false), 'Inscrição recebida',
            '<p style="margin:0 0 14px;">' . h(nome_curto($contacto['nome'])) . ',</p><p style="margin:0 0 16px;">Recebemos a inscrição de <strong>' . h($i['formando']) . '</strong> na formação ' . h($i['titulo']) . ', em ' . h($quando) . '. A Gadgetmetodo confirma a inscrição por email nos próximos dias úteis.</p>' . $datas_html
            . ($i['nivel'] ? '<p style="margin:0 0 16px;">O diagnóstico indica o nível <strong>' . h($i['nivel']) . '</strong> de utilização de IA. Vamos usá-lo para ajustar os exemplos e os exercícios ao ponto de partida do grupo.</p>' : '')
            . ($indicacao ? '<p style="margin:0 0 16px;">' . h($indicacao) . '</p>' : '') . $ver
            . '<p style="margin:22px 0 0;color:#687280;font-size:13px;">' . h($alterar) . '</p>',
            nome_curto($contacto['nome']) . ",\n\nRecebemos a inscrição de " . $i['formando'] . ' na formação ' . $i['titulo'] . ', em ' . $quando . ". A Gadgetmetodo confirma a inscrição por email nos próximos dias úteis." . $datas_texto . "\n\n" . ($i['nivel'] ? 'Nível de utilização de IA indicado pelo diagnóstico: ' . $i['nivel'] . ".\n\n" : '') . ($indicacao ? $indicacao . "\n\n" : '') . $alterar,
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
    $i = linha_bd(SQL_INSCRICAO, [$id]);
    $admin = eh_admin();
    if (!$i || (!$admin && ($i['cliente_id'] === null || (int) $i['cliente_id'] !== (int) utilizador()['cliente_id']))) {
        // A mesma resposta para inexistente e para alheia: não revela que a inscrição existe
        http_response_code(404);
        pagina_erro('Inscrição não encontrada', 'A inscrição pedida não existe ou não pertence ao seu cliente.');
    }
    $futura = strtotime($i['inicio']) >= strtotime('today');
    $contacto = contacto_inscricao($i);
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
            if (!empty($_POST['avisar']) && $novo !== $i['estado'] && $contacto) {
                avisar_estado_inscricao($i, $novo, $contacto);
                aviso('Estado atualizado e aviso enviado por email para ' . h($contacto['email']) . '.');
            } else {
                aviso('Estado atualizado.');
            }
        } elseif ($acao === 'associar' && $admin && $i['cliente_id'] === null) {
            // Inscrição feita no site: associa a um cliente existente ou cria o cliente com os dados indicados
            $escolha = (string) ($_POST['cliente_id'] ?? '');
            if ($escolha === 'novo') {
                // O cliente novo é a empresa indicada ou, se a inscrição for de um particular, quem a fez
                $cliente_id = executar('INSERT INTO clientes (tipo, nome, nif, contacto, email, telefone, notas, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$i['cliente_tipo'], $i['cliente'], $i['nif'], $i['contacto_nome'], $i['contacto_email'], $i['contacto_telefone'], 'Criado a partir de uma inscrição no site.', agora()]);
            } else {
                $cliente_id = (int) (linha_bd('SELECT id FROM clientes WHERE id = ?', [(int) $escolha])['id'] ?? 0);
            }
            if ($cliente_id) {
                executar('UPDATE inscricoes SET cliente_id = ? WHERE id = ?', [$cliente_id, $id]);
                aviso('Inscrição associada ao cliente. Os utilizadores desse cliente passam a vê-la na área reservada.');
            } else {
                aviso('Escolha o cliente.', 'erro');
            }
        }
        redirecionar('inscricao', ['id' => $id]);
    }
    $acoes = '';
    if ($admin) {
        $acoes = '<form method="post" class="formulario linha-form">' . campo_csrf() . '<input type="hidden" name="acao" value="estado">
          ' . campo_select('estado', 'Estado da inscrição', ESTADOS_INSCRICAO, $i['estado']) . '
          <label class="opcao consentimento"><input type="checkbox" name="avisar" value="1"' . ($contacto ? ' checked' : ' disabled') . '> Avisar por email quem fez a inscrição</label>
          <div><button class="botao" type="submit">Guardar estado</button></div></form>';
        if ($i['cliente_id'] === null) {
            $clientes = ['novo' => 'Criar cliente novo com estes dados'] + array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
            $acoes .= '<div class="aviso aviso-info">Inscrição feita no site público, ainda sem cliente associado.</div>
            <form method="post" class="formulario linha-form">' . campo_csrf() . '<input type="hidden" name="acao" value="associar">
              ' . campo_select('cliente_id', 'Associar a cliente', $clientes, '', 'Escolha o cliente', 'required') . '
              <div></div><div><button class="botao botao-secundario" type="submit">Associar</button></div></form>';
        }
    } elseif ($i['estado'] !== 'cancelada' && $futura) {
        $acoes = '<form method="post" class="espaco-topo">' . campo_csrf() . '<input type="hidden" name="acao" value="cancelar"><button class="botao botao-secundario" type="submit">Cancelar esta inscrição</button></form>';
    }
    $publica = $i['origem'] === 'site';
    $dados = [
        'Formação' => h($i['titulo']) . '<br><span class="texto-suave">' . h(nome_modulo($i)) . '</span>',
        'Data e local' => h(horario($i)) . '<br><span class="texto-suave">' . h(MODALIDADES[$i['modalidade']] ?? '') . ($i['local'] ? ' · ' . h($i['local']) : '') . '</span>',
        'Cliente' => cliente_html($i) . ($i['nif'] ? ' <span class="texto-suave">· NIF ' . h($i['nif']) . '</span>' : ''),
        'Formando' => h($i['formando']), 'Email' => h($i['email']), 'Função' => h($i['funcao']),
        'Estado' => estado_etiqueta($i['estado'], ESTADOS_INSCRICAO),
        'Sessão online' => $i['modalidade'] === 'presencial' || !$i['reuniao'] ? '' : ($admin || $i['estado'] === 'confirmada'
            ? '<a href="' . h($i['reuniao']) . '" target="_blank" rel="noopener">Entrar na reunião</a><br><span class="texto-suave">' . h($i['reuniao']) . '</span>'
            : '<span class="texto-suave">A ligação para a reunião é enviada com a confirmação da inscrição.</span>'),
        'Origem' => h(ORIGENS[$i['origem']] ?? $i['origem']) . (($pre = $admin ? linha_bd('SELECT id, criado, formandos FROM preinscricoes WHERE inscricao_id = ?', [$id]) : null)
            ? '<br><a href="' . h(url('preinscricao', ['id' => $pre['id']])) . '">Pré-inscrição de ' . h(data_hora($pre['criado'], false)) . '</a>' . ((int) $pre['formandos'] > 1 ? ' <span class="texto-suave">(' . (int) $pre['formandos'] . ' formandos previstos)</span>' : '') : ''),
        'Inscrito por' => $publica
            ? h($i['contacto_nome']) . ', ' . h(data_hora($i['criado'])) . '<br><span class="texto-suave"><a href="mailto:' . h($i['contacto_email']) . '">' . h($i['contacto_email']) . '</a>' . ($i['contacto_telefone'] ? ' · ' . h($i['contacto_telefone']) : '') . '</span>'
            : h($i['autor']) . ', ' . h(data_hora($i['criado'])),
    ];
    if (!$admin) {
        unset($dados['Origem']);
    }
    $dados = array_filter($dados, function ($x) { return $x !== ''; });
    $tabela = '';
    foreach ($dados as $k => $val) {
        $tabela .= '<tr><th scope="row">' . h($k) . '</th><td>' . $val . '</td></tr>';
    }
    pagina_area('inscricoes', $i['formando'], ($admin ? $acoes : '') . '
      <div class="conteudo"><table class="tabela-ficha"><tbody>' . $tabela . '</tbody></table></div>
      ' . ($i['respostas'] ? '<h2 class="espaco-topo">Diagnóstico de utilização de IA</h2>
      <div class="diagnostico-resultado">
        <div><span class="texto-suave">Nível</span>' . nivel_etiqueta($i['nivel']) . '</div>
        <div><span class="texto-suave">Pontuação</span><span><strong>' . (int) $i['pontuacao'] . '</strong> em 24</span></div>
        <div><span class="texto-suave">Uso responsável</span><span><strong>' . (int) $i['responsavel'] . '</strong> em 9</span></div>
      </div>
      ' . (($rec = recomendacao_de($i)) ? '<p><strong>Recomendação:</strong> ' . h($rec) . '</p>' : '') . '
      <div class="conteudo tabela-rolante">' . tabela_diagnostico($i) . '</div>' : '') . '
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

// Email a quem fez a inscrição com o novo estado; na confirmação, com o local e a ligação da sessão online (cópia ao formando)
function avisar_estado_inscricao(array $i, string $novo, array $contacto): void
{
    $frase = $novo === 'confirmada'
        ? 'A inscrição de ' . $i['formando'] . ' na formação ' . $i['titulo'] . ', em ' . horario($i) . ', está confirmada.'
        : 'A inscrição de ' . $i['formando'] . ' na formação ' . $i['titulo'] . ', em ' . horario($i) . ', passou ao estado: ' . strtolower(ESTADOS_INSCRICAO[$novo]) . '.';
    $local = $novo === 'confirmada' ? (MODALIDADES[$i['modalidade']] ?? '') . ($i['local'] ? ': ' . $i['local'] : '') : '';
    [$reuniao_html, $reuniao_texto] = $novo === 'confirmada' ? bloco_reuniao($i) : ['', ''];
    [$datas_html, $datas_texto] = datas_email($i);
    enviar_aviso($contacto['email'], ($novo === 'confirmada' ? 'Inscrição confirmada: ' : 'Inscrição atualizada: ') . $i['formando'] . ', ' . data_hora($i['inicio'], false), $novo === 'confirmada' ? 'Inscrição confirmada' : 'Inscrição atualizada',
        '<p style="margin:0 0 14px;">' . h(nome_curto($contacto['nome'])) . ',</p><p style="margin:0 0 14px;">' . h($frase) . '</p>' . $datas_html . ($local ? '<p style="margin:0 0 14px;"><strong>Local:</strong> ' . h($local) . '</p>' : '') . $reuniao_html . '<p style="margin:0;">Para qualquer questão, basta responder a este email.</p>',
        nome_curto($contacto['nome']) . ",\n\n" . $frase . $datas_texto . ($local ? "\n\nLocal: " . $local : '') . $reuniao_texto . "\n\nPara qualquer questão, basta responder a este email.",
        (string) (cfg()['destinatario'] ?? 'info@gadgetmetodo.pt'), '', $novo === 'confirmada' ? (string) $i['email'] : '');
}

// Datas de cada sessão, para os emails (HTML e texto); vazio quando a formação decorre numa só data
function datas_email(array $f): array
{
    $l = linhas_sessoes($f);
    if (!$l) {
        return ['', ''];
    }
    return ['<p style="margin:0 0 4px;"><strong>Datas das sessões</strong></p><ol style="margin:0 0 14px;padding-left:20px;">' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $l)) . '</ol>',
        "\n\nDatas das sessões:\n- " . implode("\n- ", $l)];
}

/* Ligação da reunião online e convites a candidatos */

// Bloco com a ligação da sessão online, para os emails de confirmação e de envio da ligação
function bloco_reuniao(array $i): array
{
    if (($i['modalidade'] ?? '') === 'presencial' || empty($i['reuniao'])) {
        return ['', ''];
    }
    $nome = stripos((string) $i['reuniao'], 'meet.google.com') !== false ? 'Google Meet' : 'reunião online';
    return [
        '<p style="margin:0 0 6px;"><strong>Sessão online (' . h($nome) . '):</strong> <a href="' . h($i['reuniao']) . '" style="color:#00636A;">' . h($i['reuniao']) . '</a></p>'
        . '<p style="margin:0 0 18px;"><a href="' . h($i['reuniao']) . '" style="display:inline-block;padding:11px 20px;background:#00636A;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:6px;">Entrar na sessão</a></p>',
        "\n\nSessão online (" . $nome . '): ' . $i['reuniao'],
    ];
}

// Envia a ligação da reunião a cada inscrição confirmada: ao formando e, com cópia, a quem o inscreveu
function enviar_reuniao_confirmados(array $f): int
{
    $enviados = 0;
    foreach (consulta("SELECT id FROM inscricoes WHERE formacao_id = ? AND estado = 'confirmada'", [$f['id']]) as $linha) {
        $i = linha_bd(SQL_INSCRICAO, [$linha['id']]);
        $contacto = contacto_inscricao($i);
        $para = $i['email'] ?: ($contacto['email'] ?? '');
        if ($para === '') {
            continue;
        }
        [$html, $texto] = bloco_reuniao($i);
        $nome = $i['email'] ? $i['formando'] : ($contacto['nome'] ?? $i['formando']);
        $frase = 'Segue a ligação para a sessão de ' . $i['titulo'] . ' (' . nome_modulo($i) . '), em ' . horario($i) . ', em que ' . ($i['email'] ? 'está inscrito(a)' : $i['formando'] . ' está inscrito(a)') . '.';
        enviar_aviso($para, 'Ligação para a sessão: ' . $i['titulo'] . ', ' . data_hora($i['inicio']), 'Ligação para a sessão online',
            '<p style="margin:0 0 14px;">' . h(nome_curto($nome)) . ',</p><p style="margin:0 0 16px;">' . h($frase) . '</p>' . $html . '<p style="margin:0;">Recomendamos entrar alguns minutos antes da hora de início. Para qualquer questão, basta responder a este email.</p>',
            nome_curto($nome) . ",\n\n" . $frase . $texto . "\n\nRecomendamos entrar alguns minutos antes da hora de início.",
            (string) (cfg()['destinatario'] ?? 'info@gadgetmetodo.pt'), '', $i['email'] ? ($contacto['email'] ?? '') : '');
        $enviados++;
    }
    return $enviados;
}

// Convite por email a um candidato, com a ligação para a página do curso ou para a inscrição numa sessão
function enviar_convite(string $nome, string $email, string $mensagem, string $assunto, string $resumo_html, string $resumo_texto, string $link, string $botao): void
{
    $cfg = cfg();
    $u = utilizador();
    enviar_aviso($email, $assunto, 'Convite para formação',
        '<p style="margin:0 0 14px;">' . h(nome_curto($nome)) . ',</p>'
        . ($mensagem !== '' ? '<p style="margin:0 0 16px;">' . nl2br(h($mensagem)) . '</p>' : '<p style="margin:0 0 16px;">Temos o gosto de o(a) convidar para a formação abaixo. Na página indicada encontra o programa, os temas de cada módulo e as datas disponíveis, e pode fazer a inscrição diretamente.</p>')
        . $resumo_html
        . '<p style="margin:22px 0 0;"><a href="' . h($link) . '" style="display:inline-block;padding:11px 20px;background:#00636A;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:6px;">' . h($botao) . '</a></p>'
        . '<p style="margin:18px 0 0;color:#687280;font-size:13px;">Se o botão não funcionar, copie este endereço para o navegador: ' . h($link) . '</p>'
        . '<p style="margin:18px 0 0;">Com os melhores cumprimentos,<br>' . h((string) ($u['nome'] ?? 'Gadgetmetodo')) . '<br>Gadgetmetodo, Lda.</p>',
        nome_curto($nome) . ",\n\n" . ($mensagem !== '' ? $mensagem : 'Temos o gosto de o(a) convidar para a formação abaixo. Na página indicada encontra o programa, os temas e as datas disponíveis, e pode fazer a inscrição diretamente.') . "\n\n" . $resumo_texto . "\n\n" . $botao . ': ' . $link . "\n\nCom os melhores cumprimentos,\n" . ($u['nome'] ?? 'Gadgetmetodo') . "\nGadgetmetodo, Lda.",
        (string) ($u['email'] ?? ($cfg['destinatario'] ?? 'info@gadgetmetodo.pt')), (string) ($cfg['destinatario'] ?? 'info@gadgetmetodo.pt'));
}
