<?php
// Menu "Formandos" (administração e formadores): uma linha por pessoa, pelo email, com tudo o que lhe diz respeito:
// pré-inscrições, inscrições (curso, edição, estado e presenças), diagnósticos, utilizador registado (Formando, Cliente
// que também é formando ou sem conta), dados para o certificado e certificados emitidos. Ações em lote: inscrever numa
// edição sem enviar emails, pedir os dados para o certificado e criar o acesso à área do formando.
// A ficha de cada pessoa é a ficha do formando (area.php?p=formando&e=email ou &i=inscrição).

declare(strict_types=1);

// Pessoas (formandos e candidatos), agrupadas pelo email; as inscrições sem email ficam cada uma como uma pessoa
function pessoas_formandos(): array
{
    $p = [];
    $chave = function (string $email, string $alt) {
        $e = mb_strtolower(trim($email));
        return $e !== '' ? 'e:' . $e : $alt;
    };
    $nova = function (string $nome, string $email) {
        return ['nome' => $nome, 'email' => trim($email), 'inscricoes' => [], 'pre' => [], 'diag' => [], 'cliente' => ''];
    };
    foreach (consulta(SQL_INSCRICOES_FORMANDO . ' ORDER BY f.inicio DESC, i.id DESC') as $i) {
        $k = $chave((string) $i['email'], 'i:' . (int) $i['id']);
        $p[$k] = $p[$k] ?? $nova((string) $i['formando'], (string) $i['email']);
        $p[$k]['inscricoes'][] = $i;
        $p[$k]['cliente'] = $p[$k]['cliente'] ?: (string) ($i['cliente_registado'] ?? '');
    }
    foreach (consulta('SELECT p.*, c.nome AS curso FROM preinscricoes p LEFT JOIN cursos c ON c.id = p.curso_id ORDER BY p.criado DESC') as $x) {
        $k = $chave((string) $x['email'], 'p:' . (int) $x['id']);
        $p[$k] = $p[$k] ?? $nova((string) $x['nome'], (string) $x['email']);
        $p[$k]['pre'][] = $x;
        $p[$k]['cliente'] = $p[$k]['cliente'] ?: ($x['tipo_cliente'] === 'particular' ? '' : (string) $x['empresa']);
    }
    foreach (consulta('SELECT * FROM diagnosticos ORDER BY enviado DESC') as $d) {
        $k = $chave((string) $d['email'], 'd:' . (int) $d['id']);
        $p[$k] = $p[$k] ?? $nova((string) $d['nome'], (string) $d['email']);
        $p[$k]['diag'][] = $d;
    }
    // Utilizadores Formando sem inscrições, pré-inscrições nem diagnósticos (por exemplo, criados à mão)
    foreach (consulta("SELECT * FROM utilizadores WHERE papel = 'formando'") as $u) {
        $k = $chave((string) $u['email'], 'u:' . (int) $u['id']);
        $p[$k] = $p[$k] ?? $nova((string) $u['nome'], (string) $u['email']);
    }
    $clientes = array_column(consulta('SELECT id, nome FROM clientes'), 'nome', 'id');
    foreach ($p as &$x) {
        $u = $x['email'] !== '' ? utilizador_por_email($x['email']) : null;
        $x['utilizador'] = $u;
        if ($u && !empty($u['cliente_id']) && isset($clientes[(int) $u['cliente_id']])) {
            $x['cliente'] = $clientes[(int) $u['cliente_id']];
        }
        $x['ficha'] = $x['email'] !== '' ? ficha_certificado($x['email']) : null;
    }
    unset($x);
    uasort($p, function ($a, $b) { return strcoll(mb_strtolower($a['nome']), mb_strtolower($b['nome'])); });
    return $p;
}

// Perfil da pessoa: Formando, Cliente + Formando, Gadgetmetodo/Formador ou "Sem conta"
function perfil_pessoa(array $x): string
{
    $u = $x['utilizador'];
    if (!$u) {
        return $x['email'] === '' ? 'Sem email' : 'Sem conta';
    }
    $tem_inscricao = (bool) array_filter($x['inscricoes'], function ($i) { return in_array($i['estado'], ESTADOS_ATIVOS, true); });
    return $u['papel'] === 'formando' ? 'Formando' : (PAPEIS[$u['papel']] ?? $u['papel']) . ($tem_inscricao ? ' + Formando' : '');
}

