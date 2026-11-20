<?php
// Área pública de formação (/formacoes/): cursos com inscrição, comparação do percurso, novas formações,
// calendário, inscrição numa turma sem conta e inscrição no curso. As páginas formacoes/*.php são geradas por
// ferramentas/gerar-site.mjs com o cabeçalho e o rodapé do site e chamam as funções deste ficheiro.

declare(strict_types=1);

require_once __DIR__ . '/nucleo.php';
require_once __DIR__ . '/diagnostico.php';
require_once __DIR__ . '/vistas.php';
require_once __DIR__ . '/paginas-cliente.php';
require_once __DIR__ . '/lembretes.php';
require_once __DIR__ . '/formandos.php';
require_once __DIR__ . '/diagnosticos.php';

verificar_lembretes_na_saida();

const SITE_CANONICO = 'https://www.gadgetmetodo.pt';
const MODOS_EVENTO = ['presencial' => 'OfflineEventAttendanceMode', 'online' => 'OnlineEventAttendanceMode', 'misto' => 'MixedEventAttendanceMode'];

function indisponivel_publico(): string
{
    return '<section class="secao"><div class="contentor estreito"><div class="aviso aviso-info">A área de formação está temporariamente indisponível. Para saber as próximas datas ou pedir uma inscrição, escreva para <a href="mailto:info@gadgetmetodo.pt">info@gadgetmetodo.pt</a>.</div></div></section>';
}

function botao_inscrever_publico(array $f, int $restantes): string
{
    if ($f['estado'] !== 'aberta') {
        return '';
    }
    return $restantes > 0
        ? '<a class="botao" href="/formacoes/inscricao.php?f=' . (int) $f['id'] . '">Inscrever-me</a>'
        : '<a href="' . h(link_inscricao_curso((int) ($f['curso_id'] ?? 0))) . '">Esgotada: inscreva-se no curso para as próximas turmas</a>';
}

// Botão de inscrição de um curso: uma sessão com vagas leva direto à inscrição; várias, à escolha da data
// na página do curso; nenhuma, à inscrição no curso (a turma é indicada depois)
function opcao_inscricao(array $c, bool $na_pagina_do_curso = false): array
{
    $sessoes = sessoes_com_vagas((int) $c['id']);
    if (!$sessoes) {
        return ['href' => link_inscricao_curso((int) $c['id']), 'rotulo' => 'Inscrever-me no curso', 'sessoes' => [],
            'nota' => 'Sem turmas agendadas: inscreva-se no curso e a Gadgetmetodo indica-lhe a turma quando as datas forem marcadas.'];
    }
    $proxima = $sessoes[0];
    return [
        'href' => count($sessoes) === 1 ? '/formacoes/inscricao.php?f=' . (int) $proxima['id'] : ($na_pagina_do_curso ? '' : link_curso_publico($c)) . '#sessoes',
        'rotulo' => 'Inscrever-me', 'sessoes' => $sessoes,
        'nota' => 'Próxima edição: ' . horario($proxima) . (count($sessoes) > 1 ? ' (' . count($sessoes) . ' edições disponíveis)' : '') . '.',
    ];
}

