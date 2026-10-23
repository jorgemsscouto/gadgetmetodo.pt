<?php
// Cursos e módulos: dados iniciais, migração das sessões antigas e apresentação do programa.
// Cada sessão do calendário pertence a um curso e pode ser de um só módulo ou do curso completo.

declare(strict_types=1);

const TIPOLOGIAS = ['Programa de formação', 'Curso de curta duração', 'Workshop', 'Masterclass', 'Webinar', 'Formação à medida'];
const CURSO_AI_ESSENTIALS = 'ai-essentials';

// Cursos criados na primeira utilização: o AI Essentials, com o conteúdo do site, e um exemplo para adaptar
function semear_cursos(PDO $pdo): void
{
    $cursos = [
        [
            'chave' => CURSO_AI_ESSENTIALS, 'nome' => 'AI Essentials', 'tipologia' => 'Programa de formação', 'diagnostico' => 1,
            'descricao' => 'Programa de formação em inteligência artificial aplicada à gestão, em cinco módulos, para decidir, utilizar e governar a IA com conhecimento de causa. Os exemplos e o nível técnico são ajustados ao setor e a cada grupo. Ministrado por formador com Certificado de Competências Pedagógicas (CCP).',
            'publico' => 'Administrações, direções e equipas que vão decidir sobre IA, utilizá-la no dia a dia ou garantir a sua conformidade.',
            'duracao' => '',
            'modulos' => [
                ['Fundamentos de IA para a administração', 'Bases para decidir sobre inteligência artificial na empresa.', "O que a IA faz e não faz\nTipos de ferramentas\nImpacto no negócio\nCritérios de decisão"],
                ['Casos de uso e integração em sistemas', 'Como passar das ideias aos casos de uso com retorno.', "Identificar oportunidades\nPriorizar pelo retorno\nIntegrar a IA em processos e aplicações existentes"],
                ['Governação e ética da IA', 'Regras e responsabilidades para uma utilização controlada.', "Regras de utilização\nResponsabilidades\nRiscos e controlo"],
                ['RGPD, privacidade e IA', 'Proteção de dados pessoais na utilização de ferramentas de IA.', "Dados pessoais em ferramentas de IA\nTransferências internacionais\nContratos com fornecedores"],
                ['NIS2 e segurança da informação', 'Cibersegurança e o papel de cada pessoa.', "Requisitos de cibersegurança\nBoas práticas\nO papel de cada pessoa"],
            ],
        ],
        [
            'chave' => 'exemplo-processos', 'nome' => 'Mapeamento e melhoria de processos (exemplo)', 'tipologia' => 'Workshop', 'diagnostico' => 0,
            'descricao' => 'Exemplo de curso para adaptar ou apagar. Workshop prático para mapear um processo real da empresa, identificar desperdícios e preparar o plano de melhoria.',
            'publico' => 'Responsáveis de área, chefias intermédias e equipas de qualidade.',
            'duracao' => '7 horas',
            'modulos' => [
                ['Levantamento e mapeamento', 'Do processo real ao mapa partilhado pela equipa.', "Técnicas de levantamento\nNotação essencial de processos\nFronteiras, entradas, saídas e responsáveis", 2],
                ['Análise e oportunidades', 'Onde se perde tempo, qualidade e controlo.', "Indicadores de tempo e de qualidade\nPontos de controlo\nOportunidades de simplificação e automação", 2],
                ['Redesenho e plano de melhoria', 'Do processo futuro à execução.', "Processo futuro\nNormas e procedimentos\nPlano de ação com responsáveis e prazos", 3],
            ],
        ],
    ];
    $agora = date('Y-m-d H:i:s');
    foreach ($cursos as $c) {
        $pdo->prepare('INSERT INTO cursos (chave, nome, tipologia, descricao, publico, duracao, diagnostico, ativo, criado) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)')
            ->execute([$c['chave'], $c['nome'], $c['tipologia'], $c['descricao'], $c['publico'], $c['duracao'], $c['diagnostico'], $agora]);
        $curso_id = (int) $pdo->lastInsertId();
        foreach ($c['modulos'] as $n => $m) {
            $pdo->prepare('INSERT INTO modulos (curso_id, ordem, nome, descricao, temas, horas) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$curso_id, $n + 1, $m[0], $m[1], $m[2], $m[3] ?? null]);
        }
    }
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

// "Módulo N: nome", com N pela ordem atual dos módulos do curso
function rotulo_modulo(array $m): string
{
    foreach (modulos_curso((int) $m['curso_id']) as $n => $outro) {
        if ((int) $outro['id'] === (int) $m['id']) {
            return 'Módulo ' . ($n + 1) . ': ' . $m['nome'];
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
    return $n > 1 ? 'Curso completo (' . $n . ' módulos)' : (string) ($c['tipologia'] ?: 'Curso completo');
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
        $corpo = ($m['descricao'] ? '<p>' . h($m['descricao']) . '</p>' : '') . $lista(temas($m['temas']));
    } else {
        $corpo = ($c['descricao'] ? '<p>' . nl2br(h($c['descricao'])) . '</p>' : '');
        foreach (modulos_curso((int) $c['id']) as $m) {
            $corpo .= '<h4>' . h(rotulo_modulo($m)) . ($m['horas'] ? ' <span class="texto-suave">· ' . h(horas((string) $m['horas'])) . '</span>' : '') . '</h4>' . $lista(temas($m['temas']));
        }
    }
    if ($c['publico']) {
        $corpo .= '<p class="texto-suave"><strong>Destinatários:</strong> ' . h($c['publico']) . '</p>';
    }
    return $corpo === '' ? '' : '<details class="programa"' . ($aberto ? ' open' : '') . '><summary>Programa e temas</summary><div class="programa-corpo">' . $corpo . '</div></details>';
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
