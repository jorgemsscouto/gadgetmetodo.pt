<?php
// Cursos e módulos: catálogo inicial, migração das sessões antigas e apresentação do programa.
// Cada sessão do calendário pertence a um curso e pode ser de um só módulo ou do curso completo.

declare(strict_types=1);

const TIPOLOGIAS = ['Programa de formação', 'Curso de curta duração', 'Workshop', 'Masterclass', 'Webinar', 'Formação à medida'];
const CURSO_AI_ESSENTIALS = 'ai-essentials';

const CATALOGO = __DIR__ . '/catalogo/ia-generativa.json';

function catalogo(): array
{
    $dados = json_decode((string) @file_get_contents(CATALOGO), true);
    return is_array($dados) ? $dados : [];
}

/* Definições guardadas na base de dados (por exemplo, a versão do catálogo carregado) */

function definicao(PDO $pdo, string $chave): ?string
{
    $st = $pdo->prepare('SELECT valor FROM definicoes WHERE chave = ?');
    $st->execute([$chave]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}

function definir(PDO $pdo, string $chave, string $valor): void
{
    $pdo->prepare('INSERT OR REPLACE INTO definicoes (chave, valor) VALUES (?, ?)')->execute([$chave, $valor]);
}

// Carrega os cursos do catálogo (ficheiro de dados) para a base de dados
function carregar_catalogo(PDO $pdo): int
{
    $cat = catalogo();
    $comum = $cat['comum'] ?? [];
    $agora = date('Y-m-d H:i:s');
    $n = 0;
    foreach ($cat['cursos'] ?? [] as $c) {
        $c += $comum;
        $pdo->prepare('INSERT INTO cursos (chave, nome, tipologia, descricao, publico, duracao, diagnostico, ativo, criado, percurso, unidade, objetivo, prerequisitos, resultado, estrutura, avaliacao, requisitos, enquadramento, investimento)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$c['chave'] ?? null, $c['nome'], $c['tipologia'] ?? '', $c['descricao'] ?? '', $c['publico'] ?? '', $c['duracao'] ?? '', (int) ($c['diagnostico'] ?? 0), $agora,
                $c['percurso'] ?? '', $c['unidade'] ?? 'Módulo', $c['objetivo'] ?? '', $c['prerequisitos'] ?? '', $c['resultado'] ?? '', $c['estrutura'] ?? '', $c['avaliacao'] ?? '', $c['requisitos'] ?? '', $c['enquadramento'] ?? '', $c['investimento'] ?? '']);
        $curso_id = (int) $pdo->lastInsertId();
        foreach ($c['modulos'] ?? [] as $i => $m) {
            $pdo->prepare('INSERT INTO modulos (curso_id, ordem, nome, descricao, temas, horas, caso_pratico) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$curso_id, $i + 1, $m['nome'], $m['descricao'] ?? '', $m['temas'] ?? '', $m['horas'] ?? null, $m['caso_pratico'] ?? '']);
        }
        $n++;
    }
    return $n;
}

// Apaga todas as sessões do calendário (e as respetivas inscrições), as pré-inscrições, os módulos e os cursos
function limpar_agenda_e_cursos(PDO $pdo): void
{
    $pdo->exec('DELETE FROM inscricoes');
    $pdo->exec('DELETE FROM preinscricoes');
    $pdo->exec('DELETE FROM sessoes');
    $pdo->exec('DELETE FROM formacoes');
    $pdo->exec('DELETE FROM modulos');
    $pdo->exec('DELETE FROM cursos');
}

function repor_catalogo(PDO $pdo): int
{
    $pdo->beginTransaction();
    limpar_agenda_e_cursos($pdo);
    $n = carregar_catalogo($pdo);
    definir($pdo, 'catalogo', (string) (catalogo()['versao'] ?? ''));
    $pdo->commit();
    return $n;
}

// Na primeira utilização de uma versão nova do catálogo: substitui a agenda e os cursos só quando não há
// inscrições nem pré-inscrições a perder; caso contrário, a Gadgetmetodo decide na administração (Cursos, "Repor o catálogo")
function aplicar_catalogo(PDO $pdo): void
{
    $versao = (string) (catalogo()['versao'] ?? '');
    if ($versao === '' || definicao($pdo, 'catalogo') === $versao) {
        return;
    }
    if (!$pdo->query('SELECT 1 FROM inscricoes UNION ALL SELECT 1 FROM preinscricoes LIMIT 1')->fetchColumn()) {
        repor_catalogo($pdo);
    } elseif (!$pdo->query('SELECT 1 FROM cursos LIMIT 1')->fetchColumn()) {
        carregar_catalogo($pdo);
        definir($pdo, 'catalogo', $versao);
    }
}

function catalogo_pendente(): bool
{
    $versao = (string) (catalogo()['versao'] ?? '');
    return $versao !== '' && definicao(bd(), 'catalogo') !== $versao;
}

// Sessões criadas antes da configuração de cursos: passam a pertencer ao AI Essentials
function migrar_sessoes_para_cursos(PDO $pdo): void
{
    if (!$pdo->query('SELECT 1 FROM formacoes WHERE curso_id IS NULL LIMIT 1')->fetchColumn()) {
        return;
    }
    $curso = $pdo->query("SELECT id FROM cursos WHERE chave = '" . CURSO_AI_ESSENTIALS . "'")->fetchColumn();
    if (!$curso) {
        return;
    }
    for ($n = 1; $n <= 5; $n++) {
        $pdo->prepare('UPDATE formacoes SET modulo_id = (SELECT id FROM modulos WHERE curso_id = ? AND ordem = ?) WHERE curso_id IS NULL AND modulo = ?')
            ->execute([$curso, $n, 'm' . $n]);
    }
    $pdo->prepare('UPDATE formacoes SET curso_id = ? WHERE curso_id IS NULL')->execute([$curso]);
}

// Formações agendadas sessão a sessão (uma formação por módulo, antes de a inscrição passar a ser sempre no curso
// completo): as de cada curso que ainda não terminaram juntam-se numa edição, com a data de cada sessão. As inscrições
// passam para a edição; quem estava inscrito em várias sessões fica com uma só inscrição (a confirmada ou, não havendo,
// a mais antiga) e as restantes ficam canceladas. As edições que já terminaram ficam como estavam.
function juntar_formacoes_por_sessao(PDO $pdo): void
{
    $st = $pdo->prepare("SELECT f.*, m.ordem FROM formacoes f JOIN modulos m ON m.id = f.modulo_id
        WHERE f.estado != 'cancelada' AND f.inicio >= ? ORDER BY f.curso_id, f.inicio");
    $st->execute([date('Y-m-d 00:00:00', strtotime('-120 days'))]);
    $lista = $st->fetchAll(PDO::FETCH_ASSOC);
    // Uma edição por grupo: muda quando muda o curso, quando a sessão do curso se repete ou recua, ou após 60 dias sem sessões
    $grupos = [];
    foreach ($lista as $f) {
        $n = count($grupos) - 1;
        $ultima = $n >= 0 ? $grupos[$n][count($grupos[$n]) - 1] : null;
        if (!$ultima || $ultima['curso_id'] !== $f['curso_id'] || (int) $f['ordem'] <= (int) $ultima['ordem']
            || strtotime($f['inicio']) - strtotime($ultima['inicio']) > 60 * 86400) {
            $grupos[] = [$f];
        } else {
            $grupos[$n][] = $f;
        }
    }
    $grupos = array_filter($grupos, function ($g) { $u = $g[count($g) - 1]; return ($u['fim'] ?: $u['inicio']) >= date('Y-m-d 00:00:00'); });
    if (!$grupos) {
        return;
    }
    $pdo->beginTransaction();
    $sessao = $pdo->prepare('INSERT INTO sessoes (formacao_id, ordem, modulo_id, inicio, fim) VALUES (?, ?, ?, ?, ?)');
    foreach ($grupos as $g) {
        $id = (int) $g[0]['id'];
        $ultima = $g[count($g) - 1];
        $primeiro = function (string $campo) use ($g) { return array_values(array_filter(array_column($g, $campo)))[0] ?? null; };
        $pdo->prepare('UPDATE formacoes SET modulo_id = NULL, fim = ?, local = ?, reuniao = ? WHERE id = ?')
            ->execute([$ultima['fim'] ?: null, (string) $primeiro('local'), $primeiro('reuniao'), $id]);
        $pdo->prepare('DELETE FROM sessoes WHERE formacao_id = ?')->execute([$id]);
        foreach ($g as $n => $f) {
            $sessao->execute([$id, $n + 1, $f['modulo_id'], $f['inicio'], $f['fim'] ?: null]);
        }
        $outras = implode(',', array_map('intval', array_column(array_slice($g, 1), 'id')));
        if ($outras !== '') {
            $pdo->exec("UPDATE inscricoes SET formacao_id = $id WHERE formacao_id IN ($outras)");
            $pdo->exec("DELETE FROM sessoes WHERE formacao_id IN ($outras)");
            $pdo->exec("DELETE FROM formacoes WHERE id IN ($outras)");
        }
        $pessoa = function (string $t) { return "COALESCE(NULLIF(lower($t.email), ''), lower($t.formando))"; };
        $pdo->exec("UPDATE inscricoes SET estado = 'cancelada' WHERE formacao_id = $id AND estado != 'cancelada'
            AND id != (SELECT i2.id FROM inscricoes i2 WHERE i2.formacao_id = $id AND i2.estado != 'cancelada' AND " . $pessoa('i2') . ' = ' . $pessoa('inscricoes') . "
                ORDER BY i2.estado = 'confirmada' DESC, i2.id LIMIT 1)");
    }
    $pdo->commit();
}

/* Consulta (com memória por pedido) */

function curso(?int $id): ?array
{
    static $cache = [];
    if (!$id) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = linha_bd('SELECT * FROM cursos WHERE id = ?', [$id]);
    }
    return $cache[$id];
}

function modulos_curso(?int $curso_id): array
{
    static $cache = [];
    if (!$curso_id) {
        return [];
    }
    if (!isset($cache[$curso_id])) {
        $cache[$curso_id] = consulta('SELECT * FROM modulos WHERE curso_id = ? ORDER BY ordem, id', [$curso_id]);
    }
    return $cache[$curso_id];
}

// "o Curso 2: ..." quando o nome já começa por "Curso"; "o curso ..." nos restantes
function curso_com_artigo(string $nome): string
{
    return (preg_match('/^Curso\b/u', $nome) ? 'o ' : 'o curso ') . $nome;
}

// "Curso 1: Iniciação..." passa a sobretítulo "Curso 1" e título "Iniciação..."
function partes_nome(array $c): array
{
    if (preg_match('/^((?:Curso|Módulo|Nível|Parte)\s+\S+)\s*:\s*(.+)$/u', (string) $c['nome'], $m)) {
        return [$m[1], $m[2]];
    }
    return [(string) ($c['tipologia'] ?: 'Curso'), (string) $c['nome']];
}

// Nome das unidades do curso: "Módulo" ou "Sessão"
function unidade(?array $curso, bool $plural = false): string
{
    $u = ($curso['unidade'] ?? '') === 'Sessão' ? 'Sessão' : 'Módulo';
    return $plural ? ($u === 'Sessão' ? 'sessões' : 'módulos') : $u;
}

// "Módulo N: nome" (ou "Sessão N: nome"), com N pela ordem atual dos módulos do curso
function rotulo_modulo(array $m): string
{
    $u = unidade(curso((int) $m['curso_id']));
    foreach (modulos_curso((int) $m['curso_id']) as $n => $outro) {
        if ((int) $outro['id'] === (int) $m['id']) {
            return $u . ' ' . ($n + 1) . ': ' . $m['nome'];
        }
    }
    return $m['nome'];
}

function modulo_da_sessao(array $f): ?array
{
    if (empty($f['modulo_id'])) {
        return null;
    }
    foreach (modulos_curso((int) ($f['curso_id'] ?? 0)) as $m) {
        if ((int) $m['id'] === (int) $f['modulo_id']) {
            return $m;
        }
    }
    return null;
}

// O que a sessão cobre: um módulo ou o curso completo
function nome_modulo(array $f): string
{
    if ($m = modulo_da_sessao($f)) {
        return rotulo_modulo($m);
    }
    $c = curso((int) ($f['curso_id'] ?? 0));
    if (!$c) {
        return '';
    }
    $n = count(modulos_curso((int) $c['id']));
    return $n > 1 ? 'Curso completo (' . $n . ' ' . unidade($c, true) . ')' : (string) ($c['tipologia'] ?: 'Curso completo');
}

function com_diagnostico(array $f): bool
{
    $c = curso((int) ($f['curso_id'] ?? 0));
    return $c ? (bool) $c['diagnostico'] : true;
}

function eh_ai_essentials(array $f): bool
{
    return (curso((int) ($f['curso_id'] ?? 0))['chave'] ?? '') === CURSO_AI_ESSENTIALS;
}

function temas(?string $texto): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $texto) ?: [])));
}

