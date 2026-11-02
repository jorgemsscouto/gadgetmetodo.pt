<?php
// Inquérito Luxury Tours Portugal: utilizadores internos do painel, perfis e permissões.
// O primeiro acesso faz-se com a palavra-passe de configuração (config.php, senha_painel), que só serve
// enquanto não houver nenhum administrador ativo; a partir daí, cada pessoa entra com o seu email.

declare(strict_types=1);

const LTP_PAPEIS = ['admin' => 'Administrador', 'gestor' => 'Gestor', 'consulta' => 'Consulta'];
const LTP_DESCRICAO_PAPEIS = [
    'admin' => 'Tudo, incluindo utilizadores, revisão dos idiomas e eliminação de todos os dados.',
    'gestor' => 'Resumo, respostas, clientes e convites (importação, ligações pessoais e envio em série), emails e dados de demonstração.',
    'consulta' => 'Só leitura: resumo, respostas e exportações das respostas e do ranking.',
];
// Permissões por perfil
const LTP_PERMISSOES = [
    'admin' => ['ver', 'clientes', 'idiomas', 'utilizadores', 'apagar_tudo'],
    'gestor' => ['ver', 'clientes'],
    'consulta' => ['ver'],
];
const LTP_SENHA_MINIMA = 10;

function ltp_pode(?array $eu, string $permissao): bool
{
    return $eu !== null && in_array($permissao, LTP_PERMISSOES[$eu['papel']] ?? [], true);
}

function ltp_ha_admin(?PDO $pdo): bool
{
    return $pdo !== null && (bool) $pdo->query("SELECT 1 FROM utilizadores WHERE papel = 'admin' AND ativo = 1 LIMIT 1")->fetchColumn();
}

function ltp_ha_utilizadores(?PDO $pdo): bool
{
    return $pdo !== null && (bool) $pdo->query('SELECT 1 FROM utilizadores LIMIT 1')->fetchColumn();
}

// Acesso inicial com a palavra-passe de configuração: só enquanto não houver administrador ativo
function ltp_acesso_inicial_disponivel(?PDO $pdo): bool
{
    return (string) ltp_cfg()['senha_painel'] !== '' && !ltp_ha_admin($pdo);
}

function ltp_utilizador(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM utilizadores WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Utilizador da sessão atual (ou null); id 0 = acesso inicial
function ltp_utilizador_sessao(?PDO $pdo): ?array
{
    if (!isset($_SESSION['utilizador'])) {
        return null;
    }
    $id = (int) $_SESSION['utilizador'];
    if ($id === 0) {
        return ltp_acesso_inicial_disponivel($pdo) ? ['id' => 0, 'nome' => 'Acesso inicial', 'email' => '', 'papel' => 'admin'] : null;
    }
    $u = $pdo ? ltp_utilizador($pdo, $id) : null;
    return $u && (int) $u['ativo'] === 1 ? $u : null;
}

function ltp_autenticar(PDO $pdo, string $email, string $senha): ?array
{
    $st = $pdo->prepare('SELECT * FROM utilizadores WHERE email = ? AND ativo = 1');
    $st->execute([mb_strtolower(trim($email))]);
    $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    // Verifica sempre uma palavra-passe, para o tempo de resposta não revelar se o email existe
    $ok = password_verify($senha, $u['senha'] ?? '$2y$10$hfYd4zh7Iebc6DaxN.aHNerA33q.LBeOqgMWKavkaruz24.PYh70m');
    if (!$u || !$ok) {
        return null;
    }
    if (password_needs_rehash($u['senha'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE utilizadores SET senha = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
    }
    $pdo->prepare('UPDATE utilizadores SET ultimo_acesso = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $u['id']]);
    return $u;
}

function ltp_erro_senha(string $senha): ?string
{
    if (mb_strlen($senha) < LTP_SENHA_MINIMA) {
        return 'A palavra-passe tem de ter pelo menos ' . LTP_SENHA_MINIMA . ' caracteres.';
    }
    if (mb_strlen($senha) > 200) {
        return 'A palavra-passe é demasiado longa.';
    }
    return null;
}

// Devolve uma mensagem de erro ou null
function ltp_criar_utilizador(PDO $pdo, string $nome, string $email, string $papel, string $senha): ?string
{
    $nome = trim($nome);
    $email = mb_strtolower(trim($email));
    if (mb_strlen($nome) < 2) {
        return 'Indique o nome.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Indique um email válido.';
    }
    if (!isset(LTP_PAPEIS[$papel])) {
        return 'Perfil inválido.';
    }
    if ($erro = ltp_erro_senha($senha)) {
        return $erro;
    }
    $st = $pdo->prepare('SELECT 1 FROM utilizadores WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetchColumn()) {
        return 'Já existe um utilizador com este email.';
    }
    $pdo->prepare('INSERT INTO utilizadores (nome, email, senha, papel, ativo, criado) VALUES (?, ?, ?, ?, 1, ?)')
        ->execute([mb_substr($nome, 0, 120), $email, password_hash($senha, PASSWORD_DEFAULT), $papel, date('Y-m-d H:i:s')]);
    return null;
}

// Impede que a última conta de administrador ativa seja desativada, despromovida ou apagada
function ltp_ultimo_admin(PDO $pdo, int $id): bool
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM utilizadores WHERE papel = 'admin' AND ativo = 1 AND id <> ?");
    $st->execute([$id]);
    $u = ltp_utilizador($pdo, $id);
    return $u && $u['papel'] === 'admin' && (int) $u['ativo'] === 1 && (int) $st->fetchColumn() === 0;
}
