<?php
// Diagnóstico do estado de utilização de inteligência artificial por parte do formando, na inscrição e na pré-inscrição.
// Nove perguntas de escolha (0 a 3 pontos cada, total 0 a 27), mais a pergunta das ferramentas de IA (não pontuada).
// As perguntas marcadas como "responsavel" medem o uso responsável (0 a 9). Nos cursos de um percurso, as respostas
// indicam o curso aconselhado, que só a Gadgetmetodo vê na área reservada e confirma (ou troca) ao confirmar a inscrição.
// As respostas guardadas com a versão anterior (11 perguntas, total de 0 a 24) continuam a ser lidas com as regras dela.

declare(strict_types=1);

const DIAGNOSTICO = [
    'frequencia' => [
        'texto' => 'Com que frequência usa ferramentas de IA generativa, como o ChatGPT, o Copilot, o Gemini ou o Claude?',
        'opcoes' => ['Nunca usei', 'Experimentei poucas vezes', 'Todas as semanas', 'Todos os dias'],
    ],
    'utilizacao' => [
        'texto' => 'Qual é a tarefa mais exigente para que já usou a IA?',
        'opcoes' => ['Nenhuma', 'Perguntas e pesquisas simples', 'Escrever, resumir ou traduzir textos e documentos', 'Analisar dados, preparar apresentações ou automatizar tarefas'],
    ],
    'instrucoes' => [
        'texto' => 'Quando faz um pedido à IA, como o prepara?',
        'opcoes' => ['Escrevo uma pergunta curta', 'Explico o contexto e o resultado que pretendo', 'Indico também o formato e dou exemplos ou documentos de referência', 'Uso instruções que já testei e reutilizo'],
    ],
    'verificacao' => [
        'texto' => 'O que faz com as respostas da IA antes de as usar?',
        'opcoes' => ['Uso-as como estão', 'Leio-as e corrijo o que me parece errado', 'Confirmo os factos, os números e as fontes importantes', 'Confirmo sempre e peço à IA que justifique ou indique as fontes'],
        'responsavel' => true,
    ],
    'dados' => [
        'texto' => 'Antes de colocar informação numa ferramenta de IA, verifica se tem dados pessoais ou confidenciais?',
        'opcoes' => ['Nunca pensei nisso', 'Às vezes', 'Quase sempre', 'Sempre'],
        'responsavel' => true,
    ],
    'regras' => [
        'texto' => 'Conhece as regras para o uso de IA no seu trabalho (da organização, dos clientes ou da sua atividade)?',
        'opcoes' => ['Não existem ou não sei', 'Existem, mas não as conheço bem', 'Conheço-as em termos gerais', 'Conheço-as e aplico-as'],
        'responsavel' => true,
    ],
    'reutilizacao' => [
        'texto' => 'Já criou instruções, modelos de pedidos ou assistentes que reutiliza (por exemplo, projetos ou GPT personalizados)?',
        'opcoes' => ['Não', 'Experimentei', 'Uso alguns no meu trabalho', 'Criei alguns para a equipa e mantenho-os'],
    ],
    'processos' => [
        'texto' => 'A IA já faz parte de processos de trabalho, seus ou da equipa (por exemplo, ligada ao email, a documentos ou a folhas de cálculo)?',
        'opcoes' => ['Não', 'Em testes pontuais', 'Em alguns processos', 'De forma sistemática, com tarefas automatizadas'],
    ],
    'papel' => [
        'texto' => 'Que papel tem, ou vai ter, na adoção de IA no seu trabalho ou na sua organização?',
        'opcoes' => ['Usar a IA nas minhas tarefas', 'Ajudar colegas a usá-la', 'Melhorar processos da equipa ou da área', 'Decidir ou liderar a adoção na organização'],
    ],
];

const NIVEIS = [
    ['max' => 7, 'nome' => 'Inicial', 'recomendacao' => 'Programa completo, a começar pelo Módulo 1 (fundamentos), com exercícios práticos guiados.'],
    ['max' => 14, 'nome' => 'Exploratório', 'recomendacao' => 'Foco nos Módulos 1 e 2: consolidar os fundamentos e identificar casos de uso no trabalho diário.'],
    ['max' => 21, 'nome' => 'Em adoção', 'recomendacao' => 'Foco nos Módulos 2 e 3: integração da IA nos processos e regras de utilização na equipa.'],
    ['max' => 27, 'nome' => 'Avançado', 'recomendacao' => 'Foco nos Módulos 3 a 5: governação, privacidade e segurança, e partilha de boas práticas com a equipa.'],
];

/* Versão anterior (respostas já guardadas) */