// Situação resumida: Inscrito, Pré-inscrito, Só diagnóstico, Sem inscrição
function situacao_pessoa(array $x): string
{
    $ativas = array_filter($x['inscricoes'], function ($i) { return !in_array($i['estado'], ESTADOS_SAIDA, true); });
    if ($ativas) {
        return 'Inscrito';
    }
    if (array_filter($x['pre'], function ($q) { return $q['estado'] !== 'inscrita' && $q['estado'] !== 'arquivada'; })) {
        return 'Pré-inscrito';
    }
    return $x['diag'] ? 'Só diagnóstico' : 'Sem inscrição';
}

function link_pessoa(array $x): string
{
    if ($x['email'] !== '') {
        return url('formando', ['e' => $x['email']]);
    }
    return $x['inscricoes'] ? url('formando', ['i' => $x['inscricoes'][0]['id']]) : ($x['pre'] ? url('preinscricao', ['id' => $x['pre'][0]['id']]) : url('diagnostico', ['id' => $x['diag'][0]['id']]));
}

function admin_lista_formandos(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        tratar_acoes_formandos();
    }
    $linhas = '';
    $pessoas = pessoas_formandos();
    foreach ($pessoas as $k => $x) {
        $insc = '';
        foreach ($x['inscricoes'] as $i) {
            $pp = '';
            if (in_array($i['estado'], ESTADOS_ATIVOS, true)) {
                [$presentes, , $total] = resumo_presencas($i);
                $pp = ' · ' . $presentes . '/' . $total;
            }
            $insc .= '<a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h(curso_curto((string) $i['titulo'])) . '</a> <span class="texto-suave">' . h(date('d/m/Y', strtotime($i['inicio']))) . ' · ' . h(mb_strtolower(ESTADOS_INSCRICAO[$i['estado']] ?? $i['estado'])) . $pp . '</span><br>';
        }
        $pre = '';
        foreach ($x['pre'] as $q) {
            $pre .= '<a href="' . h(url('preinscricao', ['id' => $q['id']])) . '">' . h(curso_curto((string) $q['curso'])) . '</a> <span class="texto-suave">' . h(mb_strtolower(ESTADOS_PREINSCRICAO[$q['estado']] ?? $q['estado'])) . '</span><br>';
        }
        $diag = '';
        foreach ($x['diag'] as $d) {
            [$estado] = estado_diagnostico($d);
            $diag .= '<a href="' . h(url('diagnostico', ['id' => $d['id']])) . '">' . h($estado) . '</a>' . ($d['nivel'] ? ' <span class="texto-suave">' . h($d['nivel']) . '</span>' : '') . '<br>';
        }
        // Diagnóstico feito na inscrição ou na pré-inscrição (sem diagnóstico enviado por email)
        if ($diag === '' && ($nivel = ($x['inscricoes'][0]['nivel'] ?? '') ?: '')) {
            $diag = '<span class="texto-suave">Na inscrição:</span> ' . h($nivel);
        }
        $ficha = $x['ficha'];
        $certs = 0;
        foreach ($x['inscricoes'] as $i) {
            $certs += certificado_da_inscricao((int) $i['id']) ? 1 : 0;
        }
        $cert = ($x['email'] === '' ? '<span class="texto-suave">Sem email</span>' : (ficha_completa($ficha) ? etiqueta('Dados completos', 'ok') : etiqueta('Dados em falta', 'info')))
            . ($certs ? '<br><span class="texto-suave">' . $certs . ' certificado(s)</span>' : '');
        $u = $x['utilizador'];
        $linhas .= '<tr><td><input type="checkbox" form="acoes-formandos" name="pessoa[]" value="' . h($k) . '" aria-label="Escolher ' . h($x['nome']) . '"></td>'
            . '<td><a href="' . h(link_pessoa($x)) . '">' . h($x['nome']) . '</a></td><td>' . h($x['email']) . '</td>'
            . '<td>' . h(perfil_pessoa($x)) . '</td><td>' . h($x['cliente']) . '</td><td>' . h(situacao_pessoa($x)) . '</td>'
            . '<td>' . $pre . '</td><td>' . $insc . '</td><td>' . $diag . '</td>'
            . '<td data-valor="' . (ficha_completa($ficha) ? 'Dados completos' : 'Dados em falta') . '">' . $cert . '</td>'
            . '<td data-valor="' . h((string) ($u['ultimo_acesso'] ?? '')) . '">' . h($u && $u['ultimo_acesso'] ? data_hora($u['ultimo_acesso'], false) : '') . '</td></tr>';
    }
    $edicoes = '';
    foreach (edicoes_para_inscrever() as $f) {
        $edicoes .= '<option value="' . (int) $f['id'] . '">' . h($f['titulo'] . ' · ' . horario($f) . ' · ' . (ESTADOS_FORMACAO[$f['estado']] ?? $f['estado'])) . '</option>';
    }
    $acoes = '<form method="post" id="acoes-formandos" class="formulario espaco-topo-curto">' . campo_csrf() . '
        <h2 class="mt-0">Ações para os escolhidos</h2>
        <div class="campos-2">
          <div class="campo"><label for="c-edicao">Edição (para inscrever)</label><select id="c-edicao" name="edicao"><option value="">Escolha a edição</option>' . $edicoes . '</select></div>
        </div>
        <div class="acoes">
          <button class="botao" type="submit" name="acao" value="inscrever">Inscrever na edição sem enviar emails</button>
          <button class="botao botao-secundario" type="submit" name="acao" value="dados_certificado">Pedir os dados para o certificado</button>
          <button class="botao botao-secundario" type="submit" name="acao" value="acesso">Criar o acesso à área do formando</button>
        </div>
        <p class="ajuda">Inscrever: inscreve na edição escolhida, confirmados, com o nome, o email, a empresa e o diagnóstico (o da pré-inscrição desse curso, que passa a "Inscrita", ou o último diagnóstico respondido), sem enviar nenhum email; quem já está inscrito nessa edição fica como está. Pedir os dados para o certificado: só para quem tem uma inscrição confirmada e a ficha por completar. Criar o acesso: para quem tem uma inscrição confirmada e ainda não tem conta (envia a ligação para definir a palavra-passe). O diagnóstico envia-se em <a href="' . h(url('diagnosticos')) . '">Diagnósticos</a>.</p>
      </form>';
    pagina_area('formandos', 'Formandos', '
      <p class="texto-suave">Todas as pessoas que se pré-inscreveram, se inscreveram, receberam um diagnóstico ou têm conta de formando, uma por linha (pelo email): o perfil na área reservada (incluindo clientes que também são formandos), a situação, os cursos, o diagnóstico e os dados para o certificado. O nome abre a ficha do formando. Use os filtros por baixo dos títulos e o botão "Colunas".</p>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table data-filtros="formandos" class="tabela-escolha"><thead><tr><th data-sem-filtro><input type="checkbox" class="marcar-todos" aria-label="Escolher todos os formandos visíveis"></th><th>Nome</th><th>Email</th><th data-filtro="lista">Perfil</th><th data-filtro="lista">Cliente</th><th data-filtro="lista">Situação</th><th>Pré-inscrição</th><th>Inscrições</th><th>Diagnóstico</th><th data-filtro="lista">Certificado</th><th>Último acesso</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' . $acoes
        : '<p class="texto-suave">Ainda não há formandos.</p>'), 'Formação');
}

