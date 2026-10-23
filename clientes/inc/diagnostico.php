<?php
// Diagnóstico do estado de utilização de inteligência artificial por parte do formando.
// Cada pergunta vale de 0 a 3 pontos (total 0 a 24); as perguntas marcadas como "responsavel"
// medem práticas de uso responsável (0 a 9). Nos cursos que fazem parte de um percurso, três perguntas
// adicionais (DIAGNOSTICO_NIVEL) permitem indicar o curso do percurso mais adequado ao formando.

declare(strict_types=1);

const DIAGNOSTICO = [
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

const DIAGNOSTICO_NIVEL = [
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

const NIVEIS = [
    ['max' => 6, 'nome' => 'Inicial', 'recomendacao' => 'Programa completo, a começar pelo Módulo 1 (fundamentos), com exercícios práticos guiados.'],
    ['max' => 12, 'nome' => 'Exploratório', 'recomendacao' => 'Foco nos Módulos 1 e 2: consolidar os fundamentos e identificar casos de uso no trabalho diário.'],
    ['max' => 18, 'nome' => 'Em adoção', 'recomendacao' => 'Foco nos Módulos 2 e 3: integração da IA nos processos e regras de utilização na equipa.'],
    ['max' => 24, 'nome' => 'Avançado', 'recomendacao' => 'Foco nos Módulos 3 a 5: governação, privacidade e segurança, e partilha de boas práticas com a equipa.'],
];

function perguntas_diagnostico(bool $nivel = false): array
{
    return $nivel ? DIAGNOSTICO + DIAGNOSTICO_NIVEL : DIAGNOSTICO;
}

// Lê as respostas do formulário; devolve null se faltar alguma
function respostas_diagnostico(bool $nivel = false): ?array
{
    $respostas = [];
    foreach (perguntas_diagnostico($nivel) as $id => $p) {
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
    // A pontuação de 0 a 24 conta só as 8 perguntas base (as do nível não mudam a escala)
    $total = array_sum(array_intersect_key($respostas, DIAGNOSTICO));
    $responsavel = 0;
    foreach (DIAGNOSTICO as $id => $p) {
        if (!empty($p['responsavel'])) {
            $responsavel += $respostas[$id] ?? 0;
        }
    }
    $nivel = NIVEIS[count(NIVEIS) - 1];
    foreach (NIVEIS as $n) {
        if ($total <= $n['max']) {
            $nivel = $n;
            break;
        }
    }
    $recomendacao = $nivel['recomendacao'];
    if ($responsavel <= 4) {
        $recomendacao .= ' Atenção ao uso responsável: reforçar os Módulos 3, 4 e 5 (regras, dados pessoais e segurança).';
    }
    return ['pontuacao' => $total, 'responsavel' => $responsavel, 'nivel' => $nivel['nome'], 'recomendacao' => $recomendacao];
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

// Curso indicado pelo diagnóstico, no percurso do curso escolhido
function curso_indicado(array $respostas, ?array $curso): ?array
{
    $lista = cursos_do_percurso($curso);
    if (!$lista || !isset($respostas['praticas'], $respostas['automacao'], $respostas['papel'])) {
        return null;
    }
    [$nivel, $motivo] = colocacao($respostas);
    return ['curso' => $lista[(int) round($nivel * (count($lista) - 1) / 3)], 'motivo' => $motivo];
}

// Frase para quem inscreve (páginas públicas e emails de confirmação)
function frase_indicacao(array $indicado, array $curso, bool $com_motivo = true): string
{
    if ((int) $indicado['curso']['id'] === (int) $curso['id']) {
        return 'O diagnóstico confirma que este é o curso indicado para o ponto de partida do formando' . ($com_motivo ? ' (' . $indicado['motivo'] . ')' : '') . '.';
    }
    return 'O diagnóstico indica ' . curso_com_artigo((string) $indicado['curso']['nome']) . ' como o mais adequado ao ponto de partida do formando' . ($com_motivo ? ' (' . $indicado['motivo'] . ')' : '')
        . '. A Gadgetmetodo vai confirmar consigo o curso antes de confirmar a inscrição.';
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
        $partes[] = 'Curso indicado pelo diagnóstico: ' . $ind['curso']['nome'] . ' (' . $ind['motivo'] . ').'
            . ((int) $ind['curso']['id'] === (int) $curso['id'] ? ' Corresponde ao curso escolhido.' : ' É diferente do curso escolhido (' . $curso['nome'] . '): confirmar com o candidato antes de confirmar a inscrição.');
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
    foreach (perguntas_diagnostico(isset($r['praticas'])) as $id => $p) {
        $v = $r[$id] ?? null;
        $html .= '<tr><th scope="row">' . h($p['texto']) . '</th><td>' . ($v === null ? '' : h($p['opcoes'][$v])) . '</td></tr>';
    }
    return $html . '</tbody></table>';
}
