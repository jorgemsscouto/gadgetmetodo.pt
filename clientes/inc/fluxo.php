<?php
// Fluxo do formando (migração 001): o formando é uma pessoa (tabela formandos, uma por email; sem email, uma por
// inscrição), com 0..n diagnósticos e 0..n inscrições. Cada inscrição liga o formando a um curso e, depois de escolhida,
// a uma turma (formacoes), e tem a sua assiduidade, avaliação, certificado e estado.

declare(strict_types=1);

function formando(int $id): ?array
{
    return $id ? linha_bd('SELECT f.*, c.nome AS cliente FROM formandos f LEFT JOIN clientes c ON c.id = f.cliente_id WHERE f.id = ?', [$id]) : null;
}

function formando_por_email(string $email): ?array
{
    return trim($email) === '' ? null : linha_bd('SELECT * FROM formandos WHERE lower(trim(email)) = lower(trim(?))', [$email]);
}

// Formando com este email (criado se ainda não existir); sem email, um formando novo. Completa o cliente e o nome em falta.
function formando_para(string $nome, string $email, ?int $cliente_id = null, ?string $telefone = null): int
{
    $f = formando_por_email($email);
    if ($f) {
        if ($cliente_id && !$f['cliente_id']) {
            executar('UPDATE formandos SET cliente_id = ? WHERE id = ?', [$cliente_id, $f['id']]);
        }
        if ($telefone && !$f['telefone']) {
            executar('UPDATE formandos SET telefone = ? WHERE id = ?', [$telefone, $f['id']]);
        }
        return (int) $f['id'];
    }
    return executar('INSERT INTO formandos (nome, email, telefone, cliente_id, idioma, criado, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [trim($nome) !== '' ? trim($nome) : trim($email), trim($email) !== '' ? trim($email) : null, $telefone ?: null, $cliente_id ?: null,
         idioma_da_pagina_publica(), agora(), utilizador()['id'] ?? null]);
}

function inscricao_ativa(array $i): bool
{
    return in_array($i['estado'], ESTADOS_ATIVOS, true);
}

/* Etapas 1 a 3: diagnóstico, inscrição automática e email da inscrição */

// Cria o formando (ou usa o que já existe com este email) e envia-lhe o diagnóstico para o curso indicado.
// Devolve [id do diagnóstico, enviado?] ou lança uma exceção com o motivo.
function enviar_diagnostico_formando(int $formando_id, int $curso_id): array
{
    $f = formando($formando_id);
    $c = curso($curso_id);
    if (!$f || !$c) {
        throw new InvalidArgumentException('Formando ou curso inexistente.');
    }
    if (!filter_var((string) $f['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('O formando não tem um email válido.');
    }
    // Um diagnóstico por responder do mesmo formando é reaproveitado (curso atualizado e ligação nova)
    $d = linha_bd('SELECT id FROM diagnosticos WHERE formando_id = ? AND respondido IS NULL ORDER BY id DESC LIMIT 1', [$formando_id]);
    if ($d) {
        $id = (int) $d['id'];
        executar('UPDATE diagnosticos SET curso_id = ?, cursos = ?, nome = ?, email = ? WHERE id = ?', [$curso_id, json_encode([$curso_id]), $f['nome'], $f['email'], $id]);
    } else {
        $u = utilizador_por_email((string) $f['email']);
        $id = executar("INSERT INTO diagnosticos (utilizador_id, nome, email, curso_id, cursos, enviado, enviado_por, formando_id, origem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'email')",
            [$u['id'] ?? null, $f['nome'], trim((string) $f['email']), $curso_id, json_encode([$curso_id]), agora(), utilizador()['id'] ?? null, $formando_id]);
    }
    return [$id, enviar_ligacao_diagnostico($id)];
}

// Inscrição de um formando num curso, ainda sem turma (etapa 2). Se já tiver uma inscrição em curso nesse curso
// (Inscrito ou Em formação), devolve essa.
function criar_inscricao_curso(int $formando_id, int $curso_id, ?int $diagnostico_id, string $origem): int
{
    $existente = linha_bd("SELECT id FROM inscricoes WHERE formando_id = ? AND curso_id = ? AND estado IN ('inscrito', 'em_formacao') ORDER BY id DESC LIMIT 1", [$formando_id, $curso_id]);
    if ($existente) {
        if ($diagnostico_id) {
            executar('UPDATE inscricoes SET diagnostico_id = COALESCE(diagnostico_id, ?) WHERE id = ?', [$diagnostico_id, $existente['id']]);
        }
        return (int) $existente['id'];
    }
    $f = formando($formando_id);
    $d = $diagnostico_id ? linha_bd('SELECT * FROM diagnosticos WHERE id = ?', [$diagnostico_id]) : null;
    $respostas = $d ? json_decode((string) $d['respostas'], true) : null;
    $a = is_array($respostas) && $respostas && function_exists('avaliar_diagnostico') ? avaliar_diagnostico($respostas) : ['pontuacao' => null, 'responsavel' => null, 'nivel' => $d['nivel'] ?? null];
    $ferramentas = $d ? json_decode((string) $d['ferramentas'], true) : null;
    $cliente = !empty($f['cliente_id']) ? linha_bd('SELECT * FROM clientes WHERE id = ?', [(int) $f['cliente_id']]) : null;
    return executar('INSERT INTO inscricoes (formando_id, curso_id, formacao_id, cliente_id, utilizador_id, origem, tipo_cliente, contacto_nome, contacto_email, contacto_telefone,
        formando, email, funcao, respostas, pontuacao, responsavel, nivel, ferramentas, expectativas, estado, criado, idioma, diagnostico_id)
        VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $formando_id, $curso_id, $cliente['id'] ?? null, utilizador()['id'] ?? null, $origem, $cliente['tipo'] ?? null, $f['nome'], $f['email'], $f['telefone'],
        $f['nome'], $f['email'], '', is_array($respostas) && $respostas ? json_encode($respostas) : null, $a['pontuacao'], $a['responsavel'], $a['nivel'],
        is_array($ferramentas) ? implode(', ', $ferramentas) : '', '', 'inscrito', agora(), $f['idioma'] ?: idioma_da_pagina_publica(), $diagnostico_id,
    ]);
}