const DIAGNOSTICO_V1 = [
    'frequencia' => [
        'texto' => 'Com que frequência usa ferramentas de IA (por exemplo ChatGPT, Copilot, Gemini ou Claude) no trabalho?',
        'opcoes' => ['Nunca', 'Raramente (algumas vezes por mês)', 'Todas as semanas', 'Todos os dias'],
    ],
    'utilizacao' => [
        'texto' => 'Qual a utilização mais avançada que já fez da IA no trabalho?',
        'opcoes' => ['Nenhuma', 'Perguntas e pesquisas simples', 'Redação, resumos ou tradução de documentos', 'Análise de dados, automação ou integração em processos'],
    ],
    'instrucoes' => [
        'texto' => 'Como avalia a sua capacidade de escrever instruções (prompts) que produzem bons resultados?',
        'opcoes' => ['Nenhuma', 'Básica', 'Boa', 'Muito boa'],
    ],
    'regras' => [
        'texto' => 'Conhece as regras da sua organização para o uso de IA?',
        'opcoes' => ['Não existem regras', 'Existem, mas não as conheço', 'Conheço-as em termos gerais', 'Conheço-as e aplico-as'],
        'responsavel' => true,
    ],
    'dados' => [
        'texto' => 'Antes de introduzir informação numa ferramenta de IA, verifica se contém dados pessoais ou confidenciais?',
        'opcoes' => ['Nunca', 'Às vezes', 'Quase sempre', 'Sempre'],
        'responsavel' => true,
    ],
    'verificacao' => [
        'texto' => 'Confirma a exatidão das respostas da IA antes de as usar?',
        'opcoes' => ['Nunca', 'Às vezes', 'Quase sempre', 'Sempre'],
        'responsavel' => true,
    ],
    'equipa' => [
        'texto' => 'A sua equipa usa IA de forma integrada nos processos de trabalho?',
        'opcoes' => ['Não', 'Em testes pontuais', 'Em alguns processos', 'De forma sistemática'],
    ],
    'tempo' => [
        'texto' => 'Quanto tempo estima poupar por semana com a IA?',
        'opcoes' => ['Nenhum', 'Menos de 1 hora', '1 a 3 horas', 'Mais de 3 horas'],
    ],
];

const DIAGNOSTICO_NIVEL_V1 = [
    'praticas' => [
        'texto' => 'Quando usa IA, como prepara os pedidos?',
        'opcoes' => ['Escrevo perguntas curtas, sem contexto', 'Explico o contexto e o resultado que pretendo', 'Uso modelos de instruções, exemplos ou documentos de referência', 'Tenho instruções e projetos reutilizáveis, que partilho com colegas'],
    ],
    'automacao' => [
        'texto' => 'Já ligou a IA a outras ferramentas de trabalho (email, calendário, documentos, folhas de cálculo) ou automatizou tarefas recorrentes?',
        'opcoes' => ['Nunca', 'Experimentei uma vez', 'Em algumas tarefas', 'Regularmente, em tarefas da equipa'],
    ],
    'papel' => [
        'texto' => 'Que papel tem, ou vai ter, na adoção de IA na sua organização?',
        'opcoes' => ['Utilizo-a no meu trabalho', 'Ajudo colegas a utilizá-la', 'Sou responsável por uma equipa ou por um processo', 'Decido ou lidero a adoção na organização'],
    ],
];

const NIVEIS_V1 = [
    ['max' => 6, 'nome' => 'Inicial', 'recomendacao' => 'Programa completo, a começar pelo Módulo 1 (fundamentos), com exercícios práticos guiados.'],
    ['max' => 12, 'nome' => 'Exploratório', 'recomendacao' => 'Foco nos Módulos 1 e 2: consolidar os fundamentos e identificar casos de uso no trabalho diário.'],
    ['max' => 18, 'nome' => 'Em adoção', 'recomendacao' => 'Foco nos Módulos 2 e 3: integração da IA nos processos e regras de utilização na equipa.'],
    ['max' => 24, 'nome' => 'Avançado', 'recomendacao' => 'Foco nos Módulos 3 a 5: governação, privacidade e segurança, e partilha de boas práticas com a equipa.'],
];

// Perguntas do formulário (o parâmetro mantém-se por compatibilidade: as mesmas em todos os cursos)
function perguntas_diagnostico(bool $nivel = false): array
{
    return DIAGNOSTICO;
}

// Respostas guardadas com a versão anterior do diagnóstico
function diagnostico_v1(array $r): bool
{
    return isset($r['tempo']) || isset($r['equipa']) || isset($r['praticas']);
}

