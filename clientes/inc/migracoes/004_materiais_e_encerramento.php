<?php
// 004: materiais e encerramento das turmas.
// - materiais: manual, áudio ou outra ligação, de um curso (para todas as turmas) ou de uma turma.
// - materiais_acessos: envio por email ou acesso do formando, por inscrição (etapa 7 do fluxo).
// - formacoes.encerrada: data do encerramento da turma (estado "encerrada"; as inscrições passam a Encerrado).
// Reversão: apaga as duas tabelas e a coluna; as turmas encerradas voltam a "fechada".

declare(strict_types=1);

return [
    'descricao' => 'Materiais do curso ou da turma (manual, áudio, ligações), registo de envio e acesso por inscrição, e encerramento das turmas.',
    'subir' => function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE materiais (
            id INTEGER PRIMARY KEY AUTOINCREMENT, curso_id INTEGER REFERENCES cursos(id), formacao_id INTEGER REFERENCES formacoes(id),
            titulo TEXT NOT NULL, tipo TEXT NOT NULL DEFAULT 'manual', url TEXT NOT NULL, ordem INTEGER NOT NULL DEFAULT 0, criado TEXT NOT NULL)");
        $pdo->exec('CREATE INDEX materiais_curso ON materiais (curso_id)');
        $pdo->exec('CREATE INDEX materiais_formacao ON materiais (formacao_id)');
        $pdo->exec("CREATE TABLE materiais_acessos (
            id INTEGER PRIMARY KEY AUTOINCREMENT, inscricao_id INTEGER NOT NULL REFERENCES inscricoes(id) ON DELETE CASCADE,
            material_id INTEGER REFERENCES materiais(id) ON DELETE SET NULL, tipo TEXT NOT NULL, registado TEXT NOT NULL, utilizador_id INTEGER)");
        $pdo->exec('CREATE INDEX materiais_acessos_inscricao ON materiais_acessos (inscricao_id)');
        $pdo->exec('ALTER TABLE formacoes ADD COLUMN encerrada TEXT');
    },
    'descer' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE materiais_acessos');
        $pdo->exec('DROP TABLE materiais');
        $pdo->exec("UPDATE formacoes SET estado = 'fechada' WHERE estado = 'encerrada'");
        // A coluna sai com ALTER TABLE DROP COLUMN (SQLite 3.35+); em versões anteriores fica, sem uso
        try {
            $pdo->exec('ALTER TABLE formacoes DROP COLUMN encerrada');
        } catch (Throwable $e) {
        }
    },
];
