<?php
// Migrações da base de dados: alterações de estrutura versionadas e reversíveis, em clientes/inc/migracoes/NNN_nome.php.
// Cada ficheiro devolve ['descricao' => texto, 'subir' => function (PDO $pdo), 'descer' => function (PDO $pdo)].
// As migrações por aplicar são aplicadas por ordem na primeira ligação à base de dados (depois de publicar o código que
// precisa delas) e podem ser aplicadas ou revertidas em Configuração, Migrações, ou pelo endereço com chave
// (clientes/migrar.php). Antes de cada aplicação ou reversão é feita uma cópia da base de dados em dados/copias/.
// Cada migração corre numa transação: se falhar, nada fica alterado.

declare(strict_types=1);

const MIGRACOES_DIR = __DIR__ . '/migracoes';
const COPIAS_MANTIDAS = 20;

// Migrações conhecidas, por ordem do nome do ficheiro: id => definição
function lista_migracoes(): array
{
    static $lista = null;
    if ($lista === null) {
        $lista = [];
        foreach (glob(MIGRACOES_DIR . '/[0-9][0-9][0-9]_*.php') ?: [] as $f) {
            $def = require $f;
            if (is_array($def) && isset($def['subir'], $def['descer'])) {
                $lista[basename($f, '.php')] = $def;
            }
        }
        ksort($lista);
    }
    return $lista;
}

function preparar_tabela_migracoes(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS migracoes (id TEXT PRIMARY KEY, aplicada TEXT NOT NULL, por INTEGER, copia TEXT)');
}

