<?php
// Área de clientes: configuração, base de dados, sessão, permissões e utilitários comuns.

declare(strict_types=1);

require_once __DIR__ . '/../../formulario/dados.php';
require_once __DIR__ . '/../../formulario/email.php';
require_once __DIR__ . '/../../formulario/comunicacao.php';
require_once __DIR__ . '/cursos.php';

date_default_timezone_set('Europe/Lisbon');

const PAPEIS = ['admin' => 'Gadgetmetodo', 'cliente' => 'Cliente'];
const ESTADOS_FORMACAO = ['aberta' => 'Inscrições abertas', 'fechada' => 'Inscrições fechadas', 'cancelada' => 'Cancelada'];
const ESTADOS_INSCRICAO = ['pendente' => 'Pendente', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'];
const MODALIDADES = ['presencial' => 'Presencial', 'online' => 'Online', 'misto' => 'Misto'];
const SESSAO_MAXIMA = 7200; // segundos de inatividade
const DIAS_INSCRICOES = 730; // conservação das inscrições após a data da formação
const DIAS_NOVA = 30; // formações agendadas há menos de 30 dias aparecem como novas
const ORIGENS = ['area' => 'Área reservada', 'site' => 'Site'];
const ESTADOS_PREINSCRICAO = ['nova' => 'Nova', 'convidada' => 'Convite enviado', 'inscrita' => 'Inscrita', 'arquivada' => 'Arquivada'];
const DIAS_PREINSCRICOES = 365; // conservação das pré-inscrições após o registo
const TIPOS_CLIENTE = ['particular' => 'Particular', 'empresa' => 'Empresa'];
// Rótulos das opções nos formulários públicos
const OPCOES_TIPO_CLIENTE = ['particular' => 'Particular', 'empresa' => 'Empresa ou outra organização'];

function endereco_site(): string
{
    return rtrim((string) (cfg()['site'] ?? 'https://www.gadgetmetodo.pt'), '/');
}

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
        $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
            id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, nif TEXT, contacto TEXT, email TEXT,
            telefone TEXT, notas TEXT, criado TEXT NOT NULL, tipo TEXT NOT NULL DEFAULT 'empresa')");
        // Os clientes podem ser empresas (ou outras organizações) ou particulares
        acrescentar_colunas($pdo, 'clientes', ['tipo' => "TEXT NOT NULL DEFAULT 'empresa'"]);
        $pdo->exec("CREATE TABLE IF NOT EXISTS utilizadores (
            id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE COLLATE NOCASE, nome TEXT NOT NULL,
            senha TEXT NOT NULL, papel TEXT NOT NULL CHECK (papel IN ('admin', 'cliente')),
            cliente_id INTEGER REFERENCES clientes(id), ativo INTEGER NOT NULL DEFAULT 1,
            criado TEXT NOT NULL, ultimo_acesso TEXT)");
        $pdo->exec('CREATE TABLE IF NOT EXISTS definicoes (chave TEXT PRIMARY KEY, valor TEXT)');
        // Campos da ficha do curso: percurso, unidade (Módulo ou Sessão), objetivo, pré-requisitos, resultado esperado,
        // estrutura-tipo da sessão e avaliação (linhas "rótulo: valor"), requisitos, enquadramento legal e investimento
        $pdo->exec('CREATE TABLE IF NOT EXISTS cursos (
            id INTEGER PRIMARY KEY AUTOINCREMENT, chave TEXT UNIQUE, nome TEXT NOT NULL, tipologia TEXT, descricao TEXT,
            publico TEXT, duracao TEXT, diagnostico INTEGER NOT NULL DEFAULT 1, ativo INTEGER NOT NULL DEFAULT 1, criado TEXT NOT NULL,
            percurso TEXT, unidade TEXT, objetivo TEXT, prerequisitos TEXT, resultado TEXT, estrutura TEXT, avaliacao TEXT,
            requisitos TEXT, enquadramento TEXT, investimento TEXT)');
        acrescentar_colunas($pdo, 'cursos', array_fill_keys(['percurso', 'unidade', 'objetivo', 'prerequisitos', 'resultado', 'estrutura', 'avaliacao', 'requisitos', 'enquadramento', 'investimento'], 'TEXT'));
        $pdo->exec('CREATE TABLE IF NOT EXISTS modulos (
            id INTEGER PRIMARY KEY AUTOINCREMENT, curso_id INTEGER NOT NULL REFERENCES cursos(id), ordem INTEGER NOT NULL DEFAULT 1,
            nome TEXT NOT NULL, descricao TEXT, temas TEXT, horas REAL, caso_pratico TEXT)');
        acrescentar_colunas($pdo, 'modulos', ['caso_pratico' => 'TEXT']);
        $pdo->exec('CREATE INDEX IF NOT EXISTS modulos_curso ON modulos (curso_id, ordem)');
        // "titulo" guarda o nome do curso (atualizado quando o curso muda de nome); "reuniao" é a ligação da sessão online
        $pdo->exec("CREATE TABLE IF NOT EXISTS formacoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT, titulo TEXT NOT NULL, modulo TEXT, inicio TEXT NOT NULL, fim TEXT,
            modalidade TEXT, local TEXT, vagas INTEGER NOT NULL DEFAULT 12, estado TEXT NOT NULL DEFAULT 'aberta',
            notas TEXT, criado TEXT NOT NULL, curso_id INTEGER REFERENCES cursos(id), modulo_id INTEGER REFERENCES modulos(id), reuniao TEXT)");
        acrescentar_colunas($pdo, 'formacoes', ['curso_id' => 'INTEGER REFERENCES cursos(id)', 'modulo_id' => 'INTEGER REFERENCES modulos(id)', 'reuniao' => 'TEXT']);
        // Edições do curso completo: uma linha por sessão, com data e horário próprios (formacoes.inicio é o da 1.ª
        // sessão e formacoes.fim o fim da última); sem linhas, a formação decorre numa só data
        $pdo->exec('CREATE TABLE IF NOT EXISTS sessoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT, formacao_id INTEGER NOT NULL REFERENCES formacoes(id), ordem INTEGER NOT NULL,
            modulo_id INTEGER REFERENCES modulos(id), inicio TEXT NOT NULL, fim TEXT)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS sessoes_formacao ON sessoes (formacao_id, inicio)');
        migrar_sessoes_para_cursos($pdo);
        // As inscrições feitas no site público ficam sem cliente até a Gadgetmetodo as associar
        $tabela_inscricoes = "CREATE TABLE IF NOT EXISTS inscricoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT, formacao_id INTEGER NOT NULL REFERENCES formacoes(id),
            cliente_id INTEGER REFERENCES clientes(id), utilizador_id INTEGER, origem TEXT NOT NULL DEFAULT 'area',
            empresa TEXT, nif TEXT, contacto_nome TEXT, contacto_email TEXT, contacto_telefone TEXT, formando TEXT NOT NULL,
            email TEXT, funcao TEXT, respostas TEXT, pontuacao INTEGER, responsavel INTEGER, nivel TEXT,
            ferramentas TEXT, expectativas TEXT, estado TEXT NOT NULL DEFAULT 'pendente', criado TEXT NOT NULL, tipo_cliente TEXT)";
        $pdo->exec($tabela_inscricoes);
        migrar_inscricoes($pdo, $tabela_inscricoes);
        acrescentar_colunas($pdo, 'inscricoes', ['tipo_cliente' => 'TEXT']);
        $pdo->exec('CREATE INDEX IF NOT EXISTS inscricoes_formacao ON inscricoes (formacao_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS inscricoes_cliente ON inscricoes (cliente_id)');
        // Pré-inscrições em cursos sem datas: o candidato recebe o convite quando houver sessões
        $pdo->exec("CREATE TABLE IF NOT EXISTS preinscricoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT, curso_id INTEGER NOT NULL REFERENCES cursos(id), nome TEXT NOT NULL,
            empresa TEXT, email TEXT NOT NULL, telefone TEXT, formandos INTEGER NOT NULL DEFAULT 1, modalidade TEXT,
            preferencias TEXT, estado TEXT NOT NULL DEFAULT 'nova', criado TEXT NOT NULL, respostas TEXT, inscricao_id INTEGER,
            tipo_cliente TEXT, ferramentas TEXT)");
        acrescentar_colunas($pdo, 'preinscricoes', ['respostas' => 'TEXT', 'inscricao_id' => 'INTEGER', 'tipo_cliente' => 'TEXT', 'ferramentas' => 'TEXT']);
        $pdo->exec('CREATE INDEX IF NOT EXISTS preinscricoes_curso ON preinscricoes (curso_id)');
        // Tabela de preços (administração): linhas (cursos), modalidades (colunas) e preço de cada linha em cada modalidade,
        // em cêntimos e sem IVA (sem valor, "sob consulta"). As linhas com "legado" são da 1.ª versão (uma lista simples
        // com categoria, unidade, valor e IVA) e ficam só para consulta.
        $pdo->exec("CREATE TABLE IF NOT EXISTS precos (
            id INTEGER PRIMARY KEY AUTOINCREMENT, categoria TEXT NOT NULL, designacao TEXT NOT NULL, descricao TEXT, unidade TEXT,
            valor INTEGER, iva TEXT NOT NULL DEFAULT '23', ativo INTEGER NOT NULL DEFAULT 1, ordem INTEGER NOT NULL DEFAULT 0, atualizado TEXT NOT NULL,
            detalhe TEXT, legado INTEGER NOT NULL DEFAULT 0)");
        acrescentar_colunas($pdo, 'precos', ['detalhe' => 'TEXT', 'legado' => 'INTEGER NOT NULL DEFAULT 0']);
        $pdo->exec('CREATE TABLE IF NOT EXISTS precos_colunas (
            id INTEGER PRIMARY KEY AUTOINCREMENT, etiqueta TEXT NOT NULL, titulo TEXT, descricao TEXT, coluna TEXT NOT NULL, detalhe TEXT,
            ordem INTEGER NOT NULL DEFAULT 0, ativo INTEGER NOT NULL DEFAULT 1, atualizado TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS precos_valores (
            preco_id INTEGER NOT NULL REFERENCES precos(id), coluna_id INTEGER NOT NULL REFERENCES precos_colunas(id), valor INTEGER,
            PRIMARY KEY (preco_id, coluna_id))');
        juntar_formacoes_por_sessao($pdo);
        // Conservação: as inscrições e os diagnósticos são apagados 2 anos depois da formação; as pré-inscrições, 12 meses depois do registo
        $pdo->prepare('DELETE FROM inscricoes WHERE formacao_id IN (SELECT id FROM formacoes WHERE inicio < ?)')
            ->execute([date('Y-m-d H:i:s', time() - DIAS_INSCRICOES * 86400)]);
        $pdo->prepare('DELETE FROM preinscricoes WHERE criado < ?')->execute([date('Y-m-d H:i:s', time() - DIAS_PREINSCRICOES * 86400)]);
        aplicar_catalogo($pdo);
        $pronta = true;
    }
    return $pdo;
}

