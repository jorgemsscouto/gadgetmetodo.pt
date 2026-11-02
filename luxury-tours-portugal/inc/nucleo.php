<?php
// Inquérito Luxury Tours Portugal: configuração, estrutura do inquérito, base de dados, idiomas e utilitários.
// Projeto autónomo: não depende do resto do site gadgetmetodo.pt.

declare(strict_types=1);

date_default_timezone_set('Europe/Lisbon');

const LTP_IDIOMAS = ['pt', 'en', 'es', 'fr', 'el', 'ar'];
const LTP_IDIOMA_PADRAO = 'en';
// Nomes alternativos aceites na importação da lista de clientes ("gr" = grego)
const LTP_ALIASES_IDIOMA = [
    'pt-pt' => 'pt', 'pt_pt' => 'pt', 'pt-br' => 'pt', 'portugues' => 'pt', 'português' => 'pt', 'portuguese' => 'pt',
    'en-gb' => 'en', 'en-us' => 'en', 'ingles' => 'en', 'inglês' => 'en', 'english' => 'en',
    'es-es' => 'es', 'espanhol' => 'es', 'spanish' => 'es', 'español' => 'es',
    'fr-fr' => 'fr', 'frances' => 'fr', 'francês' => 'fr', 'french' => 'fr', 'français' => 'fr',
    'gr' => 'el', 'el-gr' => 'el', 'grego' => 'el', 'greek' => 'el', 'ελληνικά' => 'el',
    'arabe' => 'ar', 'árabe' => 'ar', 'arabic' => 'ar', 'العربية' => 'ar',
];

// Códigos internos neutros: as respostas guardam estes códigos, nunca o texto traduzido
const LTP_REGIOES = [
    'region_tuscany', 'region_piedmont', 'region_bordeaux', 'region_burgundy', 'region_champagne', 'region_rioja',
    'region_ribera', 'region_mosel', 'region_santorini', 'region_napa', 'region_mendoza', 'region_stellenbosch',
];
const LTP_MAX_REGIOES = 3;
const LTP_MAX_PRIORIDADES = 3;
const LTP_CAMPOS = [
    // campo => [tipo, opções, obrigatório]
    'intent' => ['single', ['intent_very_likely', 'intent_likely', 'intent_considering', 'intent_unlikely'], true],
    'duration' => ['single', ['duration_3_4', 'duration_5_7', 'duration_8_plus'], true],
    'group' => ['single', ['group_couple', 'group_family', 'group_friends', 'group_private'], true],
    'budget' => ['single', ['budget_up_to_5000', 'budget_5000_10000', 'budget_10000_20000', 'budget_over_20000', 'budget_undisclosed'], true],
    'seasons' => ['multi', ['season_spring', 'season_summer', 'season_harvest', 'season_winter', 'season_flexible'], true],
    'priorities' => ['multi', ['priority_private_access', 'priority_winemakers', 'priority_dining', 'priority_hotels', 'priority_culture', 'priority_wellness', 'priority_transfers'], true],
    'contact' => ['single', ['contact_yes', 'contact_no'], true],
];
// Valores dos escalões de orçamento em euros (o texto é formatado no idioma de quem responde)
const LTP_ORCAMENTO_VALORES = [
    'budget_up_to_5000' => ['max' => 5000],
    'budget_5000_10000' => ['min' => 5000, 'max' => 10000],
    'budget_10000_20000' => ['min' => 10000, 'max' => 20000],
    'budget_over_20000' => ['min' => 20000],
];
// Passos do inquérito (a ordem dos passos e dos campos é a da página)
const LTP_PASSOS = [
    ['id' => 'regions', 'fields' => ['regions']],
    ['id' => 'intent', 'fields' => ['intent']],
    ['id' => 'format', 'fields' => ['duration', 'group']],
    ['id' => 'budget', 'fields' => ['budget', 'seasons']],
    ['id' => 'priorities', 'fields' => ['priorities', 'comment']],
    ['id' => 'contact', 'fields' => ['contact']],
];
const LTP_MAX_COMENTARIO = 1000;
const LTP_MAX_OUTRA = 120;
const LTP_TRATAMENTOS = ['m', 'f'];

/* Configuração */

function ltp_cfg(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $ficheiro = dirname(__DIR__) . '/config.php';
        $lido = is_file($ficheiro) ? require $ficheiro : [];
        $cfg = array_merge([
            'senha_painel' => '',
            'demo' => true,
            'data_fecho' => '2026-12-15',
            'endereco' => '',
            'privacidade_url' => '',
            'acesso_publico' => true,
            'pasta_dados' => '',
        ], is_array($lido) ? $lido : []);
    }
    return $cfg;
}

