<?php
// Páginas públicas de formações (/formacoes/): novas formações, calendário e inscrição sem conta.
// As páginas formacoes/index.php e formacoes/inscricao.php são geradas por ferramentas/gerar-site.mjs
// com o cabeçalho e o rodapé do site e chamam as funções deste ficheiro.

declare(strict_types=1);

require_once __DIR__ . '/nucleo.php';
require_once __DIR__ . '/diagnostico.php';
require_once __DIR__ . '/vistas.php';
require_once __DIR__ . '/paginas-cliente.php';

const SITE_CANONICO = 'https://www.gadgetmetodo.pt';
const MODOS_EVENTO = ['presencial' => 'OfflineEventAttendanceMode', 'online' => 'OnlineEventAttendanceMode', 'misto' => 'MixedEventAttendanceMode'];

function indisponivel_publico(): string
{
    return '<section class="secao"><div class="contentor estreito"><div class="aviso aviso-info">O calendário de formações está temporariamente indisponível. Para saber as próximas datas ou pedir uma inscrição, escreva para <a href="mailto:info@gadgetmetodo.pt">info@gadgetmetodo.pt</a>.</div></div></section>';
}

function botao_inscrever_publico(array $f, int $restantes): string
{
    if ($f['estado'] !== 'aberta') {
        return '';
    }
    return $restantes > 0
        ? '<a class="botao" href="/formacoes/inscricao.php?f=' . (int) $f['id'] . '">Inscrever</a>'
        : '<a href="/contactos/#formulario">Pedir lista de espera</a>';
}

