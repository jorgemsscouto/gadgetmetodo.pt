<?php
// Área de clientes: Configuração (só administração). Reúne os utilizadores, a tabela de preços, a comunicação por email
// (remetente, destinatários e textos de todos os emails enviados pelo site, definidos em formulario/comunicacao.php)
// e a tarefa agendada dos lembretes automáticos das sessões (clientes/inc/lembretes.php).

declare(strict_types=1);

function admin_configuracao(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'chave_agenda') {
        chave_agenda(true);
        aviso('Nova chave gerada para a agenda. O endereço anterior deixou de funcionar: volte a acrescentar a agenda no Google Calendar com o endereço novo.');
        redirecionar('configuracao', [], 'agenda');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'chave_lembretes') {
        chave_lembretes(true);
        aviso('Nova chave gerada. Atualize o endereço na tarefa agendada do alojamento: o endereço anterior deixou de funcionar.');
        redirecionar('configuracao', [], 'lembretes');
    }
    semear_precos();
    $n = function (string $sql) { return (int) (linha_bd($sql)['n'] ?? 0); };
    $com = comunicacao();
    $desativados = count(array_filter($com['emails'], function ($e) { return !$e['ativo']; }));
    $alterados = count($com['alterados']);
    $ultima_preco = (string) (linha_bd('SELECT MAX(atualizado) AS d FROM precos WHERE legado = 0')['d'] ?? '');
    $bcc = lista_emails($com['geral']['bcc']) ?: [];
    $tarefa = estado_tarefa_lembretes();
    $cartao = function (string $titulo, string $texto, array $factos, string $acoes) {
        return '<article class="cartao-config"><h2>' . h($titulo) . '</h2><p>' . h($texto) . '</p>'
            . '<ul>' . implode('', array_map(function ($f) { return '<li>' . $f . '</li>'; }, $factos)) . '</ul>'
            . '<div class="acoes-formulario">' . $acoes . '</div></article>';
    };
    pagina_area('configuracao', 'Configuração', '
      <p class="texto-suave">Definições gerais da área reservada e do site: quem tem acesso, a tabela de preços, os emails enviados a clientes, candidatos, utilizadores e à administração e os lembretes automáticos das sessões.</p>
      <div class="cartoes-config">
        ' . $cartao('Utilizadores', 'Acessos à área reservada: perfis Gadgetmetodo e Formador (veem e gerem tudo), Cliente (vê as formações e as inscrições do seu cliente) e Formando.', [
            '<strong>' . $n('SELECT COUNT(*) AS n FROM utilizadores WHERE ativo = 1') . '</strong> ativos: ' . $n("SELECT COUNT(*) AS n FROM utilizadores WHERE ativo = 1 AND papel IN ('admin', 'formador')") . ' da Gadgetmetodo (incluindo formadores) e ' . $n("SELECT COUNT(*) AS n FROM utilizadores WHERE ativo = 1 AND papel = 'cliente'") . ' de clientes',
            $n('SELECT COUNT(*) AS n FROM utilizadores WHERE ativo = 0') . ' inativos',
        ], '<a class="botao" href="' . h(url('utilizadores')) . '">Gerir utilizadores</a><a class="botao botao-secundario" href="' . h(url('utilizadores')) . '#novo">Novo utilizador</a>') . '
        ' . $cartao('Preços', 'Tabela de preços por curso e por modalidade, com os textos e as condições do documento em PDF.', [
            '<strong>' . $n('SELECT COUNT(*) AS n FROM precos WHERE legado = 0') . '</strong> linhas e ' . $n('SELECT COUNT(*) AS n FROM precos_colunas') . ' modalidades',
            'Última alteração: ' . ($ultima_preco ? h(data_hora($ultima_preco)) : 'sem alterações'),
        ], '<a class="botao" href="' . h(url('precos')) . '">Abrir a tabela</a><a class="botao botao-secundario" href="' . h(url('precos', ['pdf' => 1])) . '" target="_blank" rel="noopener">Exportar PDF</a>') . '
        ' . $cartao('Comunicação', 'Remetente, destinatários e textos de todos os emails enviados pelo site e pela área reservada.', [
            '<strong>' . count(EMAILS) . '</strong> emails: ' . $alterados . ' com textos alterados, ' . $desativados . ' desativados',
            'Avisos internos para ' . h($com['geral']['email_admin']) . ($bcc ? ', com cópia oculta para ' . h(implode(', ', $bcc)) : ''),
        ], '<a class="botao" href="' . h(url('comunicacao')) . '">Configurar emails</a>') . '
        ' . $cartao('Lembretes', 'Lembretes das sessões aos formandos confirmados: botão "Enviar lembrete" na lista de inscritos de cada edição, para a véspera ou para a sessão escolhida, e envio automático antes de cada sessão (1 hora, por omissão, ou outra antecedência por edição).', [
            'Envio automático: <strong>' . ($tarefa['ativo'] ? 'ativo' : 'desativado') . '</strong>',
            'Tarefa agendada: ' . ($tarefa['ultima'] ? 'última execução em ' . h(data_hora($tarefa['ultima'])) : 'ainda não executada'),
        ], '<a class="botao" href="#lembretes">Tarefa agendada</a><a class="botao botao-secundario" href="' . h(url('comunicacao', ['aberto' => 'lembrete_hora'])) . '#email-lembrete_hora">Textos dos lembretes</a>') . '
        ' . $cartao('Agenda Google', 'Todas as sessões dos cursos num endereço que o Google Calendar subscreve (Outros calendários, A partir do URL).', [
            '<strong>' . $n("SELECT COUNT(*) AS n FROM formacoes WHERE estado != 'cancelada' AND COALESCE(fim, inicio) >= '" . date('Y-m-d 00:00:00') . "'") . '</strong> edições futuras ou em curso',
        ], '<a class="botao" href="#agenda">Endereço e instruções</a>') . '
      </div>
      ' . bloco_tarefa_lembretes($tarefa) . bloco_agenda_google(), 'Gadgetmetodo');
}

