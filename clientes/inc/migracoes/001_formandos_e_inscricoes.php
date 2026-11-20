<?php
// 001: formandos como entidade própria e inscrições no novo fluxo.
// - Tabela formandos (uma linha por pessoa, pelo email; sem email, uma por inscrição), criada a partir das inscrições,
//   dos utilizadores Formando, dos diagnósticos e das pré-inscrições existentes.
// - Inscrições: turma (formacao_id) opcional, curso_id, formando_id, estados do novo fluxo (inscrito, em_formacao,
//   concluido, certificado, encerrado, desistiu, nao_concluido), avaliação, validação da assiduidade pelo formador,
//   email da inscrição e encerramento. O estado anterior fica em estado_antigo, para a reversão.
// - Diagnósticos: formando_id e inscricao_id (a inscrição criada quando o diagnóstico é respondido).
// Reversão: repõe as tabelas como estavam (as inscrições criadas depois sem turma passam para inscricoes_sem_turma).

declare(strict_types=1);

const INSCRICOES_001 = "CREATE TABLE {tabela} (
    id INTEGER PRIMARY KEY AUTOINCREMENT, formando_id INTEGER REFERENCES formandos(id), curso_id INTEGER REFERENCES cursos(id),
    formacao_id INTEGER REFERENCES formacoes(id), cliente_id INTEGER REFERENCES clientes(id), utilizador_id INTEGER,
    origem TEXT NOT NULL DEFAULT 'area', empresa TEXT, nif TEXT, contacto_nome TEXT, contacto_email TEXT, contacto_telefone TEXT,
    formando TEXT NOT NULL, email TEXT, funcao TEXT, respostas TEXT, pontuacao INTEGER, responsavel INTEGER, nivel TEXT,
    ferramentas TEXT, expectativas TEXT, estado TEXT NOT NULL DEFAULT 'inscrito', criado TEXT NOT NULL, tipo_cliente TEXT,
    dados_pedidos TEXT, idioma TEXT, diagnostico_id INTEGER, avaliacao TEXT NOT NULL DEFAULT 'por_fazer', avaliacao_nota TEXT,
    avaliacao_obs TEXT, assiduidade_validada TEXT, assiduidade_por INTEGER, email_inscricao TEXT, encerrada TEXT,
    estado_antigo TEXT)";

const INSCRICOES_ANTES_001 = "CREATE TABLE {tabela} (
    id INTEGER PRIMARY KEY AUTOINCREMENT, formacao_id INTEGER NOT NULL REFERENCES formacoes(id),
    cliente_id INTEGER REFERENCES clientes(id), utilizador_id INTEGER, origem TEXT NOT NULL DEFAULT 'area',
    empresa TEXT, nif TEXT, contacto_nome TEXT, contacto_email TEXT, contacto_telefone TEXT, formando TEXT NOT NULL,
    email TEXT, funcao TEXT, respostas TEXT, pontuacao INTEGER, responsavel INTEGER, nivel TEXT,
    ferramentas TEXT, expectativas TEXT, estado TEXT NOT NULL DEFAULT 'pendente', criado TEXT NOT NULL, tipo_cliente TEXT,
    dados_pedidos TEXT, idioma TEXT)";

