<?php
// Comunicação por email, partilhada pela área reservada e pelo formulário de contacto: catálogo dos emails enviados,
// textos e destinatários configurados na área reservada (Configuração, Comunicação) e substituição das variáveis.
// Sem configuração guardada (ou sem base de dados), valem os textos deste ficheiro e os endereços do config.php.

declare(strict_types=1);

const GRUPOS_EMAIL = [
    'admin' => ['Emails para a administração', 'Avisos internos enviados para o email da administração, com a cópia oculta geral. Responder a um destes emails responde diretamente ao cliente ou candidato.'],
    'clientes' => ['Emails para clientes, formandos e candidatos', 'Enviados a quem faz a inscrição ou o pedido. As respostas vão para o email da administração.'],
    'utilizadores' => ['Emails para os utilizadores da área reservada', 'Enviados aos utilizadores criados em Configuração, Utilizadores.'],
];

// Variáveis que os textos podem usar, com a descrição e um exemplo (usado na pré-visualização e no email de teste)
const VARIAVEIS_EMAIL = [
    'nome' => ['Nome próprio do destinatário', 'Ana'],
    'nome_completo' => ['Nome completo da pessoa', 'Ana Martins'],
    'email' => ['Email da pessoa', 'ana.martins@exemplo.pt'],
    'formando' => ['Nome do formando', 'Ana Martins'],
    'cliente' => ['Cliente: empresa ou "Particular"', 'Empresa Exemplo, Lda.'],
    'formacao' => ['Curso da formação', 'Curso 1: Iniciação à IA Generativa no Trabalho'],
    'curso' => ['Nome do curso', 'Curso 1: Iniciação à IA Generativa no Trabalho'],
    'no_curso' => ['"no" e o nome do curso', 'no Curso 1: Iniciação à IA Generativa no Trabalho'],
    'data' => ['Data da 1.ª sessão (vazia num convite para o curso)', '03/11/2026'],
    'horario' => ['Datas e horário da edição', '6 sessões, de 3 de novembro a 8 de dezembro de 2026, 18:30 às 19:30'],
    'local' => ['Modalidade e local', 'Online'],
    'canal' => ['"pelo site" ou "na área reservada"', 'pelo site'],
    'estado' => ['Novo estado da inscrição', 'pendente'],
    'utilizador' => ['Utilizador que fez a ação', 'Rui Costa'],
    'acao' => ['"Nova pré-inscrição pelo site" ou "Pré-inscrição atualizada"', 'Nova pré-inscrição pelo site'],
    'tipo' => ['"Pedido de orçamento" ou "Pedido de informações"', 'Pedido de orçamento'],
    'tipo_pedido' => ['"pedido de orçamento" ou "pedido de informações"', 'pedido de orçamento'],
    'referencia' => ['Referência do pedido', 'GM-20261030-A1B2'],
];