// Instruções e endereço da tarefa agendada que envia os lembretes automáticos (clientes/lembretes.php)
function bloco_tarefa_lembretes(array $tarefa): string
{
    $endereco = endereco_tarefa_lembretes();
    $enviados = (int) (linha_bd("SELECT SUM(enviados) AS n FROM lembretes WHERE tipo = 'hora' AND criado >= ?", [date('Y-m-d H:i:s', time() - 30 * 86400)])['n'] ?? 0);
    $estado = !$tarefa['ativo'] ? 'O envio automático está desativado (email "Lembrete automático antes da sessão", em Comunicação).'
        : ($tarefa['recente'] ? 'A tarefa agendada está a funcionar: última execução em ' . data_hora($tarefa['ultima']) . '.'
            : ($tarefa['ultima'] ? 'A tarefa agendada não corre desde ' . data_hora($tarefa['ultima']) . ': confirme-a no painel do alojamento.' : 'A tarefa agendada ainda não foi executada: configure-a como indicado abaixo.'));
    return '<section class="partilha espaco-topo" id="lembretes">
      <h2 class="mt-0">Lembretes automáticos das sessões</h2>
      <p>Antes de cada sessão (1 hora antes, por omissão, ou a antecedência escolhida em cada edição), cada formando com inscrição confirmada recebe um lembrete com a hora, o local e a ligação da sessão. O alojamento só envia emails quando uma página é aberta, por isso é preciso uma tarefa agendada (cron) que abra o endereço abaixo a cada 10 minutos.</p>
      <div class="aviso ' . ($tarefa['ativo'] && $tarefa['recente'] ? 'aviso-ok' : 'aviso-info') . '">' . h($estado) . ' Lembretes automáticos enviados nos últimos 30 dias: ' . $enviados . '.</div>
      <div class="partilha-ligacao"><label class="visualmente-oculto" for="endereco-lembretes">Endereço da tarefa agendada</label><input id="endereco-lembretes" type="text" readonly value="' . h($endereco) . '"><button class="botao botao-secundario" type="button" data-copiar="' . h($endereco) . '">Copiar endereço</button></div>
      <p class="estado-copia" role="status" aria-live="polite"></p>
      <h3>Como configurar</h3>
      <ol class="lista-datas">
        <li>No painel de controlo do alojamento, abra as tarefas agendadas (Cron).</li>
        <li>Crie uma tarefa a cada 10 minutos (<code>*/10 * * * *</code>). Se o painel pedir um comando, use <code>wget -q -O /dev/null "' . h($endereco) . '"</code>; se pedir um endereço, cole o endereço acima.</li>
        <li>Volte a esta página depois de 10 minutos: o estado passa a mostrar a hora da última execução.</li>
      </ol>
      <p class="texto-suave">Se o plano de alojamento não tiver tarefas agendadas, pode usar um serviço externo de chamadas agendadas com o mesmo endereço. Sem tarefa agendada, os lembretes automáticos só seguem quando alguém abre a área reservada ou as páginas de formação na hora anterior à sessão. O endereço tem uma chave própria e só envia os lembretes que estiverem na hora; se for divulgado, gere uma nova chave e atualize a tarefa.</p>
      <form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="chave_lembretes"><button class="botao botao-secundario" type="submit">Gerar nova chave</button></form>
    </section>';
}

