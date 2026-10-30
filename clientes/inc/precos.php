<?php
// Tabela de preços (só administração): preços por linha (cursos) e por modalidade (colunas), textos do documento
// e exportação em PDF. Os valores guardam-se em cêntimos, sem IVA; sem valor, aparece "Sob consulta".

declare(strict_types=1);

// Ponto de partida (tabela de preços do percurso IA Generativa no Trabalho, de 23 de outubro de 2026)
const MODALIDADES_INICIAIS = [
    ['etiqueta' => 'Empresas', 'titulo' => 'Turma fechada', 'descricao' => 'Turma exclusiva da empresa, até 10 pessoas, em modelo 100% remoto. Preço por turma e por curso.', 'coluna' => 'Empresas', 'detalhe' => 'turma até 10 pessoas', 'valor' => 125000],
    ['etiqueta' => 'Particulares', 'titulo' => 'Turma aberta', 'descricao' => 'Inscrição individual numa turma de 10 pessoas. A turma arranca quando reunir 10 inscritos. Preço por pessoa e por curso.', 'coluna' => 'Particulares', 'detalhe' => 'por pessoa', 'valor' => 14634],
    ['etiqueta' => 'Individual', 'titulo' => 'One-to-one', 'descricao' => 'Formação personalizada, só com o formador e um formando, com o programa do curso adaptado à sua realidade. Preço por curso.', 'coluna' => 'One-to-one', 'detalhe' => 'por formando', 'valor' => 55900],
];
const NIVEIS_INICIAIS = ['Iniciação', 'Intermédio', 'Avançado', 'Especialização'];
const TEXTOS_PRECOS = [
    'sobretitulo' => 'IA Generativa no Trabalho: do primeiro uso à liderança da implementação',
    'titulo' => 'Preços por curso',
    'subtitulo' => 'Percurso de 4 cursos · 6 sessões síncronas de 60 minutos por curso',
    'factos' => [['Cursos', '4 níveis sequenciais'], ['Duração', '6 horas por curso, 24 horas no total'], ['Modalidades', 'Empresas, particulares e one-to-one'], ['Formato', 'Sessões síncronas, com casos práticos']],
    'titulo_modalidades' => 'Modalidades',
    'titulo_tabela' => 'Preços por curso',
    'coluna_linhas' => 'Curso',
    'nota_tabela' => 'Preços por curso de 6 horas. Todos os valores são apresentados sem IVA incluído; acresce IVA à taxa legal em vigor.',
    'titulo_condicoes' => 'Condições',
    'condicoes' => "Os preços são por curso. Cada curso pode ser adquirido isoladamente, respeitando os pré-requisitos: Curso 1 sem pré-requisitos; Curso 2 exige o Curso 1 ou utilização regular demonstrada; Cursos 3 e 4 exigem o curso anterior.\n"
        . "Turma aberta para particulares: só arranca com 10 inscritos. Até lá, a inscrição fica registada e o formando é informado da data de início.\n"
        . "Turma fechada para empresas: até 10 participantes, 100% remota, em sessões síncronas de 60 minutos com datas acordadas com a empresa.\n"
        . "Cada formando precisa de computador com navegador atualizado e conta própria no Claude. Os Cursos 3 e 4 exigem plano pago do Claude (Pro, Max, Team ou Enterprise), cujo custo não está incluído nos preços.\n"
        . 'O preço inclui as 6 sessões, os materiais de apoio, os casos práticos e a avaliação.',
    'texto_programa' => 'Programa detalhado de cada curso nas respetivas fichas de curso e em:',
    'nota_documento' => 'Documento informativo de {data}. Os preços podem ser revistos; prevalece a versão publicada no site à data da inscrição.',
    'data_documento' => '',
];
const MAX_MODALIDADES_PDF = 4; // colunas de preços que cabem na tabela A4

function textos_precos(): array
{
    $guardados = json_decode((string) definicao(bd(), 'precos_textos'), true);
    return array_merge(TEXTOS_PRECOS, is_array($guardados) ? $guardados : []);
}

// Na primeira abertura desta versão: a tabela da versão anterior (uma lista simples) fica só para consulta
// e a tabela nova começa com as modalidades, os cursos ativos e os preços da tabela de referência
function semear_precos(): void
{
    if (definicao(bd(), 'precos_v2') !== null) {
        return;
    }
    $pdo = bd();
    $pdo->beginTransaction();
    definir($pdo, 'precos_v2', agora());
    executar('UPDATE precos SET legado = 1');
    $colunas = [];
    if (!linha_bd('SELECT id FROM precos_colunas LIMIT 1')) {
        foreach (MODALIDADES_INICIAIS as $n => $m) {
            $colunas[] = [executar('INSERT INTO precos_colunas (etiqueta, titulo, descricao, coluna, detalhe, ordem, ativo, atualizado) VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
                [$m['etiqueta'], $m['titulo'], $m['descricao'], $m['coluna'], $m['detalhe'], ($n + 1) * 10, agora()]), $m['valor']];
        }
    }
    foreach (consulta('SELECT nome FROM cursos WHERE ativo = 1 ORDER BY id') as $n => $c) {
        $id = executar("INSERT INTO precos (categoria, designacao, detalhe, iva, ativo, ordem, atualizado, legado) VALUES ('', ?, ?, '', 1, ?, ?, 0)",
            [$c['nome'], trim((NIVEIS_INICIAIS[$n] ?? '') . ' · 6 horas', ' ·'), ($n + 1) * 10, agora()]);
        foreach ($colunas as [$coluna, $valor]) {
            executar('INSERT INTO precos_valores (preco_id, coluna_id, valor) VALUES (?, ?, ?)', [$id, $coluna, $valor]);
        }
    }
    $pdo->commit();
}

