<?php
// Inquérito Luxury Tours Portugal: estrutura configurável no painel (separador Inquérito).
// Passos, perguntas, tipos de resposta e opções ficam na tabela "definicoes" (chave "estrutura"); os textos alterados no
// painel ficam por idioma (chaves "textos_xx"), por cima dos ficheiros i18n/*.json. Sem configuração guardada, vale o
// inquérito original, definido pelas constantes de nucleo.php e pelos textos dos ficheiros.

declare(strict_types=1);

// Tipos de resposta (rótulos em português, para o painel)
const LTP_TIPOS = [
    'unica' => 'Escolha única',
    'multipla' => 'Escolha múltipla',
    'ordenacao' => 'Ordenação por preferência (com pontos)',
    'escala' => 'Escala numérica',
    'texto_curto' => 'Texto curto',
    'texto_longo' => 'Texto longo',
];
const LTP_TIPOS_COM_OPCOES = ['unica', 'multipla', 'ordenacao'];
const LTP_MAX_PERGUNTAS = 40;
const LTP_MAX_OPCOES = 30;

/* Estrutura */

// Inquérito original (o da proposta), usado enquanto não houver configuração guardada e em "Repor o inquérito original"
function ltp_estrutura_inicial(): array
{
    $rotulos = [
        'regions' => 'Ranking das regiões', 'intent' => 'Intenção de viajar (próximos dois anos)', 'duration' => 'Duração preferida',
        'group' => 'Com quem viajaria', 'budget' => 'Orçamento por pessoa (sem voos)', 'seasons' => 'Época preferida (escolha múltipla)',
        'priorities' => 'O que torna a viagem inesquecível (até três)', 'comment' => 'Comentário', 'contact' => 'Aceita ser contactado',
    ];
    $perguntas = ['regions' => ['tipo' => 'ordenacao', 'obrigatoria' => true, 'max' => LTP_MAX_REGIOES, 'outra' => true, 'cartoes' => true, 'opcoes' => LTP_REGIOES]];
    foreach (LTP_CAMPOS as $id => [$tipo, $opcoes, $obrigatoria]) {
        $perguntas[$id] = ['tipo' => $tipo === 'single' ? 'unica' : 'multipla', 'obrigatoria' => $obrigatoria, 'opcoes' => $opcoes];
    }
    $perguntas['priorities']['max'] = LTP_MAX_PRIORIDADES;
    $perguntas['intent']['antetitulo'] = true;
    $perguntas['contact']['privacidade'] = true;
    $perguntas['comment'] = ['tipo' => 'texto_longo', 'obrigatoria' => false, 'max' => LTP_MAX_COMENTARIO];
    foreach ($perguntas as $id => &$p) {
        $p['rotulo'] = $rotulos[$id] ?? '';
    }
    unset($p);
    $info = [];
    foreach (LTP_MAPAS as $codigo => $pesquisa) {
        $info[$codigo] = ['mapa' => $pesquisa, 'imagem' => true];
    }
    foreach (LTP_ORCAMENTO_VALORES as $codigo => $valores) {
        $info[$codigo]['euros'] = $valores;
    }
    $passos = array_map(function ($s) { return ['id' => $s['id'], 'perguntas' => $s['fields']]; }, LTP_PASSOS);
    return ltp_normalizar_estrutura(['passos' => $passos, 'perguntas' => $perguntas, 'opcoes' => $info]);
}