/* Comunicação */

// Versão em inglês de um email (para quem usa o site ou a área em inglês): os campos vazios usam a tradução automática
// dos textos em português (dicionário clientes/inc/traducoes/en.json)
function versao_inglesa_email(string $tipo, array $campos): string
{
    $guardados = (array) (comunicacao_guardada(bd())['emails_en'][$tipo] ?? []);
    $auto = com_idioma('en', function () use ($tipo) { return textos_email_en($tipo); });
    $id = 'e-' . $tipo . '-en-';
    $campo = function (string $c, string $rotulo, string $tipo_campo, string $extra) use ($guardados, $auto, $id) {
        $valor = (string) ($guardados[$c] ?? '');
        $html = $tipo_campo === 'textarea'
            ? '<textarea id="' . $id . $c . '" name="' . $c . '_en" ' . $extra . ' lang="en-GB" placeholder="' . h($auto[$c]) . '">' . h($valor) . '</textarea>'
            : '<input id="' . $id . $c . '" name="' . $c . '_en" type="text" value="' . h($valor) . '" ' . $extra . ' lang="en-GB" placeholder="' . h($auto[$c]) . '">';
        return '<div class="campo"><label for="' . $id . $c . '">' . $rotulo . '</label>' . $html . '</div>';
    };
    return '<details class="programa espaco-topo-curto"><summary>Versão em inglês' . ($guardados ? ' ' . etiqueta('Escrita à mão', 'info') : '') . '</summary>
      <form method="post" class="formulario" action="' . h(url('comunicacao', ['aberto' => $tipo])) . '#email-' . h($tipo) . '">' . campo_csrf() . '
        <input type="hidden" name="acao" value="email_en"><input type="hidden" name="tipo" value="' . h($tipo) . '">
        <p class="ajuda mt-0">Enviada a quem usa o site ou a área reservada em inglês. Em branco, cada campo usa a tradução automática do texto em português (mostrada a cinzento); se o texto em português tiver sido alterado e não houver tradução, usa a tradução do texto original. As variáveis são as mesmas.</p>
        ' . $campo('assunto', 'Subject', 'text', 'maxlength="200"')
        . (in_array('titulo', $campos, true) ? $campo('titulo', 'Title', 'text', 'maxlength="120"') : '')
        . (in_array('texto', $campos, true) ? $campo('texto', 'Text', 'textarea', 'maxlength="4000" rows="6"') : '')
        . (in_array('fecho', $campos, true) ? $campo('fecho', 'Closing', 'textarea', 'maxlength="1500" rows="2"') : '') . '
        <div class="acoes-formulario"><button class="botao botao-secundario" type="submit">Guardar a versão em inglês</button></div>
      </form>
      ' . ($guardados ? '<form method="post" action="' . h(url('comunicacao', ['aberto' => $tipo])) . '#email-' . h($tipo) . '">' . campo_csrf() . '<input type="hidden" name="acao" value="repor_en"><input type="hidden" name="tipo" value="' . h($tipo) . '"><button class="botao-texto" type="submit">Usar a tradução automática</button></form>' : '') . '
    </details>';
}

function guardar_comunicacao(array $guardada): void
{
    definir(bd(), 'comunicacao', json_encode($guardada, JSON_UNESCAPED_UNICODE));
    comunicacao(true);
}