function horas(?string $h): string
{
    if ($h === null || $h === '' || (float) $h <= 0) {
        return '';
    }
    $v = rtrim(rtrim(number_format((float) $h, 1, ',', ''), '0'), ',');
    return $v . ((float) $h === 1.0 ? ' hora' : ' horas');
}

// Programa da sessão (descrição e temas), num bloco que abre e fecha sem JavaScript
function programa_html(array $f, bool $aberto = false): string
{
    $c = curso((int) ($f['curso_id'] ?? 0));
    if (!$c) {
        return '';
    }
    $lista = function (array $t) {
        return $t ? '<ul>' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $t)) . '</ul>' : '';
    };
    if ($m = modulo_da_sessao($f)) {
        $corpo = ($m['descricao'] ? '<p>' . h($m['descricao']) . '</p>' : '') . $lista(temas($m['temas'])) . caso_html($m);
    } else {
        $corpo = ($c['descricao'] ? '<p>' . nl2br(h($c['descricao'])) . '</p>' : '');
        foreach (modulos_curso((int) $c['id']) as $m) {
            $corpo .= '<h4>' . h(rotulo_modulo($m)) . ($m['horas'] ? ' <span class="texto-suave">· ' . h(horas((string) $m['horas'])) . '</span>' : '') . '</h4>' . $lista(temas($m['temas'])) . caso_html($m);
        }
    }
    if ($c['publico']) {
        $corpo .= '<p class="texto-suave"><strong>Destinatários:</strong> ' . h($c['publico']) . '</p>';
    }
    return $corpo === '' ? '' : '<details class="programa"' . ($aberto ? ' open' : '') . '><summary>Programa e temas</summary><div class="programa-corpo">' . $corpo . '</div></details>';
}

