<?php
// Certificados de frequência: ficha do formando para o certificado (nome completo e número e validade do Cartão de
// Cidadão, preenchidos na área do formando ou pela administração), pedido dos dados por email, emissão com número
// sequencial por ano ("001/2026"), PDF no modelo da Gadgetmetodo (A4 horizontal, com o painel do programa à esquerda)
// e envio por email com o PDF em anexo. O formando descarrega os seus certificados na área do formando.

declare(strict_types=1);

const CERTIFICADO_LOCAL = 'Porto';
const CERTIFICADO_FORMADOR = 'Jorge Manuel de Sá Silva Couto';
const CERTIFICADO_FORMADOR_DADOS = 'Formador  |  CCP n.º F625978/2014 (IEFP)';
const CERTIFICADO_ENTIDADE = 'Gadgetmetodo, Lda.';

// Utilizadores que podem ser formadores de uma edição (ativos, perfis Gadgetmetodo e Formador), por id
function formadores(): array
{
    static $lista = null;
    if ($lista === null) {
        $lista = [];
        foreach (consulta("SELECT id, nome, ccp FROM utilizadores WHERE ativo = 1 AND papel IN ('admin', 'formador') ORDER BY nome COLLATE NOCASE") as $u) {
            $lista[(int) $u['id']] = $u;
        }
    }
    return $lista;
}

// [nome, linha "Formador | CCP n.º …"] do formador da edição; sem formador indicado, os de CERTIFICADO_FORMADOR*
function formador_certificado(int $formacao_id): array
{
    $u = linha_bd('SELECT u.nome, u.ccp FROM formacoes f JOIN utilizadores u ON u.id = f.formador_id WHERE f.id = ?', [$formacao_id]);
    if (!$u) {
        return [CERTIFICADO_FORMADOR, CERTIFICADO_FORMADOR_DADOS];
    }
    return [(string) $u['nome'], 'Formador' . (trim((string) $u['ccp']) !== '' ? '  |  CCP n.º ' . trim((string) $u['ccp']) : '')];
}

/* Ficha do formando para o certificado */

function ficha_certificado(string $email): ?array
{
    return trim($email) === '' ? null : linha_bd('SELECT * FROM fichas_certificado WHERE ' . sprintf(SQL_EMAIL_IGUAL, 'email'), [$email]);
}

function ficha_completa(?array $f): bool
{
    return $f && trim((string) $f['nome_completo']) !== '' && trim((string) $f['cc_numero']) !== '' && trim((string) $f['cc_validade']) !== '';
}

// Número do documento sem espaços nem separadores, em maiúsculas
function numero_cc_normalizado(string $s): string
{
    return strtoupper((string) preg_replace('/[\s.\-\/]+/', '', $s));
}

// Aceita o número de identificação civil (8 algarismos, com ou sem o algarismo de controlo) ou o número completo do
// documento (12 carateres, como 12345678 9 ZZ1), validado pelo algarismo de controlo
function numero_cc_valido(string $n): bool
{
    if (preg_match('/^\d{8,9}$/', $n)) {
        return true;
    }
    if (!preg_match('/^\d{9}[A-Z0-9]{2}\d$/', $n)) {
        return false;
    }
    $soma = 0;
    $dobrar = false;
    for ($k = strlen($n) - 1; $k >= 0; $k--) {
        $c = $n[$k];
        $v = ctype_digit($c) ? (int) $c : ord($c) - 55;
        if ($dobrar) {
            $v *= 2;
            $v -= $v > 9 ? 9 : 0;
        }
        $soma += $v;
        $dobrar = !$dobrar;
    }
    return $soma % 10 === 0;
}

// "12345678 9 ZZ1" (número completo do documento) ou "12345678 9"
function numero_cc_formatado(string $n): string
{
    if (strlen($n) === 12) {
        return substr($n, 0, 8) . ' ' . $n[8] . ' ' . substr($n, 9);
    }
    return strlen($n) === 9 ? substr($n, 0, 8) . ' ' . $n[8] : $n;
}

function data_curta(string $data): string
{
    return date('d/m/Y', strtotime($data));
}