function admin_comunicacao(): void
{
    $guardada = comunicacao_guardada(bd());
    $tipo = (string) ($_POST['tipo'] ?? '');
    $tipo = isset(EMAILS[$tipo]) ? $tipo : '';
    $erros = [];
    $env_geral = null;
    $env_email = [];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'geral') {
            $g = ['remetente_nome' => texto('remetente_nome', 80), 'email_admin' => texto('email_admin', 160), 'bcc' => texto('bcc', 400),
                  'cabecalho' => texto('cabecalho', 80), 'assinatura' => texto('assinatura', 400, true), 'rodape' => texto('rodape', 400, true)];
            if ($g['remetente_nome'] === '') {
                $erros[] = 'Indique o nome do remetente.';
            }
            if (!filter_var($g['email_admin'], FILTER_VALIDATE_EMAIL)) {
                $erros[] = 'Indique um email da administração válido.';
            }
            if (lista_emails($g['bcc']) === false) {
                $erros[] = 'A cópia oculta tem um email inválido (separe vários com vírgulas).';
            }
            if (!$erros) {
                $g['bcc'] = implode(', ', lista_emails($g['bcc']));
                $guardada['geral'] = $g;
                guardar_comunicacao($guardada);
                aviso('Remetente e destinatários guardados.');
                redirecionar('comunicacao', [], 'geral');
            }
            $env_geral = $g;
        } elseif ($acao === 'repor_geral') {
            unset($guardada['geral']);
            guardar_comunicacao($guardada);
            aviso('Remetente, destinatários, assinatura e rodapé repostos com os valores iniciais (os do config.php).');
            redirecionar('comunicacao', [], 'geral');
        } elseif ($acao === 'email' && $tipo) {
            $e = EMAILS[$tipo];
            $campos = $e['campos'] ?? CAMPOS_EMAIL;
            $d = comunicacao()['emails'][$tipo];
            $maximos = ['assunto' => [200, false], 'titulo' => [120, false], 'texto' => [4000, true], 'fecho' => [1500, true], 'copia' => [400, false]];
            foreach ($maximos as $c => [$max, $linhas]) {
                if (in_array($c, $campos, true)) {
                    $d[$c] = texto($c, $max, $linhas);
                }
            }
            $d['ativo'] = empty($e['desativavel']) || !empty($_POST['ativo']);
            if ($d['assunto'] === '') {
                $erros[] = 'Indique o assunto.';
            }
            $rotulos = ['assunto' => 'Assunto', 'titulo' => 'Título', 'texto' => 'Texto', 'fecho' => 'Fecho'];
            foreach ($rotulos as $c => $rotulo) {
                if ($desconhecidas = variaveis_desconhecidas((string) $d[$c], $tipo)) {
                    $erros[] = $rotulo . ': ' . implode(', ', array_map(function ($v) { return '{' . $v . '}'; }, $desconhecidas)) . (count($desconhecidas) > 1 ? ' não são variáveis' : ' não é uma variável') . ' deste email.';
                }
            }
            if (lista_emails((string) $d['copia']) === false) {
                $erros[] = 'A cópia oculta tem um email inválido (separe vários com vírgulas).';
            }
            if (!$erros) {
                $d['copia'] = implode(', ', lista_emails((string) $d['copia']));
                $guardada['emails'][$tipo] = array_intersect_key($d, array_flip(CAMPOS_EMAIL));
                guardar_comunicacao($guardada);
                aviso('Email "' . h($e['nome']) . '" guardado.');
                redirecionar('comunicacao', ['aberto' => $tipo], 'email-' . $tipo);
            }
            $env_email[$tipo] = $d;
        } elseif ($acao === 'email_en' && $tipo) {
            // Versão em inglês: guardam-se só os campos preenchidos; os vazios usam a tradução automática
            $en = [];
            foreach (['assunto' => [200, false], 'titulo' => [120, false], 'texto' => [4000, true], 'fecho' => [1500, true]] as $c => [$max, $linhas]) {
                $en[$c] = texto($c . '_en', $max, $linhas);
            }
            foreach (['assunto' => 'Subject', 'titulo' => 'Title', 'texto' => 'Text', 'fecho' => 'Closing'] as $c => $rotulo) {
                if ($desconhecidas = variaveis_desconhecidas($en[$c], $tipo)) {
                    $erros[] = 'Versão em inglês, ' . $rotulo . ': ' . implode(', ', array_map(function ($v) { return '{' . $v . '}'; }, $desconhecidas)) . ' não é variável deste email.';
                }
            }
            if (!$erros) {
                $guardada['emails_en'][$tipo] = array_filter($en, 'strlen');
                guardar_comunicacao($guardada);
                aviso('Versão em inglês do email "' . h(EMAILS[$tipo]['nome']) . '" guardada.');
                redirecionar('comunicacao', ['aberto' => $tipo], 'email-' . $tipo);
            }
        } elseif ($acao === 'repor_en' && $tipo) {
            unset($guardada['emails_en'][$tipo]);
            guardar_comunicacao($guardada);
            aviso('Versão em inglês do email "' . h(EMAILS[$tipo]['nome']) . '" reposta com a tradução automática.');
            redirecionar('comunicacao', ['aberto' => $tipo], 'email-' . $tipo);
        } elseif ($acao === 'repor' && $tipo) {
            unset($guardada['emails'][$tipo]);
            guardar_comunicacao($guardada);
            aviso('Email "' . h(EMAILS[$tipo]['nome']) . '" reposto com os textos originais.');
            redirecionar('comunicacao', ['aberto' => $tipo], 'email-' . $tipo);
        } elseif ($acao === 'teste' && $tipo) {
            $para = (string) utilizador()['email'];
            $nota = 'Email de teste com dados de exemplo. No envio real, aqui entram: ' . lcfirst(EMAILS[$tipo]['dados'] ?: 'os dados do envio.');
            $ok = enviar_mensagem($tipo, $para, exemplos_email($tipo), [
                '<p style="margin:0 0 14px;padding:14px 20px;color:#687280;background:#F2EDE6;border:1px dashed #C9CED8;border-radius:12px;">' . h($nota) . '</p>', '[' . $nota . ']',
            ], ['teste' => true]);
            aviso_envio($ok, 'Email de teste "' . h(EMAILS[$tipo]['nome']) . '" enviado para ' . h($para) . '.');
            redirecionar('comunicacao', ['aberto' => $tipo], 'email-' . $tipo);
        }
    }

    $com = comunicacao();
    $cfg = cfg();
    $smtp = $cfg['smtp'] ?? [];
    $envio = !empty($smtp['host'])
        ? 'servidor ' . h((string) $smtp['host']) . ', porta ' . (int) ($smtp['porta'] ?? 465) . (($smtp['seguranca'] ?? 'ssl') !== '' ? ' (' . h(strtoupper((string) $smtp['seguranca'])) . ')' : ' (sem cifra)')
        : 'função mail() do alojamento';
    $g = $env_geral ?? $com['geral'];

    $html = ($erros ? '<div class="aviso aviso-erro" role="alert">' . h(implode(' ', $erros)) . '</div>' : '') . '
      <p class="texto-suave">Todos os emails enviados pelo site e pela área reservada, agrupados por destinatário. Em cada um pode mudar o assunto, o título, o texto e o fecho; os dados próprios de cada envio (tabelas, datas, ligações e botões) são acrescentados pelo sistema. Os emails automáticos podem ser desativados.</p>
      <p class="texto-suave">Envio a partir de <strong>' . h((string) ($cfg['remetente_email'] ?? 'sem configuração')) . '</strong> (' . $envio . '). O endereço de envio e a palavra-passe da caixa ficam em <code>formulario/config.php</code> (<code>./configurar-formulario.sh</code>), fora da área reservada.</p>

      <h2 class="espaco-topo" id="geral">Remetente e destinatários</h2>
      <form method="post" class="formulario" action="' . h(url('comunicacao')) . '#geral">' . campo_csrf() . '<input type="hidden" name="acao" value="geral">
        <div class="campos-2">
          ' . campo('remetente_nome', 'Nome do remetente *', $g['remetente_nome'], 'text', 'required maxlength="80"') . '
          ' . campo('email_admin', 'Email da administração *', $g['email_admin'], 'email', 'required maxlength="160"') . '
          ' . campo('bcc', 'Cópia oculta dos avisos internos', $g['bcc'], 'text', 'maxlength="400" placeholder="Em branco: sem cópia"') . '
          ' . campo('cabecalho', 'Sobretítulo do cabeçalho dos emails', $g['cabecalho'], 'text', 'maxlength="80"') . '
          ' . campo('assinatura', 'Assinatura dos emails a clientes e candidatos', $g['assinatura'], 'textarea', 'maxlength="400" rows="3"') . '
          ' . campo('rodape', 'Rodapé dos emails da área reservada', $g['rodape'], 'textarea', 'maxlength="400" rows="3"') . '
        </div>
        <p class="ajuda">O email da administração recebe os avisos internos e as respostas de clientes e candidatos. A cópia oculta aceita vários emails separados por vírgulas. A assinatura também é usada na confirmação dos pedidos de contacto; em branco, os emails seguem sem assinatura.</p>
        <div class="acoes-formulario"><button class="botao" type="submit">Guardar</button></div>
      </form>
      ' . (isset($guardada['geral']) ? '<form method="post" class="espaco-topo-curto" action="' . h(url('comunicacao')) . '">' . campo_csrf() . '<input type="hidden" name="acao" value="repor_geral"><button class="botao-texto" type="submit">Repor os valores iniciais</button></form>' : '');

    foreach (GRUPOS_EMAIL as $grupo => [$titulo, $descricao]) {
        $html .= '<h2 class="espaco-topo" id="grupo-' . h($grupo) . '">' . h($titulo) . '</h2><p class="texto-suave">' . h($descricao) . '</p>';
        foreach (EMAILS as $t => $e) {
            if ($e['grupo'] === $grupo) {
                $html .= cartao_email($t, $env_email[$t] ?? $com['emails'][$t], isset($env_email[$t]) || ($_GET['aberto'] ?? '') === $t, in_array($t, $com['alterados'], true));
            }
        }
    }
    pagina_area('comunicacao', 'Comunicação', $html, 'Configuração');
}