// Perguntas que correspondem a um conjunto de respostas guardado (versão atual ou anterior)
function perguntas_das_respostas(array $r): array
{
    return diagnostico_v1($r) ? DIAGNOSTICO_V1 + (isset($r['praticas']) ? DIAGNOSTICO_NIVEL_V1 : []) : DIAGNOSTICO;
}

// Lê as respostas do formulário; devolve null se faltar alguma
function respostas_diagnostico(bool $nivel = false): ?array
{
    $respostas = [];
    foreach (perguntas_diagnostico() as $id => $p) {
        $v = $_POST['diag'][$id] ?? null;
        if (!is_string($v) || !ctype_digit($v) || (int) $v > 3) {
            return null;
        }
        $respostas[$id] = (int) $v;
    }
    return $respostas;
}

function avaliar_diagnostico(array $respostas): array
{
    // Versão anterior: a pontuação conta só as 8 perguntas base (0 a 24)
    [$perguntas, $niveis] = diagnostico_v1($respostas) ? [DIAGNOSTICO_V1, NIVEIS_V1] : [DIAGNOSTICO, NIVEIS];
    $total = array_sum(array_intersect_key($respostas, $perguntas));
    $responsavel = 0;
    foreach ($perguntas as $id => $p) {
        if (!empty($p['responsavel'])) {
            $responsavel += $respostas[$id] ?? 0;
        }
    }
    $nivel = $niveis[count($niveis) - 1];
    foreach ($niveis as $n) {
        if ($total <= $n['max']) {
            $nivel = $n;
            break;
        }
    }
    $recomendacao = $nivel['recomendacao'];
    if ($responsavel <= 4) {
        $recomendacao .= ' Atenção ao uso responsável: reforçar os Módulos 3, 4 e 5 (regras, dados pessoais e segurança).';
    }
    return ['pontuacao' => $total, 'maximo' => 3 * count($perguntas), 'responsavel' => $responsavel, 'nivel' => $nivel['nome'], 'recomendacao' => $recomendacao];
}

// "Em adoção (18 em 27; uso responsável 7 em 9)"
function resumo_diagnostico(array $a): string
{
    return $a['nivel'] . ' (' . $a['pontuacao'] . ' em ' . $a['maximo'] . '; uso responsável ' . $a['responsavel'] . ' em 9)';
}

/* Nível no percurso: qual dos cursos é o mais adequado */

// Cursos ativos do mesmo percurso, pela ordem do catálogo (vazio se o curso não fizer parte de um percurso)
function cursos_do_percurso(?array $curso): array
{
    if (!$curso || trim((string) $curso['percurso']) === '') {
        return [];
    }
    $lista = consulta('SELECT * FROM cursos WHERE percurso = ? AND ativo = 1 ORDER BY id', [$curso['percurso']]);
    return count($lista) > 1 ? $lista : [];
}

// O diagnóstico inclui as perguntas de nível quando o curso tem diagnóstico e faz parte de um percurso
function com_nivel(array $f): bool
{
    $c = curso((int) ($f['curso_id'] ?? 0));
    return $c && !empty($c['diagnostico']) && cursos_do_percurso($c);
}

// Posição no percurso (0 a 3, do primeiro ao quarto curso) e o motivo. Cada degrau corresponde ao
// pré-requisito do curso seguinte: uso regular, uso estruturado, aplicação em processos e papel na adoção.
function colocacao(array $r): array
{
    $v = function (string $k) use ($r) { return (int) ($r[$k] ?? 0); };
    if (diagnostico_v1($r)) {
        return colocacao_v1($r);
    }
    if ($v('frequencia') <= 1 || $v('utilizacao') <= 1) {
        return [0, 'utilização ainda ocasional ou limitada a perguntas simples: o ponto de partida é a iniciação'];
    }
    if ($v('instrucoes') + $v('verificacao') + $v('reutilizacao') < 6) {
        return [1, 'utilização regular: o passo seguinte é estruturar os pedidos, o contexto e a verificação'];
    }
    if ($v('processos') < 2) {
        return [2, 'uso individual consolidado: o passo seguinte é aplicar a IA nos processos da equipa'];
    }
    if ($v('papel') >= 2) {
        return [3, 'IA já aplicada em processos e responsabilidade na adoção: o foco é liderar a implementação'];
    }
    return [2, 'IA já aplicada em processos; o último curso do percurso destina-se sobretudo a quem lidera a adoção'];
}

