<?php
// 007: materiais carregados como ficheiro (manual em PDF, áudio, vídeo…), além das ligações. O ficheiro fica na pasta de
// dados (materiais/), fora da parte pública, e só é servido pela área reservada aos formandos do curso ou da turma.
// - materiais.ficheiro: nome na pasta; ficheiro_nome: nome original; tamanho (bytes); mime.
// Reversão: retira as colunas e os materiais carregados como ficheiro (os ficheiros ficam na pasta, sem uso).

declare(strict_types=1);

return [
    'descricao' => 'Materiais carregados como ficheiro (manual, áudio, vídeo), guardados fora da parte pública.',
    'subir' => function (PDO $pdo): void {
        foreach (['ficheiro TEXT', 'ficheiro_nome TEXT', 'tamanho INTEGER', 'mime TEXT'] as $c) {
            $pdo->exec('ALTER TABLE materiais ADD COLUMN ' . $c);
        }
    },
    'descer' => function (PDO $pdo): void {
        $pdo->exec('DELETE FROM materiais_acessos WHERE material_id IN (SELECT id FROM materiais WHERE ficheiro IS NOT NULL)');
        $pdo->exec('DELETE FROM materiais WHERE ficheiro IS NOT NULL');
        refazer_tabela($pdo, 'materiais', "CREATE TABLE {tabela} (
            id INTEGER PRIMARY KEY AUTOINCREMENT, curso_id INTEGER REFERENCES cursos(id), formacao_id INTEGER REFERENCES formacoes(id),
            titulo TEXT NOT NULL, tipo TEXT NOT NULL DEFAULT 'manual', url TEXT NOT NULL, ordem INTEGER NOT NULL DEFAULT 0, criado TEXT NOT NULL)");
    },
];
