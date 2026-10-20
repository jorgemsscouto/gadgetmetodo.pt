<?php
// Área de clientes: páginas da administração (Gadgetmetodo).

declare(strict_types=1);

function admin_painel(): void
{
    $n = function (string $sql) { return (int) (linha_bd($sql)['n'] ?? 0); };
    $proximas = consulta("SELECT * FROM formacoes WHERE inicio >= ? AND estado != 'cancelada' ORDER BY inicio LIMIT 5", [date('Y-m-d 00:00:00')]);
    $ultimas = consulta('SELECT i.*, c.nome AS cliente, f.titulo, f.inicio FROM inscricoes i JOIN clientes c ON c.id = i.cliente_id JOIN formacoes f ON f.id = i.formacao_id ORDER BY i.criado DESC LIMIT 8');
    $linhas = '';
    foreach ($ultimas as $i) {
        $linhas .= '<tr><td>' . h(data_hora($i['criado'])) . '</td><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . h($i['cliente']) . '</td><td>' . h($i['titulo']) . ', ' . h(data_hora($i['inicio'], false)) . '</td><td>' . nivel_etiqueta($i['nivel']) . '</td></tr>';
    }
    pagina_area('painel', 'Painel', '
      <div class="numeros">
        <div><strong>' . $n("SELECT COUNT(*) AS n FROM formacoes WHERE inicio >= '" . date('Y-m-d') . "' AND estado != 'cancelada'") . '</strong>formações agendadas</div>
        <div><strong>' . $n("SELECT COUNT(*) AS n FROM inscricoes WHERE estado != 'cancelada'") . '</strong>inscrições ativas</div>
        <div><strong>' . $n('SELECT COUNT(*) AS n FROM clientes') . '</strong>clientes</div>
        <div><strong>' . $n('SELECT COUNT(*) AS n FROM utilizadores WHERE ativo = 1') . '</strong>utilizadores ativos</div>
      </div>
      <div class="acoes mb-0" style="margin-top:0"><a class="botao" href="' . h(url('formacao')) . '">Nova formação</a><a class="botao botao-secundario" href="' . h(url('utilizadores')) . '">Novo utilizador</a></div>
      <h2 class="espaco-topo">Próximas formações</h2>
      ' . calendario($proximas, function ($f) { return '<a href="' . h(url('formacao', ['id' => $f['id']])) . '">Gerir</a>'; }) . '
      <h2 class="espaco-topo">Últimas inscrições</h2>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Data</th><th>Formando</th><th>Cliente</th><th>Formação</th><th>Nível de IA</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há inscrições.</p>'), 'Gadgetmetodo');
}

/* Calendário de formações */

function admin_calendario(): void
{
    $passadas = ($_GET['ver'] ?? '') === 'passadas';
    $formacoes = $passadas
        ? consulta('SELECT * FROM formacoes WHERE inicio < ? ORDER BY inicio DESC', [date('Y-m-d 00:00:00')])
        : consulta('SELECT * FROM formacoes WHERE inicio >= ? ORDER BY inicio', [date('Y-m-d 00:00:00')]);
    pagina_area('calendario', 'Calendário de formações', '
      <div class="acoes" style="margin-top:0"><a class="botao" href="' . h(url('formacao')) . '">Nova formação</a>
      <a class="botao botao-secundario" href="' . h(url('calendario', $passadas ? [] : ['ver' => 'passadas'])) . '">' . ($passadas ? 'Ver próximas' : 'Ver formações passadas') . '</a></div>
      ' . calendario($formacoes, function ($f) {
          return '<a href="' . h(url('formacao', ['id' => $f['id']])) . '">Gerir e ver inscritos</a>'
              . ($f['estado'] !== 'cancelada' ? '<a href="' . h(url('inscrever', ['formacao' => $f['id']])) . '">Inscrever formando</a>' : '');
      }), 'AI Essentials');
}