function colocacao_v1(array $r): array
{
    $v = function (string $k) use ($r) { return (int) ($r[$k] ?? 0); };
    if ($v('frequencia') <= 1 || $v('utilizacao') <= 1) {
        return [0, 'utilização ainda ocasional ou limitada a perguntas simples: o ponto de partida é a iniciação'];
    }
    if ($v('instrucoes') + $v('praticas') + $v('verificacao') < 6) {
        return [1, 'utilização regular: o passo seguinte é estruturar instruções, contexto e verificação'];
    }
    if ($v('equipa') + $v('automacao') < 4) {
        return [2, 'uso individual consolidado: o passo seguinte é aplicar a IA nos processos da equipa'];
    }
    if ($v('papel') >= 2) {
        return [3, 'IA já aplicada em processos e responsabilidade na adoção: o foco é liderar a implementação'];
    }
    return [2, 'IA já aplicada em processos; o último curso do percurso destina-se sobretudo a quem lidera a adoção'];
}

// Curso aconselhado pelo diagnóstico, no percurso do curso indicado
function curso_indicado(array $respostas, ?array $curso): ?array
{
    $lista = cursos_do_percurso($curso);
    $chaves = diagnostico_v1($respostas) ? ['praticas', 'automacao', 'papel'] : ['reutilizacao', 'processos', 'papel'];
    if (!$lista || array_diff($chaves, array_keys($respostas))) {
        return null;
    }
    [$nivel, $motivo] = colocacao($respostas);
    // Os níveis 0 a 3 correspondem aos quatro primeiros cursos do percurso (Cursos 1 a 4); o Curso 5, de implementação
    // de casos práticos, não é aconselhado pelo diagnóstico. Percursos mais curtos repartem os níveis pelos cursos que têm.
    $i = count($lista) >= 4 ? $nivel : (int) round($nivel * (count($lista) - 1) / 3);
    return ['curso' => $lista[$i], 'motivo' => $motivo];
}

// Recomendação a mostrar numa inscrição: a de módulos só no AI Essentials (cita os seus módulos); nos cursos de
// um percurso, o curso indicado pelo diagnóstico; e, em todos, o alerta de uso responsável quando a pontuação é baixa
function recomendacao_de(array $inscricao): string
{
    $r = json_decode((string) ($inscricao['respostas'] ?? ''), true);
    if (!is_array($r) || !$r) {
        return '';
    }
    $a = avaliar_diagnostico($r);
    if (eh_ai_essentials($inscricao)) {
        return $a['recomendacao'];
    }
    $partes = [];
    $curso = curso((int) ($inscricao['curso_id'] ?? 0));
    if ($curso && ($ind = curso_indicado($r, $curso))) {
        $partes[] = 'Curso aconselhado pelo diagnóstico: ' . $ind['curso']['nome'] . ' (' . $ind['motivo'] . ').'
            . ((int) $ind['curso']['id'] === (int) $curso['id'] ? ' Corresponde ao curso associado.' : ' É diferente do curso associado (' . $curso['nome'] . '): pode associar o aconselhado ou outro antes de confirmar.');
    }
    if ($a['responsavel'] <= 4) {
        $partes[] = 'Atenção ao uso responsável: pontuação baixa nas práticas de regras de utilização, dados pessoais e verificação de resultados. Convém reforçar estes pontos nos casos práticos.';
    }
    return implode(' ', $partes);
}

// Campos do diagnóstico para o formulário de inscrição
function campos_diagnostico(array $anteriores = [], bool $nivel = false): string
{
    $html = '';
    $n = 0;
    foreach (perguntas_diagnostico($nivel) as $id => $p) {
        $n++;
        $html .= '<fieldset class="campo-grupo diag-pergunta"><legend>' . $n . '. ' . h($p['texto']) . ' *</legend><div class="opcoes opcoes-grelha">';
        foreach ($p['opcoes'] as $valor => $rotulo) {
            $marcado = isset($anteriores[$id]) && (int) $anteriores[$id] === $valor ? ' checked' : '';
            $html .= '<label class="opcao"><input type="radio" name="diag[' . h($id) . ']" value="' . $valor . '" required' . $marcado . '> ' . h($rotulo) . '</label>';
        }
        $html .= '</div></fieldset>';
    }
    return $html;
}

// Tabela com as respostas, para consulta de uma inscrição
function tabela_diagnostico(array $inscricao): string
{
    $r = json_decode((string) ($inscricao['respostas'] ?? ''), true);
    if (!is_array($r)) {
        return '';
    }
    $html = '<table><tbody>';
    foreach (perguntas_das_respostas($r) as $id => $p) {
        $v = $r[$id] ?? null;
        $html .= '<tr><th scope="row">' . h($p['texto']) . '</th><td>' . ($v === null ? '' : h($p['opcoes'][$v])) . '</td></tr>';
    }
    return $html . '</tbody></table>';
}
