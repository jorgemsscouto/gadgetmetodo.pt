<?php
// Inquérito Luxury Tours Portugal: funções do painel (estatísticas, importação, exportações e dados de demonstração).
// O painel e as exportações mostram sempre os rótulos em português (i18n/pt.json).

declare(strict_types=1);

const LTP_NOMES_IDIOMAS = ['pt' => 'Português', 'en' => 'Inglês', 'es' => 'Espanhol', 'fr' => 'Francês', 'de' => 'Alemão', 'el' => 'Grego', 'ar' => 'Árabe'];
const LTP_ROTULOS_CAMPOS = [
    'intent' => 'Intenção de viajar (próximos dois anos)',
    'duration' => 'Duração preferida',
    'group' => 'Com quem viajaria',
    'budget' => 'Orçamento por pessoa (sem voos)',
    'seasons' => 'Época preferida (escolha múltipla)',
    'priorities' => 'O que torna a viagem inesquecível (até três)',
    'contact' => 'Aceita ser contactado',
];
// Colunas da tabela respostas correspondentes a cada campo do inquérito
const LTP_COLUNAS = [
    'intent' => 'intencao', 'duration' => 'duracao', 'group' => 'grupo', 'budget' => 'orcamento',
    'seasons' => 'epocas', 'priorities' => 'prioridades', 'contact' => 'contacto',
];

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

function ltp_rotulo_regiao(?string $codigo): string
{
    if ($codigo === null || $codigo === '') {
        return '';
    }
    $r = ltp_textos('pt')['regions'][$codigo] ?? null;
    return $r ? $r['name'] . ' (' . $r['country'] . ')' : $codigo;
}

function ltp_rotulo_opcao(?string $codigo): string
{
    if ($codigo === null || $codigo === '') {
        return '';
    }
    $texto = ltp_textos('pt')['options'][$codigo] ?? $codigo;
    $v = LTP_ORCAMENTO_VALORES[$codigo] ?? [];
    foreach ($v as $k => $n) {
        $texto = str_replace('{' . $k . '}', ltp_euros_pt($n), $texto);
    }
    return $texto;
}

function ltp_rotulos_lista(?string $codigos): string
{
    return implode('; ', array_map('ltp_rotulo_opcao', array_filter(explode(',', (string) $codigos))));
}

// Data e hora no formato português (dd/mm/aaaa hh:mm)
function ltp_data_pt(?string $iso, bool $comHora = true): string
{
    $d = $iso ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $iso) : false;
    return $d ? $d->format($comHora ? 'd/m/Y H:i' : 'd/m/Y') : '';
}

function ltp_percentagem(int $parte, int $total): string
{
    return $total > 0 ? number_format(100 * $parte / $total, 0, ',', ' ') . ' %' : '–';
}

/* Estatísticas */