function tratar_acoes_formandos(): void
{
    $acao = (string) ($_POST['acao'] ?? '');
    $pessoas = pessoas_formandos();
    $escolhidas = array_values(array_filter(array_map(function ($k) use ($pessoas) { return $pessoas[$k] ?? null; }, array_filter((array) ($_POST['pessoa'] ?? []), 'is_string'))));
    if (!$escolhidas) {
        aviso('Escolha os formandos.', 'erro');
        redirecionar('formandos');
    }
    $feitos = [];
    $ignorados = [];
    if ($acao === 'inscrever') {
        $f = linha_bd("SELECT * FROM formacoes WHERE id = ? AND estado != 'cancelada'", [(int) ($_POST['edicao'] ?? 0)]);
        if (!$f) {
            aviso('Escolha a edição.', 'erro');
            redirecionar('formandos');
        }
        foreach ($escolhidas as $x) {
            $r = inscrever_pessoa_sem_emails($x, $f);
            if ($r === '') {
                $feitos[] = $x['nome'];
            } else {
                $ignorados[] = $x['nome'] . ' (' . $r . ')';
            }
        }
        aviso(($feitos ? count($feitos) . ' pessoa(s) inscrita(s) em ' . h($f['titulo']) . ' (' . h(horario($f)) . '), confirmada(s), sem enviar nenhum email: ' . h(implode(', ', $feitos)) . '.' : 'Ninguém foi inscrito.')
            . ($ignorados ? ' Não inscritas: ' . h(implode('; ', $ignorados)) . '.' : ''), $ignorados ? ($feitos ? 'info' : 'erro') : 'ok');
        redirecionar('formandos');
    }
    foreach ($escolhidas as $x) {
        $confirmada = array_values(array_filter($x['inscricoes'], function ($i) { return in_array($i['estado'], ESTADOS_ATIVOS, true); }))[0] ?? null;
        $i = $confirmada ? linha_bd(SQL_INSCRICAO, [(int) $confirmada['id']]) : null;
        if (!$i || $x['email'] === '') {
            $ignorados[] = $x['nome'] . ' (sem inscrição confirmada com email)';
        } elseif ($acao === 'dados_certificado') {
            if (ficha_completa($x['ficha'])) {
                $ignorados[] = $x['nome'] . ' (dados já completos)';
            } elseif (pedir_dados_certificado($i)) {
                $feitos[] = $x['nome'];
            } else {
                $ignorados[] = $x['nome'] . ' (falha no envio)';
            }
        } elseif ($acao === 'acesso') {
            $r = garantir_acesso_formando($i);
            if (in_array($r, ['criado', 'reenviado'], true)) {
                $feitos[] = $x['nome'];
            } else {
                $ignorados[] = $x['nome'] . ' (' . ['existe' => 'já tem acesso', 'outro_perfil' => 'já é utilizador', 'falhou' => 'falha no envio', 'sem_email' => 'sem email'][$r] . ')';
            }
        }
    }
    $o_que = $acao === 'acesso' ? 'Acesso criado e ligação enviada a ' : 'Pedido dos dados para o certificado enviado a ';
    aviso(($feitos ? $o_que . count($feitos) . ' pessoa(s): ' . h(implode(', ', $feitos)) . '.' : 'Nada enviado.') . ($ignorados ? ' Sem envio: ' . h(implode('; ', $ignorados)) . '.' : ''), $ignorados ? ($feitos ? 'info' : 'erro') : 'ok');
    redirecionar('formandos');
}

