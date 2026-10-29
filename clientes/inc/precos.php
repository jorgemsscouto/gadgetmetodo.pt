<?php
// Tabela de preços (só administração): manutenção dos preços numa tabela editável e exportação em PDF.
// Os valores guardam-se em cêntimos, sem IVA; uma linha sem valor aparece como "Sob consulta".

declare(strict_types=1);

const TAXAS_IVA = ['23' => '23%', '13' => '13%', '6' => '6%', 'isento' => 'Isento'];
const UNIDADES_PRECO = ['por formando', 'por edição', 'por grupo', 'por hora', 'por dia', 'por mês', 'por projeto'];
const CATEGORIAS_PRECO = ['Formação: cursos abertos', 'Formação à medida', 'Consultoria'];
const OUTRA_OPCAO = '__outra__'; // escolha "Outra (escrever)" nas listas de categoria e de unidade

// Linhas iniciais, sem valores: os cursos ativos e as áreas de serviço. Só na primeira abertura da página.
function semear_precos(): void
{
    if (definicao(bd(), 'precos_iniciais') !== null) {
        return;
    }
    definir(bd(), 'precos_iniciais', agora());
    if (linha_bd('SELECT id FROM precos LIMIT 1')) {
        return;
    }
    $linhas = [];
    foreach (consulta('SELECT nome FROM cursos WHERE ativo = 1 ORDER BY id') as $c) {
        $linhas[] = ['Formação: cursos abertos', $c['nome'], 'por formando'];
    }
    $linhas[] = ['Formação à medida', 'AI Essentials: inteligência artificial para administrações e equipas', 'por grupo'];
    foreach (['Organização e Métodos (O&M)', 'Inteligência artificial e transformação digital', 'Proteção de dados e conformidade (RGPD, NIS2, DORA)', 'Encarregado de Proteção de Dados (DPO) externo', 'Estratégia e gestão'] as $s) {
        $linhas[] = ['Consultoria', $s, 'por projeto'];
    }
    foreach ($linhas as $n => [$categoria, $designacao, $unidade]) {
        executar('INSERT INTO precos (categoria, designacao, unidade, valor, iva, ativo, ordem, atualizado) VALUES (?, ?, ?, NULL, ?, 1, ?, ?)',
            [$categoria, $designacao, $unidade, '23', ($n + 1) * 10, agora()]);
    }
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

// Cêntimos em euros: "1 250,00 €" (com $simbolo = false, "1250,00", para os campos do formulário)
function euros(int $centimos, bool $simbolo = true): string
{
    return $simbolo ? number_format($centimos / 100, 2, ',', ' ') . ' €' : number_format($centimos / 100, 2, ',', '');
}

function com_iva(int $centimos, string $iva): int
{
    return ctype_digit($iva) ? (int) round($centimos * (100 + (int) $iva) / 100) : $centimos;
}

function lista_precos(bool $so_ativos = false): array
{
    return consulta('SELECT * FROM precos' . ($so_ativos ? ' WHERE ativo = 1' : '') . ' ORDER BY ordem, id');
}

// Valida uma linha do formulário; devolve [dados, erro]
function ler_linha_preco(array $l, string $onde): array
{
    $limpar = function ($v, int $max) {
        $v = is_string($v) && preg_match('//u', $v) ? preg_replace('/[^\P{C}]+/u', '', preg_replace('/\s+/u', ' ', $v)) : '';
        return trim((string) preg_replace('/^(.{0,' . $max . '}).*$/us', '$1', $v));
    };
    // Categoria e unidade: a opção da lista ou, em "Outra (escrever)", o texto escrito ao lado
    $escolha = function (string $campo, int $max) use ($l, $limpar) {
        $v = $limpar($l[$campo] ?? '', $max);
        return $v === OUTRA_OPCAO ? $limpar($l[$campo . '_outra'] ?? '', $max) : $v;
    };
    $d = [
        'categoria' => $escolha('categoria', 80),
        'designacao' => $limpar($l['designacao'] ?? '', 160),
        'descricao' => $limpar($l['descricao'] ?? '', 300),
        'unidade' => $escolha('unidade', 40),
        'categoria_escolha' => (string) ($l['categoria'] ?? ''),
        'unidade_escolha' => (string) ($l['unidade'] ?? ''),
        'valor_texto' => $limpar($l['valor'] ?? '', 20),
        'iva' => isset(TAXAS_IVA[$l['iva'] ?? '']) ? (string) $l['iva'] : '23',
        'ativo' => empty($l['ativo']) ? 0 : 1,
        'ordem' => max(0, min(9999, (int) ($l['ordem'] ?? 0))),
    ];
    $d['valor'] = centimos($d['valor_texto']);
    if ($d['designacao'] === '') {
        return [$d, $onde . ': indique a designação.'];
    }
    if ($d['categoria'] === '') {
        return [$d, $onde . ': escolha a categoria ou escreva a nova.'];
    }
    if (($l['unidade'] ?? '') === OUTRA_OPCAO && $d['unidade'] === '') {
        return [$d, $onde . ': escreva a nova unidade.'];
    }
    if ($d['valor'] === false) {
        return [$d, $onde . ': o preço "' . $d['valor_texto'] . '" não é válido (por exemplo, 250 ou 1250,50).'];
    }
    return [$d, ''];
}

function admin_precos(): void
{
    semear_precos();
    if (!empty($_GET['pdf'])) {
        precos_pdf();
    }
    $erros = [];
    $enviadas = [];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'documento') {
            $validade = texto('validade', 10);
            definir(bd(), 'precos_validade', preg_match('/^\d{4}-\d{2}-\d{2}$/', $validade) && strtotime($validade) ? $validade : '');
            definir(bd(), 'precos_notas', texto('notas', 1500, true));
            aviso('Dados do documento guardados.');
            redirecionar('precos');
        }
        if ($acao === 'guardar') {
            // Valida tudo antes de gravar: com um erro, nada é alterado e o formulário mostra o que foi escrito
            $existentes = array_column(lista_precos(), null, 'id');
            $gravar = [];
            $apagar = [];
            $n = 0;
            foreach ((array) ($_POST['precos'] ?? []) as $id => $l) {
                if (!is_array($l)) {
                    continue;
                }
                $n++;
                $id = (string) $id;
                if (preg_match('/^nova\w*$/', $id)) {
                    // Linha nova deixada em branco: ignorada
                    if (trim((string) ($l['designacao'] ?? '')) === '' && trim((string) ($l['valor'] ?? '')) === '') {
                        continue;
                    }
                    [$d, $e] = ler_linha_preco($l, 'Linha nova');
                } elseif (isset($existentes[(int) $id])) {
                    if (!empty($l['apagar'])) {
                        $apagar[] = (int) $id;
                        continue;
                    }
                    [$d, $e] = ler_linha_preco($l, 'Linha ' . $n);
                } else {
                    continue;
                }
                $enviadas[$id] = $d;
                if ($e) {
                    $erros[] = $e;
                }
                $gravar[$id] = $d;
            }
            if (!$erros) {
                $pdo = bd();
                $pdo->beginTransaction();
                foreach ($apagar as $id) {
                    executar('DELETE FROM precos WHERE id = ?', [$id]);
                }
                foreach ($gravar as $id => $d) {
                    $valores = [$d['categoria'], $d['designacao'], $d['descricao'], $d['unidade'], $d['valor'], $d['iva'], $d['ativo'], $d['ordem'], agora()];
                    if (preg_match('/^nova\w*$/', (string) $id)) {
                        executar('INSERT INTO precos (categoria, designacao, descricao, unidade, valor, iva, ativo, ordem, atualizado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $valores);
                    } else {
                        $a = $existentes[(int) $id];
                        // Só muda a data de atualização das linhas alteradas
                        $igual = [$a['categoria'], $a['designacao'], (string) $a['descricao'], (string) $a['unidade'], $a['valor'] === null ? null : (int) $a['valor'], (string) $a['iva'], (int) $a['ativo'], (int) $a['ordem']]
                            === array_slice($valores, 0, 8);
                        if (!$igual) {
                            executar('UPDATE precos SET categoria = ?, designacao = ?, descricao = ?, unidade = ?, valor = ?, iva = ?, ativo = ?, ordem = ?, atualizado = ? WHERE id = ?', array_merge($valores, [(int) $id]));
                        }
                    }
                }
                $pdo->commit();
                aviso('Tabela de preços guardada' . ($apagar ? ' (' . count($apagar) . ' linha(s) apagada(s))' : '') . '.');
                // "Acrescentar linha" sem JavaScript: grava e volta com uma linha em branco
                redirecionar('precos', empty($_POST['acrescentar']) ? [] : ['nova' => 1]);
            }
        }
    }

    $lista = lista_precos();
    // Opções das listas: as predefinidas e as já usadas na tabela, mais "Outra (escrever)"
    $categorias = array_values(array_unique(array_merge(CATEGORIAS_PRECO, array_column($lista, 'categoria'))));
    $unidades = array_values(array_unique(array_merge(UNIDADES_PRECO, array_filter(array_column($lista, 'unidade')))));
    $lista_escolha = function (string $nome, string $campo, string $rotulo, array $opcoes, string $atual, bool $vazio, int $max) {
        $outra = $atual === OUTRA_OPCAO;
        $html = $vazio ? '<option value=""' . ($atual === '' ? ' selected' : '') . '>(sem unidade)</option>' : '';
        if ($atual !== '' && $atual !== OUTRA_OPCAO && !in_array($atual, $opcoes, true)) {
            $opcoes[] = $atual;
        }
        foreach ($opcoes as $o) {
            $html .= '<option value="' . h($o) . '"' . ($o === $atual ? ' selected' : '') . '>' . h($o) . '</option>';
        }
        $html .= '<option value="' . OUTRA_OPCAO . '"' . ($outra ? ' selected' : '') . '>Outra (escrever)</option>';
        return '<select name="' . $nome . '[' . $campo . ']" data-outra aria-label="' . $rotulo . '">' . $html . '</select>'
            . '<input type="text" name="' . $nome . '[' . $campo . '_outra]" class="nova-opcao" maxlength="' . $max . '" placeholder="Escreva a nova opção" aria-label="Nova opção: ' . $rotulo . '"' . ($outra ? '' : ' hidden') . '>';
    };
    $linha = function (string $id, array $p, int $n) use ($enviadas, $categorias, $unidades, $lista_escolha) {
        $v = $enviadas[$id] ?? null;
        $nova = (bool) preg_match('/^nova/', $id);
        $nome = 'precos[' . $id . ']';
        $val = function (string $campo) use ($v, $p) {
            if ($v !== null) {
                return $campo === 'valor' ? $v['valor_texto'] : (string) $v[$campo];
            }
            if ($campo === 'valor') {
                return ($p['valor'] ?? null) === null ? '' : euros((int) $p['valor'], false);
            }
            return (string) ($p[$campo] ?? '');
        };
        $rotulo = $nova ? 'linha nova' : 'linha ' . $n;
        $ativo = $v !== null ? $v['ativo'] : (int) ($p['ativo'] ?? 1);
        $iva = $val('iva') ?: '23';
        $total = ($p['valor'] ?? null) !== null && $v === null ? euros(com_iva((int) $p['valor'], (string) $p['iva'])) : '';
        // Depois de um erro em "Outra (escrever)" sem texto, a lista volta a mostrar essa escolha
        $categoria = $val('categoria') !== '' ? $val('categoria') : ($nova && $v === null ? CATEGORIAS_PRECO[0] : OUTRA_OPCAO);
        $unidade = $v !== null && $v['unidade'] === '' && $v['unidade_escolha'] === OUTRA_OPCAO ? OUTRA_OPCAO : $val('unidade');
        return '<tr' . ($nova ? ' class="linha-nova"' : '') . '>'
            . '<td>' . $lista_escolha($nome, 'categoria', 'Categoria, ' . $rotulo, $categorias, $categoria, false, 80) . '</td>'
            . '<td><input type="text" name="' . $nome . '[designacao]" value="' . h($val('designacao')) . '" maxlength="160" aria-label="Designação, ' . $rotulo . '"' . ($nova ? ' placeholder="Designação da nova linha"' : ' required') . '>'
            . '<input type="text" name="' . $nome . '[descricao]" value="' . h($val('descricao')) . '" maxlength="300" class="descricao" placeholder="Descrição (opcional)" aria-label="Descrição, ' . $rotulo . '"></td>'
            . '<td>' . $lista_escolha($nome, 'unidade', 'Unidade, ' . $rotulo, $unidades, $unidade, true, 40) . '</td>'
            . '<td><input type="text" name="' . $nome . '[valor]" value="' . h($val('valor')) . '" inputmode="decimal" maxlength="20" placeholder="Sob consulta" class="valor" aria-label="Preço sem IVA em euros, ' . $rotulo . '">'
            . ($total ? '<span class="com-iva">Com IVA: ' . h($total) . '</span>' : '') . '</td>'
            . '<td><select name="' . $nome . '[iva]" aria-label="IVA, ' . $rotulo . '">' . opcoes_select(TAXAS_IVA, $iva) . '</select></td>'
            . '<td class="centro"><input type="checkbox" name="' . $nome . '[ativo]" value="1"' . ($ativo ? ' checked' : '') . ' aria-label="Incluir no PDF, ' . $rotulo . '"></td>'
            . '<td><input type="number" name="' . $nome . '[ordem]" value="' . h($nova && $v === null ? (string) ($n * 10) : $val('ordem')) . '" min="0" max="9999" class="ordem" aria-label="Ordem, ' . $rotulo . '"></td>'
            . '<td class="centro">' . ($nova ? '' : '<input type="checkbox" name="' . $nome . '[apagar]" value="1" aria-label="Apagar, ' . $rotulo . '">') . '</td>'
            . '</tr>';
    };
    $corpo = '';
    foreach ($lista as $n => $p) {
        $corpo .= $linha((string) $p['id'], $p, $n + 1);
    }
    // Linhas novas: as enviadas (depois de um erro) ou uma em branco quando a tabela está vazia ou se pediu "Acrescentar linha"
    $proxima = ($lista ? max(array_map('intval', array_column($lista, 'ordem'))) : 0) + 10;
    $novas = array_values(array_filter(array_keys($enviadas), function ($k) { return (bool) preg_match('/^nova/', (string) $k); }));
    if (!$novas && (!$lista || !empty($_GET['nova']))) {
        $novas = ['nova'];
    }
    foreach ($novas as $k => $id) {
        $corpo .= $linha((string) $id, [], (int) ($proxima / 10) + $k);
    }
    $modelo = $linha('nova__N__', [], (int) ($proxima / 10));
    $ultima = $lista ? max(array_column($lista, 'atualizado')) : '';
    $validade = (string) definicao(bd(), 'precos_validade');
    $notas = definicao(bd(), 'precos_notas') ?? 'Preços em euros. Aos preços sem IVA acresce o IVA à taxa indicada. Os valores "sob consulta" são definidos em proposta, de acordo com o âmbito.';

    pagina_area('precos', 'Tabela de preços', '
      ' . ($erros ? '<div class="aviso aviso-erro" role="alert">' . h(implode(' ', $erros)) . '</div>' : '') . '
      <p class="texto-suave">Uso interno: só o perfil de administração vê esta página. Os preços indicam-se sem IVA (por exemplo, 250 ou 1250,50); sem preço, a linha aparece como "Sob consulta". Desmarque "No PDF" para manter uma linha na tabela sem a exportar.' . ($ultima ? ' Última alteração: ' . h(data_hora($ultima)) . '.' : '') . '</p>
      <p><a class="botao" href="' . h(url('precos', ['pdf' => 1])) . '" target="_blank" rel="noopener">Exportar PDF</a></p>
      <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="guardar">
        <div class="tabela-rolante"><table class="tabela-precos">
          <colgroup><col class="c-categoria"><col><col class="c-unidade"><col class="c-valor"><col class="c-iva"><col class="c-pdf"><col class="c-ordem"><col class="c-apagar"></colgroup>
          <thead><tr><th scope="col">Categoria</th><th scope="col">Designação e descrição</th><th scope="col">Unidade</th><th scope="col">Preço sem IVA (€)</th><th scope="col">IVA</th><th scope="col">No PDF</th><th scope="col">Ordem</th><th scope="col">Apagar</th></tr></thead>
          <tbody>' . $corpo . '</tbody>
        </table></div>
        <template id="modelo-linha-preco">' . $modelo . '</template>
        <p class="ajuda">Em Categoria e Unidade, escolha "Outra (escrever)" para criar uma opção nova. As linhas aparecem no PDF agrupadas por categoria, pela ordem indicada; as linhas novas deixadas em branco são ignoradas.</p>
        <div class="acoes-formulario"><button class="botao" type="submit">Guardar alterações</button> <button class="botao botao-secundario" type="submit" name="acrescentar" value="1" data-acrescentar-linha>Acrescentar linha</button></div>
      </form>
      <h2 class="espaco-topo">Documento em PDF</h2>
      <form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="documento">
        <div class="campos-2">' . campo('validade', 'Válida a partir de', $validade, 'date') . '</div>
        ' . campo('notas', 'Notas no fim da tabela', $notas, 'textarea', 'maxlength="1500" rows="3"') . '
        <div><button class="botao botao-secundario" type="submit">Guardar dados do documento</button></div>
      </form>', 'Gestão');
}

// Tabela de preços em PDF (A4), com o mesmo grafismo da ficha de curso
function precos_pdf(): void
{
    require_once __DIR__ . '/ficha-pdf.php';
    $lista = lista_precos(true);
    $validade = (string) definicao(bd(), 'precos_validade');
    $notas = (string) (definicao(bd(), 'precos_notas') ?? '');
    $pdf = new FichaPdf();
    $pdf->documento = 'Tabela de preços';
    $pdf->logotipo = __DIR__ . '/../../assets/img/gadgetmetodo-logotipo-pdf.png';
    $pdf->SetTitle('Tabela de preços', true);
    $pdf->SetAuthor('Gadgetmetodo, Lda.', true);
    $pdf->SetCreator('www.gadgetmetodo.pt', true);
    $pdf->AddPage();
    $L = 20.0;
    $W = FichaPdf::LARGURA;

    $pdf->Image($pdf->logotipo, $L, 15, 50);
    $pdf->fonte('montserrat', 'B', 7.5, 'petroleo');
    $pdf->rotulo($L + $W, 18.5, 'Tabela de preços', 0.45, 'R');
    $pdf->fonte('inter', '', 8, 'suave');
    $t = pdf_txt('Emitida a ' . data_extensa(date('Y-m-d')));
    $pdf->Text($L + $W - $pdf->GetStringWidth($t), 23, $t);
    $pdf->cor('linha', 'D');
    $pdf->Line($L, 30, $L + $W, 30);

    // Faixa de título
    $pdf->cor('marinho', 'F');
    $pdf->Rect($L, 36, $W, $validade ? 24 : 19, 'F');
    $pdf->cor('petroleo', 'F');
    $pdf->Rect($L, 36, 3, $validade ? 24 : 19, 'F');
    $pdf->fonte('montserrat', 'B', 19, 'branco');
    $pdf->Text($L + 12, 49, pdf_txt('Tabela de preços'));
    if ($validade) {
        $pdf->fonte('inter', '', 9.5, 'claro');
        $pdf->Text($L + 12, 55.5, pdf_txt('Válida a partir de ' . data_extensa($validade)));
    }
    $pdf->SetY($validade ? 68 : 63);

    // Colunas: designação (com descrição), unidade, sem IVA, IVA, com IVA
    $col = [['Designação', 76, 'L'], ['Unidade', 28, 'L'], ['Sem IVA', 24, 'R'], ['IVA', 14, 'R'], ['Com IVA', 28, 'R']];
    $cabecalho = function () use ($pdf, $col, $L) {
        $y = $pdf->GetY();
        $pdf->cor('nevoa', 'F');
        $pdf->Rect($L, $y, FichaPdf::LARGURA, 6.6, 'F');
        $pdf->fonte('montserrat', 'B', 7.2, 'marinho');
        $x = $L;
        foreach ($col as [$t, $w, $a]) {
            $tt = pdf_txt($t);
            $pdf->Text($a === 'R' ? $x + $w - 2 - $pdf->GetStringWidth($tt) : $x + 2, $y + 4.4, $tt);
            $x += $w;
        }
        $pdf->SetY($y + 6.6);
    };
    $grupos = [];
    foreach ($lista as $p) {
        $grupos[$p['categoria']][] = $p;
    }
    if (!$grupos) {
        $pdf->fonte('inter', '', 9.5, 'texto');
        $pdf->SetX($L);
        $pdf->MultiCell($W, 5, pdf_txt('A tabela de preços ainda não tem linhas para exportar.'));
    }
    $n = 0;
    foreach ($grupos as $categoria => $linhas) {
        $pdf->seccao(++$n, $categoria, 30);
        $cabecalho();
        foreach ($linhas as $p) {
            $pdf->SetFont('intersemi', '', 8.8);
            $h1 = $pdf->linhas($col[0][1] - 4, pdf_txt($p['designacao'])) * 4.3;
            $pdf->SetFont('inter', '', 7.8);
            $h2 = $p['descricao'] ? $pdf->linhas($col[0][1] - 4, pdf_txt($p['descricao'])) * 3.8 + 0.6 : 0;
            $pdf->SetFont('inter', '', 8.6);
            $hu = $pdf->linhas($col[1][1] - 4, pdf_txt((string) $p['unidade'])) * 4.3;
            $h = max($h1 + $h2, $hu) + 4;
            if (!$pdf->cabe($h)) {
                $pdf->AddPage();
                $cabecalho();
            }
            $y = $pdf->GetY();
            $pdf->fonte('intersemi', '', 8.8, 'marinho');
            $pdf->SetXY($L + 2, $y + 2);
            $pdf->MultiCell($col[0][1] - 4, 4.3, pdf_txt($p['designacao']), 0, 'L');
            if ($p['descricao']) {
                $pdf->fonte('inter', '', 7.8, 'suave');
                $pdf->SetXY($L + 2, $y + 2 + $h1 + 0.6);
                $pdf->MultiCell($col[0][1] - 4, 3.8, pdf_txt($p['descricao']), 0, 'L');
            }
            $pdf->fonte('inter', '', 8.6, 'texto');
            $x = $L + $col[0][1];
            $pdf->SetXY($x + 2, $y + 2);
            $pdf->MultiCell($col[1][1] - 4, 4.3, pdf_txt((string) $p['unidade']), 0, 'L');
            $x += $col[1][1];
            if ($p['valor'] === null) {
                $pdf->fonte('inter', '', 8.6, 'suave');
                $t = pdf_txt('Sob consulta');
                $pdf->Text($L + $W - 2 - $pdf->GetStringWidth($t), $y + 5.3, $t);
            } else {
                $v = (int) $p['valor'];
                foreach ([euros($v), TAXAS_IVA[$p['iva']] ?? '', euros(com_iva($v, (string) $p['iva']))] as $k => $texto) {
                    $w = $col[2 + $k][1];
                    $pdf->fonte($k === 2 ? 'intersemi' : 'inter', '', 8.6, $k === 2 ? 'marinho' : 'texto');
                    $t = pdf_txt($texto);
                    $pdf->Text($x + $w - 2 - $pdf->GetStringWidth($t), $y + 5.3, $t);
                    $x += $w;
                }
            }
            $pdf->cor('linha', 'D');
            $pdf->Line($L, $y + $h, $L + $W, $y + $h);
            $pdf->SetY($y + $h);
        }
        $pdf->Ln(6);
    }
    if (trim($notas) !== '') {
        $pdf->fonte('inter', '', 8.2, 'suave');
        $pdf->espaco(14);
        $pdf->SetX($L);
        $pdf->MultiCell($W, 4.2, pdf_txt($notas), 0, 'L');
    }
    $pdf->Output('I', 'Gadgetmetodo-Tabela-de-Precos-' . date('Y-m-d') . '.pdf');
    exit;
}
