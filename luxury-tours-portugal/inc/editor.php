<?php
// Inquérito Luxury Tours Portugal: editor do inquérito no painel (separador Inquérito, só administradores).
// Passos e perguntas (criar, ordenar, mover, desativar, apagar), tipo de resposta e regras de cada pergunta, opções e
// textos em cada idioma, textos gerais (introdução e agradecimento) e reposição do inquérito original.

declare(strict_types=1);

const LTP_AJUDA_TIPOS = [
    'unica' => 'Uma opção, por exemplo "Sim" ou "Não".',
    'multipla' => 'Várias opções, com um máximo opcional.',
    'ordenacao' => 'Até N opções por ordem de preferência; o painel mostra um ranking com pontos (N pelo 1.º lugar, N-1 pelo 2.º...).',
    'escala' => 'Um número entre o mínimo e o máximo (por exemplo, de 1 a 5 ou de 0 a 10), com rótulos nos extremos.',
    'texto_curto' => 'Uma linha de texto livre.',
    'texto_longo' => 'Várias linhas de texto livre.',
];
// Textos gerais editáveis: introdução, agradecimento e título da página
const LTP_TEXTOS_GERAIS = [
    'intro.eyebrow' => ['Introdução: sobretítulo', false],
    'intro.title' => ['Introdução: título', false],
    'intro.lead' => ['Introdução: texto', true],
    'intro.length' => ['Introdução: duração (por exemplo, "Seis passos curtos, cerca de dois minutos.")', false],
    'thanks.title' => ['Agradecimento: título', false],
    'thanks.title_name' => ['Agradecimento: título com o nome do cliente ({name})', false],
    'thanks.text' => ['Agradecimento: texto', true],
    'thanks.summary' => ['Agradecimento: título do resumo das escolhas', false],
    'thanks.signature' => ['Agradecimento: assinatura', false],
    'meta.title' => ['Título do separador do navegador', false],
];

/* Utilitários */

function ltp_slug(string $texto, int $max = 30): string
{
    $t = mb_strtolower(trim($texto));
    $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', 'ß' => 'ss']);
    $t = trim(preg_replace('/[^a-z0-9]+/', '_', $t) ?? '', '_');
    return rtrim(substr($t, 0, $max), '_') ?: 'item';
}

// Códigos de opções já usados (na estrutura e nos ficheiros de tradução), para não repetir
function ltp_codigos_usados(array $e): array
{
    $usados = array_keys($e['opcoes']);
    foreach ($e['perguntas'] as $p) {
        $usados = array_merge($usados, $p['opcoes']);
    }
    $f = ltp_textos_ficheiro('en');
    return array_flip(array_merge($usados, array_keys($f['options'] ?? []), array_keys($f['regions'] ?? [])));
}

function ltp_codigo_novo(string $base, array $usados): string
{
    $codigo = substr($base, 0, 56);
    for ($i = 2; isset($usados[$codigo]); $i++) {
        $codigo = substr($base, 0, 52) . '_' . $i;
    }
    return $codigo;
}

// Respostas dadas a cada pergunta (reais e fictícias)
function ltp_contagem_respostas(PDO $pdo): array
{
    $r = [];
    foreach ($pdo->query('SELECT dados, demo FROM respostas') as $linha) {
        $dados = json_decode((string) $linha['dados'], true) ?: [];
        foreach ($dados as $id => $v) {
            if ((is_array($v) && $v) || (!is_array($v) && (string) $v !== '')) {
                $r[$id] = $r[$id] ?? ['reais' => 0, 'demo' => 0];
                $r[$id][$linha['demo'] ? 'demo' : 'reais']++;
            }
        }
    }
    return $r;
}

// Respostas a cada opção de uma pergunta
function ltp_contagem_opcoes(PDO $pdo, array $p): array
{
    $r = [];
    foreach ($pdo->query('SELECT dados FROM respostas') as $linha) {
        $v = ltp_valor(json_decode((string) $linha['dados'], true) ?: [], $p);
        foreach (is_array($v) ? $v : ($v === '' ? [] : [$v]) as $c) {
            $r[$c] = ($r[$c] ?? 0) + 1;
        }
    }
    return $r;
}

// Chaves de texto de uma pergunta, conforme o tipo e as opções
function ltp_chaves_pergunta(array $p): array
{
    $b = 'steps.' . $p['id'] . '.';
    $c = ['title' => [$b . 'title', 'Título da pergunta', false]];
    $c['help'] = [$b . 'help', 'Ajuda (opcional, por baixo do título)', false];
    if ($p['antetitulo']) {
        $c['kicker'] = [$b . 'kicker', 'Antetítulo ({region} = 1.ª escolha da pergunta de ordenação)', false];
    }
    if (in_array($p['tipo'], LTP_TIPOS_COM_OPCOES, true)) {
        $c['other_label'] = [$b . 'other_label', 'Rótulo de "Outra" (só com "Outra" ativo; vazio = "Outra")', false];
        $c['other_placeholder'] = [$b . 'other_placeholder', 'Exemplo dentro do campo "Outra" (opcional)', false];
    }
    if (in_array($p['tipo'], ['texto_curto', 'texto_longo'], true)) {
        $c['placeholder'] = [$b . 'placeholder', 'Exemplo dentro do campo (opcional)', false];
    }
    if ($p['tipo'] === 'escala') {
        $c['min_label'] = [$b . 'min_label', 'Rótulo do mínimo (por exemplo, "Nada provável")', false];
        $c['max_label'] = [$b . 'max_label', 'Rótulo do máximo (por exemplo, "Muito provável")', false];
    }
    return $c;
}