function ltp_configurado(): bool
{
    return is_file(dirname(__DIR__) . '/config.php') && ltp_cfg()['senha_painel'] !== '';
}

function ltp_demo(): bool
{
    return (bool) ltp_cfg()['demo'];
}

// Endereço público do inquérito, com barra final
function ltp_endereco(): string
{
    $definido = trim((string) ltp_cfg()['endereco']);
    if ($definido !== '') {
        return rtrim($definido, '/') . '/';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $anfitriao = $_SERVER['HTTP_HOST'] ?? 'www.gadgetmetodo.pt';
    $caminho = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/luxury-tours-portugal/index.php')), '/');
    if (substr($caminho, -7) === '/painel') {
        $caminho = substr($caminho, 0, -7);
    }
    return ($https ? 'https' : 'http') . '://' . $anfitriao . $caminho . '/';
}

function ltp_ligacao_pessoal(string $token): string
{
    return ltp_endereco() . '?t=' . rawurlencode($token);
}

function ltp_data_fecho(): ?DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ltp_cfg()['data_fecho']);
    return $d ?: null;
}

function ltp_fechado(): bool
{
    $fecho = ltp_data_fecho();
    return $fecho !== null && new DateTimeImmutable('today') > $fecho;
}

/* Base de dados (SQLite fora da pasta pública sempre que possível) */