// Conta do formando na área reservada: criada se não existir (perfil Formando, com uma palavra-passe aleatória que nunca
// é comunicada). Devolve [utilizador, ligação de ativação, validade]; a ligação fica vazia para quem já ativou a conta
// ou tem outro perfil (entra com a sua palavra-passe).
function conta_formando(string $nome, string $email): array
{
    $u = utilizador_por_email($email);
    if (!$u) {
        $id = executar("INSERT INTO utilizadores (email, nome, senha, papel, criado) VALUES (?, ?, ?, 'formando', ?)",
            [trim($email), $nome, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), agora()]);
        $u = linha_bd('SELECT * FROM utilizadores WHERE id = ?', [$id]);
    }
    $ativada = $u['token_senha'] === null && $u['ultimo_acesso'] !== null;
    if ($u['papel'] !== 'formando' || $ativada || !(int) $u['ativo']) {
        return [$u, '', ''];
    }
    $token = bin2hex(random_bytes(24));
    $validade = date('Y-m-d H:i:s', time() + VALIDADE_LIGACAO_SENHA);
    executar('UPDATE utilizadores SET token_senha = ?, token_validade = ? WHERE id = ?', [hash('sha256', $token), $validade, $u['id']]);
    return [$u, link_definir_senha($token), $validade];
}

