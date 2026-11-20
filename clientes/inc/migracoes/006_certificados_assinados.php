<?php
// 006: certificados assinados digitalmente. O PDF é descarregado para assinar fora do site (por exemplo com o Cartão de
// Cidadão ou a Chave Móvel Digital) e carregado depois; fica na pasta de dados (certificados-assinados/) e passa a ser o
// que se envia ao formando e se descarrega na área do formando.
// - certificados.assinado: data do carregamento; assinado_ficheiro: nome do ficheiro na pasta; assinado_por: utilizador.
// Reversão: retira as colunas (os ficheiros ficam na pasta, sem uso).

declare(strict_types=1);

return [
    'descricao' => 'Certificados assinados digitalmente: data, ficheiro e quem carregou a versão assinada do PDF.',
    'subir' => function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE certificados ADD COLUMN assinado TEXT');
        $pdo->exec('ALTER TABLE certificados ADD COLUMN assinado_ficheiro TEXT');
        $pdo->exec('ALTER TABLE certificados ADD COLUMN assinado_por INTEGER');
    },
    'descer' => function (PDO $pdo): void {
        refazer_tabela($pdo, 'certificados', "CREATE TABLE {tabela} (
            id INTEGER PRIMARY KEY AUTOINCREMENT, inscricao_id INTEGER NOT NULL UNIQUE REFERENCES inscricoes(id) ON DELETE CASCADE,
            ano INTEGER NOT NULL, numero INTEGER NOT NULL, emitido TEXT NOT NULL, nome TEXT NOT NULL, cc_numero TEXT NOT NULL,
            cc_validade TEXT NOT NULL, presencas INTEGER NOT NULL, sessoes INTEGER NOT NULL, enviado TEXT, formador TEXT, formador_dados TEXT,
            documento_tipo TEXT NOT NULL DEFAULT 'cc', UNIQUE (ano, numero))");
    },
];