// Completa os campos em falta e retira referências a perguntas inexistentes
function ltp_normalizar_estrutura(array $e): array
{
    $perguntas = [];
    foreach ((array) ($e['perguntas'] ?? []) as $id => $p) {
        if (!is_array($p) || !preg_match('/^[a-z][a-z0-9_]{0,39}$/', (string) $id) || !isset(LTP_TIPOS[$p['tipo'] ?? ''])) {
            continue;
        }
        $escala = (array) ($p['escala'] ?? []);
        $perguntas[$id] = [
            'id' => (string) $id,
            'tipo' => (string) $p['tipo'],
            'obrigatoria' => !empty($p['obrigatoria']),
            'ativa' => !array_key_exists('ativa', $p) || !empty($p['ativa']),
            'opcoes' => array_values(array_filter(array_map('strval', (array) ($p['opcoes'] ?? [])), function ($c) { return (bool) preg_match('/^[a-z0-9][a-z0-9_]{0,59}$/', $c); })),
            'max' => max(0, (int) ($p['max'] ?? 0)),
            'outra' => !empty($p['outra']),
            'cartoes' => !empty($p['cartoes']),
            'antetitulo' => !empty($p['antetitulo']),
            'privacidade' => !empty($p['privacidade']),
            'escala' => ['min' => (int) ($escala['min'] ?? 1), 'max' => (int) ($escala['max'] ?? 5)],
            'rotulo' => (string) ($p['rotulo'] ?? ''),
        ];
    }
    $usadas = [];
    $passos = [];
    foreach ((array) ($e['passos'] ?? []) as $n => $s) {
        $lista = [];
        foreach ((array) ($s['perguntas'] ?? []) as $id) {
            if (isset($perguntas[$id]) && !isset($usadas[$id])) {
                $lista[] = $id;
                $usadas[$id] = true;
            }
        }
        $passos[] = ['id' => preg_match('/^[a-z0-9_]{1,40}$/', (string) ($s['id'] ?? '')) ? (string) $s['id'] : 'passo_' . ($n + 1), 'perguntas' => $lista];
    }
    // Perguntas sem passo vão para o último passo (ou para um passo novo)
    foreach (array_keys($perguntas) as $id) {
        if (!isset($usadas[$id])) {
            if (!$passos) {
                $passos[] = ['id' => 'passo_1', 'perguntas' => []];
            }
            $passos[count($passos) - 1]['perguntas'][] = $id;
        }
    }
    $info = [];
    foreach ((array) ($e['opcoes'] ?? []) as $codigo => $i) {
        if (!is_array($i)) {
            continue;
        }
        $limpo = [];
        if (!empty($i['mapa'])) {
            $limpo['mapa'] = mb_substr((string) $i['mapa'], 0, 120);
        }
        if (!empty($i['imagem'])) {
            $limpo['imagem'] = true;
        }
        $euros = array_filter(['min' => $i['euros']['min'] ?? null, 'max' => $i['euros']['max'] ?? null], function ($v) { return $v !== null && $v !== ''; });
        if ($euros) {
            $limpo['euros'] = array_map('intval', $euros);
        }
        if ($limpo) {
            $info[(string) $codigo] = $limpo;
        }
    }
    return ['passos' => $passos, 'perguntas' => $perguntas, 'opcoes' => $info];
}

function ltp_estrutura(bool $renovar = false): array
{
    static $cache = null;
    if ($cache === null || $renovar) {
        $guardada = json_decode((string) ltp_definicao('estrutura'), true);
        $cache = is_array($guardada) && !empty($guardada['perguntas']) ? ltp_normalizar_estrutura($guardada) : ltp_estrutura_inicial();
    }
    return $cache;
}

function ltp_guardar_estrutura(array $e): void
{
    ltp_definir('estrutura', json_encode(ltp_normalizar_estrutura($e), JSON_UNESCAPED_UNICODE));
    ltp_estrutura(true);
}

function ltp_estrutura_personalizada(): bool
{
    return ltp_definicao('estrutura') !== null;
}

function ltp_pergunta(string $id): ?array
{
    return ltp_estrutura()['perguntas'][$id] ?? null;
}

// Passos com as perguntas ativas, pela ordem do inquérito (sem passos vazios)
function ltp_passos_ativos(): array
{
    $e = ltp_estrutura();
    $r = [];
    foreach ($e['passos'] as $s) {
        $ativas = array_values(array_filter($s['perguntas'], function ($id) use ($e) { return $e['perguntas'][$id]['ativa']; }));
        if ($ativas) {
            $r[] = ['id' => $s['id'], 'perguntas' => $ativas];
        }
    }
    return $r;
}

// Perguntas ativas pela ordem do inquérito
function ltp_perguntas_ativas(): array
{
    $e = ltp_estrutura();
    $r = [];
    foreach (ltp_passos_ativos() as $s) {
        foreach ($s['perguntas'] as $id) {
            $r[$id] = $e['perguntas'][$id];
        }
    }
    return $r;
}

// Todas as perguntas (ativas e desativadas) pela ordem dos passos
function ltp_todas_perguntas(): array
{
    $e = ltp_estrutura();
    $r = [];
    foreach ($e['passos'] as $s) {
        foreach ($s['perguntas'] as $id) {
            $r[$id] = $e['perguntas'][$id];
        }
    }
    return $r;
}

// Códigos das respostas possíveis: as opções ou, numa escala, os números do mínimo ao máximo
function ltp_opcoes(array $p): array
{
    if ($p['tipo'] === 'escala') {
        return array_map('strval', range($p['escala']['min'], max($p['escala']['min'], $p['escala']['max'])));
    }
    return in_array($p['tipo'], LTP_TIPOS_COM_OPCOES, true) ? $p['opcoes'] : [];
}

function ltp_info_opcao(string $codigo): array
{
    return ltp_estrutura()['opcoes'][$codigo] ?? [];
}