// Título num idioma, sem recorrer a outros idiomas (o comentário original usa "label" em vez de "title")
function ltp_titulo_proprio(string $idioma, array $p): string
{
    return ltp_texto_proprio($idioma, 'steps.' . $p['id'] . '.title') ?: ltp_texto_proprio($idioma, 'steps.' . $p['id'] . '.label');
}

// Idiomas em que faltam o título ou o texto de alguma opção
function ltp_traducoes_em_falta(array $p): array
{
    $falta = [];
    foreach (LTP_IDIOMAS as $c) {
        $ok = ltp_titulo_proprio($c, $p) !== '';
        foreach (in_array($p['tipo'], LTP_TIPOS_COM_OPCOES, true) ? $p['opcoes'] : [] as $o) {
            $ok = $ok && ltp_texto_proprio($c, ltp_chave_opcao($p, $o)) !== '';
        }
        if (!$ok) {
            $falta[] = $c;
        }
    }
    return $falta;
}

function ltp_lingua_editor(): string
{
    $l = is_string($_GET['lingua'] ?? null) ? $_GET['lingua'] : (is_string($_POST['lingua'] ?? null) ? $_POST['lingua'] : 'pt');
    return in_array($l, LTP_IDIOMAS, true) ? $l : 'pt';
}

/* Ações (POST) */

// Devolve [consulta para voltar, mensagem, tipo]
function ltp_editor_acao(string $acao, PDO $pdo): array
{
    $e = ltp_estrutura();
    $id = (string) ($_POST['id'] ?? '');
    $p = $e['perguntas'][$id] ?? null;
    $passo = (int) ($_POST['passo'] ?? -1);
    $geral = 'v=inquerito';
    // Posição de uma pergunta: [índice do passo, índice dentro do passo]
    $onde = function (array $e, string $id) {
        foreach ($e['passos'] as $i => $s) {
            $j = array_search($id, $s['perguntas'], true);
            if ($j !== false) {
                return [$i, $j];
            }
        }
        return [null, null];
    };
    switch ($acao) {
        case 'inq_nova_pergunta':
            $titulo = ltp_limpar_texto($_POST['titulo'] ?? '', 300);
            $tipo = (string) ($_POST['tipo'] ?? '');
            if (mb_strlen($titulo) < 3 || !isset(LTP_TIPOS[$tipo])) {
                return [$geral, 'Indique o título da pergunta (em português) e o tipo de resposta.', 'erro'];
            }
            if (count($e['perguntas']) >= LTP_MAX_PERGUNTAS) {
                return [$geral, 'O inquérito já tem ' . LTP_MAX_PERGUNTAS . ' perguntas, o máximo.', 'erro'];
            }
            $novo = ltp_codigo_novo('p_' . ltp_slug($titulo, 28), array_flip(array_merge(array_keys($e['perguntas']), array_keys(ltp_textos_ficheiro('en')['steps'] ?? []))));
            $e['perguntas'][$novo] = ['tipo' => $tipo, 'obrigatoria' => false, 'ativa' => !in_array($tipo, LTP_TIPOS_COM_OPCOES, true),
                'max' => $tipo === 'ordenacao' ? 3 : 0, 'escala' => ['min' => 1, 'max' => 5]];
            if ($passo < 0 || !isset($e['passos'][$passo])) {
                $e['passos'][] = ['id' => 'passo_' . substr(md5($novo), 0, 6), 'perguntas' => [$novo]];
            } else {
                $e['passos'][$passo]['perguntas'][] = $novo;
            }
            ltp_guardar_estrutura($e);
            ltp_guardar_textos('pt', ['steps.' . $novo . '.title' => $titulo]);
            return ['v=inquerito&pergunta=' . $novo, in_array($tipo, LTP_TIPOS_COM_OPCOES, true)
                ? 'Pergunta criada, desativada até ter opções. Acrescente as opções e ative-a.' : 'Pergunta criada e visível no inquérito.', 'ok'];

        case 'inq_mover_pergunta':
            [$i, $j] = $onde($e, $id);
            if ($i === null) {
                break;
            }
            $lista = &$e['passos'][$i]['perguntas'];
            $k = ($_POST['direcao'] ?? '') === 'cima' ? $j - 1 : $j + 1;
            if ($k >= 0 && $k < count($lista)) {
                [$lista[$j], $lista[$k]] = [$lista[$k], $lista[$j]];
            } elseif ($k < 0 && $i > 0) {
                array_splice($lista, $j, 1);
                $e['passos'][$i - 1]['perguntas'][] = $id;
            } elseif ($k >= count($lista) && $i < count($e['passos']) - 1) {
                array_splice($lista, $j, 1);
                array_unshift($e['passos'][$i + 1]['perguntas'], $id);
            }
            unset($lista);
            ltp_guardar_estrutura($e);
            return [$geral . '#pergunta-' . $id, null, 'ok'];

        case 'inq_passo_pergunta':
            [$i, $j] = $onde($e, $id);
            if ($i === null || !isset($e['passos'][$passo]) || $passo === $i) {
                break;
            }
            array_splice($e['passos'][$i]['perguntas'], $j, 1);
            $e['passos'][$passo]['perguntas'][] = $id;
            ltp_guardar_estrutura($e);
            return [$geral . '#pergunta-' . $id, 'Pergunta movida para o passo ' . ($passo + 1) . '.', 'ok'];

        case 'inq_ativar':
            if (!$p) {
                break;
            }
            $ativar = !empty($_POST['valor']);
            if ($ativar && in_array($p['tipo'], LTP_TIPOS_COM_OPCOES, true) && count($p['opcoes']) < 2) {
                return ['v=inquerito&pergunta=' . $id, 'Acrescente pelo menos 2 opções antes de ativar a pergunta.', 'erro'];
            }
            $e['perguntas'][$id]['ativa'] = $ativar;
            ltp_guardar_estrutura($e);
            return [$geral . '#pergunta-' . $id, $ativar ? 'Pergunta ativada: já aparece no inquérito.' : 'Pergunta desativada: deixa de aparecer no inquérito; as respostas já recolhidas mantêm-se.', 'ok'];

        case 'inq_apagar_pergunta':
            if (!$p) {
                break;
            }
            $n = ltp_contagem_respostas($pdo)[$id] ?? ['reais' => 0, 'demo' => 0];
            if ($n['reais'] && empty($_POST['confirmo'])) {
                return [$geral . '#pergunta-' . $id, 'Esta pergunta tem ' . $n['reais'] . ' resposta(s) reais: marque a confirmação para a apagar, ou desative-a.', 'erro'];
            }
            if (count($e['perguntas']) < 2) {
                return [$geral, 'O inquérito tem de ter pelo menos uma pergunta.', 'erro'];
            }
            [$i, $j] = $onde($e, $id);
            if ($i !== null) {
                array_splice($e['passos'][$i]['perguntas'], $j, 1);
            }
            unset($e['perguntas'][$id]);
            ltp_guardar_estrutura($e);
            return [$geral, 'Pergunta apagada. As respostas já recolhidas continuam guardadas, mas deixam de aparecer no painel e nas exportações.', 'ok'];

        case 'inq_novo_passo':
            $e['passos'][] = ['id' => 'passo_' . substr(md5(uniqid('', true)), 0, 6), 'perguntas' => []];
            ltp_guardar_estrutura($e);
            return [$geral . '#passo-' . count($e['passos']), 'Passo acrescentado no fim. Crie ou mova perguntas para ele; passos sem perguntas ativas não aparecem no inquérito.', 'ok'];

        case 'inq_mover_passo':
            $k = ($_POST['direcao'] ?? '') === 'cima' ? $passo - 1 : $passo + 1;
            if (isset($e['passos'][$passo], $e['passos'][$k])) {
                [$e['passos'][$passo], $e['passos'][$k]] = [$e['passos'][$k], $e['passos'][$passo]];
                ltp_guardar_estrutura($e);
            }
            return [$geral . '#passo-' . ($k + 1), null, 'ok'];

        case 'inq_apagar_passo':
            if (isset($e['passos'][$passo]) && !$e['passos'][$passo]['perguntas'] && count($e['passos']) > 1) {
                array_splice($e['passos'], $passo, 1);
                ltp_guardar_estrutura($e);
                return [$geral, 'Passo apagado.', 'ok'];
            }
            return [$geral, 'Só se apagam passos sem perguntas (e o inquérito tem de ter pelo menos um passo).', 'erro'];

        case 'inq_guardar_pergunta':
            return $p ? ltp_editor_guardar_pergunta($e, $p, $pdo) : [$geral, 'Pergunta não encontrada.', 'erro'];

        case 'inq_textos_gerais':
            $lingua = ltp_lingua_editor();
            $novos = [];
            foreach (array_keys(LTP_TEXTOS_GERAIS) as $chave) {
                $novos[$chave] = ltp_limpar_texto_multilinha($_POST['t'][str_replace('.', '__', $chave)] ?? '', 1000);
            }
            ltp_guardar_textos($lingua, $novos);
            return ['v=inquerito&textos=gerais&lingua=' . $lingua, 'Textos gerais guardados (' . LTP_NOMES_IDIOMAS[$lingua] . ').', 'ok'];

        case 'inq_repor':
            if (empty($_POST['confirmo'])) {
                return [$geral . '#repor', 'Marque a confirmação para repor o inquérito original.', 'erro'];
            }
            $pdo->prepare("DELETE FROM definicoes WHERE chave = 'estrutura'")->execute();
            ltp_apagar_textos_alterados();
            ltp_estrutura(true);
            return [$geral, 'Inquérito original reposto: passos, perguntas, opções e textos. As respostas já recolhidas mantêm-se.', 'ok'];
    }
    return [$geral, 'Pedido inválido.', 'erro'];
}

