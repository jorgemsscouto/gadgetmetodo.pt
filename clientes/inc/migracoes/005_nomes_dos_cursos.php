<?php
// 005: nomes uniformes dos cursos: "Curso N: <título> (6 sessões)" e "Curso N: <título> (3 × 2 h)" (pelo número de
// temas e as horas de cada um; o sufixo antigo "(formato 2 h)" sai). As turmas guardam o nome do curso (formacoes.titulo)
// e acompanham. Os nomes antigos ficam em migracao_005_nomes, para a reversão.

declare(strict_types=1);

return [
    'descricao' => 'Nomes uniformes dos cursos: "Curso N: título (6 sessões)" e "Curso N: título (3 × 2 h)", também nas turmas.',
    'subir' => function (PDO $pdo): void {
        $pdo->exec('CREATE TABLE migracao_005_nomes (curso_id INTEGER PRIMARY KEY, nome TEXT NOT NULL)');
        $guardar = $pdo->prepare('INSERT INTO migracao_005_nomes (curso_id, nome) VALUES (?, ?)');
        $mudar = $pdo->prepare('UPDATE cursos SET nome = ? WHERE id = ?');
        $turmas = $pdo->prepare('UPDATE formacoes SET titulo = ? WHERE curso_id = ?');
        foreach ($pdo->query('SELECT id, nome FROM cursos')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $horas = array_map('floatval', $pdo->query('SELECT COALESCE(horas, 1) FROM modulos WHERE curso_id = ' . (int) $c['id'] . ' ORDER BY ordem')->fetchAll(PDO::FETCH_COLUMN));
            $n = count($horas);
            if (!$n) {
                continue;
            }
            $iguais = count(array_unique(array_map('strval', $horas))) === 1;
            $h = $iguais ? $horas[0] : 0;
            $sufixo = $iguais && $h > 1 ? ' (' . $n . ' × ' . rtrim(rtrim(number_format($h, 1, ',', ''), '0'), ',') . ' h)' : ' (' . $n . ($n === 1 ? ' sessão)' : ' sessões)');
            $base = trim((string) preg_replace('/\s*\((formato [0-9,]+ ?h|\d+ sess(ão|ões)|\d+ × [0-9,]+ ?h)\)\s*$/u', '', (string) $c['nome']));
            $novo = $base . $sufixo;
            if ($novo !== $c['nome']) {
                $guardar->execute([$c['id'], $c['nome']]);
                $mudar->execute([$novo, $c['id']]);
                $turmas->execute([$novo, $c['id']]);
            }
        }
    },
    'descer' => function (PDO $pdo): void {
        foreach ($pdo->query('SELECT curso_id, nome FROM migracao_005_nomes')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pdo->prepare('UPDATE cursos SET nome = ? WHERE id = ?')->execute([$r['nome'], $r['curso_id']]);
            $pdo->prepare('UPDATE formacoes SET titulo = ? WHERE curso_id = ?')->execute([$r['nome'], $r['curso_id']]);
        }
        $pdo->exec('DROP TABLE migracao_005_nomes');
    },
];