function ltp_ligacao_mapa_opcao(string $codigo): string
{
    $pesquisa = ltp_info_opcao($codigo)['mapa'] ?? '';
    return $pesquisa !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($pesquisa) : '';
}

// Estrutura enviada à página do inquérito (só o que está ativo)
function ltp_estrutura_para_pagina(): array
{
    $perguntas = [];
    $info = [];
    foreach (ltp_perguntas_ativas() as $id => $p) {
        $perguntas[$id] = [
            'type' => $p['tipo'], 'required' => $p['obrigatoria'], 'options' => ltp_opcoes($p), 'max' => $p['max'],
            'other' => $p['outra'], 'cards' => $p['cartoes'], 'kicker' => $p['antetitulo'], 'privacy' => $p['privacidade'],
        ];
        foreach ($p['opcoes'] as $codigo) {
            $i = ltp_info_opcao($codigo);
            $dados = [];
            if (!empty($i['euros'])) {
                $dados['euros'] = $i['euros'];
            }
            if ($p['tipo'] === 'ordenacao' && ($mapa = ltp_ligacao_mapa_opcao($codigo)) !== '') {
                $dados['map'] = $mapa;
                $dados['image'] = !empty($i['imagem']) && is_file(dirname(__DIR__) . '/assets/img/mapas/' . $codigo . '.svg');
            }
            if ($dados) {
                $info[$codigo] = $dados;
            }
        }
    }
    return ['steps' => array_map(function ($s) { return ['id' => $s['id'], 'fields' => $s['perguntas']]; }, ltp_passos_ativos()), 'questions' => $perguntas, 'optionInfo' => (object) $info];
}

/* Textos alterados no painel (por idioma, por cima dos ficheiros i18n) */

// Chaves dos textos de uma pergunta e das suas opções: "steps.<id>.title", "options.<código>", "regions.<código>.name"...
function ltp_chave_opcao(array $p, string $codigo, string $parte = 'name'): string
{
    return $p['tipo'] === 'ordenacao' ? 'regions.' . $codigo . '.' . $parte : 'options.' . $codigo;
}

function ltp_textos_alterados(string $idioma): array
{
    $v = json_decode((string) ltp_definicao('textos_' . $idioma), true);
    return is_array($v) ? $v : [];
}

// Grava textos de um idioma ("caminho" => texto); texto vazio volta ao do ficheiro (ou ao de outro idioma, se não houver)
function ltp_guardar_textos(string $idioma, array $novos): void
{
    if (!in_array($idioma, LTP_IDIOMAS, true)) {
        return;
    }
    $atuais = ltp_textos_alterados($idioma);
    foreach ($novos as $caminho => $texto) {
        if (!preg_match('/^[a-z_]+(\.[a-z0-9_]+){1,3}$/', (string) $caminho)) {
            continue;
        }
        $texto = trim(str_replace("\r\n", "\n", (string) $texto));
        if ($texto === '' || $texto === ltp_texto_ficheiro($idioma, (string) $caminho)) {
            unset($atuais[$caminho]);
        } else {
            $atuais[$caminho] = mb_substr($texto, 0, 2000);
        }
    }
    ltp_definir('textos_' . $idioma, json_encode($atuais, JSON_UNESCAPED_UNICODE));
    ltp_textos('', true);
}

function ltp_apagar_textos_alterados(): void
{
    foreach (LTP_IDIOMAS as $c) {
        ltp_definir('textos_' . $c, '{}');
    }
    ltp_textos('', true);
}

function ltp_texto_ficheiro(string $idioma, string $caminho): string
{
    $v = ltp_textos_ficheiro($idioma);
    foreach (explode('.', $caminho) as $parte) {
        if (!is_array($v) || !array_key_exists($parte, $v)) {
            return '';
        }
        $v = $v[$parte];
    }
    return is_string($v) ? $v : '';
}

// Texto de um idioma sem recorrer a outros idiomas (para o editor saber o que falta traduzir)
function ltp_texto_proprio(string $idioma, string $caminho): string
{
    $alterados = ltp_textos_alterados($idioma);
    return array_key_exists($caminho, $alterados) ? (string) $alterados[$caminho] : ltp_texto_ficheiro($idioma, $caminho);
}

function ltp_aplicar_caminhos(array $base, array $planos): array
{
    foreach ($planos as $caminho => $texto) {
        $ref = &$base;
        $partes = explode('.', (string) $caminho);
        $ultima = array_pop($partes);
        foreach ($partes as $parte) {
            if (!isset($ref[$parte]) || !is_array($ref[$parte])) {
                $ref[$parte] = [];
            }
            $ref = &$ref[$parte];
        }
        $ref[$ultima] = (string) $texto;
        unset($ref);
    }
    return $base;
}

/* Rótulos em português (painel e exportações) */

