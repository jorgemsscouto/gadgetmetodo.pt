<?php
// Agenda das sessões em formato iCalendar (ICS), para subscrever no Google Calendar ("A partir de URL") ou noutra
// agenda: todas as sessões das edições não canceladas, dos últimos 6 meses em diante. O endereço tem uma chave própria
// (Configuração, Agenda Google); gerar uma chave nova invalida o endereço anterior. O Google atualiza as agendas
// subscritas sozinho, com algumas horas de atraso.

declare(strict_types=1);

function chave_agenda(bool $nova = false): string
{
    $chave = definicao(bd(), 'agenda_chave');
    if ($nova || !$chave) {
        $chave = bin2hex(random_bytes(20));
        definir(bd(), 'agenda_chave', $chave);
    }
    return $chave;
}

function endereco_agenda(): string
{
    return endereco_site() . '/clientes/agenda.ics.php?chave=' . chave_agenda();
}

// Texto de uma propriedade ICS (RFC 5545): barras, pontos e vírgulas, vírgulas e mudanças de linha escapados
function ics_texto(string $s): string
{
    return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\\,', '\\n', '\\n'], $s);
}

// Linhas com mais de 75 octetos dobradas (continuação começada por um espaço), sem cortar carateres UTF-8
function ics_linha(string $linha): string
{
    $r = '';
    $max = 75; // a 1.ª linha tem 75 octetos; as seguintes, 74 mais o espaço inicial
    while (strlen($linha) > $max) {
        $corte = $max;
        while ($corte > 0 && (ord($linha[$corte]) & 0xC0) === 0x80) {
            $corte--;
        }
        $r .= substr($linha, 0, $corte) . "\r\n ";
        $linha = substr($linha, $corte);
        $max = 74;
    }
    return $r . $linha . "\r\n";
}

// Data e hora de Lisboa em UTC ("20261207T183000Z")
function ics_utc(string $data): string
{
    return (new DateTime($data, new DateTimeZone('Europe/Lisbon')))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

// "Curso 1" a partir de "Curso 1: Iniciação à IA Generativa no Trabalho" (os outros títulos ficam inteiros)
function curso_curto(string $titulo): string
{
    return preg_match('/^(Curso\s+\d+)\s*:/u', $titulo, $m) ? $m[1] : $titulo;
}

function conteudo_agenda(): string
{
    $linhas = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Gadgetmetodo//Formacoes//PT', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
        'X-WR-CALNAME:' . ics_texto('Gadgetmetodo · Formações'), 'X-WR-TIMEZONE:Europe/Lisbon', 'X-PUBLISHED-TTL:PT1H', 'REFRESH-INTERVAL;VALUE=DURATION:PT1H'];
    $agora = gmdate('Ymd\THis\Z');
    $desde = date('Y-m-d 00:00:00', strtotime('-6 months'));
    foreach (consulta("SELECT * FROM formacoes WHERE estado != 'cancelada' AND COALESCE(fim, inicio) >= ? ORDER BY inicio", [$desde]) as $f) {
        $sessoes = sessoes_edicao($f);
        $confirmadas = (int) (linha_bd("SELECT COUNT(*) AS n FROM inscricoes WHERE formacao_id = ? AND estado IN ('inscrito', 'em_formacao', 'concluido', 'certificado', 'encerrado')", [$f['id']])['n'] ?? 0);
        $formador = !empty($f['formador_id']) ? (string) (linha_bd('SELECT nome FROM utilizadores WHERE id = ?', [(int) $f['formador_id']])['nome'] ?? '') : '';
        $online = ($f['modalidade'] ?? '') !== 'presencial' && !empty($f['reuniao']);
        foreach ($sessoes as $s) {
            $fim = !empty($s['fim']) ? $s['fim'] : date('Y-m-d H:i:s', strtotime($s['inicio']) + 3600);
            $resumo = curso_curto((string) $f['titulo']) . ($s['total'] > 1 ? ' · Sessão ' . $s['n'] . ' de ' . $s['total'] : '');
            $descricao = array_filter([
                (string) $f['titulo'],
                $s['nome'],
                modalidade_e_local($f),
                $formador !== '' ? 'Formador: ' . $formador : '',
                'Inscrições confirmadas: ' . $confirmadas . ' (vagas: ' . (int) $f['vagas'] . ')',
                $online ? 'Reunião online: ' . $f['reuniao'] : '',
                'Edição na área reservada: ' . endereco_site() . '/clientes/area.php?p=formacao&id=' . (int) $f['id'],
            ], 'strlen');
            $evento = ['BEGIN:VEVENT', 'UID:formacao-' . (int) $f['id'] . '-sessao-' . (int) $s['n'] . '@gadgetmetodo.pt', 'DTSTAMP:' . $agora,
                'DTSTART:' . ics_utc($s['inicio']), 'DTEND:' . ics_utc($fim), 'SUMMARY:' . ics_texto($resumo), 'DESCRIPTION:' . ics_texto(implode("\n", $descricao)),
                'STATUS:CONFIRMED', 'TRANSP:OPAQUE'];
            $local = trim((string) $f['local']) !== '' && ($f['modalidade'] ?? '') !== 'online' ? (string) $f['local'] : ($online ? (string) $f['reuniao'] : '');
            if ($local !== '') {
                $evento[] = 'LOCATION:' . ics_texto($local);
            }
            if ($online) {
                $evento[] = 'URL:' . $f['reuniao'];
            }
            $evento[] = 'END:VEVENT';
            $linhas = array_merge($linhas, $evento);
        }
    }
    $linhas[] = 'END:VCALENDAR';
    return implode('', array_map('ics_linha', $linhas));
}

