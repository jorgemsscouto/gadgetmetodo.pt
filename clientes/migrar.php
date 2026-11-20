<?php
// Migrações da base de dados sem a área reservada (por exemplo, se a área deixar de abrir depois de publicar):
// ?chave=…            estado das migrações
// ?chave=…&acao=reverter&id=NNN_nome   reverte a última migração aplicada (com cópia de segurança antes)
// Na linha de comandos: php clientes/migrar.php [reverter NNN_nome]

declare(strict_types=1);

require __DIR__ . '/inc/nucleo.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
}
$pdo = ligacao(cfg());
if (!$pdo) {
    exit("Base de dados indisponível.\n");
}
$chave = definicao($pdo, 'migracoes_chave');
if (!$cli && (!is_string($_GET['chave'] ?? null) || !$chave || !hash_equals($chave, $_GET['chave']))) {
    http_response_code(403);
    exit("Acesso negado.\n");
}
$acao = $cli ? ($argv[1] ?? '') : (string) ($_GET['acao'] ?? '');
$id = $cli ? ($argv[2] ?? '') : (string) ($_GET['id'] ?? '');
if ($acao === 'reverter') {
    echo ($erro = reverter_migracao($pdo, $id)) ? $erro . "\n" : 'Migração ' . $id . " revertida.\n";
}
$aplicadas = migracoes_aplicadas($pdo);
foreach (lista_migracoes() as $m => $def) {
    echo (isset($aplicadas[$m]) ? '[aplicada ' . $aplicadas[$m]['aplicada'] . '] ' : '[por aplicar] ') . $m . ': ' . $def['descricao'] . "\n";
}
