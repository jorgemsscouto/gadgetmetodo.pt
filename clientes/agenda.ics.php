<?php
// Agenda das sessões das formações em iCalendar, para subscrever no Google Calendar (clientes/inc/agenda-ics.php).
// Só responde com a chave mostrada na área reservada (Configuração, Agenda Google das sessões).

declare(strict_types=1);

require __DIR__ . '/inc/nucleo.php';
require __DIR__ . '/inc/lembretes.php';
require __DIR__ . '/inc/agenda-ics.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
if (!bd()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Base de dados indisponível.\n");
}
$chave = $_GET['chave'] ?? '';
if (!is_string($chave) || !hash_equals(chave_agenda(), $chave)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Acesso negado.\n");
}
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="gadgetmetodo-formacoes.ics"');
echo conteudo_agenda();