function ltp_respostas(PDO $pdo, ?string $idioma): array
{
    $sql = 'SELECT r.*, c.nome, c.email, c.idioma AS idioma_cliente, c.pais AS pais_cliente FROM respostas r LEFT JOIN clientes c ON c.id = r.cliente_id';
    $par = [];
    if ($idioma !== null) {
        $sql .= ' WHERE r.idioma = ?';
        $par[] = $idioma;
    }
    $st = $pdo->prepare($sql . ' ORDER BY r.atualizado DESC');
    $st->execute($par);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// Ranking ponderado: 3 pontos para o 1.º lugar, 2 para o 2.º e 1 para o 3.º
function ltp_ranking(array $respostas): array
{
    $linhas = [];
    foreach (LTP_REGIOES as $c) {
        $linhas[$c] = ['codigo' => $c, 'pontos' => 0, 'primeiro' => 0, 'mencoes' => 0];
    }
    foreach ($respostas as $r) {
        foreach (['regiao_1' => 3, 'regiao_2' => 2, 'regiao_3' => 1] as $col => $pts) {
            $c = $r[$col];
            if ($c && isset($linhas[$c])) {
                $linhas[$c]['pontos'] += $pts;
                $linhas[$c]['mencoes']++;
                if ($pts === 3) {
                    $linhas[$c]['primeiro']++;
                }
            }
        }
    }
    $linhas = array_values($linhas);
    usort($linhas, fn($a, $b) => [$b['pontos'], $b['primeiro'], $b['mencoes']] <=> [$a['pontos'], $a['primeiro'], $a['mencoes']]);
    return $linhas;
}

function ltp_distribuicao(array $respostas, string $campo): array
{
    [$tipo, $opcoes] = LTP_CAMPOS[$campo];
    $contagem = array_fill_keys($opcoes, 0);
    $col = LTP_COLUNAS[$campo];
    foreach ($respostas as $r) {
        $valores = $tipo === 'multi' ? array_filter(explode(',', (string) $r[$col])) : [$r[$col]];
        foreach ($valores as $v) {
            if (isset($contagem[$v])) {
                $contagem[$v]++;
            }
        }
    }
    return $contagem;
}

// Taxa de resposta dos clientes convidados, pelo idioma da lista de clientes ('' = sem idioma definido)
function ltp_taxas(PDO $pdo): array
{
    $linhas = $pdo->query("SELECT COALESCE(c.idioma, '') AS idioma, COUNT(*) AS convidados, COUNT(r.id) AS responderam
        FROM clientes c LEFT JOIN respostas r ON r.cliente_id = c.id GROUP BY COALESCE(c.idioma, '')")->fetchAll(PDO::FETCH_ASSOC);
    $porIdioma = [];
    foreach ($linhas as $l) {
        $porIdioma[$l['idioma']] = ['convidados' => (int) $l['convidados'], 'responderam' => (int) $l['responderam']];
    }
    $gerais = $pdo->query('SELECT idioma, COUNT(*) FROM respostas WHERE cliente_id IS NULL GROUP BY idioma')->fetchAll(PDO::FETCH_KEY_PAIR);
    $resultado = [];
    foreach (array_merge(LTP_IDIOMAS, ['']) as $i) {
        $resultado[$i] = ($porIdioma[$i] ?? ['convidados' => 0, 'responderam' => 0]) + ['ligacao_geral' => (int) ($gerais[$i] ?? 0)];
    }
    return $resultado;
}

/* Importação da lista de clientes (CSV do Excel, separador ; ou , e UTF-8 ou Windows-1252) */

function ltp_normalizar_cabecalho(string $c): string
{
    $c = mb_strtolower(trim($c, " \t\n\r\0\x0B\"'\u{FEFF}"));
    $c = strtr($c, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c']);
    $mapa = [
        'name' => 'nome', 'nome' => 'nome', 'first name' => 'nome', 'first_name' => 'nome', 'cliente' => 'nome',
        'email' => 'email', 'e-mail' => 'email', 'mail' => 'email',
        'language' => 'idioma', 'idioma' => 'idioma', 'lang' => 'idioma', 'lingua' => 'idioma',
        'form' => 'tratamento', 'tratamento' => 'tratamento', 'gender' => 'tratamento', 'genero' => 'tratamento', 'salutation' => 'tratamento',
        'country' => 'pais', 'pais' => 'pais',
        'reference' => 'referencia', 'referencia' => 'referencia', 'ref' => 'referencia', 'id' => 'referencia',
    ];
    return $mapa[$c] ?? '';
}

function ltp_normalizar_tratamento(string $v): string
{
    $v = mb_strtolower(trim($v, " ."));
    if (in_array($v, ['m', 'masculino', 'male', 'sr', 'mr', 'senhor', 'h', 'homem'], true)) {
        return 'm';
    }
    if (in_array($v, ['f', 'feminino', 'female', 'sra', 'mrs', 'ms', 'senhora', 'mulher'], true)) {
        return 'f';
    }
    return '';
}

function ltp_importar_csv(PDO $pdo, string $conteudo): array
{
    $rel = ['inseridos' => 0, 'atualizados' => 0, 'sem_idioma' => 0, 'ignorados' => []];
    if (strncmp($conteudo, "\xEF\xBB\xBF", 3) === 0) {
        $conteudo = substr($conteudo, 3);
    }
    if (!mb_check_encoding($conteudo, 'UTF-8')) {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
    }
    $linhas = preg_split('/\r\n|\r|\n/', $conteudo);
    $primeira = (string) ($linhas[0] ?? '');
    $sep = ';';
    $max = -1;
    foreach ([';', ',', "\t"] as $s) {
        if (substr_count($primeira, $s) > $max) {
            $max = substr_count($primeira, $s);
            $sep = $s;
        }
    }
    $cab = array_map('ltp_normalizar_cabecalho', str_getcsv($primeira, $sep, '"', '\\'));
    if (!in_array('email', $cab, true)) {
        return ['erro' => 'O ficheiro tem de ter uma coluna "email" na primeira linha.'];
    }
    $existe = $pdo->prepare('SELECT id FROM clientes WHERE email = ?');
    $inserir = $pdo->prepare('INSERT INTO clientes (token, nome, email, idioma, tratamento, pais, referencia, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    // Numa nova importação, só se atualizam as colunas presentes no ficheiro
    $colunas = array_values(array_intersect(['nome', 'idioma', 'tratamento', 'pais', 'referencia'], $cab));
    $atualizar = $pdo->prepare('UPDATE clientes SET ' . implode('', array_map(fn($c) => "$c = ?, ", $colunas)) . 'demo = 0 WHERE id = ?');
    $pdo->beginTransaction();
    foreach (array_slice($linhas, 1) as $n => $linha) {
        if (trim($linha) === '' || trim(str_replace($sep, '', $linha)) === '') {
            continue;
        }
        $valores = str_getcsv($linha, $sep, '"', '\\');
        $d = ['nome' => '', 'email' => '', 'idioma' => '', 'tratamento' => '', 'pais' => '', 'referencia' => ''];
        foreach ($cab as $i => $campo) {
            if ($campo !== '' && isset($valores[$i])) {
                $d[$campo] = trim((string) $valores[$i]);
            }
        }
        $email = mb_strtolower($d['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $rel['ignorados'][] = 'Linha ' . ($n + 2) . ': email inválido (' . mb_substr($d['email'], 0, 60) . ')';
            continue;
        }
        // Idioma em falta ou inválido: fica vazio e a página usa o idioma do navegador (ou inglês)
        $idioma = ltp_normalizar_idioma($d['idioma']);
        if ($idioma === null) {
            $rel['sem_idioma']++;
        }
        $campos = [
            'nome' => mb_substr($d['nome'], 0, 120), 'idioma' => $idioma, 'tratamento' => ltp_normalizar_tratamento($d['tratamento']),
            'pais' => mb_substr($d['pais'], 0, 80), 'referencia' => mb_substr($d['referencia'], 0, 80),
        ];
        $existe->execute([$email]);
        $id = $existe->fetchColumn();
        if ($id) {
            $atualizar->execute(array_merge(array_values(array_intersect_key($campos, array_flip($colunas))), [$id]));
            $rel['atualizados']++;
        } else {
            $inserir->execute([ltp_novo_token(), $campos['nome'], $email, $campos['idioma'], $campos['tratamento'], $campos['pais'], $campos['referencia'], date('Y-m-d H:i:s')]);
            $rel['inseridos']++;
        }
    }
    $pdo->commit();
    return $rel;
}

/* Exportações */

function ltp_exportar_respostas(PDO $pdo, ?string $idioma): void
{
    $linhas = [];
    foreach (ltp_respostas($pdo, $idioma) as $r) {
        $linhas[] = [
            $r['criado'], $r['atualizado'], LTP_NOMES_IDIOMAS[$r['idioma']] ?? $r['idioma'],
            $r['cliente_id'] ? 'Convite' : 'Ligação geral',
            (string) ($r['cliente_id'] ? $r['nome'] : $r['ident_nome']), (string) ($r['cliente_id'] ? $r['email'] : $r['ident_email']),
            (string) $r['ident_telemovel'], $r['cliente_id'] ? (string) $r['pais_cliente'] : (ltp_paises()[$r['ident_pais'] ?? ''] ?? ''),
            ltp_rotulo_regiao($r['regiao_1']), ltp_rotulo_regiao($r['regiao_2']), ltp_rotulo_regiao($r['regiao_3']), (string) $r['regiao_outra'],
            ltp_rotulo_opcao($r['intencao']), ltp_rotulo_opcao($r['duracao']), ltp_rotulo_opcao($r['grupo']), ltp_rotulo_opcao($r['orcamento']),
            ltp_rotulos_lista($r['epocas']), ltp_rotulos_lista($r['prioridades']), (string) $r['comentario'], ltp_rotulo_opcao($r['contacto']),
            $r['demo'] ? 'Sim' : 'Não',
        ];
    }
    ltp_enviar_csv('respostas-luxury-tours-' . ($idioma ?? 'todos') . '-' . date('Y-m-d') . '.csv', [
        'Data da resposta', 'Última alteração', 'Idioma', 'Origem', 'Nome', 'Email', 'Telemóvel', 'País', '1.º lugar', '2.º lugar', '3.º lugar', 'Outra região',
        'Intenção', 'Duração', 'Com quem viajaria', 'Orçamento por pessoa', 'Época', 'Prioridades', 'Comentário', 'Contacto', 'Demonstração',
    ], $linhas);
}

function ltp_exportar_ranking(PDO $pdo, ?string $idioma): void
{
    $respostas = ltp_respostas($pdo, $idioma);
    $n = count($respostas);
    $linhas = [];
    foreach (ltp_ranking($respostas) as $i => $l) {
        $linhas[] = [$i + 1, ltp_rotulo_regiao($l['codigo']), $l['pontos'], $l['primeiro'], $l['mencoes'], ltp_percentagem($l['mencoes'], $n)];
    }
    ltp_enviar_csv('ranking-luxury-tours-' . ($idioma ?? 'todos') . '-' . date('Y-m-d') . '.csv',
        ['Posição', 'Região', 'Pontos (3-2-1)', 'Vezes em 1.º lugar', 'Menções', '% de respondentes'], $linhas);
}

// CSV para mail merge: uma linha por cliente, com o idioma, a saudação, o assunto e a ligação pessoal
function ltp_exportar_mail_merge(PDO $pdo, ?string $idioma, bool $soPendentes): void
{
    require_once __DIR__ . '/email.php';
    $publicados = ltp_idiomas_publicados();
    $linhas = [];
    $st = $pdo->query('SELECT c.*, r.id AS resposta FROM clientes c LEFT JOIN respostas r ON r.cliente_id = c.id ORDER BY c.idioma, c.nome');
    foreach ($st as $c) {
        // Sem idioma válido na lista, o email segue em inglês (a página adapta-se depois ao navegador)
        $lingua = in_array($c['idioma'], $publicados, true) ? $c['idioma']
            : (in_array(LTP_IDIOMA_PADRAO, $publicados, true) ? LTP_IDIOMA_PADRAO : ($publicados[0] ?? LTP_IDIOMA_PADRAO));
        if (($idioma !== null && $lingua !== $idioma) || ($soPendentes && $c['resposta'])) {
            continue;
        }
        $linhas[] = [
            $c['nome'], $c['email'], $lingua, $lingua === $c['idioma'] ? '' : ($c['idioma'] ? 'idioma ' . $c['idioma'] . ' por rever: usado ' . $lingua : 'idioma em falta na lista: usado ' . $lingua),
            ltp_email_saudacao($lingua, (string) $c['nome'], (string) $c['tratamento']),
            ltp_email_assunto($lingua), ltp_ligacao_pessoal($c['token']), ltp_email_data_limite($lingua),
        ];
    }
    ltp_enviar_csv('mail-merge-luxury-tours-' . ($idioma ?? 'todos') . '-' . date('Y-m-d') . '.csv',
        ['name', 'email', 'language', 'note', 'greeting', 'subject', 'link', 'deadline'], $linhas);
}

/* Dados de demonstração (fictícios, marcados e apagáveis à parte) */

function ltp_gerar_demo(PDO $pdo): int
{
    mt_srand(2026);
    $escolher = function (array $pesos) {
        $total = array_sum($pesos);
        $r = mt_rand(1, $total);
        foreach ($pesos as $k => $p) {
            $r -= $p;
            if ($r <= 0) {
                return $k;
            }
        }
        return array_key_first($pesos);
    };
    $idiomas = ['en' => 30, 'pt' => 14, 'fr' => 12, 'de' => 10, 'es' => 9, 'ar' => 13, 'el' => 8, '' => 4];
    $pesosRegioes = [
        'region_tuscany' => 18, 'region_champagne' => 15, 'region_bordeaux' => 14, 'region_santorini' => 11, 'region_burgundy' => 10,
        'region_piedmont' => 9, 'region_napa' => 7, 'region_rioja' => 6, 'region_stellenbosch' => 5, 'region_mendoza' => 4,
        'region_ribera' => 3, 'region_mosel' => 3,
    ];
    $pesos = [
        'intent' => ['intent_very_likely' => 30, 'intent_likely' => 38, 'intent_considering' => 24, 'intent_unlikely' => 8],
        'duration' => ['duration_3_4' => 22, 'duration_5_7' => 55, 'duration_8_plus' => 23],
        'group' => ['group_couple' => 58, 'group_family' => 16, 'group_friends' => 18, 'group_private' => 8],
        'budget' => ['budget_up_to_5000' => 12, 'budget_5000_10000' => 38, 'budget_10000_20000' => 30, 'budget_over_20000' => 12, 'budget_undisclosed' => 8],
        'contact' => ['contact_yes' => 82, 'contact_no' => 18],
    ];
    $pesosEpocas = ['season_spring' => 40, 'season_summer' => 18, 'season_harvest' => 52, 'season_winter' => 10, 'season_flexible' => 20];
    $pesosPrioridades = ['priority_private_access' => 44, 'priority_winemakers' => 40, 'priority_dining' => 36, 'priority_hotels' => 30, 'priority_culture' => 18, 'priority_wellness' => 12, 'priority_transfers' => 14];
    $amostra = function (array $p, int $n) use ($escolher) {
        $r = [];
        while (count($r) < $n && $p) {
            $k = $escolher($p);
            $r[] = $k;
            unset($p[$k]);
        }
        return $r;
    };
    $gerarRespostas = function () use ($escolher, $amostra, $pesosRegioes, $pesos, $pesosEpocas, $pesosPrioridades) {
        $v = ['regions' => $amostra($pesosRegioes, mt_rand(1, 10) > 2 ? 3 : mt_rand(1, 2)), 'regions_other' => '', 'comment' => ''];
        foreach ($pesos as $campo => $p) {
            $v[$campo] = $escolher($p);
        }
        $v['seasons'] = $amostra($pesosEpocas, mt_rand(1, 2));
        $v['priorities'] = $amostra($pesosPrioridades, mt_rand(2, 3));
        return $v;
    };

    $pdo->beginTransaction();
    $inserir = $pdo->prepare('INSERT INTO clientes (token, nome, email, idioma, tratamento, pais, referencia, demo, criado) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)');
    $criados = 0;
    for ($i = 1; $i <= 60; $i++) {
        $idioma = $escolher($idiomas);
        $email = sprintf('cliente.demo%02d@example.com', $i);
        $existe = $pdo->prepare('SELECT 1 FROM clientes WHERE email = ?');
        $existe->execute([$email]);
        if ($existe->fetchColumn()) {
            continue;
        }
        $inserir->execute([ltp_novo_token(), sprintf('Cliente demo %02d', $i), $email, $idioma ?: null, '', '', 'DEMO-' . $i, date('Y-m-d H:i:s')]);
        $criados++;
        $id = (int) $pdo->lastInsertId();
        if (mt_rand(1, 100) <= 62) {
            $quando = date('Y-m-d H:i:s', time() - mt_rand(1, 12 * 86400));
            ltp_guardar_resposta($pdo, $gerarRespostas(), $idioma ?: 'en', $id, null, true, $quando);
        }
    }
    // Algumas respostas pela ligação geral, sem convite, com identificação fictícia
    $paises = ['GB' => 4, 'US' => 3, 'AE' => 2, 'DE' => 2, 'FR' => 1, 'BR' => 1, 'CH' => 1];
    for ($i = 1; $i <= 8; $i++) {
        $quando = date('Y-m-d H:i:s', time() - mt_rand(1, 12 * 86400));
        $identidade = [
            'nome' => sprintf('Visitante demo %02d', $i), 'email' => sprintf('visitante.demo%02d@example.com', $i),
            'telemovel' => sprintf('+44 7700 900%03d', $i), 'pais' => $escolher($paises),
        ];
        ltp_guardar_resposta($pdo, $gerarRespostas(), $escolher(['en' => 5, 'de' => 1, 'fr' => 1, 'ar' => 1]), null, sprintf('demo-anonimo-%04d', $i), true, $quando, $identidade);
    }
    $pdo->commit();
    return $criados;
}

function ltp_apagar_demo(PDO $pdo): void
{
    $pdo->exec('DELETE FROM respostas WHERE demo = 1');
    $pdo->exec('DELETE FROM clientes WHERE demo = 1');
}
