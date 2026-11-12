<?php
// Área de clientes: apagar definitivamente um registo (só administração). Cada pedido passa por uma página de
// confirmação que mostra o que vai ser apagado, o que se mantém e a alternativa sem perda de dados (cancelar,
// arquivar ou desativar). Endereço: area.php?p=apagar&tipo=...&id=...

declare(strict_types=1);

const TIPOS_APAGAR = ['inscricao', 'preinscricao', 'formacao', 'curso', 'modulo', 'cliente', 'utilizador', 'pedido', 'diagnostico'];

// Ligação "Apagar..." para a página de confirmação
function botao_apagar(string $tipo, $id, string $rotulo, string $classe = 'botao botao-secundario botao-apagar'): string
{
    return '<a class="' . h($classe) . '" href="' . h(url('apagar', ['tipo' => $tipo, 'id' => (string) $id])) . '">' . h($rotulo) . '</a>';
}

// Apaga uma edição com tudo o que depende dela: inscrições (e diagnósticos), datas das sessões e registo dos lembretes
function apagar_formacao_completa(int $id): void
{
    executar('UPDATE preinscricoes SET inscricao_id = NULL WHERE inscricao_id IN (SELECT id FROM inscricoes WHERE formacao_id = ?)', [$id]);
    executar('UPDATE preinscricoes SET formacao_id = NULL WHERE formacao_id = ?', [$id]);
    executar('DELETE FROM inscricoes WHERE formacao_id = ?', [$id]);
    executar('DELETE FROM sessoes WHERE formacao_id = ?', [$id]);
    executar('DELETE FROM lembretes WHERE formacao_id = ?', [$id]);
    executar('DELETE FROM formacoes WHERE id = ?', [$id]);
}

function contar(string $sql, array $p): int
{
    return (int) (linha_bd($sql, $p)['n'] ?? 0);
}

