<?php
// Registo dos pedidos e das aberturas do catálogo.
// Usa SQLite quando disponível e ficheiros CSV como alternativa. Os dados ficam fora da pasta pública
// sempre que o alojamento o permite; caso contrário, numa pasta com nome não adivinhável e protegida.

declare(strict_types=1);

const CATALOGO_PDF = '/catalogo-servicos-2026.pdf';
const DIAS_CONSERVACAO = 365;

function pasta_dados(array $cfg): ?string
{
    static $pasta = false;
    if ($pasta !== false) {
        return $pasta;
    }
    $candidatas = [];
    if (!empty($cfg['pasta_dados'])) {
        $candidatas[] = rtrim((string) $cfg['pasta_dados'], '/');
    }
    $candidatas[] = dirname(__DIR__, 2) . '/dados-site';
    if (!empty($cfg['chave'])) {
        $candidatas[] = __DIR__ . '/dados-' . substr(hash('sha256', 'pasta|' . $cfg['chave']), 0, 24);
    }
    foreach ($candidatas as $p) {
        if (!@is_dir($p)) {
            @mkdir($p, 0700, true);
        }
        if (@is_dir($p) && @is_writable($p)) {
            if (strpos($p, __DIR__) === 0) {
                @file_put_contents($p . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
                @file_put_contents($p . '/index.html', '');
            }
            return $pasta = $p;
        }
    }
    error_log('Formulário: nenhuma pasta de dados com permissão de escrita');
    return $pasta = null;
}

function usa_sqlite(): bool
{
    return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
}

function ligacao(array $cfg): ?PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pasta = pasta_dados($cfg);
    if ($pasta === null || !usa_sqlite()) {
        return null;
    }
    $pdo = new PDO('sqlite:' . $pasta . '/registos.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS pedidos (
        ref TEXT PRIMARY KEY, criado TEXT NOT NULL, tipo TEXT, nome TEXT, empresa TEXT,
        email TEXT, telemovel TEXT, servicos TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS aberturas (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT NOT NULL, aberto TEXT NOT NULL, agente TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS aberturas_ref ON aberturas (ref)');
    return $pdo;
}

/* Assinatura dos links: impede que alguém registe aberturas de referências que não recebeu */

function assinatura(array $cfg, string $ref): string
{
    return substr(hash_hmac('sha256', $ref, (string) ($cfg['chave'] ?? '')), 0, 16);
}

function link_catalogo(array $cfg, string $ref): string
{
    $site = rtrim((string) ($cfg['site'] ?? 'https://www.gadgetmetodo.pt'), '/');
    if (empty($cfg['chave'])) {
        return $site . CATALOGO_PDF;
    }
    return $site . '/formulario/catalogo.php?r=' . rawurlencode($ref) . '&s=' . assinatura($cfg, $ref);
}

/* Escrita */

function csv_acrescentar(string $ficheiro, array $linha): void
{
    $f = fopen($ficheiro, 'ab');
    if ($f && flock($f, LOCK_EX)) {
        fputcsv($f, $linha, ',', '"', '\\');
        flock($f, LOCK_UN);
    }
    if ($f) {
        fclose($f);
    }
}

function csv_ler(string $ficheiro): array
{
    $linhas = [];
    if (is_readable($ficheiro) && ($f = fopen($ficheiro, 'rb'))) {
        while (($l = fgetcsv($f, 0, ',', '"', '\\')) !== false) {
            $linhas[] = $l;
        }
        fclose($f);
    }
    return $linhas;
}

function apagar_antigos(array $cfg): void
{
    $limite = date('Y-m-d H:i:s', time() - DIAS_CONSERVACAO * 86400);
    if ($pdo = ligacao($cfg)) {
        $pdo->prepare('DELETE FROM aberturas WHERE ref IN (SELECT ref FROM pedidos WHERE criado < ?)')->execute([$limite]);
        $pdo->prepare('DELETE FROM pedidos WHERE criado < ?')->execute([$limite]);
        return;
    }
    $pasta = pasta_dados($cfg);
    if ($pasta === null) {
        return;
    }
    $pedidos = csv_ler($pasta . '/pedidos.csv');
    $manter = array_filter($pedidos, function ($l) use ($limite) { return ($l[1] ?? '') >= $limite; });
    if (count($manter) !== count($pedidos)) {
        $refs = array_flip(array_column($manter, 0));
        foreach (['pedidos.csv' => $manter, 'aberturas.csv' => array_filter(csv_ler($pasta . '/aberturas.csv'), function ($l) use ($refs) { return isset($refs[$l[0] ?? '']); })] as $nome => $linhas) {
            $tmp = $pasta . '/' . $nome . '.tmp';
            @unlink($tmp);
            foreach ($linhas as $l) {
                csv_acrescentar($tmp, $l);
            }
            @rename($tmp, $pasta . '/' . $nome);
        }
    }
}

function guardar_pedido(array $cfg, array $d): void
{
    $linha = [$d['referencia'], date('Y-m-d H:i:s'), $d['tipo'], $d['nome'], $d['empresa'], $d['email'], $d['telemovel'], implode('; ', $d['servicos'])];
    if ($pdo = ligacao($cfg)) {
        $pdo->prepare('INSERT OR IGNORE INTO pedidos (ref, criado, tipo, nome, empresa, email, telemovel, servicos) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute($linha);
    } elseif ($pasta = pasta_dados($cfg)) {
        csv_acrescentar($pasta . '/pedidos.csv', $linha);
    }
    apagar_antigos($cfg);
}

function guardar_abertura(array $cfg, string $ref, string $agente): void
{
    $linha = [$ref, date('Y-m-d H:i:s'), function_exists('mb_substr') ? mb_substr($agente, 0, 200) : substr($agente, 0, 200)];
    if ($pdo = ligacao($cfg)) {
        $pdo->prepare('INSERT INTO aberturas (ref, aberto, agente) VALUES (?, ?, ?)')->execute($linha);
    } elseif ($pasta = pasta_dados($cfg)) {
        csv_acrescentar($pasta . '/aberturas.csv', $linha);
    }
}

/* Leitura para a página de registos */

// Devolve os pedidos mais recentes primeiro, cada um com a lista das suas aberturas
function ler_registos(array $cfg): array
{
    $pedidos = [];
    $aberturas = [];
    if ($pdo = ligacao($cfg)) {
        foreach ($pdo->query('SELECT ref, criado, tipo, nome, empresa, email, telemovel, servicos FROM pedidos ORDER BY criado DESC') as $l) {
            $pedidos[] = $l;
        }
        foreach ($pdo->query('SELECT ref, aberto, agente FROM aberturas ORDER BY aberto') as $l) {
            $aberturas[$l['ref']][] = $l;
        }
    } elseif ($pasta = pasta_dados($cfg)) {
        $campos = ['ref', 'criado', 'tipo', 'nome', 'empresa', 'email', 'telemovel', 'servicos'];
        foreach (csv_ler($pasta . '/pedidos.csv') as $l) {
            $pedidos[] = array_combine($campos, array_pad($l, 8, ''));
        }
        usort($pedidos, function ($a, $b) { return strcmp($b['criado'], $a['criado']); });
        foreach (csv_ler($pasta . '/aberturas.csv') as $l) {
            $aberturas[$l[0]][] = ['ref' => $l[0], 'aberto' => $l[1] ?? '', 'agente' => $l[2] ?? ''];
        }
    }
    foreach ($pedidos as &$p) {
        $p['aberturas'] = $aberturas[$p['ref']] ?? [];
        foreach ($p['aberturas'] as &$a) {
            $a['automatica'] = abertura_automatica($p['criado'], $a['aberto'], $a['agente']);
        }
        unset($a);
    }
    unset($p);
    return $pedidos;
}

// Sistemas de segurança de email costumam abrir os links segundos depois da entrega
function abertura_automatica(string $criado, string $aberto, string $agente): bool
{
    if (preg_match('/bot|crawler|spider|preview|scan|safelink|proofpoint|mimecast|barracuda|python|curl|wget|headless/i', $agente)) {
        return true;
    }
    return strtotime($aberto) - strtotime($criado) < 120;
}