function caso_html(array $m): string
{
    return !empty($m['caso_pratico']) ? '<p class="caso"><strong>Caso prático:</strong> ' . h($m['caso_pratico']) . '</p>' : '';
}

// Linhas "rótulo: valor" (estrutura da sessão, avaliação), separadas no primeiro ":"
function pares(?string $texto): array
{
    $r = [];
    foreach (temas($texto) as $linha) {
        $i = strpos($linha, ':');
        $r[] = $i === false ? [$linha, ''] : [trim(substr($linha, 0, $i)), trim(substr($linha, $i + 1))];
    }
    return $r;
}

// Minutos do Laboratório de Casos Práticos por sessão, a partir da estrutura-tipo
function minutos_caso(array $c): int
{
    foreach (pares($c['estrutura'] ?? '') as [$bloco, $min]) {
        if (stripos($bloco, 'caso') !== false || stripos($bloco, 'laborat') !== false) {
            return (int) $min;
        }
    }
    return 0;
}

/* Blocos da ficha do curso, usados na página pública e na ficha em PDF */

function lista_html(array $itens, string $classe = ''): string
{
    return $itens ? '<ul' . ($classe ? ' class="' . $classe . '"' : '') . '>' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $itens)) . '</ul>' : '';
}

function paragrafos_html(string $texto): string
{
    return implode('', array_map(function ($p) { return '<p>' . h($p) . '</p>'; }, temas($texto)));
}

