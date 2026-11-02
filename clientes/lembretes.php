<?php
// Tarefa agendada dos lembretes automáticos (1 hora antes de cada sessão). Deve correr a cada 5 a 15 minutos:
// pelo endereço com a chave mostrada na área reservada (Configuração, Lembretes automáticos) ou, na linha de
// comandos do alojamento, com "php clientes/lembretes.php" (sem chave).

declare(strict_types=1);

require __DIR__ . '/inc/nucleo.php';
require __DIR__ . '/inc/lembretes.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
}
if (!bd()) {
    if (!$cli) {
        http_response_code(503);
    }
    exit("Base de dados indisponível.\n");
}
$chave = $_GET['chave'] ?? '';
if (!$cli && (!is_string($chave) || !hash_equals(chave_lembretes(), $chave))) {
    http_response_code(403);
    exit("Acesso negado.\n");
}
definir(bd(), 'lembretes_tarefa', agora());
echo 'Lembretes enviados: ' . enviar_lembretes_automaticos('tarefa') . "\n";