function migracoes_aplicadas(PDO $pdo): array
{
    preparar_tabela_migracoes($pdo);
    return array_column($pdo->query('SELECT id, aplicada, por, copia FROM migracoes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), null, 'id');
}

function migracoes_pendentes(PDO $pdo): array
{
    return array_diff_key(lista_migracoes(), migracoes_aplicadas($pdo));
}

// Cópia da base de dados (consistente, com VACUUM INTO) para dados/copias/; mantém as COPIAS_MANTIDAS mais recentes.
// Devolve o nome do ficheiro ou lança uma exceção (sem cópia não se migra).
function copia_seguranca(PDO $pdo, string $motivo): string
{
    $pasta = pasta_dados(cfg());
    if ($pasta === null) {
        throw new RuntimeException('Sem pasta de dados para a cópia de segurança.');
    }
    $dir = $pasta . '/copias';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        throw new RuntimeException('Não foi possível criar a pasta das cópias de segurança.');
    }
    $nome = 'registos-' . date('Ymd-His') . '-' . preg_replace('/[^a-z0-9_]+/i', '-', $motivo) . '.sqlite';
    $destino = $dir . '/' . $nome;
    try {
        $pdo->exec('VACUUM INTO ' . $pdo->quote($destino));
    } catch (Throwable $e) {
        // SQLite anterior à 3.27 (sem VACUUM INTO): cópia do ficheiro
        @copy($pasta . '/registos.sqlite', $destino);
    }
    if (!is_file($destino) || filesize($destino) === 0) {
        throw new RuntimeException('A cópia de segurança não foi criada.');
    }
    $copias = glob($dir . '/registos-*.sqlite') ?: [];
    sort($copias);
    foreach (array_slice($copias, 0, max(0, count($copias) - COPIAS_MANTIDAS)) as $velha) {
        @unlink($velha);
    }
    return $nome;
}

// Aplica uma migração (cópia, transação, registo). Devolve null ou a mensagem de erro.
function aplicar_migracao(PDO $pdo, string $id, ?int $por = null): ?string
{
    $def = lista_migracoes()[$id] ?? null;
    if (!$def) {
        return 'Migração desconhecida: ' . $id;
    }
    if (isset(migracoes_aplicadas($pdo)[$id])) {
        return null;
    }
    try {
        $copia = copia_seguranca($pdo, 'antes-' . $id);
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('BEGIN IMMEDIATE');
        if (isset(migracoes_aplicadas($pdo)[$id])) {
            $pdo->exec('COMMIT');
            return null;
        }
        $def['subir']($pdo);
        $pdo->prepare('INSERT INTO migracoes (id, aplicada, por, copia) VALUES (?, ?, ?, ?)')->execute([$id, date('Y-m-d H:i:s'), $por, $copia]);
        $pdo->exec('COMMIT');
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK');
        }
        error_log('Migração ' . $id . ' falhou: ' . $e->getMessage());
        return 'A migração ' . $id . ' falhou e não alterou nada: ' . $e->getMessage();
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
}

// Reverte uma migração aplicada (só a última, para manter a ordem). Devolve null ou a mensagem de erro.
function reverter_migracao(PDO $pdo, string $id, ?int $por = null): ?string
{
    $aplicadas = migracoes_aplicadas($pdo);
    $def = lista_migracoes()[$id] ?? null;
    if (!$def || !isset($aplicadas[$id])) {
        return 'A migração ' . $id . ' não está aplicada.';
    }
    if (array_key_last($aplicadas) !== $id) {
        return 'Reverta primeiro as migrações aplicadas depois desta.';
    }
    try {
        copia_seguranca($pdo, 'antes-reverter-' . $id);
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('BEGIN IMMEDIATE');
        $def['descer']($pdo);
        $pdo->prepare('DELETE FROM migracoes WHERE id = ?')->execute([$id]);
        // Pausa a aplicação automática até alguém aplicar de novo à mão (senão o pedido seguinte voltava a aplicá-la)
        $pdo->prepare('INSERT OR REPLACE INTO definicoes (chave, valor) VALUES (?, ?)')->execute(['migracoes_pausa', $id]);
        $pdo->exec('COMMIT');
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK');
        }
        error_log('Reversão da migração ' . $id . ' falhou: ' . $e->getMessage());
        return 'A reversão da migração ' . $id . ' falhou e não alterou nada: ' . $e->getMessage();
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
}

// Na primeira ligação depois de publicar: aplica as migrações em falta, por ordem; para na primeira que falhar
function aplicar_migracoes_pendentes(PDO $pdo, bool $manual = false): ?string
{
    if ($manual) {
        $pdo->exec("DELETE FROM definicoes WHERE chave = 'migracoes_pausa'");
    } elseif (definicao($pdo, 'migracoes_pausa') !== null) {
        return null;
    }
    foreach (array_keys(migracoes_pendentes($pdo)) as $id) {
        if ($erro = aplicar_migracao($pdo, $id)) {
            return $erro;
        }
    }
    return null;
}

/* Utilitários para os scripts de migração */

function coluna_existe(PDO $pdo, string $tabela, string $coluna): bool
{
    return in_array($coluna, array_column($pdo->query('PRAGMA table_info(' . $tabela . ')')->fetchAll(PDO::FETCH_ASSOC), 'name'), true);
}

function tabela_existe(PDO $pdo, string $tabela): bool
{
    $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
    $st->execute([$tabela]);
    return (bool) $st->fetchColumn();
}

// Refaz uma tabela com uma nova definição (o SQLite não altera restrições nem tira colunas): cria a nova, copia as
// colunas comuns (ou as indicadas em $copiar, "coluna_nova" => "expressão sobre a antiga"), apaga a antiga e renomeia
function refazer_tabela(PDO $pdo, string $tabela, string $criar, array $copiar = []): void
{
    $novo = $tabela . '_novo';
    $pdo->exec('DROP TABLE IF EXISTS ' . $novo);
    $pdo->exec(str_replace('{tabela}', $novo, $criar));
    if (!$copiar) {
        $antigas = array_column($pdo->query('PRAGMA table_info(' . $tabela . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        $novas = array_column($pdo->query('PRAGMA table_info(' . $novo . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        $comuns = array_values(array_intersect($novas, $antigas));
        $copiar = array_combine($comuns, $comuns);
    }
    $pdo->exec('INSERT INTO ' . $novo . ' (' . implode(', ', array_keys($copiar)) . ') SELECT ' . implode(', ', $copiar) . ' FROM ' . $tabela);
    $pdo->exec('DROP TABLE ' . $tabela);
    $pdo->exec('ALTER TABLE ' . $novo . ' RENAME TO ' . $tabela);
}

/* Configuração, Migrações (administração) */

function chave_migracoes(bool $nova = false): string
{
    $chave = definicao(bd(), 'migracoes_chave');
    if ($nova || !$chave) {
        $chave = bin2hex(random_bytes(20));
        definir(bd(), 'migracoes_chave', $chave);
    }
    return $chave;
}

function admin_migracoes(): void
{
    $pdo = bd();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $acao = (string) ($_POST['acao'] ?? '');
        $id = (string) ($_POST['id'] ?? '');
        if ($acao === 'aplicar') {
            $erro = aplicar_migracoes_pendentes($pdo, true);
            aviso($erro ? h($erro) : 'Migrações aplicadas, com cópia de segurança antes de cada uma.', $erro ? 'erro' : 'ok');
        } elseif ($acao === 'reverter' && !empty($_POST['confirmar'])) {
            $erro = reverter_migracao($pdo, $id, (int) utilizador()['id']);
            aviso($erro ? h($erro) : 'Migração ' . h($id) . ' revertida, com cópia de segurança antes. Publique a versão do código anterior a esta migração.', $erro ? 'erro' : 'ok');
        } elseif ($acao === 'reverter') {
            aviso('Marque a confirmação para reverter.', 'erro');
        } elseif ($acao === 'chave') {
            chave_migracoes(true);
            aviso('Nova chave gerada: o endereço anterior deixou de funcionar.');
        }
        redirecionar('migracoes');
    }
    $aplicadas = migracoes_aplicadas($pdo);
    $ultima = array_key_last($aplicadas);
    $linhas = '';
    foreach (lista_migracoes() as $id => $def) {
        $a = $aplicadas[$id] ?? null;
        $linhas .= '<tr><td><code>' . h($id) . '</code></td><td>' . h((string) $def['descricao']) . '</td><td>'
            . ($a ? etiqueta('Aplicada', 'ok') . '<br><span class="texto-suave">' . h(data_hora($a['aplicada'])) . '</span>' : etiqueta('Por aplicar', 'info')) . '</td>'
            . '<td><span class="texto-suave">' . h((string) ($a['copia'] ?? '')) . '</span></td><td>'
            . ($id === $ultima ? '<form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="reverter"><input type="hidden" name="id" value="' . h($id) . '">'
                . '<label class="opcao"><input type="checkbox" name="confirmar" value="1"> <span>Confirmo</span></label> <button class="botao botao-secundario botao-pequeno" type="submit">Reverter</button></form>' : '') . '</td></tr>';
    }
    $pendentes = migracoes_pendentes($pdo);
    $endereco = endereco_site() . '/clientes/migrar.php?chave=' . chave_migracoes();
    pagina_area('migracoes', 'Migrações da base de dados', '
      <p class="texto-suave">Alterações à estrutura da base de dados, versionadas e reversíveis. As migrações novas são aplicadas automaticamente na primeira utilização depois de publicar o código; antes de cada migração (ou reversão) é guardada uma cópia da base de dados na pasta de dados (copias/), e cada migração corre numa transação: se falhar, nada fica alterado. Só se reverte a última migração aplicada; depois de reverter, publique a versão do código anterior a essa migração.</p>
      ' . (definicao($pdo, 'migracoes_pausa') !== null ? '<div class="aviso aviso-info">A aplicação automática está em pausa desde a reversão de ' . h((string) definicao($pdo, 'migracoes_pausa')) . '. Use "Aplicar" para voltar a aplicar as migrações por aplicar.</div>' : '') . '
      ' . (!empty($GLOBALS['erro_migracao']) ? '<div class="aviso aviso-erro">' . h($GLOBALS['erro_migracao']) . '</div>' : '') . '
      <div class="conteudo tabela-rolante"><table><thead><tr><th>Migração</th><th>Descrição</th><th>Estado</th><th>Cópia de segurança</th><th><span class="visualmente-oculto">Ações</span></th></tr></thead><tbody>' . ($linhas ?: '<tr><td colspan="5" class="texto-suave">Ainda não há migrações.</td></tr>') . '</tbody></table></div>
      ' . ($pendentes ? '<form method="post" class="espaco-topo">' . campo_csrf() . '<input type="hidden" name="acao" value="aplicar"><button class="botao" type="submit">Aplicar ' . count($pendentes) . ' migração(ões) por aplicar</button></form>' : '') . '
      <h2 class="espaco-topo">Reverter sem acesso à área reservada</h2>
      <p class="texto-suave">Se depois de publicar a área reservada deixar de abrir, este endereço mostra o estado das migrações e permite reverter a última: acrescente <code>&amp;acao=reverter&amp;id=NOME</code>. Guarde-o num local seguro; se for divulgado, gere uma chave nova.</p>
      <div class="partilha-ligacao"><label class="visualmente-oculto" for="endereco-migracoes">Endereço das migrações</label><input id="endereco-migracoes" type="text" readonly value="' . h($endereco) . '"><button class="botao botao-secundario" type="button" data-copiar="' . h($endereco) . '">Copiar endereço</button></div>
      <p class="estado-copia" role="status" aria-live="polite"></p>
      <form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="chave"><button class="botao-texto" type="submit">Gerar nova chave</button></form>', 'Configuração');
}