// Emails enviados pelo site. Em cada um: grupo, nome, quando é enviado, os dados acrescentados pelo sistema entre o texto
// e o fecho, se pode ser desativado (só os automáticos), os campos editáveis, as variáveis e os textos iniciais.
// O texto e o fecho aceitam parágrafos (linha em branco) e **negrito**.
const EMAILS = [
    'inscricao_aviso' => [
        'grupo' => 'admin', 'nome' => 'Nova inscrição',
        'quando' => 'A cada inscrição feita na área reservada ou no site.',
        'dados' => 'Dados da inscrição e do diagnóstico, recomendação e botão para a inscrição na área reservada.',
        'desativavel' => true, 'variaveis' => ['formando', 'cliente', 'formacao', 'data', 'horario', 'local', 'canal'],
        'assunto' => 'Nova inscrição {canal}: {formando} ({cliente}), {data}',
        'titulo' => 'Nova inscrição em formação',
        'texto' => 'Foi registada uma nova inscrição, pendente de confirmação.',
        'fecho' => '',
    ],
    'preinscricao_aviso' => [
        'grupo' => 'admin', 'nome' => 'Nova pré-inscrição',
        'quando' => 'A cada pré-inscrição feita no site e quando o candidato atualiza uma pré-inscrição por tratar.',
        'dados' => 'Dados da pré-inscrição e do diagnóstico, aviso quando o curso indicado é outro e botão para a área reservada.',
        'desativavel' => true, 'variaveis' => ['acao', 'nome_completo', 'cliente', 'curso', 'email'],
        'assunto' => '{acao}: {nome_completo} ({cliente}), {curso}',
        'titulo' => 'Nova pré-inscrição em curso',
        'texto' => 'Foi registada uma pré-inscrição. Está em Inscrições, na área reservada, com o estado "Pré-inscrição": aí pode inscrever o candidato numa edição já agendada ou numa edição nova, definindo as datas.',
        'fecho' => '',
    ],
    'cancelamento_aviso' => [
        'grupo' => 'admin', 'nome' => 'Inscrição cancelada pelo cliente',
        'quando' => 'Quando um utilizador de um cliente cancela uma inscrição na área reservada.',
        'dados' => '',
        'desativavel' => true, 'variaveis' => ['utilizador', 'formando', 'cliente', 'formacao', 'data', 'horario'],
        'assunto' => 'Inscrição cancelada pelo cliente: {formando} ({cliente})',
        'titulo' => 'Inscrição cancelada pelo cliente',
        'texto' => '{utilizador} cancelou a inscrição de **{formando}** ({cliente}) na formação {formacao} de {horario}.',
        'fecho' => '',
    ],
    'pedido_aviso' => [
        'grupo' => 'admin', 'nome' => 'Pedido pelo formulário de contacto',
        'quando' => 'A cada pedido de orçamento ou de informações feito em Contactos, no site. Não pode ser desativado: é o próprio pedido.',
        'dados' => 'O email tem o grafismo do formulário de contacto, com os dados e a mensagem do pedido; só o assunto é editável.',
        'desativavel' => false, 'campos' => ['assunto', 'copia'], 'variaveis' => ['tipo', 'cliente', 'nome_completo', 'email', 'referencia'],
        'assunto' => '{tipo}: {cliente} ({nome_completo}) [{referencia}]',
    ],
    'inscricao_recebida' => [
        'grupo' => 'clientes', 'nome' => 'Inscrição recebida',
        'quando' => 'Logo a seguir a cada inscrição, para quem a fez (utilizador do cliente ou pessoa que se inscreveu no site).',
        'dados' => 'Datas das sessões, nível do diagnóstico, curso indicado e botão para a inscrição ou para a página do curso.',
        'desativavel' => true, 'variaveis' => ['nome', 'formando', 'cliente', 'formacao', 'data', 'horario', 'local'],
        'assunto' => 'Inscrição recebida: {formando}, {formacao} de {data}',
        'titulo' => 'Inscrição recebida',
        'texto' => "{nome},\n\nRecebemos a inscrição de **{formando}** na formação {formacao}, em {horario} ({local}). A Gadgetmetodo confirma a inscrição por email nos próximos dias úteis.",
        'fecho' => 'Para alterar ou cancelar a inscrição, basta responder a este email.',
    ],
    'inscricao_confirmada' => [
        'grupo' => 'clientes', 'nome' => 'Inscrição confirmada',
        'quando' => 'Quando a Gadgetmetodo confirma a inscrição com a opção "Avisar por email", também ao inscrever a partir de uma pré-inscrição. Com cópia para o formando.',
        'dados' => 'Datas das sessões, local e ligação para as sessões online.',
        'desativavel' => false, 'variaveis' => ['nome', 'formando', 'cliente', 'formacao', 'data', 'horario', 'local'],
        'assunto' => 'Inscrição confirmada: {formando}, {data}',
        'titulo' => 'Inscrição confirmada',
        'texto' => "{nome},\n\nA inscrição de {formando} na formação {formacao}, em {horario}, está confirmada.",
        'fecho' => 'Para qualquer questão, basta responder a este email.',
    ],
    'inscricao_estado' => [
        'grupo' => 'clientes', 'nome' => 'Inscrição atualizada',
        'quando' => 'Quando a Gadgetmetodo passa a inscrição a Pendente ou Cancelada com a opção "Avisar por email".',
        'dados' => 'Datas das sessões.',
        'desativavel' => false, 'variaveis' => ['nome', 'formando', 'cliente', 'formacao', 'data', 'horario', 'estado'],
        'assunto' => 'Inscrição atualizada: {formando}, {data}',
        'titulo' => 'Inscrição atualizada',
        'texto' => "{nome},\n\nA inscrição de {formando} na formação {formacao}, em {horario}, passou ao estado: {estado}.",
        'fecho' => 'Para qualquer questão, basta responder a este email.',
    ],
    'reuniao' => [
        'grupo' => 'clientes', 'nome' => 'Ligação para as sessões online',
        'quando' => 'Botão "Enviar a ligação" na ficha da formação: a cada inscrição confirmada, para o formando, com cópia para quem o inscreveu.',
        'dados' => 'Ligação e botão para entrar na sessão.',
        'desativavel' => false, 'variaveis' => ['nome', 'formando', 'formacao', 'data', 'horario'],
        'assunto' => 'Ligação para as sessões online: {formacao}, {data}',
        'titulo' => 'Ligação para as sessões online',
        'texto' => "{nome},\n\nSegue a ligação para as sessões online da formação {formacao}, em {horario} (inscrição de {formando}).",
        'fecho' => 'Recomendamos entrar alguns minutos antes da hora de início. Para qualquer questão, basta responder a este email.',
    ],
    'preinscricao_recebida' => [
        'grupo' => 'clientes', 'nome' => 'Pré-inscrição recebida',
        'quando' => 'Logo a seguir a cada pré-inscrição no site, para o candidato.',
        'dados' => 'Curso indicado pelo diagnóstico, dados da pré-inscrição e botões para o programa e a ficha do curso.',
        'desativavel' => true, 'variaveis' => ['nome', 'curso', 'no_curso'],
        'assunto' => 'Pré-inscrição recebida: {curso}',
        'titulo' => 'Pré-inscrição recebida',
        'texto' => "{nome},\n\nRecebemos a sua pré-inscrição {no_curso}.\n\nQuando houver datas, enviamos-lhe o convite para a inscrição, com a data, o horário e a modalidade de cada sessão. A pré-inscrição não o(a) obriga a inscrever-se.",
        'fecho' => 'Para alterar ou cancelar a pré-inscrição, basta responder a este email. Os dados são apagados automaticamente 12 meses após o registo.',
    ],
    'convite' => [
        'grupo' => 'clientes', 'nome' => 'Convite para formação',
        'quando' => 'Formulário "Enviar a um candidato" na ficha de um curso ou de uma edição. A mensagem escrita no formulário substitui o texto (a saudação mantém-se). Com cópia para a administração.',
        'dados' => 'Resumo do curso ou da edição e botão para a página do curso ou para a inscrição.',
        'desativavel' => false, 'variaveis' => ['nome', 'formacao', 'data'],
        'assunto' => 'Convite: {formacao}, {data}',
        'titulo' => 'Convite para formação',
        'texto' => "{nome},\n\nTemos o gosto de o(a) convidar para a formação abaixo. Na página indicada encontra o programa, os temas de cada sessão e as datas disponíveis, e pode fazer a inscrição diretamente.",
        'fecho' => '',
    ],
    'convite_preinscricao' => [
        'grupo' => 'clientes', 'nome' => 'Convite a pré-inscritos',
        'quando' => 'Botões "Enviar convite" nas pré-inscrições de um curso, quando há edições abertas. Com cópia para a administração.',
        'dados' => 'Resumo do curso, próximas edições e botão para a página do curso.',
        'desativavel' => false, 'variaveis' => ['nome', 'curso', 'no_curso'],
        'assunto' => 'Convite: {curso}',
        'titulo' => 'Convite para formação',
        'texto' => "{nome},\n\nFez a pré-inscrição {no_curso}. Já há datas agendadas: escolha a edição que lhe convém e faça a inscrição na página do curso (a inscrição inclui todas as sessões). As vagas são limitadas e atribuídas por ordem de inscrição.",
        'fecho' => '',
    ],
    'pedido_confirmacao' => [
        'grupo' => 'clientes', 'nome' => 'Confirmação do pedido de contacto',
        'quando' => 'Logo a seguir a cada pedido feito em Contactos, no site, para quem o enviou.',
        'dados' => 'O email tem o grafismo do formulário de contacto: depois do texto vêm a ligação para o catálogo, o resumo do pedido e os próximos passos.',
        'desativavel' => true, 'variaveis' => ['nome', 'tipo_pedido', 'referencia'],
        'assunto' => 'Confirmação de receção do seu pedido · Ref. {referencia}',
        'titulo' => 'Obrigado pelo seu contacto.',
        'texto' => "Caro(a) {nome},\n\nAgradecemos o interesse nos serviços da Gadgetmetodo. Confirmamos a receção do seu {tipo_pedido}, que ficou registado com a referência **{referencia}**.\n\nUm membro da nossa equipa irá analisar o pedido e entrará em contacto consigo brevemente.",
        'fecho' => 'Se pretender acrescentar informação, basta responder a este email indicando a referência do pedido.',
    ],
    'acesso' => [
        'grupo' => 'utilizadores', 'nome' => 'Dados de acesso',
        'quando' => 'Opção "Enviar os dados de acesso por email" ao criar um utilizador ou ao gerar uma nova palavra-passe.',
        'dados' => 'Endereço da área reservada, email e palavra-passe, e botão para entrar.',
        'desativavel' => false, 'variaveis' => ['nome', 'nome_completo', 'email', 'cliente'],
        'assunto' => 'Acesso à área reservada da Gadgetmetodo',
        'titulo' => 'Acesso à área reservada',
        'texto' => "{nome},\n\nSeguem os dados para entrar na área reservada da Gadgetmetodo, onde pode consultar as formações, inscrever formandos e acompanhar as inscrições.",
        'fecho' => 'Por segurança, altere a palavra-passe no primeiro acesso, em "A minha conta". Se não esperava este email, responda a informar-nos.',
    ],
];