// Dados da ficha a partir do formulário: [dados, erros]
function ler_ficha_certificado(): array
{
    $nome = trim((string) preg_replace('/\s+/u', ' ', texto('nome_completo', 160)));
    $cc = numero_cc_normalizado(texto('cc_numero', 30));
    $validade = texto('cc_validade', 10);
    $erros = [];
    if (count(explode(' ', $nome)) < 2 || mb_strlen($nome) < 5) {
        $erros['nome_completo'] = 'Indique o nome completo, tal como consta do Cartão de Cidadão.';
    }
    if (!numero_cc_valido($cc)) {
        $erros['cc_numero'] = 'Confirme o número do Cartão de Cidadão: indique os 8 algarismos do número de identificação civil ou o número completo do documento (por exemplo, 12345678 9 ZZ1).';
    }
    $t = preg_match('/^\d{4}-\d{2}-\d{2}$/', $validade) ? strtotime($validade) : false;
    if (!$t || date('Y-m-d', $t) !== $validade) {
        $erros['cc_validade'] = 'Indique a data de validade do Cartão de Cidadão.';
    } elseif ($validade < date('Y-m-d')) {
        $erros['cc_validade'] = 'O Cartão de Cidadão indicado já expirou. Indique os dados do cartão válido.';
    } elseif ($t > strtotime('+15 years')) {
        $erros['cc_validade'] = 'Confirme a data de validade do Cartão de Cidadão.';
    }
    return [['nome_completo' => $nome, 'cc_numero' => $cc, 'cc_validade' => $validade], $erros];
}

function gravar_ficha_certificado(string $email, array $d, int $por): void
{
    $atual = ficha_certificado($email);
    if ($atual) {
        executar('UPDATE fichas_certificado SET nome_completo = ?, cc_numero = ?, cc_validade = ?, atualizado = ?, atualizado_por = ? WHERE id = ?',
            [$d['nome_completo'], $d['cc_numero'], $d['cc_validade'], agora(), $por, $atual['id']]);
    } else {
        executar('INSERT INTO fichas_certificado (email, nome_completo, cc_numero, cc_validade, atualizado, atualizado_por) VALUES (?, ?, ?, ?, ?, ?)',
            [trim($email), $d['nome_completo'], $d['cc_numero'], $d['cc_validade'], agora(), $por]);
    }
}

// Campos da ficha (nome completo, número e validade do CC), com os erros de cada campo
function campos_ficha_certificado(array $v, array $erros): string
{
    $erro = function (string $c) use ($erros) {
        return isset($erros[$c]) ? '<p class="erro-campo">' . h($erros[$c]) . '</p>' : '';
    };
    return '<div class="campos-2">'
        . '<div>' . campo('nome_completo', 'Nome completo *', (string) ($v['nome_completo'] ?? ''), 'text', 'required maxlength="160" autocomplete="name"') . $erro('nome_completo') . '</div>'
        . '<div>' . campo('cc_numero', 'N.º do Cartão de Cidadão *', numero_cc_formatado((string) ($v['cc_numero'] ?? '')), 'text', 'required maxlength="20" autocomplete="off" placeholder="12345678 9 ZZ1"') . $erro('cc_numero') . '</div>'
        . '<div>' . campo('cc_validade', 'Válido até *', (string) ($v['cc_validade'] ?? ''), 'date', 'required min="' . date('Y-m-d') . '"') . $erro('cc_validade') . '</div>'
        . '</div>';
}

/* Contexto do certificado de uma inscrição */

// Curso, programa, percurso, sessões e presenças de uma inscrição (linha de SQL_INSCRICAO)
function contexto_certificado(array $i): array
{
    $sessoes = sessoes_formacao_cache((int) $i['formacao_id']);
    $curso = !empty($i['curso_id']) ? linha_bd('SELECT * FROM cursos WHERE id = ?', [(int) $i['curso_id']]) : null;
    $nome = trim((string) preg_replace('/^Curso\s+\d+\s*[:.\x{2013}-]\s*/u', '', (string) ($curso['nome'] ?? $i['titulo'])));
    $posicao = '';
    if ($curso && preg_match('/Curso\s+(\d+)\s+de\s+(\d+)/u', (string) $curso['descricao'], $m)) {
        $posicao = 'Curso ' . $m[1] . ' de ' . $m[2];
    }
    $ultima = $sessoes ? $sessoes[count($sessoes) - 1] : null;
    $fim = $ultima ? strtotime($ultima['fim'] !== '' ? $ultima['fim'] : $ultima['inicio']) : 0;
    return [
        'curso' => $nome,
        'programa' => $curso ? array_column(consulta('SELECT nome FROM modulos WHERE curso_id = ? ORDER BY ordem, id', [(int) $curso['id']]), 'nome') : [],
        'percurso' => trim((string) ($curso['percurso'] ?? '')),
        'posicao' => $posicao,
        'sessoes' => count($sessoes),
        'inicio' => $sessoes ? $sessoes[0]['inicio'] : (string) $i['inicio'],
        'fim' => $ultima ? $ultima['inicio'] : (string) $i['inicio'],
        'terminada' => $fim > 0 && $fim <= time(),
        'presencas' => count(presencas_inscricao((int) $i['id'])),
    ];
}