// Email da inscrição (etapa 3): inscrito no curso, nome de utilizador, ligação de ativação (nunca a palavra-passe) e
// pedido dos dados para o certificado
function enviar_email_inscricao(int $inscricao_id): bool
{
    $i = linha_bd(SQL_INSCRICAO, [$inscricao_id]);
    $email = trim((string) ($i['email'] ?? ''));
    if (!$i || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    [$u, $link, $validade] = conta_formando((string) $i['formando'], $email);
    $curso = (string) (curso((int) $i['curso_id'])['nome'] ?? $i['titulo']);
    $area = endereco_site() . '/clientes/area.php';
    $botao = function (string $href, string $rotulo) {
        return '<p style="margin:0 0 18px;"><a href="' . h($href) . '" style="display:inline-block;padding:13px 26px;background:#0A0F26;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:999px;">' . h($rotulo) . '</a></p>';
    };
    $campos = array_values(array_map(function ($c) { return $c['rotulo']; }, campos_certificado_pedidos()));
    $html = '<p style="margin:0 0 6px;"><strong>Curso:</strong> ' . h($curso) . '</p>'
        . '<p style="margin:0 0 6px;"><strong>Nome de utilizador:</strong> ' . h($email) . '</p>'
        . ($link !== '' ? '<p style="margin:0 0 18px;"><strong>Ligação válida até:</strong> ' . h(data_hora($validade)) . '</p>' . $botao($link, 'Ativar a conta e definir a palavra-passe')
            : '<p style="margin:0 0 18px;">Já tem conta: entre com o seu email e a sua palavra-passe.</p>' . $botao($area, 'Entrar na área do formando'))
        . '<p style="margin:0 0 4px;"><strong>Dados para o certificado</strong> (na área do formando, em "Ficha para o certificado"):</p>'
        . '<ul style="margin:0 0 14px;padding-left:20px;">' . implode('', array_map(function ($c) { return '<li>' . h($c) . '</li>'; }, $campos)) . '</ul>';
    $texto = 'Curso: ' . $curso . "\nNome de utilizador: " . $email . "\n"
        . ($link !== '' ? 'Ativar a conta e definir a palavra-passe (ligação válida até ' . data_hora($validade) . '): ' . $link : 'Entrar na área do formando: ' . $area)
        . "\n\nDados para o certificado (na área do formando):\n- " . implode("\n- ", $campos);
    $ok = enviar_mensagem('inscricao_formando', $email, [
        'nome' => nome_curto((string) $i['formando']), 'nome_completo' => $i['formando'], 'email' => $email, 'curso' => $curso,
        'validade' => $validade !== '' ? data_hora($validade) : '',
    ], [$html, $texto]);
    if ($ok) {
        executar('UPDATE inscricoes SET email_inscricao = ?, dados_pedidos = COALESCE(dados_pedidos, ?) WHERE id = ?', [agora(), agora(), $inscricao_id]);
    }
    return $ok;
}

// Diagnóstico respondido (etapa 2): inscrição automática no curso indicado no envio e email da inscrição (etapa 3)
function inscrever_apos_diagnostico(int $diagnostico_id): ?int
{
    $d = linha_bd('SELECT * FROM diagnosticos WHERE id = ?', [$diagnostico_id]);
    if (!$d || $d['respondido'] === null || empty($d['curso_id']) || !curso((int) $d['curso_id'])) {
        return null;
    }
    $fid = (int) ($d['formando_id'] ?: formando_para((string) $d['nome'], (string) $d['email']));
    $iid = criar_inscricao_curso($fid, (int) $d['curso_id'], $diagnostico_id, $d['origem'] === 'publico' ? 'site' : 'area');
    executar('UPDATE diagnosticos SET formando_id = ?, inscricao_id = ? WHERE id = ?', [$fid, $iid, $diagnostico_id]);
    $i = linha_bd('SELECT email_inscricao FROM inscricoes WHERE id = ?', [$iid]);
    if (empty($i['email_inscricao'])) {
        enviar_email_inscricao($iid);
    }
    return $iid;
}

/* Etapa 4: dados para o certificado (campos configuráveis em Configuração, Certificados) */

// Campos possíveis da ficha para o certificado. "fixo": sempre obrigatório.
const CAMPOS_CERTIFICADO = [
    'nome_completo' => ['rotulo' => 'Nome completo', 'fixo' => true],
    'documento' => ['rotulo' => 'Tipo, número e validade do documento de identificação', 'fixo' => false, 'omissao' => 'obrigatorio'],
    'data_nascimento' => ['rotulo' => 'Data de nascimento', 'fixo' => false, 'omissao' => 'desligado'],
    'nif' => ['rotulo' => 'NIF', 'fixo' => false, 'omissao' => 'desligado'],
    'naturalidade' => ['rotulo' => 'Naturalidade', 'fixo' => false, 'omissao' => 'desligado'],
    'nacionalidade' => ['rotulo' => 'Nacionalidade', 'fixo' => false, 'omissao' => 'desligado'],
];
const TIPOS_DOCUMENTO = ['cc' => 'Cartão de Cidadão', 'passaporte' => 'Passaporte', 'residencia' => 'Título de residência'];

// Configuração dos campos: campo => obrigatorio | opcional | desligado
function config_campos_certificado(): array
{
    $guardada = json_decode((string) definicao(bd(), 'certificado_campos'), true);
    $r = [];
    foreach (CAMPOS_CERTIFICADO as $k => $c) {
        $v = is_array($guardada) ? ($guardada[$k] ?? null) : null;
        $r[$k] = $c['fixo'] ? 'obrigatorio' : (in_array($v, ['obrigatorio', 'opcional', 'desligado'], true) ? $v : $c['omissao']);
    }
    return $r;
}

// Campos pedidos ao formando (não desligados), com a indicação de obrigatório
function campos_certificado_pedidos(): array
{
    $r = [];
    foreach (config_campos_certificado() as $k => $estado) {
        if ($estado !== 'desligado') {
            $r[$k] = ['rotulo' => CAMPOS_CERTIFICADO[$k]['rotulo'] . ($estado === 'opcional' ? ' (opcional)' : ''), 'obrigatorio' => $estado === 'obrigatorio'];
        }
    }
    return $r;
}