const CAMPOS_EMAIL = ['ativo', 'assunto', 'titulo', 'texto', 'fecho', 'copia'];

// Valores gerais iniciais: os do config.php (remetente, caixa da administração e cópia oculta)
function comunicacao_geral_inicial(array $cfg): array
{
    return [
        'remetente_nome' => (string) ($cfg['remetente_nome'] ?? 'Gadgetmetodo, Lda.'),
        'email_admin' => (string) ($cfg['destinatario'] ?? 'info@gadgetmetodo.pt'),
        'bcc' => (string) ($cfg['bcc'] ?? ''),
        'cabecalho' => 'Formação · Gadgetmetodo',
        'assinatura' => "Com os melhores cumprimentos,\nA equipa da Gadgetmetodo",
        'rodape' => 'Gadgetmetodo, Lda. · Rua das Cruzes, 625 · 4100-195 Porto · info@gadgetmetodo.pt · www.gadgetmetodo.pt',
    ];
}

// Configuração guardada na tabela "definicoes" (vazia se a área reservada ainda não foi usada)
function comunicacao_guardada(?PDO $pdo): array
{
    if (!$pdo) {
        return [];
    }
    try {
        $st = $pdo->prepare('SELECT valor FROM definicoes WHERE chave = ?');
        $st->execute(['comunicacao']);
        $v = json_decode((string) $st->fetchColumn(), true);
        return is_array($v) ? $v : [];
    } catch (Throwable $e) {
        return [];
    }
}