// "1.250,50", "1250,5", "1250.50" ou "1 250 €" em cêntimos; vazio é null (sob consulta); false se não for um valor válido
function centimos(string $texto)
{
    $t = str_replace(['€', ' ', "\u{00A0}", "\u{202F}"], '', trim($texto));
    if ($t === '') {
        return null;
    }
    if (strpos($t, ',') !== false) {
        $t = str_replace(',', '.', str_replace('.', '', $t));
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $t)) {
        $t = str_replace('.', '', $t);
    }
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $t)) {
        return false;
    }
    return (int) round((float) $t * 100);
}

// Cêntimos em euros: "1.250,00 €" (com $simbolo = false, "1250,00", para os campos do formulário)
function euros(int $centimos, bool $simbolo = true): string
{
    return $simbolo ? number_format($centimos / 100, 2, ',', '.') . ' €' : number_format($centimos / 100, 2, ',', '');
}

function lista_precos(bool $so_ativos = false): array
{
    return consulta('SELECT * FROM precos WHERE legado = 0' . ($so_ativos ? ' AND ativo = 1' : '') . ' ORDER BY ordem, id');
}

function modalidades_precos(bool $so_ativas = false): array
{
    return consulta('SELECT * FROM precos_colunas' . ($so_ativas ? ' WHERE ativo = 1' : '') . ' ORDER BY ordem, id');
}

// Valores por linha e por modalidade: [preco_id][coluna_id] => cêntimos ou null
function valores_precos(): array
{
    $r = [];
    foreach (consulta('SELECT * FROM precos_valores') as $v) {
        $r[(int) $v['preco_id']][(int) $v['coluna_id']] = $v['valor'] === null ? null : (int) $v['valor'];
    }
    return $r;
}

function limpar_texto_preco($v, int $max, bool $linhas = false): string
{
    if (!is_string($v) || !preg_match('//u', $v)) {
        return '';
    }
    $v = $linhas ? preg_replace('/[^\P{C}\n]+/u', '', str_replace("\r", '', $v)) : preg_replace('/[^\P{C}]+/u', '', preg_replace('/\s+/u', ' ', $v));
    return trim((string) preg_replace('/^(.{0,' . $max . '}).*$/us', '$1', (string) $v));
}

// Linhas enviadas num formulário de tabela: as existentes (pelo id) e as novas ("nova", "novajs1", ...), sem as novas em branco
function linhas_enviadas(string $campo, array $existentes): array
{
    $vazio = function ($l) {
        $textos = array_merge(array_diff_key($l, ['ordem' => 1, 'ativo' => 1, 'valores' => 1]), (array) ($l['valores'] ?? []));
        return implode('', array_map(function ($x) { return is_string($x) ? trim($x) : ''; }, $textos)) === '';
    };
    $r = [];
    foreach ((array) ($_POST[$campo] ?? []) as $id => $l) {
        $id = (string) $id;
        if (!is_array($l) || (preg_match('/^nova\w*$/', $id) ? $vazio($l) : !isset($existentes[(int) $id]))) {
            continue;
        }
        $r[$id] = $l;
    }
    return $r;
}