function ltp_rotulo_pergunta(array $p): string
{
    if ($p['rotulo'] !== '') {
        return $p['rotulo'];
    }
    $t = ltp_textos('pt');
    $titulo = $t['steps'][$p['id']]['title'] ?? ($t['steps'][$p['id']]['label'] ?? '');
    return $titulo !== '' ? $titulo : $p['id'];
}

function ltp_rotulo_resposta(array $p, ?string $codigo): string
{
    if ($codigo === null || $codigo === '') {
        return '';
    }
    if ($p['tipo'] === 'escala') {
        return $codigo;
    }
    $t = ltp_textos('pt');
    if ($p['tipo'] === 'ordenacao') {
        $r = $t['regions'][$codigo] ?? null;
        return $r && ($r['name'] ?? '') !== '' ? $r['name'] . (($r['country'] ?? '') !== '' ? ' (' . $r['country'] . ')' : '') : $codigo;
    }
    $texto = $t['options'][$codigo] ?? $codigo;
    foreach ((ltp_info_opcao($codigo)['euros'] ?? []) as $k => $n) {
        $texto = str_replace('{' . $k . '}', ltp_euros_pt((int) $n), $texto);
    }
    return $texto;
}

function ltp_euros_pt(int $valor): string
{
    if (class_exists('NumberFormatter')) {
        $f = new NumberFormatter('pt-PT', NumberFormatter::CURRENCY);
        $f->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);
        $texto = $f->formatCurrency($valor, 'EUR');
        if (is_string($texto)) {
            return $texto;
        }
    }
    return number_format($valor, 0, ',', $valor >= 10000 ? ' ' : '') . ' €';
}

/* Respostas */

// Valor de uma resposta guardada, ajustado ao tipo atual da pergunta (lista nas de escolha múltipla e de ordenação)
function ltp_valor(array $dados, array $p)
{
    $v = $dados[$p['id']] ?? null;
    if (in_array($p['tipo'], ['multipla', 'ordenacao'], true)) {
        return array_values(array_filter(array_map('strval', is_array($v) ? $v : ($v === null || $v === '' ? [] : [$v])), 'strlen'));
    }
    if (is_array($v)) {
        $v = $v[0] ?? null;
    }
    return $v === null ? '' : (string) $v;
}

// Texto de uma resposta para o painel e as exportações ("; " entre várias escolhas)
function ltp_resposta_texto(array $dados, array $p): string
{
    $v = ltp_valor($dados, $p);
    $texto = is_array($v) ? implode('; ', array_map(function ($c) use ($p) { return ltp_rotulo_resposta($p, $c); }, $v))
        : (in_array($p['tipo'], ['texto_curto', 'texto_longo'], true) ? $v : ltp_rotulo_resposta($p, $v));
    $outra = trim((string) ($dados[$p['id'] . '_other'] ?? ''));
    return $outra !== '' && $p['outra'] ? trim($texto . ($texto !== '' ? '; ' : '') . 'Outra: ' . $outra) : $texto;
}

// Valida as respostas recebidas da página, pergunta a pergunta; devolve [respostas normalizadas, erro]
function ltp_validar(array $r): array
{
    $limpo = [];
    foreach (ltp_perguntas_ativas() as $id => $p) {
        $v = $r[$id] ?? null;
        $opcoes = ltp_opcoes($p);
        if (in_array($p['tipo'], ['multipla', 'ordenacao'], true)) {
            $v = is_array($v) ? array_values(array_unique(array_map('strval', array_filter($v, 'is_scalar')))) : [];
            $limite = $p['tipo'] === 'ordenacao' ? max(1, $p['max'] ?: 3) : $p['max'];
            if (($p['obrigatoria'] && !$v) || ($limite > 0 && count($v) > $limite) || array_diff($v, $opcoes)) {
                return [null, $id];
            }
        } elseif (in_array($p['tipo'], ['unica', 'escala'], true)) {
            $v = is_scalar($v) ? (string) $v : '';
            if (($v === '' && $p['obrigatoria']) || ($v !== '' && !in_array($v, $opcoes, true))) {
                return [null, $id];
            }
        } else {
            $v = ltp_limpar_texto($r[$id] ?? '', $p['max'] ?: ($p['tipo'] === 'texto_longo' ? LTP_MAX_COMENTARIO : LTP_MAX_OUTRA));
            if ($p['obrigatoria'] && $v === '') {
                return [null, $id];
            }
        }
        $limpo[$id] = $v;
        if ($p['outra']) {
            $limpo[$id . '_other'] = ltp_limpar_texto($r[$id . '_other'] ?? '', LTP_MAX_OUTRA);
        }
    }
    return [$limpo, null];
}