// Configuração em vigor: valores iniciais com o que foi alterado por cima; memória por pedido
function config_comunicacao(?PDO $pdo, array $cfg, bool $renovar = false): array
{
    static $cache = null;
    if ($cache !== null && !$renovar) {
        return $cache;
    }
    $guardada = comunicacao_guardada($pdo);
    $geral = array_merge(comunicacao_geral_inicial($cfg), array_intersect_key((array) ($guardada['geral'] ?? []), comunicacao_geral_inicial($cfg)));
    if (!filter_var($geral['email_admin'], FILTER_VALIDATE_EMAIL)) {
        $geral['email_admin'] = comunicacao_geral_inicial($cfg)['email_admin'];
    }
    $emails = [];
    foreach (EMAILS as $tipo => $e) {
        $emails[$tipo] = array_merge(email_inicial($tipo), array_intersect_key((array) ($guardada['emails'][$tipo] ?? []), array_flip(CAMPOS_EMAIL)));
        $emails[$tipo]['ativo'] = !empty($emails[$tipo]['ativo']) || empty($e['desativavel']);
    }
    return $cache = ['geral' => $geral, 'emails' => $emails, 'alterados' => array_keys(array_intersect_key((array) ($guardada['emails'] ?? []), EMAILS))];
}

function email_inicial(string $tipo): array
{
    $e = EMAILS[$tipo];
    return ['ativo' => true, 'assunto' => $e['assunto'], 'titulo' => $e['titulo'] ?? '', 'texto' => $e['texto'] ?? '', 'fecho' => $e['fecho'] ?? '', 'copia' => ''];
}

