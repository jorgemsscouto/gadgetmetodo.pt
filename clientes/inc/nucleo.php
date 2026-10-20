<?php
// Área de clientes: configuração, base de dados, sessão, permissões e utilitários comuns.

declare(strict_types=1);

require_once __DIR__ . '/../../formulario/dados.php';
require_once __DIR__ . '/../../formulario/email.php';

date_default_timezone_set('Europe/Lisbon');

const PAPEIS = ['admin' => 'Gadgetmetodo', 'cliente' => 'Cliente'];
const ESTADOS_FORMACAO = ['aberta' => 'Inscrições abertas', 'fechada' => 'Inscrições fechadas', 'cancelada' => 'Cancelada'];
const ESTADOS_INSCRICAO = ['pendente' => 'Pendente', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'];
const MODALIDADES = ['presencial' => 'Presencial', 'online' => 'Online', 'misto' => 'Misto'];
const MODULOS = [
    'completo' => 'Programa completo (5 módulos)',
    'm1' => 'Módulo 1: Fundamentos de IA para a administração',
    'm2' => 'Módulo 2: Casos de uso e integração em sistemas',
    'm3' => 'Módulo 3: Governação e ética da IA',
    'm4' => 'Módulo 4: RGPD, privacidade e IA',
    'm5' => 'Módulo 5: NIS2 e segurança da informação',
    'medida' => 'Programa à medida',
];
const SESSAO_MAXIMA = 7200; // segundos de inatividade
const DIAS_INSCRICOES = 730; // conservação das inscrições após a data da formação

function cfg(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $ficheiro = __DIR__ . '/../../formulario/config.php';
        $cfg = is_file($ficheiro) ? require $ficheiro : [];
    }
    return $cfg;
}

function bd(): ?PDO
{
    static $pronta = false;
    $pdo = ligacao(cfg());
    if ($pdo && !$pronta) {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE IF NOT EXISTS clientes (
            id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, nif TEXT, contacto TEXT, email TEXT,
            telefone TEXT, notas TEXT, criado TEXT NOT NULL)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS utilizadores (
            id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE COLLATE NOCASE, nome TEXT NOT NULL,
            senha TEXT NOT NULL, papel TEXT NOT NULL CHECK (papel IN ('admin', 'cliente')),
            cliente_id INTEGER REFERENCES clientes(id), ativo INTEGER NOT NULL DEFAULT 1,
            criado TEXT NOT NULL, ultimo_acesso TEXT)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS formacoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT, titulo TEXT NOT NULL, modulo TEXT, inicio TEXT NOT NULL, fim TEXT,
            modalidade TEXT, local TEXT, vagas INTEGER NOT NULL DEFAULT 12, estado TEXT NOT NULL DEFAULT 'aberta',
            notas TEXT, criado TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS inscricoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT, formacao_id INTEGER NOT NULL REFERENCES formacoes(id),
            cliente_id INTEGER NOT NULL REFERENCES clientes(id), utilizador_id INTEGER, formando TEXT NOT NULL,
            email TEXT, funcao TEXT, respostas TEXT, pontuacao INTEGER, responsavel INTEGER, nivel TEXT,
            ferramentas TEXT, expectativas TEXT, estado TEXT NOT NULL DEFAULT 'pendente', criado TEXT NOT NULL)");
        $pdo->exec('CREATE INDEX IF NOT EXISTS inscricoes_formacao ON inscricoes (formacao_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS inscricoes_cliente ON inscricoes (cliente_id)');
        // Conservação: as inscrições e os diagnósticos são apagados 2 anos depois da formação
        $pdo->prepare('DELETE FROM inscricoes WHERE formacao_id IN (SELECT id FROM formacoes WHERE inicio < ?)')
            ->execute([date('Y-m-d H:i:s', time() - DIAS_INSCRICOES * 86400)]);
        $pronta = true;
    }
    return $pdo;
}

