<?php
// Inscrições: sessões de cada turma (com memória por pedido), resumo das presenças e estado do acesso à área do formando.

declare(strict_types=1);

// Sessões de cada edição, com memória por pedido
function sessoes_formacao_cache(int $formacao_id): array
{
    static $cache = [];
    if (!isset($cache[$formacao_id])) {
        $f = linha_bd('SELECT * FROM formacoes WHERE id = ?', [$formacao_id]);
        $cache[$formacao_id] = $f ? sessoes_edicao($f) : [];
    }
    return $cache[$formacao_id];
}

// Presenças de uma inscrição confirmada: [presenças, sessões já iniciadas, total de sessões]
function resumo_presencas(array $i): array
{
    $sessoes = sessoes_formacao_cache((int) $i['formacao_id']);
    $decorridas = count(array_filter($sessoes, function ($s) { return strtotime($s['inicio']) <= time(); }));
    return [count(presencas_inscricao((int) $i['id'])), $decorridas, count($sessoes)];
}

// Estado do acesso à área do formando a partir do email
function estado_acesso_formando(string $email): array
{
    if (trim($email) === '') {
        return ['sem_email', 'Sem email', null];
    }
    $u = utilizador_por_email($email);
    if (!$u) {
        return ['sem_conta', 'Sem acesso', null];
    }
    if ($u['papel'] !== 'formando') {
        return ['outro', 'Utilizador ' . mb_strtolower(PAPEIS[$u['papel']] ?? $u['papel']), $u];
    }
    if (!(int) $u['ativo']) {
        return ['inativo', 'Acesso inativo', $u];
    }
    if ($u['token_senha'] !== null && $u['ultimo_acesso'] === null) {
        return strtotime((string) $u['token_validade']) >= time()
            ? ['ligacao', 'Ligação enviada, válida até ' . data_hora($u['token_validade']), $u]
            : ['expirada', 'Ligação expirada sem uso', $u];
    }
    return ['ativo', $u['ultimo_acesso'] ? 'Último acesso em ' . data_hora($u['ultimo_acesso']) : 'Com acesso', $u];
}

