<?php
// 002: fim da pré-inscrição. Os dados de cada pré-inscrição passam para o novo modelo e a tabela é arquivada (renomeada
// para preinscricoes_arquivo, sem apagar nada):
// - o telefone passa para o formando, se ele ainda não tiver;
// - as respostas ao diagnóstico passam a um diagnóstico "respondido" do formando (origem "pré-inscrição"), ligado à
//   inscrição criada a partir dela;
// - diagnosticos ganha a coluna origem (email, publico, pre-inscricao) e preinscricao_id (para a reversão).
// Reversão: apaga os diagnósticos criados aqui, repõe os telefones e o nome da tabela.

declare(strict_types=1);

return [
    'descricao' => 'Fim da pré-inscrição: telefones e diagnósticos das pré-inscrições passam para o formando; a tabela passa a preinscricoes_arquivo (sem apagar dados).',
    'subir' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE diagnosticos ADD COLUMN origem TEXT NOT NULL DEFAULT 'email'");
        $pdo->exec('ALTER TABLE diagnosticos ADD COLUMN preinscricao_id INTEGER');
        $pdo->exec('CREATE TABLE migracao_002_telefones (formando_id INTEGER PRIMARY KEY)');
        if (!tabela_existe($pdo, 'preinscricoes')) {
            $pdo->exec('CREATE TABLE preinscricoes_arquivo (id INTEGER PRIMARY KEY)');
            return;
        }
        $telefone = $pdo->prepare("UPDATE formandos SET telefone = ? WHERE id = ? AND trim(COALESCE(telefone, '')) = ''");
        $marca = $pdo->prepare('INSERT OR IGNORE INTO migracao_002_telefones (formando_id) VALUES (?)');
        $diag = $pdo->prepare("INSERT INTO diagnosticos (utilizador_id, nome, email, curso_id, cursos, enviado, respondido, respostas, ferramentas, nivel, formando_id, inscricao_id, origem, preinscricao_id)
            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pre-inscricao', ?)");
        foreach ($pdo->query('SELECT * FROM preinscricoes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $fid = (int) valor_unico($pdo, 'SELECT id FROM formandos WHERE lower(trim(email)) = lower(trim(?))', [(string) $p['email']]);
            if (!$fid) {
                $pdo->prepare('INSERT INTO formandos (nome, email, telefone, idioma, criado) VALUES (?, ?, ?, ?, ?)')
                    ->execute([(string) $p['nome'], trim((string) $p['email']), $p['telefone'] ?: null, $p['idioma'] ?: null, (string) $p['criado']]);
                $fid = (int) $pdo->lastInsertId();
            } elseif (trim((string) $p['telefone']) !== '') {
                $telefone->execute([trim((string) $p['telefone']), $fid]);
                if ($telefone->rowCount()) {
                    $marca->execute([$fid]);
                }
            }
            if (trim((string) $p['respostas']) !== '') {
                // A inscrição criada a partir da pré-inscrição (ou a do mesmo email nesse curso)
                $iid = $p['inscricao_id'] ? (int) $p['inscricao_id'] : null;
                if (!$iid) {
                    $iid = (int) valor_unico($pdo, 'SELECT id FROM inscricoes WHERE formando_id = ? ORDER BY id DESC LIMIT 1', [$fid]) ?: null;
                }
                $nivel = null;
                if ($iid) {
                    $nivel = valor_unico($pdo, 'SELECT nivel FROM inscricoes WHERE id = ?', [$iid]) ?: null;
                }
                $ferramentas = trim((string) $p['ferramentas']) !== '' ? json_encode(array_map('trim', explode(',', (string) $p['ferramentas'])), JSON_UNESCAPED_UNICODE) : null;
                $diag->execute([(string) $p['nome'], trim((string) $p['email']), (int) $p['curso_id'], json_encode([(int) $p['curso_id']]), (string) $p['criado'], (string) $p['criado'],
                    (string) $p['respostas'], $ferramentas, $nivel, $fid, $iid, (int) $p['id']]);
                if ($iid) {
                    $pdo->prepare('UPDATE inscricoes SET diagnostico_id = COALESCE(diagnostico_id, ?) WHERE id = ?')->execute([(int) $pdo->lastInsertId(), $iid]);
                }
            }
        }
        $pdo->exec('ALTER TABLE preinscricoes RENAME TO preinscricoes_arquivo');
        $pdo->exec('DROP INDEX IF EXISTS preinscricoes_curso');
    },
    'descer' => function (PDO $pdo): void {
        $pdo->exec('UPDATE inscricoes SET diagnostico_id = NULL WHERE diagnostico_id IN (SELECT id FROM diagnosticos WHERE preinscricao_id IS NOT NULL)');
        $pdo->exec('DELETE FROM diagnosticos WHERE preinscricao_id IS NOT NULL');
        $pdo->exec('UPDATE formandos SET telefone = NULL WHERE id IN (SELECT formando_id FROM migracao_002_telefones)');
        $pdo->exec('DROP TABLE migracao_002_telefones');
        if (coluna_existe($pdo, 'preinscricoes_arquivo', 'email')) {
            $pdo->exec('ALTER TABLE preinscricoes_arquivo RENAME TO preinscricoes');
            $pdo->exec('CREATE INDEX IF NOT EXISTS preinscricoes_curso ON preinscricoes (curso_id)');
        } else {
            $pdo->exec('DROP TABLE preinscricoes_arquivo');
        }
        refazer_tabela($pdo, 'diagnosticos', "CREATE TABLE {tabela} (
            id INTEGER PRIMARY KEY AUTOINCREMENT, utilizador_id INTEGER, nome TEXT NOT NULL, email TEXT NOT NULL, curso_id INTEGER,
            token TEXT, validade TEXT, enviado TEXT NOT NULL, enviado_por INTEGER, respondido TEXT, respostas TEXT,
            ferramentas TEXT, pontuacao INTEGER, nivel TEXT, cursos TEXT, formando_id INTEGER, inscricao_id INTEGER)");
    },
];
