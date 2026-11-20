<?php
// 003: ficha para o certificado com campos configuráveis (Configuração, Certificados): tipo de documento (Cartão de
// Cidadão, passaporte ou título de residência; os dados já guardados são de Cartão de Cidadão), data de nascimento, NIF,
// naturalidade e nacionalidade (desligados por omissão). O número e a validade do documento continuam em cc_numero e
// cc_validade. Certificados: tipo de documento com que foram emitidos.

declare(strict_types=1);

return [
    'descricao' => 'Ficha para o certificado: tipo de documento e campos opcionais (data de nascimento, NIF, naturalidade e nacionalidade).',
    'subir' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE fichas_certificado ADD COLUMN documento_tipo TEXT NOT NULL DEFAULT 'cc'");
        foreach (['data_nascimento', 'nif', 'naturalidade', 'nacionalidade'] as $c) {
            $pdo->exec('ALTER TABLE fichas_certificado ADD COLUMN ' . $c . ' TEXT');
        }
        $pdo->exec("ALTER TABLE certificados ADD COLUMN documento_tipo TEXT NOT NULL DEFAULT 'cc'");
    },
    'descer' => function (PDO $pdo): void {
        refazer_tabela($pdo, 'fichas_certificado', 'CREATE TABLE {tabela} (
            id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE COLLATE NOCASE, nome_completo TEXT NOT NULL,
            cc_numero TEXT NOT NULL, cc_validade TEXT NOT NULL, atualizado TEXT NOT NULL, atualizado_por INTEGER)');
        refazer_tabela($pdo, 'certificados', 'CREATE TABLE {tabela} (
            id INTEGER PRIMARY KEY AUTOINCREMENT, inscricao_id INTEGER NOT NULL UNIQUE REFERENCES inscricoes(id) ON DELETE CASCADE,
            ano INTEGER NOT NULL, numero INTEGER NOT NULL, emitido TEXT NOT NULL, nome TEXT NOT NULL, cc_numero TEXT NOT NULL,
            cc_validade TEXT NOT NULL, presencas INTEGER NOT NULL, sessoes INTEGER NOT NULL, enviado TEXT, formador TEXT, formador_dados TEXT, UNIQUE (ano, numero))');
    },
];