function certificado_da_inscricao(int $inscricao_id): ?array
{
    return linha_bd('SELECT * FROM certificados WHERE inscricao_id = ?', [$inscricao_id]);
}

function numero_certificado(array $c): string
{
    return sprintf('%03d/%d', (int) $c['numero'], (int) $c['ano']);
}

// Emite (ou volta a emitir, com o mesmo número) o certificado de uma inscrição com os dados atuais da ficha e das presenças
function emitir_certificado(array $i, array $ficha): array
{
    $ctx = contexto_certificado($i);
    $dados = array_merge([$ficha['nome_completo'], $ficha['cc_numero'], $ficha['cc_validade'], $ctx['presencas'], $ctx['sessoes']], formador_certificado((int) $i['formacao_id']));
    $atual = certificado_da_inscricao((int) $i['id']);
    if ($atual) {
        executar('UPDATE certificados SET emitido = ?, nome = ?, cc_numero = ?, cc_validade = ?, presencas = ?, sessoes = ?, formador = ?, formador_dados = ? WHERE id = ?', array_merge([agora()], $dados, [$atual['id']]));
    } else {
        $pdo = bd();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $ano = (int) date('Y');
            $n = (int) (linha_bd('SELECT COALESCE(MAX(numero), 0) + 1 AS n FROM certificados WHERE ano = ?', [$ano])['n'] ?? 1);
            executar('INSERT INTO certificados (inscricao_id, ano, numero, emitido, nome, cc_numero, cc_validade, presencas, sessoes, formador, formador_dados) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge([(int) $i['id'], $ano, $n, agora()], $dados));
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }
    return certificado_da_inscricao((int) $i['id']);
}

function nome_ficheiro_certificado(array $c): string
{
    $nome = iconv('UTF-8', 'ASCII//TRANSLIT', (string) $c['nome']) ?: 'formando';
    $nome = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $nome), '_');
    return 'Certificado_' . sprintf('%03d-%d', (int) $c['numero'], (int) $c['ano']) . '_' . $nome . '.pdf';
}

/* PDF (clientes/inc/certificado-pdf.php, carregado só quando é preciso) */

// PDF do certificado (conteúdo). $c: linha de certificados; $i: linha de SQL_INSCRICAO.
function pdf_certificado(array $c, array $i): string
{
    require_once __DIR__ . '/certificado-pdf.php';
    return gerar_pdf_certificado($c, contexto_certificado($i));
}

// Envia o PDF para o navegador (descarga)
function descarregar_certificado(array $c, array $i): void
{
    $conteudo = pdf_certificado($c, $i);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . nome_ficheiro_certificado($c) . '"');
    header('Content-Length: ' . strlen($conteudo));
    echo $conteudo;
    exit;
}

/* Emails */

function variaveis_certificado(array $i, string $nome = ''): array
{
    return ['nome' => nome_curto($nome !== '' ? $nome : (string) $i['formando']), 'formando' => $nome !== '' ? $nome : $i['formando'], 'formacao' => $i['titulo'], 'horario' => horario($i)];
}