// Descrição do pedido: título, o que é apagado, o que se mantém, a alternativa, a página de origem, para onde volta
// depois de apagar, o separador do menu e a função que apaga. Devolve null se o registo não existir ou não puder ser apagado.
function pedido_apagar(string $tipo, string $id): ?array
{
    $n = (int) $id;
    switch ($tipo) {
        case 'inscricao':
            if (!$i = linha_bd(SQL_INSCRICAO, [$n])) {
                return null;
            }
            return [
                'titulo' => 'Apagar a inscrição de ' . $i['formando'],
                'apaga' => ['A inscrição de ' . $i['formando'] . ' no ' . $i['titulo'] . ' (' . horario($i) . '), com os dados do formando e de quem o inscreveu.', 'As respostas ao diagnóstico desta inscrição.'],
                'mantem' => [],
                'alternativa' => 'Em alternativa, mude o estado da inscrição para Cancelada: deixa de contar para as vagas e os dados ficam guardados.',
                'voltar' => url('inscricao', ['id' => $n]), 'depois' => ['formacao', ['id' => (int) $i['formacao_id']]], 'menu' => 'inscricoes',
                'executar' => function () use ($n) {
                    executar('UPDATE preinscricoes SET inscricao_id = NULL WHERE inscricao_id = ?', [$n]);
                    executar('DELETE FROM inscricoes WHERE id = ?', [$n]);
                },
            ];
        case 'preinscricao':
            if (!$p = linha_bd('SELECT * FROM preinscricoes WHERE id = ?', [$n])) {
                return null;
            }
            $c = curso((int) $p['curso_id']);
            return [
                'titulo' => 'Apagar a pré-inscrição de ' . $p['nome'],
                'apaga' => ['A pré-inscrição de ' . $p['nome'] . ' (' . $p['email'] . ')' . ($c ? ' no ' . $c['nome'] : '') . ', com os contactos e as preferências.', 'As respostas ao diagnóstico desta pré-inscrição.'],
                'mantem' => $p['inscricao_id'] ? ['A inscrição feita a partir desta pré-inscrição.'] : [],
                'alternativa' => 'Em alternativa, mude o estado para Arquivada: sai da lista por tratar e os dados ficam guardados.',
                'voltar' => url('preinscricao', ['id' => $n]), 'depois' => ['inscricoes', ['estado' => 'preinscricao']], 'menu' => 'inscricoes',
                'executar' => function () use ($n) { executar('DELETE FROM preinscricoes WHERE id = ?', [$n]); },
            ];
        case 'formacao':
            if (!$f = linha_bd('SELECT * FROM formacoes WHERE id = ?', [$n])) {
                return null;
            }
            $ins = contar('SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ?', [$n]);
            $conf = contar("SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ? AND estado = 'confirmada'", [$n]);
            return [
                'titulo' => 'Apagar uma edição do ' . $f['titulo'],
                'apaga' => array_values(array_filter([
                    'A edição (' . horario($f) . '), com as datas das sessões, a ligação da reunião e as notas.',
                    $ins ? $ins . ' inscrição(ões) nesta edição (' . $conf . ' confirmada(s)), com os diagnósticos.' : '',
                    contar('SELECT COUNT(*) AS n FROM lembretes WHERE formacao_id = ?', [$n]) ? 'O registo dos lembretes enviados.' : '',
                ])),
                'mantem' => ['O curso e as pré-inscrições.'],
                'alternativa' => 'Em alternativa, mude o estado para Cancelada: a edição sai do site e os dados ficam guardados.',
                'voltar' => url('formacao', ['id' => $n]), 'depois' => ['calendario', []], 'menu' => 'calendario',
                'executar' => function () use ($n) { apagar_formacao_completa($n); },
            ];
        case 'curso':
            if (!$c = curso($n)) {
                return null;
            }
            $edicoes = array_map('intval', array_column(consulta('SELECT id FROM formacoes WHERE curso_id = ?', [$n]), 'id'));
            $ins = contar('SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id IN (SELECT id FROM formacoes WHERE curso_id = ?)', [$n]);
            $pre = contar('SELECT COUNT(*) AS n FROM preinscricoes WHERE curso_id = ?', [$n]);
            return [
                'titulo' => 'Apagar o ' . $c['nome'],
                'apaga' => array_values(array_filter([
                    'O curso ' . $c['nome'] . ', com a ficha e as ' . count(modulos_curso($n)) . ' ' . unidade($c, true) . ' (temas e casos práticos).',
                    $edicoes ? count($edicoes) . ' edição(ões) no calendário, com as datas das sessões.' : '',
                    $ins ? $ins . ' inscrição(ões) nessas edições, com os diagnósticos.' : '',
                    $pre ? $pre . ' pré-inscrição(ões) neste curso.' : '',
                ])),
                'mantem' => ['A tabela de preços e os outros cursos do percurso.'],
                'alternativa' => 'Em alternativa, desative o curso: deixa de poder ser agendado e de aparecer no site, e os dados ficam guardados.',
                'voltar' => url('curso', ['id' => $n]), 'depois' => ['cursos', []], 'menu' => 'cursos',
                'executar' => function () use ($n, $edicoes) {
                    foreach ($edicoes as $e) {
                        apagar_formacao_completa($e);
                    }
                    executar('DELETE FROM preinscricoes WHERE curso_id = ?', [$n]);
                    executar('UPDATE preinscricoes SET curso_origem = NULL WHERE curso_origem = ?', [$n]);
                    executar('DELETE FROM modulos WHERE curso_id = ?', [$n]);
                    executar('UPDATE diagnosticos SET curso_id = NULL WHERE curso_id = ?', [$n]);
                    executar('DELETE FROM cursos WHERE id = ?', [$n]);
                },
            ];
        case 'modulo':
            if (!$m = linha_bd('SELECT * FROM modulos WHERE id = ?', [$n])) {
                return null;
            }
            $c = curso((int) $m['curso_id']);
            $agendadas = contar('SELECT COUNT(*) AS n FROM sessoes WHERE modulo_id = ?', [$n]) + contar('SELECT COUNT(*) AS n FROM formacoes WHERE modulo_id = ?', [$n]);
            return [
                'titulo' => 'Apagar ' . rotulo_modulo($m),
                'apaga' => [rotulo_modulo($m) . ' do ' . ($c['nome'] ?? 'curso') . ', com a descrição, os temas e o caso prático.'],
                'mantem' => $agendadas ? [$agendadas . ' sessão(ões) agendada(s) com este tema mantêm a data e o horário, sem tema associado.'] : [],
                'alternativa' => '',
                'voltar' => url('curso', ['id' => (int) $m['curso_id']]), 'depois' => ['curso', ['id' => (int) $m['curso_id']]], 'menu' => 'cursos',
                'executar' => function () use ($n) {
                    executar('UPDATE sessoes SET modulo_id = NULL WHERE modulo_id = ?', [$n]);
                    executar('UPDATE formacoes SET modulo_id = NULL WHERE modulo_id = ?', [$n]);
                    executar('DELETE FROM modulos WHERE id = ?', [$n]);
                },
            ];
        case 'cliente':
            if (!$c = linha_bd('SELECT * FROM clientes WHERE id = ?', [$n])) {
                return null;
            }
            // Os utilizadores Cliente deste cliente são apagados; os formandos associados à empresa mantêm a conta, sem a associação
            $u = contar("SELECT COUNT(*) AS n FROM utilizadores WHERE cliente_id = ? AND papel = 'cliente'", [$n]);
            $fo = contar("SELECT COUNT(*) AS n FROM utilizadores WHERE cliente_id = ? AND papel != 'cliente'", [$n]);
            $ins = contar('SELECT COUNT(*) AS n FROM inscricoes WHERE cliente_id = ?', [$n]);
            return [
                'titulo' => 'Apagar o cliente ' . $c['nome'],
                'apaga' => array_values(array_filter(['O cliente ' . $c['nome'] . ', com o NIF, os contactos e as notas.', $u ? $u . ' utilizador(es) deste cliente, que deixam de ter acesso à área reservada.' : ''])),
                'mantem' => array_values(array_filter([$ins ? $ins . ' inscrição(ões) deste cliente, com o nome do cliente guardado na própria inscrição.' : '',
                    $fo ? $fo . ' formando(s) associado(s) a este cliente, que mantêm o acesso, sem a associação.' : ''])),
                'alternativa' => $u ? 'Em alternativa, desative os utilizadores do cliente: deixam de entrar e os dados ficam guardados.' : '',
                'voltar' => url('cliente', ['id' => $n]), 'depois' => ['clientes', []], 'menu' => 'clientes',
                'executar' => function () use ($n, $c) {
                    executar("UPDATE inscricoes SET empresa = CASE WHEN COALESCE(empresa, '') = '' THEN ? ELSE empresa END, cliente_id = NULL WHERE cliente_id = ?", [$c['nome'], $n]);
                    executar("DELETE FROM utilizadores WHERE cliente_id = ? AND papel = 'cliente'", [$n]);
                    executar('UPDATE utilizadores SET cliente_id = NULL WHERE cliente_id = ?', [$n]);
                    executar('DELETE FROM clientes WHERE id = ?', [$n]);
                },
            ];
        case 'utilizador':
            $u = linha_bd('SELECT * FROM utilizadores WHERE id = ?', [$n]);
            if (!$u || (int) utilizador()['id'] === $n) {
                return null;
            }
            return [
                'titulo' => 'Apagar o utilizador ' . $u['nome'],
                'apaga' => ['O acesso de ' . $u['nome'] . ' (' . $u['email'] . ') à área reservada.'],
                'mantem' => ['As inscrições feitas por este utilizador.'],
                'alternativa' => 'Em alternativa, desative o utilizador: deixa de entrar e os dados ficam guardados.',
                'voltar' => url('utilizador', ['id' => $n]), 'depois' => ['utilizadores', []], 'menu' => 'utilizadores',
                'executar' => function () use ($n) {
                    // Os diagnósticos enviados ficam (com o nome e o email), sem a ligação ao utilizador
                    executar('UPDATE diagnosticos SET utilizador_id = NULL WHERE utilizador_id = ?', [$n]);
                    executar('DELETE FROM utilizadores WHERE id = ?', [$n]);
                },
            ];
        case 'diagnostico':
            if (!$d = linha_bd('SELECT d.*, c.nome AS curso FROM diagnosticos d LEFT JOIN cursos c ON c.id = d.curso_id WHERE d.id = ?', [$n])) {
                return null;
            }
            return [
                'titulo' => 'Apagar o diagnóstico de ' . $d['nome'],
                'apaga' => ['O diagnóstico enviado a ' . $d['nome'] . ' (' . $d['email'] . ')' . ($d['curso'] ? ' para o ' . $d['curso'] : '') . ($d['respondido'] !== null ? ', com as respostas e o resultado.' : '. A ligação enviada deixa de funcionar.')],
                'mantem' => [], 'alternativa' => '',
                'voltar' => url('diagnostico', ['id' => $n]), 'depois' => ['diagnosticos', []], 'menu' => 'diagnosticos',
                'executar' => function () use ($n) { executar('DELETE FROM diagnosticos WHERE id = ?', [$n]); },
            ];
        case 'pedido':
            $p = null;
            foreach (ler_registos(cfg()) as $r) {
                if ($r['ref'] === $id) {
                    $p = $r;
                }
            }
            if (!$p) {
                return null;
            }
            return [
                'titulo' => 'Apagar o pedido ' . $p['ref'],
                'apaga' => ['O pedido de ' . $p['nome'] . ' (' . ($p['empresa'] ?: 'Particular') . ', ' . $p['email'] . ') de ' . data_hora($p['criado']) . '.', 'O registo das aberturas do catálogo enviado com a confirmação.'],
                'mantem' => ['O email do pedido, que continua na caixa da administração.'],
                'alternativa' => '',
                'voltar' => url('pedidos'), 'depois' => ['pedidos', []], 'menu' => 'pedidos',
                'executar' => function () use ($id) {
                    executar('DELETE FROM aberturas WHERE ref = ?', [$id]);
                    executar('DELETE FROM pedidos WHERE ref = ?', [$id]);
                },
            ];
    }
    return null;
}