function ltp_limpar_texto_multilinha($valor, int $maximo): string
{
    $t = is_string($valor) ? str_replace("\r\n", "\n", $valor) : '';
    $t = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', '', $t) ?? '';
    return mb_substr(trim($t), 0, $maximo);
}

// Grava o tipo, as regras, as opções e os textos (no idioma escolhido) de uma pergunta
function ltp_editor_guardar_pergunta(array $e, array $p, PDO $pdo): array
{
    $id = $p['id'];
    $lingua = ltp_lingua_editor();
    $voltar = 'v=inquerito&pergunta=' . $id . '&lingua=' . $lingua;
    $tipo = (string) ($_POST['tipo'] ?? $p['tipo']);
    if (!isset(LTP_TIPOS[$tipo])) {
        return [$voltar, 'Tipo de resposta inválido.', 'erro'];
    }
    $novo = $p;
    $novo['tipo'] = $tipo;
    $novo['obrigatoria'] = !empty($_POST['obrigatoria']);
    $novo['ativa'] = !empty($_POST['ativa']);
    $novo['outra'] = !empty($_POST['outra']) && in_array($tipo, LTP_TIPOS_COM_OPCOES, true);
    $novo['antetitulo'] = !empty($_POST['antetitulo']);
    $novo['privacidade'] = !empty($_POST['privacidade']);
    $novo['rotulo'] = ltp_limpar_texto($_POST['rotulo'] ?? '', 120);
    $max = (int) ($_POST[in_array($tipo, ['texto_curto', 'texto_longo'], true) ? 'max_texto' : 'max'] ?? 0);
    $erros = [];
    if ($tipo === 'escala') {
        $min = (int) ($_POST['escala_min'] ?? 1);
        $maxEscala = (int) ($_POST['escala_max'] ?? 5);
        if ($min < 0 || $min > 1 || $maxEscala < 2 || $maxEscala > 10 || $maxEscala <= $min) {
            $erros[] = 'A escala começa em 0 ou 1 e termina entre 2 e 10.';
        } else {
            $novo['escala'] = ['min' => $min, 'max' => $maxEscala];
        }
        $novo['max'] = 0;
    } elseif (in_array($tipo, ['texto_curto', 'texto_longo'], true)) {
        $novo['max'] = max(0, min($tipo === 'texto_longo' ? 2000 : 300, $max));
    } else {
        $novo['max'] = max(0, min(LTP_MAX_OPCOES, $max));
    }

    // Opções: ordem, retirar, valores em euros, pesquisa no mapa e texto no idioma escolhido; linhas novas criam opções
    $textos = [];
    $infos = $e['opcoes'];
    $usados = ltp_codigos_usados($e);
    $linhas = [];
    foreach ((array) ($_POST['opcao'] ?? []) as $chave => $o) {
        if (!is_array($o)) {
            continue;
        }
        $codigo = (string) ($o['codigo'] ?? '');
        $texto = ltp_limpar_texto($o['texto'] ?? '', 200);
        if ($codigo === '' || !in_array($codigo, $p['opcoes'], true)) {
            if ($texto === '') {
                continue;
            }
            $codigo = ltp_codigo_novo($id . '_' . ltp_slug($texto, 24), $usados);
            $usados[$codigo] = true;
        }
        if (!empty($o['retirar'])) {
            continue;
        }
        $linhas[] = ['codigo' => $codigo, 'ordem' => (float) ($o['ordem'] ?? 999) + count($linhas) / 1000];
        $tipoTexto = $tipo === 'ordenacao' ? 'ordenacao' : 'opcao';
        if ($tipoTexto === 'ordenacao') {
            $textos['regions.' . $codigo . '.name'] = $texto;
            $textos['regions.' . $codigo . '.country'] = ltp_limpar_texto($o['pais'] ?? '', 80);
            $textos['regions.' . $codigo . '.note'] = ltp_limpar_texto($o['nota'] ?? '', 200);
        } else {
            $textos['options.' . $codigo] = $texto;
        }
        $i = $infos[$codigo] ?? [];
        if (array_key_exists('mapa', $o)) {
            $mapa = ltp_limpar_texto($o['mapa'], 120);
            if ($mapa === '') {
                unset($i['mapa']);
            } else {
                $i['mapa'] = $mapa;
            }
        }
        if (array_key_exists('euros_min', $o) || array_key_exists('euros_max', $o)) {
            $euros = array_filter(['min' => trim((string) ($o['euros_min'] ?? '')), 'max' => trim((string) ($o['euros_max'] ?? ''))], fn($v) => $v !== '');
            foreach ($euros as $k => $v) {
                $euros[$k] = (int) preg_replace('/\D/', '', $v);
            }
            if ($euros) {
                $i['euros'] = $euros;
            } else {
                unset($i['euros']);
            }
        }
        $infos[$codigo] = $i;
    }
    usort($linhas, fn($a, $b) => $a['ordem'] <=> $b['ordem']);
    $opcoes = array_slice(array_values(array_unique(array_column($linhas, 'codigo'))), 0, LTP_MAX_OPCOES);
    if (in_array($tipo, LTP_TIPOS_COM_OPCOES, true)) {
        $novo['opcoes'] = $opcoes;
        if ($tipo === 'ordenacao') {
            $novo['max'] = max(1, min($novo['max'] ?: 3, max(1, count($opcoes))));
        }
    }
    $aviso = '';
    if ($novo['ativa'] && in_array($tipo, LTP_TIPOS_COM_OPCOES, true) && count($novo['opcoes']) < 2) {
        $novo['ativa'] = false;
        $aviso = ' A pergunta ficou desativada até ter pelo menos 2 opções.';
    }

    // Textos da pergunta no idioma escolhido
    foreach (ltp_chaves_pergunta($novo) as $nome => [$chave]) {
        if (array_key_exists($nome, (array) ($_POST['t'] ?? []))) {
            $textos[$chave] = ltp_limpar_texto_multilinha($_POST['t'][$nome], $nome === 'help' ? 600 : 300);
        }
    }
    if ($lingua === 'pt' && trim((string) ($textos['steps.' . $id . '.title'] ?? 'x')) === '' && ltp_texto_ficheiro('pt', 'steps.' . $id . '.label') === '') {
        $erros[] = 'O título em português é obrigatório (é o que aparece no painel e nas exportações).';
    }
    if ($erros) {
        return [$voltar, implode(' ', $erros), 'erro'];
    }
    $e['perguntas'][$id] = $novo;
    $e['opcoes'] = $infos;
    ltp_guardar_estrutura($e);
    ltp_guardar_textos($lingua, $textos);
    return [$voltar, 'Pergunta guardada (textos em ' . mb_strtolower(LTP_NOMES_IDIOMAS[$lingua]) . ').' . $aviso, $aviso ? 'aviso' : 'ok'];
}