// Pede ao formando, por email, que complete a ficha na área do formando (cria o acesso, se ainda não o tiver)
function pedir_dados_certificado(array $i): bool
{
    $email = trim((string) $i['email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    garantir_acesso_formando($i);
    $u = utilizador_por_email($email);
    $link = endereco_site() . '/clientes/area.php?' . http_build_query(['p' => ($u['papel'] ?? '') === 'cliente' ? 'presencas' : 'painel']) . '#certificado';
    $ok = enviar_mensagem('dados_certificado', $email, variaveis_certificado($i), [
        '<p style="margin:0 0 4px;"><a href="' . h($link) . '" style="display:inline-block;padding:13px 26px;background:#0A0F26;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:999px;">Completar a ficha</a></p>',
        'Completar a ficha: ' . $link,
    ]);
    if ($ok) {
        executar('UPDATE inscricoes SET dados_pedidos = ? WHERE id = ?', [agora(), (int) $i['id']]);
    }
    return $ok;
}

function enviar_certificado(array $c, array $i): bool
{
    $email = trim((string) $i['email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $ok = enviar_mensagem('certificado', $email, variaveis_certificado($i, (string) $c['nome']), [
        '<p style="margin:0 0 14px;"><strong>Certificado n.º:</strong> ' . h(numero_certificado($c)) . '</p>',
        'Certificado n.º ' . numero_certificado($c),
    ], ['anexos' => [[nome_ficheiro_certificado($c), pdf_certificado($c, $i), 'application/pdf']]]);
    if ($ok) {
        executar('UPDATE certificados SET enviado = ? WHERE id = ?', [agora(), (int) $c['id']]);
    }
    return $ok;
}

/* Área do formando: ficha e certificados */

// Trata o envio da ficha na área do formando (devolve os erros, ou redireciona depois de gravar)
function tratar_ficha_formando(array $u): array
{
    [$d, $erros] = ler_ficha_certificado();
    if ($erros) {
        return [$d, $erros];
    }
    gravar_ficha_certificado((string) $u['email'], $d, (int) $u['id']);
    aviso('Dados para o certificado guardados. Obrigado.');
    redirecionar(rota_formando(), [], 'certificado');
}

// Bloco da área do formando: ficha para o certificado e certificados emitidos
function bloco_certificado_formando(array $u, array $inscricoes, array $valores = [], array $erros = []): string
{
    if (!$inscricoes) {
        return '';
    }
    $ficha = ficha_certificado((string) $u['email']);
    $v = $valores ?: ($ficha ?: ['nome_completo' => (string) $u['nome']]);
    $completa = ficha_completa($ficha);
    $emitidos = '';
    foreach ($inscricoes as $i) {
        $c = certificado_da_inscricao((int) $i['id']);
        if ($c) {
            $emitidos .= '<li><a href="' . h(url('certificado', ['i' => $i['id']])) . '">' . h($i['titulo']) . '</a> <span class="texto-suave">· n.º ' . h(numero_certificado($c)) . ', emitido a ' . h(data_hora($c['emitido'], false)) . '</span></li>';
        }
    }
    $aberta = !$completa || $erros;
    return '<section class="bloco-formando" id="certificado">
      <h2>Ficha para o certificado ' . ($completa ? etiqueta('Completa', 'ok') : etiqueta('Por completar', 'info')) . '</h2>
      ' . ($emitidos !== '' ? '<p><strong>Certificados emitidos</strong></p><ul class="lista-datas">' . $emitidos . '</ul>' : '') . '
      <details' . ($aberta ? ' open' : '') . '><summary>' . ($completa ? 'Ver ou corrigir os dados (' . h($ficha['nome_completo']) . ', CC n.º ' . h(numero_cc_formatado((string) $ficha['cc_numero'])) . ', válido até ' . h(data_curta((string) $ficha['cc_validade'])) . ')' : 'Preencher os dados para o certificado') . '</summary>
        <p class="texto-suave">Indique o nome completo e o número e a validade do Cartão de Cidadão, tal como devem constar do certificado de frequência. Estes dados servem apenas para emitir os seus certificados.</p>
        <form method="post" action="' . h(url(rota_formando())) . '#certificado" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="ficha_certificado">
          ' . campos_ficha_certificado($v, $erros) . '
          <div><button class="botao" type="submit">Guardar os dados</button></div>
        </form>
      </details>
    </section>';
}

// Descarga do certificado: o formando só o das suas inscrições; a administração, qualquer um
function pagina_certificado(): void
{
    $u = utilizador();
    $i = linha_bd(SQL_INSCRICAO, [(int) ($_GET['i'] ?? 0)]);
    $c = $i ? certificado_da_inscricao((int) $i['id']) : null;
    if (!$c || (!eh_admin() && !mesmo_email((string) $i['email'], (string) $u['email']))) {
        http_response_code(404);
        pagina_erro('Certificado não encontrado', 'O certificado pedido não existe ou ainda não foi emitido.');
    }
    descarregar_certificado($c, $i);
}

/* Administração: certificados de uma edição */

function tratar_certificados_edicao(array $f): void
{
    $id = (int) $f['id'];
    $acao = (string) ($_POST['acao'] ?? '');
    $escolhidas = array_map('intval', array_filter((array) ($_POST['cert'] ?? []), 'is_numeric'));
    $lista = [];
    foreach ($escolhidas as $iid) {
        $i = linha_bd(SQL_INSCRICAO, [$iid]);
        if ($i && (int) $i['formacao_id'] === $id && $i['estado'] === 'confirmada') {
            $lista[] = $i;
        }
    }
    if (!$lista) {
        aviso('Escolha os formandos.', 'erro');
        redirecionar('formacao', ['id' => $id], 'certificados');
    }
    if ($acao === 'cert_pedir') {
        $enviados = 0;
        $completas = 0;
        $sem = [];
        foreach ($lista as $i) {
            if (ficha_completa(ficha_certificado((string) $i['email']))) {
                $completas++;
            } elseif (pedir_dados_certificado($i)) {
                $enviados++;
            } else {
                $sem[] = (string) $i['formando'];
            }
        }
        aviso(($enviados ? 'Pedido dos dados enviado a ' . $enviados . ' formando(s).' : 'Nenhum pedido enviado.')
            . ($completas ? ' ' . $completas . ' formando(s) já com a ficha completa (sem pedido).' : '')
            . ($sem ? ' Sem envio (sem email ou falha no envio): ' . h(implode(', ', $sem)) . '.' : ''), $sem ? ($enviados ? 'info' : 'erro') : 'ok');
        redirecionar('formacao', ['id' => $id], 'certificados');
    }
    $enviar = $acao === 'cert_enviar';
    $emitidos = 0;
    $enviados = 0;
    $sem_ficha = [];
    $sem_presencas = [];
    $falhados = [];
    foreach ($lista as $i) {
        $ficha = ficha_certificado((string) $i['email']);
        if (!ficha_completa($ficha)) {
            $sem_ficha[] = (string) $i['formando'];
            continue;
        }
        if (!contexto_certificado($i)['presencas']) {
            $sem_presencas[] = (string) $i['formando'];
            continue;
        }
        $c = emitir_certificado($i, $ficha);
        $emitidos++;
        if ($enviar) {
            if (enviar_certificado($c, $i)) {
                $enviados++;
            } else {
                $falhados[] = (string) $i['formando'];
            }
        }
    }
    $partes = array_filter([
        $emitidos ? $emitidos . ' certificado(s) emitido(s)' . ($enviar ? ', ' . $enviados . ' enviado(s) por email' : '') . '.' : 'Nenhum certificado emitido.',
        $sem_ficha ? 'Sem a ficha completa: ' . implode(', ', $sem_ficha) . '.' : '',
        $sem_presencas ? 'Sem presenças registadas: ' . implode(', ', $sem_presencas) . '.' : '',
        $falhados ? 'Email não enviado: ' . implode(', ', $falhados) . '.' : '',
    ]);
    aviso(h(implode(' ', $partes)), $sem_ficha || $sem_presencas || $falhados ? ($emitidos ? 'info' : 'erro') : 'ok');
    redirecionar('formacao', ['id' => $id], 'certificados');
}

function certificados_edicao_html(array $f): string
{
    $lista = consulta("SELECT i.id FROM inscricoes i WHERE i.formacao_id = ? AND i.estado = 'confirmada' ORDER BY i.formando COLLATE NOCASE", [$f['id']]);
    if (!$lista) {
        return '';
    }
    $linhas = '';
    $terminada = false;
    $faltam = 0;
    foreach ($lista as $l) {
        $i = linha_bd(SQL_INSCRICAO, [$l['id']]);
        $ctx = contexto_certificado($i);
        $terminada = $ctx['terminada'];
        $ficha = ficha_certificado((string) $i['email']);
        $c = certificado_da_inscricao((int) $i['id']);
        if (ficha_completa($ficha)) {
            $dados = etiqueta('Completa', 'ok') . '<br><span class="texto-suave">' . h($ficha['nome_completo']) . '<br>CC ' . h(numero_cc_formatado((string) $ficha['cc_numero'])) . ', até ' . h(data_curta((string) $ficha['cc_validade'])) . '</span>';
        } else {
            $faltam++;
            $dados = etiqueta('Em falta', 'info') . ((string) $i['email'] === '' ? '<br><span class="texto-suave">Sem email: preencha na ficha do formando</span>'
                : ($i['dados_pedidos'] ? '<br><span class="texto-suave">Pedidos em ' . h(data_hora($i['dados_pedidos'], false)) . '</span>' : ''));
        }
        $cert = $c ? '<a href="' . h(url('certificado', ['i' => $i['id']])) . '" target="_blank" rel="noopener">N.º ' . h(numero_certificado($c)) . '</a><br><span class="texto-suave">Emitido a ' . h(data_hora($c['emitido'], false))
            . ($c['enviado'] ? '<br>Enviado a ' . h(data_hora($c['enviado'], false)) : '<br>Por enviar') . '</span>' : '<span class="texto-suave">Por emitir</span>';
        $linhas .= '<tr><td class="c-sessao"><input type="checkbox" name="cert[]" value="' . (int) $i['id'] . '" checked aria-label="' . h($i['formando']) . '"></td>'
            . '<td><a href="' . h(url('formando', ['i' => $i['id']])) . '#certificado">' . h($i['formando']) . '</a></td><td>' . $dados . '</td>'
            . '<td>' . $ctx['presencas'] . ' de ' . $ctx['sessoes'] . '</td><td>' . $cert . '</td></tr>';
    }
    return '<h2 class="espaco-topo" id="certificados">Certificados de frequência</h2>
      <p class="texto-suave">O certificado leva o nome completo e o número e a validade do Cartão de Cidadão da ficha do formando, preenchida na área do formando (ou, na ficha do formando, pela administração), e a assiduidade registada nas presenças.'
        . ($faltam ? ' ' . $faltam . ' formando(s) sem a ficha completa: use "Pedir os dados por email".' : '')
        . ($terminada ? '' : ' A edição ainda não terminou: confirme as presenças antes de emitir.') . ' Voltar a emitir um certificado mantém o número e atualiza os dados.</p>
      <form method="post">' . campo_csrf() . '
        <div class="conteudo tabela-rolante"><table class="tabela-presencas"><thead><tr><th class="c-sessao" scope="col"><span class="visualmente-oculto">Escolher</span></th><th scope="col">Formando</th><th scope="col">Dados para o certificado</th><th scope="col">Presenças</th><th scope="col">Certificado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
        <div class="espaco-topo acoes">
          <button class="botao botao-secundario" type="submit" name="acao" value="cert_pedir">Pedir os dados por email</button>
          <button class="botao botao-secundario" type="submit" name="acao" value="cert_emitir">Emitir (sem enviar)</button>
          <button class="botao" type="submit" name="acao" value="cert_enviar">Emitir e enviar por email</button>
        </div>
        <p class="ajuda">As ações aplicam-se aos formandos escolhidos. Só se emitem certificados com a ficha completa e pelo menos uma presença; o email leva o PDF em anexo e o formando pode descarregá-lo na área do formando.</p>
      </form>';
}

// Ficha do formando (administração): os dados para o certificado, editáveis
function ficha_certificado_admin_html(string $email, int $inscricao_base, array $valores = [], array $erros = []): string
{
    if (trim($email) === '') {
        return '<h2 class="espaco-topo" id="certificado">Dados para o certificado</h2><p class="texto-suave">Sem email: a ficha para o certificado fica associada ao email do formando. Acrescente o email na inscrição para a preencher.</p>';
    }
    $ficha = ficha_certificado($email);
    $v = $valores ?: ($ficha ?: []);
    return '<h2 class="espaco-topo" id="certificado">Dados para o certificado ' . (ficha_completa($ficha) ? etiqueta('Completa', 'ok') : etiqueta('Em falta', 'info')) . '</h2>
      <p class="texto-suave">' . ($ficha ? 'Atualizados em ' . h(data_hora($ficha['atualizado'])) . ((int) $ficha['atualizado_por'] === (int) (utilizador_por_email($email)['id'] ?? -1) ? ' pelo próprio formando.' : ' pela administração.') : 'O formando ainda não preencheu a ficha na área do formando. Pode preenchê-la aqui.') . '</p>
      <form method="post" action="' . h(url('formando', ['i' => $inscricao_base])) . '#certificado" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="ficha_certificado">
        ' . campos_ficha_certificado($v, $erros) . '
        <div><button class="botao botao-secundario" type="submit">Guardar os dados</button></div>
      </form>';
}