// Inscreve uma pessoa numa edição, confirmada e sem emails: com a pré-inscrição desse curso (que passa a "Inscrita"),
// se houver; senão com o último diagnóstico respondido. Devolve '' ou o motivo de não inscrever.
function inscrever_pessoa_sem_emails(array $x, array $f): string
{
    foreach ($x['pre'] as $q) {
        if ((int) $q['curso_id'] === (int) $f['curso_id'] && in_array($q['estado'], ['nova', 'convidada'], true)) {
            $r = inscrever_preinscricao($q, $f, (string) $q['nome'], trim((string) $q['email']), 'confirmada');
            return $r['erro'] === '' ? '' : rtrim($r['erro'], '.');
        }
    }
    $d = array_values(array_filter($x['diag'], function ($d) { return $d['respondido'] !== null; }))[0] ?? null;
    $u = $x['utilizador'];
    $cliente = $u && !empty($u['cliente_id']) ? linha_bd('SELECT * FROM clientes WHERE id = ?', [(int) $u['cliente_id']]) : null;
    $respostas = $d ? json_decode((string) $d['respostas'], true) : null;
    $ferramentas = $d ? json_decode((string) $d['ferramentas'], true) : null;
    $r = gravar_inscricao($f, ['cliente_id' => $cliente['id'] ?? null, 'utilizador_id' => utilizador()['id'], 'origem' => 'area',
        'tipo_cliente' => $cliente['tipo'] ?? null, 'empresa' => null, 'nif' => null, 'contacto_nome' => $x['nome'], 'contacto_email' => $x['email'], 'contacto_telefone' => null,
        'formando' => $x['nome'], 'email' => $x['email'], 'funcao' => '', 'ferramentas' => is_array($ferramentas) ? array_map('strval', $ferramentas) : [], 'expectativas' => ''],
        is_array($respostas) ? $respostas : [], true);
    if ($r['erro'] !== '') {
        return rtrim($r['erro'], '.');
    }
    executar("UPDATE inscricoes SET estado = 'confirmada' WHERE id = ?", [$r['id']]);
    return '';
}