function consulta(string $sql, array $parametros = []): array
{
    $st = bd()->prepare($sql);
    $st->execute($parametros);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function linha_bd(string $sql, array $parametros = []): ?array
{
    $linhas = consulta($sql, $parametros);
    return $linhas[0] ?? null;
}

function executar(string $sql, array $parametros = []): int
{
    $st = bd()->prepare($sql);
    $st->execute($parametros);
    return (int) bd()->lastInsertId();
}

function agora(): string
{
    return date('Y-m-d H:i:s');
}

/* Texto e formatação */

if (!function_exists('h')) {
    function h(?string $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

function texto(string $chave, int $maximo = 200, bool $varias_linhas = false): string
{
    $v = $_POST[$chave] ?? '';
    if (!is_string($v) || !preg_match('//u', $v)) {
        return '';
    }
    $v = $varias_linhas ? preg_replace('/[^\P{C}\n\t]+/u', '', $v) : preg_replace('/\s+/u', ' ', preg_replace('/[^\P{C}\n\t]+/u', '', $v));
    return trim(function_exists('mb_substr') ? mb_substr($v, 0, $maximo, 'UTF-8') : substr($v, 0, $maximo));
}

function data_hora(?string $data, bool $com_hora = true): string
{
    $t = $data ? strtotime($data) : false;
    return $t ? date($com_hora ? 'd/m/Y H:i' : 'd/m/Y', $t) : '';
}

function data_extensa(string $data): string
{
    $meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $t = strtotime($data);
    return $t ? date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t) : '';
}

function gerar_senha(): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < 12; $i++) {
        $s .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    return $s;
}

/* Sessão e autenticação */

function iniciar_sessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('gm_area');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/clientes/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (isset($_SESSION['visto']) && time() - (int) $_SESSION['visto'] > SESSAO_MAXIMA) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['aviso'] = 'A sessão expirou por inatividade. Entre de novo.';
    }
    $_SESSION['visto'] = time();
}

function utilizador(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    $id = (int) ($_SESSION['uid'] ?? 0);
    $u = $id && bd() ? linha_bd('SELECT u.*, c.nome AS cliente_nome FROM utilizadores u LEFT JOIN clientes c ON c.id = u.cliente_id WHERE u.id = ? AND u.ativo = 1', [$id]) : null;
    return $u;
}

function eh_admin(): bool
{
    return (utilizador()['papel'] ?? '') === 'admin';
}

function negar(): void
{
    http_response_code(403);
    pagina_erro('Sem permissão', 'Não tem permissão para ver esta página.');
}

// Limite de tentativas de entrada por origem: 10 em 15 minutos
function entradas_excedidas(bool $registar): bool
{
    $ficheiro = sys_get_temp_dir() . '/gm-area-' . hash('sha256', 'area|' . ($_SERVER['REMOTE_ADDR'] ?? '')) . '.json';
    $agora = time();
    $lista = is_readable($ficheiro) ? json_decode((string) @file_get_contents($ficheiro), true) : [];
    $lista = array_values(array_filter(is_array($lista) ? $lista : [], function ($t) use ($agora) { return is_int($t) && $t > $agora - 900; }));
    if (count($lista) >= 10) {
        return true;
    }
    if ($registar) {
        $lista[] = $agora;
        @file_put_contents($ficheiro, json_encode($lista), LOCK_EX);
    }
    return false;
}

function entrar(string $email, string $senha): ?string
{
    if (entradas_excedidas(false)) {
        return 'Demasiadas tentativas. Aguarde 15 minutos.';
    }
    $total = (int) (linha_bd('SELECT COUNT(*) AS n FROM utilizadores')['n'] ?? 0);
    if ($total === 0) {
        // Primeiro acesso: cria a conta de administração com a palavra-passe da área reservada
        $cfg = cfg();
        $email_admin = (string) ($cfg['remetente_email'] ?? 'info@gadgetmetodo.pt');
        if (strcasecmp($email, $email_admin) === 0 && !empty($cfg['admin_sha256']) && hash_equals((string) $cfg['admin_sha256'], hash('sha256', $senha))) {
            $id = executar('INSERT INTO utilizadores (email, nome, senha, papel, criado) VALUES (?, ?, ?, ?, ?)',
                [$email_admin, 'Administração', password_hash($senha, PASSWORD_DEFAULT), 'admin', agora()]);
            return sessao_iniciada($id);
        }
        entradas_excedidas(true);
        return 'Email ou palavra-passe incorretos.';
    }
    $u = linha_bd('SELECT id, senha FROM utilizadores WHERE email = ? AND ativo = 1', [$email]);
    if (!$u || !password_verify($senha, $u['senha'])) {
        entradas_excedidas(true);
        return 'Email ou palavra-passe incorretos.';
    }
    if (password_needs_rehash($u['senha'], PASSWORD_DEFAULT)) {
        executar('UPDATE utilizadores SET senha = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
    }
    return sessao_iniciada((int) $u['id']);
}

function sessao_iniciada(int $id): ?string
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    executar('UPDATE utilizadores SET ultimo_acesso = ? WHERE id = ?', [agora(), $id]);
    return null;
}

/* Proteção dos formulários (CSRF) */

function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . h(token_csrf()) . '">';
}