/* Vistas */

function ltp_editor_vista(PDO $pdo): string
{
    if (is_string($_GET['pergunta'] ?? null) && ltp_pergunta($_GET['pergunta'])) {
        return ltp_editor_vista_pergunta($pdo, ltp_pergunta($_GET['pergunta']));
    }
    if (($_GET['textos'] ?? '') === 'gerais') {
        return ltp_editor_vista_textos();
    }
    return ltp_editor_vista_geral($pdo);
}

function ltp_editor_botao(string $acao, array $campos, string $rotulo, string $classe = 'ligacao', string $extra = ''): string
{
    $h = '<form method="post" class="em-linha">' . campo_csrf();
    foreach ($campos as $k => $v) {
        $h .= '<input type="hidden" name="' . ltp_h($k) . '" value="' . ltp_h((string) $v) . '">';
    }
    return $h . $extra . '<button type="submit" name="acao" value="' . ltp_h($acao) . '" class="' . ltp_h($classe) . '">' . ltp_h($rotulo) . '</button></form>';
}

function ltp_editor_vista_geral(PDO $pdo): string
{
    $e = ltp_estrutura();
    $contagem = ltp_contagem_respostas($pdo);
    $reais = (int) $pdo->query('SELECT COUNT(*) FROM respostas WHERE demo = 0')->fetchColumn();
    $nomes = LTP_NOMES_IDIOMAS;
    $h = '<div class="titulo-linha"><h1>Inquérito</h1><a class="botao botao-secundario" href="../?lang=pt" target="_blank" rel="noopener">Pré-visualizar o inquérito</a></div>'
        . '<p class="nota">Passos e perguntas do inquérito, pela ordem em que aparecem. As alterações aplicam-se de imediato à página do inquérito. '
        . 'Em cada pergunta escolhe-se o tipo de resposta, se é obrigatória, as opções e os textos em cada idioma; o que faltar traduzir aparece em inglês (ou, sem inglês, em português).</p>';
    if ($reais) {
        $h .= '<p class="mensagem mensagem-aviso">Já há ' . $reais . ' resposta(s) reais. Pode mudar os textos à vontade; mudar o tipo de uma pergunta, retirar opções ou apagar perguntas torna as respostas já recolhidas difíceis de comparar. Para deixar de perguntar, prefira desativar.</p>';
    }
    $opcoesPassos = '';
    foreach ($e['passos'] as $i => $s) {
        $opcoesPassos .= '<option value="' . $i . '">Passo ' . ($i + 1) . '</option>';
    }
    $total = count($e['passos']);
    foreach ($e['passos'] as $i => $s) {
        $h .= '<section class="bloco passo-editor" id="passo-' . ($i + 1) . '"><div class="titulo-linha"><h2>Passo ' . ($i + 1) . '</h2><p class="acoes-linha">'
            . ($i > 0 ? ltp_editor_botao('inq_mover_passo', ['passo' => $i, 'direcao' => 'cima'], 'Subir passo') : '')
            . ($i < $total - 1 ? ltp_editor_botao('inq_mover_passo', ['passo' => $i, 'direcao' => 'baixo'], 'Descer passo') : '')
            . (!$s['perguntas'] && $total > 1 ? ltp_editor_botao('inq_apagar_passo', ['passo' => $i], 'Apagar passo') : '') . '</p></div>';
        if (!$s['perguntas']) {
            $h .= '<p class="vazio">Passo sem perguntas: não aparece no inquérito.</p></section>';
            continue;
        }
        $h .= '<div class="tabela-rolante"><table class="tabela editor-perguntas"><thead><tr><th scope="col">Pergunta</th><th scope="col">Tipo de resposta</th><th scope="col">Obrigatória</th><th scope="col" class="num">Opções</th><th scope="col" class="num">Respostas</th><th scope="col">Traduções</th><th scope="col"><span class="sr">Ações</span></th></tr></thead><tbody>';
        foreach ($s['perguntas'] as $j => $id) {
            $p = $e['perguntas'][$id];
            $n = $contagem[$id] ?? ['reais' => 0, 'demo' => 0];
            $falta = ltp_traducoes_em_falta($p);
            $titulo = ltp_titulo_proprio('pt', $p) ?: ltp_rotulo_pergunta($p);
            $h .= '<tr id="pergunta-' . ltp_h($id) . '"' . ($p['ativa'] ? '' : ' class="inativa"') . '>'
                . '<th scope="row"><a href="?v=inquerito&amp;pergunta=' . ltp_h($id) . '">' . ltp_h($titulo) . '</a>' . ($p['ativa'] ? '' : ' <span class="etiqueta">desativada</span>') . '</th>'
                . '<td>' . ltp_h(LTP_TIPOS[$p['tipo']]) . ($p['tipo'] === 'ordenacao' ? ' <span class="suave">(até ' . ltp_lugares($p) . ')</span>' : ($p['tipo'] === 'escala' ? ' <span class="suave">(' . $p['escala']['min'] . ' a ' . $p['escala']['max'] . ')</span>' : '')) . '</td>'
                . '<td>' . ($p['obrigatoria'] ? 'Sim' : 'Não') . '</td>'
                . '<td class="num">' . (in_array($p['tipo'], LTP_TIPOS_COM_OPCOES, true) ? count($p['opcoes']) : '–') . '</td>'
                . '<td class="num">' . $n['reais'] . ($n['demo'] ? ' <span class="suave">+ ' . $n['demo'] . ' fictícias</span>' : '') . '</td>'
                . '<td>' . ($falta ? '<span class="pendente">Falta: ' . ltp_h(implode(', ', array_map(fn($c) => $nomes[$c], $falta))) . '</span>' : 'Completas') . '</td>'
                . '<td class="acoes-celula">'
                . '<a href="?v=inquerito&amp;pergunta=' . ltp_h($id) . '">Editar</a> '
                . ($i > 0 || $j > 0 ? ltp_editor_botao('inq_mover_pergunta', ['id' => $id, 'direcao' => 'cima'], 'Subir') : '')
                . ($i < $total - 1 || $j < count($s['perguntas']) - 1 ? ltp_editor_botao('inq_mover_pergunta', ['id' => $id, 'direcao' => 'baixo'], 'Descer') : '')
                . ($total > 1 ? '<form method="post" class="em-linha">' . campo_csrf() . '<input type="hidden" name="id" value="' . ltp_h($id) . '"><label class="sr" for="mover-' . ltp_h($id) . '">Mover para o passo</label><select id="mover-' . ltp_h($id) . '" name="passo">' . str_replace('value="' . $i . '"', 'value="' . $i . '" selected', $opcoesPassos) . '</select><button type="submit" name="acao" value="inq_passo_pergunta" class="ligacao">Mover</button></form>' : '')
                . ltp_editor_botao('inq_ativar', ['id' => $id, 'valor' => $p['ativa'] ? '0' : '1'], $p['ativa'] ? 'Desativar' : 'Ativar')
                . ltp_editor_botao('inq_apagar_pergunta', ['id' => $id], 'Apagar', 'ligacao perigo', $n['reais'] ? '<label class="caixa-pequena"><input type="checkbox" name="confirmo" value="1"> confirmo</label>' : '')
                . '</td></tr>';
        }
        $h .= '</tbody></table></div></section>';
    }
    $tipos = '';
    foreach (LTP_TIPOS as $c => $n) {
        $tipos .= '<option value="' . $c . '">' . ltp_h($n) . '</option>';
    }
    $h .= '<div class="grelha">'
        . '<section class="bloco" id="nova"><h2>Nova pergunta</h2><form method="post" class="formulario">' . campo_csrf()
        . '<label>Título da pergunta (em português) <input type="text" name="titulo" required maxlength="300"></label>'
        . '<label>Tipo de resposta <select name="tipo">' . $tipos . '</select></label>'
        . '<label>Passo <select name="passo">' . $opcoesPassos . '<option value="-1" selected>Num passo novo, no fim</option></select></label>'
        . '<p class="nota">' . implode('<br>', array_map(fn($c) => '<strong>' . ltp_h(LTP_TIPOS[$c]) . ':</strong> ' . ltp_h(LTP_AJUDA_TIPOS[$c]), array_keys(LTP_TIPOS))) . '</p>'
        . '<button type="submit" name="acao" value="inq_nova_pergunta" class="botao">Criar pergunta</button></form>'
        . '<form method="post" class="espaco-topo">' . campo_csrf() . '<button type="submit" name="acao" value="inq_novo_passo" class="botao botao-secundario">Acrescentar passo vazio</button></form></section>'
        . '<section class="bloco"><h2>Textos gerais</h2><p>Introdução (título, texto e duração indicada), agradecimento final e título da página, em cada idioma.</p><p><a class="botao botao-secundario" href="?v=inquerito&amp;textos=gerais">Editar os textos gerais</a></p>'
        . '<h2 class="espaco-topo" id="repor">Repor o inquérito original</h2><p class="nota">Volta aos passos, perguntas, opções e textos da proposta original. As respostas já recolhidas mantêm-se.' . (ltp_estrutura_personalizada() ? '' : ' O inquérito está como o original.') . '</p>'
        . '<form method="post" class="formulario">' . campo_csrf() . '<label class="caixa"><input type="checkbox" name="confirmo" value="1" required> Confirmo que quero repor o inquérito original</label><button type="submit" name="acao" value="inq_repor" class="botao botao-secundario">Repor o original</button></form></section>'
        . '</div>';
    return $h;
}