function admin_formacao(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $f = $id ? linha_bd('SELECT * FROM formacoes WHERE id = ?', [$id]) : null;
    if ($id && !$f) {
        pagina_erro('Formação não encontrada', 'A formação pedida não existe.');
    }
    $erros = [];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'apagar' && $f) {
        // Só se apaga uma formação sem inscrições; com inscrições, deve ser cancelada
        if ((int) (linha_bd('SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ?', [$id])['n'] ?? 0) > 0) {
            aviso('Esta formação tem inscrições e não pode ser apagada. Altere o estado para Cancelada.', 'erro');
            redirecionar('formacao', ['id' => $id]);
        }
        executar('DELETE FROM formacoes WHERE id = ?', [$id]);
        aviso('Formação apagada.');
        redirecionar('calendario');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $d = [
            'titulo' => texto('titulo', 120) ?: 'AI Essentials',
            'modulo' => isset(MODULOS[$_POST['modulo'] ?? '']) ? $_POST['modulo'] : 'completo',
            'data' => texto('data', 10), 'hora_inicio' => texto('hora_inicio', 5), 'hora_fim' => texto('hora_fim', 5),
            'modalidade' => isset(MODALIDADES[$_POST['modalidade'] ?? '']) ? $_POST['modalidade'] : 'presencial',
            'local' => texto('local', 160), 'vagas' => max(1, min(500, (int) ($_POST['vagas'] ?? 12))),
            'estado' => isset(ESTADOS_FORMACAO[$_POST['estado'] ?? '']) ? $_POST['estado'] : 'aberta',
            'notas' => texto('notas', 2000, true),
        ];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['data']) || !strtotime($d['data'])) {
            $erros[] = 'Indique a data.';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $d['hora_inicio'])) {
            $erros[] = 'Indique a hora de início.';
        }
        if ($d['hora_fim'] !== '' && (!preg_match('/^\d{2}:\d{2}$/', $d['hora_fim']) || $d['hora_fim'] <= $d['hora_inicio'])) {
            $erros[] = 'A hora de fim tem de ser posterior à de início.';
        }
        if (!$erros) {
            $inicio = $d['data'] . ' ' . $d['hora_inicio'] . ':00';
            $fim = $d['hora_fim'] !== '' ? $d['data'] . ' ' . $d['hora_fim'] . ':00' : null;
            $valores = [$d['titulo'], $d['modulo'], $inicio, $fim, $d['modalidade'], $d['local'], $d['vagas'], $d['estado'], $d['notas']];
            if ($f) {
                executar('UPDATE formacoes SET titulo = ?, modulo = ?, inicio = ?, fim = ?, modalidade = ?, local = ?, vagas = ?, estado = ?, notas = ? WHERE id = ?', array_merge($valores, [$id]));
                aviso('Formação atualizada.');
            } else {
                $id = executar('INSERT INTO formacoes (titulo, modulo, inicio, fim, modalidade, local, vagas, estado, notas, criado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($valores, [agora()]));
                aviso('Formação criada e visível para os clientes.');
            }
            redirecionar('formacao', ['id' => $id]);
        }
        $f = ['titulo' => $d['titulo'], 'modulo' => $d['modulo'], 'inicio' => $d['data'] . ' ' . $d['hora_inicio'], 'fim' => $d['hora_fim'] ? $d['data'] . ' ' . $d['hora_fim'] : '', 'modalidade' => $d['modalidade'], 'local' => $d['local'], 'vagas' => $d['vagas'], 'estado' => $d['estado'], 'notas' => $d['notas'], 'id' => $id ?: null];
    }
    $v = $f ?: ['titulo' => 'AI Essentials', 'modulo' => 'completo', 'inicio' => '', 'fim' => '', 'modalidade' => 'presencial', 'local' => '', 'vagas' => 12, 'estado' => 'aberta', 'notas' => ''];
    $t = $v['inicio'] ? strtotime($v['inicio']) : false;
    $form = ($erros ? '<div class="aviso aviso-erro">' . h(implode(' ', $erros)) . '</div>' : '') . '
      <form method="post" class="formulario">' . campo_csrf() . '
        <div class="campos-2">
          ' . campo('titulo', 'Título *', (string) $v['titulo'], 'text', 'required maxlength="120"') . '
          ' . campo_select('modulo', 'Módulo *', MODULOS, $v['modulo']) . '
          ' . campo('data', 'Data *', $t ? date('Y-m-d', $t) : '', 'date', 'required') . '
          <div class="campos-2">' . campo('hora_inicio', 'Início *', $t ? date('H:i', $t) : '09:30', 'time', 'required') . campo('hora_fim', 'Fim', $v['fim'] ? date('H:i', strtotime($v['fim'])) : '13:00', 'time') . '</div>
          ' . campo_select('modalidade', 'Modalidade *', MODALIDADES, $v['modalidade']) . '
          ' . campo('local', 'Local ou ligação', (string) $v['local'], 'text', 'maxlength="160" placeholder="Morada ou indicação de que a ligação segue por email"') . '
          ' . campo('vagas', 'Vagas *', (string) $v['vagas'], 'number', 'min="1" max="500" required') . '
          ' . campo_select('estado', 'Estado *', ESTADOS_FORMACAO, $v['estado']) . '
        </div>
        ' . campo('notas', 'Notas internas', (string) $v['notas'], 'textarea', 'maxlength="2000"') . '
        <div><button class="botao" type="submit">' . ($id ? 'Guardar alterações' : 'Criar formação') . '</button></div>
      </form>';
    $inscritos = '';
    if ($id) {
        $lista = consulta('SELECT i.*, c.nome AS cliente FROM inscricoes i JOIN clientes c ON c.id = i.cliente_id WHERE i.formacao_id = ? ORDER BY i.criado', [$id]);
        $linhas = '';
        foreach ($lista as $i) {
            $linhas .= '<tr><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . h($i['funcao']) . '</td><td>' . h($i['cliente']) . '</td><td>' . nivel_etiqueta($i['nivel']) . '</td><td>' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</td></tr>';
        }
        $inscritos = '<h2 class="espaco-topo">Inscritos (' . inscritos($id) . ' de ' . (int) $v['vagas'] . ')</h2>'
            . '<p><a class="botao" href="' . h(url('inscrever', ['formacao' => $id])) . '">Inscrever formando</a>'
            . ($linhas ? ' <a class="botao botao-secundario" href="' . h(url('inscricoes', ['formacao' => $id, 'exportar' => 1])) . '">Exportar inscritos para Excel</a>' : '') . '</p>'
            . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Formando</th><th>Função</th><th>Cliente</th><th>Nível de IA</th><th>Estado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há inscrições nesta formação.</p>');
        if (!$linhas && !linha_bd('SELECT id FROM inscricoes WHERE formacao_id = ?', [$id])) {
            $inscritos .= '<form method="post" class="espaco-topo">' . campo_csrf() . '<input type="hidden" name="acao" value="apagar"><button class="botao botao-secundario" type="submit">Apagar esta formação</button></form>';
        }
    }
    pagina_area('calendario', $id ? 'Editar formação' : 'Nova formação', $form . $inscritos, 'Calendário');
}