// Ficha de quem ainda não tem inscrições (só pré-inscrição, diagnóstico ou conta): dados, pré-inscrições, diagnósticos
// e dados para o certificado
function admin_pessoa_sem_inscricao(string $email): void
{
    $x = pessoas_formandos()['e:' . mb_strtolower(trim($email))] ?? null;
    if (!$x) {
        pagina_erro('Formando não encontrado', 'Não há nenhum formando, candidato ou diagnóstico com este email.');
    }
    $erros = [[], []];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'ficha_certificado') {
        $erros = ler_ficha_certificado();
        if (!$erros[1]) {
            gravar_ficha_certificado($x['email'], $erros[0], (int) utilizador()['id']);
            aviso('Dados para o certificado guardados.');
            redirecionar('formando', ['e' => $x['email']], 'certificado');
        }
    }
    [, $texto_acesso] = estado_acesso_formando($x['email']);
    $ficha = '<tr><th scope="row">Nome</th><td>' . h($x['nome']) . '</td></tr><tr><th scope="row">Email</th><td><a href="mailto:' . h($x['email']) . '">' . h($x['email']) . '</a></td></tr>'
        . '<tr><th scope="row">Perfil</th><td>' . h(perfil_pessoa($x)) . ($x['utilizador'] ? ' · <a href="' . h(url('utilizador', ['id' => $x['utilizador']['id']])) . '">Ver o utilizador</a>' : '') . '</td></tr>'
        . ($x['cliente'] !== '' ? '<tr><th scope="row">Cliente</th><td>' . h($x['cliente']) . '</td></tr>' : '')
        . '<tr><th scope="row">Área do formando</th><td>' . h($texto_acesso) . '</td></tr><tr><th scope="row">Situação</th><td>' . h(situacao_pessoa($x)) . '</td></tr>';
    pagina_area('formandos', $x['nome'], '<div class="conteudo"><table class="tabela-ficha"><tbody>' . $ficha . '</tbody></table></div>'
        . pre_e_diagnosticos_html($x['email']) . ficha_certificado_admin_html($x['email'], url('formando', ['e' => $x['email']]), ...$erros)
        . '<p class="espaco-topo"><a href="' . h(url('formandos')) . '">Voltar aos formandos</a></p>', 'Formando');
}

// Pré-inscrições e diagnósticos de um email (na ficha do formando)
function pre_e_diagnosticos_html(string $email): string
{
    if (trim($email) === '') {
        return '';
    }
    $pre = consulta('SELECT p.*, c.nome AS curso FROM preinscricoes p LEFT JOIN cursos c ON c.id = p.curso_id WHERE ' . sprintf(SQL_EMAIL_IGUAL, 'p.email') . ' ORDER BY p.criado DESC', [$email]);
    $diag = consulta('SELECT * FROM diagnosticos WHERE ' . sprintf(SQL_EMAIL_IGUAL, 'email') . ' ORDER BY enviado DESC', [$email]);
    $linhas = '';
    foreach ($pre as $q) {
        $linhas .= '<tr><td>Pré-inscrição</td><td><a href="' . h(url('preinscricao', ['id' => $q['id']])) . '">' . h((string) $q['curso']) . '</a></td><td>' . h(data_hora($q['criado'], false)) . '</td><td>' . h(ESTADOS_PREINSCRICAO[$q['estado']] ?? $q['estado']) . '</td></tr>';
    }
    foreach ($diag as $d) {
        [$estado] = estado_diagnostico($d);
        $linhas .= '<tr><td>Diagnóstico</td><td><a href="' . h(url('diagnostico', ['id' => $d['id']])) . '">' . h(nomes_cursos_diagnostico($d)) . '</a></td><td>' . h(data_hora($d['enviado'], false)) . '</td><td>' . h($estado) . ($d['nivel'] ? ' · ' . h($d['nivel']) : '') . '</td></tr>';
    }
    return '<h2 class="espaco-topo">Pré-inscrições e diagnósticos</h2>' . ($linhas
        ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Tipo</th><th>Curso</th><th>Data</th><th>Estado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>'
        : '<p class="texto-suave">Sem pré-inscrições nem diagnósticos enviados.</p>');
}