function ltp_editor_abas_idioma(string $base, string $atual, ?array $p = null): string
{
    $h = '<nav class="abas-idioma" aria-label="Idioma dos textos"><span class="suave">Textos em:</span> ';
    foreach (LTP_NOMES_IDIOMAS as $c => $n) {
        $falta = $p !== null && in_array($c, ltp_traducoes_em_falta($p), true);
        $h .= '<a href="' . ltp_h($base . '&lingua=' . $c) . '"' . ($c === $atual ? ' aria-current="page"' : '') . '>' . ltp_h($n) . ($falta ? ' <span class="pendente">(falta)</span>' : '') . '</a> ';
    }
    return $h . '</nav>';
}

// Campo de texto de um idioma, com o texto em inglês por baixo como referência
function ltp_editor_campo_texto(string $nome, string $rotulo, string $chave, string $lingua, bool $longo = false, bool $obrigatorio = false, int $max = 300): string
{
    $valor = ltp_texto_proprio($lingua, $chave);
    $ref = $lingua !== 'en' ? ltp_texto_proprio('en', $chave) : '';
    $dir = $lingua === 'ar' ? ' dir="rtl"' : '';
    $campo = $longo
        ? '<textarea name="t[' . ltp_h($nome) . ']" rows="3" maxlength="' . $max . '" lang="' . $lingua . '"' . $dir . '>' . ltp_h($valor) . '</textarea>'
        : '<input type="text" name="t[' . ltp_h($nome) . ']" value="' . ltp_h($valor) . '" maxlength="' . $max . '" lang="' . $lingua . '"' . $dir . ($obrigatorio ? ' required' : '') . '>';
    return '<label>' . ltp_h($rotulo) . ($obrigatorio ? ' *' : '') . ' ' . $campo . '</label>' . ($ref !== '' ? '<p class="referencia"><span class="suave">Inglês:</span> ' . ltp_h($ref) . '</p>' : '');
}