function verificar_csrf(): void
{
    if (!hash_equals(token_csrf(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        pagina_erro('Pedido inválido', 'O formulário expirou ou não é válido. Volte atrás, atualize a página e tente de novo.');
    }
}

/* Navegação e mensagens */

function url(string $p, array $parametros = []): string
{
    return 'area.php?' . http_build_query(['p' => $p] + $parametros);
}

function redirecionar(string $p, array $parametros = []): void
{
    header('Location: ' . url($p, $parametros), true, 303);
    exit;
}

function aviso(string $texto, string $tipo = 'ok'): void
{
    $_SESSION['avisos'][] = [$tipo, $texto];
}

function avisos(): string
{
    $html = '';
    if (!empty($_SESSION['aviso'])) {
        $html .= '<div class="aviso aviso-info">' . h($_SESSION['aviso']) . '</div>';
        unset($_SESSION['aviso']);
    }
    foreach ($_SESSION['avisos'] ?? [] as [$tipo, $texto]) {
        $html .= '<div class="aviso aviso-' . h($tipo) . '">' . $texto . '</div>';
    }
    unset($_SESSION['avisos']);
    return $html;
}

/* Formações e vagas */

function inscritos(int $formacao_id): int
{
    return (int) (linha_bd("SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ? AND estado != 'cancelada'", [$formacao_id])['n'] ?? 0);
}

function etiqueta(string $texto, string $tipo = ''): string
{
    return '<span class="etiqueta' . ($tipo ? ' etiqueta-' . h($tipo) : '') . '">' . h($texto) . '</span>';
}

function horario(array $f): string
{
    $fim = !empty($f['fim']) ? ' às ' . date('H:i', strtotime($f['fim'])) : '';
    return data_extensa($f['inicio']) . ', ' . date('H:i', strtotime($f['inicio'])) . $fim;
}

/* Exportação para Excel (separador ; e UTF-8 com BOM) */

function exportar_csv(string $nome, array $cabecalho, array $linhas): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome . '-' . date('Y-m-d') . '.csv"');
    $saida = fopen('php://output', 'wb');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, $cabecalho, ';', '"', '\\');
    foreach ($linhas as $l) {
        fputcsv($saida, array_map('celula_csv', $l), ';', '"', '\\');
    }
    fclose($saida);
    exit;
}

/* Emails da área */

function enviar_aviso(string $para, string $assunto, string $titulo, string $corpo_html, string $corpo_texto, string $responder_a = '', string $bcc = ''): void
{
    $cfg = cfg();
    if (empty($cfg['remetente_email'])) {
        error_log('Área de clientes: email não enviado, formulario/config.php em falta');
        return;
    }
    try {
        $m = novo_email($cfg);
        $m->addAddress($para);
        if ($responder_a !== '') {
            $m->addReplyTo($responder_a);
        }
        if ($bcc !== '' && strcasecmp($bcc, $para) !== 0) {
            $m->addBCC($bcc);
        }
        $m->Subject = $assunto;
        $m->isHTML(true);
        $f = "'Segoe UI', Helvetica, Arial, sans-serif";
        $m->Body = '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"></head><body style="margin:0;background:#F5F7F8;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 16px;">'
            . '<table role="presentation" width="620" cellpadding="0" cellspacing="0" style="width:100%;max-width:620px;background:#FFFFFF;border:1px solid #E5E7EB;">'
            . '<tr><td style="padding:22px 32px;background:#0A0F26;"><p style="margin:0 0 4px;font-family:' . $f . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8FC3C6;font-weight:700;">Área de clientes · Gadgetmetodo</p>'
            . '<p style="margin:0;font-family:' . $f . ';font-size:19px;color:#FFFFFF;font-weight:700;">' . h($titulo) . '</p></td></tr>'
            . '<tr><td style="padding:24px 32px 28px;font-family:' . $f . ';font-size:14px;line-height:1.6;color:#333333;">' . $corpo_html . '</td></tr>'
            . '<tr><td style="padding:18px 32px;border-top:1px solid #E5E7EB;font-family:' . $f . ';font-size:12px;color:#687280;">Gadgetmetodo, Lda. · Rua das Cruzes, 625 · 4100-195 Porto · info@gadgetmetodo.pt · www.gadgetmetodo.pt</td></tr>'
            . '</table></td></tr></table></body></html>';
        $m->AltBody = $titulo . "\n\n" . $corpo_texto . "\n\n--\nGadgetmetodo, Lda. · info@gadgetmetodo.pt · www.gadgetmetodo.pt\n";
        $m->send();
    } catch (Throwable $e) {
        error_log('Área de clientes: falha no envio de email: ' . $e->getMessage());
    }
}
