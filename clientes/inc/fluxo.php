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