function ltp_editor_vista_pergunta(PDO $pdo, array $p): string
{
    $id = $p['id'];
    $lingua = ltp_lingua_editor();
    $n = ltp_contagem_respostas($pdo)[$id] ?? ['reais' => 0, 'demo' => 0];
    $porOpcao = ltp_contagem_opcoes($pdo, $p);
    $titulo = ltp_titulo_proprio('pt', $p) ?: ltp_rotulo_pergunta($p);
    $h = '<p><a href="?v=inquerito#pergunta-' . ltp_h($id) . '">Voltar ao inquérito</a></p>'
        . '<div class="titulo-linha"><h1>' . ltp_h($titulo) . '</h1><a class="botao botao-secundario" href="../?lang=' . $lingua . '" target="_blank" rel="noopener">Pré-visualizar</a></div>'
        . '<p class="nota">Código interno: <code>' . ltp_h($id) . '</code>. ' . ($n['reais'] + $n['demo'] ? 'Respostas recolhidas: ' . $n['reais'] . ($n['demo'] ? ' (mais ' . $n['demo'] . ' fictícias)' : '') . '.' : 'Ainda sem respostas.') . '</p>';
    if ($n['reais']) {
        $h .= '<p class="mensagem mensagem-aviso">Esta pergunta já tem respostas reais: mudar o tipo de resposta ou retirar opções torna-as difíceis de comparar. Os textos podem ser melhorados à vontade.</p>';
    }
    $h .= ltp_editor_abas_idioma('?v=inquerito&pergunta=' . $id, $lingua, $p);
    $tipos = '';
    foreach (LTP_TIPOS as $c => $nome) {
        $tipos .= '<option value="' . $c . '"' . ($c === $p['tipo'] ? ' selected' : '') . '>' . ltp_h($nome) . '</option>';
    }
    $marca = fn(bool $v) => $v ? ' checked' : '';
    $h .= '<form method="post" class="formulario editor-pergunta">' . campo_csrf()
        . '<input type="hidden" name="id" value="' . ltp_h($id) . '"><input type="hidden" name="lingua" value="' . $lingua . '">'
        . '<fieldset class="bloco"><legend>Resposta</legend><div class="campos-2">'
        . '<label>Tipo de resposta <select name="tipo" data-tipo>' . $tipos . '</select></label>'
        . '<label data-para="multipla ordenacao">Máximo de escolhas <input type="number" name="max" min="0" max="' . LTP_MAX_OPCOES . '" value="' . ($p['tipo'] === 'ordenacao' ? ltp_lugares($p) : (in_array($p['tipo'], ['multipla'], true) ? $p['max'] : 3)) . '"><span class="ajuda">Escolha múltipla: 0 = sem limite. Ordenação: número de lugares (pontos 3-2-1 com 3 lugares).</span></label>'
        . '<label data-para="escala">Escala: de <select name="escala_min"><option value="0"' . ($p['escala']['min'] === 0 ? ' selected' : '') . '>0</option><option value="1"' . ($p['escala']['min'] === 1 ? ' selected' : '') . '>1</option></select></label>'
        . '<label data-para="escala">a <input type="number" name="escala_max" min="2" max="10" value="' . $p['escala']['max'] . '"></label>'
        . '<label data-para="texto_curto texto_longo">Máximo de caracteres (0 = o valor por omissão) <input type="number" name="max_texto" min="0" max="2000" value="' . (in_array($p['tipo'], ['texto_curto', 'texto_longo'], true) ? $p['max'] : 0) . '"></label>'
        . '<label>Rótulo no painel e nas exportações (em português; vazio = o título) <input type="text" name="rotulo" maxlength="120" value="' . ltp_h($p['rotulo']) . '"></label>'
        . '</div>'
        . '<label class="caixa"><input type="checkbox" name="ativa" value="1"' . $marca($p['ativa']) . '> Ativa (aparece no inquérito)</label>'
        . '<label class="caixa"><input type="checkbox" name="obrigatoria" value="1"' . $marca($p['obrigatoria']) . '> Resposta obrigatória</label>'
        . '<label class="caixa" data-para="unica multipla ordenacao"><input type="checkbox" name="outra" value="1"' . $marca($p['outra']) . '> Permitir "Outra" com texto livre (opcional para quem responde)</label>'
        . '<label class="caixa"><input type="checkbox" name="antetitulo" value="1"' . $marca($p['antetitulo']) . '> Mostrar um antetítulo com a 1.ª escolha da pergunta de ordenação (por exemplo, "A sua primeira escolha: Toscana")</label>'
        . '<label class="caixa"><input type="checkbox" name="privacidade" value="1"' . $marca($p['privacidade']) . '> Mostrar a nota de privacidade e a ligação para a política de privacidade</label>'
        . '<p class="nota" data-ajuda-tipo>' . ltp_h(LTP_AJUDA_TIPOS[$p['tipo']]) . ' Ao mudar o tipo, guarde para ver os campos e as opções próprios desse tipo.</p>'
        . '</fieldset>';

    // Textos da pergunta no idioma escolhido
    $h .= '<fieldset class="bloco"><legend>Textos em ' . mb_strtolower(LTP_NOMES_IDIOMAS[$lingua]) . '</legend>';
    foreach (ltp_chaves_pergunta($p) as $nome => [$chave, $rotulo]) {
        if ($nome === 'title' && ltp_texto_proprio($lingua, $chave) === '' && ltp_texto_proprio($lingua, 'steps.' . $id . '.label') !== '') {
            $chave = 'steps.' . $id . '.label';
        }
        $h .= ltp_editor_campo_texto($nome, $rotulo, $chave, $lingua, $nome === 'help', $nome === 'title' && $lingua === 'pt', $nome === 'help' ? 600 : 300);
    }
    $h .= '</fieldset>';

    // Opções
    if (in_array($p['tipo'], LTP_TIPOS_COM_OPCOES, true)) {
        $ord = $p['tipo'] === 'ordenacao';
        $h .= '<fieldset class="bloco"><legend>Opções (textos em ' . mb_strtolower(LTP_NOMES_IDIOMAS[$lingua]) . ')</legend>'
            . '<p class="nota">' . ($ord ? 'Cada opção é um cartão com o nome, o país ou detalhe e uma nota. A pesquisa no mapa (opcional) acrescenta o ícone (i) com a ligação para o Google Maps.'
                : 'Nos valores em euros (opcionais), o texto pode usar {min} e {max}: são substituídos pelos valores, formatados no idioma de quem responde (por exemplo, "De {min} a {max}").')
            . ' A ordem segue os números da primeira coluna. Para retirar uma opção, marque "Retirar"; as respostas já dadas mantêm-se.</p>'
            . '<div class="tabela-rolante"><table class="tabela editor-opcoes"><thead><tr><th scope="col">Ordem</th><th scope="col">Texto *</th>'
            . ($ord ? '<th scope="col">País ou detalhe</th><th scope="col">Nota</th><th scope="col">Pesquisa no mapa</th>' : '<th scope="col">Euros: de</th><th scope="col">Euros: até</th>')
            . '<th scope="col" class="num">Respostas</th><th scope="col">Retirar</th></tr></thead><tbody>';
        $linhas = array_merge(array_map(fn($c) => ['codigo' => $c], $p['opcoes']), array_fill(0, 3, ['codigo' => '']));
        foreach ($linhas as $k => $l) {
            $c = $l['codigo'];
            $nova = $c === '';
            $info = $nova ? [] : ltp_info_opcao($c);
            $nome = 'opcao[' . $k . ']';
            $texto = $nova ? '' : ltp_texto_proprio($lingua, ltp_chave_opcao($p, $c));
            $ref = !$nova && $lingua !== 'en' ? ltp_texto_proprio('en', ltp_chave_opcao($p, $c)) : '';
            $h .= '<tr' . ($nova ? ' class="nova"' : '') . '>'
                . '<td><input type="number" name="' . $nome . '[ordem]" value="' . ($k + 1) . '" min="1" max="99" class="curto" aria-label="Ordem"><input type="hidden" name="' . $nome . '[codigo]" value="' . ltp_h($c) . '"></td>'
                . '<td><input type="text" name="' . $nome . '[texto]" value="' . ltp_h($texto) . '" maxlength="200" lang="' . $lingua . '"' . ($lingua === 'ar' ? ' dir="rtl"' : '') . ' aria-label="Texto da opção" placeholder="' . ($nova ? 'Nova opção' : '') . '">'
                . ($ref !== '' ? '<span class="referencia">' . ltp_h($ref) . '</span>' : '') . ($nova ? '' : '<span class="codigo">' . ltp_h($c) . '</span>') . '</td>';
            if ($ord) {
                $h .= '<td><input type="text" name="' . $nome . '[pais]" value="' . ($nova ? '' : ltp_h(ltp_texto_proprio($lingua, 'regions.' . $c . '.country'))) . '" maxlength="80" aria-label="País ou detalhe"></td>'
                    . '<td><input type="text" name="' . $nome . '[nota]" value="' . ($nova ? '' : ltp_h(ltp_texto_proprio($lingua, 'regions.' . $c . '.note'))) . '" maxlength="200" aria-label="Nota"></td>'
                    . '<td><input type="text" name="' . $nome . '[mapa]" value="' . ltp_h($info['mapa'] ?? '') . '" maxlength="120" aria-label="Pesquisa no mapa" placeholder="Wachau, Austria"></td>';
            } else {
                $h .= '<td><input type="number" name="' . $nome . '[euros_min]" value="' . ltp_h((string) ($info['euros']['min'] ?? '')) . '" min="0" class="medio" aria-label="Euros: de"></td>'
                    . '<td><input type="number" name="' . $nome . '[euros_max]" value="' . ltp_h((string) ($info['euros']['max'] ?? '')) . '" min="0" class="medio" aria-label="Euros: até"></td>';
            }
            $h .= '<td class="num">' . ($nova ? '' : (int) ($porOpcao[$c] ?? 0)) . '</td>'
                . '<td>' . ($nova ? '<span class="suave">nova</span>' : '<label class="caixa-pequena"><input type="checkbox" name="' . $nome . '[retirar]" value="1"> retirar</label>') . '</td></tr>';
        }
        $h .= '</tbody></table></div><p class="nota">Para mais de 3 opções novas, guarde e volte a acrescentar: aparecem sempre 3 linhas vazias.</p></fieldset>';
    }
    $h .= '<p class="acoes-linha"><button type="submit" name="acao" value="inq_guardar_pergunta" class="botao">Guardar</button> <a href="?v=inquerito">Cancelar</a></p></form>';
    return $h;
}

function ltp_editor_vista_textos(): string
{
    $lingua = ltp_lingua_editor();
    $h = '<p><a href="?v=inquerito">Voltar ao inquérito</a></p><h1>Textos gerais</h1>'
        . '<p class="nota">Textos da introdução, do agradecimento final e do título da página. Os campos vazios voltam ao texto original.</p>'
        . ltp_editor_abas_idioma('?v=inquerito&textos=gerais', $lingua)
        . '<form method="post" class="formulario">' . campo_csrf() . '<input type="hidden" name="lingua" value="' . $lingua . '"><fieldset class="bloco"><legend>Textos em ' . mb_strtolower(LTP_NOMES_IDIOMAS[$lingua]) . '</legend>';
    foreach (LTP_TEXTOS_GERAIS as $chave => [$rotulo, $longo]) {
        $h .= ltp_editor_campo_texto(str_replace('.', '__', $chave), $rotulo, $chave, $lingua, $longo, false, 1000);
    }
    return $h . '</fieldset><p class="acoes-linha"><button type="submit" name="acao" value="inq_textos_gerais" class="botao">Guardar</button></p></form>';
}