function ltp_pasta_dados(): ?string
{
    static $pasta = false;
    if ($pasta !== false) {
        return $pasta;
    }
    $candidatas = [];
    if (trim((string) ltp_cfg()['pasta_dados']) !== '') {
        $candidatas[] = rtrim((string) ltp_cfg()['pasta_dados'], '/');
    }
    $candidatas[] = dirname(__DIR__, 3) . '/dados-luxury-tours';
    $candidatas[] = dirname(__DIR__) . '/dados';
    foreach ($candidatas as $p) {
        if (!@is_dir($p)) {
            @mkdir($p, 0700, true);
        }
        if (@is_dir($p) && @is_writable($p)) {
            if (strpos($p, dirname(__DIR__)) === 0) {
                @file_put_contents($p . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
                @file_put_contents($p . '/index.html', '');
            }
            return $pasta = $p;
        }
    }
    error_log('Luxury Tours Portugal: nenhuma pasta de dados com permissão de escrita');
    return $pasta = null;
}

function ltp_bd(): ?PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pasta = ltp_pasta_dados();
    if ($pasta === null || !class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        return null;
    }
    $pdo = new PDO('sqlite:' . $pasta . '/inquerito.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT NOT NULL UNIQUE, nome TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL UNIQUE COLLATE NOCASE, idioma TEXT, tratamento TEXT, pais TEXT, referencia TEXT,
        demo INTEGER NOT NULL DEFAULT 0, criado TEXT NOT NULL)");
    // Uma resposta por cliente convidado; a ligação geral (sem convite) usa um identificador anónimo do navegador
    $pdo->exec('CREATE TABLE IF NOT EXISTS respostas (
        id INTEGER PRIMARY KEY AUTOINCREMENT, cliente_id INTEGER UNIQUE REFERENCES clientes(id) ON DELETE CASCADE,
        anonimo TEXT UNIQUE, idioma TEXT NOT NULL, regiao_1 TEXT, regiao_2 TEXT, regiao_3 TEXT, regiao_outra TEXT,
        intencao TEXT, duracao TEXT, grupo TEXT, orcamento TEXT, epocas TEXT, prioridades TEXT, comentario TEXT,
        contacto TEXT, demo INTEGER NOT NULL DEFAULT 0, criado TEXT NOT NULL, atualizado TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS definicoes (chave TEXT PRIMARY KEY, valor TEXT)');
    return $pdo;
}

function ltp_definicao(string $chave): ?string
{
    $pdo = ltp_bd();
    if (!$pdo) {
        return null;
    }
    $st = $pdo->prepare('SELECT valor FROM definicoes WHERE chave = ?');
    $st->execute([$chave]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}

function ltp_definir(string $chave, string $valor): void
{
    $pdo = ltp_bd();
    if ($pdo) {
        $pdo->prepare('INSERT INTO definicoes (chave, valor) VALUES (?, ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor')
            ->execute([$chave, $valor]);
    }
}

/* Idiomas */

function ltp_textos(string $idioma): array
{
    static $cache = [];
    if (!in_array($idioma, LTP_IDIOMAS, true)) {
        $idioma = LTP_IDIOMA_PADRAO;
    }
    if (!isset($cache[$idioma])) {
        $json = @file_get_contents(dirname(__DIR__) . '/i18n/' . $idioma . '.json');
        $cache[$idioma] = $json ? (json_decode($json, true) ?: []) : [];
    }
    return $cache[$idioma];
}

// Texto por caminho ("ui.next"), com substituição de {marcadores}
function ltp_t(string $idioma, string $caminho, array $vars = []): string
{
    $v = ltp_textos($idioma);
    foreach (explode('.', $caminho) as $parte) {
        if (!is_array($v) || !array_key_exists($parte, $v)) {
            return '';
        }
        $v = $v[$parte];
    }
    if (!is_string($v)) {
        return '';
    }
    foreach ($vars as $k => $valor) {
        $v = str_replace('{' . $k . '}', (string) $valor, $v);
    }
    return $v;
}

// Revisão por falante nativo: marcada no painel, por idioma
function ltp_revisto(string $idioma): bool
{
    return ltp_definicao('revisto_' . $idioma) === '1';
}

// Idiomas visíveis no inquérito: só os revistos; em modo de demonstração, todos (com aviso)
function ltp_idiomas_publicados(): array
{
    if (ltp_demo()) {
        return LTP_IDIOMAS;
    }
    return array_values(array_filter(LTP_IDIOMAS, 'ltp_revisto'));
}

function ltp_normalizar_idioma(?string $valor): ?string
{
    $v = mb_strtolower(trim((string) $valor));
    if ($v === '') {
        return null;
    }
    if (in_array($v, LTP_IDIOMAS, true)) {
        return $v;
    }
    return LTP_ALIASES_IDIOMA[$v] ?? null;
}

// Idioma do navegador (cabeçalho Accept-Language), se suportado e publicado
function ltp_idioma_navegador(array $publicados): ?string
{
    $cabecalho = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $preferencias = [];
    foreach (explode(',', $cabecalho) as $i => $parte) {
        $bits = explode(';', trim($parte));
        $codigo = strtolower(substr(trim($bits[0]), 0, 2));
        $q = 1.0;
        if (isset($bits[1]) && preg_match('/q=([0-9.]+)/', $bits[1], $m)) {
            $q = (float) $m[1];
        }
        if ($codigo !== '') {
            $preferencias[] = [$codigo, $q, $i];
        }
    }
    usort($preferencias, fn($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);
    foreach ($preferencias as [$codigo]) {
        if (in_array($codigo, $publicados, true)) {
            return $codigo;
        }
    }
    return null;
}

// Ordem: escolha manual (?lang), idioma do cliente, idioma do navegador, inglês, primeiro publicado
function ltp_resolver_idioma(?string $pedido, ?string $doCliente, array $publicados): ?string
{
    foreach ([ltp_normalizar_idioma($pedido), ltp_normalizar_idioma($doCliente)] as $c) {
        if ($c !== null && in_array($c, $publicados, true)) {
            return $c;
        }
    }
    $nav = ltp_idioma_navegador($publicados);
    if ($nav !== null) {
        return $nav;
    }
    if (in_array(LTP_IDIOMA_PADRAO, $publicados, true)) {
        return LTP_IDIOMA_PADRAO;
    }
    return $publicados[0] ?? null;
}

function ltp_formatar_data(DateTimeImmutable $data, string $idioma): string
{
    if (class_exists('IntlDateFormatter')) {
        $locale = str_replace('-u-nu-', '@numbers=', (string) (ltp_textos($idioma)['locale'] ?? $idioma));
        $f = new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'Europe/Lisbon', IntlDateFormatter::GREGORIAN);
        $texto = $f->format($data);
        if (is_string($texto) && $texto !== '') {
            return $texto;
        }
    }
    return $data->format('d/m/Y');
}

/* Clientes e respostas */

function ltp_cliente_por_token(string $token): ?array
{
    $pdo = ltp_bd();
    if (!$pdo || !preg_match('/^[A-Za-z0-9]{12,40}$/', $token)) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM clientes WHERE token = ?');
    $st->execute([$token]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function ltp_novo_token(): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $t = '';
    for ($i = 0; $i < 16; $i++) {
        $t .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    return $t;
}

// Resposta guardada convertida no formato usado pela página
function ltp_resposta_para_pagina(array $r): array
{
    return [
        'regions' => array_values(array_filter([$r['regiao_1'], $r['regiao_2'], $r['regiao_3']])),
        'regions_other' => (string) $r['regiao_outra'],
        'intent' => $r['intencao'],
        'duration' => $r['duracao'],
        'group' => $r['grupo'],
        'budget' => $r['orcamento'],
        'seasons' => array_values(array_filter(explode(',', (string) $r['epocas']))),
        'priorities' => array_values(array_filter(explode(',', (string) $r['prioridades']))),
        'comment' => (string) $r['comentario'],
        'contact' => $r['contacto'],
    ];
}

function ltp_resposta_do_cliente(int $clienteId): ?array
{
    $pdo = ltp_bd();
    if (!$pdo) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM respostas WHERE cliente_id = ?');
    $st->execute([$clienteId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function ltp_limpar_texto($valor, int $maximo): string
{
    $t = is_string($valor) ? $valor : '';
    $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $t) ?? '';
    $t = trim(preg_replace('/[ \t]+/u', ' ', $t) ?? '');
    return mb_substr($t, 0, $maximo);
}

// Valida as respostas recebidas da página; devolve [respostas normalizadas, erro]
function ltp_validar(array $r): array
{
    $regioes = is_array($r['regions'] ?? null) ? array_values($r['regions']) : [];
    if (count($regioes) < 1 || count($regioes) > LTP_MAX_REGIOES || count(array_unique($regioes)) !== count($regioes)) {
        return [null, 'regions'];
    }
    foreach ($regioes as $c) {
        if (!in_array($c, LTP_REGIOES, true)) {
            return [null, 'regions'];
        }
    }
    $limpo = ['regions' => $regioes, 'regions_other' => ltp_limpar_texto($r['regions_other'] ?? '', LTP_MAX_OUTRA)];
    foreach (LTP_CAMPOS as $campo => [$tipo, $opcoes, $obrigatorio]) {
        $v = $r[$campo] ?? null;
        if ($tipo === 'single') {
            if (!is_string($v) || !in_array($v, $opcoes, true)) {
                return [null, $campo];
            }
        } else {
            $v = is_array($v) ? array_values(array_unique($v)) : [];
            if (($obrigatorio && !$v) || ($campo === 'priorities' && count($v) > LTP_MAX_PRIORIDADES)) {
                return [null, $campo];
            }
            foreach ($v as $c) {
                if (!in_array($c, $opcoes, true)) {
                    return [null, $campo];
                }
            }
        }
        $limpo[$campo] = $v;
    }
    $limpo['comment'] = ltp_limpar_texto($r['comment'] ?? '', LTP_MAX_COMENTARIO);
    return [$limpo, null];
}

function ltp_guardar_resposta(PDO $pdo, array $v, string $idioma, ?int $clienteId, ?string $anonimo, bool $demo = false, ?string $quando = null): void
{
    $agora = $quando ?? date('Y-m-d H:i:s');
    $dados = [
        'idioma' => $idioma,
        'regiao_1' => $v['regions'][0] ?? null,
        'regiao_2' => $v['regions'][1] ?? null,
        'regiao_3' => $v['regions'][2] ?? null,
        'regiao_outra' => $v['regions_other'],
        'intencao' => $v['intent'],
        'duracao' => $v['duration'],
        'grupo' => $v['group'],
        'orcamento' => $v['budget'],
        'epocas' => implode(',', $v['seasons']),
        'prioridades' => implode(',', $v['priorities']),
        'comentario' => $v['comment'],
        'contacto' => $v['contact'],
    ];
    $chave = $clienteId !== null ? 'cliente_id' : 'anonimo';
    $valorChave = $clienteId ?? $anonimo;
    $st = $pdo->prepare("SELECT id FROM respostas WHERE $chave = ?");
    $st->execute([$valorChave]);
    $id = $st->fetchColumn();
    if ($id) {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($dados)));
        $pdo->prepare("UPDATE respostas SET $sets, atualizado = :atualizado WHERE id = :id")
            ->execute($dados + ['atualizado' => $agora, 'id' => $id]);
        return;
    }
    $dados += [$chave => $valorChave, 'demo' => $demo ? 1 : 0, 'criado' => $agora, 'atualizado' => $agora];
    $colunas = implode(', ', array_keys($dados));
    $marcas = implode(', ', array_map(fn($k) => ":$k", array_keys($dados)));
    $pdo->prepare("INSERT INTO respostas ($colunas) VALUES ($marcas)")->execute($dados);
}

/* Utilitários */

function ltp_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ltp_versao(string $ficheiro): string
{
    $caminho = dirname(__DIR__) . '/' . $ficheiro;
    return $ficheiro . '?v=' . (is_file($caminho) ? substr(md5((string) filemtime($caminho)), 0, 8) : '1');
}

// Exportação para Excel: impede que um texto começado por =, +, - ou @ seja lido como fórmula
function ltp_celula_csv($valor)
{
    if (!is_string($valor) || $valor === '') {
        return $valor;
    }
    return strpbrk($valor[0], "=+-@\t\r") !== false ? "'" . $valor : $valor;
}

// CSV para Excel em português (separador ; e UTF-8 com BOM)
function ltp_enviar_csv(string $nome, array $cabecalho, iterable $linhas): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Cache-Control: no-store');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, $cabecalho, ';', '"', '\\');
    foreach ($linhas as $l) {
        fputcsv($saida, array_map('ltp_celula_csv', $l), ';', '"', '\\');
    }
    fclose($saida);
}