/* Inscrições */

function admin_inscricoes(): void
{
    $filtros = ['formacao' => (int) ($_GET['formacao'] ?? 0), 'cliente' => (int) ($_GET['cliente'] ?? 0), 'estado' => isset(ESTADOS_INSCRICAO[$_GET['estado'] ?? '']) ? $_GET['estado'] : ''];
    $sql = 'SELECT i.*, c.nome AS cliente, f.titulo, f.inicio FROM inscricoes i JOIN clientes c ON c.id = i.cliente_id JOIN formacoes f ON f.id = i.formacao_id WHERE 1 = 1';
    $par = [];
    if ($filtros['formacao']) { $sql .= ' AND i.formacao_id = ?'; $par[] = $filtros['formacao']; }
    if ($filtros['cliente']) { $sql .= ' AND i.cliente_id = ?'; $par[] = $filtros['cliente']; }
    if ($filtros['estado']) { $sql .= ' AND i.estado = ?'; $par[] = $filtros['estado']; }
    $lista = consulta($sql . ' ORDER BY f.inicio DESC, i.criado', $par);
    if (!empty($_GET['exportar'])) {
        $linhas = [];
        foreach ($lista as $i) {
            $linhas[] = [$i['titulo'], data_hora($i['inicio']), $i['cliente'], $i['formando'], $i['email'], $i['funcao'], ESTADOS_INSCRICAO[$i['estado']] ?? $i['estado'], $i['nivel'], $i['pontuacao'], $i['responsavel'], $i['ferramentas'], $i['expectativas'], data_hora($i['criado'])];
        }
        exportar_csv('gadgetmetodo-inscricoes', ['Formação', 'Data da formação', 'Cliente', 'Formando', 'Email', 'Função', 'Estado', 'Nível de IA', 'Pontuação (0-24)', 'Uso responsável (0-9)', 'Ferramentas usadas', 'Expectativas', 'Data da inscrição'], $linhas);
    }
    $formacoes = [];
    foreach (consulta('SELECT id, titulo, inicio FROM formacoes ORDER BY inicio DESC') as $f) {
        $formacoes[$f['id']] = data_hora($f['inicio'], false) . ' · ' . $f['titulo'];
    }
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    $linhas = '';
    foreach ($lista as $i) {
        $linhas .= '<tr><td><a href="' . h(url('inscricao', ['id' => $i['id']])) . '">' . h($i['formando']) . '</a></td><td>' . h($i['cliente']) . '</td><td>' . h($i['titulo']) . '<br><span class="texto-suave">' . h(data_hora($i['inicio'])) . '</span></td><td>' . nivel_etiqueta($i['nivel']) . '</td><td>' . estado_etiqueta($i['estado'], ESTADOS_INSCRICAO) . '</td><td>' . h(data_hora($i['criado'])) . '</td></tr>';
    }
    pagina_area('inscricoes', 'Inscrições', '
      <form method="get" class="filtros">
        <input type="hidden" name="p" value="inscricoes">
        ' . campo_select('formacao', 'Formação', $formacoes, $filtros['formacao'], 'Todas') . campo_select('cliente', 'Cliente', $clientes, $filtros['cliente'], 'Todos') . campo_select('estado', 'Estado', ESTADOS_INSCRICAO, $filtros['estado'], 'Todos') . '
        <div class="filtros-acoes"><button class="botao" type="submit">Filtrar</button><a class="botao botao-secundario" href="' . h(url('inscricoes', array_filter($filtros) + ['exportar' => 1])) . '">Exportar para Excel</a></div>
      </form>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Formando</th><th>Cliente</th><th>Formação</th><th>Nível de IA</th><th>Estado</th><th>Inscrito em</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Sem inscrições para os filtros escolhidos.</p>'), 'Formações');
}

/* Clientes */

function admin_clientes(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $nome = texto('nome', 160);
        if ($nome === '') {
            aviso('Indique o nome do cliente.', 'erro');
            redirecionar('clientes');
        }
        $id = executar('INSERT INTO clientes (nome, nif, contacto, email, telefone, notas, criado) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$nome, texto('nif', 20), texto('contacto', 120), texto('email', 160), texto('telefone', 30), texto('notas', 2000, true), agora()]);
        aviso('Cliente criado. Crie agora o acesso de um utilizador deste cliente.');
        redirecionar('utilizadores', ['cliente' => $id]);
    }
    $lista = consulta('SELECT c.*, (SELECT COUNT(*) FROM utilizadores u WHERE u.cliente_id = c.id) AS n_utilizadores, (SELECT COUNT(*) FROM inscricoes i WHERE i.cliente_id = c.id AND i.estado != \'cancelada\') AS n_inscricoes FROM clientes c ORDER BY c.nome');
    $linhas = '';
    foreach ($lista as $c) {
        $linhas .= '<tr><td><a href="' . h(url('cliente', ['id' => $c['id']])) . '">' . h($c['nome']) . '</a></td><td>' . h($c['nif']) . '</td><td>' . h($c['contacto']) . '<br><span class="texto-suave">' . h($c['email']) . '</span></td><td>' . (int) $c['n_utilizadores'] . '</td><td>' . (int) $c['n_inscricoes'] . '</td></tr>';
    }
    pagina_area('clientes', 'Clientes', '
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Cliente</th><th>NIF</th><th>Contacto</th><th>Utilizadores</th><th>Inscrições</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há clientes.</p>') . '
      <h2 class="espaco-topo">Novo cliente</h2>
      ' . form_cliente([]), 'Gestão');
}

function form_cliente(array $c): string
{
    return '<form method="post" class="formulario">' . campo_csrf() . '<div class="campos-2">
        ' . campo('nome', 'Empresa *', (string) ($c['nome'] ?? ''), 'text', 'required maxlength="160"') . '
        ' . campo('nif', 'NIF', (string) ($c['nif'] ?? ''), 'text', 'maxlength="20"') . '
        ' . campo('contacto', 'Pessoa de contacto', (string) ($c['contacto'] ?? ''), 'text', 'maxlength="120"') . '
        ' . campo('email', 'Email', (string) ($c['email'] ?? ''), 'email', 'maxlength="160"') . '
        ' . campo('telefone', 'Telefone', (string) ($c['telefone'] ?? ''), 'tel', 'maxlength="30"') . '
      </div>' . campo('notas', 'Notas internas', (string) ($c['notas'] ?? ''), 'textarea', 'maxlength="2000"') . '
      <div><button class="botao" type="submit">' . (isset($c['id']) ? 'Guardar alterações' : 'Criar cliente') . '</button></div></form>';
}

function admin_cliente(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $c = linha_bd('SELECT * FROM clientes WHERE id = ?', [$id]);
    if (!$c) {
        pagina_erro('Cliente não encontrado', 'O cliente pedido não existe.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $nome = texto('nome', 160);
        if ($nome !== '') {
            executar('UPDATE clientes SET nome = ?, nif = ?, contacto = ?, email = ?, telefone = ?, notas = ? WHERE id = ?',
                [$nome, texto('nif', 20), texto('contacto', 120), texto('email', 160), texto('telefone', 30), texto('notas', 2000, true), $id]);
            aviso('Cliente atualizado.');
        }
        redirecionar('cliente', ['id' => $id]);
    }
    $utilizadores = consulta('SELECT * FROM utilizadores WHERE cliente_id = ? ORDER BY nome', [$id]);
    $lu = '';
    foreach ($utilizadores as $u) {
        $lu .= '<li><a href="' . h(url('utilizador', ['id' => $u['id']])) . '">' . h($u['nome']) . '</a> · ' . h($u['email']) . ($u['ativo'] ? '' : ' ' . etiqueta('Inativo')) . '</li>';
    }
    pagina_area('clientes', $c['nome'], form_cliente($c) . '
      <h2 class="espaco-topo">Utilizadores deste cliente</h2>
      ' . ($lu ? '<ul>' . $lu . '</ul>' : '<p class="texto-suave">Ainda não tem utilizadores.</p>') . '
      <p><a class="botao botao-secundario" href="' . h(url('utilizadores', ['cliente' => $id])) . '">Criar utilizador</a> <a class="botao botao-secundario" href="' . h(url('inscricoes', ['cliente' => $id])) . '">Ver inscrições</a></p>', 'Cliente');
}

/* Utilizadores */

function admin_utilizadores(): void
{
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $nome = texto('nome', 120);
        $email = texto('email', 160);
        $papel = ($_POST['papel'] ?? '') === 'admin' ? 'admin' : 'cliente';
        $cliente_id = $papel === 'cliente' ? (int) ($_POST['cliente_id'] ?? 0) : null;
        $senha = (string) ($_POST['senha'] ?? '');
        $erro = '';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Indique o nome e um email válido.';
        } elseif ($papel === 'cliente' && !isset($clientes[$cliente_id])) {
            $erro = 'Escolha o cliente deste utilizador.';
        } elseif ($senha !== '' && strlen($senha) < 10) {
            $erro = 'A palavra-passe tem de ter pelo menos 10 caracteres.';
        } elseif (linha_bd('SELECT id FROM utilizadores WHERE email = ?', [$email])) {
            $erro = 'Já existe um utilizador com este email.';
        }
        if ($erro) {
            aviso(h($erro), 'erro');
            redirecionar('utilizadores');
        }
        $gerada = $senha === '';
        $senha = $gerada ? gerar_senha() : $senha;
        executar('INSERT INTO utilizadores (email, nome, senha, papel, cliente_id, criado) VALUES (?, ?, ?, ?, ?, ?)', [$email, $nome, password_hash($senha, PASSWORD_DEFAULT), $papel, $cliente_id, agora()]);
        aviso('Utilizador criado. Dados de acesso para comunicar a ' . h($nome) . ':<br>Endereço: <strong>https://www.gadgetmetodo.pt/clientes/</strong><br>Email: <strong>' . h($email) . '</strong><br>Palavra-passe: <strong class="senha">' . h($senha) . '</strong><br>' . ($gerada ? 'Esta palavra-passe não volta a ser mostrada.' : ''));
        redirecionar('utilizadores');
    }
    $lista = consulta('SELECT u.*, c.nome AS cliente FROM utilizadores u LEFT JOIN clientes c ON c.id = u.cliente_id ORDER BY u.papel, c.nome, u.nome');
    $linhas = '';
    foreach ($lista as $u) {
        $linhas .= '<tr><td><a href="' . h(url('utilizador', ['id' => $u['id']])) . '">' . h($u['nome']) . '</a></td><td>' . h($u['email']) . '</td><td>' . h(PAPEIS[$u['papel']]) . '</td><td>' . h($u['cliente']) . '</td><td>' . ($u['ativo'] ? etiqueta('Ativo', 'ok') : etiqueta('Inativo')) . '</td><td>' . h(data_hora($u['ultimo_acesso'])) . '</td></tr>';
    }
    $pre = (int) ($_GET['cliente'] ?? 0);
    pagina_area('utilizadores', 'Utilizadores', '
      <div class="conteudo tabela-rolante"><table><thead><tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Cliente</th><th>Estado</th><th>Último acesso</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
      <h2 class="espaco-topo">Novo utilizador</h2>
      <p class="texto-suave">Perfil Gadgetmetodo: vê e gere tudo. Perfil Cliente: vê apenas as formações e as inscrições do seu cliente.</p>
      <form method="post" class="formulario">' . campo_csrf() . '<div class="campos-2">
        ' . campo('nome', 'Nome *', '', 'text', 'required maxlength="120"') . '
        ' . campo('email', 'Email (é o nome de utilizador) *', '', 'email', 'required maxlength="160" autocomplete="off"') . '
        ' . campo_select('papel', 'Perfil *', PAPEIS, 'cliente') . '
        ' . campo_select('cliente_id', 'Cliente (perfil Cliente)', $clientes, $pre, 'Escolha o cliente') . '
        ' . campo('senha', 'Palavra-passe', '', 'text', 'minlength="10" autocomplete="new-password" placeholder="Vazio: é gerada automaticamente"') . '
      </div><div><button class="botao" type="submit">Criar utilizador</button></div></form>', 'Gestão de acessos');
}

function admin_utilizador(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $u = linha_bd('SELECT * FROM utilizadores WHERE id = ?', [$id]);
    if (!$u) {
        pagina_erro('Utilizador não encontrado', 'O utilizador pedido não existe.');
    }
    $eu = (int) utilizador()['id'] === $id;
    $clientes = array_column(consulta('SELECT id, nome FROM clientes ORDER BY nome'), 'nome', 'id');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (($_POST['acao'] ?? '') === 'nova_senha') {
            $senha = gerar_senha();
            executar('UPDATE utilizadores SET senha = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $id]);
            aviso('Nova palavra-passe de ' . h($u['nome']) . ': <strong class="senha">' . h($senha) . '</strong><br>Comunique-a ao utilizador; não volta a ser mostrada.');
            redirecionar('utilizador', ['id' => $id]);
        }
        $nome = texto('nome', 120);
        $email = texto('email', 160);
        $papel = ($_POST['papel'] ?? '') === 'admin' ? 'admin' : 'cliente';
        $cliente_id = $papel === 'cliente' ? (int) ($_POST['cliente_id'] ?? 0) : null;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $erro = '';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Indique o nome e um email válido.';
        } elseif ($papel === 'cliente' && !isset($clientes[$cliente_id])) {
            $erro = 'Escolha o cliente deste utilizador.';
        } elseif ($eu && ($papel !== 'admin' || !$ativo)) {
            $erro = 'Não pode retirar o seu próprio acesso de administração.';
        } elseif (linha_bd('SELECT id FROM utilizadores WHERE email = ? AND id != ?', [$email, $id])) {
            $erro = 'Já existe outro utilizador com este email.';
        }
        if ($erro) {
            aviso(h($erro), 'erro');
        } else {
            executar('UPDATE utilizadores SET nome = ?, email = ?, papel = ?, cliente_id = ?, ativo = ? WHERE id = ?', [$nome, $email, $papel, $cliente_id, $ativo, $id]);
            aviso('Utilizador atualizado.');
        }
        redirecionar('utilizador', ['id' => $id]);
    }
    pagina_area('utilizadores', $u['nome'], '
      <form method="post" class="formulario">' . campo_csrf() . '<div class="campos-2">
        ' . campo('nome', 'Nome *', $u['nome'], 'text', 'required maxlength="120"') . '
        ' . campo('email', 'Email *', $u['email'], 'email', 'required maxlength="160"') . '
        ' . campo_select('papel', 'Perfil *', PAPEIS, $u['papel']) . '
        ' . campo_select('cliente_id', 'Cliente (perfil Cliente)', $clientes, $u['cliente_id'], 'Escolha o cliente') . '
      </div>
      <label class="opcao consentimento"><input type="checkbox" name="ativo" value="1"' . ($u['ativo'] ? ' checked' : '') . '> Acesso ativo</label>
      <div><button class="botao" type="submit">Guardar alterações</button></div></form>
      <h2 class="espaco-topo">Palavra-passe</h2>
      <p class="texto-suave">Gera uma palavra-passe nova, mostrada uma única vez, para comunicar ao utilizador.</p>
      <form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="nova_senha"><button class="botao botao-secundario" type="submit">Gerar nova palavra-passe</button></form>
      <p class="texto-suave espaco-topo">Criado em ' . h(data_hora($u['criado'])) . '. Último acesso: ' . h(data_hora($u['ultimo_acesso']) ?: 'nunca') . '.</p>', 'Utilizador');
}

/* Pedidos do site e aberturas do catálogo */

function admin_pedidos(): void
{
    $registos = ler_registos(cfg());
    if (!empty($_GET['exportar'])) {
        $linhas = [];
        foreach ($registos as $p) {
            $ab = $p['aberturas'];
            $linhas[] = [$p['ref'], data_hora($p['criado']), $p['tipo'], $p['nome'], $p['empresa'], $p['email'], $p['telemovel'], $p['servicos'], count($ab), $ab ? data_hora($ab[0]['aberto']) : '', $ab ? data_hora($ab[count($ab) - 1]['aberto']) : ''];
        }
        exportar_csv('gadgetmetodo-pedidos', ['Referência', 'Data do pedido', 'Tipo', 'Nome', 'Empresa', 'Email', 'Telemóvel', 'Serviços', 'Aberturas do catálogo', 'Primeira abertura', 'Última abertura'], $linhas);
    }
    $linhas = '';
    foreach ($registos as $p) {
        $reais = array_filter($p['aberturas'], function ($a) { return !$a['automatica']; });
        $ab = $p['aberturas'] ? implode('<br>', array_map(function ($a) { return h(data_hora($a['aberto'])) . ($a['automatica'] ? ' ' . etiqueta('automática') : ''); }, $p['aberturas'])) : etiqueta('Não aberto');
        $linhas .= '<tr><td>' . h($p['ref']) . '<br><span class="texto-suave">' . h(data_hora($p['criado'])) . '</span></td><td>' . h($p['nome']) . '<br><span class="texto-suave">' . h($p['empresa']) . '</span></td><td><a href="mailto:' . h($p['email']) . '">' . h($p['email']) . '</a><br>' . h($p['telemovel']) . '</td><td>' . h($p['servicos']) . '</td><td>' . ($reais ? etiqueta('Aberto', 'ok') . '<br>' : '') . $ab . '</td></tr>';
    }
    pagina_area('pedidos', 'Pedidos do site', '
      <p class="texto-suave">Pedidos de orçamento e de informações recebidos pelo formulário do site e aberturas do catálogo enviado na confirmação. Registos dos últimos 12 meses.</p>
      <p><a class="botao botao-secundario" href="' . h(url('pedidos', ['exportar' => 1])) . '">Exportar para Excel</a></p>
      ' . ($linhas ? '<div class="conteudo tabela-rolante"><table><thead><tr><th>Pedido</th><th>Nome e empresa</th><th>Contactos</th><th>Serviços</th><th>Catálogo</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>' : '<p class="texto-suave">Ainda não há pedidos registados.</p>'), 'Formulário do site');
}