function admin_precos(): void
{
    semear_precos();
    if (!empty($_GET['pdf'])) {
        precos_pdf();
    }
    $erros = [];
    $env_linhas = null;
    $env_modalidades = null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $acao = (string) ($_POST['acao'] ?? '');
        // "Acrescentar linha" sem JavaScript: grava e volta com uma linha em branco nessa tabela
        $volta = empty($_POST['acrescentar']) ? [] : ['nova' => $acao];
        if ($acao === 'precos') {
            // Valida tudo antes de gravar: com um erro, nada é alterado e o formulário mostra o que foi escrito
            $existentes = array_column(lista_precos(), null, 'id');
            $colunas = array_column(modalidades_precos(), null, 'id');
            $env_linhas = [];
            $n = 0;
            foreach (linhas_enviadas('precos', $existentes) as $id => $l) {
                $id = (string) $id; // as chaves numéricas chegam como inteiros
                $n++;
                $d = ['designacao' => limpar_texto_preco($l['designacao'] ?? '', 160), 'detalhe' => limpar_texto_preco($l['detalhe'] ?? '', 120),
                      'ordem' => max(0, min(9999, (int) ($l['ordem'] ?? 0))), 'ativo' => empty($l['ativo']) ? 0 : 1, 'apagar' => !empty($l['apagar']), 'valores' => [], 'textos' => []];
                $onde = preg_match('/^nova/', $id) ? 'Linha nova' : 'Linha ' . $n;
                foreach ($colunas as $cid => $c) {
                    $t = limpar_texto_preco(((array) ($l['valores'] ?? []))[$cid] ?? '', 20);
                    $d['textos'][$cid] = $t;
                    $d['valores'][$cid] = centimos($t);
                    if (!$d['apagar'] && $d['valores'][$cid] === false) {
                        $erros[] = $onde . ', ' . $c['coluna'] . ': o preço "' . $t . '" não é válido (por exemplo, 1250 ou 146,34).';
                    }
                }
                if (!$d['apagar'] && $d['designacao'] === '') {
                    $erros[] = $onde . ': indique a designação.';
                }
                $env_linhas[$id] = $d;
            }
            if (!$erros) {
                $pdo = bd();
                $pdo->beginTransaction();
                $apagadas = 0;
                foreach ($env_linhas as $id => $d) {
                    $nova = (bool) preg_match('/^nova/', (string) $id);
                    if ($d['apagar']) {
                        if (!$nova) {
                            executar('DELETE FROM precos_valores WHERE preco_id = ?', [(int) $id]);
                            executar('DELETE FROM precos WHERE id = ?', [(int) $id]);
                            $apagadas++;
                        }
                        continue;
                    }
                    if ($nova) {
                        $pid = executar("INSERT INTO precos (categoria, designacao, detalhe, iva, ativo, ordem, atualizado, legado) VALUES ('', ?, ?, '', ?, ?, ?, 0)",
                            [$d['designacao'], $d['detalhe'], $d['ativo'], $d['ordem'], agora()]);
                    } else {
                        $pid = (int) $id;
                        executar('UPDATE precos SET designacao = ?, detalhe = ?, ativo = ?, ordem = ?, atualizado = ? WHERE id = ?', [$d['designacao'], $d['detalhe'], $d['ativo'], $d['ordem'], agora(), $pid]);
                    }
                    foreach ($d['valores'] as $cid => $v) {
                        executar('INSERT OR REPLACE INTO precos_valores (preco_id, coluna_id, valor) VALUES (?, ?, ?)', [$pid, $cid, $v]);
                    }
                }
                $pdo->commit();
                aviso('Preços guardados' . ($apagadas ? ' (' . $apagadas . ' linha(s) apagada(s))' : '') . '.');
                redirecionar('precos', $volta);
            }
        } elseif ($acao === 'modalidades') {
            $existentes = array_column(modalidades_precos(), null, 'id');
            $env_modalidades = [];
            $n = 0;
            $ativas = 0;
            foreach (linhas_enviadas('modalidades', $existentes) as $id => $l) {
                $id = (string) $id;
                $n++;
                $d = ['etiqueta' => limpar_texto_preco($l['etiqueta'] ?? '', 40), 'titulo' => limpar_texto_preco($l['titulo'] ?? '', 60),
                      'descricao' => limpar_texto_preco($l['descricao'] ?? '', 400), 'coluna' => limpar_texto_preco($l['coluna'] ?? '', 30),
                      'detalhe' => limpar_texto_preco($l['detalhe'] ?? '', 40), 'ordem' => max(0, min(9999, (int) ($l['ordem'] ?? 0))),
                      'ativo' => empty($l['ativo']) ? 0 : 1, 'apagar' => !empty($l['apagar'])];
                $d['coluna'] = $d['coluna'] ?: $d['etiqueta'];
                if (!$d['apagar']) {
                    if ($d['etiqueta'] === '') {
                        $erros[] = (preg_match('/^nova/', $id) ? 'Modalidade nova' : 'Modalidade ' . $n) . ': indique o nome.';
                    }
                    $ativas += $d['ativo'];
                }
                $env_modalidades[$id] = $d;
            }
            if ($ativas > MAX_MODALIDADES_PDF) {
                $erros[] = 'No máximo ' . MAX_MODALIDADES_PDF . ' modalidades com "No PDF" marcado: com mais, a tabela não cabe na página.';
            }
            if (!$erros) {
                $pdo = bd();
                $pdo->beginTransaction();
                foreach ($env_modalidades as $id => $d) {
                    $nova = (bool) preg_match('/^nova/', (string) $id);
                    if ($d['apagar']) {
                        if (!$nova) {
                            executar('DELETE FROM precos_valores WHERE coluna_id = ?', [(int) $id]);
                            executar('DELETE FROM precos_colunas WHERE id = ?', [(int) $id]);
                        }
                        continue;
                    }
                    $valores = [$d['etiqueta'], $d['titulo'], $d['descricao'], $d['coluna'], $d['detalhe'], $d['ordem'], $d['ativo'], agora()];
                    if ($nova) {
                        executar('INSERT INTO precos_colunas (etiqueta, titulo, descricao, coluna, detalhe, ordem, ativo, atualizado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $valores);
                    } else {
                        executar('UPDATE precos_colunas SET etiqueta = ?, titulo = ?, descricao = ?, coluna = ?, detalhe = ?, ordem = ?, ativo = ?, atualizado = ? WHERE id = ?', array_merge($valores, [(int) $id]));
                    }
                }
                $pdo->commit();
                aviso('Modalidades guardadas.');
                redirecionar('precos', $volta);
            }
        } elseif ($acao === 'textos') {
            $t = ['factos' => []];
            foreach (TEXTOS_PRECOS as $k => $padrao) {
                if ($k === 'factos') {
                    foreach (range(0, 3) as $i) {
                        $t['factos'][] = [limpar_texto_preco($_POST['facto_rotulo'][$i] ?? '', 30), limpar_texto_preco($_POST['facto_texto'][$i] ?? '', 80)];
                    }
                } elseif ($k === 'data_documento') {
                    $data = limpar_texto_preco($_POST[$k] ?? '', 10);
                    $t[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && strtotime($data) ? $data : '';
                } else {
                    $t[$k] = limpar_texto_preco($_POST[$k] ?? '', $k === 'condicoes' ? 3000 : 400, $k === 'condicoes');
                }
            }
            definir(bd(), 'precos_textos', (string) json_encode($t, JSON_UNESCAPED_UNICODE));
            aviso('Textos do documento guardados.');
            redirecionar('precos');
        } elseif ($acao === 'apagar_legado') {
            executar('DELETE FROM precos WHERE legado = 1');
            aviso('Tabela anterior apagada.');
            redirecionar('precos');
        }
    }

    $linhas = lista_precos();
    $modalidades = modalidades_precos();
    $valores = valores_precos();
    $textos = textos_precos();

    // 1. Preços: uma linha por curso, um preço por modalidade
    $linha_preco = function (string $id, array $p, int $n) use ($modalidades, $valores, $env_linhas) {
        $e = $env_linhas[$id] ?? null;
        $nova = (bool) preg_match('/^nova/', $id);
        $nome = 'precos[' . $id . ']';
        $rotulo = $nova ? 'linha nova' : 'linha ' . $n;
        $campo = function (string $c) use ($e, $p) { return $e !== null ? (string) $e[$c] : (string) ($p[$c] ?? ''); };
        $celulas = '';
        foreach ($modalidades as $m) {
            $cid = (int) $m['id'];
            $guardado = $valores[(int) ($p['id'] ?? 0)][$cid] ?? null;
            $v = $e !== null ? ($e['textos'][$cid] ?? '') : ($guardado === null ? '' : euros($guardado, false));
            $celulas .= '<td><input type="text" name="' . $nome . '[valores][' . $cid . ']" value="' . h($v) . '" inputmode="decimal" maxlength="20" placeholder="Sob consulta" class="valor" aria-label="' . h($m['coluna']) . ', ' . $rotulo . '"></td>';
        }
        $ativo = $e !== null ? $e['ativo'] : (int) ($p['ativo'] ?? 1);
        return '<tr' . ($nova ? ' class="linha-nova"' : '') . '>'
            . '<td class="n">' . ($nova ? '+' : $n) . '</td>'
            . '<td><input type="text" name="' . $nome . '[designacao]" value="' . h($campo('designacao')) . '" maxlength="160" aria-label="Designação, ' . $rotulo . '"' . ($nova ? ' placeholder="Designação da nova linha"' : ' required') . '>'
            . '<input type="text" name="' . $nome . '[detalhe]" value="' . h($campo('detalhe')) . '" maxlength="120" class="descricao" placeholder="Detalhe (por exemplo, Iniciação · 6 horas)" aria-label="Detalhe, ' . $rotulo . '"></td>'
            . $celulas
            . '<td class="centro"><input type="checkbox" name="' . $nome . '[ativo]" value="1"' . ($ativo ? ' checked' : '') . ' aria-label="Incluir no PDF, ' . $rotulo . '"></td>'
            . '<td><input type="number" name="' . $nome . '[ordem]" value="' . h($nova && $e === null ? (string) ($n * 10) : $campo('ordem')) . '" min="0" max="9999" class="ordem" aria-label="Ordem, ' . $rotulo . '"></td>'
            . '<td class="centro">' . ($nova ? '' : '<input type="checkbox" name="' . $nome . '[apagar]" value="1" aria-label="Apagar, ' . $rotulo . '">') . '</td>'
            . '</tr>';
    };
    $corpo = '';
    foreach ($linhas as $n => $p) {
        $corpo .= $linha_preco((string) $p['id'], $p, $n + 1);
    }
    // Linhas novas: as enviadas (depois de um erro) ou uma em branco com a tabela vazia ou depois de "Acrescentar linha" sem JavaScript
    $proxima = ($linhas ? max(array_map('intval', array_column($linhas, 'ordem'))) : 0) + 10;
    $novas = array_values(array_filter(array_keys((array) $env_linhas), function ($k) { return (bool) preg_match('/^nova/', (string) $k); }));
    if (!$novas && (!$linhas || ($_GET['nova'] ?? '') === 'precos')) {
        $novas = ['nova'];
    }
    foreach ($novas as $k => $id) {
        $corpo .= $linha_preco((string) $id, [], (int) ($proxima / 10) + $k);
    }
    $cab_modalidades = implode('', array_map(function ($m) {
        return '<th scope="col" class="c-preco">' . h($m['coluna']) . ($m['detalhe'] ? '<small>' . h($m['detalhe']) . '</small>' : '') . ($m['ativo'] ? '' : '<small>(fora do PDF)</small>') . '</th>';
    }, $modalidades));

    // 2. Modalidades: colunas de preços e cartões do PDF
    $linha_modalidade = function (string $id, array $m, int $n) use ($env_modalidades) {
        $e = $env_modalidades[$id] ?? null;
        $nova = (bool) preg_match('/^nova/', $id);
        $nome = 'modalidades[' . $id . ']';
        $rotulo = $nova ? 'modalidade nova' : 'modalidade ' . $n;
        $campo = function (string $c) use ($e, $m) { return $e !== null ? (string) $e[$c] : (string) ($m[$c] ?? ''); };
        $ativo = $e !== null ? $e['ativo'] : (int) ($m['ativo'] ?? 1);
        return '<tr' . ($nova ? ' class="linha-nova"' : '') . '>'
            . '<td><input type="text" name="' . $nome . '[etiqueta]" value="' . h($campo('etiqueta')) . '" maxlength="40" aria-label="Nome, ' . $rotulo . '"' . ($nova ? ' placeholder="Nome (por exemplo, Empresas)"' : ' required') . '>'
            . '<input type="text" name="' . $nome . '[titulo]" value="' . h($campo('titulo')) . '" maxlength="60" class="descricao" placeholder="Título do cartão" aria-label="Título do cartão, ' . $rotulo . '"></td>'
            . '<td><textarea name="' . $nome . '[descricao]" maxlength="400" rows="3" aria-label="Texto do cartão, ' . $rotulo . '">' . h($campo('descricao')) . '</textarea></td>'
            . '<td><input type="text" name="' . $nome . '[coluna]" value="' . h($campo('coluna')) . '" maxlength="30" placeholder="Igual ao nome" aria-label="Coluna da tabela, ' . $rotulo . '">'
            . '<input type="text" name="' . $nome . '[detalhe]" value="' . h($campo('detalhe')) . '" maxlength="40" class="descricao" placeholder="Por exemplo, por pessoa" aria-label="Detalhe da coluna, ' . $rotulo . '"></td>'
            . '<td class="centro"><input type="checkbox" name="' . $nome . '[ativo]" value="1"' . ($ativo ? ' checked' : '') . ' aria-label="Incluir no PDF, ' . $rotulo . '"></td>'
            . '<td><input type="number" name="' . $nome . '[ordem]" value="' . h($nova && $e === null ? (string) ($n * 10) : $campo('ordem')) . '" min="0" max="9999" class="ordem" aria-label="Ordem, ' . $rotulo . '"></td>'
            . '<td class="centro">' . ($nova ? '' : '<input type="checkbox" name="' . $nome . '[apagar]" value="1" aria-label="Apagar, ' . $rotulo . '">') . '</td>'
            . '</tr>';
    };
    $corpo_m = '';
    foreach ($modalidades as $n => $m) {
        $corpo_m .= $linha_modalidade((string) $m['id'], $m, $n + 1);
    }
    $proxima_m = ($modalidades ? max(array_map('intval', array_column($modalidades, 'ordem'))) : 0) + 10;
    $novas_m = array_values(array_filter(array_keys((array) $env_modalidades), function ($k) { return (bool) preg_match('/^nova/', (string) $k); }));
    if (!$novas_m && (!$modalidades || ($_GET['nova'] ?? '') === 'modalidades')) {
        $novas_m = ['nova'];
    }
    foreach ($novas_m as $k => $id) {
        $corpo_m .= $linha_modalidade((string) $id, [], (int) ($proxima_m / 10) + $k);
    }

    // Tabela da versão anterior (lista simples), guardada só para consulta
    $legado = consulta('SELECT * FROM precos WHERE legado = 1 ORDER BY ordem, id');
    $html_legado = $legado ? '<details class="programa espaco-topo"><summary>Tabela anterior (' . count($legado) . ' linhas, só para consulta)</summary><div class="programa-corpo">
        <p class="texto-suave">Linhas da primeira versão da tabela de preços, antes da tabela por modalidades. Não entram no PDF.</p>
        <div class="tabela-rolante"><table><thead><tr><th scope="col">Categoria</th><th scope="col">Designação</th><th scope="col">Unidade</th><th scope="col">Preço sem IVA</th></tr></thead><tbody>'
        . implode('', array_map(function ($p) {
            return '<tr><td>' . h((string) $p['categoria']) . '</td><td>' . h((string) $p['designacao']) . ($p['descricao'] ? '<br><span class="texto-suave">' . h((string) $p['descricao']) . '</span>' : '') . '</td><td>' . h((string) $p['unidade']) . '</td><td>' . ($p['valor'] === null ? 'Sob consulta' : h(euros((int) $p['valor']))) . '</td></tr>';
        }, $legado)) . '</tbody></table></div>
        <form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="apagar_legado"><button class="botao botao-secundario botao-pequeno" type="submit">Apagar a tabela anterior</button></form></div></details>' : '';

    $facto = function (int $i) use ($textos) {
        $f = $textos['factos'][$i] ?? ['', ''];
        return '<div class="campos-2">' . campo('facto_rotulo[' . $i . ']', 'Facto ' . ($i + 1) . ': rótulo', (string) ($f[0] ?? ''), 'text', 'maxlength="30"', 'c-facto-r' . $i)
            . campo('facto_texto[' . $i . ']', 'Facto ' . ($i + 1) . ': texto', (string) ($f[1] ?? ''), 'text', 'maxlength="80"', 'c-facto-t' . $i) . '</div>';
    };
    $ultima = max(array_merge([''], array_column($linhas, 'atualizado'), array_column($modalidades, 'atualizado')));

    pagina_area('precos', 'Tabela de preços', '
      ' . ($erros ? '<div class="aviso aviso-erro" role="alert">' . h(implode(' ', $erros)) . '</div>' : '') . '
      <p class="texto-suave">Uso interno: só o perfil de administração vê esta página. Preços sem IVA (por exemplo, 1250 ou 146,34); um preço em branco aparece como "Sob consulta". O PDF tem o título com os factos principais, as modalidades, os preços e as condições.' . ($ultima ? ' Última alteração: ' . h(data_hora($ultima)) . '.' : '') . '</p>
      <p><a class="botao" href="' . h(url('precos', ['pdf' => 1])) . '" target="_blank" rel="noopener">Exportar PDF</a></p>

      <h2 class="espaco-topo" id="tabela">1. ' . h($textos['titulo_tabela'] ?: 'Preços') . '</h2>
      <form method="post" class="formulario" action="' . h(url('precos')) . '">' . campo_csrf() . '<input type="hidden" name="acao" value="precos">
        <div class="tabela-rolante"><table class="tabela-precos tabela-editavel">
          <thead><tr><th scope="col" class="c-n">#</th><th scope="col">' . h($textos['coluna_linhas'] ?: 'Designação') . ' e detalhe</th>' . $cab_modalidades . '<th scope="col" class="c-pdf">No PDF</th><th scope="col" class="c-ordem">Ordem</th><th scope="col" class="c-apagar">Apagar</th></tr></thead>
          <tbody>' . $corpo . '</tbody>
        </table></div>
        <template data-modelo>' . $linha_preco('nova__N__', [], (int) ($proxima / 10)) . '</template>
        <p class="ajuda">Cada coluna de preços é uma modalidade (secção 2). As linhas aparecem no PDF numeradas, pela ordem indicada; as linhas novas deixadas em branco são ignoradas.</p>
        <div class="acoes-formulario"><button class="botao" type="submit">Guardar alterações</button> <button class="botao botao-secundario" type="submit" name="acrescentar" value="1" data-acrescentar>Acrescentar linha</button></div>
      </form>

      <h2 class="espaco-topo" id="modalidades">2. Modalidades</h2>
      <p class="texto-suave">Cada modalidade é uma coluna de preços e um cartão na secção "' . h($textos['titulo_modalidades']) . '" do PDF (no máximo ' . MAX_MODALIDADES_PDF . ' no PDF).</p>
      <form method="post" class="formulario" action="' . h(url('precos')) . '">' . campo_csrf() . '<input type="hidden" name="acao" value="modalidades">
        <div class="tabela-rolante"><table class="tabela-precos tabela-modalidades tabela-editavel">
          <thead><tr><th scope="col">Nome e título do cartão</th><th scope="col">Texto do cartão</th><th scope="col">Coluna da tabela e detalhe</th><th scope="col" class="c-pdf">No PDF</th><th scope="col" class="c-ordem">Ordem</th><th scope="col" class="c-apagar">Apagar</th></tr></thead>
          <tbody>' . $corpo_m . '</tbody>
        </table></div>
        <template data-modelo>' . $linha_modalidade('nova__N__', [], (int) ($proxima_m / 10)) . '</template>
        <div class="acoes-formulario"><button class="botao" type="submit">Guardar modalidades</button> <button class="botao botao-secundario" type="submit" name="acrescentar" value="1" data-acrescentar>Acrescentar modalidade</button></div>
      </form>

      <h2 class="espaco-topo" id="textos">3. Textos do documento</h2>
      <form method="post" class="formulario" action="' . h(url('precos')) . '">' . campo_csrf() . '<input type="hidden" name="acao" value="textos">
        ' . campo('sobretitulo', 'Sobretítulo (faixa do título)', (string) $textos['sobretitulo'], 'text', 'maxlength="400"') . '
        <div class="campos-2">' . campo('titulo', 'Título', (string) $textos['titulo'], 'text', 'maxlength="400"') . campo('data_documento', 'Data do documento (em branco: data da exportação)', (string) $textos['data_documento'], 'date') . '</div>
        ' . campo('subtitulo', 'Subtítulo', (string) $textos['subtitulo'], 'text', 'maxlength="400"') . '
        ' . $facto(0) . $facto(1) . $facto(2) . $facto(3) . '
        <div class="campos-2">' . campo('titulo_modalidades', 'Título da secção das modalidades', (string) $textos['titulo_modalidades'], 'text', 'maxlength="400"')
          . campo('titulo_tabela', 'Título da secção dos preços', (string) $textos['titulo_tabela'], 'text', 'maxlength="400"') . '</div>
        <div class="campos-2">' . campo('coluna_linhas', 'Cabeçalho da 1.ª coluna da tabela', (string) $textos['coluna_linhas'], 'text', 'maxlength="400"')
          . campo('titulo_condicoes', 'Título da secção das condições', (string) $textos['titulo_condicoes'], 'text', 'maxlength="400"') . '</div>
        ' . campo('nota_tabela', 'Nota por baixo da tabela', (string) $textos['nota_tabela'], 'textarea', 'maxlength="400" rows="2"') . '
        ' . campo('condicoes', 'Condições (uma por linha)', (string) $textos['condicoes'], 'textarea', 'maxlength="3000" rows="7"') . '
        <div class="campos-2">' . campo('texto_programa', 'Texto antes do endereço do site', (string) $textos['texto_programa'], 'textarea', 'maxlength="400" rows="2"')
          . campo('nota_documento', 'Nota final ({data} é substituído pela data do documento)', (string) $textos['nota_documento'], 'textarea', 'maxlength="400" rows="2"') . '</div>
        <p class="ajuda">A caixa "Informações e inscrições" do PDF usa os contactos da empresa e um código QR para a página de formação do site.</p>
        <div><button class="botao" type="submit">Guardar textos</button></div>
      </form>
      ' . $html_legado, 'Configuração');
}

/* PDF */

// Mesmo grafismo da ficha de curso, com o cabeçalho (logótipo, "Tabela de preços" e data) em todas as páginas
function precos_pdf(): void
{
    require_once __DIR__ . '/ficha-pdf.php';
    $t = textos_precos();
    $data = data_extensa($t['data_documento'] ?: date('Y-m-d'));
    $modalidades = modalidades_precos(true);
    $linhas = lista_precos(true);
    $valores = valores_precos();

    $pdf = new class extends FichaPdf {
        public $data_documento = '';
        public function Header(): void
        {
            $L = $this->lMargin;
            $W = FichaPdf::LARGURA;
            $this->Image($this->logotipo, $L, 14.2, 47);
            $this->fonte('montserrat', 'B', 7.5, 'petroleo');
            $this->rotulo($L + $W, 18.6, 'Tabela de preços', 0.6, 'R');
            $this->fonte('inter', '', 8, 'suave');
            $d = pdf_txt($this->data_documento);
            $this->Text($L + $W - $this->GetStringWidth($d), 22.4, $d);
            $this->cor('linha', 'D');
            $this->SetLineWidth(0.2);
            $this->Line($L, 26, $L + $W, 26);
            $this->SetY(33);
        }
    };
    $pdf->SetTopMargin(33);
    $pdf->data_documento = $data;
    $pdf->logotipo = __DIR__ . '/../../assets/img/gadgetmetodo-logotipo-pdf.png';
    $pdf->SetTitle('Tabela de preços: ' . $t['titulo'], true);
    $pdf->SetAuthor('Gadgetmetodo, Lda.', true);
    $pdf->SetCreator('www.gadgetmetodo.pt', true);
    $pdf->AddPage();
    $L = 20.0;
    $W = FichaPdf::LARGURA;

    // Faixa de título: sobretítulo, título e subtítulo; por baixo, os factos principais
    $y = 32.0;
    $wt = $W - 18;
    $pdf->SetFont('montserrat', 'B', 19);
    $h_titulo = $pdf->linhas($wt, pdf_txt((string) $t['titulo'])) * 8;
    $h = 8 + ($t['sobretitulo'] ? 6 : 0) + $h_titulo + ($t['subtitulo'] ? 6.5 : 0) + 5;
    $pdf->cor('marinho', 'F');
    $pdf->Rect($L, $y, $W, $h, 'F');
    $pdf->cor('petroleo', 'F');
    $pdf->Rect($L, $y, 3, $h, 'F');
    $yy = $y + 8;
    if ($t['sobretitulo']) {
        $s = (string) $t['sobretitulo'];
        $pdf->fonte('montserrat', 'B', 6.8, 'claro');
        while ($pdf->GetStringWidth(pdf_txt(pdf_maiusculas($s))) + 0.35 * strlen($s) > $wt && strlen($s) > 10) {
            $s = rtrim(substr($s, 0, -2));
        }
        $pdf->rotulo($L + 12, $yy + 1.5, $s, 0.35);
        $yy += 6;
    }
    $pdf->fonte('montserrat', 'B', 19, 'branco');
    $pdf->SetXY($L + 12, $yy - 1);
    $pdf->MultiCell($wt, 8, pdf_txt((string) $t['titulo']), 0, 'L');
    $yy += $h_titulo;
    if ($t['subtitulo']) {
        $pdf->fonte('inter', '', 9.4, 'cinza');
        $pdf->Text($L + 12, $yy + 2.6, pdf_txt((string) $t['subtitulo']));
    }
    $y += $h;
    $factos = array_values(array_filter((array) $t['factos'], function ($f) { return is_array($f) && trim(($f[0] ?? '') . ($f[1] ?? '')) !== ''; }));
    if ($factos) {
        $wc = $W / count($factos);
        $pdf->SetFont('intersemi', '', 8.4);
        $hf = 0;
        foreach ($factos as $f) {
            $hf = max($hf, $pdf->linhas($wc - 8.6, pdf_txt((string) ($f[1] ?? ''))) * 4.2);
        }
        $hf += 12;
        $pdf->cor('linha', 'D');
        foreach ($factos as $i => $f) {
            $x = $L + $i * $wc;
            $pdf->Rect($x, $y, $wc, $hf, 'D');
            $pdf->fonte('montserrat', 'B', 6.3, 'petroleo');
            $pdf->rotulo($x + 4.3, $y + 5.6, (string) ($f[0] ?? ''), 0.4);
            $pdf->fonte('intersemi', '', 8.4, 'marinho');
            $pdf->SetXY($x + 4.3, $y + 7.4);
            $pdf->MultiCell($wc - 8.6, 4.2, pdf_txt((string) ($f[1] ?? '')), 0, 'L');
        }
        $y += $hf;
    }
    $pdf->SetY($y + 9);
    $n = 0;

    // Modalidades: um cartão por modalidade
    if ($modalidades) {
        $gap = 4.0;
        $wc = ($W - $gap * (count($modalidades) - 1)) / count($modalidades);
        $hc = 0;
        foreach ($modalidades as $m) {
            $pdf->SetFont('montserrat', 'B', 9.6);
            $ht = $m['titulo'] ? $pdf->linhas($wc - 8.6, pdf_txt((string) $m['titulo'])) * 4.8 + 1 : 0;
            $pdf->SetFont('inter', '', 8.4);
            $hc = max($hc, 9 + $ht + ($m['descricao'] ? $pdf->linhas($wc - 8.6, pdf_txt((string) $m['descricao'])) * 4.5 : 0) + 5);
        }
        $pdf->seccao(++$n, (string) $t['titulo_modalidades'], $hc + 14);
        $y = $pdf->GetY() + 1;
        foreach ($modalidades as $i => $m) {
            $x = $L + $i * ($wc + $gap);
            $pdf->cor('nevoa', 'F');
            $pdf->Rect($x, $y, $wc, $hc, 'F');
            $pdf->cor('petroleo', 'F');
            $pdf->Rect($x, $y, $wc, 0.8, 'F');
            $pdf->fonte('montserrat', 'B', 6.3, 'petroleo');
            $pdf->rotulo($x + 4.3, $y + 7, (string) $m['etiqueta'], 0.4);
            $yy = $y + 9;
            if ($m['titulo']) {
                $pdf->fonte('montserrat', 'B', 9.6, 'marinho');
                $pdf->SetXY($x + 4.3, $yy);
                $pdf->MultiCell($wc - 8.6, 4.8, pdf_txt((string) $m['titulo']), 0, 'L');
                $yy = $pdf->GetY() + 1;
            }
            if ($m['descricao']) {
                $pdf->fonte('inter', '', 8.4, 'texto');
                $pdf->SetXY($x + 4.3, $yy);
                $pdf->MultiCell($wc - 8.6, 4.5, pdf_txt((string) $m['descricao']), 0, 'L');
            }
        }
        $pdf->SetY($y + $hc + 10);
    }

    // Preços: uma linha numerada por curso, um preço por modalidade
    if ($linhas) {
        $pdf->seccao(++$n, (string) $t['titulo_tabela'], 50);
        // Cada coluna de preços com a largura do seu conteúdo (cabeçalho e preços); a 1.ª coluna fica com o resto
        $wps = [];
        foreach ($modalidades as $i => $m) {
            $pdf->SetFont('intersemi', '', 7.6);
            $w = max($pdf->GetStringWidth(pdf_txt((string) $m['coluna'])), $pdf->GetStringWidth(pdf_txt((string) $m['detalhe'])));
            $pdf->SetFont('inter', 'B', 10.4);
            foreach ($linhas as $p) {
                $v = $valores[(int) $p['id']][(int) $m['id']] ?? null;
                $w = max($w, $v === null ? 18.0 : $pdf->GetStringWidth(pdf_txt(euros($v))));
            }
            $wps[$i] = max(22.0, $w + 7);
        }
        $w1 = $W - array_sum($wps);
        $direita = function (int $i) use ($L, $w1, $wps) { return $L + $w1 + array_sum(array_slice($wps, 0, $i + 1)) - 3; };
        $cabecalho = function () use ($pdf, $modalidades, $t, $L, $W, $direita) {
            $y = $pdf->GetY() + 1;
            $pdf->cor('nevoa', 'F');
            $pdf->Rect($L, $y, $W, 12, 'F');
            $pdf->fonte('intersemi', '', 7.6, 'marinho');
            $pdf->Text($L + 3, $y + 8.6, pdf_txt((string) $t['coluna_linhas']));
            foreach ($modalidades as $i => $m) {
                $xd = $direita($i);
                $a = pdf_txt((string) $m['coluna']);
                $b = pdf_txt((string) $m['detalhe']);
                $pdf->Text($xd - $pdf->GetStringWidth($a), $y + ($b !== '' ? 5.2 : 8.6), $a);
                if ($b !== '') {
                    $pdf->Text($xd - $pdf->GetStringWidth($b), $y + 8.6, $b);
                }
            }
            $pdf->SetY($y + 12);
        };
        $cabecalho();
        foreach ($linhas as $k => $p) {
            $pdf->SetFont('intersemi', '', 8.8);
            $hn = $pdf->linhas($w1 - 16, pdf_txt((string) $p['designacao'])) * 4.4;
            $hd = $p['detalhe'] ? 4 : 0;
            $h = max(14.5, $hn + $hd + 7);
            if (!$pdf->cabe($h)) {
                $pdf->AddPage();
                $cabecalho();
            }
            $y = $pdf->GetY();
            $meio = $y + $h / 2;
            $pdf->cor('marinho', 'F');
            $pdf->Rect($L + 3, $meio - 3.3, 6.6, 6.6, 'F');
            $pdf->fonte('montserrat', 'B', 8.4, 'branco');
            $num = (string) ($k + 1);
            $pdf->Text($L + 6.3 - $pdf->GetStringWidth($num) / 2, $meio + 1.1, $num);
            $yt = $meio - ($hn + $hd) / 2;
            $pdf->fonte('intersemi', '', 8.8, 'marinho');
            $pdf->SetXY($L + 13.5, $yt);
            $pdf->MultiCell($w1 - 16, 4.4, pdf_txt((string) $p['designacao']), 0, 'L');
            if ($p['detalhe']) {
                $pdf->fonte('inter', '', 7.4, 'suave');
                $pdf->Text($L + 13.5, $yt + $hn + 2.6, pdf_txt((string) $p['detalhe']));
            }
            foreach ($modalidades as $i => $m) {
                $v = $valores[(int) $p['id']][(int) $m['id']] ?? null;
                $xd = $direita($i);
                if ($v === null) {
                    $pdf->fonte('inter', '', 8, 'suave');
                    $s = pdf_txt('Sob consulta');
                } else {
                    $pdf->fonte('inter', 'B', 10.4, 'marinho');
                    $s = pdf_txt(euros($v));
                }
                $pdf->Text($xd - $pdf->GetStringWidth($s), $meio + 1.4, $s);
            }
            $pdf->cor('linha', 'D');
            $pdf->Line($L, $y + $h, $L + $W, $y + $h);
            $pdf->SetY($y + $h);
        }
        if ($t['nota_tabela']) {
            $pdf->Ln(4);
            $pdf->fonte('inter', '', 7.6, 'suave');
            $pdf->SetX($L);
            $pdf->MultiCell($W, 4, pdf_txt((string) $t['nota_tabela']), 0, 'L');
        }
        $pdf->Ln(8);
    }

    // Condições: uma por linha, com marcador
    $condicoes = array_values(array_filter(array_map('trim', explode("\n", (string) $t['condicoes']))));
    if ($condicoes) {
        $pdf->seccao(++$n, (string) $t['titulo_condicoes'], 40);
        foreach ($condicoes as $c) {
            $pdf->fonte('inter', '', 8.9, 'texto');
            $hc = $pdf->linhas($W - 4, pdf_txt($c)) * 5;
            $pdf->espaco($hc + 1.6);
            $y = $pdf->GetY();
            $pdf->cor('petroleo');
            $pdf->Text($L + 0.6, $y + 3.7, chr(149));
            $pdf->cor('texto');
            $pdf->SetXY($L + 4, $y);
            $pdf->MultiCell($W - 4, 5, pdf_txt($c), 0, 'L');
            $pdf->Ln(1.6);
        }
        $pdf->Ln(4);
    }

    // Fecho: endereço do site e nota (à esquerda); informações e inscrições, com código QR (à direita)
    $wl = 70.0;
    $xr = $L + $wl + 4;
    $wr = $W - $wl - 4;
    $contactos = "Rua das Cruzes, 625, 4100-195 Porto, Portugal\ninfo@gadgetmetodo.pt\n+351 915 416 112 (chamada para a rede móvel nacional)\nwww.gadgetmetodo.pt";
    $pdf->SetFont('inter', '', 8.4);
    $hr = max(42.0, 14.6 + $pdf->linhas($wr - 10 - 30, pdf_txt($contactos)) * 4.6 + 6);
    $pdf->espaco($hr + 2);
    $y = $pdf->GetY();
    $yy = $y;
    if ($t['texto_programa']) {
        $pdf->fonte('inter', '', 8.6, 'texto');
        $pdf->SetXY($L, $yy);
        $pdf->MultiCell($wl, 4.6, pdf_txt((string) $t['texto_programa']), 0, 'L');
        $yy = $pdf->GetY() + 2.6;
    }
    $pdf->fonte('intersemi', '', 8.6, 'petroleo');
    $pdf->Text($L, $yy + 3.2, pdf_txt('www.gadgetmetodo.pt'));
    $yy += 9;
    if ($t['nota_documento']) {
        $pdf->fonte('inter', '', 7.6, 'suave');
        $pdf->SetXY($L, $yy);
        $pdf->MultiCell($wl, 4.2, pdf_txt(str_replace('{data}', $data, (string) $t['nota_documento'])), 0, 'L');
    }
    $pdf->cor('nevoa', 'F');
    $pdf->Rect($xr, $y, $wr, $hr, 'F');
    $pdf->cor('petroleo', 'F');
    $pdf->Rect($xr, $y, $wr, 0.8, 'F');
    $pdf->fonte('montserrat', 'B', 6.3, 'petroleo');
    $pdf->rotulo($xr + 5, $y + 7.4, 'Informações e inscrições', 0.4);
    $pdf->fonte('inter', 'B', 8.6, 'marinho');
    $pdf->Text($xr + 5, $y + 13.2, pdf_txt('Gadgetmetodo, Lda.'));
    $pdf->fonte('inter', '', 8.4, 'texto');
    $pdf->SetXY($xr + 5, $y + 14.6);
    $pdf->MultiCell($wr - 10 - 30, 4.6, pdf_txt($contactos), 0, 'L');
    $qr = __DIR__ . '/../../assets/img/qr-formacoes.png';
    if (is_file($qr)) {
        $pdf->Image($qr, $xr + $wr - 5 - 25, $y + 6, 25, 25);
        $pdf->fonte('inter', '', 6.6, 'suave');
        foreach (['Formações e pré-', 'inscrição'] as $k => $l) {
            $s = pdf_txt($l);
            $pdf->Text($xr + $wr - 5 - 12.5 - $pdf->GetStringWidth($s) / 2, $y + 34 + $k * 3.2, $s);
        }
    }
    $pdf->Output('I', 'Gadgetmetodo-Tabela-de-Precos-' . date('Y-m-d') . '.pdf');
    exit;
}