// Factos principais do curso (duração, número de sessões ou módulos, destinatários, pré-requisitos)
function factos_curso(array $c, array $mods): array
{
    $total = array_sum(array_map(function ($m) { return (float) $m['horas']; }, $mods));
    return array_filter([
        'Tipologia' => (string) $c['tipologia'],
        'Duração' => (string) ($c['duracao'] ?: horas($total ? (string) $total : null)),
        (unidade($c) === 'Sessão' ? 'Sessões' : 'Módulos') => $mods ? (string) count($mods) : '',
        'Destinatários' => (string) $c['publico'],
        'Pré-requisitos' => (string) $c['prerequisitos'],
    ]);
}

// Estrutura-tipo da sessão: barra proporcional (decorativa) e tabela com os minutos de cada bloco
function estrutura_html(array $c): string
{
    $pares = pares($c['estrutura'] ?? '');
    if (!$pares) {
        return '';
    }
    $total = array_sum(array_map(function ($x) { return (int) $x[1]; }, $pares));
    $barra = '';
    $linhas = '';
    foreach ($pares as $i => [$bloco, $min]) {
        $lab = stripos($bloco, 'laborat') !== false || stripos($bloco, 'caso') !== false;
        if ($total > 0 && (int) $min > 0) {
            $barra .= '<span class="seg ' . ($lab ? 'seg-lab' : 'seg-' . ($i % 3)) . '" style="width:' . round((int) $min / $total * 100, 2) . '%">' . (int) $min . '</span>';
        }
        $linhas .= '<tr' . ($lab ? ' class="destaque"' : '') . '><td>' . h($bloco) . '</td><td class="num">' . h($min) . '</td></tr>';
    }
    return '<div class="estrutura-sessao">' . ($barra ? '<div class="barra-sessao" aria-hidden="true">' . $barra . '</div>' : '')
        . '<table class="tabela-pares"><thead><tr><th scope="col">Bloco</th><th scope="col" class="num">Minutos</th></tr></thead><tbody>' . $linhas
        . ($total ? '<tr class="total"><th scope="row">Total</th><td class="num">' . $total . '</td></tr>' : '') . '</tbody></table></div>';
}

function avaliacao_html(array $c): string
{
    $pares = pares($c['avaliacao'] ?? '');
    if (!$pares) {
        return '';
    }
    $linhas = '';
    foreach ($pares as [$componente, $peso]) {
        $linhas .= '<tr><td>' . h($componente) . '</td><td>' . h($peso) . '</td></tr>';
    }
    return '<table class="tabela-pares"><thead><tr><th scope="col">Componente</th><th scope="col">Peso ou critério</th></tr></thead><tbody>' . $linhas . '</tbody></table>';
}

// Atalho para criar a sessão no Google Calendar, com data e hora preenchidas, e acrescentar o Google Meet
function link_google_calendar(array $f): string
{
    $inicio = strtotime($f['inicio']);
    $fim = !empty($f['fim']) ? strtotime($f['fim']) : $inicio + 3 * 3600;
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE',
        'text' => $f['titulo'] . ' · ' . nome_modulo($f),
        'dates' => date('Ymd\THis', $inicio) . '/' . date('Ymd\THis', $fim),
        'ctz' => 'Europe/Lisbon',
        'details' => 'Sessão de formação da Gadgetmetodo, Lda.',
        'location' => (string) ($f['local'] ?? ''),
    ]);
}