// Acrescenta as colunas em falta a uma tabela criada por uma versão anterior
function acrescentar_colunas(PDO $pdo, string $tabela, array $colunas): void
{
    $existentes = array_column($pdo->query('PRAGMA table_info(' . $tabela . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach ($colunas as $nome => $tipo) {
        if (!in_array($nome, $existentes, true)) {
            $pdo->exec('ALTER TABLE ' . $tabela . ' ADD COLUMN ' . $nome . ' ' . $tipo);
        }
    }
}

// Bases criadas pela primeira versão da área: cliente obrigatório e sem campos de origem
function migrar_inscricoes(PDO $pdo, string $tabela): void
{
    $colunas = array_column($pdo->query('PRAGMA table_info(inscricoes)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (in_array('origem', $colunas, true)) {
        return;
    }
    $comuns = 'id, formacao_id, cliente_id, utilizador_id, formando, email, funcao, respostas, pontuacao, responsavel, nivel, ferramentas, expectativas, estado, criado';
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->beginTransaction();
    $pdo->exec('ALTER TABLE inscricoes RENAME TO inscricoes_v1');
    $pdo->exec($tabela);
    $pdo->exec("INSERT INTO inscricoes ($comuns) SELECT $comuns FROM inscricoes_v1");
    $pdo->exec('DROP TABLE inscricoes_v1');
    $pdo->commit();
    $pdo->exec('PRAGMA foreign_keys = ON');
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

// Limite de pedidos por origem (endereço IP, guardado só como código derivado)
function limite_excedido(string $nome, int $maximo, int $segundos, bool $registar): bool
{
    $ficheiro = sys_get_temp_dir() . '/gm-' . $nome . '-' . hash('sha256', $nome . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')) . '.json';
    $agora = time();
    $lista = is_readable($ficheiro) ? json_decode((string) @file_get_contents($ficheiro), true) : [];
    $lista = array_values(array_filter(is_array($lista) ? $lista : [], function ($t) use ($agora, $segundos) { return is_int($t) && $t > $agora - $segundos; }));
    if (count($lista) >= $maximo) {
        return true;
    }
    if ($registar) {
        $lista[] = $agora;
        @file_put_contents($ficheiro, json_encode($lista), LOCK_EX);
    }
    return false;
}

// Tentativas de entrada na área: 10 em 15 minutos
function entradas_excedidas(bool $registar): bool
{
    return limite_excedido('area', 10, 900, $registar);
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

function redirecionar(string $p, array $parametros = [], string $ancora = ''): void
{
    header('Location: ' . url($p, $parametros) . ($ancora !== '' ? '#' . $ancora : ''), true, 303);
    exit;
}

function aviso(string $texto, string $tipo = 'ok'): void
{
    $_SESSION['avisos'][] = [$tipo, $texto];
}

// Mensagem depois de um envio de email pedido na administração, com aviso de erro se o envio falhou
function aviso_envio(bool $ok, string $mensagem): void
{
    aviso($ok ? $mensagem : 'O email não foi enviado: o envio falhou ou este email está desativado em Configuração, Comunicação. Os detalhes ficam no registo de erros do alojamento.', $ok ? 'ok' : 'erro');
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

function formacao_nova(array $f): bool
{
    return !empty($f['criado']) && strtotime($f['criado']) >= time() - DIAS_NOVA * 86400;
}

function formacoes_novas(): array
{
    return consulta("SELECT * FROM formacoes WHERE inicio >= ? AND criado >= ? AND estado = 'aberta' ORDER BY inicio", [date('Y-m-d 00:00:00'), date('Y-m-d H:i:s', time() - DIAS_NOVA * 86400)]);
}

// Nome e tipo do cliente de uma inscrição: o cliente associado ou, nas inscrições do site, a empresa indicada
// ou, se for particular, quem fez a inscrição (as inscrições antigas do site eram todas de empresas)
const SQL_CLIENTE = "COALESCE(c.nome, NULLIF(i.empresa, ''), i.contacto_nome) AS cliente, COALESCE(c.tipo, i.tipo_cliente, 'empresa') AS cliente_tipo";

// Nome do cliente com a indicação "Particular", para as listas e fichas da área reservada
function cliente_html(array $i): string
{
    return h((string) $i['cliente']) . (($i['cliente_tipo'] ?? '') === 'particular' ? ' ' . etiqueta('Particular') : '');
}

// Tipo de cliente escolhido num formulário (vazio se não for válido)
function tipo_cliente_post(): string
{
    $t = (string) ($_POST['tipo_cliente'] ?? '');
    return isset(TIPOS_CLIENTE[$t]) ? $t : '';
}

// Escolha "Particular / Empresa" dos formulários públicos; o nome da empresa (data-so-empresa) só aparece para empresas (assets/js/site.js)
function campo_tipo_cliente(string $selecionado, string $legenda): string
{
    $op = '';
    foreach (OPCOES_TIPO_CLIENTE as $valor => $rotulo) {
        $op .= '<label class="opcao"><input type="radio" name="tipo_cliente" value="' . $valor . '"' . ($selecionado === $valor ? ' checked' : '') . ' required> ' . h($rotulo) . '</label>';
    }
    return '<fieldset class="campo-grupo" data-tipo-cliente><legend>' . h($legenda) . ' *</legend><div class="opcoes opcoes-linha">' . $op . '</div></fieldset>';
}

// Modalidade e local, sem repetir o local quando só diz a modalidade (por exemplo "On-line" numa formação Online)
function modalidade_e_local(array $f, string $separador = ' · '): string
{
    $modalidade = MODALIDADES[$f['modalidade'] ?? ''] ?? '';
    $local = trim((string) ($f['local'] ?? ''));
    $simples = function (string $x) { return preg_replace('/[^a-z]/', '', strtolower($x)); };
    return $local !== '' && $simples($local) !== $simples($modalidade) ? ($modalidade !== '' ? $modalidade . $separador : '') . $local : $modalidade;
}

function etiqueta(string $texto, string $tipo = ''): string
{
    return '<span class="etiqueta' . ($tipo ? ' etiqueta-' . h($tipo) : '') . '">' . h($texto) . '</span>';
}

// Datas de uma formação: numa só data, ou "6 sessões, de 3 a 24 de novembro de 2026, 18:30 às 19:30" nas edições com várias sessões
function horario(array $f): string
{
    $s = sessoes_formacao((int) ($f['formacao_id'] ?? $f['id'] ?? 0));
    if (count($s) > 1) {
        $horas = array_values(array_unique(array_map('hora_sessao', $s)));
        return count($s) . ' sessões, ' . periodo($s[0]['inicio'], $s[count($s) - 1]['inicio']) . (count($horas) === 1 ? ', ' . $horas[0] : '');
    }
    return data_extensa($f['inicio']) . ', ' . hora_sessao($f);
}

function hora_sessao(array $s): string
{
    return date('H:i', strtotime($s['inicio'])) . (!empty($s['fim']) ? ' às ' . date('H:i', strtotime($s['fim'])) : '');
}

// "de 3 a 24 de novembro de 2026", "de 3 de novembro a 8 de dezembro de 2026" ou com os dois anos
function periodo(string $de, string $ate): string
{
    $a = strtotime($de);
    $z = strtotime($ate);
    $meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $inicio = date('j', $a) . (date('Y-m', $a) === date('Y-m', $z) ? '' : ' de ' . $meses[(int) date('n', $a)] . (date('Y', $a) === date('Y', $z) ? '' : ' de ' . date('Y', $a)));
    return 'de ' . $inicio . ' a ' . data_extensa($ate);
}

// Sessões com data de uma edição (vazio quando a formação decorre numa só data); memória por pedido
function sessoes_formacao(int $formacao_id, bool $renovar = false): array
{
    static $cache = [];
    if (!$formacao_id) {
        return [];
    }
    if ($renovar || !isset($cache[$formacao_id])) {
        $cache[$formacao_id] = consulta('SELECT * FROM sessoes WHERE formacao_id = ? ORDER BY inicio, ordem', [$formacao_id]);
    }
    return $cache[$formacao_id];
}

// Uma linha por sessão: "Sessão 1: Nome · terça, 3 de novembro de 2026, 18:30 às 19:30"
function linhas_sessoes(array $f): array
{
    $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    $r = [];
    foreach (sessoes_formacao((int) ($f['formacao_id'] ?? $f['id'] ?? 0)) as $n => $s) {
        $m = $s['modulo_id'] ? linha_bd('SELECT * FROM modulos WHERE id = ?', [$s['modulo_id']]) : null;
        $r[] = ($m ? rotulo_modulo($m) : 'Sessão ' . ($n + 1)) . ' · ' . $dias[(int) date('w', strtotime($s['inicio']))] . ', ' . data_extensa($s['inicio']) . ', ' . hora_sessao($s);
    }
    return count($r) > 1 ? $r : [];
}

function sessoes_html(array $f, bool $aberto = false): string
{
    $l = linhas_sessoes($f);
    return $l ? '<details class="programa sessoes-datas"' . ($aberto ? ' open' : '') . '><summary>Datas das ' . count($l) . ' sessões</summary><div class="programa-corpo"><ol>'
        . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $l)) . '</ol></div></details>' : '';
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

// Configuração da comunicação (Configuração, Comunicação): remetente, destinatários e textos dos emails
function comunicacao(bool $renovar = false): array
{
    return config_comunicacao(bd(), cfg(), $renovar);
}

// Caixa que recebe os avisos internos e as respostas dos clientes
function email_admin(): string
{
    return comunicacao()['geral']['email_admin'];
}

// Envia um email do catálogo (formulario/comunicacao.php) com o assunto, o título, o texto e o fecho configurados.
// $meio são os dados próprios do envio (HTML e texto), entre o texto e o fecho. Os emails para clientes levam a
// assinatura e respondem para a administração; os avisos internos levam a cópia oculta geral.
// Opções: texto (substitui o texto configurado, mantendo a saudação), responder_a, cc, teste (envia mesmo desativado e sem cópias).
function enviar_mensagem(string $tipo, string $para, array $vars, array $meio = ['', ''], array $op = []): bool
{
    $com = comunicacao();
    $e = $com['emails'][$tipo];
    if (!$e['ativo'] && empty($op['teste'])) {
        return false;
    }
    $interno = EMAILS[$tipo]['grupo'] === 'admin';
    $texto = substituir_variaveis($e['texto'], $vars);
    if (($op['texto'] ?? '') !== '') {
        // Mensagem escrita à mão (sem variáveis): fica a saudação do modelo (1.º parágrafo, curto e acabado em vírgula)
        $partes = preg_split('/\n[ \t]*\n/u', trim(str_replace("\r\n", "\n", $texto)), 2);
        $saudacao = count($partes) === 2 && strlen($partes[0]) <= 60 && substr(rtrim($partes[0]), -1) === ',' ? $partes[0] : '';
        $texto = ($saudacao !== '' ? $saudacao . "\n\n" : '') . $op['texto'];
    }
    $fecho = substituir_variaveis($e['fecho'], $vars);
    $assinatura = $interno ? '' : $com['geral']['assinatura'];
    $html = html_paragrafos($texto) . $meio[0] . html_paragrafos($fecho, 'margin:18px 0 0;') . html_paragrafos($assinatura, 'margin:22px 0 0;');
    $txt = implode("\n\n", array_filter([texto_paragrafos($texto), trim($meio[1]), texto_paragrafos($fecho), texto_paragrafos($assinatura)], 'strlen'));
    // Os emails de teste vão só para quem os pede, sem cópias
    $bcc = !empty($op['teste']) ? [] : array_merge($interno ? (lista_emails($com['geral']['bcc']) ?: []) : [], lista_emails($e['copia']) ?: []);
    return enviar_aviso($para, (!empty($op['teste']) ? '[Teste] ' : '') . assunto_email($e['assunto'], $vars), substituir_variaveis($e['titulo'], $vars) ?: EMAILS[$tipo]['nome'],
        $html, $txt, (string) ($op['responder_a'] ?? ($interno ? '' : email_admin())), implode(',', $bcc), (string) ($op['cc'] ?? ''));
}

// Email com o grafismo da área (cabeçalho, corpo e rodapé). $cc e $bcc aceitam vários endereços separados por vírgula.
function enviar_aviso(string $para, string $assunto, string $titulo, string $corpo_html, string $corpo_texto, string $responder_a = '', string $bcc = '', string $cc = ''): bool
{
    $cfg = cfg();
    if (empty($cfg['remetente_email'])) {
        error_log('Área de clientes: email não enviado, formulario/config.php em falta');
        return false;
    }
    $geral = comunicacao()['geral'];
    try {
        $m = novo_email(['remetente_nome' => $geral['remetente_nome']] + $cfg);
        $m->addAddress($para);
        if ($responder_a !== '') {
            $m->addReplyTo($responder_a);
        }
        foreach (lista_emails($cc) ?: [] as $e) {
            if (strcasecmp($e, $para) !== 0) {
                $m->addCC($e);
            }
        }
        foreach (lista_emails($bcc) ?: [] as $e) {
            if (strcasecmp($e, $para) !== 0) {
                $m->addBCC($e);
            }
        }
        $m->Subject = $assunto;
        $m->isHTML(true);
        $f = "'Segoe UI', Helvetica, Arial, sans-serif";
        $m->Body = '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"></head><body style="margin:0;background:#F5F7F8;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 16px;">'
            . '<table role="presentation" width="620" cellpadding="0" cellspacing="0" style="width:100%;max-width:620px;background:#FFFFFF;border:1px solid #E5E7EB;">'
            . '<tr><td style="padding:22px 32px;background:#0A0F26;">' . ($geral['cabecalho'] !== '' ? '<p style="margin:0 0 4px;font-family:' . $f . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8FC3C6;font-weight:700;">' . h($geral['cabecalho']) . '</p>' : '')
            . '<p style="margin:0;font-family:' . $f . ';font-size:19px;color:#FFFFFF;font-weight:700;">' . h($titulo) . '</p></td></tr>'
            . '<tr><td style="padding:24px 32px 28px;font-family:' . $f . ';font-size:14px;line-height:1.6;color:#333333;">' . $corpo_html . '</td></tr>'
            . ($geral['rodape'] !== '' ? '<tr><td style="padding:18px 32px;border-top:1px solid #E5E7EB;font-family:' . $f . ';font-size:12px;color:#687280;">' . nl2br(h($geral['rodape']), false) . '</td></tr>' : '')
            . '</table></td></tr></table></body></html>';
        $m->AltBody = $titulo . "\n\n" . $corpo_texto . ($geral['rodape'] !== '' ? "\n\n--\n" . $geral['rodape'] : '') . "\n";
        $m->send();
        return true;
    } catch (Throwable $e) {
        error_log('Área de clientes: falha no envio de email: ' . $e->getMessage());
        return false;
    }
}