// Dados estruturados de cada sessão (schema.org EducationEvent), para motores de pesquisa e assistentes de IA
function jsonld_formacoes(array $formacoes): string
{
    $eventos = [];
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

// Página /formacoes/: novas formações e calendário
function formacoes_publico(): array
{
    header('Cache-Control: no-cache');
    if (!bd()) {
        return ['conteudo' => indisponivel_publico(), 'jsonld' => ''];
    }
    $formacoes = proximas_formacoes();
    $novas = formacoes_novas();
    $html = '<section class="secao secao-calendario"><div class="contentor">';
    if ($novas) {
        $html .= '<div class="cabeca-secao"><span class="sobretitulo">Novidades</span><h2 id="novas">Novas formações</h2><p>Sessões agendadas nos últimos ' . DIAS_NOVA . ' dias.</p></div>'
            . calendario($novas, 'botao_inscrever_publico', true);
    }
    $html .= '<div class="cabeca-secao' . ($novas ? ' espaco-topo' : '') . '"><span class="sobretitulo">Calendário</span><h2 id="calendario">Próximas sessões</h2><p>Escolha a sessão e inscreva um ou mais formandos. Cada inscrição fica pendente até ser confirmada pela Gadgetmetodo por email.</p></div>';
    $html .= $formacoes
        ? calendario($formacoes, 'botao_inscrever_publico', true)
        : '<div class="aviso aviso-info">Não há sessões agendadas neste momento. Para receber as próximas datas ou organizar uma formação para a sua equipa, <a href="/contactos/#formulario">contacte-nos</a>.</div>';
    $cursos = consulta('SELECT * FROM cursos WHERE ativo = 1 ORDER BY nome');
    if ($cursos) {
        $html .= '<div class="cabeca-secao espaco-topo"><span class="sobretitulo">Cursos</span><h2 id="cursos">Os nossos cursos</h2><p>Programa, módulos e temas de cada curso, com as próximas sessões e a inscrição.</p></div><div class="lista-cursos">';
        foreach ($cursos as $c) {
            $n = count(modulos_curso((int) $c['id']));
            $html .= '<a class="cartao-curso" href="' . h(link_curso_publico($c)) . '"><span class="sobretitulo">' . h($c['tipologia'] ?: 'Curso') . '</span><h3>' . h($c['nome']) . '</h3>'
                . ($c['descricao'] ? '<p>' . h(resumir((string) $c['descricao'], 170)) . '</p>' : '')
                . '<span class="texto-suave">' . h(implode(' · ', array_filter([$n ? $n . ($n === 1 ? ' módulo' : ' módulos') : '', (string) $c['duracao']]))) . '</span><span class="mais">Ver o curso</span></a>';
        }
        $html .= '</div>';
    }
    $html .= '</div></section>';
    return ['conteudo' => $html, 'jsonld' => jsonld_formacoes($formacoes)];
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
          <div class="acoes">' . ($id ? '<a class="botao" href="/formacoes/inscricao.php?f=' . $id . '">Inscrever outra pessoa</a>' : '') . '<a class="botao botao-secundario" href="/formacoes/">Voltar ao calendário</a></div>
        </div></section>'];
    }
    $f = $id ? linha_bd('SELECT * FROM formacoes WHERE id = ?', [$id]) : null;
    if (!$f || $f['estado'] !== 'aberta' || strtotime($f['inicio']) < strtotime('today')) {
        http_response_code($f ? 410 : 404);
        return ['conteudo' => '<section class="secao"><div class="contentor estreito"><div class="aviso aviso-info">Esta sessão não existe ou já não aceita inscrições.</div><div class="acoes"><a class="botao" href="/formacoes/">Ver as próximas sessões</a></div></div></section>'];
    }
    $restantes = max(0, (int) $f['vagas'] - inscritos((int) $f['id']));
    if ($restantes === 0) {
        return ['conteudo' => '<section class="secao"><div class="contentor estreito"><div class="aviso aviso-info">Esta sessão está esgotada.</div><div class="acoes"><a class="botao" href="/formacoes/">Ver outras sessões</a><a class="botao botao-secundario" href="/contactos/#formulario">Pedir lista de espera</a></div></div></section>'];
    }

    $cfg = cfg();
    $erros = [];
    $v = ['empresa' => '', 'nif' => '', 'contacto_nome' => '', 'contacto_email' => '', 'contacto_telefone' => '', 'eu' => false,
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
            'empresa' => texto('empresa', 160), 'nif' => preg_replace('/\D+/', '', texto('nif', 20)),
            'contacto_nome' => texto('contacto_nome', 120), 'contacto_email' => texto('contacto_email', 160), 'contacto_telefone' => texto('contacto_telefone', 30),
            'eu' => !empty($_POST['eu']),
            'formando' => texto('formando', 120), 'email' => texto('email', 160), 'funcao' => texto('funcao', 120),
            'expectativas' => texto('expectativas', 1500, true),
            'ferramentas' => com_diagnostico($f) ? array_values(array_intersect(FERRAMENTAS_IA, array_filter((array) ($_POST['ferramentas'] ?? []), 'is_string'))) : [],
            'diag' => array_filter(array_intersect_key((array) ($_POST['diag'] ?? []), DIAGNOSTICO), 'is_string'),
        ];
        if ($v['eu']) {
            $v['formando'] = $v['formando'] ?: $v['contacto_nome'];
            $v['email'] = $v['email'] ?: $v['contacto_email'];
        }
        $respostas = com_diagnostico($f) ? respostas_diagnostico() : [];
        if (!token_valido(is_string($_POST['t'] ?? null) ? $_POST['t'] : '')) {
            $erros[] = 'O formulário expirou ou foi enviado demasiado depressa. Confirme os dados e envie de novo.';
        }
        if ($v['empresa'] === '') {
            $erros[] = 'Indique a empresa.';
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
        <h2 class="mb-0">1. Empresa e contacto</h2>
        <div class="campos-2">
          ' . campo('empresa', 'Empresa *', $v['empresa'], 'text', 'required maxlength="160" autocomplete="organization"') . '
          ' . campo('nif', 'NIF da empresa', $v['nif'], 'text', 'maxlength="11" inputmode="numeric" pattern="[0-9 ]{9,11}"') . '
          ' . campo('contacto_nome', 'O seu nome *', $v['contacto_nome'], 'text', 'required maxlength="120" autocomplete="name"') . '
          ' . campo('contacto_email', 'O seu email *', $v['contacto_email'], 'email', 'required maxlength="160" autocomplete="email"') . '
          ' . campo('contacto_telefone', 'Telefone', $v['contacto_telefone'], 'tel', 'maxlength="30" autocomplete="tel"') . '
        </div>
        <h2 class="mb-0 espaco-topo">2. Formando</h2>
        <label class="opcao consentimento"><input type="checkbox" name="eu" value="1"' . ($v['eu'] ? ' checked' : '') . '> <span>O formando sou eu (pode deixar os campos seguintes em branco)</span></label>
        <div class="campos-2">
          ' . campo('formando', 'Nome completo do formando', $v['formando'], 'text', 'maxlength="120"') . '
          ' . campo('email', 'Email profissional do formando', $v['email'], 'email', 'maxlength="160"') . '
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
        'conteudo' => '<section class="topo-pagina"><div class="contentor"><h1>Curso não encontrado</h1><p class="lead">Este curso não existe ou já não está disponível.</p></div></section><section class="secao"><div class="contentor"><div class="acoes"><a class="botao" href="/formacoes/">Ver o calendário de formações</a></div></div></section>'];
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
    $seccao = function (string $id, string $titulo, string $corpo) {
        return $corpo === '' ? '' : '<h2 class="espaco-topo" id="' . $id . '">' . h($titulo) . '</h2>' . $corpo;
    };
    $html = '<section class="topo-pagina"><div class="contentor">
        <nav class="migalhas" aria-label="Localização"><ol><li><a href="/">Início</a></li><li><a href="/formacoes/">Calendário de formações</a></li><li aria-current="page">' . h($c['nome']) . '</li></ol></nav>
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
            ' . ($c['diagnostico'] ? '<p class="texto-suave">A inscrição inclui um diagnóstico curto de utilização de inteligência artificial, para ajustar a formação ao ponto de partida de cada formando.</p>' : '') . '
            <div class="ficha-acoes">
              <a class="botao" href="#sessoes">Ver datas e inscrever</a>
              <a class="botao botao-secundario" href="' . h(link_ficha($c, true)) . '" target="_blank" rel="noopener">Exportar PDF</a>
              <button class="botao botao-secundario" type="button" data-copiar="' . h($link) . '">Copiar ligação</button>
              <p class="estado-copia" role="status" aria-live="polite"></p>
            </div>
          </aside>
        </div>
        <div class="cabeca-secao espaco-topo"><span class="sobretitulo">Calendário</span><h2 id="sessoes">Próximas sessões</h2></div>
        ' . ($sessoes ? calendario($sessoes, 'botao_inscrever_publico', true)
            : '<div class="aviso aviso-info">Não há sessões agendadas deste curso neste momento. Para saber as próximas datas, fazer uma pré-inscrição ou organizar uma sessão para a sua equipa, <a href="/contactos/#formulario">contacte-nos</a>.</div>') . '
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
        'descricao' => resumir((string) ($c['descricao'] ?: $c['nome'] . ': programa, módulos, temas e próximas sessões.'), 155),
        'canonical' => SITE_CANONICO . link_curso_publico($c),
        'jsonld' => "  <script type=\"application/ld+json\">\n" . $json . "\n  </script>\n",
        'conteudo' => $html,
    ];
}
