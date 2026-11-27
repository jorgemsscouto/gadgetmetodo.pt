<?php
// 008: reorganização da formação (curso, turmas com ciclos de horários próprios, pré-inscritos e diagnóstico inicial).
// - Diagnóstico inicial: as inscrições sem diagnóstico próprio passam a apontar para o primeiro diagnóstico respondido
//   do formando, que serve para todos os cursos seguintes (os ids alterados ficam em migracao_008_diagnosticos).
// - Pré-inscritos: o novo estado "pre_inscrito" não precisa de alterar a estrutura (o estado é texto); as inscrições
//   existentes ficam como estão.
// - Turmas: o formato (ciclo de horários) volta a ser escolhido em cada turma, na coluna formacoes.formato, que já
//   existe; as turmas gravadas mantêm as suas sessões.
// Reversão: as inscrições alteradas voltam a ficar sem diagnóstico; as pré-inscrições passam a Inscrito.

declare(strict_types=1);

return [
    'descricao' => 'Reorganização da formação: diagnóstico inicial do formando nas inscrições sem diagnóstico e estado "Pré-inscrito".',
    'subir' => function (PDO $pdo): void {
        $pdo->exec('CREATE TABLE migracao_008_diagnosticos (inscricao_id INTEGER PRIMARY KEY)');
        $pdo->exec("INSERT INTO migracao_008_diagnosticos (inscricao_id)
            SELECT i.id FROM inscricoes i WHERE i.diagnostico_id IS NULL AND i.formando_id IS NOT NULL
            AND EXISTS (SELECT 1 FROM diagnosticos d WHERE d.formando_id = i.formando_id AND d.respondido IS NOT NULL)");
        $pdo->exec("UPDATE inscricoes SET diagnostico_id = (SELECT d.id FROM diagnosticos d WHERE d.formando_id = inscricoes.formando_id
                AND d.respondido IS NOT NULL ORDER BY d.respondido, d.id LIMIT 1)
            WHERE id IN (SELECT inscricao_id FROM migracao_008_diagnosticos)");
    },
    'descer' => function (PDO $pdo): void {
        $pdo->exec('UPDATE inscricoes SET diagnostico_id = NULL WHERE id IN (SELECT inscricao_id FROM migracao_008_diagnosticos)');
        $pdo->exec('DROP TABLE migracao_008_diagnosticos');
        $pdo->exec("UPDATE inscricoes SET estado = 'inscrito' WHERE estado = 'pre_inscrito'");
    },
];
