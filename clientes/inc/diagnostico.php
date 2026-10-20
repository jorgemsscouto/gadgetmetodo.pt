<?php
// Diagnóstico do estado de utilização de inteligência artificial por parte do formando.
// Cada pergunta vale de 0 a 3 pontos (total 0 a 24); as perguntas marcadas como "responsavel"
// medem práticas de uso responsável (0 a 9).

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

const NIVEIS = [
    ['max' => 6, 'nome' => 'Inicial', 'recomendacao' => 'Programa completo, a começar pelo Módulo 1 (fundamentos), com exercícios práticos guiados.'],
    ['max' => 12, 'nome' => 'Exploratório', 'recomendacao' => 'Foco nos Módulos 1 e 2: consolidar os fundamentos e identificar casos de uso no trabalho diário.'],
    ['max' => 18, 'nome' => 'Em adoção', 'recomendacao' => 'Foco nos Módulos 2 e 3: integração da IA nos processos e regras de utilização na equipa.'],
    ['max' => 24, 'nome' => 'Avançado', 'recomendacao' => 'Foco nos Módulos 3 a 5: governação, privacidade e segurança, e partilha de boas práticas com a equipa.'],
];

// Lê as respostas do formulário; devolve null se faltar alguma
function respostas_diagnostico(): ?array
{
    $respostas = [];
    foreach (DIAGNOSTICO as $id => $p) {
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
    $total = array_sum($respostas);
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

function recomendacao_de(array $inscricao): string
{
    $r = json_decode((string) ($inscricao['respostas'] ?? ''), true);
    return is_array($r) ? avaliar_diagnostico($r)['recomendacao'] : '';
}

// Campos do diagnóstico para o formulário de inscrição
function campos_diagnostico(array $anteriores = []): string
{
    $html = '';
    $n = 0;
    foreach (DIAGNOSTICO as $id => $p) {
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
    foreach (DIAGNOSTICO as $id => $p) {
        $v = $r[$id] ?? null;
        $html .= '<tr><th scope="row">' . h($p['texto']) . '</th><td>' . ($v === null ? '' : h($p['opcoes'][$v])) . '</td></tr>';
    }
    return $html . '</tbody></table>';
}