// Um email do catálogo: quando é enviado, campos editáveis, variáveis, pré-visualização, teste e reposição dos textos
function cartao_email(string $tipo, array $d, bool $aberto, bool $alterado): string
{
    $e = EMAILS[$tipo];
    $campos = $e['campos'] ?? CAMPOS_EMAIL;
    $interno = $e['grupo'] === 'admin';
    $exemplo = exemplos_email($tipo);
    $form = function (string $acao, string $botao, string $classe) use ($tipo) {
        return '<form method="post" action="' . h(url('comunicacao', ['aberto' => $tipo])) . '#email-' . h($tipo) . '">' . campo_csrf()
            . '<input type="hidden" name="acao" value="' . $acao . '"><input type="hidden" name="tipo" value="' . h($tipo) . '"><button class="' . $classe . '" type="submit">' . $botao . '</button></form>';
    };
    $id = 'e-' . $tipo . '-';
    $variaveis = '';
    foreach ($e['variaveis'] as $v) {
        $variaveis .= '<dt>{' . h($v) . '}</dt><dd>' . h(VARIAVEIS_EMAIL[$v][0]) . ' <span class="texto-suave">(ex.: ' . h(VARIAVEIS_EMAIL[$v][1]) . ')</span></dd>';
    }
    $previa = '<p class="pre-assunto"><span>Assunto</span> ' . h(assunto_email((string) $d['assunto'], $exemplo)) . '</p>';
    if (in_array('texto', $campos, true)) {
        $previa .= ($d['titulo'] !== '' ? '<p class="pre-titulo">' . h(substituir_variaveis((string) $d['titulo'], $exemplo)) . '</p>' : '')
            . html_paragrafos(substituir_variaveis((string) $d['texto'], $exemplo))
            . ($e['dados'] ? '<p class="pre-dados">' . h($e['dados']) . '</p>' : '')
            . html_paragrafos(substituir_variaveis((string) $d['fecho'], $exemplo), 'margin:14px 0 0;')
            . ($interno ? '' : html_paragrafos(comunicacao()['geral']['assinatura'], 'margin:18px 0 0;'));
    } else {
        $previa .= '<p class="pre-dados">' . h($e['dados']) . '</p>';
    }

    return '<details class="cartao-email" id="email-' . h($tipo) . '"' . ($aberto ? ' open' : '') . '>
      <summary><strong>' . h($e['nome']) . '</strong>' . (!$d['ativo'] ? ' ' . etiqueta('Desativado', 'erro') : '') . ($alterado ? ' ' . etiqueta('Alterado', 'info') : '')
        . '<span class="texto-suave">' . h(assunto_email((string) $d['assunto'], $exemplo)) . '</span></summary>
      <div class="cartao-email-corpo">
        <p class="texto-suave mb-0"><strong>Quando:</strong> ' . h($e['quando']) . '</p>
        <form method="post" class="formulario" action="' . h(url('comunicacao', ['aberto' => $tipo])) . '#email-' . h($tipo) . '">' . campo_csrf() . '
          <input type="hidden" name="acao" value="email"><input type="hidden" name="tipo" value="' . h($tipo) . '">
          ' . (!empty($e['desativavel']) ? '<label class="opcao consentimento"><input type="checkbox" name="ativo" value="1"' . ($d['ativo'] ? ' checked' : '') . '> Enviar este email</label>' : '') . '
          ' . campo('assunto', 'Assunto *', (string) $d['assunto'], 'text', 'required maxlength="200"', $id . 'assunto') . '
          ' . (in_array('titulo', $campos, true) ? campo('titulo', 'Título (no cabeçalho do email)', (string) $d['titulo'], 'text', 'maxlength="120"', $id . 'titulo') : '') . '
          ' . (in_array('texto', $campos, true) ? campo('texto', 'Texto', (string) $d['texto'], 'textarea', 'maxlength="4000" rows="6"', $id . 'texto') : '') . '
          ' . (in_array('fecho', $campos, true) ? campo('fecho', 'Fecho (depois dos dados do envio)', (string) $d['fecho'], 'textarea', 'maxlength="1500" rows="2"', $id . 'fecho') : '') . '
          ' . campo('copia', 'Cópia oculta para' . ($interno ? ' (além da cópia oculta geral)' : ''), (string) $d['copia'], 'text', 'maxlength="400" placeholder="Em branco: sem cópia"', $id . 'copia') . '
          <div class="ajuda">' . (in_array('texto', $campos, true) ? '<p class="mt-0">Parágrafos separados por uma linha em branco; **texto** fica a negrito. Variáveis deste email:</p>' : '<p class="mt-0">Variáveis deste email:</p>') . '<dl class="variaveis-email">' . $variaveis . '</dl></div>
          <div class="acoes-formulario"><button class="botao" type="submit">Guardar</button></div>
        </form>
        <details class="programa"><summary>Pré-visualização com dados de exemplo</summary><div class="pre-email">' . $previa . '</div></details>
        ' . ($interno ? '' : versao_inglesa_email($tipo, $campos)) . '
        <div class="acoes-formulario espaco-topo-curto">' . $form('teste', 'Enviar um teste para ' . h((string) utilizador()['email']), 'botao botao-secundario botao-pequeno')
          . ($alterado ? $form('repor', 'Repor os textos originais', 'botao-texto') : '') . '</div>
      </div>
    </details>';
}