// Bloco da Configuração: endereço da agenda, instruções para o Google Calendar e nova chave
function bloco_agenda_google(): string
{
    $endereco = endereco_agenda();
    $n = (int) (linha_bd("SELECT COUNT(*) AS n FROM formacoes WHERE estado != 'cancelada' AND COALESCE(fim, inicio) >= ?", [date('Y-m-d 00:00:00')])['n'] ?? 0);
    return '<section class="partilha espaco-topo" id="agenda">
      <h2 class="mt-0">Agenda Google das sessões</h2>
      <p>Todas as sessões das edições agendadas (não canceladas), dos últimos 6 meses em diante, num endereço que o Google Calendar subscreve: cada sessão aparece com o curso, o número da sessão, os temas, o local ou a ligação da reunião, o formador e as inscrições confirmadas. Edições futuras ou em curso: ' . $n . '.</p>
      <div class="partilha-ligacao"><label class="visualmente-oculto" for="endereco-agenda">Endereço da agenda</label><input id="endereco-agenda" type="text" readonly value="' . h($endereco) . '"><button class="botao botao-secundario" type="button" data-copiar="' . h($endereco) . '">Copiar endereço</button></div>
      <p class="estado-copia" role="status" aria-live="polite"></p>
      <h3>Como acrescentar ao Google Calendar (uma só vez)</h3>
      <ol class="lista-datas">
        <li>Copie o endereço acima.</li>
        <li>No computador, abra <a href="https://calendar.google.com/calendar/u/0/r/settings/addbyurl" target="_blank" rel="noopener">Google Calendar, Outros calendários, A partir do URL</a> com a conta onde quer as sessões.</li>
        <li>Cole o endereço e escolha "Adicionar calendário". A agenda "Gadgetmetodo · Formações" aparece em "Outros calendários" e chega também ao telemóvel.</li>
      </ol>
      <p class="texto-suave">O Google Calendar atualiza as agendas subscritas sozinho, normalmente várias vezes por dia: uma sessão nova, alterada ou cancelada pode demorar algumas horas a aparecer. A agenda só se lê (as alterações fazem-se aqui). O endereço tem uma chave própria; se for divulgado, gere uma nova chave e volte a acrescentar a agenda com o endereço novo (o anterior deixa de funcionar).</p>
      <form method="post">' . campo_csrf() . '<input type="hidden" name="acao" value="chave_agenda"><button class="botao botao-secundario" type="submit">Gerar nova chave</button></form>
    </section>';
}