function admin_apagar(): void
{
    $tipo = (string) ($_GET['tipo'] ?? '');
    $id = (string) ($_GET['id'] ?? '');
    $pedido = in_array($tipo, TIPOS_APAGAR, true) && $id !== '' ? pedido_apagar($tipo, $id) : null;
    if (!$pedido) {
        pagina_erro('Registo não encontrado', $tipo === 'utilizador' && (string) (utilizador()['id'] ?? '') === $id ? 'Não pode apagar o seu próprio utilizador.' : 'O registo pedido não existe ou já foi apagado.');
    }
    $erro = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (empty($_POST['confirmo'])) {
            $erro = 'Marque a confirmação para apagar.';
        } else {
            $pdo = bd();
            $pdo->beginTransaction();
            try {
                $pedido['executar']();
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('Área de clientes: falha ao apagar ' . $tipo . ' ' . $id . ': ' . $e->getMessage());
                aviso('Não foi possível apagar o registo. Os detalhes ficam no registo de erros do alojamento.', 'erro');
                redirecionar('apagar', ['tipo' => $tipo, 'id' => $id]);
            }
            aviso('Registo apagado definitivamente: ' . h(preg_replace('/^Apagar /u', '', $pedido['titulo'])) . '.');
            redirecionar($pedido['depois'][0], $pedido['depois'][1]);
        }
    }
    $lista = function (array $itens) { return '<ul class="lista-apagar">' . implode('', array_map(function ($t) { return '<li>' . h($t) . '</li>'; }, $itens)) . '</ul>'; };
    pagina_area($pedido['menu'], $pedido['titulo'], ($erro ? '<div class="aviso aviso-erro">' . h($erro) . '</div>' : '') . '
      <div class="aviso aviso-erro">Esta operação apaga definitivamente os dados abaixo e não pode ser desfeita.</div>
      <h2>O que é apagado</h2>' . $lista($pedido['apaga'])
      . ($pedido['mantem'] ? '<h2 class="espaco-topo">O que se mantém</h2>' . $lista($pedido['mantem']) : '')
      . ($pedido['alternativa'] ? '<p class="espaco-topo texto-suave">' . h($pedido['alternativa']) . '</p>' : '') . '
      <form method="post" class="formulario espaco-topo">' . campo_csrf() . '
        <label class="opcao consentimento"><input type="checkbox" name="confirmo" value="1" required> <span>Compreendo que estes dados são apagados definitivamente.</span></label>
        <div class="acoes-formulario"><button class="botao botao-perigo" type="submit">Apagar definitivamente</button><a class="botao botao-secundario" href="' . h($pedido['voltar']) . '">Cancelar</a></div>
      </form>', 'Apagar');
}