// Dados estruturados de cada sessão (schema.org EducationEvent), para motores de pesquisa e assistentes de IA
function jsonld_formacoes(array $formacoes, array $cursos = []): string
{
    $eventos = [];
    if ($cursos) {
        $eventos[] = [
            '@type' => 'ItemList', '@id' => SITE_CANONICO . '/formacoes/#cursos', 'name' => 'Cursos abertos da Gadgetmetodo, Lda.',
            'itemListElement' => array_map(function ($c, $i) {
                return ['@type' => 'ListItem', 'position' => $i + 1, 'item' => [
                    '@type' => 'Course', '@id' => SITE_CANONICO . link_curso_publico($c) . '#curso', 'name' => $c['nome'], 'url' => SITE_CANONICO . link_curso_publico($c),
                    'description' => resumir((string) ($c['objetivo'] ?: ($c['descricao'] ?: $c['nome'])), 300), 'inLanguage' => 'pt-PT', 'provider' => ['@id' => SITE_CANONICO . '/#organizacao'],
                ]];
            }, $cursos, array_keys($cursos)),
        ];
    }
    foreach ($formacoes as $f) {
        $online = $f['modalidade'] === 'online';
        $evento = [
            '@type' => 'EducationEvent',
            '@id' => SITE_CANONICO . '/formacoes/#formacao-' . (int) $f['id'],
            'name' => $f['titulo'] . (!empty($f['modulo_id']) ? ': ' . nome_modulo($f) : ''),
            'description' => descricao_sessao($f),
            'startDate' => date('c', strtotime($f['inicio'])),
            'eventStatus' => 'https://schema.org/' . ($f['estado'] === 'cancelada' ? 'EventCancelled' : 'EventScheduled'),
            'eventAttendanceMode' => 'https://schema.org/' . (MODOS_EVENTO[$f['modalidade']] ?? 'OfflineEventAttendanceMode'),
            'inLanguage' => 'pt-PT',
            'organizer' => ['@id' => SITE_CANONICO . '/#organizacao'],
            'url' => SITE_CANONICO . '/formacoes/curso.php?c=' . (int) $f['curso_id'],
            'location' => $online
                ? ['@type' => 'VirtualLocation', 'url' => SITE_CANONICO . '/formacoes/']
                : ['@type' => 'Place', 'name' => $f['local'] ?: 'Porto', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Porto', 'addressCountry' => 'PT']],
            'maximumAttendeeCapacity' => (int) $f['vagas'],
        ];
        if (!empty($f['fim'])) {
            $evento['endDate'] = date('c', strtotime($f['fim']));
        }
        // Edição com várias sessões: cada sessão com a sua data
        $datas = sessoes_formacao((int) $f['id']);
        if (count($datas) > 1) {
            $evento['subEvent'] = array_map(function ($s, $texto) use ($f) {
                $e = ['@type' => 'EducationEvent', 'name' => $f['titulo'] . ': ' . explode(' · ', $texto)[0], 'startDate' => date('c', strtotime($s['inicio']))];
                if ($s['fim']) {
                    $e['endDate'] = date('c', strtotime($s['fim']));
                }
                return $e;
            }, $datas, linhas_sessoes($f));
        }
        $eventos[] = $evento;
    }
    if (!$eventos) {
        return '';
    }
    $json = json_encode(['@context' => 'https://schema.org', '@graph' => $eventos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT);
    return "  <script type=\"application/ld+json\">\n" . $json . "\n  </script>\n";
}

function descricao_sessao(array $f): string
{
    $m = modulo_da_sessao($f);
    $c = curso((int) ($f['curso_id'] ?? 0));
    $texto = trim((string) ($m['descricao'] ?? '') ?: (string) ($c['descricao'] ?? ''));
    return $texto !== '' ? $texto : nome_modulo($f) . '. Formação da Gadgetmetodo, Lda.';
}

// Cartão de um curso na área de formação, com inscrição, página do curso e ficha em PDF
function cartao_curso(array $c): string
{
    [$sobre, $titulo] = partes_nome($c);
    $op = opcao_inscricao($c);
    $mods = modulos_curso((int) $c['id']);
    $lab = minutos_caso($c);
    $total = array_sum(array_map(function ($x) { return (int) $x[1]; }, pares($c['estrutura'] ?? '')));
    $meta = array_filter([
        (string) ($c['duracao'] ?: ($mods ? count($mods) . ' ' . strtolower(unidade($c, count($mods) > 1)) : '')),
        $lab && $total ? $lab . ' de ' . $total . ' minutos de prática por sessão' : '',
        $c['prerequisitos'] ? 'Pré-requisitos: ' . rtrim(preg_split('/\.\s/u', (string) $c['prerequisitos'])[0], '.') : '',
    ]);
    return '<article class="cartao-curso" id="curso-' . (int) $c['id'] . '">
        <span class="sobretitulo">' . h($sobre) . '</span>
        <h3><a href="' . h(link_curso_publico($c)) . '">' . h($titulo) . '</a></h3>
        ' . ($c['objetivo'] || $c['descricao'] ? '<p>' . h(resumir((string) ($c['objetivo'] ?: $c['descricao']), 190)) . '</p>' : '') . '
        ' . ($meta ? '<ul class="cartao-curso-meta">' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $meta)) . '</ul>' : '') . '
        <p class="cartao-curso-datas' . ($op['sessoes'] ? ' com-datas' : '') . '">' . h($op['nota']) . '</p>
        <div class="cartao-curso-acoes">
          <a class="botao" href="' . h($op['href']) . '">' . h($op['rotulo']) . '<span class="visualmente-oculto">: ' . h($titulo) . '</span></a>
          <a class="botao botao-secundario" href="' . h(link_curso_publico($c)) . '">Ver o curso<span class="visualmente-oculto">: ' . h($titulo) . '</span></a>
          <a class="ligacao-pdf" href="' . h(link_ficha($c)) . '" target="_blank" rel="noopener">Ficha em PDF<span class="visualmente-oculto"> (' . h($titulo) . ')</span></a>
        </div>
      </article>';
}

// Comparação dos cursos de um percurso: sessão-tipo e avaliação lado a lado
function comparacao_cursos(array $cursos): string
{
    $grupos = ['Sessão-tipo (minutos por sessão)' => 'estrutura', 'Avaliação' => 'avaliacao'];
    $cab = '';
    foreach ($cursos as $c) {
        $cab .= '<th scope="col">' . h(partes_nome($c)[0]) . '</th>';
    }
    $corpo = '';
    foreach ($grupos as $rotulo => $campo) {
        $linhas = [];
        foreach ($cursos as $i => $c) {
            foreach (pares($c[$campo] ?? '') as [$k, $val]) {
                $linhas[$k][$i] = $val;
            }
        }
        if (!$linhas) {
            continue;
        }
        $corpo .= '<tr class="grupo"><th scope="colgroup" colspan="' . (count($cursos) + 1) . '">' . h($rotulo) . '</th></tr>';
        foreach ($linhas as $k => $valores) {
            $lab = $campo === 'estrutura' && (stripos($k, 'laborat') !== false || stripos($k, 'caso') !== false);
            $corpo .= '<tr' . ($lab ? ' class="destaque"' : '') . '><th scope="row">' . h($k) . '</th>';
            foreach (array_keys($cursos) as $i) {
                $corpo .= '<td>' . h($valores[$i] ?? '') . '</td>';
            }
            $corpo .= '</tr>';
        }
    }
    return $corpo === '' ? '' : '<div class="conteudo tabela-rolante"><table class="tabela-comparar"><caption class="visualmente-oculto">Comparação dos cursos do percurso</caption><thead><tr><th scope="col">Curso</th>' . $cab . '</tr></thead><tbody>' . $corpo . '</tbody></table></div>';
}

// Requisitos comuns a todos os cursos e, à parte, os que só se aplicam a alguns
function requisitos_cursos(array $cursos): string
{
    $por_linha = [];
    foreach ($cursos as $c) {
        foreach (temas($c['requisitos']) as $r) {
            $por_linha[$r][] = partes_nome($c)[0];
        }
    }
    $comuns = [];
    $outros = [];
    foreach ($por_linha as $r => $quais) {
        if (count($quais) === count($cursos)) {
            $comuns[] = $r;
        } else {
            $ultimo = array_pop($quais);
            $outros[] = ($quais ? implode(', ', $quais) . ' e ' . $ultimo : $ultimo) . ': ' . $r;
        }
    }
    return lista_html(array_merge($comuns, $outros));
}

// Página /formacoes/: cursos por percurso, com inscrição, comparação, novas formações e calendário
function formacoes_publico(): array
{
    header('Cache-Control: no-cache');
    if (!bd()) {
        return ['conteudo' => indisponivel_publico(), 'jsonld' => ''];
    }
    $cursos = consulta('SELECT * FROM cursos WHERE ativo = 1 ORDER BY id');
    $percursos = [];
    $soltos = [];
    foreach ($cursos as $c) {
        if (trim((string) $c['percurso']) !== '') {
            $percursos[$c['percurso']][] = $c;
        } else {
            $soltos[] = $c;
        }
    }
    $html = '';
    $n = 0;
    foreach ($percursos as $nome => $lista) {
        $n++;
        $id = $n === 1 ? 'percurso' : 'percurso-' . $n;
        $horas = array_sum(array_map(function ($c) { return array_sum(array_map(function ($m) { return (float) $m['horas']; }, modulos_curso((int) $c['id']))); }, $lista));
        [$titulo, $sub] = array_pad(array_map('trim', explode(':', $nome, 2)), 2, '');
        $publicos = array_unique(array_filter(array_column($lista, 'publico')));
        $enquadramentos = array_unique(array_filter(array_column($lista, 'enquadramento')));
        // Tempo de prática por sessão em cada curso: só é referido quando cresce de curso para curso
        $labs = array_map('minutos_caso', $lista);
        $crescente = count($labs) > 1 && $labs[0] > 0;
        for ($i = 1; $i < count($labs); $i++) {
            $crescente = $crescente && $labs[$i] > $labs[$i - 1];
        }
        $intro = count($lista) . ' cursos' . ($horas > 0 ? ', ' . horas((string) $horas) . ' no total' : '') . '.'
            . ($crescente
                ? ' A prática no Laboratório de Casos Práticos passa de ' . reset($labs) . ' para ' . end($labs) . ' minutos por sessão ao longo do percurso.' : '')
            . (count($publicos) === 1 ? ' Destinatários: ' . rtrim(lcfirst((string) reset($publicos)), '.') . '.' : '')
            . (com_nivel(['curso_id' => $lista[0]['id']]) ? ' Não sabe por qual começar? A inscrição inclui um diagnóstico com 20 perguntas (utilização e conhecimentos de IA): a Gadgetmetodo analisa as respostas e indica-lhe o curso mais adequado.' : '');
        $html .= '<section class="secao secao-nevoa secao-percurso" aria-labelledby="' . $id . '"><div class="contentor">
          <div class="cabeca-secao"><span class="sobretitulo">Cursos abertos · Percurso</span><h2 id="' . $id . '">' . h($titulo) . '</h2>' . ($sub !== '' ? '<p class="lead-secao">' . h(ucfirst($sub)) . '</p>' : '') . '<p>' . h($intro) . '</p></div>
          <div class="lista-cursos">' . implode('', array_map('cartao_curso', $lista)) . '</div>';
        if (count($lista) > 1) {
            $comparar = comparacao_cursos($lista);
            $requisitos = requisitos_cursos($lista);
            $html .= ($comparar ? '<h3 class="espaco-topo" id="' . $id . '-comparar">Comparar os cursos</h3><p class="texto-suave">Como se distribui o tempo de cada sessão e como é feita a avaliação em cada curso. O programa completo, sessão a sessão, está na página e na ficha de cada curso.</p>' . $comparar : '')
                . ($requisitos ? '<h3 class="espaco-topo-curto">Requisitos técnicos e condições</h3>' . $requisitos : '');
        }
        if (count($enquadramentos) === 1) {
            $html .= '<div class="nota-legal espaco-topo"><h3 class="mt-0">Enquadramento legal</h3>' . paragrafos_html((string) reset($enquadramentos)) . '</div>';
        }
        $html .= '</div></section>';
    }
    if ($soltos) {
        $html .= '<section class="secao secao-nevoa" aria-labelledby="outros-cursos"><div class="contentor">
          <div class="cabeca-secao"><span class="sobretitulo">Cursos abertos</span><h2 id="outros-cursos">' . ($percursos ? 'Outros cursos' : 'Cursos') . '</h2></div>
          <div class="lista-cursos">' . implode('', array_map('cartao_curso', $soltos)) . '</div>
        </div></section>';
    }
    if (!$cursos) {
        $html .= '<section class="secao"><div class="contentor"><div class="aviso aviso-info">Não há cursos abertos neste momento. Para organizar uma formação para a sua equipa, <a href="/contactos/#formulario">contacte-nos</a>.</div></div></section>';
    }

    $formacoes = proximas_formacoes();
    $novas = formacoes_novas();
    $html .= '<section class="secao secao-calendario" aria-labelledby="calendario"><div class="contentor">';
    if ($novas) {
        $html .= '<div class="cabeca-secao"><span class="sobretitulo">Novidades</span><h2 id="novas">Novas formações</h2><p>Edições agendadas nos últimos ' . DIAS_NOVA . ' dias.</p></div>'
            . calendario($novas, 'botao_inscrever_publico', true);
    }
    $html .= '<div class="cabeca-secao' . ($novas ? ' espaco-topo' : '') . '"><span class="sobretitulo">Calendário</span><h2 id="calendario">Próximas edições</h2><p>Escolha a edição e inscreva um ou mais formandos. Cada inscrição inclui todas as sessões do curso e fica pendente até ser confirmada pela Gadgetmetodo por email.</p></div>';
    $html .= $formacoes
        ? calendario($formacoes, 'botao_inscrever_publico', true)
        : '<div class="aviso aviso-info">Ainda não há edições agendadas. ' . ($cursos ? 'Inscreva-se no curso que lhe interessa: a Gadgetmetodo indica-lhe a turma quando as datas forem marcadas.' : 'Para receber as próximas datas, <a href="/contactos/#formulario">contacte-nos</a>.') . '</div>';
    $html .= '</div></section>';
    return ['conteudo' => $html, 'jsonld' => jsonld_formacoes($formacoes, $cursos)];
}

/* Inscrição pública */

// Código com a hora em que o formulário foi mostrado, assinado: impede envios automáticos imediatos e forjados
function token_formulario(): string
{
    $t = (string) time();
    return $t . '.' . substr(hash_hmac('sha256', 'formacoes|' . $t, (string) (cfg()['chave'] ?? 'gm')), 0, 16);
}

function token_valido(string $token): bool
{
    [$t, $assinatura] = array_pad(explode('.', $token, 2), 2, '');
    if (!ctype_digit($t) || !hash_equals(substr(hash_hmac('sha256', 'formacoes|' . $t, (string) (cfg()['chave'] ?? 'gm')), 0, 16), $assinatura)) {
        return false;
    }
    $idade = time() - (int) $t;
    return $idade >= 4 && $idade <= 7200;
}

function inscricao_publica(): array
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    if (!bd()) {
        return ['conteudo' => indisponivel_publico()];
    }
    $id = (int) ($_GET['f'] ?? 0);
    if (isset($_GET['recebida'])) {
        return ['conteudo' => '<section class="secao"><div class="contentor estreito">
          <div class="aviso aviso-ok"><strong>Inscrição recebida.</strong> Enviámos uma confirmação para o email indicado. A Gadgetmetodo confirma a inscrição nos próximos dias úteis.</div>
          <p>Se não receber o email nos próximos minutos, verifique a pasta de correio não desejado ou escreva para <a href="mailto:info@gadgetmetodo.pt">info@gadgetmetodo.pt</a>.</p>
          <div class="acoes">' . ($id ? '<a class="botao" href="/formacoes/inscricao.php?f=' . $id . '">Inscrever outra pessoa</a>' : '') . '<a class="botao botao-secundario" href="/formacoes/">Voltar à área de formação</a></div>
        </div></section>'];
    }
    $f = $id ? linha_bd('SELECT * FROM formacoes WHERE id = ?', [$id]) : null;
    if (!$f || $f['estado'] !== 'aberta' || strtotime($f['inicio']) < strtotime('today')) {
        http_response_code($f ? 410 : 404);
        return ['conteudo' => '<section class="secao"><div class="contentor estreito"><div class="aviso aviso-info">Esta edição não existe ou já não aceita inscrições.</div><div class="acoes"><a class="botao" href="/formacoes/">Ver as próximas edições</a></div></div></section>'];
    }
    $restantes = max(0, (int) $f['vagas'] - inscritos((int) $f['id']));
    if ($restantes === 0) {
        return ['conteudo' => '<section class="secao"><div class="contentor estreito"><div class="aviso aviso-info">Esta edição está esgotada.</div><div class="acoes"><a class="botao" href="/formacoes/">Ver outras edições</a><a class="botao botao-secundario" href="/contactos/#formulario">Pedir lista de espera</a></div></div></section>'];
    }

    $cfg = cfg();
    $erros = [];
    $v = ['tipo_cliente' => '', 'empresa' => '', 'nif' => '', 'contacto_nome' => '', 'contacto_email' => '', 'contacto_telefone' => '', 'eu' => false,
          'formando' => '', 'email' => '', 'funcao' => '', 'expectativas' => '', 'ferramentas' => [], 'diag' => []];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $origem = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
        if ($origem !== '' && !empty($cfg['origens']) && !in_array($origem, $cfg['origens'], true)) {
            http_response_code(403);
            return ['conteudo' => '<section class="secao"><div class="contentor estreito"><div class="aviso aviso-erro">Pedido inválido.</div></div></section>'];
        }
        if (texto('website', 200) !== '') {
            // Campo isca preenchido: robô. Responde como se tivesse corrido bem, sem gravar nada.
            header('Location: /formacoes/inscricao.php?recebida=1', true, 303);
            exit;
        }
        $v = [
            'tipo_cliente' => tipo_cliente_post(), 'empresa' => texto('empresa', 160), 'nif' => preg_replace('/\D+/', '', texto('nif', 20)),
            'contacto_nome' => texto('contacto_nome', 120), 'contacto_email' => texto('contacto_email', 160), 'contacto_telefone' => texto('contacto_telefone', 30),
            'eu' => !empty($_POST['eu']),
            'formando' => texto('formando', 120), 'email' => texto('email', 160), 'funcao' => texto('funcao', 120),
            'expectativas' => texto('expectativas', 1500, true),
            'diag' => array_filter(array_intersect_key((array) ($_POST['diag'] ?? []), perguntas_diagnostico(com_nivel($f))), 'is_string'),
        ] + ferramentas_post(com_diagnostico($f));
        if ($v['tipo_cliente'] === 'particular') {
            $v['empresa'] = '';
        }
        if ($v['eu']) {
            $v['formando'] = $v['formando'] ?: $v['contacto_nome'];
            $v['email'] = $v['email'] ?: $v['contacto_email'];
        }
        $respostas = com_diagnostico($f) ? respostas_diagnostico(com_nivel($f)) : [];
        if (!token_valido(is_string($_POST['t'] ?? null) ? $_POST['t'] : '')) {
            $erros[] = 'O formulário expirou ou foi enviado demasiado depressa. Confirme os dados e envie de novo.';
        }
        if ($v['tipo_cliente'] === '') {
            $erros[] = 'Indique se se inscreve como particular ou em nome de uma empresa.';
        } elseif ($v['tipo_cliente'] === 'empresa' && $v['empresa'] === '') {
            $erros[] = 'Indique o nome da empresa.';
        }
        if ($v['nif'] !== '' && strlen($v['nif']) !== 9) {
            $erros[] = 'O NIF deve ter 9 algarismos.';
        }
        if ($v['contacto_nome'] === '' || !filter_var($v['contacto_email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Indique o seu nome e um email válido.';
        }
        if ($v['formando'] === '') {
            $erros[] = 'Indique o nome do formando.';
        }
        if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'O email do formando não é válido.';
        }
        if ($respostas === null) {
            $erros[] = 'Responda a todas as perguntas do diagnóstico.';
        }
        if ($e = erro_ferramentas($v)) {
            $erros[] = $e;
        }
        if (empty($_POST['consentimento'])) {
            $erros[] = 'É necessário aceitar a política de privacidade.';
        }
        if (!$erros && limite_excedido('formacoes', 6, 3600, false)) {
            $erros[] = 'Recebemos várias inscrições a partir desta ligação na última hora. Para inscrever mais pessoas, escreva para info@gadgetmetodo.pt.';
        }
        if (!$erros) {
            $r = gravar_inscricao($f, ['cliente_id' => null, 'utilizador_id' => null, 'origem' => 'site'] + $v, $respostas, false);
            if ($r['erro']) {
                $erros[] = $r['erro'];
            } else {
                limite_excedido('formacoes', 6, 3600, true);
                emails_nova_inscricao($r['id']);
                header('Location: /formacoes/inscricao.php?recebida=1&f=' . (int) $f['id'], true, 303);
                exit;
            }
        }
    }

    $html = '<section class="secao"><div class="contentor estreito">
      ' . resumo_sessao($f, $restantes, true) . '
      ' . ($erros ? '<div class="aviso aviso-erro" role="alert">' . h(implode(' ', $erros)) . '</div>' : '') . '
      <form method="post" action="/formacoes/inscricao.php?f=' . (int) $f['id'] . '" class="formulario formulario-inscricao">
        <input type="hidden" name="t" value="' . h(token_formulario()) . '">
        <div class="campo-oculto" aria-hidden="true"><label for="c-website">Não preencher</label><input id="c-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
        <h2 class="mb-0">1. Cliente e contacto</h2>
        ' . campo_tipo_cliente($v['tipo_cliente'], 'Inscreve-se como') . '
        <div class="campos-2">
          <div data-so-empresa>' . campo('empresa', 'Nome da empresa ou organização *', $v['empresa'], 'text', 'maxlength="160" autocomplete="organization"') . '</div>
          ' . campo('nif', 'NIF (para a fatura)', $v['nif'], 'text', 'maxlength="11" inputmode="numeric" pattern="[0-9 ]{9,11}"') . '
          ' . campo('contacto_nome', 'O seu nome *', $v['contacto_nome'], 'text', 'required maxlength="120" autocomplete="name"') . '
          ' . campo('contacto_email', 'O seu email *', $v['contacto_email'], 'email', 'required maxlength="160" autocomplete="email"') . '
          ' . campo('contacto_telefone', 'Telefone', $v['contacto_telefone'], 'tel', 'maxlength="30" autocomplete="tel"') . '
        </div>
        <h2 class="mb-0 espaco-topo">2. Formando</h2>
        <label class="opcao consentimento"><input type="checkbox" name="eu" value="1"' . ($v['eu'] ? ' checked' : '') . '> <span>O formando sou eu (pode deixar os campos seguintes em branco)</span></label>
        <div class="campos-2">
          ' . campo('formando', 'Nome completo do formando', $v['formando'], 'text', 'maxlength="120"') . '
          ' . campo('email', 'Email do formando', $v['email'], 'email', 'maxlength="160"') . '
          ' . campo('funcao', 'Função ou departamento', $v['funcao'], 'text', 'maxlength="120"') . '
        </div>
        ' . bloco_diagnostico_form($f, $v, 3, 'Respondidas pelo formando ou com base no que ele indicar.') . '
        ' . campo('expectativas', 'O que espera da formação? Há algum caso concreto do seu trabalho que gostaria de ver tratado?', $v['expectativas'], 'textarea', 'maxlength="1500"') . '
        <label class="opcao consentimento"><input type="checkbox" name="consentimento" value="1" required> <span>Li a <a href="/privacidade/" target="_blank" rel="noopener">Política de privacidade</a> e autorizo o tratamento destes dados pela Gadgetmetodo, Lda. para organizar a formação e adaptar os conteúdos. Se inscrevo outra pessoa, confirmo que a informei. *</span></label>
        <div><button class="botao" type="submit">Enviar inscrição</button></div>
      </form>
    </div></section>';
    return ['conteudo' => $html];
}

/* Inscrição no curso: /formacoes/inscricao-curso.php?c=ID (diagnóstico e dados; a turma é indicada depois) */

function inscricao_curso_publica(): array
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    if (!bd()) {
        return ['conteudo' => indisponivel_publico()];
    }
    $cursos = consulta('SELECT * FROM cursos WHERE ativo = 1 ORDER BY nome');
    if (!$cursos) {
        return ['conteudo' => '<section class="secao"><div class="contentor estreito"><div class="aviso aviso-info">Não há cursos abertos neste momento. Para organizar uma formação para a sua equipa, <a href="/contactos/#formulario">contacte-nos</a>.</div></div></section>'];
    }
    $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    $id = (int) ($post ? ($_POST['curso'] ?? ($_GET['c'] ?? 0)) : ($_GET['c'] ?? 0));
    $c = null;
    foreach ($cursos as $x) {
        if ((int) $x['id'] === $id) {
            $c = $x;
        }
    }
    if (isset($_GET['recebida'])) {
        return ['conteudo' => '<section class="secao"><div class="contentor estreito">
          <div class="aviso aviso-ok"><strong>Inscrição recebida.</strong> Está inscrito(a)' . ($c ? ' no ' . h($c['nome']) : '') . '. Enviámos para o seu email a confirmação, com a ligação para ativar a sua área de formando e o pedido dos dados para o certificado. A Gadgetmetodo indica-lhe a turma, com as datas e o horário das sessões.</div>
          <p>Se não receber o email nos próximos minutos, verifique a pasta de correio não desejado ou escreva para <a href="mailto:info@gadgetmetodo.pt">info@gadgetmetodo.pt</a>.</p>
          <div class="acoes">' . ($c ? '<a class="botao" href="' . h(link_curso_publico($c)) . '">Voltar ao curso</a>' : '') . '<a class="botao botao-secundario" href="/formacoes/">Ver os cursos</a></div>
        </div></section>'];
    }
    $cfg = cfg();
    $erros = [];
    $ref = ['curso_id' => (int) ($c['id'] ?? $cursos[0]['id'])];
    $diag = com_diagnostico($ref);
    $v = ['nome' => '', 'email' => '', 'telefone' => '', 'empresa' => '', 'diag' => [], 'ferramentas' => []];
    if ($post) {
        $origem = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
        if ($origem !== '' && !empty($cfg['origens']) && !in_array($origem, $cfg['origens'], true)) {
            http_response_code(403);
            return ['conteudo' => '<section class="secao"><div class="contentor estreito"><div class="aviso aviso-erro">Pedido inválido.</div></div></section>'];
        }
        if (texto('website', 200) !== '') {
            // Campo isca preenchido: robô. Responde como se tivesse corrido bem, sem gravar nada.
            header('Location: /formacoes/inscricao-curso.php?recebida=1' . ($c ? '&c=' . (int) $c['id'] : ''), true, 303);
            exit;
        }
        $v = ['nome' => texto('nome', 120), 'email' => texto('email', 160), 'telefone' => texto('telefone', 30), 'empresa' => texto('empresa', 160),
            'diag' => array_filter(array_intersect_key((array) ($_POST['diag'] ?? []), perguntas_diagnostico(com_nivel($ref))), 'is_string')] + ferramentas_post($diag);
        $respostas = $diag ? respostas_diagnostico(com_nivel($ref)) : [];
        if (!token_valido(is_string($_POST['t'] ?? null) ? $_POST['t'] : '')) {
            $erros[] = 'O formulário expirou ou foi enviado demasiado depressa. Confirme os dados e envie de novo.';
        }
        if (!$c) {
            $erros[] = 'Escolha o curso.';
        }
        if ($v['nome'] === '' || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Indique o seu nome e um email válido.';
        }
        if ($respostas === null) {
            $erros[] = 'Responda a todas as perguntas do diagnóstico.';
        }
        if ($e = erro_ferramentas($v)) {
            $erros[] = $e;
        }
        if (empty($_POST['consentimento'])) {
            $erros[] = 'É necessário aceitar a política de privacidade.';
        }
        if (!$erros && limite_excedido('inscricoes_curso', 5, 3600, false)) {
            $erros[] = 'Recebemos várias inscrições a partir desta ligação na última hora. Para mais inscrições, escreva para info@gadgetmetodo.pt.';
        }
        if (!$erros) {
            // Formando (pelo email), diagnóstico respondido e inscrição no curso, sem turma; email da inscrição
            $fid = formando_para($v['nome'], $v['email'], null, $v['telefone'] ?: null);
            executar('UPDATE formandos SET consentimento = COALESCE(consentimento, ?) WHERE id = ?', [agora(), $fid]);
            $a = $respostas ? avaliar_diagnostico($respostas) : ['pontuacao' => null, 'nivel' => null];
            $did = executar("INSERT INTO diagnosticos (utilizador_id, nome, email, curso_id, cursos, enviado, respondido, respostas, ferramentas, pontuacao, nivel, formando_id, origem)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'publico')", [$v['nome'], $v['email'], (int) $c['id'], json_encode([(int) $c['id']]), agora(), agora(),
                $respostas ? json_encode($respostas) : null, json_encode(array_values($v['ferramentas']), JSON_UNESCAPED_UNICODE), $a['pontuacao'], $a['nivel'], $fid]);
            $iid = inscrever_apos_diagnostico($did);
            if ($iid && $v['empresa'] !== '') {
                executar("UPDATE inscricoes SET empresa = ?, tipo_cliente = 'empresa' WHERE id = ?", [$v['empresa'], $iid]);
            }
            limite_excedido('inscricoes_curso', 5, 3600, true);
            // Aviso à administração: diagnóstico respondido, com o resultado e o curso aconselhado
            aviso_diagnostico_respondido($did);
            header('Location: /formacoes/inscricao-curso.php?recebida=1&c=' . (int) $c['id'], true, 303);
            exit;
        }
    }
    $resumo = '';
    if ($c) {
        [$sobre, $titulo] = partes_nome($c);
        $op = opcao_inscricao($c);
        $resumo = '<div class="resumo-formacao resumo-curso">
        <span class="sobretitulo">' . h($c['percurso'] ? $sobre . ' · ' . explode(':', (string) $c['percurso'])[0] : $sobre) . '</span>
        <h2 class="mt-0">' . h($titulo) . '</h2>
        <p class="texto-suave mb-0">' . h(implode(' · ', array_filter([(string) $c['tipologia'], (string) $c['duracao']]))) . '</p>
        <p class="mb-0"><a href="' . h(link_curso_publico($c)) . '">Ver o programa do curso</a> · <a href="' . h(link_ficha($c)) . '" target="_blank" rel="noopener">Ficha em PDF</a></p>
      </div>'
      . (!empty($op['sessoes']) ? '<div class="aviso aviso-info">Este curso já tem turmas com datas: ' . h(lcfirst((string) $op['nota'])) . ' <a href="' . h((string) $op['href']) . '">Inscreva-se numa turma</a> ou faça aqui a inscrição no curso e a Gadgetmetodo indica-lhe a turma.</div>' : '');
    }
    $opcoes = '';
    foreach ($cursos as $x) {
        $opcoes .= '<option value="' . (int) $x['id'] . '"' . ($c && (int) $x['id'] === (int) $c['id'] ? ' selected' : '') . '>' . h($x['nome']) . '</option>';
    }
    $html = '<section class="secao"><div class="contentor estreito">
      ' . $resumo . '
      ' . ($erros ? '<div class="aviso aviso-erro" role="alert">' . h(implode(' ', $erros)) . '</div>' : '') . '
      <form method="post" action="/formacoes/inscricao-curso.php' . ($c ? '?c=' . (int) $c['id'] : '') . '" class="formulario formulario-inscricao">
        <input type="hidden" name="t" value="' . h(token_formulario()) . '">
        <div class="campo-oculto" aria-hidden="true"><label for="c-website">Não preencher</label><input id="c-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
        <h2 class="mb-0">1. Os seus dados</h2>
        <div class="campos-2">
          ' . ($c ? '<input type="hidden" name="curso" value="' . (int) $c['id'] . '">' : '<div class="campo"><label for="c-curso">Curso *</label><select id="c-curso" name="curso" required><option value="">Escolha o curso</option>' . $opcoes . '</select></div>') . '
          ' . campo('nome', 'O seu nome *', $v['nome'], 'text', 'required maxlength="120" autocomplete="name"') . '
          ' . campo('email', 'O seu email *', $v['email'], 'email', 'required maxlength="160" autocomplete="email"') . '
          ' . campo('telefone', 'Telefone (opcional)', $v['telefone'], 'tel', 'maxlength="30" autocomplete="tel"') . '
          ' . campo('empresa', 'Empresa ou organização (opcional)', $v['empresa'], 'text', 'maxlength="160" autocomplete="organization"') . '
        </div>
        ' . bloco_diagnostico_form($ref, $v, 2, 'Responda sem recorrer a ferramentas de IA nem a pesquisas: o objetivo é conhecer o seu ponto de partida.') . '
        <label class="opcao consentimento"><input type="checkbox" name="consentimento" value="1" required> <span>Li a <a href="/privacidade/" target="_blank" rel="noopener">Política de privacidade</a> e autorizo o tratamento destes dados pela Gadgetmetodo, Lda. para a minha inscrição e formação. *</span></label>
        <p class="texto-suave mb-0">Depois de enviar, fica inscrito(a) no curso e recebe por email a ligação para ativar a sua área de formando. A turma, com as datas e o horário, é indicada pela Gadgetmetodo.</p>
        <div><button class="botao" type="submit">Inscrever-me no curso</button></div>
      </form>
    </div></section>';
    return ['conteudo' => $html];
}

// Encurta um texto no fim de uma palavra (sem depender da extensão mbstring)
function resumir(string $texto, int $maximo): string
{
    $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
    if (preg_match('/^(.{1,' . $maximo . '})(?:\s|$)/u', $texto, $r) && $r[1] !== $texto) {
        return rtrim($r[1], ' ,.;:') . '…';
    }
    return $texto;
}

/* Página pública de um curso: /formacoes/curso.php?c=ID */

function curso_publico(): array
{
    header('Cache-Control: no-cache');
    $nao_encontrado = ['titulo' => 'Curso não encontrado | Gadgetmetodo, Lda.', 'descricao' => 'Este curso não existe ou já não está disponível.', 'canonical' => SITE_CANONICO . '/formacoes/', 'jsonld' => '',
        'conteudo' => '<section class="topo-pagina"><div class="contentor"><h1>Curso não encontrado</h1><p class="lead">Este curso não existe ou já não está disponível.</p></div></section><section class="secao"><div class="contentor"><div class="acoes"><a class="botao" href="/formacoes/">Ver a área de formação</a></div></div></section>'];
    if (!bd()) {
        return ['conteudo' => indisponivel_publico()] + $nao_encontrado;
    }
    $c = linha_bd('SELECT * FROM cursos WHERE id = ? AND ativo = 1', [(int) ($_GET['c'] ?? 0)]);
    if (!$c) {
        http_response_code(404);
        header('X-Robots-Tag: noindex');
        return $nao_encontrado;
    }
    $mods = modulos_curso((int) $c['id']);
    $sessoes = consulta("SELECT * FROM formacoes WHERE curso_id = ? AND inicio >= ? AND estado != 'cancelada' ORDER BY inicio", [$c['id'], date('Y-m-d 00:00:00')]);
    $total_horas = array_sum(array_map(function ($m) { return (float) $m['horas']; }, $mods));
    $u = unidade($c);
    $factos = factos_curso($c, $mods);
    $min_caso = minutos_caso($c);
    $modulos = '';
    foreach ($mods as $n => $m) {
        $modulos .= '<li class="modulo-publico"><h3><span>' . h($u) . ' ' . ($n + 1) . '</span> ' . h($m['nome']) . ($m['horas'] ? ' <small>' . h(horas((string) $m['horas'])) . '</small>' : '') . '</h3>'
            . ($m['descricao'] ? '<p>' . h($m['descricao']) . '</p>' : '')
            . lista_html(temas($m['temas']))
            . ($m['caso_pratico'] ? '<p class="caso"><strong>Caso prático' . ($min_caso ? ' (' . $min_caso . ' min)' : '') . ':</strong> ' . h($m['caso_pratico']) . '</p>' : '') . '</li>';
    }
    $link = SITE_CANONICO . link_curso_publico($c);
    $op = opcao_inscricao($c, true);
    $seccao = function (string $id, string $titulo, string $corpo) {
        return $corpo === '' ? '' : '<h2 class="espaco-topo" id="' . $id . '">' . h($titulo) . '</h2>' . $corpo;
    };
    $html = '<section class="topo-pagina"><div class="contentor">
        <nav class="migalhas" aria-label="Localização"><ol><li><a href="/">Início</a></li><li><a href="/formacoes/">Formação</a></li><li aria-current="page">' . h($c['nome']) . '</li></ol></nav>
        ' . ($c['percurso'] ? '<span class="sobretitulo">' . h($c['percurso']) . '</span>' : '') . '
        <h1>' . h($c['nome']) . '</h1>
        ' . ($c['tipologia'] || $c['duracao'] ? '<p class="lead">' . h(implode(' · ', array_filter([(string) $c['tipologia'], (string) $c['duracao']]))) . '</p>' : '') . '
      </div></section>
      <section class="secao"><div class="contentor">
        <div class="duas-colunas texto-largo">
          <div>
            ' . ($c['descricao'] ? '<div class="resumo"><h2>Sobre o curso</h2>' . paragrafos_html((string) $c['descricao']) . '</div>' : '') . '
            ' . $seccao('objetivo', 'Objetivo', $c['objetivo'] ? paragrafos_html((string) $c['objetivo']) : '') . '
            ' . $seccao('programa', 'Programa e temas', $modulos ? '<ol class="modulos-publicos">' . $modulos . '</ol>' : '') . '
            ' . $seccao('sessao', 'Como funciona uma sessão', estrutura_html($c)) . '
            ' . $seccao('resultado', 'Resultado esperado', $c['resultado'] ? paragrafos_html((string) $c['resultado']) : '') . '
            ' . $seccao('avaliacao', 'Avaliação', avaliacao_html($c)) . '
            ' . $seccao('requisitos', 'Requisitos técnicos e condições', lista_html(temas($c['requisitos']))) . '
            ' . $seccao('investimento', 'Investimento', $c['investimento'] ? paragrafos_html((string) $c['investimento']) : '') . '
            ' . ($c['enquadramento'] ? '<div class="nota-legal espaco-topo"><h2 id="enquadramento">Enquadramento legal</h2>' . paragrafos_html((string) $c['enquadramento']) . '</div>' : '') . '
          </div>
          <aside class="ficha-curso">
            ' . ($factos ? '<dl>' . implode('', array_map(function ($k, $x) { return '<dt>' . h($k) . '</dt><dd>' . h($x) . '</dd>'; }, array_keys($factos), $factos)) . '</dl>' : '') . '
            ' . ($c['diagnostico'] ? '<p class="texto-suave">' . (com_nivel(['curso_id' => $c['id']])
                ? 'A inscrição inclui um diagnóstico com 20 perguntas (utilização e conhecimentos de IA): a Gadgetmetodo analisa as respostas e indica o curso do percurso mais adequado ao ponto de partida do formando.'
                : 'A inscrição inclui um diagnóstico curto de utilização de inteligência artificial, para ajustar a formação ao ponto de partida de cada formando.') . '</p>' : '') . '
            <p class="cartao-curso-datas' . ($op['sessoes'] ? ' com-datas' : '') . '">' . h($op['nota']) . '</p>
            <div class="ficha-acoes">
              <a class="botao" href="' . h($op['href']) . '">' . h($op['rotulo']) . '</a>
              <a class="botao botao-secundario" href="' . h(link_ficha($c)) . '" target="_blank" rel="noopener">Exportar PDF</a>
              <button class="botao botao-secundario" type="button" data-copiar="' . h($link) . '">Copiar ligação</button>
              <p class="estado-copia" role="status" aria-live="polite"></p>
            </div>
          </aside>
        </div>
        <div class="cabeca-secao espaco-topo"><span class="sobretitulo">Calendário</span><h2 id="sessoes">Próximas edições</h2></div>
        ' . ($sessoes ? calendario($sessoes, 'botao_inscrever_publico', true) . '<p class="espaco-topo-curto">Nenhuma data lhe convém? <a href="' . h(link_inscricao_curso((int) $c['id'])) . '">Inscreva-se no curso</a> e a Gadgetmetodo indica-lhe a próxima turma.</p>'
            : '<div class="aviso aviso-info">Ainda não há turmas agendadas deste curso. Inscreva-se no curso: a Gadgetmetodo indica-lhe a turma quando as datas forem marcadas. Para organizar o curso só para a sua equipa, <a href="/contactos/#formulario">contacte-nos</a>.</div>'
              . '<p><a class="botao" href="' . h(link_inscricao_curso((int) $c['id'])) . '">Inscrever-me no curso</a></p>') . '
      </div></section>';
    $curso_ld = [
        '@context' => 'https://schema.org', '@type' => 'Course', '@id' => SITE_CANONICO . link_curso_publico($c) . '#curso',
        'name' => $c['nome'], 'description' => (string) ($c['descricao'] ?: $c['nome']), 'inLanguage' => 'pt-PT',
        'url' => SITE_CANONICO . link_curso_publico($c), 'provider' => ['@id' => SITE_CANONICO . '/#organizacao'],
    ];
    if ($c['objetivo']) {
        $curso_ld['teaches'] = (string) $c['objetivo'];
    }
    if ($c['prerequisitos']) {
        $curso_ld['coursePrerequisites'] = (string) $c['prerequisitos'];
    }
    if ($total_horas > 0) {
        $curso_ld['timeRequired'] = 'PT' . rtrim(rtrim(number_format($total_horas, 1, '.', ''), '0'), '.') . 'H';
    }
    if ($c['percurso']) {
        $curso_ld['isPartOf'] = ['@type' => 'EducationalOccupationalProgram', 'name' => (string) $c['percurso']];
    }
    if ($mods) {
        $curso_ld['syllabusSections'] = array_map(function ($m) { return ['@type' => 'Syllabus', 'name' => $m['nome'], 'description' => implode('; ', temas($m['temas'])) ?: (string) $m['descricao']]; }, $mods);
    }
    if ($sessoes) {
        $curso_ld['hasCourseInstance'] = array_map(function ($f) {
            $i = ['@type' => 'CourseInstance', 'name' => $f['titulo'] . ' · ' . nome_modulo($f), 'startDate' => date('c', strtotime($f['inicio'])),
                'courseMode' => ['presencial' => 'Onsite', 'online' => 'Online', 'misto' => 'Blended'][$f['modalidade']] ?? 'Onsite'];
            if (!empty($f['fim'])) {
                $i['endDate'] = date('c', strtotime($f['fim']));
            }
            if ($f['modalidade'] !== 'online' && $f['local']) {
                $i['location'] = ['@type' => 'Place', 'name' => $f['local']];
            }
            return $i;
        }, $sessoes);
    }
    $json = json_encode($curso_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT);
    return [
        'titulo' => $c['nome'] . ' | Formação | Gadgetmetodo, Lda.',
        // A duração total abre a descrição: distingue cursos com o mesmo texto em formatos diferentes (ex.: 6 h e 2 h),
        // para cada página ter uma descrição única nos motores de pesquisa
        'descricao' => resumir(($total_horas > 0 ? 'Formação de ' . rtrim(rtrim(number_format($total_horas, 1, ',', ''), '0'), ',') . ($total_horas == 1 ? ' hora' : ' horas') . '. ' : '')
            . (string) ($c['descricao'] ?: $c['nome'] . ': programa, módulos, temas e próximas edições.'), 155),
        'canonical' => SITE_CANONICO . link_curso_publico($c),
        'jsonld' => "  <script type=\"application/ld+json\">\n" . $json . "\n  </script>\n",
        'conteudo' => $html,
    ];
}