return [
    'descricao' => 'Formandos como entidade própria; inscrições com turma opcional, curso, estados do novo fluxo, avaliação e validação da assiduidade; diagnósticos ligados ao formando.',
    'subir' => function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE formandos (
            id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, email TEXT COLLATE NOCASE, telefone TEXT,
            cliente_id INTEGER REFERENCES clientes(id), consentimento TEXT, idioma TEXT, notas TEXT, criado TEXT NOT NULL, criado_por INTEGER)");
        $pdo->exec("CREATE UNIQUE INDEX formandos_email ON formandos (lower(trim(email))) WHERE trim(COALESCE(email, '')) != ''");

        // Pessoas existentes, pelo email (a fonte mais recente dá o nome); as inscrições sem email ficam uma a uma
        $pessoas = [];
        $juntar = function (string $email, string $nome, ?int $cliente, ?string $telefone, string $criado, ?string $idioma) use (&$pessoas) {
            $k = mb_strtolower(trim($email));
            if ($k === '') {
                return;
            }
            $p = $pessoas[$k] ?? ['email' => trim($email), 'nome' => '', 'cliente' => null, 'telefone' => null, 'criado' => $criado, 'idioma' => null];
            $p['nome'] = $p['nome'] !== '' ? $p['nome'] : trim($nome);
            $p['cliente'] = $p['cliente'] ?? $cliente;
            $p['telefone'] = $p['telefone'] ?? ($telefone !== null && trim($telefone) !== '' ? trim($telefone) : null);
            $p['idioma'] = $p['idioma'] ?? $idioma;
            $p['criado'] = min($p['criado'], $criado);
            $pessoas[$k] = $p;
        };
        foreach ($pdo->query("SELECT * FROM utilizadores WHERE papel = 'formando' ORDER BY id DESC") as $u) {
            $juntar((string) $u['email'], (string) $u['nome'], $u['cliente_id'] ? (int) $u['cliente_id'] : null, null, (string) $u['criado'], $u['idioma'] ?: null);
        }
        foreach ($pdo->query('SELECT * FROM inscricoes ORDER BY id DESC') as $i) {
            $juntar((string) $i['email'], (string) $i['formando'], $i['cliente_id'] ? (int) $i['cliente_id'] : null, null, (string) $i['criado'], $i['idioma'] ?: null);
        }
        foreach ($pdo->query('SELECT * FROM diagnosticos ORDER BY id DESC') as $d) {
            $juntar((string) $d['email'], (string) $d['nome'], null, null, (string) $d['enviado'], null);
        }
        if (tabela_existe($pdo, 'preinscricoes')) {
            foreach ($pdo->query('SELECT * FROM preinscricoes ORDER BY id DESC') as $q) {
                $juntar((string) $q['email'], (string) $q['nome'], null, $q['telefone'], (string) $q['criado'], $q['idioma'] ?: null);
            }
        }
        $inserir = $pdo->prepare('INSERT INTO formandos (nome, email, telefone, cliente_id, idioma, criado) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($pessoas as $p) {
            $inserir->execute([$p['nome'] !== '' ? $p['nome'] : $p['email'], $p['email'], $p['telefone'], $p['cliente'], $p['idioma'], $p['criado']]);
        }
        $sem_email = [];
        foreach ($pdo->query("SELECT id, formando, cliente_id, criado FROM inscricoes WHERE trim(COALESCE(email, '')) = ''") as $i) {
            $inserir->execute([(string) $i['formando'], null, null, $i['cliente_id'] ?: null, null, (string) $i['criado']]);
            $sem_email[(int) $i['id']] = (int) $pdo->lastInsertId();
        }

        // Inscrições com o novo modelo
        refazer_tabela($pdo, 'inscricoes', INSCRICOES_001, [
            'id' => 'id', 'formando_id' => "(SELECT f.id FROM formandos f WHERE trim(COALESCE(inscricoes.email, '')) != '' AND lower(trim(f.email)) = lower(trim(inscricoes.email)))",
            'curso_id' => '(SELECT t.curso_id FROM formacoes t WHERE t.id = inscricoes.formacao_id)',
            'formacao_id' => 'formacao_id', 'cliente_id' => 'cliente_id', 'utilizador_id' => 'utilizador_id', 'origem' => 'origem', 'empresa' => 'empresa', 'nif' => 'nif',
            'contacto_nome' => 'contacto_nome', 'contacto_email' => 'contacto_email', 'contacto_telefone' => 'contacto_telefone', 'formando' => 'formando', 'email' => 'email',
            'funcao' => 'funcao', 'respostas' => 'respostas', 'pontuacao' => 'pontuacao', 'responsavel' => 'responsavel', 'nivel' => 'nivel', 'ferramentas' => 'ferramentas',
            'expectativas' => 'expectativas', 'criado' => 'criado', 'tipo_cliente' => 'tipo_cliente', 'dados_pedidos' => 'dados_pedidos', 'idioma' => 'idioma',
            'estado_antigo' => 'estado',
            // Estados: cancelada → desistiu; com certificado → certificado; turma já começada → em_formacao; restantes → inscrito
            'estado' => "CASE WHEN estado = 'cancelada' THEN 'desistiu'
                WHEN EXISTS (SELECT 1 FROM certificados c WHERE c.inscricao_id = inscricoes.id) THEN 'certificado'
                WHEN (SELECT MIN(COALESCE(s.inicio, t.inicio)) FROM formacoes t LEFT JOIN sessoes s ON s.formacao_id = t.id WHERE t.id = inscricoes.formacao_id) <= datetime('now', 'localtime') THEN 'em_formacao'
                ELSE 'inscrito' END",
        ]);
        $ligar = $pdo->prepare('UPDATE inscricoes SET formando_id = ? WHERE id = ?');
        foreach ($sem_email as $iid => $fid) {
            $ligar->execute([$fid, $iid]);
        }
        $pdo->exec('CREATE INDEX inscricoes_formacao ON inscricoes (formacao_id)');
        $pdo->exec('CREATE INDEX inscricoes_cliente ON inscricoes (cliente_id)');
        $pdo->exec('CREATE INDEX inscricoes_formando ON inscricoes (formando_id)');
        $pdo->exec('CREATE INDEX inscricoes_curso ON inscricoes (curso_id)');

        // Diagnósticos ligados ao formando
        $pdo->exec('ALTER TABLE diagnosticos ADD COLUMN formando_id INTEGER');
        $pdo->exec('ALTER TABLE diagnosticos ADD COLUMN inscricao_id INTEGER');
        $pdo->exec("UPDATE diagnosticos SET formando_id = (SELECT f.id FROM formandos f WHERE lower(trim(f.email)) = lower(trim(diagnosticos.email)))");
    },
    'descer' => function (PDO $pdo): void {
        // Inscrições sem turma (só existem no novo fluxo) ficam guardadas à parte
        $pdo->exec('DROP TABLE IF EXISTS inscricoes_sem_turma');
        $pdo->exec('CREATE TABLE inscricoes_sem_turma AS SELECT * FROM inscricoes WHERE formacao_id IS NULL');
        $pdo->exec('DELETE FROM inscricoes WHERE formacao_id IS NULL');
        $copiar = [];
        foreach (['id', 'formacao_id', 'cliente_id', 'utilizador_id', 'origem', 'empresa', 'nif', 'contacto_nome', 'contacto_email', 'contacto_telefone', 'formando',
            'email', 'funcao', 'respostas', 'pontuacao', 'responsavel', 'nivel', 'ferramentas', 'expectativas', 'criado', 'tipo_cliente', 'dados_pedidos', 'idioma'] as $c) {
            $copiar[$c] = $c;
        }
        $copiar['estado'] = "COALESCE(estado_antigo, CASE WHEN estado IN ('desistiu', 'nao_concluido') THEN 'cancelada' ELSE 'confirmada' END)";
        refazer_tabela($pdo, 'inscricoes', INSCRICOES_ANTES_001, $copiar);
        $pdo->exec('CREATE INDEX inscricoes_formacao ON inscricoes (formacao_id)');
        $pdo->exec('CREATE INDEX inscricoes_cliente ON inscricoes (cliente_id)');
        refazer_tabela($pdo, 'diagnosticos', "CREATE TABLE {tabela} (
            id INTEGER PRIMARY KEY AUTOINCREMENT, utilizador_id INTEGER, nome TEXT NOT NULL, email TEXT NOT NULL, curso_id INTEGER,
            token TEXT, validade TEXT, enviado TEXT NOT NULL, enviado_por INTEGER, respondido TEXT, respostas TEXT,
            ferramentas TEXT, pontuacao INTEGER, nivel TEXT, cursos TEXT)");
        $pdo->exec('DROP TABLE formandos');
    },
];