/* Certificados: campos pedidos para o certificado */

function admin_config_certificados(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $novo = [];
        foreach (CAMPOS_CERTIFICADO as $k => $c) {
            $v = (string) ($_POST['campo'][$k] ?? '');
            $novo[$k] = $c['fixo'] ? 'obrigatorio' : (in_array($v, ['obrigatorio', 'opcional', 'desligado'], true) ? $v : $c['omissao']);
        }
        definir(bd(), 'certificado_campos', (string) json_encode($novo));
        aviso('Campos para o certificado guardados. As fichas já preenchidas mantêm os dados; os campos obrigatórios novos passam a ser pedidos a quem ainda não os tiver.');
        redirecionar('certificados_config');
    }
    $config = config_campos_certificado();
    $linhas = '';
    foreach (CAMPOS_CERTIFICADO as $k => $c) {
        $linhas .= '<tr><th scope="row">' . h($c['rotulo']) . '</th><td>' . ($c['fixo'] ? 'Sempre obrigatório' : '<select name="campo[' . h($k) . ']" aria-label="' . h($c['rotulo']) . '">'
            . opcoes_select(['obrigatorio' => 'Obrigatório', 'opcional' => 'Opcional', 'desligado' => 'Não pedir'], $config[$k]) . '</select>') . '</td></tr>';
    }
    pagina_area('certificados_config', 'Dados para o certificado', '
      <p class="texto-suave">Campos pedidos ao formando na ficha para o certificado (área do formando, email da inscrição e ficha do formando). Recolha apenas o mínimo necessário (RGPD): por omissão, o nome completo e o documento de identificação (tipo, número e validade), que aparecem no certificado. O certificado só é emitido com todos os campos obrigatórios preenchidos.</p>
      <form method="post" class="formulario">' . campo_csrf() . '
        <div class="conteudo tabela-rolante"><table class="tabela-ficha"><tbody>' . $linhas . '</tbody></table></div>
        <div><button class="botao" type="submit">Guardar</button></div>
      </form>
      <p class="texto-suave espaco-topo">A assiduidade não tem um mínimo automático: conta a validação do formador, na ficha do formando (etapa Assiduidade).</p>', 'Configuração');
}