// Valores de exemplo das variáveis de um email (pré-visualização e email de teste)
function exemplos_email(string $tipo): array
{
    $v = [];
    foreach (EMAILS[$tipo]['variaveis'] as $n) {
        $v[$n] = VARIAVEIS_EMAIL[$n][1];
    }
    return $v;
}

// Troca {variavel} pelo valor; sem parênteses vazios nem vírgulas soltas quando uma variável fica vazia
function substituir_variaveis(string $modelo, array $vars): string
{
    $r = preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars) {
        return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
    }, $modelo);
    return (string) preg_replace(['/[ \t]*\([ \t]*\)/u', '/,[ \t]*(?=[,.])/u'], '', $r);
}

// Assunto numa só linha, sem separadores soltos no fim (por exemplo "Convite: Curso 1," num convite sem data)
function assunto_email(string $modelo, array $vars): string
{
    return rtrim(trim((string) preg_replace('/\s+/u', ' ', substituir_variaveis($modelo, $vars))), " ,·:-");
}

// Variáveis usadas num texto que este email não conhece
function variaveis_desconhecidas(string $texto, string $tipo): array
{
    preg_match_all('/\{([a-z_]+)\}/', $texto, $m);
    return array_values(array_unique(array_diff($m[1], EMAILS[$tipo]['variaveis'])));
}

// Texto configurado em HTML: parágrafos separados por linha em branco, mudanças de linha e **negrito**
function html_paragrafos(string $texto, string $estilo = 'margin:0 0 14px;'): string
{
    $html = '';
    foreach (preg_split('/\n[ \t]*\n/u', trim(str_replace("\r\n", "\n", $texto))) ?: [] as $p) {
        if (trim($p) === '') {
            continue;
        }
        $p = htmlspecialchars(trim($p), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html .= '<p style="' . $estilo . '">' . nl2br((string) preg_replace('/\*\*(.+?)\*\*/us', '<strong>$1</strong>', $p), false) . '</p>';
    }
    return $html;
}

function texto_paragrafos(string $texto): string
{
    return trim((string) preg_replace(['/\*\*(.+?)\*\*/us', '/\n[ \t]*\n+/u'], ['$1', "\n\n"], str_replace("\r\n", "\n", $texto)));
}

// Lista de emails separados por vírgula, ponto e vírgula ou espaço; false se algum não for válido
function lista_emails(string $texto)
{
    $lista = array_values(array_filter(preg_split('/[\s,;]+/', trim($texto)) ?: [], 'strlen'));
    foreach ($lista as $e) {
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
    }
    return array_values(array_unique($lista));
}
